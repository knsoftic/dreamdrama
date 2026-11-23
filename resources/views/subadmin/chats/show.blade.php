@extends('layouts.panel')
@section('title', 'Chat — '.$conversation->user->name)

@section('content')
<div class="flex flex-col" style="height: calc(100dvh - 190px)">
    <div class="mb-2 flex items-center justify-between gap-2">
        <div class="flex items-center gap-3">
            <a href="{{ route('team.chats.index') }}" class="text-xl text-slate-400 hover:text-slate-600">&larr;</a>
            <div class="grid size-10 place-items-center rounded-full bg-violet-100 font-bold text-violet-700">{{ mb_substr($conversation->user->name, 0, 1) }}</div>
            <div>
                <div class="font-bold text-slate-900">{{ $conversation->user->name }}</div>
                <div class="text-xs text-slate-500">{{ $conversation->user->phone }}</div>
            </div>
        </div>
        <div class="flex gap-1">
            <form method="POST" action="{{ route('team.chats.mute', $conversation) }}">@csrf
                <button class="a-btn-light !px-2.5 !py-1.5 text-xs">{{ $conversation->muted ? '🔔 Unmute' : '🔇 Mute' }}</button>
            </form>
            <form method="POST" action="{{ route('team.chats.block', $conversation) }}" onsubmit="return confirm('{{ $conversation->blocked ? 'Is user ko unblock karein?' : 'Is user ko block karein?' }}')">@csrf
                <button class="a-btn-light !px-2.5 !py-1.5 text-xs text-rose-600">{{ $conversation->blocked ? 'Unblock' : 'Block' }}</button>
            </form>
        </div>
    </div>

    @include('chat._window', [
        'conversationId' => $conversation->id,
        'meId' => auth()->id(),
        'messages' => $messages,
        'messagesUrl' => route('team.chats.messages', $conversation),
        'sendUrl' => route('team.chats.send', $conversation),
        'blocked' => false,
        'dark' => false,
    ])
</div>
@endsection
