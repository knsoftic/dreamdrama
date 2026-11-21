@php
    $message = session('success');
    $error = $errors->any() && ! ($hideErrors ?? false) ? $errors->first() : null;
@endphp
@if ($message || $error)
    <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 4500)" x-show="show" x-transition.opacity.duration.300ms
         class="pointer-events-none fixed inset-x-0 top-3 z-[70] flex justify-center px-4 safe-top">
        <div @click="show = false"
             class="pointer-events-auto flex max-w-md items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium shadow-2xl animate-pop-in {{ $message ? 'bg-emerald-500 text-white' : 'bg-rose-500 text-white' }}">
            <x-icon :name="$message ? 'check' : 'x'" class="size-5 shrink-0" stroke="2.5"/>
            <span>{{ $message ?? $error }}</span>
        </div>
    </div>
@endif
