{{-- Shared chat window. Expects: $conversationId, $meId, $messages, $messagesUrl, $sendUrl, $blocked, $dark --}}
<div id="chatMsgs" class="flex-1 space-y-2 overflow-y-auto px-0.5 py-2" style="min-height:0"></div>

@if ($blocked)
    <div class="mt-2 rounded-xl p-3 text-center text-sm {{ $dark ? 'bg-white/5 text-white/70' : 'bg-rose-50 text-rose-700' }}">Ye chat band hai — aap message nahi bhej sakte.</div>
@else
    <form id="chatForm" class="mt-2 flex items-center gap-1.5">
        <input type="file" id="chatImgInput" accept="image/*" class="hidden">
        <button type="button" id="chatImgBtn" title="Photo" class="grid size-10 shrink-0 place-items-center rounded-full {{ $dark ? 'text-white/70 hover:bg-white/10' : 'text-slate-500 hover:bg-slate-100' }}">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
        </button>
        <button type="button" id="chatMicBtn" title="Voice" class="grid size-10 shrink-0 place-items-center rounded-full {{ $dark ? 'text-white/70 hover:bg-white/10' : 'text-slate-500 hover:bg-slate-100' }}">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v3"/></svg>
        </button>
        <input id="chatInput" autocomplete="off" maxlength="4000" placeholder="Message likhein…"
               class="flex-1 rounded-full px-4 py-3 text-sm outline-none {{ $dark ? 'border border-white/10 bg-white/10 text-white placeholder-white/40' : 'border border-slate-300 bg-white text-slate-900' }}">
        <button type="submit" class="grid size-11 shrink-0 place-items-center rounded-full bg-violet-600 text-white active:scale-95">
            <svg width="20" height="20" viewBox="0 0 24 24"><path d="M4 12l16-8-6 16-2-6-8-2z" fill="currentColor"/></svg>
        </button>
    </form>
    <div id="chatRecBar" class="mt-1 hidden items-center gap-2 text-xs {{ $dark ? 'text-rose-300' : 'text-rose-600' }}">
        <span class="inline-block size-2 animate-pulse rounded-full bg-rose-500"></span>
        Recording… <b id="chatRecTime">0s</b>
        <button type="button" id="chatRecStop" class="font-semibold underline">Stop &amp; send</button>
        <button type="button" id="chatRecCancel" class="underline opacity-70">Cancel</button>
    </div>
@endif

<script>
window.CHAT = {
    id: {{ $conversationId }},
    me: {{ $meId }},
    theme: @json($dark ? 'dark' : 'light'),
    messagesUrl: @json($messagesUrl),
    sendUrl: @json($sendUrl),
    initial: @json($messages->map->toWire()->values()),
};
(function () {
    const cfg = window.CHAT, box = document.getElementById('chatMsgs');
    const form = document.getElementById('chatForm'), input = document.getElementById('chatInput');
    const seen = new Set();
    let lastId = 0;
    const esc = s => { const d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; };
    const linkify = s => esc(s).replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank" rel="noopener" class="underline">$1</a>');
    const theirs = cfg.theme === 'dark' ? 'mr-auto bg-white/10 text-white' : 'mr-auto bg-slate-100 text-slate-900';

    function bubble(m) {
        let inner;
        if (m.type === 'image' && m.attachment) inner = `<a href="${m.attachment}" target="_blank" rel="noopener"><img src="${m.attachment}" class="max-h-64 rounded-lg"></a>`;
        else if (m.type === 'voice' && m.attachment) inner = `<audio controls src="${m.attachment}" class="w-52"></audio>`;
        else inner = `<div class="whitespace-pre-wrap break-words">${linkify(m.body || '')}</div>`;
        const mine = m.sender_id === cfg.me;
        return `<div class="max-w-[80%] rounded-2xl px-3 py-2 text-sm ${mine ? 'ml-auto bg-violet-600 text-white' : theirs}">${inner}<div class="mt-0.5 text-right text-[10px] opacity-60">${esc(m.time || '')}</div></div>`;
    }
    const add = m => { if (seen.has(m.id)) return; seen.add(m.id); if (m.id > lastId) lastId = m.id; box.insertAdjacentHTML('beforeend', bubble(m)); };
    const scroll = () => { box.scrollTop = box.scrollHeight; };

    (cfg.initial || []).forEach(add);
    scroll();

    if (form) form.addEventListener('submit', async e => {
        e.preventDefault();
        const body = input.value.trim();
        if (!body) return;
        input.value = '';
        try { const r = await window.axios.post(cfg.sendUrl, { body }); add(r.data.message); scroll(); }
        catch (err) { input.value = body; if (err?.response?.status === 403) alert('Ye chat band hai.'); }
    });

    // ---- attachments: image + voice ----
    async function upload(file, name, extra) {
        const fd = new FormData();
        fd.append('attachment', file, name);
        if (extra && extra.duration) fd.append('duration', extra.duration);
        try { const r = await window.axios.post(cfg.sendUrl, fd); add(r.data.message); scroll(); }
        catch (err) { alert(err?.response?.status === 403 ? 'Ye chat band hai.' : 'Bhejne me masla — dobara try karein.'); }
    }

    const imgBtn = document.getElementById('chatImgBtn'), imgInput = document.getElementById('chatImgInput');
    if (imgBtn && imgInput) {
        imgBtn.addEventListener('click', () => imgInput.click());
        imgInput.addEventListener('change', () => { const f = imgInput.files[0]; if (f) upload(f, f.name); imgInput.value = ''; });
    }

    const micBtn = document.getElementById('chatMicBtn'), recBar = document.getElementById('chatRecBar');
    const recTime = document.getElementById('chatRecTime'), recStop = document.getElementById('chatRecStop'), recCancel = document.getElementById('chatRecCancel');
    let rec = null, chunks = [], recT0 = 0, recTimer = null, cancelled = false;
    const showBar = on => { if (!recBar) return; recBar.classList.toggle('hidden', !on); recBar.classList.toggle('flex', on); micBtn.classList.toggle('text-rose-500', on); };
    if (micBtn) micBtn.addEventListener('click', async () => {
        if (rec && rec.state === 'recording') { rec.stop(); return; }
        if (!navigator.mediaDevices || !window.MediaRecorder) { alert('Is browser me voice record support nahi hai.'); return; }
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            chunks = []; cancelled = false; recT0 = Date.now();
            rec = new MediaRecorder(stream); rec.stream = stream;
            rec.ondataavailable = e => { if (e.data.size) chunks.push(e.data); };
            rec.onstop = () => {
                stream.getTracks().forEach(t => t.stop());
                clearInterval(recTimer); showBar(false);
                if (cancelled || !chunks.length) return;
                upload(new Blob(chunks, { type: rec.mimeType || 'audio/webm' }), 'voice.webm', { duration: Math.max(1, Math.round((Date.now() - recT0) / 1000)) });
            };
            rec.start(); showBar(true);
            recTimer = setInterval(() => { if (recTime) recTime.textContent = Math.round((Date.now() - recT0) / 1000) + 's'; }, 500);
        } catch (e) { alert('Microphone access nahi mila.'); }
    });
    if (recStop) recStop.addEventListener('click', () => { if (rec && rec.state === 'recording') rec.stop(); });
    if (recCancel) recCancel.addEventListener('click', () => { cancelled = true; if (rec && rec.state === 'recording') rec.stop(); });

    // Instant via Reverb when it is configured…
    try {
        const echo = window.connectEcho && window.connectEcho();
        if (echo) echo.private('conversation.' + cfg.id).listen('.MessageSent', e => { add(e.message); scroll(); });
    } catch (e) {}

    // …and a polling fallback so it always works.
    setInterval(async () => {
        try {
            const r = await window.axios.get(cfg.messagesUrl, { params: { after: lastId } });
            const list = r.data.messages || [];
            if (list.length) { list.forEach(add); scroll(); }
        } catch (e) {}
    }, 4000);
})();
</script>
