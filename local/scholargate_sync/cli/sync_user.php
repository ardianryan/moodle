<?php
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognized) = cli_get_params([
    'help'     => false,
    'username' => '',
    'userid'   => 0,
], [
    'h' => 'help',
    'u' => 'username',
]);

if ($options['help'] || (empty($options['username']) && empty($options['userid']))) {
    echo "ScholarGate Identity - Single User Sync CLI\n\n";
    echo "Usage:\n";
    echo "  php sync_user.php --username=<username/nip>\n";
    echo "  php sync_user.php --userid=<id>\n\n";
    exit(0);
}

global $DB;
$user = null;
if (!empty($options['userid'])) {
    $user = $DB->get_record('user', ['id' => $options['userid'], 'deleted' => 0]);
} elseif (!empty($options['username'])) {
    $user = $DB->get_record('user', ['username' => $options['username'], 'deleted' => 0]);
}

if (!$user) {
    echo "User not found in local Moodle database!\n";
    echo "Attempting direct lookup in ScholarGate Identity API...\n";
    $client = new \local_scholargate_sync\client();
    $res = $client->lookup_member(['username' => $options['username']]);
    if (!empty($res['found'])) {
        echo "Found in ScholarGate! Upserting to Moodle...\n";
        $userid = \local_scholargate_sync\bulk_sync_service::upsert_member($res['data']);
        echo "Success! Created/updated Moodle user ID: {$userid}\n";
    } else {
        echo "Member not found in ScholarGate Identity API either.\n";
    }
    exit(0);
}

echo "Found local user: {$user->username} ({$user->firstname} {$user->lastname}, ID: {$user->id})\n";
echo "Executing sync...\n";

// Force sync by clearing local cached profile check
$client = new \local_scholargate_sync\client();
$res = $client->lookup_member([
    'username' => $user->username,
    'email'    => $user->email,
]);

if (!empty($res['found'])) {
    \local_scholargate_sync\sync_service::apply_member_data_to_user($user, $res['data']);
    echo "Sync SUCCESS! Data applied to user ID: {$user->id}\n";
} else {
    echo "Member not found in ScholarGate Identity API.\n";
}
