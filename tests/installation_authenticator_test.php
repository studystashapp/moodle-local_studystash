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

use local_studystash\local\installation_authenticator;
use local_studystash\local\installation_credentials;

/**
 * Tests installation request signing.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(installation_authenticator::class)]
final class installation_authenticator_test extends \advanced_testcase {
    public function test_canonical_request_is_stable(): void {
        $body = '{"moodle_user_id":42}';
        $canonical = installation_authenticator::canonical_request(
            'post',
            '/local/studystash/api/v1/grants/lti.php',
            '1723464000',
            'abcdefghijklmnopqrstuvwx',
            $body
        );

        $this->assertSame(
            "POST\n/local/studystash/api/v1/grants/lti.php\n1723464000\nabcdefghijklmnopqrstuvwx\n" . hash('sha256', $body),
            $canonical
        );
    }

    /**
     * A valid signature is accepted and its nonce is persisted for replay protection.
     */
    public function test_valid_signature_consumes_nonce(): void {
        global $DB;

        $this->resetAfterTest(true);
        $client = 'test-installation';
        $secret = str_repeat('s', 64);
        $timestamp = (string) time();
        $nonce = 'abcdefghijklmnopqrstuvwx';
        $path = '/local/studystash/api/v1/status.php';
        $body = '{}';
        set_config('installationid', $client, 'local_studystash');
        installation_credentials::store_secret($secret, true);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = $path;
        $_SERVER['HTTP_X_STUDYSTASH_CLIENT'] = $client;
        $_SERVER['HTTP_X_STUDYSTASH_TIMESTAMP'] = $timestamp;
        $_SERVER['HTTP_X_STUDYSTASH_NONCE'] = $nonce;
        $_SERVER['HTTP_X_STUDYSTASH_SIGNATURE'] = hash_hmac(
            'sha256',
            installation_authenticator::canonical_request('POST', $path, $timestamp, $nonce, $body),
            $secret
        );

        try {
            installation_authenticator::authenticate($body);
            installation_authenticator::require_authenticated();
            $this->assertTrue($DB->record_exists('local_studystash_nonces', [
                'noncehash' => hash('sha256', $nonce),
            ]));
        } finally {
            $authenticated = new \ReflectionProperty(installation_authenticator::class, 'authenticated');
            $authenticated->setValue(null, false);
            unset(
                $_SERVER['REQUEST_METHOD'],
                $_SERVER['REQUEST_URI'],
                $_SERVER['HTTP_X_STUDYSTASH_CLIENT'],
                $_SERVER['HTTP_X_STUDYSTASH_TIMESTAMP'],
                $_SERVER['HTTP_X_STUDYSTASH_NONCE'],
                $_SERVER['HTTP_X_STUDYSTASH_SIGNATURE']
            );
        }
    }
}
