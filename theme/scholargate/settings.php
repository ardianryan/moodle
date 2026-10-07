<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    $settings = new theme_boost_admin_settingspage_tabs('themesettingscholargate', get_string('configtitle', 'theme_scholargate'));
    $page = new admin_settingpage('theme_scholargate_general', get_string('generalsettings', 'theme_scholargate'));

    // Brand color.
    $name = 'theme_scholargate/brandcolor';
    $title = get_string('brandcolor', 'theme_scholargate');
    $description = get_string('brandcolor_desc', 'theme_scholargate');
    $setting = new admin_setting_configcolourpicker($name, $title, $description, '#10b981');
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    // Raw SCSS.
    $name = 'theme_scholargate/rawscss';
    $title = get_string('rawscss', 'theme_scholargate');
    $description = get_string('rawscss_desc', 'theme_scholargate');
    $setting = new admin_setting_configtextarea($name, $title, $description, '', PARAM_RAW);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $settings->add($page);
}
