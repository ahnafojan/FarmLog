<?php

namespace App\Filament\Resources\Sikluses\Pages;

use App\Actions\GetSiklusCards;
use App\Filament\Actions\CatatTransaksiAction;
use App\Filament\Resources\Sikluses\SiklusResource;
use App\Models\Usaha;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

class ListSikluses extends ListRecords
{
    protected static string $resource = SiklusResource::class;

    protected string $view = 'filament.resources.sikluses.list-sikluses';

    #[Locked]
    public string $cycleStatus = 'berjalan';

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function selectStatus(string $status): void
    {
        abort_unless(in_array($status, ['berjalan', 'selesai'], true), 422);

        $this->cycleStatus = $status;
        $this->resetPage('cyclePage');

        unset($this->cycleData);
    }

    #[Computed]
    public function cycleData(): array
    {
        $usaha = Filament::getTenant();

        abort_unless($usaha instanceof Usaha, 404);

        return app(GetSiklusCards::class)->handle(
            $usaha,
            $this->cycleStatus,
        );
    }

    public function catatAction(): CreateAction
    {
        return CatatTransaksiAction::make('catat')
            ->after(function (): void {
                unset($this->cycleData);
            });
    }
}