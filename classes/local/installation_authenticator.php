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

namespace local_studystash\local;

/**
 * Authenticates installation-signed HTTP requests.
 */
final class installation_authenticator {
    /** Maximum accepted request clock skew in seconds. */
    private const MAX_CLOCK_SKEW = 300;
    /** @var bool Whether the current request passed installation authentication. */
    private static bool $authenticated = false;

    /**
     * Authenticate the current request.
     *
     * @param string $body Raw request body.
     * @param string $scope Rate-limit operation scope.
     */
    public static function authenticate(string $body, string $scope = 'status'): void {
        global $DB;

        $client = self::header('HTTP_X_STUDYSTASH_CLIENT');
        $timestamp = self::header('HTTP_X_STUDYSTASH_TIMESTAMP');
        $nonce = self::header('HTTP_X_STUDYSTASH_NONCE');
        $signature = self::header('HTTP_X_STUDYSTASH_SIGNATURE');

        $expectedclient = installation_credentials::id();
        $secret = installation_credentials::secret();
        if ($client === '' || $secret === '' || !hash_equals($expectedclient, $client)) {
            api::error('invalid_client', 'Invalid StudyStash installation.', 401);
        }
        if (!ctype_digit($timestamp) || abs(time() - (int) $timestamp) > self::MAX_CLOCK_SKEW) {
            api::error('expired_request', 'The signed request has expired.', 401);
        }
        if (!preg_match('/^[A-Za-z0-9_-]{20,128}$/', $nonce)) {
            api::error('invalid_nonce', 'Invalid request nonce.', 401);
        }

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
        $canonical = self::canonical_request($_SERVER['REQUEST_METHOD'] ?? '', $path, $timestamp, $nonce, $body);
        $expected = hash_hmac('sha256', $canonical, $secret);
        if (!preg_match('/^[a-f0-9]{64}$/', $signature) || !hash_equals($expected, $signature)) {
            api::error('invalid_signature', 'Invalid request signature.', 401);
        }

        $noncehash = hash('sha256', $nonce);
        $DB->delete_records_select('local_studystash_nonces', 'timeexpires < ?', [time()]);
        if ($DB->record_exists('local_studystash_nonces', ['noncehash' => $noncehash])) {
            api::error('replayed_request', 'This signed request has already been used.', 401);
        }
        try {
            $DB->insert_record('local_studystash_nonces', (object) [
                'noncehash' => $noncehash,
                'timeexpires' => time() + self::MAX_CLOCK_SKEW,
            ]);
        } catch (\dml_write_exception $error) {
            if ($DB->record_exists('local_studystash_nonces', ['noncehash' => $noncehash])) {
                api::error('replayed_request', 'This signed request has already been used.', 401);
            }
            throw $error;
        }
        self::$authenticated = true;
        rate_limiter::enforce_installation($scope);
    }

    /**
     * Require installation authentication in an internal operation.
     */
    public static function require_authenticated(): void {
        if (!self::$authenticated) {
            throw new \coding_exception('Installation authentication is required for this operation.');
        }
    }

    /**
     * Build the canonical request string used for signing.
     *
     * @param string $method HTTP method.
     * @param string $path Request path.
     * @param string $timestamp Unix timestamp.
     * @param string $nonce Request nonce.
     * @param string $body Raw request body.
     * @return string Canonical request string.
     */
    public static function canonical_request(
        string $method,
        string $path,
        string $timestamp,
        string $nonce,
        string $body
    ): string {
        return implode("\n", [strtoupper($method), $path, $timestamp, $nonce, hash('sha256', $body)]);
    }

    /**
     * Read a request header from the server environment.
     *
     * @param string $name Server variable name.
     * @return string Header value.
     */
    private static function header(string $name): string {
        return trim((string) ($_SERVER[$name] ?? ''));
    }
}
