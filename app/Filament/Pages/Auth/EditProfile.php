<?php

namespace App\Filament\Pages\Auth;

use App\Filament\Actions\CatatTransaksiAction;
use Filament\Actions\CreateAction;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;

class EditProfile extends BaseEditProfile
{
    protected string $view = 'filament.pages.auth.edit-profile';

    #[Locked]
    #[Url(as: 'tenant')]
    public ?string $tenantSlug = null;

    public function booted(): void
    {
        $usahas = $this->getUser()->getTenants(Filament::getCurrentPanel());

        Filament::setTenant($usahas->firstWhere('slug', $this->tenantSlug) ?? $usahas->first());
    }

    public function catatAction(): CreateAction
    {
        return CatatTransaksiAction::make('catat');
    }

    public function getLayout(): string
    {
        return 'filament-panels::components.layout.base';
    }

    protected function getViewData(): array
    {
        return [
            'usahas' => $this->getUser()->getTenants(Filament::getCurrentPanel()),
            'tenant' => Filament::getTenant(),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Informasi akun')
                ->description('Perbarui nama dan alamat email akun Anda.')
                ->schema([
                    $this->getNameFormComponent(),
                    $this->getEmailFormComponent(),
                ])
                ->columns(['sm' => 2]),
            Section::make('Keamanan akun')
                ->description('Kosongkan kata sandi baru jika tidak ingin mengubahnya.')
                ->schema([
                    $this->getPasswordFormComponent(),
                    $this->getPasswordConfirmationFormComponent(),
                    $this->getCurrentPasswordFormComponent()->columnSpanFull(),
                ])
                ->columns(['sm' => 2]),
        ]);
    }
}
