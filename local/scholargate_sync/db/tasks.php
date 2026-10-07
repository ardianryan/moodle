<?php
defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => 'local_scholargate_sync\task\cron_sync_task',
        'blocking'  => 0,
        'minute'    => '0',
        'hour'      => '2', // Run every night at 02:00 AM
        'day'       => '*',
        'month'     => '*',
        'dayofweek' => '*',
    ],
];
