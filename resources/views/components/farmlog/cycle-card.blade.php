@props(['cycle', 'url', 'showProfit' => true])

@php
    $finished = ($cycle['status'] ?? 'berjalan') === 'selesai';

    $rupiah = fn(int $amount): string => ($amount < 0 ? '−' : '') . 'Rp' . number_format(abs($amount), 0, ',', '.');
@endphp

<article {{ $attributes->class(['farm-card', 'space-y-4']) }}>
    <div class="flex items-start justify-between gap-3">
        <h3 class="min-w-0 flex-1 text-base font-semibold leading-6 wrap-anywhere">
            <a href="{{ $url }}">{{ $cycle['nama'] }}</a>
        </h3>

        <x-filament::badge :color="$finished ? 'gray' : 'success'" class="shrink-0 self-start">
            {{ $finished ? 'Selesai' : 'Berjalan' }}
        </x-filament::badge>
    </div>

    <p class="farm-muted">
        Mulai {{ $cycle['mulai'] }}

        @if ($finished && !empty($cycle['selesai']))
            · Selesai {{ $cycle['selesai'] }}
        @endif
    </p>

    <dl class="farm-stats">
        <div>
            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-primary-600 dark:bg-gray-800 dark:text-primary-400">
                <x-filament::icon icon="heroicon-o-inbox-stack" class="size-5 shrink-0" />
            </span>
            <div>
                <dt>Populasi awal</dt>
                <dd>{{ number_format($cycle['jumlah'], 0, ',', '.') }} ekor</dd>
            </div>
        </div>

        <div>
            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-primary-600 dark:bg-gray-800 dark:text-primary-400">
                <x-filament::icon icon="heroicon-o-clock" class="size-5 shrink-0" />
            </span>
            <div>
                <dt>{{ $finished ? 'Umur saat selesai' : 'Umur budidaya' }}</dt>
                <dd>{{ $cycle['umur'] }} hari</dd>
            </div>
        </div>
    </dl>

    @if (!$finished)
        <div class="farm-inset flex items-start gap-3">
            <x-filament::icon icon="heroicon-o-calendar-days" class="size-5 shrink-0" />

            <div>
                <p class="farm-muted">Jadwal terdekat</p>
                <strong>
                    {{ $cycle['penanda'] ?? 'Belum ada jadwal berikutnya' }}
                </strong>

                @if ($cycle['tanggal_penanda'])
                    <span class="block text-xs text-gray-500 dark:text-gray-400">
                        {{ $cycle['tanggal_penanda'] }} ·
                        {{ $cycle['sisa_hari'] === 0 ? 'Hari ini' : $cycle['sisa_hari'] . ' hari lagi' }}
                    </span>
                @endif
            </div>
        </div>

        @if ($harvest = $cycle['panen'])
            <div class="space-y-2 text-sm [&_p]:text-right [&_.farm-row]:flex-wrap">
                <div class="farm-row">
                    <span class="inline-flex items-center gap-2">
                        <x-filament::icon icon="heroicon-o-check-badge" class="size-5 shrink-0" />
                        {{ $harvest['nama'] }}
                    </span>

                    <strong>
                        {{ $harvest['sisa_hari'] < 0
                            ? abs($harvest['sisa_hari']) . ' hari terlewat'
                            : ($harvest['sisa_hari'] === 0
                                ? 'Hari ini'
                                : $harvest['sisa_hari'] . ' hari lagi') }}
                    </strong>
                </div>

                <p class="farm-muted">{{ $harvest['tanggal'] }}</p>

                <progress class="farm-progress" max="100" value="{{ $harvest['progres'] }}"
                    aria-label="Progres waktu menuju {{ $harvest['nama'] }}">
                    {{ $harvest['progres'] }}%
                </progress>
            </div>
        @endif
    @endif

    @if ($showProfit)
        <div class="farm-row border-t border-gray-200 pt-4 dark:border-gray-700">
            <span class="farm-muted">
                {{ $finished ? 'Laba siklus' : 'Laba sementara' }}
            </span>

            <strong @class([
                'text-success-600 dark:text-success-400' => $cycle['laba_bersih'] >= 0,
                'text-danger-600 dark:text-danger-400' => $cycle['laba_bersih'] < 0,
            ])>
                {{ $rupiah($cycle['laba_bersih']) }}
            </strong>
        </div>
    @endif

    {{ $slot }}
</article>
