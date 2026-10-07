<?php
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname'   => '\core\event\user_loggedin',
        'callback'    => '\local_scholargate_sync\observer::user_loggedin',
        'priority'    => 1000,
        'internal'    => false,
    ],
];
