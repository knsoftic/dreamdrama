@props(['unit' => null])
@if ($unit)
    <div {{ $attributes->class(['overflow-hidden rounded-2xl border border-app-line bg-app-card']) }}>
        <div class="px-3 pt-2 text-[10px] font-semibold uppercase tracking-wider text-app-muted">Sponsored</div>
        <iframe src="{{ route('ad.frame', $unit) }}" data-ad-frame class="block w-full" style="height: 110px" loading="lazy"
                sandbox="allow-scripts allow-same-origin allow-popups allow-popups-to-escape-sandbox allow-forms" title="Advertisement"></iframe>
    </div>
@endif
