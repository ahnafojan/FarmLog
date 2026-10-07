<div data-pwa-install hidden>
    <x-filament::section heading="Instal aplikasi"
        description="Tambahkan Pariwangi Group ke layar utama agar lebih mudah dibuka.">
        <div class="space-y-4">
            <p class="farm-muted">
                Koneksi internet tetap diperlukan untuk membaca
                dan menyimpan data usaha.
            </p>

            <div data-pwa-install-action hidden>
                <x-filament::button type="button" data-pwa-install-button>
                    Instal aplikasi
                </x-filament::button>
            </div>

            <p data-pwa-install-status role="status" aria-live="polite" aria-atomic="true" class="farm-muted"></p>

            <div data-pwa-install-help="ios" hidden>
                <ol class="farm-muted list-decimal space-y-2 ps-5">
                    <li>Buka aplikasi ini melalui Safari.</li>
                    <li>Tekan Bagikan.</li>
                    <li>Pilih Tambahkan ke Layar Utama.</li>
                    <li>
                        Aktifkan Open as Web App jika pilihan tersebut tersedia.
                    </li>
                    <li>Tekan Tambahkan.</li>
                </ol>
            </div>

            <div data-pwa-install-help="android" hidden>
                <ol class="farm-muted list-decimal space-y-2 ps-5">
                    <li>Buka aplikasi ini melalui Chrome.</li>
                    <li>Tekan menu tiga titik.</li>
                    <li>
                        Pilih Instal aplikasi atau Tambahkan ke layar utama
                        jika tersedia.
                    </li>
                    <li>Ikuti petunjuk dari browser.</li>
                </ol>
            </div>

            <div data-pwa-install-help="other" hidden>
                <p class="farm-muted">
                    Cari pilihan Instal aplikasi pada menu browser
                    atau ikon instalasi di bilah alamat.
                    Jika tidak tersedia, Anda tetap dapat menggunakan
                    aplikasi melalui browser.
                </p>
            </div>
        </div>
    </x-filament::section>
</div>
