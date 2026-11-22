@php
    /** @var \App\Models\Plan $plan */
    $vpc = $plan->videosPerCycle();
    $apc = $plan->adsPerCycle();
    // [state, html]  state: yes = green tick, no = red cross, info = amber dot (a limit)
    $items = [
        ['yes', 'Daily <strong>'.$plan->daily_cycle_limit.'</strong> cycles · <strong>'.$plan->dailyVideos().'</strong> videos'],
        ['yes', 'Per cycle: '.$vpc.' videos + '.$apc.' ads'],
        ['yes', '<strong>'.e($plan->multiplierLabel()).'</strong> earning rate'],
        (float) $plan->daily_earning_cap > 0
            ? ['info', 'Up to '.e(money($plan->daily_earning_cap, 0)).' earning']
            : ['yes', 'No daily earning limit'],
    ];

    if (! $plan->withdrawals_enabled) {
        $items[] = ['no', '<strong>No withdrawal</strong> (upgrade required)'];
    } else {
        $items[] = ['yes', 'Withdrawal: min '.e(money($plan->min_withdrawal, 0))
            .((float) $plan->max_withdrawal > 0 ? ', max '.e(money($plan->max_withdrawal, 0)) : '')];
        $items[] = $plan->required_referrals > 0
            ? ['info', '<strong>'.$plan->required_referrals.'</strong> active invites required']
            : ['yes', 'No invite requirement'];
        $items[] = $plan->withdrawal_gap_days > 0
            ? ['info', 'Every <strong>'.$plan->withdrawal_gap_days.'</strong> day(s): 1 withdrawal']
            : ['yes', 'Withdraw anytime'];
    }
@endphp
<ul class="space-y-2.5 text-sm">
    @foreach ($items as [$state, $html])
        <li class="flex items-start gap-2">
            @if ($state === 'yes')
                <x-icon name="check" class="mt-0.5 size-4 shrink-0 text-emerald-400" stroke="3"/>
            @elseif ($state === 'no')
                <x-icon name="x" class="mt-0.5 size-4 shrink-0 text-rose-400" stroke="3"/>
            @else
                <span class="mt-1.5 ml-1 mr-1 size-2 shrink-0 rounded-full bg-amber-400"></span>
            @endif
            <span class="{{ $state === 'no' ? 'text-rose-200' : '' }}">{!! $html !!}</span>
        </li>
    @endforeach
</ul>
