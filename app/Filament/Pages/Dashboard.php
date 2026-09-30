<?php

namespace App\Filament\Pages;

use App\Actions\GetDashboardSummary;
use App\Filament\Resources\Sikluses\SiklusResource;
use App\Filament\Resources\Transaksis\TransaksiResource;
use App\Models\Transaksi;
use App\Models\Usaha;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Beranda';

    /**
     * @var array{periode?: string, tahun?: int|string|null, dasar?: string}
     */
    public array $filters = [];

    public function mount(): void
    {
        $this->filtersForm->fill([
            'periode' => 'bulan_ini',
            'tahun' => now()->year,
            'dasar' => $this->usaha()->rekap_dasar,
        ]);
    }

    protected function usaha(): Usaha
    {
        $usaha = Filament::getTenant();
        abort_unless($usaha instanceof Usaha, 404);
        Gate::authorize('view', $usaha);

        return $usaha;
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->statePath('filters')->columns(['default' => 1, 'md' => 3])->components([
            Select::make('periode')->label('Periode')->placeholder('Pilih periode')->options([
                'bulan_ini' => 'Bulan ini',
                'tahun_ini' => 'Tahun ini',
                'pilih_tahun' => 'Pilih tahun',
            ])->required()->live(),
            TextInput::make('tahun')->label('Tahun')->placeholder(fn (): string => 'Contoh: '.now()->year)->integer()->minValue(1900)->maxValue(9999)
                ->required()->live(onBlur: true)->visible(fn (Get $get): bool => $get('periode') === 'pilih_tahun'),
            Select::make('dasar')->label('Dasar rekap')->placeholder('Pilih dasar rekap')->options([
                'siklus_selesai' => 'Siklus selesai',
                'tanggal_transaksi' => 'Tanggal transaksi',
            ])->required()->live(),
        ]);
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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('buatSiklus')->label('Buat siklus')->color('gray')->url(fn (): string => SiklusResource::getUrl('create')),
            CreateAction::make('catat')->label('+ Catat')->model(Transaksi::class)->createAnother(false)
                ->schema(fn (Schema $schema): Schema => TransaksiResource::form($schema))
                ->using(fn (array $data, CreateAction $action): Transaksi => TransaksiResource::saveTransaction($data, $action))
                ->after(function (): void {
                    unset($this->dashboardData);
                }),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([EmbeddedSchema::make('filtersForm')]),
            Grid::make(['default' => 1, 'sm' => 2, 'xl' => 3])->schema([
                $this->summaryCard('pemasukan', 'Pemasukan', 'success'),
                $this->summaryCard('operasional', 'Biaya operasional', 'danger'),
                $this->summaryCard('laba_bersih', 'Laba'),
            ]),
            Section::make()->columns(['default' => 1, 'md' => 2])->schema([
                TextEntry::make('investasi')->label('Investasi')->color('gray')->money('IDR', locale: 'id', decimalPlaces: 0)
                    ->state(fn (): int => $this->dashboardData['summary']['investasi']),
                TextEntry::make('berjalan')->label('Berjalan · laba sementara')->money('IDR', locale: 'id', decimalPlaces: 0)
                    ->state(fn (): int => $this->dashboardData['running']['laba_bersih'] ?? 0)
                    ->visible(fn (): bool => $this->dashboardData['running'] !== null),
            ]),
            Section::make('Siklus berjalan terbaru')
                ->visible(fn (): bool => $this->dashboardData['cycle'] !== null)
                ->headerActions([
                    Action::make('detailSiklus')->label('Detail siklus')->url(fn (): string => SiklusResource::getUrl('edit', [
                        'record' => $this->dashboardData['cycle']['id'] ?? 0,
                    ])),
                ])
                ->columns(['default' => 1, 'sm' => 2, 'xl' => 3])->schema([
                    TextEntry::make('nama_siklus')->label('Siklus')->state(fn (): ?string => $this->dashboardData['cycle']['nama'] ?? null),
                    TextEntry::make('jumlah')->label('Jumlah awal')->numeric(locale: 'id')->suffix(' ekor')
                        ->state(fn (): ?int => $this->dashboardData['cycle']['jumlah'] ?? null),
                    TextEntry::make('umur')->label('Umur')->suffix(' hari')
                        ->state(fn (): ?int => $this->dashboardData['cycle']['umur'] ?? null),
                    TextEntry::make('penanda')->label('Penanda berikutnya')->placeholder('Tidak ada jadwal berikutnya')
                        ->state(fn (): ?string => $this->dashboardData['cycle']['penanda'] ?? null),
                    TextEntry::make('jadwal')->label('Jadwal')->placeholder('—')->state(function (): ?string {
                        $cycle = $this->dashboardData['cycle'];

                        if (($cycle['sisa_hari'] ?? null) === null) {
                            return null;
                        }

                        return $cycle['tanggal_penanda'].' · '.($cycle['sisa_hari'] === 0 ? 'Hari ini' : $cycle['sisa_hari'].' hari lagi');
                    }),
                    TextEntry::make('laba_siklus')->label('Laba sementara')->money('IDR', locale: 'id', decimalPlaces: 0)
                        ->state(fn (): int => $this->dashboardData['cycle']['laba_bersih'] ?? 0),
                ]),
            Section::make('Belum ada siklus berjalan')->visible(fn (): bool => $this->dashboardData['cycle'] === null)
                ->footerActions([Action::make('siklusPertama')->label('Buat siklus')->url(fn (): string => SiklusResource::getUrl('create'))]),
            Section::make('Transaksi terbaru')->headerActions([
                Action::make('semuaTransaksi')->label('Lihat semua')->url(fn (): string => TransaksiResource::getUrl()),
            ])->schema([
                TextEntry::make('transaksi_kosong')->hiddenLabel()->state('Belum ada transaksi pada periode ini')
                    ->visible(fn (): bool => $this->dashboardData['transactions'] === []),
                RepeatableEntry::make('transaksi_terbaru')->hiddenLabel()
                    ->state(fn (): array => $this->dashboardData['transactions'])
                    ->columns(['default' => 1, 'sm' => 2, 'xl' => 5])->schema([
                        TextEntry::make('tanggal')->label('Tanggal'),
                        TextEntry::make('kategori')->label('Kategori'),
                        TextEntry::make('siklus')->label('Siklus'),
                        TextEntry::make('arah')->label('Jenis')->badge()
                            ->formatStateUsing(fn (string $state): string => $state === 'pemasukan' ? 'Masuk' : 'Keluar')
                            ->color(fn (string $state): string => $state === 'pemasukan' ? 'success' : 'danger'),
                        TextEntry::make('total')->label('Total')->money('IDR', locale: 'id', decimalPlaces: 0),
                    ]),
            ]),
        ]);
    }

    protected function summaryCard(string $key, string $label, ?string $color = null): Section
    {
        return Section::make($label)->schema([
            TextEntry::make($key)->hiddenLabel()->size(TextSize::Large)
                ->state(fn (): int => $this->dashboardData['summary'][$key])
                ->money('IDR', locale: 'id', decimalPlaces: 0)
                ->color(fn (): string => $color ?? ($this->dashboardData['summary'][$key] < 0 ? 'danger' : 'success')),
        ]);
    }
}
