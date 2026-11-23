import './bootstrap';
import './echo';
import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import feed from './feed';
import videoUpload from './admin-upload';

Alpine.plugin(collapse);

// A random id this browser keeps; the server uses it to spot many accounts on one device.
(function deviceId() {
    let id = null;
    try {
        id = localStorage.getItem('pe_did');
        if (!id) {
            id = (crypto.randomUUID ? crypto.randomUUID() : Date.now().toString(36) + Math.random().toString(36).slice(2));
            localStorage.setItem('pe_did', id);
        }
    } catch (e) {
        return;
    }
    if (!document.cookie.includes('pe_did=')) {
        document.cookie = `pe_did=${id}; path=/; max-age=31536000; SameSite=Lax`;
    }
})();

window.money = (amount, decimals = 2) => {
    const symbol = window.APP?.currency || 'Rs';
    return `${symbol} ${Number(amount || 0).toFixed(decimals)}`;
};

// Banner ad frames report their height so the iframe can fit the ad.
window.addEventListener('message', (e) => {
    if (e.origin !== window.location.origin || !e.data || typeof e.data.adFrameHeight !== 'number') return;
    document.querySelectorAll('iframe[data-ad-frame]').forEach((frame) => {
        if (frame.contentWindow === e.source) {
            frame.style.height = `${Math.min(320, Math.max(60, e.data.adFrameHeight + 8))}px`;
        }
    });
});

// In-app banner that lets the user enable notifications if they are off.
Alpine.data('notifPrompt', () => ({
    show: false,
    init() {
        try {
            if (!window.isDramaApp || !window.isDramaApp()) return;
            if (sessionStorage.getItem('notifDismissed')) return;
            const allowed = window.DramaApp && window.DramaApp.notificationsAllowed
                ? window.DramaApp.notificationsAllowed() : true;
            this.show = !allowed;
        } catch (e) {
            /* ignore */
        }
    },
    enable() {
        try {
            window.DramaApp && window.DramaApp.enableNotifications && window.DramaApp.enableNotifications();
        } catch (e) {
            /* ignore */
        }
        this.show = false;
    },
    dismiss() {
        try {
            sessionStorage.setItem('notifDismissed', '1');
        } catch (e) {
            /* ignore */
        }
        this.show = false;
    },
}));

Alpine.data('feed', feed);
Alpine.data('videoUpload', videoUpload);

window.Alpine = Alpine;
Alpine.start();

// The Android app calls this with its push token; we hand it to the server
// so the admin can send notifications to this device.
window.__dramaPushToken = function (token) {
    if (!token || window.__dramaTokenSent === token) return;
    window.__dramaTokenSent = token;
    try {
        window.axios.post('/app/push-token', { token, platform: 'android' }).catch(() => {});
    } catch (e) {
        /* ignore */
    }
};

// Running inside our Android app?
window.isDramaApp = () => {
    try {
        return (window.DramaApp && window.DramaApp.isApp && window.DramaApp.isApp()) || / DramaEarnApp\//.test(navigator.userAgent);
    } catch (e) {
        return false;
    }
};

// Register the service worker so the site can be installed like an app.
if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}
