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
use local_studystash\local\lti_tool_registry;

/**
 * Reports connection status for registered StudyStash LTI deployments.
 */
final class get_lti_registrations extends installation_operation {
    /**
     * Describe the operation parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'input' => new external_single_structure([
                'registrations' => new external_multiple_structure(new external_single_structure([
                    'client_id' => new external_value(PARAM_TEXT, 'LTI client ID.', VALUE_OPTIONAL, ''),
                    'deployment_id' => new external_value(PARAM_TEXT, 'LTI deployment ID.', VALUE_OPTIONAL, ''),
                ])),
            ]),
        ]);
    }

    /**
     * Get LTI deployment statuses.
     *
     * @param array $input Request data.
     * @return array Registration status response.
     */
    public static function execute(array $input): array {
        ['input' => $input] = self::validate_parameters(self::execute_parameters(), ['input' => $input]);
        self::validate_installation_context();

        return ['registrations' => lti_tool_registry::statuses($input['registrations'])];
    }

    /**
     * Describe the operation result.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'registrations' => new external_multiple_structure(structures::lti_registration()),
        ]);
    }
}
