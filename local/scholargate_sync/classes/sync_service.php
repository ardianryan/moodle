<?php
namespace local_scholargate_sync;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/cohort/lib.php');
require_once($CFG->dirroot . '/user/profile/lib.php');

/**
 * ScholarGate Identity Real-Time Sync Service.
 * Implements 3 Rules for SSO Authentication & Data Matching.
 */
class sync_service {

    /**
     * Handle user login synchronization.
     *
     * @param \stdClass $user
     * @return bool
     */
    public static function sync_on_login(\stdClass $user): bool {
        global $DB, $CFG;

        if (empty($user->id) || isguestuser($user->id)) {
            return true;
        }

        // Only run if auto sync on login is enabled.
        $enabled = get_config('local_scholargate_sync', 'auto_sync_login');
        if ($enabled === '0') {
            return true;
        }

        // Load custom profile fields from local Moodle.
        profile_load_custom_fields($user);
        $localprofile = $user->profile ?? [];

        $localuuid = trim($localprofile['uuid'] ?? '');
        $localrole = strtolower(trim($localprofile['role_sekolah'] ?? ''));

        // Rule 1: Check local cache first. If UUID and school role exist, proceed.
        if (!empty($localuuid) && !empty($localrole)) {
            // Pastikan role Course Creator tetap aktif jika guru
            if ($localrole === 'guru' || $localrole === 'tendik') {
                self::ensure_course_creator_role($user->id);
            }
            return true;
        }

        // Rule 2: If profile incomplete, query ScholarGate Identity API.
        $client = new client();

        // Siapkan kriteria pencarian (prioritas: UUID -> Username/NIP -> Email)
        $criteria = [];
        if (!empty($localuuid)) {
            $criteria['uuid'] = $localuuid;
        } elseif (!empty($user->username) && !in_array($user->username, ['admin', 'guest'])) {
            $criteria['username'] = $user->username;
        } elseif (!empty($user->email)) {
            $criteria['email'] = $user->email;
        }

        if (empty($criteria)) {
            return true;
        }

        try {
            $response = $client->lookup_member($criteria);
        } catch (\Exception $e) {
            // Jika terjadi kesalahan jaringan ke API, jangan blokir login darurat
            debugging('ScholarGate API lookup failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return true;
        }

        // Jika data ditemukan di ScholarGate Identity
        if (!empty($response['success']) && !empty($response['found']) && !empty($response['data'])) {
            $data = $response['data'];
            self::apply_member_data_to_user($user, $data);
            return true;
        }

        // Rule 3: Reject unregistered user if not found locally or in ScholarGate directory.
        if (is_siteadmin($user->id)) {
            return true;
        }

        $registerurl = get_config('local_scholargate_sync', 'register_url') ?: 'https://auth.sman1gedeg.sch.id/help';
        $message = get_string('user_not_registered', 'local_scholargate_sync', $registerurl);

        // Batalkan login pengguna
        require_logout();

        \core\notification::error($message);
        redirect($registerurl, $message, 5);
        return false;
    }

    /**
     * Apply ScholarGate member data to Moodle user.
     *
     * @param \stdClass $user
     * @param array $data
     */
    public static function apply_member_data_to_user(\stdClass $user, array $data): void {
        global $DB;

        // 1. Tangani Penyesuaian Email (Email di SSO Pusat Berubah)
        $ssoemail = trim($data['google_email'] ?? $data['email_pribadi'] ?? '');
        if (!empty($ssoemail) && strtolower($user->email) !== strtolower($ssoemail)) {
            // Cek apakah email baru belum dipakai user Moodle lain
            $exists = $DB->record_exists_select('user', 'email = :email AND id != :id AND deleted = 0', [
                'email' => $ssoemail,
                'id' => $user->id
            ]);
            if (!$exists) {
                $DB->set_field('user', 'email', $ssoemail, ['id' => $user->id]);
                $user->email = $ssoemail;
            }
        }

        // 2. Simpan Data Profil Dapodik & Sekolah ke Custom Profile Fields
        $role = strtolower(trim($data['role'] ?? ''));
        $fieldsdata = [
            'uuid'              => (string)($data['id'] ?? ''),
            'dapodik_id'        => (string)($data['dapodik_id'] ?? $data['ptk_id'] ?? $data['peserta_didik_id'] ?? ''),
            'role_sekolah'      => $role,
            'nik'               => (string)($data['nik'] ?? ''),
            'nip'               => (string)($data['nip'] ?? ''),
            'nisn'              => (string)($data['nisn'] ?? ''),
            'kelas'             => (string)($data['kelas'] ?? ''),
            'academic_year_id'  => (string)($data['academic_year_id'] ?? ''),
        ];

        self::save_custom_profile_fields($user->id, $fieldsdata);

        // 3. Hak Akses & Role Assignment
        $tendikasguru = get_config('local_scholargate_sync', 'tendik_as_guru') !== '0';
        $isguru = ($role === 'guru') || ($role === 'tendik' && $tendikasguru);

        if ($isguru) {
            // Aktifkan user dan berikan role Course Creator di System Context
            if (!empty($user->suspended)) {
                $DB->set_field('user', 'suspended', 0, ['id' => $user->id]);
            }
            self::ensure_course_creator_role($user->id);
        } elseif ($role === 'siswa') {
            // Siswa Aktif: Masukkan ke Cohort Rombel Kelas
            if (!empty($user->suspended)) {
                $DB->set_field('user', 'suspended', 0, ['id' => $user->id]);
            }
            if (get_config('local_scholargate_sync', 'sync_cohort') !== '0') {
                self::sync_student_cohort($user->id, $fieldsdata['kelas']);
            }
        } elseif ($role === 'alumni') {
            // Siswa Alumni: Pindahkan ke Cohort Alumni
            self::assign_alumni_cohort($user->id);
        } elseif ($role === 'keluar') {
            // Siswa Keluar / Mutasi: Suspend Akun
            if (get_config('local_scholargate_sync', 'handle_mutasi') !== '0') {
                self::suspend_mutated_user($user->id);
            }
        }
    }

    /**
     * Ensure Course Creator role (ID 2) in System Context.
     *
     * @param int $userid
     */
    public static function ensure_course_creator_role(int $userid): void {
        global $DB;
        $syscontext = \context_system::instance();

        $hasrole = $DB->record_exists('role_assignments', [
            'roleid' => 2, // Course creator
            'contextid' => $syscontext->id,
            'userid' => $userid
        ]);

        if (!$hasrole) {
            role_assign(2, $userid, $syscontext->id);
        }
    }

    /**
     * Save custom profile fields directly into user_info_data.
     *
     * @param int $userid
     * @param array $fieldvalues [shortname => value]
     */
    public static function save_custom_profile_fields(int $userid, array $fieldvalues): void {
        global $DB;

        // Ambil semua field yang ada di user_info_field
        $fields = $DB->get_records('user_info_field');
        $fieldmap = [];
        foreach ($fields as $f) {
            $fieldmap[$f->shortname] = $f->id;
        }

        foreach ($fieldvalues as $shortname => $val) {
            if (!isset($fieldmap[$shortname])) {
                continue;
            }
            $fieldid = $fieldmap[$shortname];
            $existing = $DB->get_record('user_info_data', ['userid' => $userid, 'fieldid' => $fieldid]);

            if ($existing) {
                $existing->data = $val;
                $DB->update_record('user_info_data', $existing);
            } else {
                $newrecord = (object)[
                    'userid' => $userid,
                    'fieldid' => $fieldid,
                    'data' => $val,
                    'dataformat' => 0
                ];
                $DB->insert_record('user_info_data', $newrecord);
            }
        }
    }

    /**
     * Synchronize student to class cohort (e.g., Kelas X-1).
     *
     * @param int $userid
     * @param string $classname
     */
    public static function sync_student_cohort(int $userid, string $classname): void {
        global $DB;

        $classname = trim($classname);
        if (empty($classname) || in_array(strtoupper($classname), ['ALUMNI', 'KELUAR'])) {
            return;
        }

        $syscontext = \context_system::instance();
        $cohortname = 'Kelas ' . $classname;
        $cohortidnumber = 'kelas_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($classname));

        $cohort = $DB->get_record('cohort', ['idnumber' => $cohortidnumber]);
        if (!$cohort) {
            $cohort = $DB->get_record('cohort', ['name' => $cohortname, 'contextid' => $syscontext->id]);
        }

        if (!$cohort) {
            $newcohort = (object)[
                'contextid' => $syscontext->id,
                'name' => $cohortname,
                'idnumber' => $cohortidnumber,
                'description' => 'Cohort otomatis rombel ' . $cohortname,
                'descriptionformat' => FORMAT_HTML,
                'visible' => 1,
                'component' => 'local_scholargate_sync'
            ];
            $cohortid = cohort_add_cohort($newcohort);
        } else {
            $cohortid = $cohort->id;
        }

        // Hapus dari cohort kelas lama jika siswa pindah rombel/naik kelas
        $oldmemberships = $DB->get_records_sql("
            SELECT cm.id, cm.cohortid
            FROM {cohort_members} cm
            JOIN {cohort} c ON c.id = cm.cohortid
            WHERE cm.userid = :userid AND c.idnumber LIKE 'kelas_%' AND c.id != :newcohortid
        ", ['userid' => $userid, 'newcohortid' => $cohortid]);
        foreach ($oldmemberships as $om) {
            cohort_remove_member($om->cohortid, $userid);
        }

        if (!cohort_is_member($cohortid, $userid)) {
            cohort_add_member($cohortid, $userid);
        }
    }

    /**
     * Assign student to Alumni cohort and remove from active class cohorts.
     *
     * @param int $userid
     */
    public static function assign_alumni_cohort(int $userid): void {
        global $DB;
        $syscontext = \context_system::instance();

        $cohort = $DB->get_record('cohort', ['idnumber' => 'alumni']);
        if (!$cohort) {
            $newcohort = (object)[
                'contextid' => $syscontext->id,
                'name' => 'Alumni',
                'idnumber' => 'alumni',
                'description' => 'Cohort siswa alumni / lulusan',
                'descriptionformat' => FORMAT_HTML,
                'visible' => 1,
                'component' => 'local_scholargate_sync'
            ];
            $cohortid = cohort_add_cohort($newcohort);
        } else {
            $cohortid = $cohort->id;
        }

        if (!cohort_is_member($cohortid, $userid)) {
            cohort_add_member($cohortid, $userid);
        }
    }

    /**
     * Suspend mutated / departed user.
     *
     * @param int $userid
     */
    public static function suspend_mutated_user(int $userid): void {
        global $DB;
        $DB->set_field('user', 'suspended', 1, ['id' => $userid]);

        // Hapus dari semua cohort siswa
        $memberships = $DB->get_records('cohort_members', ['userid' => $userid]);
        foreach ($memberships as $m) {
            cohort_remove_member($m->cohortid, $userid);
        }
    }
}
