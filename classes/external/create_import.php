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

use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_studystash\local\import_service;

/**
 * Creates a short-lived grant for importing selected Moodle files.
 */
final class create_import extends installation_operation {
    /**
     * Describe the operation parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'input' => new external_single_structure([
                'access_token' => new external_value(PARAM_RAW, 'User-scoped access token.'),
                'file_ids' => new external_multiple_structure(new external_value(PARAM_INT, 'Moodle file ID.')),
            ]),
        ]);
    }

    /**
     * Create an import grant.
     *
     * @param array $input Request data.
     * @return array Import grant response.
     */
    public static function execute(array $input): array {
        ['input' => $input] = self::validate_parameters(self::execute_parameters(), ['input' => $input]);
        self::validate_installation_context();

        return import_service::create($input);
    }

    /**
     * Describe the operation result.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'import_token' => new external_value(PARAM_RAW, 'Opaque import grant token.'),
            'expires_in' => new external_value(PARAM_INT, 'Import grant lifetime in seconds.'),
            'files' => new external_multiple_structure(new external_single_structure([
                'id' => new external_value(PARAM_TEXT, 'Moodle file ID.'),
                'file_name' => new external_value(PARAM_FILE, 'Authoritative Moodle filename.'),
                'mime_type' => new external_value(PARAM_RAW, 'Authoritative Moodle MIME type.'),
                'size_bytes' => new external_value(PARAM_INT, 'Authoritative Moodle file size in bytes.'),
                'modified_at' => new external_value(PARAM_TEXT, 'Authoritative Moodle file modification time (ISO 8601).'),
            ])),
        ]);
    }
}
