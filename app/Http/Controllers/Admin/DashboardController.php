<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdView;
use App\Models\Cycle;
use App\Models\Payment;
use App\Models\RevenueEntry;
use App\Models\User;
use App\Models\Video;
use App\Models\WatchSession;
use App\Models\Withdrawal;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $today = today();

        $stats = [
            'users' => User::query()->where('role', 'user')->count(),
            'new_users' => User::query()->where('role', 'user')->where('created_at', '>=', $today)->count(),
            'active_today' => User::query()->where('role', 'user')->where('last_active_at', '>=', $today)->count(),
            'videos' => Video::query()->count(),
            'views_today' => WatchSession::query()->where('created_at', '>=', $today)->count(),
            'ads_today' => AdView::query()->where('status', 'completed')->where('completed_at', '>=', $today)->count(),
            'cycles_today' => Cycle::query()->where('status', 'completed')->where('completed_at', '>=', $today)->count(),
            'paid_out_today' => (float) Cycle::query()->where('status', 'completed')->where('completed_at', '>=', $today)->sum('credited_amount'),
            'wallet_liability' => (float) User::query()->sum('balance'),
            'pending_withdrawals' => Withdrawal::query()->where('status', 'pending')->count(),
            'pending_withdrawal_amount' => (float) Withdrawal::query()->where('status', 'pending')->sum('amount'),
            'pending_payments' => Payment::query()->where('status', 'pending')->count(),
            'flagged' => User::query()->where('is_flagged', true)->count(),
        ];

        // Revenue (entered by admin from ad network reports) vs earning credited to users.
        $from = $today->copy()->subDays(13);
        $revenue = RevenueEntry::query()->where('date', '>=', $from)
            ->selectRaw('DATE(date) as d, SUM(amount) as total')->groupBy('d')->pluck('total', 'd');
        $payout = Cycle::query()->where('status', 'completed')->where('completed_at', '>=', $from)
            ->selectRaw('DATE(completed_at) as d, SUM(credited_amount) as total')->groupBy('d')->pluck('total', 'd');
        $ads = AdView::query()->where('status', 'completed')->where('completed_at', '>=', $from)
            ->selectRaw('DATE(completed_at) as d, COUNT(*) as total')->groupBy('d')->pluck('total', 'd');

        $days = collect(range(13, 0))->map(function ($i) use ($today, $revenue, $payout, $ads) {
            $d = $today->copy()->subDays($i)->toDateString();
            $rev = (float) ($revenue[$d] ?? 0);
            $pay = (float) ($payout[$d] ?? 0);

            return ['date' => $d, 'revenue' => $rev, 'payout' => $pay, 'ads' => (int) ($ads[$d] ?? 0), 'profit' => $rev - $pay];
        });

        return view('admin.dashboard', [
            'stats' => $stats,
            'days' => $days,
            'totals' => [
                'revenue' => $days->sum('revenue'),
                'payout' => $days->sum('payout'),
            ],
            'latestWithdrawals' => Withdrawal::query()->with('user')->where('status', 'pending')->latest()->limit(5)->get(),
            'latestUsers' => User::query()->where('role', 'user')->latest()->limit(5)->get(),
        ]);
    }
}
