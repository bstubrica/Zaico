@props(['title', 'subtitle' => null, 'icon' => null])

<div class="flex flex-wrap items-center justify-between gap-4">
    <div class="flex items-center gap-3">
        @if ($icon)
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-600 text-white shadow-sm">
                <x-icon :name="$icon" :size="22" />
            </span>
        @endif
        <div>
            <h1 class="text-xl font-bold leading-tight text-slate-900">{{ $title }}</h1>
            @if ($subtitle)
                <p class="mt-0.5 text-sm text-slate-500">{{ $subtitle }}</p>
            @endif
        </div>
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
