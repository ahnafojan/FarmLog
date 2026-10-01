@props(['icon', 'title' => null, 'description' => null])

<div {{ $attributes->class(['farm-card', 'farm-empty']) }}>
    <x-filament::icon :icon="$icon" />

    @if ($title)
        <h3>{{ $title }}</h3>
    @endif

    @if ($description)
        <p>{{ $description }}</p>
    @endif

    {{ $slot }}

    @isset($actions)
        {{ $actions }}
    @endisset
</div>
