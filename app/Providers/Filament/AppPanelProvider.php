<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Tenancy\EditUsaha;
use App\Filament\Pages\Tenancy\RegisterUsaha;
use App\Models\Usaha;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Illuminate\View\View;

class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('app')
            ->path('app')
            ->viteTheme('resources/css/filament.css')
            ->brandLogo(fn (): View => view('brand-logo', ['logoPath' => 'logos-black.png']))
            ->darkModeBrandLogo(fn (): View => view('brand-logo', ['logoPath' => 'logos-white.png']))
            ->brandLogoHeight('2rem')
            ->login()
            ->registration()
            ->passwordReset()
            ->profile(EditProfile::class)
            ->tenant(Usaha::class, ownershipRelationship: 'usaha')
            ->tenantRegistration(RegisterUsaha::class)
            ->tenantProfile(EditUsaha::class)
            ->renderHook(PanelsRenderHook::CONTENT_START, function (): View|string {
                $user = Filament::auth()->user();
                $tenant = Filament::getTenant();
                $panel = Filament::getCurrentPanel();

                if (! ($user instanceof User) || ! ($tenant instanceof Usaha) || $panel === null) {
                    return '';
                }

                return view('filament.hooks.mobile-header', [
                    'tenant' => $tenant,
                    'usahas' => $user->getTenants($panel),
                ]);
            })
            ->renderHook(PanelsRenderHook::CONTENT_END, fn (): View|string => Filament::auth()->check() && Filament::getTenant() instanceof Usaha
                    ? view('filament.hooks.bottom-navigation')
                    : '')
            ->databaseTransactions()
            ->bootUsing(function (): void {
                app()->setLocale('id');

                TextInput::configureUsing(function (TextInput $field): void {
                    if (Filament::getCurrentPanel()?->getId() !== 'app') {
                        return;
                    }

                    $placeholder = match ($field->getName()) {
                        'name' => 'Masukkan nama lengkap',
                        'email' => 'Contoh: nama@email.com',
                        'password' => 'Masukkan kata sandi',
                        'passwordConfirmation' => 'Ulangi kata sandi',
                        'currentPassword' => 'Masukkan kata sandi saat ini',
                        default => null,
                    };

                    if ($placeholder !== null) {
                        $field->placeholder($placeholder);
                    }
                });
            })
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
