<?php

namespace App\Filament\Actions;

use App\Filament\Resources\Transaksis\TransaksiResource;
use App\Models\Transaksi;
use Filament\Actions\CreateAction;
use Filament\Schemas\Schema;

class CatatTransaksiAction extends CreateAction
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Catat transaksi')
            ->modalHeading('Catat transaksi')
            ->modalSubmitActionLabel('Simpan')
            ->successNotificationTitle('Transaksi berhasil dicatat')
            ->model(Transaksi::class)
            ->createAnother(false)
            ->schema(fn (Schema $schema): Schema => TransaksiResource::form($schema))
            ->using(fn (array $data, CreateAction $action): Transaksi => TransaksiResource::saveTransaction($data, $action));
    }
}
