@extends('layouts.admin')
@section('title', 'Chat — '.$conversation->user->name)

@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-2">
    <div class="text-sm text-slate-600"><strong class="text-slate-900">{{ $conversation->user->name }}</strong> ({{ $conversation->user->phone }}) &harr; Sub-admin: <strong class="text-slate-900">{{ $conversation->staff?->name ?? '—' }}</strong></div>
    <a href="{{ route('admin.chats.index') }}" class="a-btn-light !py-1.5 text-sm">Back</a>
</div>

<form method="POST" action="{{ route('admin.chats.labels', $conversation) }}" class="a-card mb-4 flex flex-wrap items-center gap-2 p-4">@csrf
    <span class="text-sm font-semibold text-slate-700">Global labels:</span>
    @forelse ($labels as $l)
        <label class="inline-flex cursor-pointer items-center gap-1 rounded-full border px-2 py-1 text-xs" style="border-color: {{ $l->color }}">
            <input type="checkbox" name="labels[]" value="{{ $l->id }}" @checked($conversation->labels->contains($l->id)) class="size-3.5 accent-violet-600"> {{ $l->name }}
        </label>
    @empty
        <span class="text-xs text-slate-400">Koi global label nahi — All Chats page par banayein.</span>
    @endforelse
    <button class="a-btn-primary !px-3 !py-1.5 text-xs">Save</button>
</form>

<div class="a-card space-y-2 p-4" style="max-height: 60vh; overflow-y: auto">
    @forelse ($messages as $m)
        @php $mine = $m->sender_id === $conversation->staff_id; @endphp
        <div class="flex {{ $mine ? 'justify-end' : 'justify-start' }}">
            <div class="max-w-[80%] rounded-2xl px-3 py-2 text-sm {{ $mine ? 'bg-violet-600 text-white' : 'bg-slate-100 text-slate-900' }}">
                <div class="mb-0.5 text-[10px] opacity-70">{{ $m->sender?->name }}</div>
                @if ($m->type === 'image' && $m->attachmentUrl())
                    <a href="{{ $m->attachmentUrl() }}" target="_blank" rel="noopener"><img src="{{ $m->attachmentUrl() }}" alt="" class="max-h-56 rounded-lg"></a>
                @elseif ($m->type === 'voice' && $m->attachmentUrl())
                    <audio controls src="{{ $m->attachmentUrl() }}" class="w-52"></audio>
                @else
                    <div class="whitespace-pre-wrap break-words">{{ $m->body }}</div>
                @endif
                <div class="mt-0.5 text-right text-[10px] opacity-60">{{ $m->created_at?->format('d M, h:i A') }}</div>
            </div>
        </div>
    @empty
        <div class="py-10 text-center text-slate-500">Koi message nahi.</div>
    @endforelse
</div>
<p class="mt-2 text-xs text-slate-400">🔎 Read-only oversight view.</p>
@endsection
