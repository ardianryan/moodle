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
 * This file is part of the User section Moodle
 *
 * @copyright 1999 Martin Dougiamas  http://dougiamas.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package core_user
 */

// Do not check for the site policies in require_login() to avoid the redirect loop.
define('NO_SITEPOLICY_CHECK', true);

require_once('../config.php');
require_once($CFG->libdir.'/filelib.php');
require_once($CFG->libdir.'/resourcelib.php');

$agree = optional_param('agree', 0, PARAM_BOOL);

$PAGE->set_url('/user/policy.php');
$PAGE->set_popup_notification_allowed(false);

if (!isloggedin()) {
    require_login();
}

if (!empty($SESSION->wantsurl)) {
    $return = $SESSION->wantsurl;
} else {
    $return = $CFG->wwwroot.'/';
}

$sitepolicymanager = new \core_privacy\local\sitepolicy\manager();
if (!empty($CFG->sitepolicyhandler)) {
    // We are on the wrong page, site policies are managed by somebody else.
    if ($sitepolicyurl = $sitepolicymanager->get_redirect_url(isguestuser())) {
        redirect($sitepolicyurl);
    } else {
        redirect($return);
    }
}

$sitepolicy = $sitepolicymanager->get_embed_url(isguestuser());
if (empty($sitepolicy)) {
    // Nothing to agree to, sorry, hopefully we will not get to infinite loop.
    redirect($return);
}

if ($agree and confirm_sesskey()) {    // User has agreed.
    $sitepolicymanager->accept();
    unset($SESSION->wantsurl);
    redirect($return);
}

$strpolicyagree = get_string('policyagree');
$strpolicyagreement = get_string('policyagreement');
$strpolicyagreementclick = get_string('policyagreementclick');

$PAGE->set_context(context_system::instance());
$PAGE->set_title($strpolicyagreement);
$PAGE->set_heading($SITE->fullname);
$PAGE->navbar->add($strpolicyagreement);

echo $OUTPUT->header();
echo $OUTPUT->heading($strpolicyagreement);

$mimetype = mimeinfo('type', $sitepolicy);
if ($mimetype == 'document/unknown') {
    // Fallback for missing index.php, index.html.
    $mimetype = 'text/html';
}

if (preg_match('!^https?://!i', $sitepolicy)) {
    $isid = (current_language() === 'id');
    $policydesc = $isid
        ? 'Kebijakan privasi dan ketentuan layanan dikelola secara terpusat pada portal resmi sekolah. Silakan baca dokumen kebijakan lengkap melalui tautan di bawah ini sebelum melanjutkan:'
        : 'The site policy and privacy terms are managed centrally on the official school portal. Please review the complete policy document using the link below before proceeding:';
    $tabinfo = $isid
        ? 'Dokumen kebijakan akan terbuka di tab baru browser Anda.'
        : 'The policy document will open in a new browser tab.';

    echo '<div class="card p-4 p-md-5 mb-4 text-center shadow-sm" style="background: #ffffff; border-radius: var(--radius-card, 18px); border: 1px solid var(--color-line, #e8edf2);">';
    echo '<div class="mb-3" style="color: #059669; font-size: 2.5rem;"><i class="fa fa-shield"></i></div>';
    echo '<h4 class="fw-bold mb-2">' . format_string($SITE->fullname) . ' &bull; ' . $strpolicyagreement . '</h4>';
    echo '<p class="text-muted mb-4" style="max-width: 580px; margin-inline: auto; font-size: 0.95rem; line-height: 1.6;">' . $policydesc . '</p>';
    echo '<div class="mb-3">';
    echo '<a href="' . s($sitepolicy) . '" target="_blank" rel="noopener noreferrer" class="btn btn-primary px-4 py-2" style="border-radius: 12px; font-weight: 700; background: linear-gradient(135deg, #059669 0%, #10b981 100%); border: none;">';
    echo '<i class="fa fa-external-link mr-2 me-2"></i>' . $strpolicyagreementclick . '</a>';
    echo '</div>';
    echo '<span class="text-muted small"><i class="fa fa-info-circle mr-1 me-1"></i>' . $tabinfo . '</span>';
    echo '</div>';
} else {
    echo '<div class="noticebox">';
    echo resourcelib_embed_general($sitepolicy, $strpolicyagreement, $clicktoopen, $mimetype);
    echo '</div>';
}

$formcontinue = new single_button(new moodle_url('policy.php', array('agree' => 1)), get_string('yes'));
$formcancel = new single_button(new moodle_url($CFG->wwwroot.'/login/logout.php', array('agree' => 0)), get_string('no'));
echo $OUTPUT->confirm($strpolicyagree, $formcontinue, $formcancel);

echo $OUTPUT->footer();

