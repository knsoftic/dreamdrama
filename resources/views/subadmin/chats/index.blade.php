@extends('layouts.panel')
@section('title', 'Chats')

@section('content')
<form class="mb-4 flex gap-2">
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
                    <span class="truncate text-sm text-slate-500">
                        @if ($c->muted)🔇 @endif
                        {{ $c->latestMessage ? ($c->latestMessage->type === 'text' ? \Illuminate\Support\Str::limit($c->latestMessage->body, 42) : '['.$c->latestMessage->type.']') : 'Abhi koi message nahi' }}
                    </span>
                    @if ($c->staff_unread)<span class="shrink-0 rounded-full bg-violet-600 px-2 py-0.5 text-[11px] font-bold text-white">{{ $c->staff_unread }}</span>@endif
                </div>
            </div>
        </a>
    @empty
        <div class="p-10 text-center text-slate-500">Abhi koi chat nahi. Jab aapka koi member message karega, yahan aa jayega.</div>
    @endforelse
</div>
<div class="mt-4">{{ $conversations->links() }}</div>
@endsection
