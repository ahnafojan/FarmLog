PRD MVP: Pencatat Usaha Budidaya (nama kerja: CatatTernak)
Versi final, 30 Sep 2026.

1. Tujuan dan pengguna
   Pemilik usaha budidaya (ayam petelur dan lele) mencatat sendiri pengeluaran dan penjualan per siklus, memantau perkiraan panen, dan melihat untung rugi per siklus maupun per periode (misalnya setahun), tanpa Excel. Satu akun berarti satu pemilik, dan pekerja tidak memakai aplikasi. Aplikasi dipakai di HP dan berbahasa Indonesia.
2. Keputusan kunci
   • Tidak ada input budget awal dan tidak ada pilihan sumber dana. Semua biaya, termasuk yang dibayar dari dana pribadi, dihitung sebagai biaya biasa.
   • Keuntungan dihitung dari transaksi (penjualan dikurangi biaya), tidak disimpan sebagai angka.
   • Tidak ada keanggotaan atau peran. Setiap usaha dimiliki satu pengguna.
   • Rekap punya dua dasar: siklus selesai dan tanggal transaksi.
   • Kandang, kolam, dan peralatan masuk kategori Investasi, tampil terpisah, dan tidak dihitung ke laba.
   • Ayam petelur diperlakukan sebagai siklus biasa, dengan penanda mulai bertelur dan afkir, dan penjualan telur dicatat berulang tanpa produksi harian.
3. Ruang lingkup MVP
   • Template: Ayam Petelur, Lele Pendederan, Lele Pembesaran.
   • Fitur: daftar dan masuk, buat usaha, pengalih usaha, siklus (buat, detail, tutup), catat pengeluaran dan pemasukan, jadwal perkiraan, rekap periode.
   • Di luar MVP: lihat bagian 12.
4. Struktur data
   • Pengguna → Usaha → Siklus → Transaksi. Satu pengguna bisa punya banyak usaha, dan tiap usaha memakai satu template.
   • Kategori disalin dari template ke usaha saat usaha dibuat, dan pengguna bisa mengubah dan menambah.
   • Penanda jadwal disalin dari template ke siklus saat siklus dibuat, dan tanggalnya bisa diedit.
   • Transaksi melekat ke siklus atau ke "Biaya umum usaha" (tanpa siklus).
5. Alur utama
6. Daftar/Masuk. Pengguna baru diarahkan ke Buat usaha (jenis dan nama), lalu Beranda. Pengguna lama langsung ke Beranda.
7. Buat siklus, dari menu Siklus atau dari kartu "Belum ada siklus" di Beranda. Jumlah dan harga per ekor otomatis membuat pengeluaran bibit, dan jadwal terisi otomatis.
8. Catat lewat tombol melayang: pilih Keluar atau Masuk, isi, simpan.
9. Ganti usaha lewat pengalih di bar atas. "Tambah usaha" membuka Buat usaha.
10. Tutup siklus dari menu detail siklus: status jadi selesai, dan tanggal selesai default hari ini tetapi bisa diubah karena menentukan tahun rekap.
11. Menu dan layar
    • Bar atas: pengalih usaha (nama usaha dan panah).
    • Navigasi bawah: Beranda, Siklus, Transaksi, Akun. Tombol melayang "+ Catat".
    • Masuk dan Daftar: nama, email, kata sandi, konfirmasi kata sandi (untuk Daftar), dan reset kata sandi.
    • Buat usaha: tiga kartu jenis usaha dan nama usaha.
    • Beranda:
    o Filter periode (bulan ini, tahun ini, pilih tahun) dan pilihan dasar rekap (Siklus selesai | Tanggal transaksi).
    o Kartu Pemasukan, Biaya, Laba kotor, Laba bersih, dan baris Investasi berwarna abu.
    o Baris "Berjalan" (laba sementara) saat dasarnya siklus selesai.
    o Kartu siklus berjalan terbaru: nama, jumlah, umur, penanda berikutnya dengan hitung mundur, laba bersih sementara.
    o Daftar transaksi terbaru.
    • Siklus: daftar (Berjalan/Selesai), Buat siklus, Detail siklus (tab Ringkasan, Pengeluaran, Pemasukan, Jadwal).
    • Transaksi: daftar dengan filter Semua, Keluar, Masuk; bisa ubah dan hapus.
    • Akun: Profil, Pengaturan usaha (nama), Kategori, Keluar.
12. Pencatatan
    • Keluar: tanggal (default hari ini), kategori, jumlah, satuan, dan harga satuan (hanya untuk kategori yang memakai kuantitas), total (otomatis, bisa diubah), siklus atau biaya umum, catatan.
    • Masuk: tanggal, kategori, jumlah, satuan, harga satuan (otomatis terisi dari transaksi terakhir kategori yang sama), total, pembeli (opsional), siklus, catatan.
    • Pemilihan siklus di modal Catat:
    o Masuk: siklus wajib. Satu siklus berjalan berarti otomatis terpilih (chip yang bisa diganti), dua atau lebih berarti default yang terbaru, tidak ada berarti diarahkan ke Buat siklus.
    o Keluar: aturan sama, ditambah pilihan "Biaya umum usaha". Tanpa siklus berjalan, otomatis biaya umum.
    o Siklus selesai tidak muncul di pilihan, tapi transaksi lamanya tetap bisa diedit.
13. Perhitungan dan rekap
    • Laba kotor = pemasukan − biaya langsung. Laba bersih = laba kotor − biaya operasional. Investasi dikecualikan.
    • Laba siklus hanya dari transaksi siklus itu, berlabel "Sementara" saat berjalan dan final saat selesai.
    • Rekap periode memilih himpunan transaksi menurut dasar:
    o Siklus selesai: transaksi milik siklus yang tanggal selesainya jatuh di periode, ditambah biaya umum yang bertanggal di periode. Siklus berjalan tidak ikut, dan laba sementaranya tampil di baris "Berjalan".
    o Tanggal transaksi: semua transaksi bertanggal di periode, dari siklus mana pun (termasuk berjalan) dan biaya umum.
    • Default dasar rekap per template: Ayam Petelur memakai tanggal transaksi (siklusnya bisa lebih dari setahun), sedangkan Lele memakai siklus selesai. Pilihan diingat per usaha dan bisa diganti.
    • Siklus yang melewati akhir tahun dihitung utuh di tahun selesainya (dasar siklus selesai) atau terbagi menurut tanggal (dasar tanggal transaksi).
    • Semua angka dihitung dari transaksi, tidak disimpan sebagai kolom.
    • Tanggal penanda = tanggal mulai + (hari ke − umur saat masuk). Penanda yang sudah terlewati dilewati.
14. Template
    • Ayam Petelur:
    o Langsung: Ayam/DOC (kategori sistem "bibit"), Pakan, Vaksin/vitamin/obat.
    o Operasional: Gaji, Listrik/air, Lain-lain.
    o Investasi: Kandang/peralatan.
    o Pemasukan: Penjualan telur, Penjualan ayam afkir.
    o Penanda: Mulai bertelur, Puncak produksi, Perkiraan afkir.
    • Lele Pendederan: Benih, Pakan, Obat/vitamin; operasional sama; investasi Kolam/peralatan; pemasukan Penjualan benih; penanda Perkiraan jual.
    • Lele Pembesaran: sama dengan Pendederan; pemasukan Penjualan panen; penanda Perkiraan panen.
15. Non-fungsional
    • Mobile-first, mencatat maksimal tiga ketukan.
    • Teks UI seminimal mungkin: hanya label yang diperlukan, tanpa teks bantuan, slogan, atau kartu insight. Angka dalam format Rupiah Indonesia.
    • Pengguna hanya bisa mengakses usaha miliknya (policy dan FK komposit di database).
    • Hapus lunak untuk data keuangan, dan backup database rutin.
16. Teknologi dan skema
    • Teknologi: Laravel dan Filament versi terbaru yang kompatibel. Panel /app untuk pemilik (tenancy Usaha, registrasi dan reset kata sandi) dan panel /admin untuk kelola template. PostgreSQL. Uji coba di Laravel Cloud Starter.
    • Logika hitung ditaruh di kelas Action/Service terpisah dari Filament. Elemen kustom dibatasi: navigasi bawah, tombol melayang, kartu siklus, dan tema warna.
    • Skema mengikuti DDL final dengan perubahan berikut:
    o usaha mendapat user_id (pemilik), tanpa budget.
    o Tabel usaha_user, kolom transaksi.dibuat_oleh, dan semua kolom sumber_dana dihapus.
    o CHECK kategori: pengeluaran wajib punya klasifikasi, pemasukan tidak boleh.
    o Ditambah template_usaha.rekap_default dan usaha.rekap_dasar, serta indeks siklus (usaha_id, tanggal_selesai) untuk siklus selesai.
    • Tabel: users, template_usaha, template_kategori, template_penanda, usaha, kategori, siklus, siklus_penanda, transaksi.
17. Di luar MVP
    Budget dan pemantauan dana, sumber dana, pekerja dan anggota, grafik, catatan produksi telur harian, piutang, penyusutan, biaya berulang, pengingat penanda, notifikasi WhatsApp, pembenihan lele, login Google, ekspor laporan.
18. Ukuran keberhasilan
    Pengguna baru bisa membuat siklus pertama dalam kurang dari 5 menit dan mencatat setidaknya satu transaksi per minggu.
19. Asumsi yang masih berlaku
    • Lele Pendederan dan Lele Pembesaran adalah dua template terpisah.
    • Ayam bisa dimulai dari DOC atau pullet, lewat input umur saat masuk.
    • Semua angka default template (hari ke tiap penanda) hanya isian awal dan perlu divalidasi dengan peternak sebelum dipakai.
