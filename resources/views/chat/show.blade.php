@extends('layouts.app')
@section('title', 'Support')

@section('content')
<div class="flex flex-col" style="height: calc(100dvh - 210px)">
    <div class="mb-2 flex items-center gap-3">
        <div class="grid size-10 place-items-center rounded-full bg-violet-600/25 font-bold text-violet-200">{{ mb_substr($conversation->staff->name, 0, 1) }}</div>
        <div>
            <div class="font-bold">{{ $conversation->staff->name }}</div>
            <div class="text-xs text-app-muted">Aapka administrator · kisi bhi issue par rabta karein</div>
        </div>
    </div>

    @include('chat._window', [
        'conversationId' => $conversation->id,
        'meId' => auth()->id(),
        'messages' => $messages,
        'messagesUrl' => route('chat.messages'),
        'sendUrl' => route('chat.send'),
        'blocked' => $conversation->blocked,
        'dark' => true,
    ])
</div>
@endsection
