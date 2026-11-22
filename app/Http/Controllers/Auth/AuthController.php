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
                'login' => 'Too many attempts. Try again in '.RateLimiter::availableIn($key).' seconds.',
            ]);
        }

        $field = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $value = $field === 'phone' ? $this->normalizePhone($data['login']) : Str::lower($data['login']);

        if (! Auth::attempt([$field => $value, 'password' => $data['password']], true)) {
            RateLimiter::hit($key, 120);
            throw ValidationException::withMessages(['login' => 'Phone/email or password is incorrect.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        if ($request->user()->isBlocked()) {
            Auth::logout();
            throw ValidationException::withMessages(['login' => 'Your account is blocked.']);
        }

        return redirect()->intended($this->homeFor($request->user()));
    }

    /** The landing route for a user after login, by role. */
    public static function homeFor(User $user): string
    {
        return match (true) {
            $user->isAdmin() => route('admin.dashboard'),
            $user->isSubAdmin() => route('team.dashboard'),
            default => route('feed'),
        };
    }

    public function showRegister(Request $request)
    {
        $ref = strtoupper((string) ($request->query('ref') ?: $request->session()->get('ref', '')));

        return view('auth.register', [
            'ref' => $ref,
            'inviter' => $ref ? User::query()->where('referral_code', $ref)->value('name') : null,
        ]);
    }

    public function register(Request $request)
    {
        if (setting('registration_open', '1') !== '1') {
            return back()->withErrors(['name' => 'New accounts cannot be created right now.']);
        }

        $request->merge(['phone' => $this->normalizePhone((string) $request->input('phone'))]);
        if ($request->filled('email')) {
            $request->merge(['email' => Str::lower($request->input('email'))]);
        }
        if ($request->filled('ref')) {
            $request->merge(['ref' => strtoupper(trim((string) $request->input('ref')))]);
        }

        $codeRequired = setting('require_invite_code', '1') === '1';

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'phone' => ['required', 'string', 'min:10', 'max:15', 'unique:users,phone'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(6)],
            'ref' => [$codeRequired ? 'required' : 'nullable', 'string', 'max:12', 'exists:users,referral_code'],
        ], [
            'phone.unique' => 'An account already exists with this phone number.',
            'email.unique' => 'An account already exists with this email.',
            'ref.required' => 'An invite code is required to register.',
            'ref.exists' => 'This invite code is invalid.'.($codeRequired ? '' : ' Check it or leave it empty.'),
        ]);

        $ip = $request->ip();
        $maxPerIp = (int) setting('max_accounts_per_ip', 3);
        if ($maxPerIp > 0 && User::query()->where('register_ip', $ip)->count() >= $maxPerIp) {
            FraudLog::record(null, 'ip_limit', "Register blocked for {$data['phone']}", $ip);

            return back()->withInput()->withErrors(['phone' => 'No more accounts can be created from this network.']);
        }

        $inviter = ! empty($data['ref']) ? User::query()->where('referral_code', $data['ref'])->first() : null;

        $user = new User([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'password' => $data['password'],
            'register_ip' => $ip,
            'last_ip' => $ip,
        ]);
        $user->referred_by = $inviter?->id;
        // Which sub-admin owns this user's network: the inviter if they are a
        // sub-admin, otherwise whoever owns the inviter's network.
        $user->sub_admin_id = $inviter
            ? ($inviter->isSubAdmin() ? $inviter->id : $inviter->sub_admin_id)
            : null;
        $user->save();

        if ($inviter && $inviter->register_ip === $ip) {
            FraudLog::record($user->id, 'self_referral', "Invited by #{$inviter->id} {$inviter->name} from the same IP", $ip);
        }
        $request->session()->forget('ref');

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

        return redirect()->route('feed')->with('success', 'Welcome! Start watching videos and earning.');
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
