@props(['tenant', 'usahas'])

@php
    use App\Filament\Pages\Dashboard;
    use App\Filament\Resources\Kategoris\KategoriResource;
@endphp

<header {{ $attributes->class(['farm-mobile-header']) }}>
    <div class="min-w-0 flex-1">
        <div class="[&_.fi-logo]:h-7 [&_img]:max-w-11 [&_img]:object-contain"><x-filament-panels::logo /></div>
        <x-filament::dropdown placement="bottom-start">
            <x-slot name="trigger">
                <button type="button" class="flex min-h-11 max-w-full items-center gap-1 text-sm text-gray-500 dark:text-gray-400 [&_span]:truncate" aria-label="Ganti usaha">
                    <span>{{ $tenant->nama }}</span>
                    <x-filament::icon icon="heroicon-o-chevron-down" class="size-5 shrink-0" />
                </button>
            </x-slot>
            <x-filament::dropdown.list>
                @foreach ($usahas as $usaha)
                    <x-filament::dropdown.list.item tag="a" :href="Dashboard::getUrl(tenant: $usaha)" :icon="$usaha->is($tenant) ? 'heroicon-o-check' : 'heroicon-o-building-storefront'">
                        {{ $usaha->nama }}
                    </x-filament::dropdown.list.item>
                @endforeach
                <x-filament::dropdown.list.item tag="a" :href="filament()->getTenantRegistrationUrl()" icon="heroicon-o-plus">Tambah usaha</x-filament::dropdown.list.item>
                <x-filament::dropdown.list.item tag="a" :href="filament()->getTenantProfileUrl()" icon="heroicon-o-cog-6-tooth">Pengaturan usaha</x-filament::dropdown.list.item>
                <x-filament::dropdown.list.item tag="a" :href="KategoriResource::getUrl()" icon="heroicon-o-tag">Kategori</x-filament::dropdown.list.item>
            </x-filament::dropdown.list>
        </x-filament::dropdown>
    </div>
    <div class="flex shrink-0 items-center gap-1">
        <button type="button" class="flex size-11 items-center justify-center rounded-lg text-gray-500 dark:text-gray-400" aria-label="Ganti tema" x-data x-on:click="$dispatch('theme-changed', $store.theme === 'dark' ? 'light' : 'dark')">
            <x-filament::icon icon="heroicon-o-moon" class="size-5 dark:hidden" />
            <x-filament::icon icon="heroicon-o-sun" class="hidden size-5 dark:block" />
        </button>
        <a class="flex size-11 items-center justify-center rounded-lg text-primary-600 dark:text-primary-400" href="{{ filament()->getProfileUrl() }}" aria-label="Profil akun">
            <x-filament::icon icon="heroicon-o-user" class="size-5 shrink-0" />
        </a>
    </div>
</header>
