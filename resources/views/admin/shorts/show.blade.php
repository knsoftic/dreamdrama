@extends('layouts.admin')
@section('title', 'Shorts — '.$batch->source_title)

@section('content')
@if (! $batch->isFinished())
    <script>setTimeout(() => location.reload(), 4000);</script>
@endif

<div class="a-card mb-4 p-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <div class="font-bold text-slate-900">{{ $batch->source_title }}</div>
            <div class="mt-0.5 text-sm text-slate-500">Status: <strong>{{ $batch->statusLabel() }}</strong> · {{ $batch->done }}/{{ $batch->total ?: '—' }} shorts</div>
            @if ($batch->error)<div class="mt-1 text-sm text-rose-600">{{ $batch->error }}</div>@endif
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.shorts.index') }}" class="a-btn-light !py-1.5 text-sm">Back</a>
            @if (($batch->status === 'failed' || ($batch->status === 'processing' && $batch->updated_at && $batch->updated_at->lt(now()->subMinutes(3)))) && $batch->source_path)
                <form method="POST" action="{{ route('admin.shorts.retry', $batch) }}">@csrf
                    <button class="a-btn-primary !py-1.5 text-sm">Retry</button>
                </form>
            @endif
            <form method="POST" action="{{ route('admin.shorts.destroy', $batch) }}" onsubmit="return confirm('Is batch aur iske shorts ko delete karein?')">@csrf @method('DELETE')
                <button class="a-btn-light !py-1.5 text-sm text-rose-600">Delete batch</button>
            </form>
        </div>
    </div>
    @unless ($batch->isFinished())
        <div class="mt-3 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-violet-500 transition-all" style="width: {{ max(5, $batch->progress()) }}%"></div></div>
        @if ($batch->status === 'queued')
            <p class="mt-2 text-xs text-amber-700">Processing shuru na ho to server par <code>php artisan queue:work</code> chalayein — ya <code>php artisan shorts:process {{ $batch->id }}</code>.</p>
        @endif
    @endunless
</div>

@if ($batch->videos->isNotEmpty())
    <form method="POST" action="{{ route('admin.shorts.publish', $batch) }}">
        @csrf
        <div class="mb-3 flex flex-wrap items-center gap-2">
            <button name="all" value="1" class="a-btn-success"><x-icon name="check" class="size-4" stroke="2.5"/> Publish all</button>
            <button class="a-btn-primary">Publish selected</button>
            <label class="ml-1 flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" onclick="document.querySelectorAll('.pick').forEach(c => c.checked = this.checked)"> Select all
            </label>
            <span class="ml-auto text-xs text-slate-500">Draft shorts sirf publish ke baad feed par aayenge.</span>
        </div>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($batch->videos as $v)
                <label class="a-card block cursor-pointer overflow-hidden">
                    <div class="relative aspect-[9/16] bg-slate-900">
                        @if ($v->thumbnailUrl())<img src="{{ $v->thumbnailUrl() }}" alt="" class="h-full w-full object-cover">@endif
                        <input type="checkbox" name="ids[]" value="{{ $v->id }}" class="pick absolute left-2 top-2 size-5 accent-violet-600">
                        <span class="absolute bottom-2 right-2 rounded bg-black/60 px-1.5 py-0.5 text-[11px] font-semibold text-white">{{ $v->durationLabel() }}</span>
                        @if ($v->is_active)<span class="absolute bottom-2 left-2 rounded bg-emerald-500 px-2 py-0.5 text-[11px] font-bold text-white">LIVE</span>@endif
                    </div>
                    <div class="p-3">
                        <div class="truncate text-sm font-semibold text-slate-900">{{ $v->title }}</div>
                        <a href="{{ $v->url() }}" target="_blank" rel="noopener" class="text-xs font-semibold text-violet-600">Preview ▶</a>
                    </div>
                </label>
            @endforeach
        </div>
    </form>
@elseif ($batch->isFinished())
    <div class="a-card p-10 text-center text-slate-500">Koi short generate nahi hua.</div>
@else
    <div class="a-card p-10 text-center text-slate-500">Shorts ban rahe hain… ye page khud refresh hota rahega.</div>
@endif
@endsection
