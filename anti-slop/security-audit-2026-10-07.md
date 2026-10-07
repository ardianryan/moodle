# Security Audit & Hardening Report

- **Target System:** Moodle 4.5.15 LTS (SMAN 1 Gedeg LMS)
- **Scope:**
  - `local/scholargate_sync` (Identity Sync plugin & endpoints)
  - `theme/scholargate` (Theme layout, login, modal previewer, SCSS)
  - `mod/assign` (Custom inline file preview handler)
- **Audit Date:** 2026-10-07
- **Audit Methodology:** Source-first defensive vulnerability review (OWASP ASVS / Moodle Security Guidelines)

---

## 1. Threat Model & Attack Surface Summary

| Surface | Trust Boundary | Primary Risks Evaluated | Status |
|:--------|:---------------|:------------------------|:------:|
| **SSO & API Client** | Moodle $\leftrightarrow$ ScholarGate Portal | Credential exposure, SSRF, MITM | **REMEDIATED** |
| **User Profile Sync** | External Data $\leftrightarrow$ Moodle Database | PII data leak (NIK), privilege escalation, email spoofing | **REMEDIATED** |
| **AJAX Sync Endpoints** | Web Browser $\leftrightarrow$ Moodle Admin Backend | CSRF, unauthorized execution, data leakage | **SECURE** |
| **Course Cohort Enrolment** | Teacher $\leftrightarrow$ Course Database | IDOR, broken access control, CSRF | **SECURE** |
| **Inline File Viewer** | Student Submissions $\leftrightarrow$ Browser Iframe | Stored XSS, unauthorized document access, framing injection | **REMEDIATED** |

---

## 2. Findings & Applied Remediations

### Finding 1: [CRITICAL] Hardcoded API Secret Key in Code (Credential Leakage)
- **File:** `local/scholargate_sync/classes/client.php:27` & `settings.php:22`
- **Impact:** Kunci rahasia API produksi (`316dc5de...`) sebelumnya tertulis secara *hardcoded* sebagai nilai bawaan (fallback). Jika repositori diunggah ke GitHub atau dilihat pihak luar, kunci ini berisiko bocor dan dapat digunakan untuk mengakses data induk siswa/guru langsung dari portal ScholarGate.
- **Remediation:**
  - Dihapus nilai *hardcoded* dari source code.
  - Nilai default di `settings.php` dikosongkan (`''`).
  - `client.php` kini mewajibkan API Key diambil dari tabel konfigurasi aman Moodle (`mdl_config_plugins`). Jika belum diisi, class akan melempar exception `moodle_exception` dan menolak request.

---

### Finding 2: [HIGH] PII Leakage / Pelanggaran Kerahasiaan NIK (`visible = 1`)
- **File:** Database Moodle (`mdl_user_info_field`)
- **Impact:** Field profil kustom `nik` (Nomor Induk Kependudukan) sebelumnya memiliki status `visible: 1` (`PROFILE_VISIBLE_ALL`), yang menyebabkan NIK siswa dan guru dapat dilihat secara publik oleh siapa saja (termasuk sesama siswa) saat membuka halaman profil pengguna. Hal ini melanggar standar Undang-Undang Perlindungan Data Pribadi (UU PDP).
- **Remediation:**
  - Status visibilitas `nik` di database diperbarui menjadi `visible = 0` (`PROFILE_VISIBLE_NONE`).
  - NIK kini **100% tersembunyi** dari seluruh siswa dan pengguna biasa, dan hanya dapat dilihat oleh Site Administrator untuk keperluan verifikasi Dapodik.

---

### Finding 3: [MEDIUM] Missing Origin & Protocol Validation in Iframe Previewer (DOM Iframe Injection)
- **File:** `theme/scholargate/templates/pdf_modal.mustache:53`
- **Impact:** Fungsi modal penampil dokumen menerima parameter URL berkas tanpa memvalidasi kesesuaian asal (*origin*) dan protokol, berpotensi membuka celah *framing injection* atau eksekusi scheme berbahaya (`javascript:`, `data:`).
- **Remediation:**
  - Ditambahkan validasi ketat: URL wajib bertipe protokol aman (`http:` atau `https:`) dan nilai `origin` harus sama persis dengan `window.location.origin` (Same-Origin Policy).
  - Teks antarmuka modal dinormalisasi ke bahasa Inggris baku.

---

### Finding 4: [LOW] Unvalidated External Email Update
- **File:** `local/scholargate_sync/classes/sync_service.php:108`
- **Impact:** Saat melakukan sinkronisasi data akun dari SSO, alamat email langsung ditulis ke database tanpa melewati validator format email core Moodle.
- **Remediation:**
  - Ditambahkan fungsi validasi resmi Moodle `validate_email($ssoemail)` sebelum mengeksekusi pembaruan ke tabel `mdl_user`.

---

## 3. Verifikasi Arsitektur Keamanan Lainnya

1. **SQL Injection:**
   - Semua pemanggilan `$DB->get_record*` dan `$DB->count_records*` di plugin `local_scholargate_sync` 100% menggunakan parameter binding terisolasi (`['uuid' => $uuid]`, `['id' => $id]`). Tidak ditemukan satupun konkatenasi string SQL mentah.
2. **Access Control (RBAC) & CSRF:**
   - Halaman sinkronisasi `sync.php` dan seluruh handler AJAX (`ajax_*`) dilindungi ganda oleh:
     - `require_capability('moodle/site:config', $context)` (Hanya Administrator Utama).
     - `confirm_sesskey()` (Perlindungan anti-CSRF token berbasis session unik).
   - Halaman pendaftaran rombel `enrol_rombel.php` memeriksa `require_login($course)` dan `require_capability('moodle/course:enrolconfig', $context)` sebelum memproses form.
3. **Penyimpanan Password Pengguna:**
   - Akun yang disinkronkan otomatis disetel dengan metode `auth = 'oauth2'` tanpa *local password hash*, sehingga akun tidak rentan terhadap serangan *brute force* atau *credential stuffing* lokal.
4. **Keamanan File Viewer (`mod/assign`):**
   - File pluginfile di `assignsubmission_file_pluginfile` memverifikasi kepemilikan submission dan capability guru/siswa, serta hanya mengizinkan *inline disposition* untuk MIME type dokumen dan gambar aman (`application/pdf`, `image/jpeg`, `image/png`, `image/gif`, `image/webp`). File berbasis script seperti SVG atau HTML dilarang keras dibuka inline guna mencegah Stored XSS.
