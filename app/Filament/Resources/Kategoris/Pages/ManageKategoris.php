<?php

namespace App\Filament\Resources\Kategoris\Pages;

use App\Filament\Actions\CatatTransaksiAction;
use App\Filament\Resources\Kategoris\KategoriResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageKategoris extends ManageRecords
{
    protected static string $resource = KategoriResource::class;

    public function catatAction(): CreateAction
    {
        return CatatTransaksiAction::make('catat');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->mutateFormDataUsing(fn (array $data): array => KategoriResource::prepareData($data)),
        ];
    }
}
