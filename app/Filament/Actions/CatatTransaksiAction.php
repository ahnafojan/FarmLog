<?php

namespace App\Filament\Actions;

use App\Filament\Resources\Transaksis\TransaksiResource;
use App\Models\Transaksi;
use App\Models\Usaha;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class CatatTransaksiAction extends CreateAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Catat')
            ->icon(Heroicon::OutlinedPlus)
            ->extraAttributes(['class' => 'farm-catat'])
            ->visible(fn (): bool => Filament::getTenant() instanceof Usaha)
            ->authorize(fn (): bool => TransaksiResource::canCreate())
            ->modalHeading('Catat transaksi')
            ->modalSubmitActionLabel('Simpan')
            ->successNotificationTitle('Transaksi berhasil dicatat')
            ->model(Transaksi::class)
            ->createAnother(false)
            ->schema(fn (Schema $schema): Schema => TransaksiResource::form($schema))
            ->using(fn (array $data, CreateAction $action): Transaksi => TransaksiResource::saveTransaction($data, $action));
    }
}
