@props(['icon', 'title' => null, 'description' => null])

<div {{ $attributes->class(['farm-card', 'flex flex-col items-center gap-4 text-center']) }}>
    <x-filament::icon :icon="$icon" class="size-8 shrink-0 text-gray-400" />

    @if ($title)
        <h3 class="font-semibold">{{ $title }}</h3>
    @endif

    @if ($description)
        <p class="farm-muted">{{ $description }}</p>
    @endif

    {{ $slot }}

    @isset($actions)
        {{ $actions }}
    @endisset
</div>
