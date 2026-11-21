<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\FraudLog;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $key = 'login:'.Str::lower($data['login']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 6)) {
            throw ValidationException::withMessages([
                'login' => 'Bohat zyada koshishen. '.RateLimiter::availableIn($key).' second baad dobara koshish karein.',
            ]);
        }

        $field = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $value = $field === 'phone' ? $this->normalizePhone($data['login']) : Str::lower($data['login']);

        if (! Auth::attempt([$field => $value, 'password' => $data['password']], true)) {
            RateLimiter::hit($key, 120);
            throw ValidationException::withMessages(['login' => 'Phone/email ya password ghalat hai.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        if ($request->user()->isBlocked()) {
            Auth::logout();
            throw ValidationException::withMessages(['login' => 'Aap ka account block hai.']);
        }

        return redirect()->intended($request->user()->isAdmin() ? route('admin.dashboard') : route('feed'));
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        if (setting('registration_open', '1') !== '1') {
            return back()->withErrors(['name' => 'Abhi naye account nahi ban sakte.']);
        }

        $request->merge(['phone' => $this->normalizePhone((string) $request->input('phone'))]);
        if ($request->filled('email')) {
            $request->merge(['email' => Str::lower($request->input('email'))]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'phone' => ['required', 'string', 'min:10', 'max:15', 'unique:users,phone'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(6)],
        ], [
            'phone.unique' => 'Is phone number se pehle hi account bana hua hai.',
            'email.unique' => 'Is email se pehle hi account bana hua hai.',
        ]);

        $ip = $request->ip();
        $maxPerIp = (int) setting('max_accounts_per_ip', 3);
        if ($maxPerIp > 0 && User::query()->where('register_ip', $ip)->count() >= $maxPerIp) {
            FraudLog::record(null, 'ip_limit', "Register blocked for {$data['phone']}", $ip);

            return back()->withInput()->withErrors(['phone' => 'Is network se mazeed accounts nahi ban sakte.']);
        }

        $user = User::query()->create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'password' => $data['password'],
            'register_ip' => $ip,
            'last_ip' => $ip,
        ]);

        $plan = Plan::defaultPlan();
        if ($plan->exists) {
            Subscription::query()->create([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'starts_at' => now(),
                'ends_at' => null,
                'status' => 'active',
            ]);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->route('feed')->with('success', 'Khush aamdeed! Videos dekhna shuru karein aur kamaayein.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /** 0300-1234567 / +92 300 1234567 / 923001234567 → 03001234567 */
    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (str_starts_with($digits, '92') && strlen($digits) === 12) {
            $digits = '0'.substr($digits, 2);
        }

        return $digits;
    }
}
