<?php

namespace App\Http\Controllers\SubAdmin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $me = $request->user();
        $base = fn () => User::query()->where('sub_admin_id', $me->id)->where('role', 'user');

        $total = $base()->count();
        $activeToday = $base()->where('last_active_at', '>=', today())->count();

        // Per paid plan: distinct network users with an active subscription to that plan.
        $perPlan = Subscription::query()
            ->whereIn('user_id', $base()->select('id'))
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->whereHas('plan', fn ($q) => $q->where('price', '>', 0))
            ->selectRaw('plan_id, COUNT(DISTINCT user_id) as c')
            ->groupBy('plan_id')
            ->pluck('c', 'plan_id');

        $paid = (int) $perPlan->sum();
        $free = max(0, $total - $paid);

        $planRows = Plan::query()->where('price', '>', 0)->orderBy('sort_order')->orderBy('price')->get()
            ->map(fn (Plan $p) => ['name' => $p->name, 'color' => $p->color, 'count' => (int) ($perPlan[$p->id] ?? 0)]);

        $levels = $me->downlineCounts(3);

        return view('subadmin.dashboard', [
            'me' => $me,
            'total' => $total,
            'activeToday' => $activeToday,
            'paid' => $paid,
            'free' => $free,
            'planRows' => $planRows,
            'levels' => $levels,
            'networkEarned' => (float) $base()->sum('total_earned'),
            'networkBalance' => (float) $base()->sum('balance'),
            'recent' => $base()->with('referrer:id,name')->latest('id')->limit(8)->get(),
        ]);
    }
}
