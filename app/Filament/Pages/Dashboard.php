<?php

namespace App\Filament\Pages;

use App\Actions\GetDashboardSummary;
use App\Filament\Actions\CatatTransaksiAction;
use App\Models\Usaha;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard as BaseDashboard;
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
     * @var array{periode?: string, tahun?: int|string|null, bulan?: int|string|null, dasar?: string}
     */
    public array $filters = [];

    public function mount(): void
    {
        $this->filters = [
            'periode' => 'bulan_ini',
            'tahun' => now()->year,
            'bulan' => now()->month,
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

    public function updatedFilters(): void
    {
        unset($this->dashboardData);

        $this->validate([
            'filters.periode' => ['required', Rule::in(['bulan_ini', 'pilih_bulan', 'tahun_ini', 'pilih_tahun'])],
            'filters.tahun' => ['required_if:filters.periode,pilih_bulan,pilih_tahun', 'nullable', 'integer', 'between:1900,9999'],
            'filters.bulan' => ['required_if:filters.periode,pilih_bulan', 'nullable', 'integer', 'between:1,12'],
            'filters.dasar' => ['required', Rule::in(['siklus_selesai', 'tanggal_transaksi'])],
        ]);

        $usaha = $this->usaha();

        if ($usaha->rekap_dasar !== $this->filters['dasar']) {
            Gate::authorize('update', $usaha);
            $usaha->update(['rekap_dasar' => $this->filters['dasar']]);
        }
    }

    /**
     * @return array{summary: array<string, int>, chart: array{labels: list<string>, pemasukan: list<int>, laba_bersih: list<int>}, running: array<string, int>|null, cycle: array<string, mixed>|null, transactions: array<int, array<string, mixed>>}
     */
    #[Computed]
    public function dashboardData(): array
    {
        $usaha = $this->usaha();
        $period = $this->filters['periode'] ?? 'bulan_ini';
        $basis = $this->filters['dasar'] ?? $usaha->rekap_dasar;
        $year = filter_var($this->filters['tahun'] ?? now()->year, FILTER_VALIDATE_INT);
        $month = filter_var($this->filters['bulan'] ?? now()->month, FILTER_VALIDATE_INT);

        return app(GetDashboardSummary::class)->handle(
            $usaha,
            in_array($period, ['bulan_ini', 'pilih_bulan', 'tahun_ini', 'pilih_tahun'], true) ? $period : 'bulan_ini',
            $year !== false && $year >= 1900 && $year <= 9999 ? $year : now()->year,
            in_array($basis, ['siklus_selesai', 'tanggal_transaksi'], true) ? $basis : $usaha->rekap_dasar,
            $month !== false && $month >= 1 && $month <= 12 ? $month : now()->month,
        );
    }

    public function catatAction(): CreateAction
    {
        return CatatTransaksiAction::make('catat')
            ->after(function (): void {
                unset($this->dashboardData);
            });
    }
}
