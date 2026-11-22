@extends('layouts.app')
@section('title', 'Home')
@section('fullscreen', '1')

@php
    $config = [
        'state' => $state,
        'heartbeat' => (int) setting('heartbeat_seconds', 5),
        'autoNext' => setting('auto_next_video', '1') === '1',
        'ratePerSecond' => (float) setting('rate_per_second', 0),
        'csrf' => csrf_token(),
        'routes' => [
            'feed' => route('app.feed'),
            'start' => route('app.watch.start'),
            'heartbeat' => route('app.watch.heartbeat'),
            'end' => route('app.watch.end'),
            'adStart' => route('app.ad.start'),
            'adComplete' => route('app.ad.complete'),
            'login' => route('login'),
        ],
    ];
@endphp

@section('content')
<div x-data="feed(@js($config))" class="lg:flex lg:h-dvh lg:gap-6 lg:p-6">

    {{-- ================= Video column ================= --}}
    <section class="fixed inset-x-0 top-0 bottom-[calc(4rem+env(safe-area-inset-bottom))] z-50 bg-black lg:static lg:z-auto lg:flex lg:h-full lg:flex-1 lg:justify-center lg:bg-transparent">
        <div class="relative h-full w-full overflow-hidden bg-black lg:aspect-[9/16] lg:w-auto lg:rounded-[28px] lg:shadow-2xl lg:shadow-brand/10 lg:ring-1 lg:ring-app-line">

            {{-- Slides --}}
            <div x-ref="scroller" class="no-scrollbar h-full snap-y snap-mandatory overflow-y-scroll overscroll-contain">
                <template x-for="(v, i) in videos" :key="v.uid">
                    <div class="feed-slide relative h-full w-full snap-start snap-always overflow-hidden" :data-index="i">
                        <video class="absolute inset-0 h-full w-full"
                               :class="v.landscape ? 'object-contain' : 'object-cover'"
                               :src="v.url" :poster="v.thumbnail || ''"
                               :preload="Math.abs(i - active) <= 1 ? 'auto' : 'none'"
                               playsinline webkit-playsinline muted disablepictureinpicture
                               @loadedmetadata="v.landscape = $event.target.videoWidth > $event.target.videoHeight"
                               @play="onPlay(i)" @pause="onPause(i)" @ended="onEnded(i)" @timeupdate="onTime(i, $event)"></video>

                        <div class="pointer-events-none absolute inset-x-0 top-0 h-36 bg-gradient-to-b from-black/60 to-transparent"></div>
                        <div class="pointer-events-none absolute inset-x-0 bottom-0 h-64 bg-gradient-to-t from-black/85 via-black/30 to-transparent"></div>

                        {{-- Tap to play / pause --}}
                        <div class="absolute inset-0" @click="togglePlay()"></div>

                        <div x-show="paused && active === i && !ad.open" x-transition.opacity
                             class="pointer-events-none absolute inset-0 grid place-items-center">
                            <span class="grid size-20 place-items-center rounded-full bg-black/40 backdrop-blur-sm">
                                <x-icon name="play" class="ml-1 size-9 text-white" solid/>
                            </span>
                        </div>

                        {{-- Video info --}}
                        <div class="pointer-events-none absolute inset-x-0 bottom-0 p-4 pr-20 pb-6">
                            <div class="mb-2 flex flex-wrap gap-1.5">
                                <span x-show="v.category" x-text="v.category" class="chip bg-white/15 backdrop-blur"></span>
                                <span x-show="v.reason === 'already_earned' || (v.earned && !v.reason)" class="chip bg-emerald-500/20 text-emerald-300 backdrop-blur">
                                    <x-icon name="check" class="size-3.5" stroke="3"/> Earned
                                </span>
                                <span x-show="v.reason === 'limit_reached'" class="chip bg-amber-500/20 text-amber-300 backdrop-blur">Daily limit</span>
                            </div>
                            <h2 class="text-base font-bold leading-snug drop-shadow" x-text="v.title"></h2>
                            <p class="mt-1 line-clamp-2 text-sm text-white/80" x-show="v.description" x-text="v.description"></p>
                        </div>

                        {{-- Action rail --}}
                        <div class="absolute right-3 bottom-8 flex flex-col items-center gap-5">
                            <button @click.stop="setMuted(!muted)" class="grid size-11 place-items-center rounded-full bg-black/40 backdrop-blur active:scale-95" aria-label="Sound">
                                <span x-show="muted"><x-icon name="mute" class="size-6"/></span>
                                <span x-show="!muted"><x-icon name="volume" class="size-6"/></span>
                            </button>
                            <button @click.stop="navigator.share ? navigator.share({ title: v.title, url: location.origin }) : null"
                                    class="grid size-11 place-items-center rounded-full bg-black/40 backdrop-blur active:scale-95" aria-label="Share">
                                <x-icon name="share" class="size-6"/>
                            </button>
                            <div class="flex flex-col items-center">
                                <x-icon name="eye" class="size-6 drop-shadow"/>
                                <span class="mt-0.5 text-[11px] font-semibold" x-text="v.views"></span>
                            </div>
                        </div>

                        {{-- Watch progress --}}
                        <div class="absolute inset-x-0 bottom-0 h-[3px] bg-white/15">
                            <div class="h-full grad transition-[width] duration-300" :style="`width:${v.progress}%`"></div>
                        </div>
                    </div>
                </template>

                {{-- Loading / empty --}}
                <div x-show="!videos.length" class="grid h-full place-items-center p-8 text-center">
                    <div x-show="loading || !empty" class="size-10 animate-spin rounded-full border-4 border-white/15 border-t-brand"></div>
                    <div x-show="empty && !loading" x-cloak>
                        <x-icon name="film" class="mx-auto size-12 text-app-muted"/>
                        <p class="mt-3 font-semibold">No video available yet</p>
                        <p class="mt-1 text-sm text-app-muted">Check again shortly.</p>
                    </div>
                </div>
            </div>

            {{-- Top overlay: cycle progress + balance --}}
            <div class="pointer-events-none absolute inset-x-0 top-0 z-10 p-3 safe-top">
                <div class="flex items-start justify-between gap-2">
                    <a href="{{ route('earnings') }}" class="pointer-events-auto rounded-2xl bg-black/45 px-3 py-2 backdrop-blur-md ring-1 ring-white/10">
                        <div class="flex items-center gap-3 text-[11px] font-semibold">
                            <span class="flex items-center gap-1"><x-icon name="film" class="size-3.5 text-brand"/>
                                <span x-text="`${cycle.videos_watched}/${cycle.videos_required}`"></span></span>
                            <span class="flex items-center gap-1"><x-icon name="megaphone" class="size-3.5 text-brand-2"/>
                                <span x-text="`${cycle.ads_watched}/${cycle.ads_required}`"></span></span>
                            <span class="text-coin" x-text="'+' + money(pendingLive, 3)"></span>
                        </div>
                        <div class="mt-1.5 flex gap-1">
                            <div class="h-1 w-16 overflow-hidden rounded-full bg-white/15"><div class="h-full bg-brand transition-all" :style="`width:${videoPct}%`"></div></div>
                            <div class="h-1 w-16 overflow-hidden rounded-full bg-white/15"><div class="h-full bg-brand-2 transition-all" :style="`width:${adPct}%`"></div></div>
                        </div>
                    </a>
                    <a href="{{ route('wallet') }}" class="pointer-events-auto chip bg-black/45 py-2 text-coin ring-1 ring-white/10 backdrop-blur-md lg:hidden">
                        <x-icon name="coin" class="size-4"/> <span x-text="money(state.balance)"></span>
                    </a>
                </div>

                <a x-show="state.limit_reached" x-cloak href="{{ route('plans') }}"
                   class="pointer-events-auto mt-2 flex items-center justify-between gap-2 rounded-2xl bg-amber-500/90 px-3 py-2 text-xs font-semibold text-black">
                    <span>Today's limit reached (<span x-text="`${state.cycles_today}/${state.cycle_limit}`"></span> cycles). Upgrade to earn more</span>
                    <x-icon name="chevron-right" class="size-4 shrink-0" stroke="2.5"/>
                </a>

                <button x-show="soundHint && muted && videos.length" x-cloak @click="setMuted(false)"
                        class="pointer-events-auto mx-auto mt-3 flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-xs font-bold text-black shadow-lg">
                    <x-icon name="mute" class="size-4"/> Tap for sound
                </button>

                {{-- +Rs toasts --}}
                <div class="absolute right-3 top-14 flex flex-col items-end gap-1">
                    <template x-for="t in toasts" :key="t.id">
                        <div class="animate-float-up chip shadow-lg"
                             :class="t.kind === 'ad' ? 'bg-brand-2 text-white' : (t.kind === 'warn' ? 'bg-amber-400 text-black' : 'bg-emerald-500 text-white')"
                             x-text="t.text"></div>
                    </template>
                </div>
            </div>

            {{-- ================= Ad break ================= --}}
            <div x-show="ad.open" x-cloak x-transition.opacity
                 class="fixed inset-0 z-[60] flex flex-col bg-app-bg/95 backdrop-blur-xl lg:absolute">
                <div class="flex items-center justify-between p-4 safe-top">
                    <div>
                        <div class="flex items-center gap-2 text-sm font-bold">
                            <span class="grad grid size-7 place-items-center rounded-lg"><x-icon name="megaphone" class="size-4"/></span>
                            Ad Break
                        </div>
                        <div class="mt-0.5 text-xs text-app-muted">Ad <span x-text="ad.slot"></span> / <span x-text="ad.total"></span> · to complete the cycle</div>
                    </div>
                    <span class="chip bg-coin/15 text-coin" x-text="'+' + money(ad.reward, 3)"></span>
                </div>

                <div class="flex gap-1 px-4">
                    <template x-for="n in ad.total" :key="n">
                        <div class="h-1 flex-1 rounded-full" :class="n < ad.slot ? 'bg-brand-2' : (n === ad.slot ? 'bg-white/60' : 'bg-white/15')"></div>
                    </template>
                </div>

                <div class="relative m-4 flex-1 overflow-hidden rounded-2xl border border-app-line bg-app-card">
                    <template x-if="ad.frame">
                        <iframe :key="ad.frameKey" :src="ad.frame" class="absolute inset-0 h-full w-full" title="Advertisement"
                                sandbox="allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox allow-forms"></iframe>
                    </template>
                    <div x-show="!ad.frame" class="absolute inset-0 grid place-items-center p-6 text-center">
                        <div x-show="ad.loading" class="size-10 animate-spin rounded-full border-4 border-white/15 border-t-brand"></div>
                        <div x-show="!ad.loading">
                            <x-icon name="sparkles" class="mx-auto size-10 text-brand"/>
                            <p class="mt-3 font-semibold">Advertisement</p>
                            <p class="mt-1 text-xs text-app-muted">Add your ad code in the admin panel</p>
                        </div>
                    </div>
                </div>

                <div class="p-4 pt-0 safe-bottom">
                    <button @click="claimAd()" :disabled="!ad.ready || ad.claiming"
                            class="btn-primary w-full !py-4 text-base">
                        <template x-if="!ad.ready">
                            <span class="flex items-center gap-2"><x-icon name="clock" class="size-5"/> <span x-text="`${ad.seconds} seconds left...`"></span></span>
                        </template>
                        <template x-if="ad.ready">
                            <span x-text="ad.claiming ? 'Please wait...' : `Claim ${money(ad.reward, 3)} & Continue`"></span>
                        </template>
                    </button>
                    <p class="mt-2 text-center text-[11px] text-app-muted">Do not close this page during the ad. The cycle earning goes to your wallet once the ads are done.</p>
                </div>
            </div>

            {{-- ================= Cycle complete ================= --}}
            <div x-show="celebrate" x-cloak x-transition.opacity class="fixed inset-0 z-[65] grid place-items-center bg-black/70 p-6 backdrop-blur-sm lg:absolute">
                <div class="w-full max-w-xs animate-pop-in rounded-3xl border border-app-line bg-app-card p-6 text-center">
                    <div class="grad mx-auto grid size-16 place-items-center rounded-2xl shadow-lg shadow-brand/40"><x-icon name="trophy" class="size-8"/></div>
                    <h3 class="mt-4 text-xl font-extrabold">Cycle Complete!</h3>
                    <p class="mt-1 text-sm text-app-muted">Your earnings were added to the wallet</p>
                    <div class="mt-4 text-3xl font-extrabold text-coin" x-text="'+' + money(celebrate?.amount, 2)"></div>
                    <button @click="celebrate = null" class="btn-primary mt-6 w-full">Watch more videos</button>
                </div>
            </div>
        </div>
    </section>

    {{-- ================= Desktop side panel ================= --}}
    <aside class="no-scrollbar hidden w-[340px] shrink-0 flex-col gap-4 overflow-y-auto lg:flex">
        <div class="rounded-3xl grad p-5 shadow-lg shadow-brand/20">
            <div class="text-sm font-medium text-white/80">Wallet balance</div>
            <div class="mt-1 text-3xl font-extrabold" x-text="money(state.balance)"></div>
            <div class="mt-3 flex items-center justify-between text-sm">
                <span class="text-white/80">Today's earning</span>
                <span class="font-bold" x-text="money(state.today_earning, 3)"></span>
            </div>
        </div>

        <div class="card p-5">
            <div class="flex items-center justify-between">
                <h3 class="font-bold">Current cycle</h3>
                <span class="chip bg-coin/15 text-coin" x-text="'+' + money(pendingLive, 3)"></span>
            </div>
            <div class="mt-4 space-y-4">
                <div>
                    <div class="mb-1.5 flex justify-between text-sm"><span class="flex items-center gap-1.5 text-app-muted"><x-icon name="film" class="size-4 text-brand"/> Videos</span><span class="font-semibold" x-text="`${cycle.videos_watched} / ${cycle.videos_required}`"></span></div>
                    <div class="h-2 overflow-hidden rounded-full bg-white/10"><div class="h-full rounded-full bg-brand transition-all" :style="`width:${videoPct}%`"></div></div>
                </div>
                <div>
                    <div class="mb-1.5 flex justify-between text-sm"><span class="flex items-center gap-1.5 text-app-muted"><x-icon name="megaphone" class="size-4 text-brand-2"/> Ads</span><span class="font-semibold" x-text="`${cycle.ads_watched} / ${cycle.ads_required}`"></span></div>
                    <div class="h-2 overflow-hidden rounded-full bg-white/10"><div class="h-full rounded-full bg-brand-2 transition-all" :style="`width:${adPct}%`"></div></div>
                </div>
            </div>
            <p class="mt-4 text-xs leading-relaxed text-app-muted">Earnings are added to the wallet once all videos and ads of the cycle are done.</p>
        </div>

        <div class="card p-5">
            <div class="flex items-center justify-between">
                <div>
                    <div class="text-xs text-app-muted">Your plan</div>
                    <div class="font-bold" x-text="state.plan.name"></div>
                </div>
                <a href="{{ route('plans') }}" class="btn-ghost !px-3 !py-2 text-xs"><x-icon name="crown" class="size-4 text-coin"/> Upgrade</a>
            </div>
            <div class="mt-4 flex justify-between text-sm">
                <span class="text-app-muted">Cycles today</span>
                <span class="font-semibold" x-text="`${state.cycles_today} / ${state.cycle_limit}`"></span>
            </div>
        </div>

        <x-ad-banner :unit="$banner"/>

        <div class="card p-5 text-sm">
            <h3 class="font-bold">How to earn?</h3>
            <ol class="mt-3 space-y-2 text-app-muted">
                <li class="flex gap-2"><span class="font-bold text-brand">1.</span> Watch videos fully — earn every second</li>
                <li class="flex gap-2"><span class="font-bold text-brand">2.</span> Watch ads in the ad break — fixed earning per ad</li>
                <li class="flex gap-2"><span class="font-bold text-brand">3.</span> Money to your wallet when the cycle completes</li>
                <li class="flex gap-2"><span class="font-bold text-brand">4.</span> Withdraw to JazzCash / Easypaisa from your wallet</li>
            </ol>
        </div>
    </aside>
</div>
@endsection
