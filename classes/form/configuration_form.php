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

namespace local_studystash\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Configuration form for StudyStash access modes.
 */
final class configuration_form extends \moodleform {
    /**
     * Define the configuration fields.
     */
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement(
            'advcheckbox',
            'enabled',
            get_string('enabled', 'local_studystash'),
            get_string('enabled_desc', 'local_studystash')
        );

        $mform->addElement(
            'advcheckbox',
            'oauthenabled',
            get_string('oauthenabled', 'local_studystash'),
            get_string('oauthenabled_desc', 'local_studystash')
        );

        $this->add_action_buttons(false, get_string('saveconfiguration', 'local_studystash'));
    }
}
