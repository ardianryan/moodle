<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Extends course navigation to add 'Daftarkan Rombel Siswa' link.
 *
 * @param navigation_node $navigation
 * @param stdClass $course
 * @param context_course $context
 */
function local_scholargate_sync_extend_navigation_course(navigation_node $navigation, stdClass $course, context_course $context) {
    if ($course->id == SITEID) {
        return;
    }

    if (has_capability('moodle/course:enrolconfig', $context)) {
        $url = new moodle_url('/local/scholargate_sync/enrol_rombel.php', ['id' => $course->id]);
        $node = navigation_node::create(
            get_string('enrol_rombel_nav', 'local_scholargate_sync'),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'scholargate_enrol_rombel',
            new pix_icon('i/cohort', '')
        );
        $navigation->add_node($node);
    }
}
