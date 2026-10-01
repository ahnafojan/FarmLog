<?php

namespace App\Filament\Resources\Transaksis\Pages;

use App\Filament\Actions\CatatTransaksiAction;
use App\Filament\Resources\Transaksis\TransaksiResource;
use Filament\Resources\Pages\ManageRecords;

class ManageTransaksis extends ManageRecords
{
    protected static string $resource = TransaksiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CatatTransaksiAction::make(),
        ];
    }
}
