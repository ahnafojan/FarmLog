<div x-data="{ offline: !navigator.onLine }" x-on:offline.window="offline = true" x-on:online.window="offline = false">
    <div x-show="offline" role="status" aria-live="polite" aria-atomic="true"
        style="
            display: none;
            padding: 12px 16px;
            background: #fef3c7;
            color: #78350f;
            text-align: center;
            font-size: 14px;
            line-height: 1.5;
        ">
        Koneksi internet terputus.
        Hubungkan kembali sebelum berpindah halaman atau menyimpan perubahan.
    </div>
</div>
