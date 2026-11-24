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

/**
 * Install default StudyStash configuration.
 *
 * @return bool Always true.
 */
function xmldb_local_studystash_install(): bool {
    set_config('installationid', bin2hex(random_bytes(12)), 'local_studystash');
    $secret = bin2hex(random_bytes(32));
    set_config('installationsecret', \core\encryption::encrypt($secret), 'local_studystash');
    set_config('installationsecretlastfour', substr($secret, -4), 'local_studystash');
    set_config('installationsecretrevealed', 0, 'local_studystash');
    set_config('enabled', 0, 'local_studystash');
    set_config('oauthenabled', 0, 'local_studystash');
    set_config('ltiregistrations', '[]', 'local_studystash');
    return true;
}
