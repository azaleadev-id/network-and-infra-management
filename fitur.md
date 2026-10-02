# RTRWNet Modern UI/UX Design Specification

Berdasarkan analisis lengkap dari dokumentasi fitur di `fitur.md`, berikut adalah spesifikasi desain UI/UX modern untuk RTRWNet yang fokus pada pengalaman pengguna yang intuitif, aksesibel, dan konsisten di semua peran pengguna (superadmin, owner, admin, operator, viewer) serta platform web dan mobile.

## 🎯 Prinsip Desain Utama

1. **Kebijakan Mobile-First & Responsif**  
   Desain yang dioptimalkan untuk perangkat seluler terlebih dahulu, lalu diperbesar untuk desktop, mengukung penggunaan resmi yang mayoritas dilakukan melalui aplikasi mobile oleh operator lapangan.

2. **Hierarki Informasi yang Jelas**  
   Menggunakan prinsip progresif disclosure - menunjukkan informasi esensial pertama, dengan opsi untuk mengakses detail lebih lanjut ketika diperlukan.

3. **Konsistensi Visual & Interaksi**  
   Menggunakan sistem desain yang terstandarisasi untuk mengurangi beban kognitif dan meningkatkan kecepatan pembelajaran.

4. **Aksesibilitas yang Mewajarkan**  
   Mengikuti WCAG 2.1 AA untuk memastikan aksesibilitas untuk semua pengguna termasuk yang memiliki disabilitas.

5. **Feedback yang Jelas & responsibility**  
   Setiap tindakan pengguna memberikan respons visual yang jelas dalam waktu <100ms untuk menjaga responsivitas.

6. **Pelaksanaan yang Berfokus pada Tugas**  
   Mengeliminasi elemen yang tidak perlu dan fokus pada membantu pengguna menyelesaikan tugas spesifik mereka secara efisien.

## 🎨 Sistem Desain Visual

### Palet Warna (Modern & Profesional)
| Nama | Hex | Penggunaan |
|------|-----|------------|
| **Primary** | #2563EB | Tombol utama, tautan aktif, akcent kunci |
| **Secondary** | #64748B | Teks sekunder, ikon non-aktif |
| **Success** | #10B981 | Status aktif/sukses, tombol konfirmasi positif |
| **Warning** | #F59E0B | Peringatan, status menunggu |
| **Error** | #EF4444 | Status gagal/error, tombol penghapusan |
| **Background** | #F8FAFC | Latar belakang halaman |
| **Surface** | #FFFFFF | Kartu, formulir, modal |
| **Border** | #E2E8F0 | Batas antar-komponen |
| **Text Primary** | #1E293B | Teks utama |
| **Text Secondary** | #64748B | Teks sekunder, placeholder |

### Tipografi
- **Font Utama**: Inter (sans-serif, modern, tinggi keterbacaan)
- **Skala**: 
  - Display: 2.5rem (40px) 
  - Heading 1: 2rem (32px)
  - Heading 2: 1.5rem (24px)
  - Heading 3: 1.25rem (20px)
  - Body: 1rem (16px)
  - Small: 0.875rem (14px)
- **Berat**: 
  - Regular: 400
  - Medium: 500
  - Semi-bold: 600
  - Bold: 700

### Radius & Bayangan
- **Radius**: 
  - Sm: 0.25rem (4px)
  - Md: 0.375rem (6px)
  - Lg: 0.5rem (8px)
  - Pill: 9999px
- **Bayangan**:
  - Sm: 0 1px 3px rgba(0,0,0,0.05)
  - Md: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -2px rgba(0,0,0,0.1)
  - Lg: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05)

## 🗺️ Arsitektur Informasi & Alur Pengguna

### Struktur Navigasi Utama
```
Dashboard
├── Ringkasan Singkat (Statistik Kunci)
├── Aktivitas Terbaru
└─▶ Alert Sistem (5 Terbaru)

Pengguna
├── Daftar Pengguna (Filter: Status, Pencarian)
├── Tambah Pengguna
└─▶ Detail Pengguna

Tagihan
├── Daftar Tagihan (Filter: Status Pembayaran, Jatuh Tempo)
├── Buat Tagihan Baru
├── Tagihan Massal (Seragamkan Jatuh Tempo)
└─▶ Detail Tagihan

Monitoring
├── Peta Jaringan (Topologi)
├── Status ODP/ODC (Status Real-time)
├─▶ Alert Aktif (ODP/ODC Down, Warning)
└─▶ Grafik Traffic (Historis)

Isolir
├── Konfigurasi Profil Isolir
├── Status Isolir per Pengguna
└─▶ Jalankan Isolir Otomatis (Cron)

Pengaturan
├── Profil Tenant
├── Konfigurasi MikroTik
├── Pengaturan Isolir
├── Pengaturan Backup
├── Channel Notifikasi
└─▶ Pengguna Administrasi

Laporan
├── Harian
├── Mingguan
├── Bulanan
└─▶ Ekspor (PDF/CSV)
```

## 🖥️ Wireframe Utama (Deskripsi Detail)

### 1. Dashboard Utama (Halaman Utama)
**Tujuan**: Memberikan overview singkat tentang kesehatan jaringan dan aktivitas krusial.

**Komponen**:
- **Header**: 
  - Logo aplikasi di kiri
  - Nama tenant yang sedang aktif di tengah
  - Dropdown profil pengguna (nama, avatar, role) di kanan dengan opsi: Profil, Pengaturan, Keluar
  
- **Kartu Statistik Utama** (4 kolom dalam grid responsif):
  1. **Pengguna Online**: Icon wifi + angka besar (misal: 124) + label "Online"
     - Warna hijau jika >80% kapasitas, kuning jika 50-80%, merah jika <50%
  2. **Pengguna Offline**: Icon wifi-slash + angka + label "Offline"
  3. **Alert Aktif**: Icon bell + angka + label "Alert" (badge merah jika >0)
     - Klik membuka modal daftar alert
  4. **Tagihan Jatuh Tempo**: Icon calendar + angka + label "Jatuh Tempo"
     - Warna merah jika ada, hijau jika 0

- **Grafik Tren Pengguna** (7 hari terakhir):
  - Area chart menunjukkan jumlah online/offline/hari
  - Tooltip menunjukkan angkaExact saat hover
  - Legend di pojok kanan bawah

- **Daftar Alert Terbaru** (5 item):
  - Setiap item: Icon berdasarkan tipe (odp_down, odc_down, warning, user_offline)
  - Waktu relatif (misal: "2 menit yang lalu")
  - Pesan singkat yang dapat di-expand untuk detail lengkap
  - Warna latar belakang berdasarkan severity (merah untuk critical, kuning untuk warning)

- **Aktivitas Sistem Terbaru**:
  - Timeline vertikal dengan aktivitas seperti: login pengguna, perubahan tagihan, isolir diterapkan
  - Icon kecil di setiap item menunjukkan jenis aktivitas

### 2. Halaman Pengguna (User Management)
**Tujuan**: Mengelola semua informasi pengguna termasuk registrasi, status, dan koneksi.

**Komponen**:
- **Toolbar Atas**:
  - Tombol "+ Tambah Pengguna" (primary button, icon plus)
  - Input pencarian dengan placeholder "Cari berdasarkan nama, username, atau nomor telepon"
  - Dropdown filter Status: [Semua] • Online • Offline • Disabled
  - Dropdown filter Paket: [Semua Paket] • Paket A • Paket B (dinamis dari data)
  
- **Tabel Data Pengguna** (responsif dengan horizontal scroll pada mobile):
  - Kolom: Avatar | Nama | Username | Status | Paket | Terakhir Aktif | Aksi
  - Status ditampilkan sebagai badge berwarna:
    - Online: Lingkaran hijau pengisi
    - Offline: Lingkaran abu-abu putus-putus
    - Disabled: Lingkaran merah pengisi
  - Kolom "Aksi" berisi ikon:
    - Edit (pensil)
    - Detail (mata)
    - Nonaktifkan/Aktifkan (toggle switch)
    - Reset Password (kunci)

- **Kartu Detail Pengguna** (saat menyoroti baris atau klik "Detail"):
  - Tampil dalam slide-in panel dari kanan (desktop) atau full-screen modal (mobile)
  - Bagian atas: Foto profil (placeholder jika tidak ada), nama lengkap, username
  - Tabbed interface:
    1. **Profil**:
       - Informasi dasar: Nama, Username, Email, No. Telp
       - Koordinat GPS (jika ada) dengan link ke Google Maps
       - Tanggal pembuatan akun
    2. **Koneksi Jaringan**:
       - Status saat ini (Online/Offline/Disabled)
       - ODP terkait (nama dan lokasi)
       - Alokasi bandwidth
       - Riwayat login 7 hari terakhir
    3. **Tagihan**:
       - Ringkasan tagihan bulan ini: Jumlah, Status pembayaran, Jatuh tempo
       - Tombol "Lihat Riwayat Tagihan"
       - Status isolir (ikon + teks: Aktif/Tidak Aktif)
    4. **Aktivitas**:
       - Log aktivitas terakhir (login, perubahan profil, dll)

### 3. Halaman Tagihan (Billing Management)
**Tujuan**: Mengelola siklus tagihan termasuk pembuatan, pembayaran, dan integrasi isolir.

**Komponen**:
- **Filter & Action Bar**:
  - Rentang tanggal: [Bulan Ini] • [Bulan Lalu] • [3 Bulan Terakhir] • [Custom]
  - Dropdown Status Pembayaran: [Semua] • Belum Bayar • Sudah Bayar
  - Dropdown Status Tagihan: [Semua] • Aktif • Expired • Pending
  - Tombol "+ Tambah Tagihan" (primary)
  - Dropdown "Actions Terpilih": [Tandai Sudah Bayar] • [Seragamkan Jatuh Tempo] • [Export CSV]

- **Tabel Tagihan**:
  - Kolom: Pengguna | Jumlah | Jatuh Tempo | Status Pembayaran | Status Tagihan | Isolir | Aksi
  - Format mata uang: Rp. 150.000,00
  - Status pembayaran sebagai badge:
    - Belum Bayar: Kuning
    - Sudah Bayar: Hijau
  - Status tagihan sebagai badge:
    - Aktif: Biru
    - Expired: Merah
    - Pending: Abu-abu
  - Kolom Isolir: Ikon kunci + status text (Menunggu/Terisolir/Gagal/DiPulihkan)
  - Kolom Aksi: 
    - Ikon uang (Bayar)
    - Ikon isolir (Terapkan Isolir)
    - Ikon edit (Ubah)
    - Ikon hapus (Hapus) - hanya muncul saat hover untuk mencegah klik tidak sengaja

- **Modal Tambah/Edit Tagihan**:
  - Form dalam 2 kolom (desktop) atau stacked (mobile):
    - Kolom 1: 
      - Dropdown Pengguna (with search)
      - Nominal (input currency dengan format otomatis)
      - Jatuh Tempo (date picker)
      - Status Tagihan (dropdown)
    - Kolom 2:
      - Status Pembayaran (radio buttons: Belum Bayar/Sudah Bayar)
      - Catatan (textarea)
      - Isolir Aktif (toggle switch)
        - Jika aktif: 
          - Waktu Isolir (time picker, default 00:00)
          - Profil Isolir (dropdown, terisi dari konfigurasi tenant)
  - Tombol: Batal (secondary) & Simpan (primary)

### 4. Halaman Monitoring Jaringan
**Tujuan**: Memvisualisasikan kondisi jaringan dalam waktu nyata dan mengelola alert.

**Komponen**:
- **Tabbed Interface** (atas halaman):
  1. Peta Topologi
  2. Status Perangkat
  3. Alert Aktif
  4. Grafik Traffic

- **Tab Peta Topologi**:
  - Diagram alur: Server → ODC → ODP → Pengguna
  - Setiap-node berbentuk lingkaran dengan label dan status indicator:
    - Hijau: Normal
    - Kuning: Peringatan
    - Merah: Down
    - Abu-abu: Tidak TERkoneksi: Putus-putus abu-abu
  - Klik node menampilkan detail dalam popup:
    - Nama perangkat
    - Status terkini
    - Statistik terkait (jika ODP: jumlah user online/offline)
  - Garis antar-node berubah warna berdasarkan status koneksi terburuk di jalur tersebut
  - Zoom dan pan control untuk peta besar

- **Tab Status Perangkat**:
  - Tabel dengan kolom:
    - Tipe: Icon (Server/ODC/ODP/User) + Nama
    - Status: Badge berwarna (seperti di atas)
    - Detail: Informasi spesifik per tipe:
      - ODP: "X dari Y user online"
      - ODC: "Z ODP terkoneksi"
      - User: "Terhubung ke ODP [nama]"
    - Terakhir Dilihat: Waktu relatif
    - Aksi: Ikon refresh (manual check) dan detail (mata)

- **Tab Alert Aktif**:
  - Sama seperti di dashboard tetapi tanpa batasan jumlah
  - Filter di atas: 
    - Tipe Alert: [Semua] • ODP Down • ODC Down • Warning • User Offline
    - Rentang Waktu: [24 Jam] • [7 Hari] • [30 Hari]
    - Status: [Aktif] • [Tertangani] • [Semua]
  - Setiap alert item menampilkan:
    - Icon berdasarkan tipe
    - Judul singkat (misal: "ODP-5 DOWN")
    - Deskripsi lengkap
    - Waktu deteksi
    - Tombol Tandai Selesai (hanya untuk alert yang dapat di-resolve secara manual)
    - Grafik mini trending (untuk alert berbasis persentase seperti warning ODP)

- **Tab Grafik Traffic**:
  - Pilihan metered: 
    - Traffic (Rx/Tx Bytes)
    - CPU Load
    - User Aktif
  - Rentang waktu: 
    - [1 Jam] • [6 Jam] • [24 Jam] • [7 Hari] • [30 Hari]
  - Chart type bergantung pada metrik:
    - Traffic: Area chart dual (RX dan TX)
    - CPU Load: Line chart dengan threshold line pada 80%
    - User Aktif: Line chart

### 5. Halaman Pengaturan Isolir (Isolir Configuration)
**Tujuan**: Mengelola profil isolir MikroTik dan mengawasi status penerapannya.

**Komponen**:
- **Header**: 
  - Judul: "Konfigurasi Isolir MikroTik"
  - Deskripsi singkat: "Atur profil isolir yang akan diterapkan kepada pengguna dengan tagihan jatuh tempo yang belum dibayar."

- **Form Konfigurasi Profil Isolir** (card dengan elevation):
  - Nama Profile: Input text (default: "ISOLIR")
    - Validasi: Tidak boleh kosong, maksimal 32 karakter
    - Helper text: "Nama profil PPP di MikroTik yang akan digunakan untuk izolir"
  - Batas Kecepatan: Input text dengan format validator (contoh: "64k/64k")
    - Helper text: "Format: upload/download (contoh: 64k/64k, 1M/512k)"
    - Default: "64k/64k"
    - Validasi哈斯마크: Harus berisi angka diikuti oleh k/bps/kbps/mbps/gbps
  - Komentar: Textarea (placeholder: "Deskripsi singkat tentang tujuan profil ini")
    - Default: "Profile isolir otomatis dari aplikasi RTRWNet"
  - Tombol: 
    - Simpan Konfigurasi (primary)
    - Uji Koneksi MikroTik (secondary) - membuka modal hasil koneksi

- **Status Koneksi MikroTik** (card di bawah form):
  - Status koneksi: 
    - Ikona lingkaran + teks: 
      - Hijau: Terhubung
      - Kuning: Meng Hubungkan...
      - Merah: Gagal Terhubung
  - Detail koneksi (jika terhubung):
    - Host: [alamat MikroTik]
    - Transport: [Socket/REST]
    - Profile Isolir Terdeteksi: [Ya/Tidak] + nama jika ada
    - Tombol: Buat Profile Jika Tidak Ada (primary action jika belum ada)

- **Tabel Status Isolir Pengguna**:
  - Hanya menampilkan pengguna dengan isolir diaktifkan di tagihan mereka
  - Kolom:
    - Pengguna: Nama + Username
    - Tagihan Terkait: Nominal + Jatuh tempo
    - Status Isolir: Badge berwarna (Menunggu/Terisolir/Gagal/DiPulihkan)
    - Profil Sebelumnya: Nama profile PPP sebelum izolir (jika diketahui)
    - Waktu Aksi: Timestamp terakhir isolir/di-pulihkan
    - Aksi:
      - Terapkan Isolir Sekarang (jika status Menunggu/Gagal)
      - Pulihkan Profil Sekarang (jika status Terisolir)
      - Lihat Log (membuka modal detail operasi MikroTik)

## 📱 Pertimbangan Mobile-Spesifik

Karena aplikasi memiliki endpoint mobile khusus, desain harus dioptimalkan untuk penggunaan melalui handphone:

### Navigasi Bottom Tab (Mobile)
1. Beranda (Dashboard ringkasan)
2. Pengguna (ikon pengguna)
3. Tagihan (ikon uang)
4. Monitoring (ikon sinyal)
5. Lainnya (ikon titik tiga - berisi Pengaturan, Laporan, dll)

### Adaptasi Layout
- **Sidebar** di desktop menjadi **bottom navigation** atau **drawer** di mobile
- **Tabel data** menggunakan mode "card" di mobile:
  - Setiap baris menjadi kartu vertikal dengan semua informasi penting
  - Tombol aksi berada di bagian bawah kartu
- **Form input** menggunakan full-width fields dengan spacing yang cukup untuk sentuhan
- **Date/time picker** menggunakan native mobile picker saat mogelijk
- **Grafik dan visualisasi** menggunakan library responsif yang berskala dengan layar

### Interaksi Sentuh
- Minimum touch target: 48x48px
- Gestur swipe untuk navigasi antar-tab (di mana sesuai)
- Long-peek untuk opsi tambahan (misal: swipe left pada item tabel untuk reveal aksi)
- Feedback haptic untuk tindakan kritis (hapus, terapkan isolir)

## ♿ Pedoman Aksesibilitas (WCAG 2.1 AA)

1. **Kontras Warna**:
   - Teks dan latar belakang minimal 4.5:1 untuk teks normal
   - 3:1 untuk teks besar (18pt+ atau 14pt bold)
   - Elemen interaktif (tombol, link) minimal 3:1 terhadap所在区域

2. **Navigasi Keyboard**:
   - Semua fungsi dapat diakses hanya menggunakan papan ketik
   - Urutan tab yang logis dan intuitif
   - Indeks fokus yang jelas (minimum 2px outline, contrast ≥ 3:1)

3. **Teks Alternatif**:
   - Semua gambar berguna memiliki alt text yang deskriptif
   - Ikon dekoratif memiliki `aria-hidden="true"`
   - Ikon fungsional memiliki label teks yang sesuai (menggunakan `visually-hidden` class atau `aria-label`)

4. **Ukuran Text yang Fleksibel**:
   - Mendukung peningkatan ukuran teks hingga 200% tanpa kehilangan konten atau fungsi
   - Menggunakan unit relatif (rem, em) bukan fixed (px)

5. **Navigasi yang Jelas**:
   - Judul halaman yang deskriptif dan unik
   - Label formulir yang jelas dan terkait dengan input melalui `<label>` atau `aria-label`
   - Pesan error yang spesifik dan memberikan saran perbaikan

6. **Kontrol Waktu**:
   - Tidak ada batas waktu yang tidak terduga untuk mengeksekusi tindakan
   - Jika ada timeout (misal: sesi), ada peringatan dan kesempataire untuk memperpanjang

## 💡 Rekomendasi Implementasi Teknis

### Frontend Stack (Jika menggunakan framework modern)
- **Framework**: React 18+ atau Vue 3 (untuk pengembangan yang skalabel)
- **UI Library**: 
  - Pilihan 1: Ant Design (kaya komponen, cocok untuk aplikasi enterprise)
  - Pilihan 2: MUI (Material-UI) (material design, banyak komunitas)
  - Pilihan 3: Chakra UI (aksesibilitas-first, mudah theme-able)
  - Pilihan 4: Tailwind CSS + Headless UI (untuk kontrol maksimal)
- **State Management**: 
  - Redux Toolkit atau Zustand untuk state global
  - React Query / TanStack Query untuk data fetching dan caching
- **Form Handling**: React Hook Form + Zod untuk validasi
- **Charting**: 
  - Recharts atau Chart.js untuk grafik sederhana
  - Victory atau Visx untuk visualisasi khusus (seperti topologi jaringan)
- **Maps**: Leaflet atau Mapbox GL JS untuk peta topologi (lebih ringan daripada Google Maps untuk use case ini)
- **Real-time Updates**: 
  - Socket.io untuk update real-time jika backend mendukung
  - Alternatif: Polling interval yang bijak (5-15 detik) untuk data yang tidak perlu update instant

### Integrasi dengan Backend yang Ada
1. **API Endpoints**: 
   - Manfaatkan semua endpoint API yang telah ada di `/api/`
   - Pastikan semua respons mengikuti format `{success: boolean, data: any, message: string}`
   - Implementasikan penanganan error yang konsisten berdasarkan HTTP status code

2. **Otentikasi**:
   - Untuk web: Gunakan sesi PHP yang ada dengan middleware untuk memvalidasi di frontend
   - Untuk mobile: Implementasikan token-based authentication sesuai dengan `mobile_auth.php`
   - Refresh token mekanisme untuk session yang panjang

3. **Caching**:
   - Manfaatkan sistem caching yang sudah ada di `storage/cache/` untuk data yang tidak berubah sering (misal: daftar tenant, konfigurasi)
   - Implementasikan client-side caching dengan stale-while-revalidate strategy untuk data yang sering berubah

4. **Error Handling**:
   - Tampilkan pesan error yang ramah pengguna saat ada kegagalan API
   - Logging kesalahan ke layanan pemantauanError (seperti Sentry) untuk debugging
   - Fallback UI untuk ketika layanan tidak tersedia (offline mode terbatas untuk operasi kritis)

### Struktur Proses Pengembangan
1. **Tahap 1**: Fokus pada alur inti (login, dashboard, manajemen pengguna dasar)
2. **Tahap 2**: Fitur tagihan dan isolir integrasi
3. **Tahap 3**: Monitoring jaringan dan alert system
4. **Tahap 4**: Pengaturan avançed dan laporan
5. **Tahap 5**: Optimasi performa dan aksesibilitas

## 📊 Metrik Kesuksesan UX

Untuk mengukur efektivitas desain:

1. **Task Success Rate**: 
   - Target: ≥85% pengguna dapat menyelesaikan tugas kunci (login, lihat dashboard, bayar tagihan) tanpa bantuan

2. **Time-on-Task**: 
   - Target: Turunkan waktu untuk tugas rutiner 30% dari versi sebelumnya

3. **System Usability Scale (SUS)**:
   - Target: Skor ≥80 (skala baik)

4. **Error Rate**:
   - Target: ≤5% kesalahan pengguna dalam formulir kritis (tagihan, isolir)

5. **Retention & Engagement**:
   - Target: ≥70% pengguna aktif kembali mingguan setelah minggu pertama penggunaan

## 📖 Catatan Implementasi

1. **Penggunaan Warna dengan Bijak**:
   - Jangan mengandalkan warna saja untuk menyampaikan informasi (misal: gunakan ikon + teks untuk status)
   - Pastikan palet kerja baik dalam mode gelap (jika diimplementasikan di masa depan)

2. **Prinsip Progressive Disclosure**:
   - Tampilkan hanya informasi esensial pada tampilan awal
   - Gunakan accordion, tab, dan modal untuk menampilkan detail lebih lanjut ketika diperlukan

3. **Feedback yang Konsisten**:
   - Gunakan toast notification untuk pesan non-interuptif (sukses, informasi)
   - Gunakan modal untuk tindakan yang membutuhkan konfirmasi atau input kritis
   - Gunakan inline validation untuk formulir dengan pesan error yang spesifik

4. **Optimasi Performa**:
   - Lazy load halaman yang tidak kritis
   - Implementasikan virtual scrolling untuk daftar panjang (pengguna, tagihan)
   - Compress dan optimize aset (gambar, font)

5. **Testing**:
   - Lakukan usability testing dengan pengguna sebenarnya (operator lapangan, admin)
   - Uji responsifitas pada berbagai ukuran layaman (320px шириной hingga 1920px dan lebar)
   - Aksesibilitas testing dengan alat seperti axe-core atau Lighthouse

Desain ini memberikan fondasi solida untuk menciptakan antarmuka yang modern, intuitif, dan sepennya selaras dengan semua fitur yang telah didokumentasikan dalam `fitur.md`. Dengan fokus pada claritas, efisiensi, dan aksesibilitas, RTRWNet dapat memberikan pengalaman pengguna yang jauh lebih baik daripada sistem manajemen jaringan tradisional.