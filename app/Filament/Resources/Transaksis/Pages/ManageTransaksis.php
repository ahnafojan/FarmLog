<?php

namespace App\Filament\Resources\Transaksis\Pages;

use App\Filament\Resources\Transaksis\TransaksiResource;
use App\Models\Transaksi;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTransaksis extends ManageRecords
{
    protected static string $resource = TransaksiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('+ Catat')
                ->using(fn (array $data, CreateAction $action): Transaksi => TransaksiResource::saveTransaction($data, $action)),
        ];
    }
}
