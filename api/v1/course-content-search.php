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

require(dirname(__FILE__, 5) . '/config.php');

use local_studystash\external\search_course_content;
use local_studystash\local\api;
use local_studystash\local\token_service;

api::require_method('GET');
api::require_enabled();
token_service::authenticate_access_token('user_search');
$courseid = required_param('courseid', PARAM_INT);
$query = required_param('query', PARAM_TEXT);
$offset = optional_param('offset', 0, PARAM_INT);
$limit = optional_param('limit', 40, PARAM_INT);
api::respond_external(
    search_course_content::class,
    search_course_content::execute($courseid, $query, $offset, $limit)
);
