<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Livewire\Attributes\Url;

class EditProfile extends BaseEditProfile
{
    protected string $view = 'filament.pages.auth.edit-profile';

    #[Url(as: 'tenant')]
    public ?string $tenantId = null;

    public function getLayout(): string
    {
        return 'filament-panels::components.layout.base';
    }

    protected function getViewData(): array
    {
        $usahas = $this->getUser()->getTenants(Filament::getCurrentPanel());

        return [
            'usahas' => $usahas,
            'tenant' => $usahas->firstWhere('id', $this->tenantId) ?? $usahas->first(),
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
