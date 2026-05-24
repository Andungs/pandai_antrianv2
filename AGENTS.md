# 🤖 Pandai Antrian - AI Agent Technical Guidelines (AGENTS.md)

Dokumen ini berfungsi sebagai "otak" dan sumber kebenaran teknis (Source of Truth) bagi semua AI Assistant (Gemini, Claude, Cursor, dll) yang berinteraksi dengan repositori ini. Sebelum memodifikasi, menambah fitur, atau melakukan debugging, AI wajib memahami arsitektur, flow sistem, dan aturan ketat yang tertera di bawah ini.

---

## 🏗️ 1. Arsitektur Sistem & Tech Stack

Aplikasi `Pandai Antrian` menggunakan arsitektur terpisah (Headless API) antara Backend dan Frontend, dengan komunikasi real-time menggunakan WebSockets.

### Backend (API Server)
- **Framework:** Laravel 11
- **Database:** SQLite (lokasi: `database/database.sqlite`)
- **WebSockets:** Laravel Reverb (Berjalan di port `8099`)
- **Port API:** Berjalan di port `8092`
- **Authentication:** Laravel Sanctum (Token-based)
- **Production Server:** Laravel Octane + Swoole (via `Dockerfile.prod`)

### Frontend (SPA Client)
- **Framework:** Vue 3 (Composition API, `<script setup>`) + Vite
- **Styling:** Tailwind CSS + custom CSS variables
- **UI Components:** shadcn-vue (Radix Vue), lucide-vue-next, `@vueform/multiselect`
- **Pusher Client:** `pusher-js` & `laravel-echo` untuk menangkap event WebSocket
- **Thermal Printing:** `qz-tray` npm package + composable `useQZTray.ts`

### Docker Environment (Production)
- **pandaiantrian-api:** Container utama Laravel backend (Octane/Swoole, port 8000 internal).
- **pandaiantrian-reverb:** Container khusus untuk worker WebSocket Reverb (port 8080 internal → 8099 host).
- **pandaiantrian-queue:** Container worker untuk job queue (`queue:work database`).
- **Volume Strategy (PENTING):** Di production, JANGAN mount seluruh direktori proyek (`.:/var/www`). Hanya mount data persisten: `storage/app`, `database/`, dan `.env`. Mount seluruh direktori akan menimpa `vendor/` yang sudah di-build di dalam image Docker, menyebabkan error dependensi.
- Frontend di-*serve* menggunakan *local development server* (Vite) atau Nginx (Production).

---

## 🔄 2. Flow Sistem Utama

### A. Pengambilan Tiket (KIOSK)
1. User Guest berada di KIOSK (layar KIOSK).
2. Memilih **Layanan** (Service).
3. `POST /api/guest/queues` dipanggil. Backend me-return Nomor Antrean.
4. Backend memancarkan (broadcast) event `QueueUpdated`.
5. Frontend memutar animasi pop-up dan mencetak tiket via **QZTray (Thermal Printer)** menggunakan ESC/POS raw commands.

### C. Pencetakan Tiket Thermal (QZTray)
1. Sertifikat digital di-generate dari menu **Pengaturan** (`AdminSettingController::generateQzTrayCertificate`).
2. Sertifikat (public cert + private key) disimpan di tabel `settings` (key: `qztray_certificate`, `qztray_private_key`).
3. Frontend KIOSK memanggil `GET /api/guest/qz-certs` untuk certificate dan `POST /api/guest/qz-sign` untuk signing.
4. Composable `useQZTray.ts` mengirim perintah ESC/POS raw ke printer thermal via QZTray WebSocket lokal.
5. Nama printer dikonfigurasi di tabel `settings` (key: `printer_name`).

### B. Pemanggilan Antrean (LOKET)
1. Petugas Loket / Superadmin login ke `/dashboard`.
2. **Pemilihan Loket (Wajib):** Petugas loket *wajib* memilih loket yang mereka jaga hari ini dari daftar `counter_user` (Kecuali jika mereka hanya di-assign ke 1 loket, maka akan auto-select).
3. **Panggil Berikutnya (`/api/loket/queues/next`):** 
   - Backend mencari antrean `menunggu` terlama yang ID layanannya cocok dengan *services* yang di-assign ke loket tersebut (Many-to-Many).
   - Status antrean berubah menjadi `dipanggil`.
   - Backend memancarkan `QueueCalled` dan `QueueUpdated`.
4. **Display TV (`/display`):**
   - Menangkap `QueueCalled`.
   - **(CRITICAL):** Memutar suara panggilan menggunakan browser `SpeechSynthesis` API secara lokal. Animasi *pulsing glow* aktif di kartu loket bersangkutan.

---

## 🔗 3. Skema Relasi Database Penting

Aplikasi telah di-refactor untuk mendukung **Multi-Layanan** dan **Multi-Petugas** per Loket.
- `users`: Data pengguna (Role: `superadmin` dan `loket`).
- `services`: Master data layanan (contoh: Poli Umum, CS, Teller).
- `counters`: Master data loket/meja.
- `counter_service` (Pivot): Mengizinkan 1 loket melayani > 1 layanan.
- `counter_user` (Pivot): Mengizinkan 1 loket dijaga oleh > 1 petugas (untuk shifting).
- `queues`: Data antrean harian. Berelasi ke `service_id` (wajib) dan `counter_id` (diisi saat dipanggil).

---

## 🛑 4. Aturan Ketat (DO NOT CHANGE)

Bagian ini berisi konfigurasi teknis dan desain yang **TIDAK BOLEH** diubah oleh AI Agent tanpa instruksi eksplisit dari `USER`.

### 🚨 1. Broadcasting (WebSockets)
- Event `QueueCalled.php` dan `QueueUpdated.php` **WAJIB** mengimplementasikan `ShouldBroadcastNow` (Synchronous).
  - **Alasan:** Browser memiliki aturan *Auto-play Policy* yang ketat. Jika kita menggunakan queue/asynchronous broadcasting (`ShouldBroadcast`), *delay latency* akan membuat *user gesture* kedaluwarsa, yang mengakibatkan suara panggilan (Text-to-Speech) tidak mau berbunyi di halaman Display TV.

### 🚨 2. Desain & Estetika (UI/UX)
- **Tema KIOSK & Display TV WAJIB `Light Theme` (Terang).** Jangan menggunakan warna latar belakang gelap atau *dark mode* pada halaman `DisplayView.vue` atau `KioskView.vue`.
- **Gunakan Desain Premium:** DILARANG keras menggunakan warna dasar biasa (misal `bg-red-500`, `bg-blue-500`). Selalu gunakan warna gradiasi premium yang estetik (misal `bg-gradient-to-br from-sky-500 to-blue-600`), efek *glassmorphism*, `backdrop-blur`, mikro-animasi, shadow lembut, dan *pulse glow* untuk menarik perhatian user.

### 🚨 3. Konfigurasi Port
- Port API = `8092`
- Port Reverb (WebSocket) = `8099`
- Jangan ubah port ini di *environment variables* kecuali `docker-compose.yml` sedang ditulis ulang dan disetujui.

---

## 🛠️ 5. Pedoman Pengembangan (Development Guidelines)

1. **Frontend Styling:** 
   - Gunakan `Tailwind CSS`. Jika membangun komponen yang kompleks, prioritaskan mengambil referensi dari `shadcn-vue` daripada membuat *custom CSS* dari nol.
   - Pengecualian: Gunakan tag `<style>` lokal jika meng-*override* tema library eksternal (contoh: `@vueform/multiselect`).

2. **Backend API:**
   - Gunakan pendekatan *Thin Controllers, Fat Models/Services* untuk logika antrean yang kompleks.
   - Selalu kembalikan standar *response format* JSON: `['data' => [...], 'message' => '...']`.

3. **User Management:**
   - Gunakan sistem otentikasi *token-based* Sanctum untuk semua route dengan proteksi middleware `auth:sanctum`.
   - Gunakan method spoofing (`_method=PUT`) pada frontend ketika mengirim `FormData` (seperti upload foto profil) untuk update data via Axios.

---

## 🤖 6. AI Self-Update Policy (PENTING)
- **WAJIB:** Setiap kali AI melakukan modifikasi besar yang melibatkan:
  1. Penambahan/perubahan skema database (`migrations`, `models`, `relations`).
  2. Pembuatan fitur teknis baru yang mengubah alur (flow) sistem.
  3. Penambahan file konfigurasi inti atau *tools* pihak ketiga.
  **AI harus secara otomatis memperbarui file `AGENTS.md` ini** agar dokumentasi selalu sinkron dengan kondisi *codebase* terbaru tanpa perlu disuruh secara eksplisit oleh `USER`.

---
*Dokumen ini harus selalu diperbarui jika ada pergeseran arsitektur yang signifikan.*
