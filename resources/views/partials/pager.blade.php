@if ($paginator->hasPages())
    <nav class="flex items-center justify-between gap-3 text-sm">
        @if ($paginator->onFirstPage())
            <span class="btn-ghost !px-4 !py-2 opacity-40">Pichla</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="btn-ghost !px-4 !py-2">Pichla</a>
        @endif
        <span class="text-app-muted">Page {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="btn-ghost !px-4 !py-2">Agla</a>
        @else
            <span class="btn-ghost !px-4 !py-2 opacity-40">Agla</span>
        @endif
    </nav>
@endif
