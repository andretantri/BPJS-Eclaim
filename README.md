# 🏥 ClaimGuard — Sistem Manajemen Klaim BPJS & Casemix

Aplikasi manajemen dan verifikasi klaim BPJS Kesehatan serta casemix rumah sakit berbasis **Laravel 12/13 + Inertia.js v2 + React 19 + TypeScript + Tailwind CSS**.

Aplikasi ini mencakup seluruh siklus operasional casemix: mulai dari autentikasi multi-role, dashboard analitik eksekutif, manajemen klaim episode pasien, alur verifikasi klinis 6 tahap, sinkronisasi batch ke **INA-CBGs E-Claim**, hingga pelaporan.

---

## 📋 Daftar Isi

1. [Kebutuhan Sistem](#-kebutuhan-sistem)
2. [Panduan Instalasi & Setup](#-panduan-instalasi--setup)
3. [Akun Pengguna (Username & Password)](#-akun-pengguna-username--password)
4. [Konfigurasi Integrasi BPJS (Opsional)](#-konfigurasi-integrasi-bpjs-opsional)
5. [Fitur & Alur Kerja Aplikasi](#-fitur--alur-kerja-aplikasi)
6. [Struktur Basis Data (ERD)](#-struktur-basis-data-erd)
7. [Pengujian Otomatis (Testing)](#-pengujian-otomatis-testing)
8. [Troubleshooting](#-troubleshooting)

---

## 💻 Kebutuhan Sistem

Sebelum menginstal, pastikan lingkungan pengembangan Anda telah terpasang:

- **PHP**: `>= 8.2` (Direkomendasikan PHP 8.3 atau 8.4)
- **Composer**: `>= 2.x`
- **Node.js**: `>= 18.x` (Direkomendasikan Node.js 20+) & **npm**
- **Database**:
  - **SQLite** (Default, siap pakai tanpa konfigurasi tambahan), atau
  - **MySQL** `>= 8.0` / **PostgreSQL** `>= 14`
- Ekstensi PHP wajib: `pdo`, `pdo_sqlite` atau `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `curl`.

---

## 🚀 Panduan Instalasi & Setup

Ikuti langkah-langkah berikut secara berurutan untuk menjalankan proyek dari awal:

### 1. Clone Repository
```bash
git clone <URL_REPOSITORY_ANDA>
cd BPJS
```

### 2. Install Dependensi PHP (Composer)
```bash
composer install
```

### 3. Install Dependensi Frontend (Node.js)
```bash
npm install
```

### 4. Salin & Konfigurasi File Environment
```bash
cp .env.example .env
```

### 5. Generate Application Key
```bash
php artisan key:generate
```

### 6. Konfigurasi Basis Data
Buka file `.env`, lalu pilih salah satu mode basis data di bawah ini:

#### Opsi A: Menggunakan SQLite (Rekomendasi Cepat / Dev Lokal)
Buat file database kosong (jika belum ada) dan sesuaikan `.env`:
```env
DB_CONNECTION=sqlite
# Kosongkan atau hapus variabel DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD
```
Buat berkas SQLite jika belum ada:
```bash
touch database/database.sqlite
```

#### Opsi B: Menggunakan MySQL
Buat database bernama `claimguard` pada MySQL lokal Anda, lalu sesuaikan `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=claimguard
DB_USERNAME=root
DB_PASSWORD=password_mysql_anda
```

### 7. Jalankan Migrasi & Database Seeder
Perintah ini akan membuat seluruh 17 tabel serta mengisinya dengan data master rumah sakit, dokter, kamus ICD-10 & ICD-9-CM, data klaim, berkas rekam medis, dan akun pengguna:
```bash
php artisan migrate:fresh --seed
```

### 8. Build Asset Frontend
```bash
npm run build
```

### 9. Jalankan Aplikasi
Buka **dua tab terminal**:

**Terminal 1 (Server Laravel):**
```bash
php artisan serve
```

**Terminal 2 (Vite Hot-Reload - opsional untuk dev):**
```bash
npm run dev
```

Buka peramban (browser) di alamat:
👉 **`http://localhost:8000`**

---

## 🔑 Akun Pengguna (Username & Password)

Database seeder secara otomatis menyediakan 3 akun dengan hak akses peran berbeda. Seluruh password default adalah: **`password`**

| Role / Jabatan | Nama Lengkap | Email (Username) | Password | Hak Akses & Tugas |
|---|---|---|---|---|
| **Petugas Casemix** | Nadia Putri | `nadia@cakramedika.com` | `password` | Input klaim baru, unggah revisi dokumen medis, periksa pra-klaim, validasi koding. |
| **Verifikator Medis** | Bima Satya | `bima@cakramedika.com` | `password` | Verifikasi klinis, audit kepesertaan V-Claim, persetujuan klaim, eksekusi batch sync E-Claim. |
| **Admin Casemix** | Administrator Casemix | `admin@cakramedika.com` | `password` | Hak akses penuh sistem, konfigurasi rumah sakit, audit log, dan ekspor pelaporan. |

> 💡 **Fitur Cepat:** Pada halaman login (`/login`), tersedia tombol **1-Click Akun Simulasi** untuk mengisi otomatis kredensial tanpa perlu mengetik manual.

---

## ⚙️ Konfigurasi Integrasi BPJS (Opsional)

Secara default, aplikasi berjalan dalam **Mode Simulasi Mandiri (Mock Gateway)** sehingga seluruh fungsi pencarian SEP, validasi kepesertaan, grouping tarif INA-CBGs, dan batch sync dapat diuji langsung tanpa kredensial BPJS nyata.

Jika Anda ingin menghubungkan ke endpoint BPJS TrustMark / V-Claim & E-Claim resmi, tambahkan kredensial pada file `.env`:

```env
# Integrasi BPJS V-Claim (Katalog / Web Service API)
BPJS_VCLAIM_URL="https://apijkn-dev.bpjs-kesehatan.go.id/vclaim-rest-dev"
BPJS_CONS_ID="12345"
BPJS_SECRET_KEY="rahasia_bpjs"
BPJS_USER_KEY="user_key_vclaim"

# Integrasi BPJS E-Claim (INA-CBGs)
BPJS_ECLAIM_URL="http://192.168.1.100/E-Klaim/service.php"
```

*Jika variabel di atas dikosongkan, sistem otomatis beralih ke simulasi cerdas sesuai spesifikasi klaim RS Cakra Medika.*

---

## 🌟 Fitur & Alur Kerja Aplikasi

```
[Login] ──> [Dashboard Casemix] ──> [Daftar Klaim Pasien]
                                              │
     ┌────────────────────────────────────────┘
     ▼
[Alur Verifikasi 6 Tahap]
 1. BPJS V-Claim        : Audit keabsahan nomor SEP dan status kepesertaan KIS
 2. Pra-Klaim           : Audit 5 berkas wajib (SEP, Rujukan, Resume Medis, Billing, Toraks)
 3. Validasi ICD-10     : Pencocokan koding diagnosis primer & sekunder
 4. Validasi ICD-9-CM   : Verifikasi tindakan & prosedur medis
 5. Deteksi Risiko      : Penapisan sengketa / dispute (e.g. Resume belum disahkan DPJP)
 6. Tinjauan Akhir      : Ringkasan kelayakan klaim & Tombol Approval
     │
     ▼
[Siap Diajukan] ──> [Sync E-Claim BPJS (INA-CBGs)] ──> [Pelaporan & Rekapitulasi]
```

- **Dashboard (`/dashboard`)**: Menampilkan 4 metrik KPI utama, diagram batang tren klaim vs dibayar (Recharts), diagram persentase kesiapan (81,8%), antrean prioritas, serta aktivitas verifikator real-time.
- **Klaim Pasien (`/klaim`)**: Filter tab status (*Semua, Menunggu, Bermasalah, Siap diajukan*), pencarian teks instan, drawer detail pasien sebelah kanan, simulator status, modal tambah/edit klaim, dan ekspor data ke format CSV.
- **Workflow Verifikasi (`/klaim/{id}/verifikasi/{step}`)**: Stepper interaktif 6 tahap lengkap dengan pratinjau lembar fisik rekam medis dan tombol *⚡ Simulasi Kilat Lengkapi Berkas* untuk menyelesaikan hambatan tanda tangan DPJP secara langsung.
- **Portal E-Claim (`/eclaim`)**: Dashboard pengiriman batch klaim siap diajukan, grouping tarif INA-CBGs, dan rekam log status pengiriman.
- **Pelaporan (`/laporan`)**: Analisis rasio persetujuan klaim (94,2%), top diagnosa primer terbanyak, dan unduh berkas ringkasan casemix.

---

## 🗄️ Struktur Basis Data (ERD)

Aplikasi dibangun dengan **17 tabel relasional**:

| Tabel | Deskripsi |
|---|---|
| `hospitals` | Profil fasilitas kesehatan & kode PPK BPJS (RS Cakra Medika). |
| `roles` | Daftar peran (`petugas_casemix`, `verifikator_medis`, `admin_casemix`). |
| `users` | Akun pengguna beserta NIP, jabatan, dan relasi rumah sakit/peran. |
| `patients` | Data pasien, No. RM, No. Kartu BPJS/KIS, NIK, dan hak kelas rawat. |
| `claim_periods` | Periode penagihan casemix bulanan (e.g. November 2026). |
| `claims` | Episode klaim pasien (No. SEP, diagnosa, prosedur, status, tarif). |
| `claim_documents` | Arsip berkas digital rekam medis (5 dokumen wajib). |
| `claim_verifications`| Riwayat audit hasil validasi per 6 tahapan. |
| `icd10_codes` | Master referensi kamus diagnosis ICD-10. |
| `icd9cm_codes` | Master referensi kamus prosedur ICD-9-CM. |
| `claim_diagnoses` | Rincian diagnosis primer dan sekunder per klaim. |
| `claim_procedures` | Rincian tindakan medis per klaim. |
| `claim_risks` | Temuan risiko & potensi dispute klaim (e.g. temuan `ADM-01`). |
| `eclaim_syncs` | Riwayat pengiriman batch klaim ke INA-CBGs E-Claim. |
| `eclaim_sync_items` | Status individual klaim dalam setiap batch pengiriman. |
| `activity_logs` | Jejak audit (*audit trail*) aktivitas petugas. |
| `notifications` | Notifikasi alert berkas bermasalah dan approval. |

---

## 🧪 Pengujian Otomatis (Testing)

Proyek ini telah dilengkapi dengan unit dan feature test otomatis (**32 tests, 190 assertions, 100% PASS**):

```bash
# Menjalankan seluruh test suite
php artisan test

# Menjalankan pengujian spesifik alur ClaimGuard
php artisan test --filter=ClaimGuardWorkflowTest
```

---

## 🛠️ Troubleshooting

#### 1. Masalah Permission Folder Storage
Jika muncul error permission denied saat upload atau session:
```bash
chmod -R 775 storage bootstrap/cache
```

#### 2. Tampilan Frontend Tidak Muncul / "Unable to locate file in Vite manifest"
Jalankan kompilasi aset frontend:
```bash
npm run build
```
Atau jika sedang dalam tahap development, pastikan Vite berjalan:
```bash
npm run dev
```

#### 3. Reset Database ke Kondisi Awal Bawaan Demo
Jika ingin mengembalikan semua data klaim ke kondisi semula sesuai mockup:
```bash
php artisan migrate:fresh --seed
```

---

**Unit Casemix & IT Rumah Sakit · RS Cakra Medika**  
*Dikembangkan dengan Laravel, Inertia.js, React, dan Tailwind CSS.*
