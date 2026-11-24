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
use core_external\external_single_structure;
use core_external\external_value;
use local_studystash\local\oauth_service;

/**
 * Creates a Moodle OAuth authorization request.
 */
final class create_oauth_request extends installation_operation {
    /**
     * Describe the operation parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'input' => new external_single_structure([
                'redirect_uri' => new external_value(PARAM_RAW, 'StudyStash OAuth callback URI.'),
                'state' => new external_value(PARAM_RAW, 'OAuth state value.'),
                'code_challenge' => new external_value(PARAM_RAW, 'PKCE code challenge.'),
                'code_challenge_method' => new external_value(PARAM_ALPHANUM, 'PKCE challenge method.'),
            ]),
        ]);
    }

    /**
     * Create an OAuth authorization request.
     *
     * @param array $input Request data.
     * @return array Authorization request response.
     */
    public static function execute(array $input): array {
        ['input' => $input] = self::validate_parameters(self::execute_parameters(), ['input' => $input]);
        self::validate_installation_context();

        return oauth_service::create_request($input);
    }

    /**
     * Describe the operation result.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'authorization_url' => new external_value(PARAM_RAW, 'Moodle authorization URL.'),
            'expires_in' => new external_value(PARAM_INT, 'Authorization request lifetime in seconds.'),
        ]);
    }
}
