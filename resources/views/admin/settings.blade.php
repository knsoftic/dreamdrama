@extends('layouts.admin')
@section('title', 'Settings')

@section('content')
<form method="POST" action="{{ route('admin.settings.update') }}" x-data="{ tab: 'earning' }" class="max-w-4xl">
    @csrf @method('PUT')

    <div class="mb-4 flex flex-wrap gap-1.5">
        @foreach ($groups as $key => $group)
            <button type="button" @click="tab = '{{ $key }}'" class="rounded-lg px-3 py-1.5 text-sm font-semibold"
                    :class="tab === '{{ $key }}' ? 'bg-violet-600 text-white' : 'bg-white text-slate-600 ring-1 ring-slate-200'">{{ $group['label'] }}</button>
        @endforeach
    </div>

    @foreach ($groups as $key => $group)
        <div x-show="tab === '{{ $key }}'" @if ($key !== 'earning') x-cloak @endif class="a-card space-y-5 p-5">
            @if ($key === 'earning')
                <div class="rounded-lg bg-violet-50 p-4 text-sm text-violet-900">
                    <strong>Example:</strong> {{ setting('videos_per_cycle') }} videos × ~20 sec × {{ setting('rate_per_second') }} =
                    {{ money(setting('videos_per_cycle') * 20 * setting('rate_per_second'), 3) }} video earning +
                    {{ setting('ads_per_cycle') }} ads × {{ setting('rate_per_ad') }} = {{ money(setting('ads_per_cycle') * setting('rate_per_ad'), 3) }} ad earning
                    → ek cycle ≈ <strong>{{ money(setting('videos_per_cycle') * 20 * setting('rate_per_second') + setting('ads_per_cycle') * setting('rate_per_ad'), 3) }}</strong> (1x plan).
                    Yeh hamesha aap ki ad revenue se kam rakhein.
                </div>
            @endif
            @foreach ($group['fields'] as $name => $field)
                @php $value = old($name, $values[$name] ?? ''); @endphp
                <div>
                    @if ($field['type'] === 'bool')
                        <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                            <input type="checkbox" name="{{ $name }}" value="1" class="size-4 accent-violet-600" @checked($value === '1' || $value === 1 || $value === true)>
                            {{ $field['label'] }}
                        </label>
                    @else
                        <label class="a-label" for="{{ $name }}">{{ $field['label'] }}</label>
                        @switch($field['type'])
                            @case('select')
                                <select id="{{ $name }}" name="{{ $name }}" class="a-input">
                                    @foreach ($field['options'] as $ok => $ov)<option value="{{ $ok }}" @selected($value === $ok)>{{ $ov }}</option>@endforeach
                                </select>
                                @break
                            @case('textarea')
                                <textarea id="{{ $name }}" name="{{ $name }}" rows="4" class="a-input">{{ $value }}</textarea>
                                @break
                            @case('code')
                                <textarea id="{{ $name }}" name="{{ $name }}" rows="6" class="a-input font-mono text-xs" spellcheck="false">{{ $value }}</textarea>
                                @break
                            @default
                                <input id="{{ $name }}" name="{{ $name }}" value="{{ $value }}" class="a-input"
                                       @if ($field['type'] === 'number') type="number" min="0" step="1" @elseif ($field['type'] === 'decimal') type="number" min="0" step="any" @endif>
                        @endswitch
                    @endif
                    @if (! empty($field['help']))<p class="mt-1 text-xs text-slate-500">{{ $field['help'] }}</p>@endif
                </div>
            @endforeach
        </div>
    @endforeach

    <div class="mt-4"><button class="a-btn-primary">Settings save karein</button></div>
</form>
@endsection
