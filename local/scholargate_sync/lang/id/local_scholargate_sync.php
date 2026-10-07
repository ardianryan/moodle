<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Sinkronisasi ScholarGate Identity';
$string['settings_header'] = 'Pengaturan ScholarGate Identity API';
$string['settings_header_desc'] = 'Konfigurasi integrasi master data akademik (Guru, Siswa, Rombel, Tahun Pelajaran) dari ScholarGate Identity.';

$string['api_url'] = 'Base URL ScholarGate API';
$string['api_url_desc'] = 'Alamat URL dasar portal ScholarGate Identity (contoh: https://portal.sman1gedeg.sch.id).';

$string['api_key'] = 'Kunci API (X-API-Key)';
$string['api_key_desc'] = 'Kunci rahasia API Key yang terdaftar pada Portal Apps ScholarGate.';

$string['register_url'] = 'URL Pendaftaran / Bantuan';
$string['register_url_desc'] = 'Alamat tujuan pengalihan jika pengguna login SSO tetapi datanya belum terdaftar di ScholarGate (Aturan 3).';

$string['auto_sync_login'] = 'Sinkronisasi Otomatis Saat Login';
$string['auto_sync_login_desc'] = 'Secara otomatis mencocokkan profil, hak akses peran, dan cohort saat pengguna login ke LMS.';

$string['sync_cohort'] = 'Sinkronkan Rombel ke Cohort';
$string['sync_cohort_desc'] = 'Otomatis membuat cohort kelas (contoh: Kelas X-1) dan mendaftarkan siswa ke dalamnya.';

$string['handle_mutasi'] = 'Tangani Siswa Mutasi & Alumni';
$string['handle_mutasi_desc'] = 'Otomatis menonaktifkan (suspend) akun siswa yang berstatus keluar/mutasi dan memindahkan alumni ke cohort Alumni.';

$string['tendik_as_guru'] = 'Tetapkan Tendik sebagai Pembuat Kursus';
$string['tendik_as_guru_desc'] = 'Berikan hak akses Course Creator kepada Tenaga Kependidikan (Tendik) selain Guru.';

$string['sync_dashboard'] = 'Dashboard Sinkronisasi ScholarGate';
$string['dashboard_title'] = 'Sinkronisasi Data Master ScholarGate Identity';
$string['dashboard_desc'] = 'Kelola penarikan data massal Tahun Pelajaran, Guru & Tendik, Siswa, dan Rombel dari direktori ScholarGate.';

$string['user_not_registered'] = 'Akun Anda belum terdaftar di direktori ScholarGate sekolah. Silakan hubungi operator atau lengkapi pendaftaran di {$a}.';
$string['account_suspended_mutasi'] = 'Akun Anda telah dinonaktifkan karena tercatat mutasi / keluar dari sekolah.';

$string['rombel_enrol_header'] = 'Daftarkan Rombel Kelas Siswa (ScholarGate)';
$string['choose_rombel'] = 'Pilih Rombel / Kelas';
$string['choose_rombel_help'] = 'Pilih satu atau beberapa rombel kelas untuk mendaftarkan seluruh siswanya ke kursus ini.';
$string['create_groups'] = 'Buat Grup Kelas Otomatis';
$string['create_groups_desc'] = 'Kelompokkan siswa ke dalam grup sesuai rombel kelasnya (memudahkan pemfilteran nilai & tugas per kelas).';
$string['enrol_rombel_nav'] = 'Daftarkan Rombel Siswa';
$string['enrol_rombel_title'] = 'Daftarkan Rombel Kelas';
$string['enrol_rombel_success'] = 'Berhasil mendaftarkan {$a->count} Rombel Kelas ke dalam kursus (dengan {$a->groups} grup baru dibuat).';
$string['no_rombel_selected'] = 'Silakan centang minimal satu rombel kelas untuk didaftarkan.';

