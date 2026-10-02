# Local XAMPP Notes

## Setup lokal

1. Copy `.env.example` menjadi `.env` bila belum ada.
2. Pastikan XAMPP menyalakan Apache dan MySQL.
3. Buat database `rt_rw_net`, lalu import `database/rt_rw_net.sql`.
4. Jika sebelumnya `.env` masih mengarah ke database lama seperti `nikonet`, ganti `DB_NAME` ke `rt_rw_net`.
5. Simpan project di `C:\xampp\htdocs\rtrw-net`.
6. Akses aplikasi lewat `http://localhost/rtrw-net`.

## Konfigurasi default lokal

- `APP_ENV=local`
- `APP_DEBUG=true`
- `DB_HOST=localhost`
- `DB_NAME=rt_rw_net`
- `DB_USER=root`
- `DB_PASS=` dikosongkan sesuai default XAMPP
- `MIKROTIK_TRANSPORT=socket`
- `MIKROTIK_HOST=192.168.88.1`
- `MIKROTIK_PORT=8728`

## Catatan MikroTik lokal

- Untuk jaringan lokal, koneksi default diprioritaskan ke API socket MikroTik.
- Ganti `MIKROTIK_HOST`, `MIKROTIK_USER`, dan `MIKROTIK_PASS` di `.env` sesuai router yang dipakai.
- Bila ingin memakai REST API, ubah `MIKROTIK_TRANSPORT=rest` lalu sesuaikan `MIKROTIK_REST_SCHEME`, `MIKROTIK_REST_PORT`, dan `MIKROTIK_REST_PATH`.

## Operasional

- Folder `storage/cache` dan `storage/logs` harus bisa ditulis oleh PHP.
- Health check tersedia di `/api/health.php`.
- Cron lokal yang bisa dijalankan:
  - `api/cron/check_expired.php`
  - `api/cron/scan_network.php`
