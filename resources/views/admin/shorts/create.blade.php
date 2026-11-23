@extends('layouts.admin')
@section('title', 'New shorts')

@section('content')
@unless ($ready)
    <div class="a-card mb-4 border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
        ⚠️ ffmpeg detect nahi hua — upload to ho jayega, lekin shorts tab banenge jab server par ffmpeg mojood ho (<code>FFMPEG_PATH</code>).
    </div>
@endunless

<form method="POST" action="{{ route('admin.shorts.store') }}" enctype="multipart/form-data" class="a-card max-w-2xl space-y-4 p-5">
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
</form>
@endsection
