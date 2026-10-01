@props(['cycle', 'url'])

@php
    $rupiah = fn (int $amount): string => ($amount < 0 ? '−' : '').'Rp'.number_format(abs($amount), 0, ',', '.');
@endphp

<article {{ $attributes->class(['farm-card', 'farm-cycle']) }}>
    <div class="farm-row farm-cycle-title">
        <h3><a href="{{ $url }}">{{ $cycle['nama'] }}</a></h3>
        <span class="farm-badge farm-badge-positive"><span class="farm-dot"></span>Berjalan</span>
    </div>
    <p class="farm-muted">Mulai {{ $cycle['mulai'] }}</p>
    <dl class="farm-cycle-stats">
        <div><span class="farm-stat-icon"><x-filament::icon icon="heroicon-o-inbox-stack" /></span><div><dt>Populasi awal</dt><dd>{{ number_format($cycle['jumlah'], 0, ',', '.') }} ekor</dd></div></div>
        <div><span class="farm-stat-icon"><x-filament::icon icon="heroicon-o-clock" /></span><div><dt>Umur budidaya</dt><dd>{{ $cycle['umur'] }} hari</dd></div></div>
    </dl>
    <div class="farm-inset farm-schedule">
        <x-filament::icon icon="heroicon-o-calendar-days" />
        <div><p class="farm-muted">Jadwal terdekat</p><strong>{{ $cycle['penanda'] ?? 'Belum ada jadwal berikutnya' }}</strong>
            @if ($cycle['tanggal_penanda']) <span class="farm-schedule-date">{{ $cycle['tanggal_penanda'] }} · {{ $cycle['sisa_hari'] === 0 ? 'Hari ini' : $cycle['sisa_hari'].' hari lagi' }}</span> @endif
        </div>
    </div>
    @if ($harvest = $cycle['panen'])
        <div class="farm-harvest">
            <div class="farm-row">
                <span><x-filament::icon icon="heroicon-o-check-badge" /> {{ $harvest['nama'] }}</span>
                <strong>{{ $harvest['sisa_hari'] < 0 ? abs($harvest['sisa_hari']).' hari terlewat' : ($harvest['sisa_hari'] === 0 ? 'Hari ini' : $harvest['sisa_hari'].' hari lagi') }}</strong>
            </div>
            <p class="farm-muted">{{ $harvest['tanggal'] }}</p>
            <progress max="100" value="{{ $harvest['progres'] }}" aria-label="Progres waktu menuju {{ $harvest['nama'] }}">{{ $harvest['progres'] }}%</progress>
        </div>
    @endif
    <div class="farm-row farm-cycle-profit">
        <span class="farm-muted">Laba sementara</span>
        <strong @class(['farm-positive' => $cycle['laba_bersih'] >= 0, 'farm-negative' => $cycle['laba_bersih'] < 0])>{{ $rupiah($cycle['laba_bersih']) }}</strong>
    </div>
</article>
