import axios from 'axios';

/**
 * Vertical "shorts" feed.
 *
 * - One slide per video, CSS scroll-snap, the slide that is >60% visible plays.
 * - Watch time is reported with heartbeats; the server decides what counts.
 * - When the server says ads are due, a full-screen ad break opens and the
 *   user has to sit through each ad's countdown before claiming it.
 */
export default function feed(config) {
    return {
        videos: [],
        active: -1,
        state: config.state,
        loading: false,
        empty: false,
        muted: true,
        soundHint: true,
        paused: false,
        userPaused: false,
        session: null,
        liveSeconds: 0,
        toasts: [],
        celebrate: null,
        afterAd: null,
        ad: { open: false, loading: false, id: null, frame: null, frameKey: 0, seconds: 0, total: 0, slot: 0, reward: 0, ready: false, claiming: false },
        adTimer: null,
        queue: Promise.resolve(),
        uid: 0,

        async init() {
            this.observer = new IntersectionObserver((entries) => {
                entries.forEach((e) => {
                    if (e.isIntersecting && e.intersectionRatio >= 0.6) {
                        this.setActive(Number(e.target.dataset.index));
                    }
                });
            }, { root: this.$refs.scroller, threshold: [0.6] });

            document.addEventListener('visibilitychange', () => this.onVisibility());
            window.addEventListener('pagehide', () => this.beaconEnd());
            setInterval(() => this.tick(), config.heartbeat * 1000);

            await this.load();
            if (this.state.ads_due > 0) {
                this.openAdBreak();
            }
        },

        /* ---------------- data ---------------- */

        async load() {
            if (this.loading) return;
            this.loading = true;
            try {
                const exclude = [...new Set(this.videos.map((v) => v.id))].join(',');
                let { data } = await axios.get(config.routes.feed, { params: { exclude } });

                // Watched everything once: start the list again.
                if (!data.videos.length && this.videos.length) {
                    ({ data } = await axios.get(config.routes.feed));
                }

                this.state = data.state;
                this.empty = !data.videos.length && !this.videos.length;
                data.videos.forEach((v) => this.videos.push({ ...v, uid: ++this.uid, progress: 0, reason: null }));

                await this.$nextTick();
                this.$refs.scroller.querySelectorAll('.feed-slide:not([data-observed])').forEach((el) => {
                    el.dataset.observed = '1';
                    this.observer.observe(el);
                });
            } catch (e) {
                this.toast('Internet check karein', 'warn');
            } finally {
                this.loading = false;
            }
        },

        videoEl(i) {
            return this.$refs.scroller?.querySelector(`.feed-slide[data-index="${i}"] video`);
        },

        get current() {
            return this.videos[this.active] || null;
        },

        get cycle() {
            return this.state.cycle;
        },

        get videoPct() {
            return Math.min(100, Math.round((100 * this.cycle.videos_watched) / Math.max(1, this.cycle.videos_required)));
        },

        get adPct() {
            if (!this.cycle.ads_required) return 100;
            return Math.min(100, Math.round((100 * this.cycle.ads_watched) / this.cycle.ads_required));
        },

        /** Cycle earning so far + an estimate for the video playing now. */
        get pendingLive() {
            const live = this.session?.earning ? this.liveSeconds * config.ratePerSecond * this.state.plan.multiplier : 0;
            return this.cycle.total + live;
        },

        /* ---------------- playback ---------------- */

        setActive(i) {
            if (i === this.active) return;
            const prev = this.active;
            this.active = i;
            this.liveSeconds = 0;
            this.userPaused = false;

            const prevEl = this.videoEl(prev);
            if (prevEl) {
                prevEl.pause();
                prevEl.currentTime = 0;
            }

            this.enqueue(() => this.endSession());
            this.playActive();

            if (i >= this.videos.length - 3) this.load();
        },

        async playActive() {
            if (this.ad.open) return;
            const el = this.videoEl(this.active);
            if (!el) return;
            el.muted = this.muted;
            try {
                await el.play();
            } catch (e) {
                if (!el.muted) {
                    // Browser blocked sound autoplay: play muted and ask for a tap.
                    this.muted = true;
                    this.soundHint = true;
                    el.muted = true;
                    try { await el.play(); } catch (e2) { this.paused = true; }
                } else {
                    this.paused = true;
                }
            }
        },

        onPlay(i) {
            if (i !== this.active) return;
            this.paused = false;
            const v = this.videos[i];

            this.enqueue(async () => {
                if (this.session && this.session.uid === v.uid) {
                    if (!this.session.playing) {
                        this.session.playing = true;
                        await this.post(config.routes.heartbeat, { token: this.session.token, playing: true });
                    }
                    return;
                }
                if (this.session) await this.endSession();
                if (this.active !== i) return;

                const data = await this.post(config.routes.start, { video_id: v.id });
                if (!data) return;
                if (data.state) this.state = data.state;

                if (data.status === 'ad_due') {
                    this.videoEl(i)?.pause();
                    this.openAdBreak();
                    return;
                }
                if (data.status === 'ok') {
                    this.session = { token: data.token, uid: v.uid, earning: data.earning, playing: true };
                    v.reason = data.earning ? null : data.reason;
                    if (this.active !== i) await this.endSession();
                }
            });
        },

        onPause(i) {
            if (i === this.active && !this.ad.open) this.paused = true;
            const v = this.videos[i];
            if (this.session && v && this.session.uid === v.uid && this.session.playing) {
                this.session.playing = false;
                const token = this.session.token;
                this.enqueue(() => this.post(config.routes.heartbeat, { token, playing: false }));
            }
        },

        onEnded(i) {
            if (i !== this.active) return;
            this.enqueue(async () => {
                await this.endSession();
                if (this.ad.open) {
                    this.afterAd = config.autoNext ? 'next' : 'replay';
                    return;
                }
                if (config.autoNext) this.goTo(i + 1);
                else this.playActive();
            });
        },

        onTime(i, e) {
            const v = this.videos[i];
            if (!v) return;
            const el = e.target;
            const duration = Number.isFinite(el.duration) && el.duration > 0 ? el.duration : v.duration || 1;
            v.progress = Math.min(100, (100 * el.currentTime) / duration);
            if (i === this.active) this.liveSeconds = Math.max(this.liveSeconds, Math.floor(el.currentTime));
        },

        togglePlay() {
            const el = this.videoEl(this.active);
            if (!el) return;
            if (this.soundHint && this.muted && !el.paused) {
                this.setMuted(false);
                return;
            }
            if (el.paused) {
                this.userPaused = false;
                this.playActive();
            } else {
                this.userPaused = true;
                el.pause();
            }
        },

        setMuted(muted) {
            this.muted = muted;
            this.soundHint = false;
            this.$refs.scroller.querySelectorAll('video').forEach((v) => { v.muted = muted; });
        },

        goTo(i, tries = 0) {
            const slide = this.$refs.scroller.querySelector(`.feed-slide[data-index="${i}"]`);
            if (slide) {
                slide.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else if (tries < 1) {
                this.load().then(() => this.$nextTick(() => this.goTo(i, tries + 1)));
            } else {
                this.playActive();
            }
        },

        onVisibility() {
            if (document.visibilityState === 'hidden') {
                this.videoEl(this.active)?.pause();
            } else if (!this.ad.open && !this.userPaused) {
                this.playActive();
            }
        },

        /* ---------------- server sync ---------------- */

        tick() {
            if (!this.session || !this.session.playing || this.ad.open || document.visibilityState !== 'visible') return;
            const token = this.session.token;
            this.enqueue(() => this.post(config.routes.heartbeat, { token, playing: true }));
        },

        async endSession() {
            if (!this.session) return null;
            const s = this.session;
            this.session = null;

            const data = await this.post(config.routes.end, { token: s.token });
            if (data?.state) this.state = data.state;
            if (data?.counted && data.earned > 0) this.toast(`+${window.money(data.earned, 3)}`, 'video');
            if (data?.state?.ads_due > 0) this.openAdBreak();

            return data;
        },

        beaconEnd() {
            if (!this.session || !navigator.sendBeacon) return;
            const fd = new FormData();
            fd.append('token', this.session.token);
            fd.append('_token', config.csrf);
            navigator.sendBeacon(config.routes.end, fd);
            this.session = null;
        },

        /* ---------------- ad break ---------------- */

        async openAdBreak() {
            if (this.ad.open) return;
            this.ad.open = true;
            this.videoEl(this.active)?.pause();
            await this.nextAd();
        },

        async nextAd() {
            this.ad.loading = true;
            this.ad.ready = false;
            this.ad.frame = null;

            const data = await this.post(config.routes.adStart, {});
            this.ad.loading = false;

            if (!data || data.status !== 'ok') {
                if (data?.state) this.state = data.state;
                this.closeAdBreak();
                return;
            }

            Object.assign(this.ad, {
                id: data.ad_view_id,
                frame: data.frame_url,
                frameKey: this.ad.frameKey + 1,
                seconds: data.seconds,
                total: data.total,
                slot: data.slot,
                reward: data.reward,
            });
            this.startCountdown();
        },

        startCountdown() {
            clearInterval(this.adTimer);
            if (this.ad.seconds <= 0) {
                this.ad.ready = true;
                return;
            }
            this.adTimer = setInterval(() => {
                if (document.visibilityState !== 'visible') return;
                this.ad.seconds = Math.max(0, this.ad.seconds - 1);
                if (this.ad.seconds === 0) {
                    clearInterval(this.adTimer);
                    this.ad.ready = true;
                }
            }, 1000);
        },

        async claimAd() {
            if (!this.ad.ready || this.ad.claiming) return;
            this.ad.claiming = true;
            const data = await this.post(config.routes.adComplete, { ad_view_id: this.ad.id });
            this.ad.claiming = false;
            if (!data) return;

            if (data.status === 'too_early') {
                this.ad.ready = false;
                this.ad.seconds = data.wait;
                this.startCountdown();
                return;
            }

            if (data.state) this.state = data.state;
            if (data.status === 'ok' && data.earned > 0) this.toast(`+${window.money(data.earned, 3)}`, 'ad');
            if (data.cycle_completed) this.celebrate = { amount: data.credited };

            if (this.state.ads_due > 0) {
                await this.nextAd();
                return;
            }
            this.closeAdBreak();
        },

        closeAdBreak() {
            clearInterval(this.adTimer);
            this.ad.open = false;
            this.ad.frame = null;
            const after = this.afterAd;
            this.afterAd = null;
            if (after === 'next') this.goTo(this.active + 1);
            else this.playActive();
        },

        /* ---------------- helpers ---------------- */

        async post(url, body) {
            try {
                const { data } = await axios.post(url, body);
                return data;
            } catch (e) {
                const status = e.response?.status;
                if (status === 401 || status === 419) window.location.reload();
                else if (status === 429) this.toast('Thora ruk kar dobara koshish karein', 'warn');
                else if (status === 403) window.location.href = config.routes.login;
                return null;
            }
        },

        enqueue(fn) {
            this.queue = this.queue.then(fn).catch(() => {});
            return this.queue;
        },

        toast(text, kind = 'video') {
            const id = ++this.uid;
            this.toasts.push({ id, text, kind });
            setTimeout(() => { this.toasts = this.toasts.filter((t) => t.id !== id); }, 1900);
        },
    };
}
