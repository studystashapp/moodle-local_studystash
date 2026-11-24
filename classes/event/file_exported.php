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

namespace local_studystash\event;

/**
 * Records a course file export to StudyStash.
 */
final class file_exported extends base {
    /**
     * Initialise the event.
     */
    protected function init(): void {
        $this->init_studystash_event('r');
        $this->data['objecttable'] = 'files';
    }

    /**
     * Get the event name.
     *
     * @return string Event name.
     */
    public static function get_name(): string {
        return get_string('eventfileexported', 'local_studystash');
    }

    /**
     * Get the event description.
     *
     * @return string Event description.
     */
    public function get_description(): string {
        return "The paired StudyStash installation exported file '{$this->objectid}' using import grant "
            . "'{$this->other['importgrantid']}' for the user with id '{$this->relateduserid}'.";
    }
}
