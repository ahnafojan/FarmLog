<?php

namespace App\Filament\Widgets;

use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Livewire\Attributes\Reactive;

class RevenueProfitChart extends ChartWidget
{
    protected ?string $heading = 'Pendapatan & Laba';

    protected ?string $maxHeight = '320px';

    protected ?string $pollingInterval = null;

    protected static bool $isLazy = false;

    /** @var array{labels: list<string>, pemasukan: list<int>, laba_bersih: list<int>} */
    #[Reactive]
    public array $chartData = ['labels' => [], 'pemasukan' => [], 'laba_bersih' => []];

    #[Reactive]
    public string $basis = 'siklus_selesai';

    public function getDescription(): string
    {
        $basis = $this->basis === 'siklus_selesai'
            ? 'Mengikuti tanggal siklus selesai; transaksi tanpa siklus mengikuti tanggal transaksi.'
            : 'Mengikuti tanggal transaksi, termasuk siklus berjalan.';

        return $basis.' Laba = pendapatan − biaya operasional, di luar investasi.';
    }

    protected function getData(): array
    {
        return [
            'labels' => $this->chartData['labels'],
            'datasets' => [
                [
                    'label' => 'Pendapatan',
                    'data' => $this->chartData['pemasukan'],
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => '#f59e0b',
                ],
                [
                    'label' => 'Laba',
                    'data' => $this->chartData['laba_bersih'],
                    'borderColor' => '#10b981',
                    'backgroundColor' => '#10b981',
                    'borderDash' => [5, 3],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                elements: { point: { radius: 2, hitRadius: 8 }, line: { borderWidth: 2 } },
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: (context) => context.dataset.label + ': ' + new Intl.NumberFormat('id-ID', {
                                style: 'currency', currency: 'IDR', maximumFractionDigits: 0,
                            }).format(context.parsed.y),
                        },
                    },
                },
                scales: {
                    x: { ticks: { maxTicksLimit: 12, maxRotation: 0 } },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: (value) => 'Rp' + new Intl.NumberFormat('id-ID', {
                                notation: 'compact', maximumFractionDigits: 1,
                            }).format(value),
                        },
                    },
                },
            }
            JS);
    }
}
