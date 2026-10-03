@props(['tenant' => filament()->getTenant()])

@php
    use App\Filament\Pages\Dashboard;
    use App\Filament\Pages\Laporan;
    use App\Filament\Resources\Sikluses\SiklusResource;
    use App\Filament\Resources\Transaksis\TransaksiResource;
@endphp

<nav {{ $attributes->class(['farm-bottom-nav']) }} aria-label="Navigasi utama">
    <a wire:navigate href="{{ $tenant ? Dashboard::getUrl(tenant: $tenant) : filament()->getUrl() }}"
        @if (request()->routeIs('filament.app.pages.dashboard')) aria-current="page" @endif><x-filament::icon icon="heroicon-o-home"
            class="size-5 shrink-0" /><span>Beranda</span></a>
    <a wire:navigate
        href="{{ $tenant ? SiklusResource::getUrl(tenant: $tenant) : filament()->getTenantRegistrationUrl() }}"
        @if (request()->routeIs('filament.app.resources.sikluses.*')) aria-current="page" @endif><x-filament::icon
            icon="heroicon-o-arrows-right-left" class="size-5 shrink-0" /><span>Siklus</span></a>
    <a wire:navigate
        href="{{ $tenant ? TransaksiResource::getUrl(tenant: $tenant) : filament()->getTenantRegistrationUrl() }}"
        @if (request()->routeIs('filament.app.resources.transaksis.*')) aria-current="page" @endif><x-filament::icon icon="heroicon-o-document-text"
            class="size-5 shrink-0" /><span>Transaksi</span></a>
    <a wire:navigate href="{{ $tenant ? Laporan::getUrl(tenant: $tenant) : filament()->getTenantRegistrationUrl() }}"
        @if (request()->routeIs('filament.app.pages.laporan')) aria-current="page" @endif>
        <x-filament::icon icon="heroicon-o-document-chart-bar" class="size-5 shrink-0" />

        <span>Laporan</span>
    </a>
</nav>
