import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// Lazily connect to Reverb. Chat pages call window.connectEcho(); other pages never open a socket.
// Returns null when Reverb isn't configured — the chat then falls back to polling.
window.connectEcho = function () {
    if (window.Echo) return window.Echo;

    const key = import.meta.env.VITE_REVERB_APP_KEY;
    if (!key) return null;

    try {
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key,
            wsHost: import.meta.env.VITE_REVERB_HOST,
            wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
            wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
            forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
            enabledTransports: ['ws', 'wss'],
        });
    } catch (e) {
        return null;
    }

    return window.Echo;
};
