{{-- Shown inside the Android app when notifications are not enabled. --}}
<div x-data="notifPrompt()" x-show="show" x-cloak
     class="mb-4 flex items-center gap-3 rounded-2xl border border-brand/30 bg-brand/10 p-4">
    <x-icon name="bolt" class="size-6 shrink-0 text-brand"/>
    <div class="min-w-0 flex-1">
        <div class="text-sm font-semibold">Turn on notifications</div>
        <div class="text-xs text-app-muted">Get notified about new videos and rewards.</div>
    </div>
    <button @click="enable()" class="btn-primary !px-4 !py-2 text-xs">Enable</button>
    <button @click="dismiss()" aria-label="Dismiss" class="grid size-8 place-items-center rounded-full text-app-muted hover:bg-white/10"><x-icon name="x" class="size-4"/></button>
</div>
