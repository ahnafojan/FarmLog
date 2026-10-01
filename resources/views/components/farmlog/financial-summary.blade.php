@props(['summary', 'running' => null, 'investmentNote' => 'Tidak mengurangi laba bersih periode berjalan'])

@php
    $profit = $summary['laba_bersih'];
    $rupiah = fn(int $amount): string => ($amount < 0 ? '−' : '') . 'Rp' . number_format(abs($amount), 0, ',', '.');
@endphp

<section {{ $attributes->class(['farm-card', 'space-y-4']) }} aria-label="Ringkasan keuangan">
    <div class="farm-row">
        <h2 class="text-sm font-medium text-gray-500 dark:text-gray-400">Laba bersih</h2>
        <x-filament::badge
            :color="$profit < 0 ? 'danger' : ($profit > 0 ? 'success' : 'gray')"
            :icon="$profit < 0 ? 'heroicon-o-arrow-trending-down' : 'heroicon-o-arrow-trending-up'"
            class="shrink-0"
        >
            {{ $profit > 0 ? 'Untung' : ($profit < 0 ? 'Rugi' : 'Impas') }}
        </x-filament::badge>
    </div>
    <p @class([
        'farm-money text-3xl font-bold tracking-tight',
        'text-danger-600 dark:text-danger-400' => $profit < 0,
        'text-success-600 dark:text-success-400' => $profit >= 0,
    ])>{{ $rupiah($profit) }}</p>
    <dl class="farm-inset space-y-3 [&_dt]:flex [&_dt]:items-center [&_dt]:gap-2 [&_dd]:text-right [&_dd]:font-semibold [&_dd]:wrap-anywhere">
        <div class="farm-row">
            <dt><span class="inline-block size-2 shrink-0 rounded-full bg-current text-success-600 dark:text-success-400"></span>Pemasukan</dt>
            <dd class="text-success-600 dark:text-success-400">+ {{ $rupiah($summary['pemasukan']) }}</dd>
        </div>
        <div class="farm-row">
            <dt><span class="inline-block size-2 shrink-0 rounded-full bg-current text-danger-600 dark:text-danger-400"></span>Biaya operasional</dt>
            <dd class="text-danger-600 dark:text-danger-400">− {{ $rupiah($summary['operasional']) }}</dd>
        </div>
    </dl>
    @if ($running !== null)
        <div class="farm-inset farm-row">
            <div>
                <p class="farm-muted">Laba sementara siklus berjalan</p>
                <strong>{{ $rupiah($running['laba_bersih']) }}</strong>
            </div>
            <x-filament::badge color="warning" class="shrink-0">Sementara</x-filament::badge>
        </div>
    @endif
    <div class="farm-inset">
        <div class="farm-row"><span>Investasi</span><strong>{{ $rupiah($summary['investasi']) }}</strong></div>
        <p class="farm-muted mt-1">
            {{ $investmentNote }}
        </p>
    </div>
</section>
