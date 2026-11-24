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
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_studystash\local\content_service;

/**
 * Lists courses visible to the authenticated Moodle user.
 */
final class list_courses extends external_api {
    /**
     * Describe the operation parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * List courses for the current Moodle user.
     *
     * @return array Course response.
     */
    public static function execute(): array {
        global $USER;

        self::validate_parameters(self::execute_parameters(), []);
        self::validate_context(\context_system::instance());

        return ['courses' => content_service::courses((int) $USER->id)];
    }

    /**
     * Describe the operation result.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courses' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_TEXT, 'Moodle course ID.'),
                'name' => new external_value(PARAM_RAW, 'Formatted course name.'),
                'short_name' => new external_value(PARAM_RAW, 'Formatted course short name.'),
                'start_at' => new external_value(
                    PARAM_RAW,
                    'ISO 8601 course start date.',
                    VALUE_REQUIRED,
                    null,
                    NULL_ALLOWED
                ),
                'end_at' => new external_value(
                    PARAM_RAW,
                    'ISO 8601 course end date.',
                    VALUE_REQUIRED,
                    null,
                    NULL_ALLOWED
                ),
            ])),
        ]);
    }
}
