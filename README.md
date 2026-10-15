# SIDUK — Sistem Informasi Administrasi Kependudukan

Aplikasi web pelayanan dokumen kependudukan: **PHP native + MySQL/MariaDB**, tanpa framework, tanpa Composer.

## Fitur Utama
- **Form permohonan publik (tanpa login)** wizard 5 langkah: identitas + captcha → pilih jenis layanan → isian & unggah syarat **dinamis per jenis** → pilih tanggal verifikasi sesuai kuota harian → konfirmasi. Bukti pendaftaran (PDF A5 landscape + QR) dan email konfirmasi via PHPMailer.
- **Dashboard**: kartu statistik, horizontal bar chart per jenis permohonan, 5 permohonan & aktivitas terbaru.
- **Pengaturan aplikasi** (admin/kadis/kabid): nama dinas, alamat, logo, format nomor pendaftaran dengan placeholder **`{kode}` / `{Kode}` / `{KODE}`** (kode jenis permohonan — chip klik-untuk-sisip + preview contoh langsung dari data DB), data & gambar tanda tangan kepala dinas, SMTP.
- **CRUD pengguna** (admin/kadis), **CRUD jenis permohonan + field isian + syarat + kuota/hari** (admin/kadis/kabid), **CRUD loket** (admin/kadis/kabid).
- **Pemrosesan permohonan** (petugas): filter/cari, view, verifikasi → cetak formulir (A4) & bukti verifikasi (A5 landscape), atur tanggal pengambilan, batalkan dengan alasan.
- **Penyerahan dokumen** (petugas): serahkan sendiri/diwakilkan (data penjamin) → status selesai.
- Semua aktivitas tercatat dalam **log**.

## Responsif & Upload Mobile
- Tampilan adaptif desktop ↔ mobile (sidebar dashboard menjadi drawer + hamburger, tabel dapat digeser, form satu kolom di layar kecil).
- **Setiap field upload menyediakan opsi Kamera / Galeri / Dokumen saat diakses dari HP** (`assets/js/app.js` membuat input file tersembunyi dengan atribut `capture="camera"` untuk kamera, `accept="image/*"` untuk galeri, dan `application/pdf,...` untuk dokumen; tombol sumber hanya tampil pada layar ≤991px).

## Library (instalasi manual, tanpa Composer)
| Library | Lokasi | Fungsi |
|---|---|---|
| domPDF v3.1 | `lib/dompdf/` | Generate PDF (autoload: `lib/dompdf/autoload.inc.php`) |
| PHPMailer v6.9 | `lib/phpmailer/` | Email konfirmasi pendaftaran |
| QRCode API online | api.qrserver.com (helper `qr_url()`) | QR verifikasi pada bukti |

## Instalasi
1. Salin folder proyek ke htdocs/vari/www, atau jalankan server bawaan PHP:
   ```bash
   mysql -u root -p < database.sql          # membuat DB `siduk` + data awal
   php -S localhost:8000                    # akses http://localhost:8000
   ```
2. Konfigurasi koneksi bila perlu di `includes/config.php`.
3. Seting email/logo/format nomor melalui **Dashboard → Pengaturan Aplikasi**.
4. Pastikan folder `uploads/` dapat ditulis (chmod 775).

## Akun Demo (password: `password123`)
| Username | Role |
|---|---|
| `admin` | Administrator |
| `kadis` | Kepala Dinas |
| `kabid` | Kepala Bidang |
| `petugas` | Petugas Loket |

## Struktur Singkat
```
├── index.php, permohonan*.php, cek-permohonan.php   # halaman publik
├── dashboard.php, pemrosesan.php, penyerahan.php     # layanan petugas
├── pengaturan-aplikasi.php, pengguna.php,
│   jenis-permohonan.php, loket.php, log-aktivitas.php
├── pdf/            # template PDF (domPDF)
├── app/partials/   # header/footer publik & dashboard
├── includes/       # config, db, functions, helpers, mailer, pdf
├── assets/         # css/js tema navy+gold responsif
├── lib/            # dompdf & phpmailer (manual)
└── database.sql    # skema + seed
```
