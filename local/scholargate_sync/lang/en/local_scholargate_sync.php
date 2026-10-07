<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'ScholarGate Identity Sync';
$string['settings_header'] = 'ScholarGate Identity API Settings';
$string['settings_header_desc'] = 'Configuration for synchronizing master academic data (Teachers, Students, Classes, Academic Year) from ScholarGate Identity.';

$string['api_url'] = 'ScholarGate API Base URL';
$string['api_url_desc'] = 'Base URL of the ScholarGate Identity portal (e.g., https://portal.sman1gedeg.sch.id).';

$string['api_key'] = 'ScholarGate API Key';
$string['api_key_desc'] = 'Secret API Key registered in ScholarGate Portal Apps (X-API-Key).';

$string['register_url'] = 'Registration / Help URL';
$string['register_url_desc'] = 'Redirect URL if a user tries to log in via SSO but is not found in ScholarGate (Rule 3).';

$string['auto_sync_login'] = 'Auto Sync on Login';
$string['auto_sync_login_desc'] = 'Automatically lookup and synchronize profile fields, roles, and cohorts when a user logs in.';

$string['sync_cohort'] = 'Sync Class Cohorts';
$string['sync_cohort_desc'] = 'Automatically create class cohorts (e.g., Kelas X-1) and assign students accordingly.';

$string['handle_mutasi'] = 'Handle Student Mutations & Alumni';
$string['handle_mutasi_desc'] = 'Automatically suspend accounts marked as "keluar" and move graduated students to the Alumni cohort.';

$string['tendik_as_guru'] = 'Assign Tendik as Course Creator';
$string['tendik_as_guru_desc'] = 'Grant Course Creator privileges to administrative staff (Tenaga Kependidikan) as well as Teachers.';

$string['sync_dashboard'] = 'ScholarGate Sync Dashboard';
$string['dashboard_title'] = 'ScholarGate Identity Master Data Synchronization';
$string['dashboard_desc'] = 'Manage bulk synchronization of Academic Years, Teachers, Students, and Cohorts from ScholarGate Identity.';

$string['user_not_registered'] = 'Your account is not registered in the school ScholarGate directory. Please contact the administrator or complete registration at {$a}.';
$string['account_suspended_mutasi'] = 'Your account has been deactivated because you are marked as departed / mutated in the school directory.';

$string['rombel_enrol_header'] = 'Enrol Class Rombel (ScholarGate)';
$string['choose_rombel'] = 'Choose Rombel / Class';
$string['choose_rombel_help'] = 'Select one or more class rombels to automatically enrol all students into this course.';
$string['create_groups'] = 'Automatically Create Course Groups';
$string['create_groups_desc'] = 'Organize students into course groups corresponding to their class rombel (enables grade and assignment filtering per class).';
$string['enrol_rombel_nav'] = 'Enrol Class Rombel';
$string['enrol_rombel_title'] = 'Enrol Class Rombel';
$string['enrol_rombel_success'] = 'Successfully enrolled {$a->count} Class Rombels into the course (with {$a->groups} new groups created).';
$string['no_rombel_selected'] = 'Please select at least one class rombel to enrol.';

