<?php

namespace App\Filament\Pages;

use App\Filament\Actions\CatatTransaksiAction;
use App\Models\Usaha;
use App\Models\User;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;

class Laporan extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static ?string $navigationLabel = 'Laporan';

    protected static ?string $title = 'Laporan';

    protected string $view = 'filament.pages.laporan';

    /**
     * @var array{periode?: string, tahun?: int, bulan?: int, sikluses_id?: int|null}
     */
    #[Locked]
    public array $filters = [];

    public function mount(): void
    {
        $this->filters = [
            'periode' => 'bulanan',
            'tahun' => now()->year,
            'bulan' => now()->month,
            'sikluses_id' => null,
        ];
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->filterAction(),
            $this->exportPenjualanAction(),
            $this->exportPengeluaranAction(),
        ];
    }

    public function catatAction(): CreateAction
    {
        return CatatTransaksiAction::make('catat');
    }

    protected function filterAction(): Action
    {
        return Action::make('filter')
            ->label('Filter')
            ->icon(Heroicon::OutlinedFunnel)
            ->color('gray')
            ->modalHeading('Filter laporan')
            ->modalSubmitActionLabel('Terapkan filter')
            ->fillForm(fn (): array => $this->filters)
            ->schema([
                Select::make('sikluses_id')
                    ->label('Siklus')
                    ->placeholder('Semua siklus')
                    ->searchable()
                    ->searchPrompt('Cari nama siklus')
                    ->options(fn (): array => Filament::getTenant()->sikluses()
                        ->orderByDesc('tanggal_mulai')->orderByDesc('id')
                        ->pluck('nama', 'id')->all())
                    ->rules(['nullable', 'integer'])
                    ->helperText('Kosongkan untuk semua siklus, termasuk transaksi umum. Periode tetap mengikuti tanggal transaksi.'),

                Select::make('periode')
                    ->label('Periode')
                    ->options([
                        'bulanan' => 'Bulanan',
                        'tahunan' => 'Tahunan',
                    ])
                    ->required()
                    ->live(),

                TextInput::make('tahun')
                    ->label('Tahun')
                    ->integer()
                    ->minValue(1900)
                    ->maxValue(9999)
                    ->required(),

                Select::make('bulan')
                    ->label('Bulan')
                    ->options([
                        1 => 'Januari',
                        2 => 'Februari',
                        3 => 'Maret',
                        4 => 'April',
                        5 => 'Mei',
                        6 => 'Juni',
                        7 => 'Juli',
                        8 => 'Agustus',
                        9 => 'September',
                        10 => 'Oktober',
                        11 => 'November',
                        12 => 'Desember',
                    ])
                    ->visible(
                        fn (Get $get): bool => $get('periode') === 'bulanan'
                    )
                    ->required(
                        fn (Get $get): bool => $get('periode') === 'bulanan'
                    ),
            ])
            ->action(function (array $data): void {
                $this->filters = [
                    'periode' => $data['periode'],
                    'tahun' => (int) $data['tahun'],
                    'bulan' => (int) ($data['bulan'] ?? now()->month),
                    'sikluses_id' => filled($data['sikluses_id'] ?? null) ? (int) $data['sikluses_id'] : null,
                ];
            });
    }

    protected function exportPenjualanAction(): Action
    {
        return Action::make('exportPenjualan')
            ->label(view('filament.pages.export-button-label', ['label' => 'Export Penjualan & Laba', 'jenis' => 'penjualan']))
            ->extraAttributes(['x-bind:disabled' => 'isExporting', 'wire:loading.attr' => null])
            ->alpineClickHandler("exportReport('penjualan', () => \$wire.mountAction('exportPenjualan'))")
            ->action(fn (): string => $this->getDownloadUrl('penjualan'));
    }

    protected function exportPengeluaranAction(): Action
    {
        return Action::make('exportPengeluaran')
            ->label(view('filament.pages.export-button-label', ['label' => 'Export Pengeluaran', 'jenis' => 'pengeluaran']))
            ->extraAttributes(['x-bind:disabled' => 'isExporting', 'wire:loading.attr' => null])
            ->alpineClickHandler("exportReport('pengeluaran', () => \$wire.mountAction('exportPengeluaran'))")
            ->color('gray')
            ->modalHeading('Export laporan pengeluaran')
            ->modalDescription(
                fn (): string => 'Periode: '.$this->getPeriodeLabel().' | Siklus: '.$this->getSiklusLabel()
            )
            ->modalSubmitAction(fn (Action $action): Action => $action
                ->label(view('filament.pages.export-button-label', ['label' => 'Unduh laporan', 'jenis' => 'pengeluaran']))
                ->extraAttributes(['x-bind:disabled' => 'isExporting', 'wire:loading.attr' => null]))
            ->extraModalWindowAttributes([
                'x-on:submit.capture.prevent.stop' => '$event.stopImmediatePropagation(); exportReport(\'pengeluaran\', () => $wire.callMountedAction())',
            ])
            ->schema([
                Select::make('klasifikasi')
                    ->label('Klasifikasi pengeluaran')
                    ->options([
                        'semua' => 'Semua pengeluaran',
                        'operasional' => 'Operasional',
                        'investasi' => 'Investasi',
                    ])
                    ->default('semua')
                    ->required()
                    ->rules([
                        Rule::in(['semua', 'operasional', 'investasi']),
                    ]),
            ])
            ->action(fn (array $data): string => $this->getDownloadUrl('pengeluaran', $data['klasifikasi']));
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    protected function periode(): array
    {
        $this->validate([
            'filters.periode' => [
                'required',
                Rule::in(['bulanan', 'tahunan']),
            ],
            'filters.tahun' => ['required', 'integer', 'between:1900,9999'],
            'filters.bulan' => ['required', 'integer', 'between:1,12'],
        ]);

        $bulanan = $this->filters['periode'] === 'bulanan';

        $mulai = CarbonImmutable::create(
            $this->filters['tahun'],
            $bulanan ? $this->filters['bulan'] : 1,
            1,
        )->startOfDay();

        $selesai = $bulanan
            ? $mulai->endOfMonth()
            : $mulai->endOfYear();

        return [$mulai, $selesai];
    }

    public function getPeriodeLabel(): string
    {
        [$mulai] = $this->periode();

        return $this->filters['periode'] === 'bulanan'
            ? $mulai->locale('id')->translatedFormat('F Y')
            : 'Tahun '.$mulai->format('Y');
    }

    public function getSiklusLabel(): string
    {
        if ($this->filters['sikluses_id'] === null) {
            return 'Semua siklus';
        }

        return Filament::getTenant()->sikluses()->whereKey($this->filters['sikluses_id'])->value('nama')
            ?? 'Siklus tidak tersedia';
    }

    protected function getDownloadUrl(
        string $jenis,
        string $klasifikasi = 'semua',
    ): string {
        $user = Filament::auth()->user();
        $usaha = Filament::getTenant();

        abort_unless($user instanceof User, 403);
        abort_unless($usaha instanceof Usaha, 404);

        $this->periode();

        return route('laporan.download', [
            'usaha' => $usaha->slug,
            'jenis' => $jenis,
            'klasifikasi' => $klasifikasi,
            ...$this->filters,
        ]);
    }
}
