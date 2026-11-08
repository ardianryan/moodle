<?php
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognized) = cli_get_params([
    'help' => false,
], [
    'h' => 'help',
]);

if ($options['help']) {
    echo "ScholarGate Identity - Bulk Sync CLI (Tahun Pelajaran, GTK, Siswa, Rombel)\n\n";
    echo "Usage:\n";
    echo "  php sync_all.php\n\n";
    exit(0);
}

echo "=== Memulai Sinkronisasi Massal ScholarGate Identity ===\n";
$res = \local_scholargate_sync\bulk_sync_service::sync_all();

echo $res['message'] . "\n";
echo "Detail:\n";
echo "  - Tahun Pelajaran: " . ($res['stats']['academic_year']['name'] ?? 'N/A') . "\n";
echo "  - Total Guru: " . $res['stats']['guru'] . "\n";
echo "  - Total Tendik: " . $res['stats']['tendik'] . "\n";
echo "  - Total Siswa Aktif: " . $res['stats']['siswa'] . "\n";
if (!empty($res['stats']['mutasi_alumni'])) {
    echo "  - Siswa Mutasi/Keluar (Disuspend): " . ($res['stats']['mutasi_alumni']['mutasi_count'] ?? 0) . "\n";
    echo "  - Alumni Dialihkan: " . ($res['stats']['mutasi_alumni']['alumni_count'] ?? 0) . "\n";
}

echo "Menautkan seluruh akun pengguna ke OAuth2 issuer...\n";
$linkres = \local_scholargate_sync\bulk_sync_service::sync_all_existing_linked_logins();
echo "  - " . ($linkres['message'] ?? 'Selesai menautkan.') . "\n";

echo "=== Selesai ===\n";
