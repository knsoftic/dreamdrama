@extends('layouts.admin')
@section('title', 'Fraud Reports')

@section('content')
<div class="grid gap-6 xl:grid-cols-3">
    <div class="a-card overflow-x-auto xl:col-span-2">
        <div class="border-b border-slate-100 px-5 py-4 font-bold text-slate-900">Fraud logs</div>
        <table class="a-table">
            <thead><tr><th>Waqt</th><th>User</th><th>Type</th><th>Detail</th><th>IP</th></tr></thead>
            <tbody>
            @forelse ($logs as $log)
                <tr>
                    <td class="text-xs">{{ $log->created_at->format('d M, h:i A') }}</td>
                    <td>@if ($log->user)<a href="{{ route('admin.users.show', $log->user) }}" class="font-semibold text-violet-600">{{ $log->user->name }}</a>@else — @endif</td>
                    <td>{{ \App\Models\FraudLog::TYPES[$log->type] ?? $log->type }}</td>
                    <td class="text-xs">{{ $log->details }}</td>
                    <td class="font-mono text-xs">{{ $log->ip }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-10 text-center text-slate-500">Abhi koi fraud signal nahi</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="p-4">{{ $logs->links() }}</div>
    </div>

    <div class="a-card">
        <div class="border-b border-slate-100 px-5 py-4 font-bold text-slate-900">Ek IP par kai accounts</div>
        <div class="divide-y divide-slate-100">
            @forelse ($sharedIps as $row)
                <a href="{{ route('admin.users.index', ['q' => $row->register_ip]) }}" class="flex items-center justify-between px-5 py-3 text-sm hover:bg-slate-50">
                    <span class="font-mono">{{ $row->register_ip }}</span>
                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-700">{{ $row->total }} accounts</span>
                </a>
            @empty
                <div class="px-5 py-8 text-center text-sm text-slate-500">Sab theek hai</div>
            @endforelse
        </div>
        <p class="border-t border-slate-100 px-5 py-3 text-xs text-slate-500">Note: ek ghar / office ke users ka IP same ho sakta hai. Block karne se pehle cycles aur device ID check karein.</p>
    </div>
</div>
@endsection
