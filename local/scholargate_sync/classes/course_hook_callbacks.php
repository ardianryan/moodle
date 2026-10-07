<?php
namespace local_scholargate_sync;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/cohort/lib.php');
require_once($CFG->dirroot . '/group/lib.php');

/**
 * Course Form Extension & Enrolment Hooks.
 * Allows instant student rombel / cohort selection during and after course creation.
 */
class course_hook_callbacks {

    /**
     * Extend course edit form with ScholarGate Rombel selection.
     *
     * @param \core_course\hook\after_form_definition $hook
     */
    public static function extend_course_form(\core_course\hook\after_form_definition $hook): void {
        global $DB, $CFG;

        $mform = $hook->mform;
        $course = $hook->formwrapper->get_course();
        $courseid = !empty($course->id) ? (int)$course->id : 0;

        // Ambil semua cohort rombel kelas dari database Moodle
        $cohorts = $DB->get_records_select('cohort', "idnumber LIKE 'kelas_%'", null, 'name ASC');
        if (empty($cohorts)) {
            return;
        }

        // Hitung jumlah siswa di setiap cohort
        $counts = $DB->get_records_sql("
            SELECT cohortid, COUNT(id) as studentcount
            FROM {cohort_members}
            GROUP BY cohortid
        ");

        $options = [];
        foreach ($cohorts as $c) {
            $count = isset($counts[$c->id]) ? (int)$counts[$c->id]->studentcount : 0;
            $options[$c->id] = "{$c->name} ({$count} students)";
        }

        // Add Section Header to Form
        $mform->addElement('header', 'scholargate_rombel_hdr', get_string('rombel_enrol_header', 'local_scholargate_sync'));
        $mform->setExpanded('scholargate_rombel_hdr', true);

        $mform->addElement('static', 'scholargate_rombel_info', '',
            \html_writer::div(
                '<i class="fa fa-info-circle mr-1 text-primary"></i> ' .
                '<strong>Select class cohorts</strong> below to automatically enrol all active students into this course. ' .
                'Separate <strong>Course Groups</strong> can also be generated automatically.',
                'alert alert-info py-2 px-3 mb-2 small'
            )
        );

        $mform->addElement('autocomplete', 'scholargate_cohorts', get_string('choose_rombel', 'local_scholargate_sync'), $options, [
            'multiple' => true,
            'placeholder' => 'Select class cohorts (e.g., Kelas X 1, Kelas XI 2)...',
            'showsuggestions' => true,
        ]);
        $mform->setType('scholargate_cohorts', PARAM_INT);

        $mform->addElement('advcheckbox', 'scholargate_create_groups', get_string('create_groups', 'local_scholargate_sync'), get_string('create_groups_desc', 'local_scholargate_sync'));
        $mform->setDefault('scholargate_create_groups', 1);

        // Jika mode edit kursus yang sudah ada, tandai cohort yang sudah terdaftar
        if ($courseid > 0) {
            $existing = $DB->get_records('enrol', ['courseid' => $courseid, 'enrol' => 'cohort']);
            $currentcohorts = [];
            foreach ($existing as $inst) {
                if (!empty($inst->customint1)) {
                    $currentcohorts[] = (int)$inst->customint1;
                }
            }
            $mform->setDefault('scholargate_cohorts', $currentcohorts);
        }
    }

    /**
     * Process course form submission and enrol selected cohorts.
     *
     * @param \core_course\hook\after_form_submission $hook
     */
    public static function save_course_form(\core_course\hook\after_form_submission $hook): void {
        $data = $hook->get_data();
        $courseid = !empty($data->id) ? (int)$data->id : 0;
        if (!$courseid) {
            return;
        }

        $cohortids = !empty($data->scholargate_cohorts) ? (array)$data->scholargate_cohorts : [];
        $creategroups = !empty($data->scholargate_create_groups);

        self::enrol_cohorts_to_course($courseid, $cohortids, $creategroups);
    }

    /**
     * Enrol specified cohorts into a course and optionally create groups.
     *
     * @param int $courseid
     * @param array $cohortids
     * @param bool $creategroups
     * @return array ['enrolled' => int, 'groups_created' => int]
     */
    public static function enrol_cohorts_to_course(int $courseid, array $cohortids, bool $creategroups = true): array {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/enrol/cohort/lib.php');
        require_once($CFG->dirroot . '/enrol/cohort/locallib.php');
        require_once($CFG->dirroot . '/group/lib.php');

        $course = $DB->get_record('course', ['id' => $courseid]);
        if (!$course) {
            return ['enrolled' => 0, 'groups_created' => 0];
        }

        $enrolplugin = enrol_get_plugin('cohort');
        if (!$enrolplugin) {
            return ['enrolled' => 0, 'groups_created' => 0];
        }

        $enrolledcount = 0;
        $groupscreated = 0;

        foreach ($cohortids as $cid) {
            $cid = (int)$cid;
            if (!$cid) {
                continue;
            }

            $cohort = $DB->get_record('cohort', ['id' => $cid]);
            if (!$cohort) {
                continue;
            }

            // Cek apakah cohort sudah terdaftar di kursus ini
            $exists = $DB->get_record('enrol', [
                'courseid' => $courseid,
                'enrol' => 'cohort',
                'customint1' => $cid
            ]);

            if (!$exists) {
                $groupid = 0;
                if ($creategroups) {
                    $group = $DB->get_record('groups', ['courseid' => $courseid, 'name' => $cohort->name]);
                    if (!$group) {
                        $newgroup = (object)[
                            'courseid' => $courseid,
                            'name' => $cohort->name,
                            'idnumber' => $cohort->idnumber,
                            'description' => 'Grup rombel otomatis ' . $cohort->name,
                            'descriptionformat' => FORMAT_HTML,
                        ];
                        $groupid = groups_create_group($newgroup);
                        $groupscreated++;
                    } else {
                        $groupid = $group->id;
                    }
                }

                // Tambahkan instance enrol_cohort ke kursus
                $fields = [
                    'customint1' => $cid,
                    'roleid' => 5, // Siswa (Student)
                    'customint2' => $groupid, // Sambungkan langsung ke grup rombel
                ];

                $enrolplugin->add_instance($course, $fields);
                $enrolledcount++;
            }
        }

        // Jalankan sinkronisasi anggota cohort ke kursus secara langsung
        if ($enrolledcount > 0) {
            $trace = new \null_progress_trace();
            enrol_cohort_sync($trace, $courseid);
            $trace->finished();
        }

        return [
            'enrolled' => $enrolledcount,
            'groups_created' => $groupscreated
        ];
    }
}
