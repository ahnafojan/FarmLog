<div class="farm-home">
    @push('styles')
        {{ Illuminate\Support\Facades\Vite::fonts('plus-jakarta-sans') }}
    @endpush
    @php
        use App\Filament\Resources\Sikluses\SiklusResource;
        use App\Filament\Resources\Transaksis\TransaksiResource;

        $data = $this->dashboardData;
        $cycle = $data['cycle'];
        $rupiah = fn (int $amount): string => ($amount < 0 ? '−' : '').'Rp'.number_format(abs($amount), 0, ',', '.');
    @endphp

    <h1 class="farm-desktop-title">Beranda</h1>

    <div class="farm-filters">
        <div class="farm-periods" role="group" aria-label="Periode rekap">
            @foreach (['bulan_ini' => 'Bulan ini', 'tahun_ini' => 'Tahun ini', 'pilih_tahun' => 'Pilih tahun'] as $value => $label)
                <button type="button" wire:click="$set('filters.periode', '{{ $value }}')" @class(['farm-period', 'is-active' => $filters['periode'] === $value]) aria-pressed="{{ $filters['periode'] === $value ? 'true' : 'false' }}">
                    {{ $label }}
                    @if ($value === 'pilih_tahun') <x-filament::icon icon="heroicon-o-calendar-days" /> @endif
                </button>
            @endforeach
        </div>
        @if ($filters['periode'] === 'pilih_tahun')
            <div class="farm-year">
                <label for="rekap-year">Tahun</label>
                <input id="rekap-year" type="number" inputmode="numeric" min="1900" max="9999" wire:model.live.blur="filters.tahun" aria-describedby="rekap-year-error" />
                @error('filters.tahun') <p id="rekap-year-error" role="alert">{{ $message }}</p> @enderror
            </div>
        @endif
        <div class="farm-basis">
            <span><x-filament::icon icon="heroicon-o-adjustments-horizontal" /> Dasar rekap</span>
            <div class="farm-segments" role="group" aria-label="Dasar rekap">
                @foreach (['siklus_selesai' => 'Siklus selesai', 'tanggal_transaksi' => 'Tgl transaksi'] as $value => $label)
                    <button type="button" wire:click="$set('filters.dasar', '{{ $value }}')" @class(['is-active' => $filters['dasar'] === $value]) aria-pressed="{{ $filters['dasar'] === $value ? 'true' : 'false' }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>
    </div>

    <div class="farm-overview" wire:loading.class="farm-is-loading" wire:target="filters">
        <x-farmlog.financial-summary :summary="$data['summary']" :running="$data['running']" />

        <section class="farm-cycles" aria-labelledby="cycles-title">
            <div class="farm-section-heading">
                <h2 id="cycles-title"><x-filament::icon icon="heroicon-o-square-3-stack-3d" /> Siklus Berjalan</h2>
                <a href="{{ SiklusResource::getUrl() }}">Lihat semua <x-filament::icon icon="heroicon-o-chevron-right" /></a>
            </div>
            @if ($cycle)
                <x-farmlog.cycle-card
                    :cycle="$cycle"
                    :url="SiklusResource::getUrl('edit', ['record' => $cycle['id']])"
                />
            @else
                <x-farmlog.empty-state icon="heroicon-o-square-3-stack-3d" title="Belum ada siklus berjalan">
                    <x-slot name="actions">
                        <a class="farm-primary-link" href="{{ SiklusResource::getUrl('create') }}"><x-filament::icon icon="heroicon-o-plus" /> Buat siklus</a>
                    </x-slot>
                </x-farmlog.empty-state>
            @endif
        </section>
    </div>

    <section class="farm-transactions" aria-labelledby="transactions-title">
        <div class="farm-section-heading">
            <h2 id="transactions-title"><x-filament::icon icon="heroicon-o-document-text" /> Transaksi Terbaru</h2>
            <a href="{{ TransaksiResource::getUrl() }}">Lihat semua <x-filament::icon icon="heroicon-o-chevron-right" /></a>
        </div>
        <div class="farm-transaction-list">
            @forelse ($data['transactions'] as $transaction)
                @php($isIncome = $transaction['arah'] === 'pemasukan')
                <div class="farm-card farm-transaction" wire:key="transaction-{{ $transaction['id'] }}">
                    <span @class(['farm-transaction-icon', 'is-income' => $isIncome])><x-filament::icon :icon="$isIncome ? 'heroicon-o-banknotes' : 'heroicon-o-shopping-bag'" /></span>
                    <div class="farm-transaction-info">
                        <div class="farm-transaction-title"><h3 title="{{ $transaction['kategori'] }}">{{ $transaction['kategori'] }}</h3><span @class(['farm-transaction-badge', 'is-income' => $isIncome])>{{ $isIncome ? 'Masuk' : 'Keluar' }}</span></div>
                        <p class="farm-muted" title="{{ $transaction['siklus'] }}">{{ $transaction['siklus'] }} · {{ $transaction['tanggal'] }}</p>
                    </div>
                    <strong @class(['farm-transaction-total', 'farm-positive' => $isIncome, 'farm-negative' => ! $isIncome])>{{ $isIncome ? '+' : '−' }}{{ $rupiah($transaction['total']) }}</strong>
                </div>
            @empty
                <x-farmlog.empty-state icon="heroicon-o-document-text" description="Belum ada transaksi pada periode ini" />
            @endforelse
        </div>
    </section>

    <button type="button" class="farm-catat" wire:click="mountAction('catat')" wire:loading.attr="disabled" wire:target="mountAction">
        <x-filament::icon icon="heroicon-o-plus" /> Catat
    </button>

    <x-filament-actions::modals />
</div>
