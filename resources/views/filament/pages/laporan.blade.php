<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">
            Periode: {{ $this->getPeriodeLabel() }}
        </x-slot>

        <p class="text-sm text-gray-600 dark:text-gray-400">
            Pilih periode melalui tombol Filter, kemudian unduh
            laporan penjualan dan laba bersih atau laporan pengeluaran.
            Saat memilih Export Pengeluaran, pilih semua pengeluaran, operasional, atau investasi.
            Kedua laporan menggunakan tanggal transaksi.
        </p>
    </x-filament::section>
</x-filament-panels::page>
