@props(['tenant', 'usahas'])

@php
    use App\Filament\Pages\Dashboard;
    use App\Filament\Resources\Kategoris\KategoriResource;
@endphp

<header {{ $attributes->class(['farm-mobile-header']) }}>
    <div class="farm-brand">
        <div class="farm-brand-logo"><x-filament-panels::logo /></div>
        <x-filament::dropdown placement="bottom-start">
            <x-slot name="trigger">
                <button type="button" class="farm-tenant-trigger" aria-label="Ganti usaha">
                    <span>{{ $tenant->nama }}</span>
                    <x-filament::icon icon="heroicon-o-chevron-down" />
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
    <div class="farm-header-actions">
        <button type="button" class="farm-theme-toggle" aria-label="Ganti tema" x-data x-on:click="$dispatch('theme-changed', $store.theme === 'dark' ? 'light' : 'dark')">
            <x-filament::icon icon="heroicon-o-moon" class="dark:hidden" />
            <x-filament::icon icon="heroicon-o-sun" class="hidden dark:block" />
        </button>
        <a class="farm-profile" href="{{ filament()->getProfileUrl() }}" aria-label="Profil akun">
            <x-filament::icon icon="heroicon-o-user" />
        </a>
    </div>
</header>
