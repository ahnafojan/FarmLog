@php
    use App\Filament\Pages\Dashboard;
    use App\Filament\Resources\Sikluses\SiklusResource;
    use App\Filament\Resources\Transaksis\TransaksiResource;
@endphp

<nav {{ $attributes->class(['farm-bottom-nav']) }} aria-label="Navigasi utama">
        <a href="{{ Dashboard::getUrl() }}" @if (request()->routeIs('filament.app.pages.dashboard')) aria-current="page" @endif><x-filament::icon icon="heroicon-o-home" /><span>Beranda</span></a>
    <a href="{{ SiklusResource::getUrl() }}" @if (request()->routeIs('filament.app.resources.sikluses.*')) aria-current="page" @endif><x-filament::icon icon="heroicon-o-arrows-right-left" /><span>Siklus</span></a>
    <a href="{{ TransaksiResource::getUrl() }}" @if (request()->routeIs('filament.app.resources.transaksis.*')) aria-current="page" @endif><x-filament::icon icon="heroicon-o-document-text" /><span>Transaksi</span></a>
    <a href="{{ filament()->getProfileUrl() }}" @if (request()->routeIs('filament.app.auth.profile')) aria-current="page" @endif><x-filament::icon icon="heroicon-o-user-circle" /><span>Akun</span></a>
</nav>
