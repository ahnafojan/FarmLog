<?php

namespace App\Filament\Pages\Tenancy;

use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class EditUsaha extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Pengaturan usaha';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('nama')->label('Nama usaha')->placeholder('Contoh: Ternak Maju Bersama')->required()->maxLength(150)
                ->rules(fn (): array => [
                    Rule::unique('usahas', 'nama')->where('user_id', Filament::auth()->id())
                        ->whereNull('deleted_at')->ignore($this->tenant->id),
                ]),
            Select::make('rekap_dasar')->label('Dasar rekap')->placeholder('Pilih dasar rekap')->required()->options([
                'siklus_selesai' => 'Siklus selesai',
                'tanggal_transaksi' => 'Tanggal transaksi',
            ]),
        ]);
    }
}
