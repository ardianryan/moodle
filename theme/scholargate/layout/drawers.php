<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * A drawer based layout for the boost theme.
 *
 * @package   theme_boost
 * @copyright 2021 Bas Brands
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/behat/lib.php');
require_once($CFG->dirroot . '/course/lib.php');

// Add block button in editing mode.
$addblockbutton = $OUTPUT->addblockbutton();

if (isloggedin()) {
    $courseindexopen = (get_user_preferences('drawer-open-index', true) == true);
    $blockdraweropen = (get_user_preferences('drawer-open-block') == true);
} else {
    $courseindexopen = false;
    $blockdraweropen = false;
}

if (defined('BEHAT_SITE_RUNNING') && get_user_preferences('behat_keep_drawer_closed') != 1) {
    $blockdraweropen = true;
}

$extraclasses = ['uses-drawers'];
if ($courseindexopen) {
    $extraclasses[] = 'drawer-open-index';
}

$blockshtml = $OUTPUT->blocks('side-pre');
$hasblocks = (strpos($blockshtml, 'data-block=') !== false || !empty($addblockbutton));
if (!$hasblocks) {
    $blockdraweropen = false;
}
$courseindex = core_course_drawer();
if (!$courseindex) {
    $courseindexopen = false;
}

$bodyattributes = $OUTPUT->body_attributes($extraclasses);
$forceblockdraweropen = $OUTPUT->firstview_fakeblocks();

$secondarynavigation = false;
$overflow = '';
if ($PAGE->has_secondary_navigation()) {
    $tablistnav = $PAGE->has_tablist_secondary_navigation();
    $moremenu = new \core\navigation\output\more_menu($PAGE->secondarynav, 'nav-tabs', true, $tablistnav);
    $secondarynavigation = $moremenu->export_for_template($OUTPUT);
    $overflowdata = $PAGE->secondarynav->get_overflow_menu_data();
    if (!is_null($overflowdata)) {
        $overflow = $overflowdata->export_for_template($OUTPUT);
    }
}

$primary = new core\navigation\output\primary($PAGE);
$renderer = $PAGE->get_renderer('core');
$primarymenu = $primary->export_for_template($renderer);
$buildregionmainsettings = !$PAGE->include_region_main_settings_in_header_actions() && !$PAGE->has_secondary_navigation();
// If the settings menu will be included in the header then don't add it here.
$regionmainsettingsmenu = $buildregionmainsettings ? $OUTPUT->region_main_settings_menu() : false;

$header = $PAGE->activityheader;
$headercontent = $header->export_for_template($renderer);

$isfrontpage = ($PAGE->pagelayout === 'frontpage');
$isguestorloggedout = !isloggedin() || isguestuser();
$showfrontpageportal = ($isfrontpage && $isguestorloggedout);
$loginurl = get_login_url();

$sitefullname = format_string($SITE->fullname, true, ['context' => context_course::instance(SITEID), "escape" => false]);
$siteshortname = format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), "escape" => false]);
$sitesummary = format_text($SITE->summary, FORMAT_HTML, ['context' => context_course::instance(SITEID)]);

$logourl = $OUTPUT->get_logo_url();
$compactlogourl = $OUTPUT->get_compact_logo_url();
$sitelogourl = $logourl ? $logourl->out(false) : ($compactlogourl ? $compactlogourl->out(false) : (new moodle_url('/pix/logo_smage.png'))->out(false));

$featuredcourse = null;
if ($showfrontpageportal) {
    global $DB;
    $courses = $DB->get_records_sql("
        SELECT c.id, c.fullname, c.shortname, c.summary
        FROM {course} c
        WHERE c.id > 1 AND c.visible = 1
        ORDER BY c.sortorder ASC, c.id DESC
        LIMIT 1
    ");
    if (!empty($courses)) {
        $c = reset($courses);
        $cle = new core_course_list_element($c);
        $courseimage = null;
        foreach ($cle->get_course_overviewfiles() as $file) {
            $url = moodle_url::make_file_url('/pluginfile.php', '/' . $file->get_contextid() . '/' . $file->get_component() . '/' . $file->get_filearea() . $file->get_filepath() . $file->get_filename());
            $courseimage = $url->out(false);
            break;
        }
        $rawsummary = strip_tags($c->summary);
        $cleanlines = array_filter(array_map('trim', explode("\n", $rawsummary)));
        $firstline = !empty($cleanlines) ? reset($cleanlines) : '';
        if (mb_strlen($firstline) > 130) {
            $firstline = mb_substr($firstline, 0, 127) . '...';
        }
        $featuredcourse = [
            'id' => $c->id,
            'fullname' => format_string($c->fullname),
            'shortname' => format_string($c->shortname),
            'summary' => $firstline ?: ('Kelas aktif di LMS ' . $siteshortname),
            'imageurl' => $courseimage ?: $sitelogourl,
            'viewurl' => (new moodle_url('/course/view.php', ['id' => $c->id]))->out(false),
        ];
    }
}

$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), "escape" => false]),
    'sitefullname' => $sitefullname,
    'siteshortname' => $siteshortname,
    'sitesummary' => $sitesummary,
    'sitelogourl' => $sitelogourl,
    'featuredcourse' => $featuredcourse,
    'output' => $OUTPUT,
    'sidepreblocks' => $blockshtml,
    'hasblocks' => $hasblocks,
    'bodyattributes' => $bodyattributes,
    'courseindexopen' => $courseindexopen,
    'blockdraweropen' => $blockdraweropen,
    'courseindex' => $courseindex,
    'primarymoremenu' => $primarymenu['moremenu'],
    'secondarymoremenu' => $secondarynavigation ?: false,
    'mobileprimarynav' => $primarymenu['mobileprimarynav'],
    'usermenu' => $primarymenu['user'],
    'langmenu' => $primarymenu['lang'],
    'forceblockdraweropen' => $forceblockdraweropen,
    'regionmainsettingsmenu' => $regionmainsettingsmenu,
    'hasregionmainsettingsmenu' => !empty($regionmainsettingsmenu),
    'overflow' => $overflow,
    'headercontent' => $headercontent,
    'addblockbutton' => $addblockbutton,
    'isfrontpage' => $isfrontpage,
    'showfrontpageportal' => $showfrontpageportal,
    'loginurl' => $loginurl,
];

echo $OUTPUT->render_from_template('theme_scholargate/drawers', $templatecontext);
