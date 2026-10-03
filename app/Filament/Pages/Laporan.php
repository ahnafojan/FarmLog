<?php

namespace App\Filament\Pages;

use App\Actions\ExportLaporanPdf;
use App\Models\Usaha;
use App\Models\User;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Laporan extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static ?string $navigationLabel = 'Laporan';

    protected static ?string $title = 'Laporan';

    protected string $view = 'filament.pages.laporan';

    /**
     * @var array{periode?: string, tahun?: int, bulan?: int}
     */
    #[Locked]
    public array $filters = [];

    public function mount(): void
    {
        $this->filters = [
            'periode' => 'bulanan',
            'tahun' => now()->year,
            'bulan' => now()->month,
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
                ];
            });
    }

    protected function exportPenjualanAction(): Action
    {
        return Action::make('exportPenjualan')
            ->label('Export Penjualan & Laba')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->action(
                fn (): StreamedResponse => $this->exportPdf('penjualan')
            );
    }

    protected function exportPengeluaranAction(): Action
    {
        return Action::make('exportPengeluaran')
            ->label('Export Pengeluaran')
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->action(
                fn (): StreamedResponse => $this->exportPdf('pengeluaran')
            );
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

    protected function exportPdf(string $jenis): StreamedResponse
    {
        $user = Filament::auth()->user();
        $usaha = Filament::getTenant();

        abort_unless($user instanceof User, 403);
        abort_unless($usaha instanceof Usaha, 404);

        [$mulai, $selesai] = $this->periode();

        return app(ExportLaporanPdf::class)->handle(
            $user,
            $usaha,
            $jenis,
            $mulai,
            $selesai,
            $this->filters['periode'] === 'bulanan',
        );
    }
}
