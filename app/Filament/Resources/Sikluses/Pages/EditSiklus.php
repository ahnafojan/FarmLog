<?php

namespace App\Filament\Resources\Sikluses\Pages;

use App\Filament\Resources\Sikluses\SiklusResource;
use Filament\Resources\Pages\EditRecord;

class EditSiklus extends EditRecord
{
    protected static string $resource = SiklusResource::class;

    protected static ?string $title = 'Detail siklus';

    public function getSubheading(): ?string
    {
        return $this->getRecord()->nama;
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [...parent::getPageClasses(), 'farm-cycle-edit'];
    }

    protected function getHeaderActions(): array
    {
        return [SiklusResource::closeAction()];
    }
}
