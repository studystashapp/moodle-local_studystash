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

define('NO_MOODLE_COOKIES', true);
define('AJAX_SCRIPT', true);

require(dirname(__FILE__, 6) . '/config.php');

use local_studystash\local\api;
use local_studystash\local\import_service;

api::require_method('GET');
api::require_enabled();
$token = trim((string) ($_SERVER['HTTP_X_STUDYSTASH_IMPORT_TOKEN'] ?? ''));
if ($token === '') {
    api::error('missing_import_grant', 'An import grant is required.', 401);
}
$fileid = required_param('fileid', PARAM_INT);
$file = import_service::authorized_file($token, $fileid);
send_stored_file($file, 0, 0, false, ['dontdie' => false]);
