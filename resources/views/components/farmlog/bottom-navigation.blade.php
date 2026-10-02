@props(['tenant' => filament()->getTenant()])

@php
    use App\Filament\Pages\Dashboard;
    use App\Filament\Resources\Sikluses\SiklusResource;
    use App\Filament\Resources\Transaksis\TransaksiResource;
@endphp

<nav {{ $attributes->class(['farm-bottom-nav']) }} aria-label="Navigasi utama">
    <a href="{{ $tenant ? Dashboard::getUrl(tenant: $tenant) : filament()->getUrl() }}" @if (request()->routeIs('filament.app.pages.dashboard')) aria-current="page" @endif><x-filament::icon icon="heroicon-o-home" class="size-5 shrink-0" /><span>Beranda</span></a>
    <a href="{{ $tenant ? SiklusResource::getUrl(tenant: $tenant) : filament()->getTenantRegistrationUrl() }}" @if (request()->routeIs('filament.app.resources.sikluses.*')) aria-current="page" @endif><x-filament::icon icon="heroicon-o-arrows-right-left" class="size-5 shrink-0" /><span>Siklus</span></a>
    <a href="{{ $tenant ? TransaksiResource::getUrl(tenant: $tenant) : filament()->getTenantRegistrationUrl() }}" @if (request()->routeIs('filament.app.resources.transaksis.*')) aria-current="page" @endif><x-filament::icon icon="heroicon-o-document-text" class="size-5 shrink-0" /><span>Transaksi</span></a>
    <a href="{{ filament()->getProfileUrl(['tenant' => $tenant?->slug]) }}" @if (request()->routeIs('filament.app.auth.profile')) aria-current="page" @endif><x-filament::icon icon="heroicon-o-user-circle" class="size-5 shrink-0" /><span>Akun</span></a>
</nav>
