<?php
namespace local_scholargate_sync;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/user/lib.php');

/**
 * ScholarGate Identity Bulk Data Sync Service.
 * Adopts the battle-tested akasv2 sync pattern for Academic Year, Teachers, Students & Mutations.
 */
class bulk_sync_service {

    /**
     * Sync Active Academic Year from ScholarGate.
     *
     * @return array
     */
    public static function sync_academic_year(): array {
        $client = new client();
        $res = $client->get_active_academic_year();

        if (empty($res['success']) || empty($res['data'])) {
            return [
                'ok' => false,
                'message' => $res['message'] ?? 'Gagal mengambil Tahun Pelajaran aktif dari ScholarGate.'
            ];
        }

        $ay = $res['data'];
        $ayid = (string)($ay['id'] ?? '');
        $ayname = (string)($ay['name'] ?? '');

        set_config('active_academic_year_id', $ayid, 'local_scholargate_sync');
        set_config('active_academic_year_name', $ayname, 'local_scholargate_sync');

        return [
            'ok' => true,
            'id' => $ayid,
            'name' => $ayname,
            'message' => "Berhasil menyinkronkan Tahun Pelajaran aktif: {$ayname}"
        ];
    }

    /**
     * Upsert a member record into Moodle user and profile.
     *
     * @param array $member
     * @return int User ID
     */
    public static function upsert_member(array $member): int {
        global $DB, $CFG;

        $uuid = trim((string)($member['id'] ?? ''));
        $nip = trim((string)($member['nip'] ?? ''));
        $nisn = trim((string)($member['nisn'] ?? ''));
        $name = trim((string)($member['nama'] ?? ''));
        $role = strtolower(trim((string)($member['role'] ?? '')));
        $email = trim((string)($member['google_email'] ?? $member['email_pribadi'] ?? ''));

        // Default username: NIP (guru) atau NISN (siswa) atau UUID
        $username = $nip ?: $nisn ?: $uuid;
        if (empty($username)) {
            return 0;
        }

        // Cek apakah user sudah ada di Moodle (by username, by email, or by UUID profile)
        $user = $DB->get_record('user', ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id, 'deleted' => 0]);
        if (!$user && !empty($email)) {
            $user = $DB->get_record('user', ['email' => $email, 'deleted' => 0]);
        }

        // Pisahkan nama depan dan nama belakang
        $nameparts = explode(' ', $name, 2);
        $firstname = $nameparts[0] ?: 'User';
        $lastname  = $nameparts[1] ?? '.';

        if ($user) {
            // Update nama jika belum sesuai
            $needupdate = false;
            if ($user->firstname !== $firstname || $user->lastname !== $lastname) {
                $user->firstname = $firstname;
                $user->lastname = $lastname;
                $needupdate = true;
            }
            if ($needupdate) {
                $DB->update_record('user', $user);
            }
            $userid = $user->id;
        } else {
            // Buat user baru di Moodle
            $newuser = (object)[
                'username'    => $username,
                'auth'        => 'oauth2',
                'confirmed'   => 1,
                'mnethostid'  => $CFG->mnet_localhost_id,
                'firstname'   => $firstname,
                'lastname'    => $lastname,
                'email'       => $email ?: ($username . '@sman1gedeg.sch.id'),
                'city'        => 'Mojokerto',
                'country'     => 'ID',
                'lang'        => 'id',
                'timecreated' => time(),
                'timemodified'=> time(),
            ];
            $userid = user_create_user($newuser, false, false);
            $user = $DB->get_record('user', ['id' => $userid]);
        }

        // Terapkan data member lengkap ke profil & role
        sync_service::apply_member_data_to_user($user, $member);

        return $userid;
    }

    /**
     * Sync one page of teachers and educational staff.
     *
     * @param string $role ('guru' or 'tendik')
     * @param int $page
     * @param int $perpage
     * @return array
     */
    public static function sync_gtk_page(string $role = 'guru', int $page = 1, int $perpage = 50): array {
        $client = new client();
        $res = $client->get_members([
            'role' => $role,
            'page' => $page,
            'per_page' => $perpage,
        ]);

        $members = $res['data'] ?? [];
        $meta = $res['meta'] ?? [];
        $synced = 0;

        foreach ($members as $m) {
            if (self::upsert_member($m)) {
                $synced++;
            }
        }

        return [
            'ok' => true,
            'role' => $role,
            'page' => $page,
            'synced' => $synced,
            'total_pages' => (int)($meta['total_pages'] ?? 1),
            'total_items' => (int)($meta['total'] ?? count($members)),
        ];
    }

    /**
     * Sync one page of students.
     * Strictly filters for active Academic Year and role=siswa.
     *
     * @param int $page
     * @param int $perpage
     * @return array
     */
    public static function sync_siswa_page(int $page = 1, int $perpage = 50): array {
        $client = new client();
        $active_ay_id = get_config('local_scholargate_sync', 'active_academic_year_id');

        $params = [
            'role' => 'siswa',
            'page' => $page,
            'per_page' => $perpage,
        ];
        if (!empty($active_ay_id)) {
            $params['academic_year_id'] = $active_ay_id;
        }

        $res = $client->get_members($params);

        $members = $res['data'] ?? [];
        $meta = $res['meta'] ?? [];
        $synced = 0;
        $latestname = '';
        $latestclass = '';

        foreach ($members as $m) {
            $role = strtolower(trim((string)($m['role'] ?? '')));
            if ($role !== 'siswa') {
                continue;
            }

            if (self::upsert_member($m)) {
                $synced++;
                $latestname = (string)($m['nama'] ?? '');
                $latestclass = (string)($m['kelas'] ?? '');
            }
        }

        return [
            'ok' => true,
            'page' => $page,
            'synced' => $synced,
            'latest_name' => $latestname,
            'latest_class' => $latestclass,
            'total_pages' => (int)($meta['total_pages'] ?? 1),
            'total_items' => (int)($meta['total'] ?? count($members)),
        ];
    }

    /**
     * Sync and handle Mutated / Departed students and Alumni.
     * Mutated students are suspended and removed from class cohorts.
     * Alumni are unassigned from active class cohorts and enrolled in cohort 'Alumni'.
     *
     * @return array
     */
    public static function sync_mutasi_and_alumni(): array {
        global $DB;
        $client = new client();

        $mutasi_count = 0;
        $alumni_count = 0;

        // 1. Tangani Siswa Keluar / Mutasi
        $res_keluar = $client->get_members(['role' => 'keluar', 'per_page' => 100]);
        $keluar_members = $res_keluar['data'] ?? [];

        foreach ($keluar_members as $m) {
            $nisn = trim((string)($m['nisn'] ?? ''));
            $uuid = trim((string)($m['id'] ?? ''));
            $email = trim((string)($m['google_email'] ?? $m['email_pribadi'] ?? ''));

            $user = null;
            if ($nisn) {
                $user = $DB->get_record('user', ['username' => $nisn, 'deleted' => 0]);
            }
            if (!$user && $uuid) {
                $user = $DB->get_record_sql("
                    SELECT u.*
                    FROM {user} u
                    JOIN {user_info_data} d ON d.userid = u.id
                    JOIN {user_info_field} f ON f.id = d.fieldid
                    WHERE f.shortname = 'uuid' AND d.data = :uuid AND u.deleted = 0
                ", ['uuid' => $uuid]);
            }
            if (!$user && $email) {
                $user = $DB->get_record('user', ['email' => $email, 'deleted' => 0]);
            }

            if ($user) {
                sync_service::suspend_mutated_user($user->id);
                sync_service::save_custom_profile_fields($user->id, [
                    'role_sekolah' => 'keluar',
                    'kelas' => 'KELUAR'
                ]);
                $mutasi_count++;
            }
        }

        // 2. Tangani Siswa Alumni
        $p = 1;
        do {
            $res_alumni = $client->get_members(['role' => 'alumni', 'page' => $p, 'per_page' => 100]);
            $alumni_members = $res_alumni['data'] ?? [];
            $totalpages = (int)($res_alumni['meta']['total_pages'] ?? 1);

            foreach ($alumni_members as $m) {
                $nisn = trim((string)($m['nisn'] ?? ''));
                $uuid = trim((string)($m['id'] ?? ''));
                $email = trim((string)($m['google_email'] ?? $m['email_pribadi'] ?? ''));

                $user = null;
                if ($nisn) {
                    $user = $DB->get_record('user', ['username' => $nisn, 'deleted' => 0]);
                }
                if (!$user && $uuid) {
                    $user = $DB->get_record_sql("
                        SELECT u.*
                        FROM {user} u
                        JOIN {user_info_data} d ON d.userid = u.id
                        JOIN {user_info_field} f ON f.id = d.fieldid
                        WHERE f.shortname = 'uuid' AND d.data = :uuid AND u.deleted = 0
                    ", ['uuid' => $uuid]);
                }
                if (!$user && $email) {
                    $user = $DB->get_record('user', ['email' => $email, 'deleted' => 0]);
                }

                if ($user) {
                    // Bersihkan dari cohort rombel kelas aktif
                    $memberships = $DB->get_records_sql("
                        SELECT cm.id, cm.cohortid
                        FROM {cohort_members} cm
                        JOIN {cohort} c ON c.id = cm.cohortid
                        WHERE cm.userid = :userid AND c.idnumber LIKE 'kelas_%'
                    ", ['userid' => $user->id]);
                    foreach ($memberships as $ms) {
                        cohort_remove_member($ms->cohortid, $user->id);
                    }
                    sync_service::assign_alumni_cohort($user->id);
                    sync_service::save_custom_profile_fields($user->id, [
                        'role_sekolah' => 'alumni',
                        'kelas' => 'ALUMNI'
                    ]);
                    $alumni_count++;
                }
            }
            $p++;
        } while ($p <= $totalpages);

        return [
            'ok' => true,
            'mutasi_count' => $mutasi_count,
            'alumni_count' => $alumni_count,
            'message' => "Berhasil memproses {$mutasi_count} siswa mutasi/keluar (akun dinonaktifkan) dan {$alumni_count} alumni."
        ];
    }

    /**
     * Full batch sync for all data (Academic Year, Teachers, Tendik, Students, Mutasi & Alumni).
     *
     * @return array
     */
    public static function sync_all(): array {
        $stats = [
            'academic_year' => self::sync_academic_year(),
            'guru' => 0,
            'tendik' => 0,
            'siswa' => 0,
            'mutasi_alumni' => null,
        ];

        // 1. Sync Guru
        $page = 1;
        do {
            $res = self::sync_gtk_page('guru', $page, 50);
            $stats['guru'] += $res['synced'];
            $totalpages = $res['total_pages'];
            $page++;
        } while ($page <= $totalpages);

        // 2. Sync Tendik
        $page = 1;
        do {
            $res = self::sync_gtk_page('tendik', $page, 50);
            $stats['tendik'] += $res['synced'];
            $totalpages = $res['total_pages'];
            $page++;
        } while ($page <= $totalpages);

        // 3. Sync Siswa
        $page = 1;
        do {
            $res = self::sync_siswa_page($page, 50);
            $stats['siswa'] += $res['synced'];
            $totalpages = $res['total_pages'];
            $page++;
        } while ($page <= $totalpages);

        // 4. Sync Mutasi & Alumni
        $stats['mutasi_alumni'] = self::sync_mutasi_and_alumni();

        set_config('last_bulk_sync_time', time(), 'local_scholargate_sync');

        return [
            'ok' => true,
            'stats' => $stats,
            'message' => "Sinkronisasi selesai! Guru: {$stats['guru']}, Tendik: {$stats['tendik']}, Siswa: {$stats['siswa']}."
        ];
    }
}

