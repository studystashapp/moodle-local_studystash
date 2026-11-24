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

namespace local_studystash;

use local_studystash\external\create_import;
use local_studystash\external\create_oauth_request;
use local_studystash\external\exchange_lti_grant;
use local_studystash\external\exchange_oauth_code;
use local_studystash\external\get_lti_registrations;
use local_studystash\external\get_status;
use local_studystash\external\refresh_grant;
use local_studystash\external\revoke_grant;
use local_studystash\external\revoke_import;
use local_studystash\external\set_lti_connection;

/**
 * Tests the typed non-content operation contracts.
 *
 * @covers \local_studystash\external\structures
 */
final class external_operations_test extends \advanced_testcase {
    /**
     * Installation operations cannot run outside an authenticated adapter request.
     */
    public function test_installation_operation_requires_authentication(): void {
        $this->expectException(\coding_exception::class);
        get_status::execute();
    }

    /**
     * Shared token contracts preserve both LTI and OAuth responses.
     */
    public function test_token_return_contracts(): void {
        $pair = [
            'token_type' => 'Bearer',
            'access_token' => 'access-token',
            'expires_in' => 900,
            'refresh_token' => 'refresh-token',
            'refresh_expires_in' => 2592000,
        ];

        $this->assertSame(
            $pair,
            exchange_lti_grant::clean_returnvalue(exchange_lti_grant::execute_returns(), $pair)
        );
        $this->assertSame(
            $pair,
            refresh_grant::clean_returnvalue(refresh_grant::execute_returns(), $pair)
        );

        $oauthpair = ['moodle_user_id' => '42'] + $pair;
        $this->assertSame(
            $oauthpair,
            exchange_oauth_code::clean_returnvalue(exchange_oauth_code::execute_returns(), $oauthpair)
        );
    }

    /**
     * LTI registration contracts preserve optional tool names and connection states.
     */
    public function test_lti_registration_return_contracts(): void {
        $registration = [
            'client_id' => 'client-1',
            'deployment_id' => 'deployment-1',
            'status' => 'linked',
            'tool_name' => 'StudyStash',
        ];

        $this->assertSame(
            ['registrations' => [$registration]],
            get_lti_registrations::clean_returnvalue(
                get_lti_registrations::execute_returns(),
                ['registrations' => [$registration]]
            )
        );
        $this->assertSame(
            $registration,
            set_lti_connection::clean_returnvalue(set_lti_connection::execute_returns(), $registration)
        );
    }

    /**
     * Status, authorization, import, and revocation responses retain their public fields.
     */
    public function test_remaining_return_contracts(): void {
        $status = [
            'enabled' => true,
            'site_url' => 'https://moodle.example.com',
            'site_name' => 'Example Moodle',
            'plugin_version' => '0.4.5',
            'oauth_enabled' => true,
            'oauth_available' => true,
            'lti_registration_count' => 1,
        ];
        $oauthrequest = [
            'authorization_url' => 'https://moodle.example.com/local/studystash/oauth/authorize.php?request=abc',
            'expires_in' => 600,
        ];
        $import = [
            'import_token' => 'import-token',
            'expires_in' => DAYSECS,
            'files' => [[
                'id' => '12',
                'file_name' => 'lecture.pdf',
                'mime_type' => 'application/pdf',
                'size_bytes' => 1024,
                'modified_at' => '2026-09-24T12:00:00+00:00',
            ]],
        ];
        $revoked = ['revoked' => true];

        $this->assertSame($status, get_status::clean_returnvalue(get_status::execute_returns(), $status));
        $this->assertSame(
            $oauthrequest,
            create_oauth_request::clean_returnvalue(create_oauth_request::execute_returns(), $oauthrequest)
        );
        $this->assertSame($import, create_import::clean_returnvalue(create_import::execute_returns(), $import));
        $this->assertSame(
            $revoked,
            revoke_grant::clean_returnvalue(revoke_grant::execute_returns(), $revoked)
        );
        $this->assertSame(
            $revoked,
            revoke_import::clean_returnvalue(revoke_import::execute_returns(), $revoked)
        );
    }

    /**
     * Request descriptions accept the payloads used by the StudyStash client.
     */
    public function test_input_contracts(): void {
        $registrations = ['input' => ['registrations' => [[
            'client_id' => 'client-1',
            'deployment_id' => 'deployment-1',
        ]]]];
        $import = ['input' => ['access_token' => 'access-token', 'file_ids' => ['12', 13]]];
        $oauthrequest = ['input' => [
            'redirect_uri' => 'https://studystash.example/api/lms/moodle/oauth/callback',
            'state' => str_repeat('s', 32),
            'code_challenge' => str_repeat('c', 43),
            'code_challenge_method' => 'S256',
        ]];

        $this->assertSame(
            $registrations,
            get_lti_registrations::validate_parameters(
                get_lti_registrations::execute_parameters(),
                $registrations
            )
        );
        $this->assertSame(
            ['input' => ['access_token' => 'access-token', 'file_ids' => [12, 13]]],
            create_import::validate_parameters(create_import::execute_parameters(), $import)
        );
        $this->assertSame(
            $oauthrequest,
            create_oauth_request::validate_parameters(create_oauth_request::execute_parameters(), $oauthrequest)
        );
    }
}
