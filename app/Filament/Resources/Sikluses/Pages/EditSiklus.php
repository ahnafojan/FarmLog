<?php

namespace App\Filament\Resources\Sikluses\Pages;

use App\Filament\Resources\Sikluses\SiklusResource;
use Filament\Resources\Pages\EditRecord;

class EditSiklus extends EditRecord
{
    protected static string $resource = SiklusResource::class;

    protected function getHeaderActions(): array
    {
        return [SiklusResource::closeAction()];
    }
}
