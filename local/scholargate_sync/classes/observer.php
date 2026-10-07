<?php
namespace local_scholargate_sync;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observer for local_scholargate_sync.
 */
class observer {

    /**
     * Triggered on user_loggedin event.
     *
     * @param \core\event\base $event
     */
    public static function user_loggedin(\core\event\base $event): void {
        global $DB;

        $userid = $event->userid;
        if (!$userid || isguestuser($userid)) {
            return;
        }

        $user = $DB->get_record('user', ['id' => $userid]);
        if (!$user) {
            return;
        }

        sync_service::sync_on_login($user);
    }
}
