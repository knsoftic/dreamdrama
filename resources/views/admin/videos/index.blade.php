@extends('layouts.admin')
@section('title', 'Videos')
@section('actions')
    <a href="{{ route('admin.videos.create') }}" class="a-btn-primary"><x-icon name="plus" class="size-4" stroke="2.5"/> Upload</a>
@endsection

@section('content')
<form class="mb-4 flex gap-2">
    <input name="q" value="{{ request('q') }}" placeholder="Title se search..." class="a-input max-w-xs">
    <button class="a-btn-light">Search</button>
</form>

<div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
    @forelse ($videos as $video)
        <div class="a-card overflow-hidden">
            <div class="relative aspect-[9/14] bg-slate-900">
                @if ($video->thumbnailUrl())
                    <img src="{{ $video->thumbnailUrl() }}" class="h-full w-full object-cover {{ $video->is_active ? '' : 'opacity-40 grayscale' }}" alt="">
                @else
                    <div class="grid h-full place-items-center text-slate-500"><x-icon name="film" class="size-10"/></div>
                @endif
                <span class="absolute right-2 bottom-2 rounded bg-black/70 px-1.5 py-0.5 text-[11px] font-semibold text-white">{{ $video->durationLabel() }}</span>
                @unless ($video->is_active)<span class="absolute left-2 top-2 rounded bg-slate-800 px-1.5 py-0.5 text-[10px] font-bold text-white">HIDDEN</span>@endunless
            </div>
            <div class="p-3">
                <div class="line-clamp-2 text-sm font-semibold text-slate-900">{{ $video->title }}</div>
                <div class="mt-1 text-xs text-slate-500">{{ number_format($video->views_count) }} views · {{ number_format($video->earned_views) }} earned</div>
                <div class="mt-3 flex gap-1.5">
                    <a href="{{ route('admin.videos.edit', $video) }}" class="a-btn-light flex-1 !px-2 !py-1.5 text-xs"><x-icon name="pencil" class="size-3.5"/> Edit</a>
                    <form method="POST" action="{{ route('admin.videos.toggle', $video) }}">@csrf
                        <button class="a-btn-light !px-2 !py-1.5 text-xs" title="{{ $video->is_active ? 'Hide' : 'Show' }}"><x-icon name="eye" class="size-3.5"/></button>
                    </form>
                    <form method="POST" action="{{ route('admin.videos.destroy', $video) }}" onsubmit="return confirm('This video will be deleted permanently. Sure?')">@csrf @method('DELETE')
                        <button class="a-btn-light !px-2 !py-1.5 text-xs text-rose-600"><x-icon name="trash" class="size-3.5"/></button>
                    </form>
                </div>
            </div>
        </div>
    @empty
        <div class="a-card col-span-full p-10 text-center">
            <x-icon name="film" class="mx-auto size-10 text-slate-400"/>
            <p class="mt-3 font-semibold text-slate-700">No videos yet</p>
            <a href="{{ route('admin.videos.create') }}" class="a-btn-primary mt-4">Upload your first video</a>
        </div>
    @endforelse
</div>
<div class="mt-6">{{ $videos->links() }}</div>
@endsection
