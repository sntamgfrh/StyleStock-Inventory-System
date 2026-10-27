# StyleStock — Sistem Informasi Inventaris Fashion

StyleStock adalah aplikasi web untuk membantu pengelolaan inventaris fashion, termasuk data kategori, produk, supplier, barang masuk, barang keluar, dan histori transaksi.


## 1. Fitur Aplikasi

- Login pengguna.
- Pengelolaan kategori: tambah, edit, hapus, dan pencarian kategori (khusus Admin).
- Pengelolaan produk.
- Pengelolaan supplier.
- Pencatatan barang masuk.
- Pencatatan barang keluar.
- Histori transaksi.
- Pembatasan akses berdasarkan peran pengguna, jika sudah diterapkan.

## 2. Teknologi

- PHP
- MySQL/MariaDB
- PDO
- HTML dan CSS
- Apache melalui XAMPP

## 3. Persyaratan

- XAMPP (Apache dan MySQL/MariaDB)
- Browser web, misalnya Google Chrome
- Editor kode, misalnya Visual Studio Code

## 4. Instalasi

1. Letakkan folder proyek di `C:\xampp\htdocs\InventoryApp`.
2. Jalankan Apache dan MySQL dari XAMPP Control Panel.
3. Buka phpMyAdmin: `http://localhost/phpmyadmin/`.
4. Impor file SQL database proyek jika tersedia. Nama file SQL: [inventory_db].
5. Periksa konfigurasi database pada `config/database.php` dan sesuaikan dengan pengaturan lokal.
6. Buka aplikasi di `http://localhost/InventoryApp/public/login.php`.

## 5. Konfigurasi Database

Periksa file `config/database.php`. Sesuaikan host, port, nama database, username, dan password dengan konfigurasi lokal.

Contoh pengaturan yang perlu diverifikasi:
- Host: `127.0.0.1` atau `localhost`
- Port: `3307` 
- Nama database: `inventory_db` 
- Username: `root` **[PERIKSA]**
- Password: **[PERIKSA; jangan masukkan kata sandi pribadi ke repositori publik]**

## 6. Cara Menggunakan

1. Jalankan Apache dan MySQL dari XAMPP.
2. Buka halaman login StyleStock.
3. Masuk menggunakan akun demo yang sudah disiapkan.
4. Gunakan dashboard untuk membuka fitur yang tersedia.
5. Gunakan menu barang masuk dan barang keluar untuk menguji perubahan stok.
6. Buka Histori untuk memeriksa transaksi yang tercatat.

Akun demo:
- Admin: Admin	passhword:	Admin123!
- Staff: staff	passhword:  Staff123!

.

## 7. Pengujian Aplikasi

Isi kolom hasil aktual berdasarkan pengujian yang benar-benar dilakukan.

| No. | Kasus pengujian | Hasil yang diharapkan | Hasil aktual |
|---|---|---|---|
| 1 | Login dengan akun valid | Pengguna berhasil masuk | [ISI hasil] |
| 2 | Login dengan data tidak valid | Login ditolak dan pesan ditampilkan | [ISI hasil] |
| 3 | Menambah kategori tanpa nama | Validasi menolak data kosong | [ISI hasil] |
| 4 | Menambah kategori dengan nama duplikat | Data duplikat ditolak jika aturan unik diterapkan | [ISI hasil] |
| 5 | Barang masuk berhasil | Stok bertambah | [ISI hasil] |
| 6 | Barang keluar dengan stok cukup | Stok berkurang | [ISI hasil] |
| 7 | Barang keluar melebihi stok | Transaksi ditolak dan stok tidak berubah | [ISI hasil] |
| 8 | Staff membuka fitur khusus Admin | Akses ditolak jika pembatasan role diterapkan | [ISI hasil] |

Sertakan screenshot sebagai bukti pengujian. Jangan menandai pengujian berhasil sebelum hasil aktual diperiksa.

## 8. Struktur Folder

Struktur utama yang diketahui dari proyek:

```text
InventoryApp/
├── config/
│   └── database.php
├── helpers/
│   └── auth.php
└── public/
    ├── login.php
    ├── dashboard.php
    ├── kategori.php
    ├── produk.php
    ├── barang-masuk.php
    ├── histori.php
    └── [file fitur lain sesuai proyek]
```

Periksa nama file dan tambahkan file lain yang benar-benar ada di proyek.

 README dengan versi terakhir proyek.

## 10. Identitas Proyek

- Nama aplikasi: StyleStock
- Jenis aplikasi: Sistem Informasi Inventaris Fashion
- Nama penyusun: SINTA MAGFIRAH
- NIM: [250180043]
- Mata kuliah: Pemograman Web

