@extends('layouts.admin')
@section('title', 'All Chats')

@section('content')
<div class="a-card mb-4 p-4">
    <div class="mb-2 text-sm font-bold text-slate-900">Global labels <span class="font-normal text-slate-500">— sab sub-admins ko milte hain</span></div>
    <div class="flex flex-wrap items-center gap-2">
        @foreach ($labels as $l)
            <span class="inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-semibold" style="background: {{ $l->color }}22; color: {{ $l->color }}">
                {{ $l->name }}
                <form method="POST" action="{{ route('admin.chat-labels.destroy', $l) }}" onsubmit="return confirm('Delete this label?')">@csrf @method('DELETE')<button class="opacity-60 hover:opacity-100">&times;</button></form>
            </span>
        @endforeach
        <form method="POST" action="{{ route('admin.chat-labels.store') }}" class="flex items-center gap-1">@csrf
            <input name="name" placeholder="+ New global label" maxlength="40" class="a-input !w-44 !py-1 text-xs" required>
            <input type="color" name="color" value="#8b5cf6" class="h-7 w-7 rounded border-0 bg-transparent p-0" title="Color">
            <button class="a-btn-light !px-2 !py-1 text-xs">Add</button>
        </form>
    </div>
</div>

<form class="mb-4 flex flex-wrap gap-2">
    <input name="q" value="{{ request('q') }}" placeholder="User / sub-admin name ya phone" class="a-input max-w-xs">
    <select name="label" class="a-input max-w-[12rem]"><option value="">All labels</option>@foreach ($labels as $l)<option value="{{ $l->id }}" @selected(request('label') == $l->id)>{{ $l->name }}</option>@endforeach</select>
    <button class="a-btn-light">Filter</button>
</form>

<div class="a-card overflow-x-auto">
    <table class="a-table">
        <thead><tr><th>Member</th><th>Sub-admin</th><th>Last message</th><th>Labels</th><th>Updated</th><th></th></tr></thead>
        <tbody>
        @forelse ($conversations as $c)
            <tr>
                <td class="font-semibold text-slate-900">{{ $c->user->name }}<div class="text-xs font-normal text-slate-500">{{ $c->user->phone }}</div></td>
                <td class="text-sm">{{ $c->staff?->name ?? '—' }}</td>
                <td class="max-w-xs truncate text-sm text-slate-500">{{ $c->latestMessage ? ($c->latestMessage->type === 'text' ? \Illuminate\Support\Str::limit($c->latestMessage->body, 48) : '['.$c->latestMessage->type.']') : '—' }}</td>
                <td>@foreach ($c->labels as $l)<span class="mr-1 inline-block rounded px-1.5 py-0.5 text-[10px] font-bold" style="background: {{ $l->color }}22; color: {{ $l->color }}">{{ $l->name }}</span>@endforeach</td>
                <td class="whitespace-nowrap text-xs text-slate-400">{{ $c->last_message_at?->diffForHumans() }}</td>
                <td class="text-right"><a href="{{ route('admin.chats.show', $c) }}" class="a-btn-light !px-3 !py-1.5 text-xs">View</a></td>
            </tr>
        @empty
            <tr><td colspan="6" class="py-10 text-center text-slate-500">Abhi koi chat nahi.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $conversations->links() }}</div>
@endsection
