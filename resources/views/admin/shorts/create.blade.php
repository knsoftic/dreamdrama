@extends('layouts.admin')
@section('title', 'New shorts')

@section('content')
@unless ($ready)
    <div class="a-card mb-4 border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
        ⚠️ ffmpeg detect nahi hua — upload to ho jayega, lekin shorts tab banenge jab server par ffmpeg mojood ho (<code>FFMPEG_PATH</code>).
    </div>
@endunless

<form id="shortsForm" method="POST" action="{{ route('admin.shorts.store') }}" enctype="multipart/form-data" class="a-card max-w-2xl space-y-4 p-5">
    @csrf
    <p class="text-sm text-slate-500">Apni <strong>copyright-free</strong> video upload karein — system usey vertical <strong>9:16 shorts</strong> me kaat dega. Phir aap review karke bulk-publish karte hain.</p>

    <div>
        <label class="a-label">Title</label>
        <input name="source_title" value="{{ old('source_title') }}" class="a-input" required placeholder="e.g. Funny clips pack 1">
        @error('source_title')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label class="a-label">Video file</label>
        <input type="file" name="video" accept="video/*" class="a-input" required>
        <p class="mt-1 text-xs text-slate-500">mp4 / webm / mov / mkv. Bade files ke liye server par <code>upload_max_filesize</code> &amp; <code>post_max_size</code> barhayein.</p>
        @error('video')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div><label class="a-label">Har short ki length (sec)</label><input type="number" name="clip_length" value="{{ old('clip_length', 40) }}" min="5" max="180" class="a-input" required></div>
        <div><label class="a-label">Max kitne shorts</label><input type="number" name="max_shorts" value="{{ old('max_shorts', 10) }}" min="1" max="50" class="a-input" required></div>
        <div><label class="a-label">Shuru se skip (sec)</label><input type="number" name="skip_intro" value="{{ old('skip_intro', 0) }}" min="0" max="600" class="a-input"></div>
        <div><label class="a-label">Aakhir se skip (sec)</label><input type="number" name="skip_outro" value="{{ old('skip_outro', 0) }}" min="0" max="600" class="a-input"></div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="a-label">9:16 banane ka tareeqa</label>
            <select name="reframe" class="a-input">
                <option value="blur" @selected(old('reframe') === 'blur')>Blurred background (poori frame)</option>
                <option value="crop" @selected(old('reframe') === 'crop')>Centre crop (zoom-in)</option>
            </select>
        </div>
        <div><label class="a-label">Category (optional)</label><input name="category" value="{{ old('category') }}" class="a-input" placeholder="e.g. Comedy"></div>
    </div>

    <label class="flex items-start gap-2 rounded-lg bg-slate-50 p-3 text-sm text-slate-600">
        <input type="checkbox" required class="mt-0.5">
        <span>Main confirm karta hun ke is video ke <strong>rights mere paas hain / ye copyright-free hai</strong>. (Doosron ka copyright content upload karna ad-network ban aur legal masla bana sakta hai.)</span>
    </label>

    <div class="flex gap-2">
        <button class="a-btn-primary">Upload &amp; make shorts</button>
        <a href="{{ route('admin.shorts.index') }}" class="a-btn-light">Cancel</a>
    </div>

    <div id="upProg" class="hidden">
        <div class="h-3 overflow-hidden rounded-full bg-slate-100"><div id="upBar" class="h-full rounded-full bg-violet-500 transition-all" style="width: 0%"></div></div>
        <div id="upMsg" class="mt-2 text-sm font-medium text-slate-600">Uploading… 0%</div>
        <p class="mt-1 text-xs text-slate-400">Upload complete hone tak page band na karein — bada video thoda waqt le sakta hai.</p>
    </div>
    <div id="upErr" class="hidden rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700"></div>
</form>

<script>
(function () {
    const form = document.getElementById('shortsForm');
    if (!form) return;
    const prog = document.getElementById('upProg');
    const bar = document.getElementById('upBar');
    const msg = document.getElementById('upMsg');
    const err = document.getElementById('upErr');
    const btn = form.querySelector('button');

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!form.checkValidity()) { form.reportValidity(); return; }

        err.classList.add('hidden'); err.textContent = '';
        prog.classList.remove('hidden');
        bar.style.width = '0%'; msg.textContent = 'Uploading… 0%';
        if (btn) btn.disabled = true;

        const xhr = new XMLHttpRequest();
        xhr.open('POST', form.action);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('Accept', 'application/json');

        xhr.upload.onprogress = function (ev) {
            if (!ev.lengthComputable) return;
            const p = Math.round(ev.loaded / ev.total * 100);
            bar.style.width = p + '%';
            msg.textContent = p < 100 ? ('Uploading… ' + p + '%') : 'Upload complete — shorts ban rahe hain…';
        };
        xhr.onload = function () {
            if (xhr.status >= 200 && xhr.status < 300) {
                let data = {}; try { data = JSON.parse(xhr.responseText); } catch (_) {}
                window.location = data.redirect || '{{ route('admin.shorts.index') }}';
                return;
            }
            if (btn) btn.disabled = false;
            prog.classList.add('hidden');
            let m = 'Upload fail hua. Dobara try karein.';
            if (xhr.status === 413) m = 'File server limit se bari hai — nginx client_max_body_size aur PHP upload_max_filesize barhayein.';
            else if (xhr.status === 419) m = 'Session expire ho gaya — page refresh karke dobara try karein.';
            else if (xhr.status === 422) { try { const d = JSON.parse(xhr.responseText); m = Object.values(d.errors || {}).flat().join(' '); } catch (_) {} }
            err.textContent = m; err.classList.remove('hidden');
        };
        xhr.onerror = function () {
            if (btn) btn.disabled = false;
            prog.classList.add('hidden');
            err.textContent = 'Network error — upload ruk gaya. Connection check karke dobara try karein.';
            err.classList.remove('hidden');
        };
        xhr.send(new FormData(form));
    });
})();
</script>
@endsection
