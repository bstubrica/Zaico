@props(['title', 'description' => null, 'icon' => 'inbox'])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center px-6 py-14 text-center']) }}>
    <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-400">
        <x-icon :name="$icon" :size="28" />
    </span>
    <h3 class="mt-4 text-sm font-semibold text-slate-800">{{ $title }}</h3>
    @if ($description)
        <p class="mt-1 max-w-sm text-sm text-slate-500">{{ $description }}</p>
    @endif
    @isset($action)
        <div class="mt-4">{{ $action }}</div>
    @endisset
</div>
