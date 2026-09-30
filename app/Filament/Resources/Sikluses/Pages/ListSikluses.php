<?php

namespace App\Filament\Resources\Sikluses\Pages;

use App\Filament\Resources\Sikluses\SiklusResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSikluses extends ListRecords
{
    protected static string $resource = SiklusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
