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
use local_studystash\local\lti_tool_registry;

/**
 * Reports the current plugin configuration and Moodle site identity.
 */
final class get_status extends installation_operation {
    /**
     * Describe the operation parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    /**
     * Get the plugin status.
     *
     * @return array Status response.
     */
    public static function execute(): array {
        global $CFG, $SITE;

        self::validate_parameters(self::execute_parameters(), []);
        self::validate_installation_context();

        return [
            'enabled' => true,
            'site_url' => $CFG->wwwroot,
            'site_name' => (string) $SITE->fullname,
            'plugin_version' => (string) get_config('local_studystash', 'version'),
            'oauth_enabled' => (bool) get_config('local_studystash', 'oauthenabled'),
            'oauth_available' => true,
            'lti_registration_count' => count(lti_tool_registry::registrations()),
        ];
    }

    /**
     * Describe the operation result.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'enabled' => new external_value(PARAM_BOOL, 'Whether the integration is enabled.'),
            'site_url' => new external_value(PARAM_RAW, 'Moodle site URL.'),
            'site_name' => new external_value(PARAM_RAW, 'Moodle site name.'),
            'plugin_version' => new external_value(PARAM_RAW, 'Installed plugin version.'),
            'oauth_enabled' => new external_value(PARAM_BOOL, 'Whether OAuth access is enabled.'),
            'oauth_available' => new external_value(PARAM_BOOL, 'Whether OAuth is supported.'),
            'lti_registration_count' => new external_value(PARAM_INT, 'Connected LTI deployment count.'),
        ]);
    }
}
