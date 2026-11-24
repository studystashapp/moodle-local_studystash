<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * StudyStash Moodle integration.
 *
 * @package    local_studystash
 * @copyright  2026 StudyStash Limited
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');

use local_studystash\local\oauth_service;

$request = required_param('request', PARAM_ALPHANUMEXT);
$action = optional_param('action', '', PARAM_ALPHA);
$pageurl = new moodle_url('/local/studystash/oauth/authorize.php', ['request' => $request]);

require_login(null, false);
$PAGE->set_url($pageurl);
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('oauthconsenttitle', 'local_studystash'));
$PAGE->set_heading(get_string('oauthconsenttitle', 'local_studystash'));

if ($action !== '') {
    require_sesskey();
    if ($action === 'approve') {
        redirect(oauth_service::approve($request, (int) $USER->id));
    }
    if ($action === 'deny') {
        redirect(oauth_service::deny($request));
    }
}

oauth_service::get_request($request);
echo $OUTPUT->header();
echo html_writer::start_div('card local-studystash-oauth-consent');
echo html_writer::start_div('card-body');
echo html_writer::tag('h2', get_string('oauthconsentheading', 'local_studystash'), ['class' => 'h4']);
echo html_writer::tag('p', get_string('oauthconsentdescription', 'local_studystash'));
echo html_writer::div(fullname($USER) . ' · ' . s($USER->email), 'alert alert-light border');
echo html_writer::tag('p', get_string('oauthconsentscope', 'local_studystash'), ['class' => 'text-muted']);
echo html_writer::start_div('d-flex flex-wrap gap-2');
echo $OUTPUT->single_button(
    new moodle_url($pageurl, ['action' => 'approve', 'sesskey' => sesskey()]),
    get_string('oauthapprove', 'local_studystash'),
    'post',
    ['class' => 'mb-0']
);
echo $OUTPUT->single_button(
    new moodle_url($pageurl, ['action' => 'deny', 'sesskey' => sesskey()]),
    get_string('oauthdeny', 'local_studystash'),
    'post',
    ['class' => 'mb-0', 'type' => 'secondary']
);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();
echo $OUTPUT->footer();
