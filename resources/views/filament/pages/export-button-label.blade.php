<span x-bind:aria-busy="activeExport === @js($jenis)">
    <span class="inline-flex items-center gap-2" x-show="activeExport !== @js($jenis)">
        <x-filament::icon icon="heroicon-o-arrow-down-tray" class="size-5" />
        <span>{{ $label }}</span>
    </span>
    <span class="inline-flex items-center gap-2" x-cloak x-show="activeExport === @js($jenis)"
        role="status">
        <x-filament::loading-indicator class="size-5" />
        <span>Tunggu ya..</span>
    </span>
</span>
