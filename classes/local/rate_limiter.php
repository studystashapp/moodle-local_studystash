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
 * Enforces short fixed-window limits for authenticated plugin API requests.
 */
final class rate_limiter {
    /** Rate-limit window in seconds. */
    private const WINDOW = MINSECS;

    /** Requests permitted per window for each operation. */
    private const LIMITS = [
        'status' => 120,
        'lti_registrations' => 120,
        'lti_connection' => 60,
        'lti_grant' => 300,
        'grant_refresh' => 300,
        'grant_revoke' => 120,
        'oauth_request' => 60,
        'oauth_token' => 60,
        'import_create' => 300,
        'import_revoke' => 300,
        'user_content' => 300,
        'user_search' => 30,
        'user_file' => 120,
        'import_file' => 300,
    ];

    /**
     * Enforce a limit for an authenticated installation request.
     *
     * @param string $scope Operation scope.
     */
    public static function enforce_installation(string $scope): void {
        self::enforce($scope, 'installation', installation_credentials::id());
    }

    /**
     * Enforce a limit for a linked Moodle identity.
     *
     * @param string $scope Operation scope.
     * @param int $linkid Linked identity ID.
     */
    public static function enforce_link(string $scope, int $linkid): void {
        self::enforce($scope, 'link', (string) $linkid);
    }

    /**
     * Consume one request and return the retry delay when the limit is exceeded.
     *
     * @param string $scope Operation scope.
     * @param string $subjecttype Stable subject type.
     * @param string $subjectid Stable authenticated subject identifier.
     * @param int $limit Requests permitted in the window.
     * @param int $window Window size in seconds.
     * @return int|null Retry delay in seconds, or null while allowed.
     */
    public static function consume(
        string $scope,
        string $subjecttype,
        string $subjectid,
        int $limit,
        int $window
    ): ?int {
        global $DB;

        if ($limit < 1 || $window < 1) {
            throw new \coding_exception('Rate limits must use positive limits and windows.');
        }

        $now = time();
        $windowstart = intdiv($now, $window) * $window;
        $bucketkey = self::bucket_key($scope, $subjecttype, $subjectid, $windowstart);
        $record = $DB->get_record('local_studystash_limits', ['bucketkey' => $bucketkey]);

        if (!$record) {
            try {
                $DB->insert_record('local_studystash_limits', (object) [
                    'bucketkey' => $bucketkey,
                    'requestcount' => 1,
                    'timeexpires' => $windowstart + $window,
                ]);
                return null;
            } catch (\dml_write_exception $error) {
                $record = $DB->get_record('local_studystash_limits', ['bucketkey' => $bucketkey]);
                if (!$record) {
                    throw $error;
                }
            }
        }

        $DB->execute(
            'UPDATE {local_studystash_limits} SET requestcount = requestcount + 1 WHERE bucketkey = ?',
            [$bucketkey]
        );
        $count = (int) $DB->get_field('local_studystash_limits', 'requestcount', ['bucketkey' => $bucketkey]);
        return $count > $limit ? max(1, (int) $record->timeexpires - $now) : null;
    }

    /**
     * Enforce a configured operation limit.
     *
     * @param string $scope Operation scope.
     * @param string $subjecttype Stable subject type.
     * @param string $subjectid Stable authenticated subject identifier.
     */
    private static function enforce(string $scope, string $subjecttype, string $subjectid): void {
        if (!array_key_exists($scope, self::LIMITS)) {
            throw new \coding_exception('Unknown StudyStash rate-limit scope: ' . $scope);
        }
        $retryafter = self::consume($scope, $subjecttype, $subjectid, self::LIMITS[$scope], self::WINDOW);
        if ($retryafter === null) {
            return;
        }
        if (!headers_sent()) {
            header('Retry-After: ' . $retryafter);
        }
        api::error('rate_limit_exceeded', 'Too many requests. Try again shortly.', 429);
    }

    /**
     * Build an unlinkable bucket identifier.
     *
     * @param string $scope Operation scope.
     * @param string $subjecttype Stable subject type.
     * @param string $subjectid Stable authenticated subject identifier.
     * @param int $windowstart Window start timestamp.
     * @return string HMAC bucket key.
     */
    private static function bucket_key(
        string $scope,
        string $subjecttype,
        string $subjectid,
        int $windowstart
    ): string {
        $secret = installation_credentials::secret();
        if ($secret === '') {
            throw new \coding_exception('StudyStash installation credentials are not configured.');
        }
        return hash_hmac('sha256', implode("\n", [$scope, $subjecttype, $subjectid, $windowstart]), $secret);
    }
}
