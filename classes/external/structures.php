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

use core_external\external_single_structure;
use core_external\external_value;

/**
 * Shared structures used by StudyStash operations.
 */
final class structures {
    /**
     * Describe a user-scoped access and refresh token pair.
     *
     * @param bool $includeuserid Include the Moodle user ID returned by OAuth exchange.
     * @return external_single_structure
     */
    public static function token_pair(bool $includeuserid = false): external_single_structure {
        $fields = [
            'token_type' => new external_value(PARAM_ALPHA, 'Token type.'),
            'access_token' => new external_value(PARAM_RAW, 'Opaque access token.'),
            'expires_in' => new external_value(PARAM_INT, 'Access token lifetime in seconds.'),
            'refresh_token' => new external_value(PARAM_RAW, 'Opaque refresh token.'),
            'refresh_expires_in' => new external_value(PARAM_INT, 'Refresh token lifetime in seconds.'),
        ];
        if ($includeuserid) {
            $fields = ['moodle_user_id' => new external_value(PARAM_TEXT, 'Moodle user ID.')] + $fields;
        }
        return new external_single_structure($fields);
    }

    /**
     * Describe an LTI deployment connection status.
     *
     * @return external_single_structure
     */
    public static function lti_registration(): external_single_structure {
        return new external_single_structure([
            'client_id' => new external_value(PARAM_TEXT, 'LTI client ID.'),
            'deployment_id' => new external_value(PARAM_TEXT, 'LTI deployment ID.'),
            'status' => new external_value(PARAM_ALPHAEXT, 'Connection status.'),
            'tool_name' => new external_value(
                PARAM_RAW,
                'Moodle external tool name.',
                VALUE_OPTIONAL,
                null,
                NULL_ALLOWED
            ),
        ]);
    }

    /**
     * Describe a successful revocation.
     *
     * @return external_single_structure
     */
    public static function revoked(): external_single_structure {
        return new external_single_structure([
            'revoked' => new external_value(PARAM_BOOL, 'Whether the grant was revoked.'),
        ]);
    }
}
