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
 * Exchanges an OAuth authorization code for user-scoped tokens.
 */
final class exchange_oauth_code extends installation_operation {
    /**
     * Describe the operation parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'input' => new external_single_structure([
                'code' => new external_value(PARAM_RAW, 'OAuth authorization code.'),
                'code_verifier' => new external_value(PARAM_RAW, 'PKCE code verifier.'),
                'redirect_uri' => new external_value(PARAM_RAW, 'StudyStash OAuth callback URI.'),
            ]),
        ]);
    }

    /**
     * Exchange an OAuth authorization code.
     *
     * @param array $input Request data.
     * @return array Moodle user ID and issued token pair.
     */
    public static function execute(array $input): array {
        ['input' => $input] = self::validate_parameters(self::execute_parameters(), ['input' => $input]);
        self::validate_installation_context();

        return oauth_service::exchange($input);
    }

    /**
     * Describe the operation result.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return structures::token_pair(true);
    }
}
