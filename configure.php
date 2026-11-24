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

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_studystash\form\configuration_form;
use local_studystash\event\configuration_updated;
use local_studystash\event\installation_secret_rotated;
use local_studystash\local\installation_credentials;
use local_studystash\local\lti_tool_registry;

require_login();
require_capability('local/studystash:configure', context_system::instance());

$pageurl = new moodle_url('/local/studystash/configure.php');
$PAGE->set_url($pageurl);
admin_externalpage_setup('local_studystash');
$PAGE->set_title(get_string('configurationtitle', 'local_studystash'));
$PAGE->set_heading(get_string('configurationtitle', 'local_studystash'));

$action = optional_param('action', '', PARAM_ALPHA);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$revealedsecret = $SESSION->local_studystash_revealedsecret ?? null;
unset($SESSION->local_studystash_revealedsecret);

if ($action === 'reveal' && confirm_sesskey()) {
    $secret = installation_credentials::reveal_once();
    if ($secret === null) {
        redirect(
            $pageurl,
            get_string('secretalreadyrevealed', 'local_studystash'),
            null,
            \core\output\notification::NOTIFY_WARNING
        );
    }
    $SESSION->local_studystash_revealedsecret = $secret;
    redirect($pageurl);
}

if ($action === 'rotate' && confirm_sesskey()) {
    if (!$confirm) {
        echo $OUTPUT->header();
        echo $OUTPUT->confirm(
            get_string('rotatesecretconfirm', 'local_studystash'),
            new moodle_url($pageurl, ['action' => 'rotate', 'confirm' => 1, 'sesskey' => sesskey()]),
            $pageurl
        );
        echo $OUTPUT->footer();
        exit;
    }
    $SESSION->local_studystash_revealedsecret = installation_credentials::rotate();
    installation_secret_rotated::create(['context' => context_system::instance()])->trigger();
    redirect($pageurl);
}

$form = new configuration_form($pageurl);
$form->set_data((object) [
    'enabled' => (bool) get_config('local_studystash', 'enabled'),
    'oauthenabled' => (bool) get_config('local_studystash', 'oauthenabled'),
]);

if ($data = $form->get_data()) {
    $enabledvalue = !empty($data->enabled) ? 1 : 0;
    $oauthenabledvalue = !empty($data->oauthenabled) ? 1 : 0;
    set_config('enabled', $enabledvalue, 'local_studystash');
    set_config('oauthenabled', $oauthenabledvalue, 'local_studystash');
    configuration_updated::create([
        'context' => context_system::instance(),
        'other' => ['enabled' => $enabledvalue, 'oauthenabled' => $oauthenabledvalue],
    ])->trigger();
    redirect(
        $pageurl,
        get_string('configurationsaved', 'local_studystash'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$enabled = (bool) get_config('local_studystash', 'enabled');
$statusclass = $enabled ? 'badge-success' : 'badge-secondary';
$statustext = $enabled ? get_string('statusenabled', 'local_studystash') : get_string('statusdisabled', 'local_studystash');
$maskedsecret = str_repeat('•', 12) . installation_credentials::secret_last_four();

echo $OUTPUT->header();
echo html_writer::start_div('local-studystash-settings');
echo html_writer::start_div('local-studystash-heading');
echo html_writer::tag('div', get_string('configurationintro', 'local_studystash'), ['class' => 'text-muted']);
echo html_writer::span($statustext, 'badge ' . $statusclass);
echo html_writer::end_div();

echo html_writer::start_div('card local-studystash-card');
echo html_writer::start_div('card-body');
echo html_writer::tag('h2', get_string('connectioncredentials', 'local_studystash'), ['class' => 'h4']);
echo html_writer::tag('p', get_string('connectioncredentials_desc', 'local_studystash'), ['class' => 'text-muted']);

echo html_writer::start_div('form-group');
echo html_writer::tag('label', get_string('installationid', 'local_studystash'), ['for' => 'local-studystash-installation-id']);
echo html_writer::empty_tag('input', [
    'id' => 'local-studystash-installation-id',
    'class' => 'form-control font-monospace',
    'type' => 'text',
    'value' => installation_credentials::id(),
    'readonly' => 'readonly',
]);
echo html_writer::tag('small', get_string('installationid_desc', 'local_studystash'), ['class' => 'form-text text-muted']);
echo html_writer::end_div();

echo html_writer::start_div('form-group mb-0');
echo html_writer::tag('label', get_string('installationsecret', 'local_studystash'));
echo html_writer::start_div('local-studystash-secret-row w-100');
if ($revealedsecret !== null) {
    echo html_writer::tag('code', s($revealedsecret), ['class' => 'local-studystash-secret-value flex-grow-1']);
} else {
    echo html_writer::tag('code', s($maskedsecret), ['class' => 'local-studystash-secret-mask flex-grow-1']);
}
if (installation_credentials::can_reveal()) {
    echo $OUTPUT->single_button(
        new moodle_url($pageurl, ['action' => 'reveal', 'sesskey' => sesskey()]),
        get_string('revealsecret', 'local_studystash'),
        'post',
        ['class' => 'local-studystash-action']
    );
} else {
    echo $OUTPUT->single_button(
        new moodle_url($pageurl, ['action' => 'rotate', 'sesskey' => sesskey()]),
        get_string('rotatesecret', 'local_studystash'),
        'post',
        ['class' => 'local-studystash-action local-studystash-action-rotate', 'type' => 'secondary']
    );
}
echo html_writer::end_div();
if ($revealedsecret !== null) {
    echo html_writer::start_div('alert alert-warning local-studystash-secret-warning mt-2 mb-0', ['role' => 'status']);
    echo html_writer::tag('strong', get_string('savesecretnow', 'local_studystash'), ['class' => 'd-block']);
    echo html_writer::tag('div', get_string('secretonetimewarning', 'local_studystash'), ['class' => 'small mt-1']);
    echo html_writer::end_div();
} else {
    echo html_writer::tag('small', get_string('installationsecret_desc', 'local_studystash'), ['class' => 'form-text text-muted']);
}
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('card local-studystash-card');
echo html_writer::start_div('card-body');
echo html_writer::tag('h2', get_string('ltiaccess', 'local_studystash'), ['class' => 'h4']);
echo html_writer::tag('p', get_string('ltiaccess_desc', 'local_studystash'), ['class' => 'text-muted']);

$registrations = lti_tool_registry::registrations();
echo html_writer::tag('h3', get_string('detectedregistrations', 'local_studystash'), ['class' => 'h5 mt-4']);
if ($registrations) {
    echo html_writer::start_tag('ul', ['class' => 'list-group local-studystash-registrations']);
    foreach ($registrations as $registration) {
        $details = get_string('detectedregistrationdetails', 'local_studystash', (object) [
            'clientid' => s($registration->clientid),
            'deploymentid' => s($registration->deploymentid),
        ]);
        echo html_writer::start_tag('li', ['class' => 'list-group-item']);
        echo html_writer::tag('strong', s($registration->name));
        echo html_writer::tag('div', $details, ['class' => 'small text-muted']);
        echo html_writer::end_tag('li');
    }
    echo html_writer::end_tag('ul');
} else {
    echo html_writer::div(get_string('noregistrationslinked', 'local_studystash'), 'alert alert-info mb-0');
}
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('card local-studystash-card');
echo html_writer::start_div('card-body');
echo html_writer::tag('h2', get_string('accesscontrols', 'local_studystash'), ['class' => 'h4']);
echo html_writer::tag('p', get_string('accesscontrols_desc', 'local_studystash'), ['class' => 'text-muted']);
$form->display();
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();
echo $OUTPUT->footer();
