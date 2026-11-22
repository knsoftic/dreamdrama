<?php

namespace App\Http\Controllers\SubAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $me = $request->user();
        $plan = $request->query('plan');     // free | paid
        $status = $request->query('status'); // active | blocked

        $users = User::query()
            ->where('sub_admin_id', $me->id)
            ->where('role', 'user')
            ->with('referrer:id,name')
            ->when($request->query('q'), fn ($query, $s) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")))
            ->when($status === 'blocked', fn ($q) => $q->where('status', 'blocked'))
            ->when($status === 'active', fn ($q) => $q->where('status', 'active'))
            ->when($plan === 'paid', fn ($q) => $q->whereHas('subscriptions', $this->activePaid()))
            ->when($plan === 'free', fn ($q) => $q->whereDoesntHave('subscriptions', $this->activePaid()))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('subadmin.users', [
            'users' => $users,
            'plan' => $plan,
            'status' => $status,
        ]);
    }

    public function toggleBlock(Request $request, User $user)
    {
        $this->authorizeOwn($request, $user);
        $user->update(['status' => $user->isBlocked() ? 'active' : 'blocked']);

        return back()->with('success', $user->isBlocked() ? 'User blocked.' : 'User unblocked.');
    }

    private function activePaid(): \Closure
    {
        return fn ($q) => $q->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->whereHas('plan', fn ($q) => $q->where('price', '>', 0));
    }

    /** A sub-admin may only act on users in their own network. */
    private function authorizeOwn(Request $request, User $user): void
    {
        abort_unless($user->sub_admin_id === $request->user()->id && $user->role === 'user', 403);
    }
}
