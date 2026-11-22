@extends('layouts.admin')
@section('title', $video->exists ? 'Video edit' : 'Video upload')

@section('content')
<form method="POST" enctype="multipart/form-data"
      action="{{ $video->exists ? route('admin.videos.update', $video) : route('admin.videos.store') }}"
      data-back="{{ route('admin.videos.index') }}"
      x-data="videoUpload(@js(['duration' => $video->duration_seconds ?: '', 'thumb' => $video->thumbnailUrl()]))"
      @submit.prevent="submit($event)"
      class="grid max-w-5xl gap-6 lg:grid-cols-[1fr_280px]">
    @csrf
    @if ($video->exists) @method('PUT') @endif
    <input type="hidden" name="thumbnail_data" :value="thumbData">

    <div class="a-card space-y-4 p-5">
        <div>
            <label class="a-label">Video file {{ $video->exists ? '(only to replace the current file)' : '' }}</label>
            <label class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center hover:border-violet-400 hover:bg-violet-50">
                <x-icon name="film" class="size-8 text-violet-500"/>
                <span class="mt-2 text-sm font-semibold text-slate-700" x-text="fileName || 'Select an MP4 / WEBM / MOV file'"></span>
                <span class="mt-1 text-xs text-slate-500">Vertical (9:16) is best · max 200 MB</span>
                <input type="file" name="video" accept="video/mp4,video/webm,video/quicktime" class="hidden" @change="pick($event)" {{ $video->exists ? '' : 'required' }}>
            </label>
            <template x-if="errors.video"><p class="mt-1 text-xs text-rose-600" x-text="errors.video[0]"></p></template>
        </div>

        <div>
            <label class="a-label">Title</label>
            <input name="title" value="{{ old('title', $video->title) }}" class="a-input" required maxlength="150">
            <template x-if="errors.title"><p class="mt-1 text-xs text-rose-600" x-text="errors.title[0]"></p></template>
        </div>
        <div>
            <label class="a-label">Description</label>
            <textarea name="description" rows="3" class="a-input">{{ old('description', $video->description) }}</textarea>
        </div>
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <label class="a-label">Category</label>
                <input name="category" value="{{ old('category', $video->category) }}" class="a-input" placeholder="Comedy" list="cats">
                <datalist id="cats"><option>Comedy</option><option>Funny</option><option>Motivation</option><option>Islamic</option><option>Sports</option><option>Food</option><option>Tech</option></datalist>
            </div>
            <div>
                <label class="a-label">Duration (seconds)</label>
                <input name="duration_seconds" type="number" min="1" x-model="duration" class="a-input" {{ $video->exists ? '' : 'required' }}>
                <template x-if="errors.duration_seconds"><p class="mt-1 text-xs text-rose-600" x-text="errors.duration_seconds[0]"></p></template>
            </div>
            <div>
                <label class="a-label">Priority</label>
                <input name="sort_order" type="number" value="{{ old('sort_order', $video->sort_order ?? 0) }}" class="a-input">
            </div>
        </div>
        <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" class="size-4 rounded accent-violet-600" @checked(old('is_active', $video->is_active))> Show in the feed
        </label>

        <div x-show="uploading" class="space-y-1">
            <div class="flex justify-between text-xs font-semibold text-slate-600"><span>Uploading...</span><span x-text="progress + '%'"></span></div>
            <div class="h-2 overflow-hidden rounded-full bg-slate-200"><div class="h-full bg-violet-600 transition-all" :style="`width:${progress}%`"></div></div>
        </div>

        <div class="flex gap-2 pt-2">
            <button class="a-btn-primary" :disabled="uploading">{{ $video->exists ? 'Save' : 'Upload' }}</button>
            <a href="{{ route('admin.videos.index') }}" class="a-btn-light">Cancel</a>
        </div>
    </div>

    <div class="space-y-3">
        <div class="a-card overflow-hidden">
            <div class="aspect-[9/16] bg-slate-900">
                <template x-if="thumb"><img :src="thumb" class="h-full w-full object-cover" alt=""></template>
                <div x-show="!thumb" class="grid h-full place-items-center text-xs text-slate-400">Thumbnail preview</div>
            </div>
        </div>
        <div class="a-card p-4">
            <label class="a-label">Custom thumbnail (optional)</label>
            <input type="file" name="thumbnail" accept="image/*" class="block w-full text-xs text-slate-600 file:mr-2 file:rounded file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-xs file:font-semibold"
                   @change="thumb = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : thumb">
            <p class="mt-2 text-xs text-slate-500">If left empty, a thumbnail is generated from the video.</p>
        </div>
    </div>
</form>
@endsection
