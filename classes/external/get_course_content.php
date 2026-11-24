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
 * Lists visible sections, modules, and files in a course.
 */
final class get_course_content extends external_api {
    /**
     * Describe the operation parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Moodle course ID.'),
            'sectionid' => new external_value(PARAM_INT, 'Moodle section ID.', VALUE_DEFAULT, 0),
            'recursive' => new external_value(PARAM_BOOL, 'Include descendant sections.', VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Get visible course content for the current Moodle user.
     *
     * @param int $courseid Moodle course ID.
     * @param int $sectionid Optional Moodle section ID.
     * @param bool $recursive Whether to include descendant sections.
     * @return array Course content response.
     */
    public static function execute(int $courseid, int $sectionid = 0, bool $recursive = false): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'sectionid' => $sectionid,
            'recursive' => $recursive,
        ]);
        $courseid = $params['courseid'];
        $sectionid = $params['sectionid'];
        $recursive = $params['recursive'];
        self::validate_context(\context_course::instance($courseid));

        return ['course' => content_service::course_content($courseid, (int) $USER->id, $sectionid ?: null, $recursive)];
    }

    /**
     * Describe a Moodle file result.
     *
     * @return external_single_structure
     */
    private static function file_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_TEXT, 'Moodle file ID.'),
            'name' => new external_value(PARAM_FILE, 'File name.'),
            'file_path' => new external_value(PARAM_PATH, 'Path within the Moodle file area.'),
            'mime_type' => new external_value(PARAM_RAW, 'File MIME type.', VALUE_REQUIRED, null, NULL_ALLOWED),
            'size_bytes' => new external_value(PARAM_INT, 'File size in bytes.'),
            'modified_at' => new external_value(PARAM_RAW, 'ISO 8601 modification date.'),
        ]);
    }

    /**
     * Describe the operation result.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'course' => self::course_structure(),
        ]);
    }

    /**
     * Describe the shared course-content result.
     *
     * @return external_single_structure
     */
    public static function course_structure(): external_single_structure {
        return new external_single_structure([
            'id' => new external_value(PARAM_TEXT, 'Moodle course ID.'),
            'name' => new external_value(PARAM_RAW, 'Formatted course name.'),
            'sections' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_TEXT, 'Moodle section ID.'),
                'parent_id' => new external_value(
                    PARAM_TEXT,
                    'Parent Moodle section ID for a delegated subsection.',
                    VALUE_REQUIRED,
                    null,
                    NULL_ALLOWED
                ),
                'name' => new external_value(PARAM_RAW, 'Formatted section name.'),
                'modules' => new external_multiple_structure(new external_single_structure([
                    'id' => new external_value(PARAM_TEXT, 'Moodle course module ID.'),
                    'name' => new external_value(PARAM_RAW, 'Formatted module name.'),
                    'type' => new external_value(PARAM_ALPHANUMEXT, 'Moodle module type.'),
                    'files' => new external_multiple_structure(self::file_structure()),
                ])),
            ])),
        ]);
    }
}
