<?php
namespace local_scholargate_sync\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task for nightly bulk sync from ScholarGate Identity.
 */
class cron_sync_task extends \core\task\scheduled_task {

    /**
     * Get task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('pluginname', 'local_scholargate_sync') . ' (Nightly Bulk Sync)';
    }

    /**
     * Execute task.
     */
    public function execute(): void {
        mtrace('Starting ScholarGate Identity bulk sync...');
        $result = \local_scholargate_sync\bulk_sync_service::sync_all();
        mtrace($result['message'] ?? 'Bulk sync completed.');
    }
}
