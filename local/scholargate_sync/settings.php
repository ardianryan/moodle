<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_scholargate_sync', get_string('pluginname', 'local_scholargate_sync'));

    $settings->add(new admin_setting_heading('local_scholargate_sync/header',
        get_string('settings_header', 'local_scholargate_sync'),
        get_string('settings_header_desc', 'local_scholargate_sync')));

    // API Base URL
    $settings->add(new admin_setting_configtext('local_scholargate_sync/api_url',
        get_string('api_url', 'local_scholargate_sync'),
        get_string('api_url_desc', 'local_scholargate_sync'),
        'https://portal.sman1gedeg.sch.id',
        PARAM_URL));

    // API Key
    $settings->add(new admin_setting_configpasswordunmask('local_scholargate_sync/api_key',
        get_string('api_key', 'local_scholargate_sync'),
        get_string('api_key_desc', 'local_scholargate_sync'),
        '316dc5de90e28e81c65804abf65974a49105fe98ffec44aa009ed50a964d2f5d'));

    // Register / Helpdesk URL (Rule 3)
    $settings->add(new admin_setting_configtext('local_scholargate_sync/register_url',
        get_string('register_url', 'local_scholargate_sync'),
        get_string('register_url_desc', 'local_scholargate_sync'),
        'https://auth.sman1gedeg.sch.id/help',
        PARAM_URL));

    // Auto Sync on Login (Rule 1 & 2)
    $settings->add(new admin_setting_configcheckbox('local_scholargate_sync/auto_sync_login',
        get_string('auto_sync_login', 'local_scholargate_sync'),
        get_string('auto_sync_login_desc', 'local_scholargate_sync'),
        1));

    // Sync Cohort
    $settings->add(new admin_setting_configcheckbox('local_scholargate_sync/sync_cohort',
        get_string('sync_cohort', 'local_scholargate_sync'),
        get_string('sync_cohort_desc', 'local_scholargate_sync'),
        1));

    // Handle Mutasi / Keluar
    $settings->add(new admin_setting_configcheckbox('local_scholargate_sync/handle_mutasi',
        get_string('handle_mutasi', 'local_scholargate_sync'),
        get_string('handle_mutasi_desc', 'local_scholargate_sync'),
        1));

    // Tendik as Guru
    $settings->add(new admin_setting_configcheckbox('local_scholargate_sync/tendik_as_guru',
        get_string('tendik_as_guru', 'local_scholargate_sync'),
        get_string('tendik_as_guru_desc', 'local_scholargate_sync'),
        1));

    $ADMIN->add('localplugins', $settings);

    // Add direct link to Dashboard under Server/Local plugins
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_scholargate_sync_dashboard',
        get_string('sync_dashboard', 'local_scholargate_sync'),
        new moodle_url('/local/scholargate_sync/sync.php')
    ));
}
