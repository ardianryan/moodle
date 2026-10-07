<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

/**
 * Returns the main SCSS content.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_scholargate_get_main_scss_content($theme) {
    global $CFG;
    $scss = file_get_contents($CFG->dirroot . '/theme/boost/scss/preset/default.scss');
    return $scss;
}

/**
 * Get SCSS to prepend.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_scholargate_get_pre_scss($theme) {
    $scss = '';
    $brandcolor = !empty($theme->settings->brandcolor) ? $theme->settings->brandcolor : '#10b981';

    $scss .= "\$primary: {$brandcolor};\n";
    $scss .= "\$success: #047857;\n";
    $scss .= "\$info: #0ea5e9;\n";
    $scss .= "\$warning: #f59e0b;\n";
    $scss .= "\$body-bg: #f7f4ef;\n";
    $scss .= "\$body-color: #3f4654;\n";
    $scss .= "\$font-family-base: 'Onest', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;\n";
    $scss .= "\$headings-font-family: 'Onest', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;\n";
    $scss .= "\$headings-font-weight: 700;\n";
    $scss .= "\$card-border-radius: 20px;\n";
    $scss .= "\$border-radius: 14px;\n";
    $scss .= "\$border-radius-lg: 20px;\n";
    $scss .= "\$border-radius-sm: 8px;\n";

    return $scss;
}

/**
 * Inject additional SCSS.
 *
 * @param theme_config $theme The theme config object.
 * @return string
 */
function theme_scholargate_get_extra_scss($theme) {
    global $CFG;
    $scssfile = $CFG->dirroot . '/theme/scholargate/scss/scholargate.scss';
    $content = file_exists($scssfile) ? file_get_contents($scssfile) : '';
    if (!empty($theme->settings->rawscss)) {
        $content .= "\n" . $theme->settings->rawscss;
    }
    return $content;
}

/**
 * Serves any files associated with the theme settings.
 */
function theme_scholargate_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    if ($context->contextlevel == CONTEXT_SYSTEM && ($filearea === 'logo' || $filearea === 'backgroundimage')) {
        $theme = theme_config::load('scholargate');
        if (!array_key_exists('cacheability', $options)) {
            $options['cacheability'] = 'public';
        }
        return $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
    } else {
        send_file_not_found();
    }
}
