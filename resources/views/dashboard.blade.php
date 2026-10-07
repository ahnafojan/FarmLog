<div class="farm-content pt-6 max-lg:pt-4">
    @php
        use App\Filament\Resources\Sikluses\SiklusResource;
        use App\Filament\Resources\Transaksis\TransaksiResource;

        $data = $this->dashboardData;
        $cycle = $data['cycle'];
        $rupiah = fn(int $amount): string => ($amount < 0 ? '−' : '') . 'Rp' . number_format(abs($amount), 0, ',', '.');
    @endphp

    <h1 class="hidden text-2xl font-bold tracking-tight lg:block">Beranda</h1>

    <div class="flex flex-wrap items-center gap-3">
        <x-filament::tabs label="Periode rekap" class="max-w-full">
            @foreach (['bulan_ini' => 'Bulan ini', 'pilih_bulan' => 'Pilih bulan', 'tahun_ini' => 'Tahun ini', 'pilih_tahun' => 'Pilih tahun'] as $value => $label)
                <x-filament::tabs.item :active="$filters['periode'] === $value" wire:click="$set('filters.periode', '{{ $value }}')">
                    {{ $label }}
                </x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>
        @if ($filters['periode'] === 'pilih_bulan')
            <div class="flex flex-wrap items-center gap-3">
                <label for="rekap-month">Bulan</label>
                <x-filament::input.wrapper class="w-40">
                    <x-filament::input.select id="rekap-month" wire:model.live="filters.bulan"
                        aria-describedby="rekap-month-error">
                        @foreach ([1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'] as $month => $label)
                            <option value="{{ $month }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
                @error('filters.bulan')
                    <p id="rekap-month-error" role="alert">{{ $message }}</p>
                @enderror
            </div>
        @endif
        @if (in_array($filters['periode'], ['pilih_bulan', 'pilih_tahun'], true))
            <div class="flex flex-wrap items-center gap-3">
                <label for="rekap-year">Tahun</label>
                <x-filament::input.wrapper class="w-28"><x-filament::input id="rekap-year" type="number"
                        inputmode="numeric" min="1900" max="9999" wire:model.live.blur="filters.tahun"
                        aria-describedby="rekap-year-error" /></x-filament::input.wrapper>
                @error('filters.tahun')
                    <p id="rekap-year-error" role="alert">{{ $message }}</p>
                @enderror
            </div>
        @endif
        <div class="flex flex-wrap items-center gap-3">
            <span class="inline-flex items-center gap-2 text-gray-500 dark:text-gray-400"><x-filament::icon
                    icon="heroicon-o-adjustments-horizontal" class="size-5 shrink-0" /> Dasar rekap</span>
            <x-filament::tabs label="Dasar rekap">
                @foreach (['siklus_selesai' => 'Siklus selesai', 'tanggal_transaksi' => 'Tgl transaksi'] as $value => $label)
                    <x-filament::tabs.item :active="$filters['dasar'] === $value" wire:click="$set('filters.dasar', '{{ $value }}')">
                        {{ $label }}
                    </x-filament::tabs.item>
                @endforeach
            </x-filament::tabs>
        </div>
    </div>

    <div class="grid items-start gap-5 lg:grid-cols-2" wire:loading.class="opacity-60" wire:target="filters">
        <x-farmlog.financial-summary :summary="$data['summary']" :running="$data['running']" />

        <section aria-labelledby="cycles-title">
            <div class="farm-section-heading">
                <h2 id="cycles-title"><x-filament::icon icon="heroicon-o-square-3-stack-3d" class="size-5 shrink-0" />
                    Siklus Berjalan</h2>
                <a wire:navigate href="{{ SiklusResource::getUrl() }}">Lihat semua <x-filament::icon
                        icon="heroicon-o-chevron-right" class="size-5 shrink-0" /></a>
            </div>
            @if ($cycle)
                <x-farmlog.cycle-card :cycle="$cycle" :url="SiklusResource::getUrl('edit', ['record' => $cycle['id']])" />
            @else
                <x-farmlog.empty-state icon="heroicon-o-square-3-stack-3d" title="Belum ada siklus berjalan">
                    <x-slot name="actions">
                        <x-filament::button tag="a" :href="SiklusResource::getUrl('create')" icon="heroicon-o-plus">Buat
                            siklus</x-filament::button>
                    </x-slot>
                </x-farmlog.empty-state>
            @endif
        </section>
    </div>

    @livewire(\App\Filament\Widgets\RevenueProfitChart::class, ['chartData' => $data['chart'], 'basis' => $filters['dasar']], key('revenue-profit-chart-' . \Filament\Facades\Filament::getTenant()->getKey()))

    <section class="space-y-3" aria-labelledby="transactions-title">
        <div class="farm-section-heading">
            <h2 id="transactions-title"><x-filament::icon icon="heroicon-o-document-text" class="size-5 shrink-0" />
                Transaksi Terbaru</h2>
            <a wire:navigate href="{{ TransaksiResource::getUrl() }}">Lihat semua <x-filament::icon
                    icon="heroicon-o-chevron-right" class="size-5 shrink-0" /></a>
        </div>
        <div class="grid gap-3">
            @forelse ($data['transactions'] as $transaction)
                @php($isIncome = $transaction['arah'] === 'pemasukan')
                <div class="farm-card flex flex-wrap items-center gap-3"
                    wire:key="transaction-{{ $transaction['id'] }}">
                    <span @class([
                        'flex size-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400',
                        'is-income' => $isIncome,
                    ])><x-filament::icon :icon="$isIncome ? 'heroicon-o-banknotes' : 'heroicon-o-shopping-bag'"
                            class="size-5 shrink-0" /></span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2 [&_h3]:truncate [&_h3]:font-semibold">
                            <h3 title="{{ $transaction['kategori'] }}">{{ $transaction['kategori'] }}</h3>
                            <x-filament::badge
                                :color="$isIncome ? 'success' : 'danger'">{{ $isIncome ? 'Masuk' : 'Keluar' }}</x-filament::badge>
                        </div>
                        <p class="farm-muted truncate" title="{{ $transaction['siklus'] }}">
                            {{ $transaction['siklus'] }} · {{ $transaction['tanggal'] }}</p>
                    </div>
                    <strong
                        @class([
                            'farm-money max-w-full text-right font-semibold',
                            'text-success-600 dark:text-success-400' => $isIncome,
                            'text-danger-600 dark:text-danger-400' => !$isIncome,
                        ])>{{ $isIncome ? '+' : '−' }}{{ $rupiah($transaction['total']) }}</strong>
                </div>
            @empty
                <x-farmlog.empty-state icon="heroicon-o-document-text"
                    description="Belum ada transaksi pada periode ini" />
            @endforelse
        </div>
    </section>

    @include('filament.hooks.catat-button')

    <x-filament-actions::modals />
</div>
