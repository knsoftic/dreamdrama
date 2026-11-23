<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SubAdminController extends Controller
{
    public function index(Request $request)
    {
        $subAdmins = User::query()
            ->where('role', 'sub_admin')
            ->withCount('networkUsers')
            ->withSum(['deposits as deposits_gross' => fn ($q) => $q->where('status', 'approved')], 'amount')
            ->when($request->query('q'), fn ($query, $s) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")->orWhere('referral_code', strtoupper($s))))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.subadmins.index', ['subAdmins' => $subAdmins]);
    }

    public function create()
    {
        return view('admin.subadmins.form');
    }

    /** Create a brand-new sub-admin account. */
    public function store(Request $request)
    {
        $request->merge([
            'phone' => $this->normalizePhone((string) $request->input('phone')),
            'referral_code' => strtoupper(trim((string) $request->input('referral_code'))),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'phone' => ['required', 'string', 'min:10', 'max:15', 'unique:users,phone'],
            'password' => ['required', 'confirmed', Password::min(6)],
            'referral_code' => ['required', 'string', 'regex:/^[A-Z0-9]{4,12}$/', 'unique:users,referral_code'],
            'commission_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ], ['referral_code.regex' => 'The code must be 4–12 letters/numbers.']);

        $user = new User([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'password' => $data['password'],
        ]);
        $user->referral_code = $data['referral_code'];
        $user->role = 'sub_admin';
        $user->status = 'active';
        $user->commission_percent = $data['commission_percent'];
        $user->save();

        return redirect()->route('admin.subadmins.index')->with('success', "Sub-admin created with invite code {$data['referral_code']}.");
    }

    /** Promote an existing user to sub-admin and move their downline under them. */
    public function promote(Request $request)
    {
        $request->merge([
            'phone' => $this->normalizePhone((string) $request->input('phone')),
            'referral_code' => strtoupper(trim((string) $request->input('referral_code'))),
        ]);
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'referral_code' => ['required', 'string', 'regex:/^[A-Z0-9]{4,12}$/'],
            'commission_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ], ['referral_code.regex' => 'The code must be 4–12 letters/numbers.']);

        $user = User::query()->where('phone', $data['phone'])->first();
        if (! $user) {
            return back()->withErrors(['phone' => 'No user found with that phone number.'])->withInput();
        }
        if ($user->isStaff()) {
            return back()->withErrors(['phone' => 'This account is already an admin or sub-admin.'])->withInput();
        }
        if (User::query()->where('referral_code', $data['referral_code'])->where('id', '!=', $user->id)->exists()) {
            return back()->withErrors(['referral_code' => 'That code is already in use.'])->withInput();
        }

        DB::transaction(function () use ($user, $data) {
            $user->role = 'sub_admin';
            $user->sub_admin_id = null;
            $user->referral_code = $data['referral_code'];
            $user->commission_percent = $data['commission_percent'];
            $user->save();
            $this->reassignDownline($user);
        });

        return redirect()->route('admin.subadmins.index')->with('success', "{$user->name} is now a sub-admin (code {$data['referral_code']}).");
    }

    public function updateCode(Request $request, User $user)
    {
        abort_unless($user->isSubAdmin(), 404);
        $request->merge(['referral_code' => strtoupper(trim((string) $request->input('referral_code')))]);
        $data = $request->validate([
            'referral_code' => ['required', 'string', 'regex:/^[A-Z0-9]{4,12}$/', Rule::unique('users', 'referral_code')->ignore($user->id)],
        ], ['referral_code.regex' => 'The code must be 4–12 letters/numbers.']);

        $user->update(['referral_code' => $data['referral_code']]);

        return back()->with('success', 'Invite code updated.');
    }

    public function updateCommission(Request $request, User $user)
    {
        abort_unless($user->isSubAdmin(), 404);
        $data = $request->validate(['commission_percent' => ['required', 'numeric', 'min:0', 'max:100']]);
        $user->update(['commission_percent' => $data['commission_percent']]);

        return back()->with('success', 'Tax % updated. It applies to new deposits only — past deposits keep their rate.');
    }

    public function updateName(Request $request, User $user)
    {
        abort_unless($user->isSubAdmin(), 404);
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);
        $user->update(['name' => $data['name']]);

        return back()->with('success', 'Name updated — iske saare users ko naya naam dikhega.');
    }

    public function toggleBlock(User $user)
    {
        abort_unless($user->isSubAdmin(), 404);
        $user->update(['status' => $user->isBlocked() ? 'active' : 'blocked']);

        return back()->with('success', $user->isBlocked() ? 'Sub-admin blocked.' : 'Sub-admin unblocked.');
    }

    /** Turn a sub-admin back into a normal user (their network keeps its owner). */
    public function demote(User $user)
    {
        abort_unless($user->isSubAdmin(), 404);
        $user->update(['role' => 'user']);

        return back()->with('success', "{$user->name} is a normal user again. Their network is unchanged.");
    }

    /** Point every user in this person's invite subtree at them as the owning sub-admin. */
    private function reassignDownline(User $subAdmin): void
    {
        $parents = [$subAdmin->id];
        $guard = 0;
        while ($parents && $guard++ < 50) {
            $childIds = User::query()->whereIn('referred_by', $parents)->pluck('id')->all();
            if (! $childIds) {
                break;
            }
            User::query()->whereIn('id', $childIds)->update(['sub_admin_id' => $subAdmin->id]);
            $parents = $childIds;
        }
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (str_starts_with($digits, '92') && strlen($digits) === 12) {
            $digits = '0'.substr($digits, 2);
        }

        return $digits;
    }
}
