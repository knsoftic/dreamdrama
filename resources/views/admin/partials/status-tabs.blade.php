<div class="mb-4 flex flex-wrap gap-1.5">
    @foreach ($options as $key => $label)
        <a href="{{ request()->fullUrlWithQuery(['status' => $key, 'page' => null]) }}"
           class="rounded-lg px-3 py-1.5 text-sm font-semibold {{ $status === $key ? 'bg-violet-600 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50' }}">{{ $label }}</a>
    @endforeach
</div>
