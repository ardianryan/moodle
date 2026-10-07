<?php
defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => \core_course\hook\after_form_definition::class,
        'callback' => \local_scholargate_sync\course_hook_callbacks::class . '::extend_course_form',
    ],
    [
        'hook' => \core_course\hook\after_form_submission::class,
        'callback' => \local_scholargate_sync\course_hook_callbacks::class . '::save_course_form',
    ],
];
