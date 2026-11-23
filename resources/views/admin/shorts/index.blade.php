@extends('layouts.admin')
@section('title', 'Auto Shorts')
@section('actions')
    <a href="{{ route('admin.shorts.create') }}" class="a-btn-primary"><x-icon name="plus" class="size-4" stroke="2.5"/> New shorts</a>
@endsection

@section('content')
@php $tone = ['queued' => 'bg-slate-200 text-slate-700', 'processing' => 'bg-amber-100 text-amber-700', 'done' => 'bg-emerald-100 text-emerald-700', 'failed' => 'bg-rose-100 text-rose-700']; @endphp

@unless ($ready)
    <div class="a-card mb-4 border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
        ⚠️ <strong>ffmpeg</strong> server par detect nahi hua. Shorts banane ke liye ffmpeg install karein — ya <code>FFMPEG_PATH</code> ko .env me set karein.
    </div>
@endunless

<div class="a-card overflow-x-auto">
    <table class="a-table">
        <thead><tr><th>Source</th><th>Status</th><th>Shorts</th><th>Banaya</th><th></th></tr></thead>
        <tbody>
        @forelse ($batches as $b)
            <tr>
                <td class="font-semibold text-slate-900">{{ $b->source_title }}</td>
                <td>
                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $tone[$b->status] ?? 'bg-slate-200' }}">{{ $b->statusLabel() }}</span>
                    @if (! $b->isFinished() && $b->total)<span class="ml-1 text-xs text-slate-500">{{ $b->progress() }}%</span>@endif
                    @if ($b->error)<div class="mt-0.5 text-xs text-rose-600">{{ \Illuminate\Support\Str::limit($b->error, 80) }}</div>@endif
                </td>
                <td>{{ $b->videos_count }}</td>
                <td class="whitespace-nowrap text-xs text-slate-500">{{ $b->created_at->diffForHumans() }}</td>
                <td class="text-right"><a href="{{ route('admin.shorts.show', $b) }}" class="a-btn-light !px-3 !py-1.5 text-xs">Open</a></td>
            </tr>
        @empty
            <tr><td colspan="5" class="py-10 text-center text-slate-500">Abhi koi shorts nahi. Apni copyright-free video upload karke shorts banayein.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $batches->links() }}</div>
@endsection
