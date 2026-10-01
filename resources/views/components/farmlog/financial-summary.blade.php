@props(['summary', 'running' => null])

@php
    $profit = $summary['laba_bersih'];
    $rupiah = fn (int $amount): string => ($amount < 0 ? '−' : '').'Rp'.number_format(abs($amount), 0, ',', '.');
@endphp

<section {{ $attributes->class(['farm-card', 'farm-finance']) }} aria-label="Ringkasan keuangan">
    <div class="farm-row">
        <h2 class="farm-eyebrow">Laba bersih</h2>
        <span @class(['farm-badge', 'farm-badge-negative' => $profit < 0, 'farm-badge-positive' => $profit > 0, 'farm-badge-neutral' => $profit === 0])>
            <x-filament::icon :icon="$profit < 0 ? 'heroicon-o-arrow-trending-down' : 'heroicon-o-arrow-trending-up'" />
            {{ $profit > 0 ? 'Untung' : ($profit < 0 ? 'Rugi' : 'Impas') }}
        </span>
    </div>
    <p @class(['farm-profit', 'farm-negative' => $profit < 0, 'farm-positive' => $profit >= 0])>{{ $rupiah($profit) }}</p>
    <dl class="farm-breakdown">
        <div class="farm-row">
            <dt><span class="farm-dot farm-income-dot"></span>Pemasukan</dt>
            <dd class="farm-positive">+ {{ $rupiah($summary['pemasukan']) }}</dd>
        </div>
        <div class="farm-row">
            <dt><span class="farm-dot farm-expense-dot"></span>Biaya operasional</dt>
            <dd class="farm-negative">− {{ $rupiah($summary['operasional']) }}</dd>
        </div>
    </dl>
    @if ($running !== null)
        <div class="farm-inset farm-row">
            <div><p class="farm-muted">Laba sementara siklus berjalan</p><strong>{{ $rupiah($running['laba_bersih']) }}</strong></div>
            <span class="farm-badge farm-badge-pending">Sementara</span>
        </div>
    @endif
    <div class="farm-inset">
        <div class="farm-row"><span>Investasi</span><strong>{{ $rupiah($summary['investasi']) }}</strong></div>
        <p class="farm-muted farm-investment-note">Tidak mengurangi laba bersih periode berjalan</p>
    </div>
</section>
