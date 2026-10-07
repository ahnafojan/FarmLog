<div class="farm-profile-shell">
    <a href="#profile-content" class="fi-skip-link fi-sr-only">Langsung ke konten</a>

    <div class="farm-topbar">
        <x-farmlog.mobile-header :tenant="$tenant" :usahas="$usahas" :show-user-menu="true" />
    </div>

    <main id="profile-content" tabindex="-1" class="farm-profile-content">
        <header class="flex flex-col gap-1">
            <h1 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white">Profil akun</h1>
            <p class="farm-muted">Kelola informasi pribadi dan keamanan akun Anda.</p>
        </header>

        {{ $this->content }}
        @include('filament.hooks.install-app')
    </main>

    <x-farmlog.bottom-navigation :tenant="$tenant" />
    @include('filament.hooks.catat-button')
    <x-filament-actions::modals />
</div>
