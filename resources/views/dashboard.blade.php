<div class="farm-home">
    @push('styles')
        {{ Illuminate\Support\Facades\Vite::fonts('plus-jakarta-sans') }}
    @endpush
    @php
        use App\Filament\Resources\Sikluses\SiklusResource;
        use App\Filament\Resources\Transaksis\TransaksiResource;

        $data = $this->dashboardData;
        $summary = $data['summary'];
        $cycle = $data['cycle'];
        $profit = $summary['laba_bersih'];
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
        <section class="farm-card farm-finance" aria-labelledby="profit-title">
            <div class="farm-row">
                <h2 id="profit-title" class="farm-eyebrow">Laba bersih</h2>
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
            @if ($data['running'] !== null)
                <div class="farm-inset farm-row">
                    <div><p class="farm-muted">Laba sementara siklus berjalan</p><strong>{{ $rupiah($data['running']['laba_bersih']) }}</strong></div>
                    <span class="farm-badge farm-badge-pending">Sementara</span>
                </div>
            @endif
            <div class="farm-inset">
                <div class="farm-row"><span>Investasi</span><strong>{{ $rupiah($summary['investasi']) }}</strong></div>
                <p class="farm-muted farm-investment-note">Tidak mengurangi laba bersih periode berjalan</p>
            </div>
        </section>

        <section class="farm-cycles" aria-labelledby="cycles-title">
            <div class="farm-section-heading">
                <h2 id="cycles-title"><x-filament::icon icon="heroicon-o-square-3-stack-3d" /> Siklus Berjalan</h2>
                <a href="{{ SiklusResource::getUrl() }}">Lihat semua <x-filament::icon icon="heroicon-o-chevron-right" /></a>
            </div>
            @if ($cycle)
                <article class="farm-card farm-cycle">
                    <div class="farm-row farm-cycle-title">
                        <h3><a href="{{ SiklusResource::getUrl('edit', ['record' => $cycle['id']]) }}">{{ $cycle['nama'] }}</a></h3>
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
            @else
                <div class="farm-card farm-empty">
                    <x-filament::icon icon="heroicon-o-square-3-stack-3d" />
                    <h3>Belum ada siklus berjalan</h3>
                    <a class="farm-primary-link" href="{{ SiklusResource::getUrl('create') }}"><x-filament::icon icon="heroicon-o-plus" /> Buat siklus</a>
                </div>
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
                <div class="farm-card farm-empty"><x-filament::icon icon="heroicon-o-document-text" /><p>Belum ada transaksi pada periode ini</p></div>
            @endforelse
        </div>
    </section>

    <button type="button" class="farm-catat" wire:click="mountAction('catat')" wire:loading.attr="disabled" wire:target="mountAction">
        <x-filament::icon icon="heroicon-o-plus" /> Catat
    </button>

    <x-filament-actions::modals />
</div>
