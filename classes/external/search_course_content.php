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

namespace local_studystash\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_studystash\local\content_service;

/**
 * Searches visible files in a course.
 */
final class search_course_content extends external_api {
    /** Maximum accepted search query length. */
    private const MAX_QUERY_LENGTH = 200;
    /** Maximum accepted result offset. */
    private const MAX_OFFSET = 1000;

    /**
     * Describe the operation parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Moodle course ID.'),
            'query' => new external_value(PARAM_TEXT, 'Search query.'),
            'offset' => new external_value(PARAM_INT, 'Matching files to skip.', VALUE_DEFAULT, 0),
            'limit' => new external_value(PARAM_INT, 'Maximum files to return.', VALUE_DEFAULT, 40),
        ]);
    }

    /**
     * Search visible course files for the current Moodle user.
     *
     * @param int $courseid Moodle course ID.
     * @param string $query Search query.
     * @param int $offset Number of matching files to skip.
     * @param int $limit Maximum matching files to return.
     * @return array Paginated course content response.
     */
    public static function execute(int $courseid, string $query, int $offset = 0, int $limit = 40): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), compact('courseid', 'query', 'offset', 'limit'));
        $courseid = $params['courseid'];
        $query = trim($params['query']);
        $offset = max(0, $params['offset']);
        $limit = min(100, max(1, $params['limit']));
        if ($query === '') {
            throw new \invalid_parameter_exception('A search query is required.');
        }
        if (\core_text::strlen($query) > self::MAX_QUERY_LENGTH) {
            throw new \invalid_parameter_exception('The search query is too long.');
        }
        if ($offset > self::MAX_OFFSET) {
            throw new \invalid_parameter_exception('The search offset is too large.');
        }
        self::validate_context(\context_course::instance($courseid));
        return content_service::search_course_content($courseid, (int) $USER->id, $query, $offset, $limit);
    }

    /**
     * Describe the operation result.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'course' => get_course_content::course_structure(),
            'next_offset' => new external_value(PARAM_INT, 'Next result offset.', VALUE_REQUIRED, null, NULL_ALLOWED),
        ]);
    }
}
