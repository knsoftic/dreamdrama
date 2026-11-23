@extends('layouts.panel')
@section('title', 'Chats')

@section('content')
{{-- Labels: filter + create / delete own --}}
<div class="mb-3 flex flex-wrap items-center gap-2">
    <a href="{{ route('team.chats.index') }}" class="rounded-full px-3 py-1 text-xs font-semibold {{ ! request('label') ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600' }}">All</a>
    @foreach ($labels as $l)
        <span class="inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-semibold {{ request('label') == $l->id ? 'ring-2 ring-offset-1' : '' }}" style="background: {{ $l->color }}22; color: {{ $l->color }}; --tw-ring-color: {{ $l->color }}">
            <a href="{{ route('team.chats.index', ['label' => $l->id]) }}">{{ $l->name }}</a>
            @if ($l->isGlobal())<span title="Global label" class="opacity-60">🌐</span>
            @else<form method="POST" action="{{ route('team.chat-labels.destroy', $l) }}" onsubmit="return confirm('Label delete karein?')">@csrf @method('DELETE')<button class="opacity-60 hover:opacity-100">&times;</button></form>@endif
        </span>
    @endforeach
    <form method="POST" action="{{ route('team.chat-labels.store') }}" class="flex items-center gap-1">@csrf
        <input name="name" placeholder="+ New label" maxlength="40" class="a-input !w-32 !py-1 text-xs" required>
        <input type="color" name="color" value="#8b5cf6" class="h-7 w-7 rounded border-0 bg-transparent p-0" title="Color">
        <button class="a-btn-light !px-2 !py-1 text-xs">Add</button>
    </form>
</div>

<form class="mb-4 flex gap-2">
    @if (request('label'))<input type="hidden" name="label" value="{{ request('label') }}">@endif
    <input name="q" value="{{ request('q') }}" placeholder="Name ya phone" class="a-input max-w-xs">
    <button class="a-btn-light">Search</button>
</form>

<div class="a-card divide-y divide-slate-100">
    @forelse ($conversations as $c)
        <a href="{{ route('team.chats.show', $c) }}" class="flex items-center gap-3 p-4 transition hover:bg-slate-50">
            <div class="grid size-11 shrink-0 place-items-center rounded-full bg-violet-100 font-bold text-violet-700">{{ mb_substr($c->user->name, 0, 1) }}</div>
            <div class="min-w-0 flex-1">
                <div class="flex items-center justify-between gap-2">
                    <span class="truncate font-semibold text-slate-900">{{ $c->user->name }}</span>
                    <span class="shrink-0 text-xs text-slate-400">{{ $c->last_message_at?->diffForHumans() }}</span>
                </div>
                <div class="mt-0.5 flex items-center justify-between gap-2">
                    <span class="truncate text-sm text-slate-500">@if ($c->muted)🔇 @endif{{ $c->latestMessage ? ($c->latestMessage->type === 'text' ? \Illuminate\Support\Str::limit($c->latestMessage->body, 42) : '['.$c->latestMessage->type.']') : 'Abhi koi message nahi' }}</span>
                    @if ($c->staff_unread)<span class="shrink-0 rounded-full bg-violet-600 px-2 py-0.5 text-[11px] font-bold text-white">{{ $c->staff_unread }}</span>@endif
                </div>
                @if ($c->labels->isNotEmpty())
                    <div class="mt-1 flex flex-wrap gap-1">@foreach ($c->labels as $l)<span class="rounded px-1.5 py-0.5 text-[10px] font-bold" style="background: {{ $l->color }}22; color: {{ $l->color }}">{{ $l->name }}</span>@endforeach</div>
                @endif
            </div>
        </a>
    @empty
        <div class="p-10 text-center text-slate-500">Abhi koi chat nahi. Jab aapka koi member message karega, yahan aa jayega.</div>
    @endforelse
</div>
<div class="mt-4">{{ $conversations->links() }}</div>
@endsection
