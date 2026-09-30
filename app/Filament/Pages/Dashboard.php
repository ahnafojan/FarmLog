<?php

namespace App\Filament\Pages;

use App\Actions\GetDashboardSummary;
use App\Filament\Resources\Transaksis\TransaksiResource;
use App\Models\Transaksi;
use App\Models\Usaha;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Beranda';

    protected string $view = 'dashboard';

    /** @var array<string, string> */
    protected array $extraBodyAttributes = ['class' => 'farmlog-dashboard'];

    /**
     * @var array{periode?: string, tahun?: int|string|null, dasar?: string}
     */
    public array $filters = [];

    public function mount(): void
    {
        $this->filters = [
            'periode' => 'bulan_ini',
            'tahun' => now()->year,
            'dasar' => $this->usaha()->rekap_dasar,
        ];
    }

    protected function usaha(): Usaha
    {
        $usaha = Filament::getTenant();
        abort_unless($usaha instanceof Usaha, 404);
        Gate::authorize('view', $usaha);

        return $usaha;
    }

    /** @return Collection<int, Usaha> */
    #[Computed]
    public function availableUsahas(): Collection
    {
        return Filament::auth()->user()->getTenants(Filament::getCurrentPanel());
    }

    public function updatedFilters(): void
    {
        unset($this->dashboardData);

        $this->validate([
            'filters.periode' => ['required', Rule::in(['bulan_ini', 'tahun_ini', 'pilih_tahun'])],
            'filters.tahun' => ['required_if:filters.periode,pilih_tahun', 'nullable', 'integer', 'between:1900,9999'],
            'filters.dasar' => ['required', Rule::in(['siklus_selesai', 'tanggal_transaksi'])],
        ]);

        $usaha = $this->usaha();

        if ($usaha->rekap_dasar !== $this->filters['dasar']) {
            Gate::authorize('update', $usaha);
            $usaha->update(['rekap_dasar' => $this->filters['dasar']]);
        }
    }

    /**
     * @return array{summary: array<string, int>, running: array<string, int>|null, cycle: array<string, mixed>|null, transactions: array<int, array<string, mixed>>}
     */
    #[Computed]
    public function dashboardData(): array
    {
        $usaha = $this->usaha();
        $period = $this->filters['periode'] ?? 'bulan_ini';
        $basis = $this->filters['dasar'] ?? $usaha->rekap_dasar;
        $year = filter_var($this->filters['tahun'] ?? now()->year, FILTER_VALIDATE_INT);

        return app(GetDashboardSummary::class)->handle(
            $usaha,
            in_array($period, ['bulan_ini', 'tahun_ini', 'pilih_tahun'], true) ? $period : 'bulan_ini',
            $year !== false && $year >= 1900 && $year <= 9999 ? $year : now()->year,
            in_array($basis, ['siklus_selesai', 'tanggal_transaksi'], true) ? $basis : $usaha->rekap_dasar,
        );
    }

    public function catatAction(): CreateAction
    {
        return CreateAction::make('catat')->label('Catat')->modalHeading('Catat transaksi')
            ->modalSubmitActionLabel('Simpan')
            ->model(Transaksi::class)->createAnother(false)
            ->schema(fn (Schema $schema): Schema => TransaksiResource::form($schema))
            ->using(fn (array $data, CreateAction $action): Transaksi => TransaksiResource::saveTransaction($data, $action))
            ->after(function (): void {
                unset($this->dashboardData);
            });
    }
}
