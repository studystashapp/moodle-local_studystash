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
 * Implements Moodle account authorization for StudyStash.
 */
final class oauth_service {
    /** Authorization request lifetime in seconds. */
    private const REQUEST_LIFETIME = 600;

    /**
     * Create an authorization request.
     *
     * @param array $input Request data.
     * @return array Authorization URL and expiry.
     */
    public static function create_request(array $input): array {
        global $CFG, $DB;

        self::require_enabled();
        $redirecturi = trim((string) ($input['redirect_uri'] ?? ''));
        $state = trim((string) ($input['state'] ?? ''));
        $challenge = trim((string) ($input['code_challenge'] ?? ''));
        if (
            ($input['code_challenge_method'] ?? '') !== 'S256'
                || !preg_match('/^[A-Za-z0-9_-]{43}$/', $challenge)
                || !preg_match('/^[A-Za-z0-9_-]{20,128}$/', $state)
                || !self::valid_redirect_uri($redirecturi)
        ) {
            api::error('invalid_request', 'The OAuth authorization request is invalid.', 400);
        }

        $request = self::random_token(32);
        $now = time();
        $DB->insert_record('local_studystash_oauth', (object) [
            'requesthash' => hash('sha256', $request),
            'codehash' => null,
            'userid' => null,
            'redirecturi' => $redirecturi,
            'state' => $state,
            'codechallenge' => $challenge,
            'timeexpires' => $now + self::REQUEST_LIFETIME,
            'usedat' => null,
            'timecreated' => $now,
        ]);
        return [
            'authorization_url' => (new \moodle_url('/local/studystash/oauth/authorize.php', [
                'request' => $request,
            ]))->out(false),
            'expires_in' => self::REQUEST_LIFETIME,
        ];
    }

    /**
     * Load an active authorization request.
     *
     * @param string $request Opaque request token.
     * @return \stdClass Authorization request record.
     */
    public static function get_request(string $request): \stdClass {
        global $DB;

        if (!get_config('local_studystash', 'enabled') || !get_config('local_studystash', 'oauthenabled')) {
            throw new \moodle_exception('oauthdisabled', 'local_studystash');
        }
        if (!preg_match('/^[A-Za-z0-9_-]{43}$/', $request)) {
            throw new \moodle_exception('invalidoauthrequest', 'local_studystash');
        }
        $record = $DB->get_record('local_studystash_oauth', [
            'requesthash' => hash('sha256', $request),
        ]);
        if (!$record || $record->usedat || $record->codehash || $record->timeexpires <= time()) {
            throw new \moodle_exception('invalidoauthrequest', 'local_studystash');
        }
        return $record;
    }

    /**
     * Approve an authorization request for a Moodle user.
     *
     * @param string $request Opaque request token.
     * @param int $userid Moodle user ID.
     * @return string StudyStash callback URL.
     */
    public static function approve(string $request, int $userid): string {
        global $DB;

        $record = self::get_request($request);
        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0, 'suspended' => 0]);
        if (!$user || isguestuser($user)) {
            throw new \moodle_exception('oauthinvaliduser', 'local_studystash');
        }
        $code = self::random_token(48);
        $record->userid = $userid;
        $record->codehash = hash('sha256', $code);
        $DB->update_record('local_studystash_oauth', $record);
        \local_studystash\event\oauth_authorization_updated::create([
            'context' => \context_system::instance(),
            'userid' => $userid,
            'other' => ['decision' => 'approved'],
        ])->trigger();
        return self::callback_url($record, ['code' => $code, 'state' => $record->state]);
    }

    /**
     * Deny an authorization request.
     *
     * @param string $request Opaque request token.
     * @return string StudyStash callback URL.
     */
    public static function deny(string $request): string {
        global $DB;

        $record = self::get_request($request);
        $record->usedat = time();
        $DB->update_record('local_studystash_oauth', $record);
        \local_studystash\event\oauth_authorization_updated::create([
            'context' => \context_system::instance(),
            'other' => ['decision' => 'denied'],
        ])->trigger();
        return self::callback_url($record, ['error' => 'access_denied', 'state' => $record->state]);
    }

    /**
     * Exchange an authorization code for user-scoped tokens.
     *
     * @param array $input Token exchange data.
     * @return array Issued token pair.
     */
    public static function exchange(array $input): array {
        global $DB;

        self::require_enabled();
        $code = trim((string) ($input['code'] ?? ''));
        $verifier = trim((string) ($input['code_verifier'] ?? ''));
        $redirecturi = trim((string) ($input['redirect_uri'] ?? ''));
        if (
            !preg_match('/^[A-Za-z0-9_-]{64}$/', $code)
                || !preg_match('/^[A-Za-z0-9_-]{43,128}$/', $verifier)
        ) {
            api::error('invalid_grant', 'The authorization code is invalid or expired.', 401);
        }
        $transaction = $DB->start_delegated_transaction();
        $record = $DB->get_record_sql(
            'SELECT * FROM {local_studystash_oauth} WHERE codehash = :codehash FOR UPDATE',
            ['codehash' => hash('sha256', $code)]
        );
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        if (
            !$record || $record->usedat || !$record->userid || $record->timeexpires <= time()
                || !hash_equals($record->redirecturi, $redirecturi)
                || !hash_equals($record->codechallenge, $challenge)
        ) {
            $transaction->allow_commit();
            api::error('invalid_grant', 'The authorization code is invalid or expired.', 401);
        }

        $user = $DB->get_record('user', ['id' => $record->userid, 'deleted' => 0, 'suspended' => 0]);
        if (!$user || isguestuser($user)) {
            $transaction->allow_commit();
            api::error('invalid_user', 'A guest or inactive Moodle account cannot be connected.', 403);
        }

        $record->usedat = time();
        $DB->update_record('local_studystash_oauth', $record);
        $pair = token_service::issue_oauth_pair((int) $record->userid);
        $transaction->allow_commit();
        \local_studystash\event\user_grant_updated::create([
            'context' => \context_system::instance(),
            'userid' => 0,
            'relateduserid' => $record->userid,
            'other' => ['action' => 'issued', 'granttype' => 'oauth'],
        ])->trigger();
        return ['moodle_user_id' => (string) $record->userid] + $pair;
    }

    /**
     * Require OAuth access to be enabled.
     */
    private static function require_enabled(): void {
        if (!get_config('local_studystash', 'oauthenabled')) {
            api::error('oauth_access_disabled', 'StudyStash OAuth access is disabled.', 403);
        }
    }

    /**
     * Check that a callback URI is supported.
     *
     * @param string $value Callback URI.
     * @return bool Whether the URI is valid.
     */
    private static function valid_redirect_uri(string $value): bool {
        $parts = parse_url($value);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }
        if ($parts['scheme'] === 'https') {
            return true;
        }
        return $parts['scheme'] === 'http' && in_array(strtolower($parts['host']), ['localhost', '127.0.0.1', '::1'], true);
    }

    /**
     * Append OAuth parameters to a callback URI.
     *
     * @param \stdClass $record Authorization request record.
     * @param array $params Query parameters.
     * @return string Callback URL.
     */
    private static function callback_url(\stdClass $record, array $params): string {
        $url = new \moodle_url($record->redirecturi);
        $url->params($params);
        return $url->out(false);
    }

    /**
     * Generate a cryptographically secure opaque token.
     *
     * @param int $bytes Number of random bytes.
     * @return string URL-safe token.
     */
    private static function random_token(int $bytes): string {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }
}
