import './bootstrap';
import Alpine from 'alpinejs';
import feed from './feed';
import videoUpload from './admin-upload';

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

Alpine.data('feed', feed);
Alpine.data('videoUpload', videoUpload);

window.Alpine = Alpine;
Alpine.start();

// Register the service worker so the site can be installed like an app.
if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}
