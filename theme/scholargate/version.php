<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2026100703;
$plugin->requires  = 2024100100; // Moodle 4.5+
$plugin->component = 'theme_scholargate';
$plugin->dependencies = [
    'theme_boost' => 2024100100,
];
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0.0';
