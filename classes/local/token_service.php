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

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use mod_lti\local\ltiopenid\jwks_helper;

/**
 * Issues and validates user-scoped StudyStash tokens.
 */
final class token_service {
    /** Access token lifetime in seconds. */
    private const ACCESS_LIFETIME = 900;
    /** Refresh token lifetime in seconds. */
    private const REFRESH_LIFETIME = 2592000;
    /** Maximum seconds to wait for another rotation of the same linked identity. */
    private const REFRESH_LOCK_TIMEOUT = 10;

    /**
     * Exchange a verified LTI identity for tokens.
     *
     * @param array $input LTI exchange data.
     * @return array Issued token pair.
     */
    public static function exchange_lti(array $input): array {
        global $DB;

        $claims = isset($input['id_token'])
            ? self::verify_lti_launch($input['id_token'])
            : self::verify_trusted_launch($input['verified_claims'] ?? null);
        $issuer = $claims['issuer'];
        $clientid = $claims['clientid'];
        $deploymentid = $claims['deploymentid'];
        $subject = $claims['subject'];
        $userid = $claims['userid'];

        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0, 'suspended' => 0]);
        if (!$user || isguestuser($user)) {
            api::error('invalid_user', 'The Moodle user is not active.', 403);
        }

        $identity = [
            'issuer' => $issuer,
            'clientid' => $clientid,
            'deploymentid' => $deploymentid,
            'subject' => $subject,
            'granttype' => 'lti',
            'identityhash' => hash('sha256', implode("\n", [$issuer, $clientid, $deploymentid, $subject])),
        ];
        $link = $DB->get_record('local_studystash_links', ['identityhash' => $identity['identityhash']]);
        $grantcreated = !$link || $link->revokedat !== null;
        $now = time();
        if ($link) {
            $link->userid = $userid;
            $link->clientid = $clientid;
            $link->granttype = 'lti';
            $link->revokedat = null;
            $link->timemodified = $now;
            $DB->update_record('local_studystash_links', $link);
        } else {
            $link = (object) ($identity + [
                'userid' => $userid,
                'revokedat' => null,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
            $link->id = $DB->insert_record('local_studystash_links', $link);
        }

        $DB->set_field('local_studystash_tokens', 'revokedat', $now, ['linkid' => $link->id, 'revokedat' => null]);
        $pair = self::issue_pair($link->id);
        if ($grantcreated) {
            \local_studystash\event\user_grant_updated::create([
                'context' => \context_system::instance(),
                'userid' => 0,
                'relateduserid' => $userid,
                'other' => ['action' => 'issued', 'granttype' => 'lti'],
            ])->trigger();
        }
        return $pair;
    }

    /**
     * Issue an OAuth token pair for a Moodle user.
     *
     * @param int $userid Moodle user ID.
     * @return array Issued token pair.
     */
    public static function issue_oauth_pair(int $userid): array {
        global $CFG, $DB;

        $user = $DB->get_record('user', ['id' => $userid, 'deleted' => 0, 'suspended' => 0]);
        if (!$user || isguestuser($user)) {
            api::error('invalid_user', 'The Moodle user is not active.', 403);
        }
        $issuer = rtrim($CFG->wwwroot, '/');
        $identity = [
            'issuer' => $issuer,
            'clientid' => '',
            'deploymentid' => 'oauth',
            'subject' => (string) $userid,
            'granttype' => 'oauth',
            'identityhash' => hash('sha256', implode("\n", [$issuer, 'oauth', (string) $userid])),
        ];
        $link = $DB->get_record('local_studystash_links', ['identityhash' => $identity['identityhash']]);
        $now = time();
        if ($link) {
            $link->userid = $userid;
            $link->granttype = 'oauth';
            $link->revokedat = null;
            $link->timemodified = $now;
            $DB->update_record('local_studystash_links', $link);
        } else {
            $link = (object) ($identity + [
                'userid' => $userid,
                'revokedat' => null,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
            $link->id = $DB->insert_record('local_studystash_links', $link);
        }
        $DB->set_field('local_studystash_tokens', 'revokedat', $now, ['linkid' => $link->id, 'revokedat' => null]);
        return self::issue_pair($link->id);
    }

    /**
     * Rotate a refresh token and issue a new token pair.
     *
     * @param string $refreshtoken Refresh token.
     * @return array Issued token pair.
     */
    public static function refresh(string $refreshtoken): array {
        global $DB;

        if (!preg_match('/^[A-Za-z0-9_-]{64}$/', $refreshtoken)) {
            api::error('invalid_token', 'Invalid token.', 401);
        }

        $record = self::find_token($refreshtoken, 'refresh');
        self::require_link_access((int) $record->linkid);
        self::require_active_linked_user((int) $record->linkid);
        $tokenhash = hash('sha256', $refreshtoken);
        $lockfactory = \core\lock\lock_config::get_lock_factory('local_studystash');
        $lock = $lockfactory->get_lock('refresh_link_' . $record->linkid, self::REFRESH_LOCK_TIMEOUT);
        if (!$lock) {
            api::error('refresh_in_progress', 'This token is already being refreshed.', 409);
        }

        $pair = null;
        try {
            // Re-read after acquiring the lock so a concurrent rotation cannot reuse the token.
            $record = $DB->get_record('local_studystash_tokens', [
                'tokenhash' => $tokenhash,
                'tokentype' => 'refresh',
            ]);
            if ($record && !$record->revokedat && $record->timeexpires > time()) {
                $transaction = $DB->start_delegated_transaction();
                try {
                    $now = time();
                    $DB->set_field('local_studystash_tokens', 'revokedat', $now, [
                        'linkid' => $record->linkid,
                        'revokedat' => null,
                    ]);
                    $pair = self::issue_pair($record->linkid);
                    $transaction->allow_commit();
                } catch (\Throwable $error) {
                    $transaction->rollback($error);
                }
            }
        } finally {
            $lock->release();
        }

        if ($pair === null) {
            api::error('invalid_token', 'The token is invalid or expired.', 401);
        }
        return $pair;
    }

    /**
     * Authenticate the access token in the current request.
     *
     * @param string $scope Rate-limit operation scope.
     * @return \stdClass Authenticated Moodle user.
     */
    public static function authenticate_access_token(string $scope = 'user_content'): \stdClass {
        $header = trim((string) (
            $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? ''
        ));
        $token = trim((string) ($_SERVER['HTTP_X_STUDYSTASH_ACCESS_TOKEN'] ?? ''));
        if ($token === '' && preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            $token = $matches[1];
        }
        if ($token === '') {
            api::error('missing_token', 'A bearer access token is required.', 401);
        }
        return self::authenticate_access_token_context($token, $scope)['user'];
    }

    /**
     * Authenticate an access token and return its link context.
     *
     * @param string $token Access token.
     * @param string $scope Rate-limit operation scope.
     * @return array Link ID and Moodle user.
     */
    public static function authenticate_access_token_context(string $token, string $scope = 'user_content'): array {
        $record = self::find_token($token, 'access');
        rate_limiter::enforce_link($scope, (int) $record->linkid);
        return [
            'linkid' => (int) $record->linkid,
            'user' => self::activate_linked_user_for_link((int) $record->linkid),
        ];
    }

    /**
     * Activate the user linked to a grant.
     *
     * @param int $linkid Linked identity record ID.
     * @return \stdClass Active Moodle user.
     */
    public static function activate_linked_user_for_link(int $linkid): \stdClass {
        self::require_link_access($linkid);
        return self::activate_linked_user($linkid);
    }

    /**
     * Revoke the link associated with a refresh token.
     *
     * @param string $refreshtoken Refresh token.
     */
    public static function revoke_link(string $refreshtoken): void {
        global $DB;
        $record = self::find_token($refreshtoken, 'refresh');
        $link = $DB->get_record('local_studystash_links', ['id' => $record->linkid], '*', MUST_EXIST);
        self::revoke_link_by_id((int) $record->linkid);
        \local_studystash\event\user_grant_updated::create([
            'context' => \context_system::instance(),
            'userid' => 0,
            'relateduserid' => $link->userid,
            'other' => ['action' => 'revoked', 'granttype' => $link->granttype],
        ])->trigger();
    }

    /**
     * Revoke a linked identity and all active tokens.
     *
     * @param int $linkid Linked identity record ID.
     */
    private static function revoke_link_by_id(int $linkid): void {
        global $DB;
        $now = time();
        $DB->set_field('local_studystash_links', 'revokedat', $now, ['id' => $linkid]);
        $DB->set_field('local_studystash_tokens', 'revokedat', $now, ['linkid' => $linkid, 'revokedat' => null]);
    }

    /**
     * Issue access and refresh tokens for a linked identity.
     *
     * @param int $linkid Linked identity record ID.
     * @return array Issued token pair.
     */
    private static function issue_pair(int $linkid): array {
        $access = self::issue_token($linkid, 'access', self::ACCESS_LIFETIME);
        $refresh = self::issue_token($linkid, 'refresh', self::REFRESH_LIFETIME);
        return [
            'token_type' => 'Bearer',
            'access_token' => $access,
            'expires_in' => self::ACCESS_LIFETIME,
            'refresh_token' => $refresh,
            'refresh_expires_in' => self::REFRESH_LIFETIME,
        ];
    }

    /**
     * Require the access mode for a linked identity to remain enabled.
     *
     * @param int $linkid Linked identity record ID.
     */
    private static function require_link_access(int $linkid): void {
        global $DB;

        $link = $DB->get_record('local_studystash_links', ['id' => $linkid, 'revokedat' => null]);
        if (!$link) {
            api::error('invalid_grant', 'The linked account has been revoked.', 401);
        }
        $granttype = $link->granttype ?? 'lti';
        if ($granttype === 'oauth' && !get_config('local_studystash', 'oauthenabled')) {
            api::error('oauth_access_disabled', 'StudyStash OAuth access is disabled.', 403);
        }
        if ($granttype !== 'oauth' && !lti_tool_registry::allows((string) $link->clientid, $link->deploymentid)) {
            api::error('lti_access_disabled', 'The StudyStash LTI deployment is no longer linked.', 403);
        }
    }

    /**
     * Issue and persist an opaque token.
     *
     * @param int $linkid Linked identity record ID.
     * @param string $type Token type.
     * @param int $lifetime Token lifetime in seconds.
     * @return string Opaque token.
     */
    private static function issue_token(int $linkid, string $type, int $lifetime): string {
        global $DB;
        $token = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        $DB->insert_record('local_studystash_tokens', (object) [
            'linkid' => $linkid,
            'tokentype' => $type,
            'tokenhash' => hash('sha256', $token),
            'timeexpires' => time() + $lifetime,
            'revokedat' => null,
            'timecreated' => time(),
        ]);
        return $token;
    }

    /**
     * Find a valid token record.
     *
     * @param string $token Opaque token.
     * @param string $type Expected token type.
     * @return \stdClass Token record.
     */
    private static function find_token(string $token, string $type): \stdClass {
        global $DB;
        if (!preg_match('/^[A-Za-z0-9_-]{64}$/', $token)) {
            api::error('invalid_token', 'Invalid token.', 401);
        }
        $record = $DB->get_record('local_studystash_tokens', [
            'tokenhash' => hash('sha256', $token),
            'tokentype' => $type,
        ]);
        if (!$record || $record->revokedat || $record->timeexpires <= time()) {
            api::error('invalid_token', 'The token is invalid or expired.', 401);
        }
        return $record;
    }

    /**
     * Activate a linked Moodle user in the current session.
     *
     * @param int $linkid Linked identity record ID.
     * @return \stdClass Active Moodle user.
     */
    private static function activate_linked_user(int $linkid): \stdClass {
        $user = self::require_active_linked_user($linkid);
        \core\session\manager::set_user($user);
        return $user;
    }

    /**
     * Require the linked Moodle user to remain active.
     *
     * @param int $linkid Linked identity record ID.
     * @return \stdClass Active Moodle user.
     */
    private static function require_active_linked_user(int $linkid): \stdClass {
        global $DB;
        $link = $DB->get_record('local_studystash_links', ['id' => $linkid, 'revokedat' => null]);
        if (!$link) {
            api::error('invalid_grant', 'The linked account has been revoked.', 401);
        }
        $user = $DB->get_record('user', ['id' => $link->userid, 'deleted' => 0, 'suspended' => 0]);
        if (!$user || isguestuser($user)) {
            self::revoke_link_by_id($linkid);
            api::error('invalid_user', 'The linked Moodle user is not active.', 403);
        }
        return $user;
    }

    /**
     * Verify a Moodle-issued LTI launch token.
     *
     * @param mixed $idtoken Raw ID token.
     * @return array Verified identity claims.
     */
    private static function verify_lti_launch(mixed $idtoken): array {
        global $CFG;

        if (!is_string($idtoken) || strlen($idtoken) > 16384 || substr_count($idtoken, '.') !== 2) {
            api::error('invalid_lti_token', 'A valid LTI id_token is required.', 400);
        }
        try {
            $decoded = (array) JWT::decode($idtoken, JWK::parseKeySet(jwks_helper::get_jwks()));
        } catch (\Throwable $error) {
            api::error('invalid_lti_token', 'The LTI launch token could not be verified.', 403);
        }

        $issuer = rtrim(clean_param($decoded['iss'] ?? '', PARAM_URL), '/');
        $subject = clean_param($decoded['sub'] ?? '', PARAM_TEXT);
        $deploymentclaim = 'https://purl.imsglobal.org/spec/lti/claim/deployment_id';
        $messageclaim = 'https://purl.imsglobal.org/spec/lti/claim/message_type';
        $customclaim = 'https://purl.imsglobal.org/spec/lti/claim/custom';
        $deploymentid = clean_param($decoded[$deploymentclaim] ?? '', PARAM_TEXT);
        $custom = is_object($decoded[$customclaim] ?? null)
            ? (array) $decoded[$customclaim]
            : ($decoded[$customclaim] ?? []);
        $userid = clean_param(is_array($custom) ? ($custom['moodle_user_id'] ?? 0) : 0, PARAM_INT);

        $audiences = is_array($decoded['aud'] ?? null) ? $decoded['aud'] : [$decoded['aud'] ?? ''];
        $clientid = count($audiences) === 1 ? (string) $audiences[0] : (string) ($decoded['azp'] ?? '');

        if ($issuer !== rtrim($CFG->wwwroot, '/')) {
            api::error('invalid_issuer', 'The LTI issuer does not match this Moodle site.', 403);
        }
        if ($subject === '' || !$userid) {
            api::error('invalid_lti_identity', 'The LTI launch does not contain a Moodle user identity.', 403);
        }
        if (($decoded[$messageclaim] ?? '') !== 'LtiResourceLinkRequest') {
            api::error('invalid_lti_message', 'The token is not an LTI resource-link launch.', 403);
        }
        if (!in_array($clientid, $audiences, true) || !lti_tool_registry::allows($clientid, $deploymentid)) {
            api::error('invalid_client', 'The LTI token was not issued for an allowed StudyStash client.', 403);
        }

        return [
            'issuer' => $issuer,
            'clientid' => $clientid,
            'deploymentid' => $deploymentid,
            'subject' => $subject,
            'userid' => $userid,
        ];
    }

    /**
     * Verify an installation-signed LTI claim envelope.
     *
     * @param mixed $claims Raw verified claims.
     * @return array Verified identity claims.
     */
    private static function verify_trusted_launch(mixed $claims): array {
        global $CFG;

        if (!is_array($claims)) {
            api::error('invalid_lti_claims', 'Verified LTI claims are required.', 400);
        }

        $issuer = rtrim(clean_param($claims['issuer'] ?? '', PARAM_URL), '/');
        $deploymentid = clean_param($claims['deployment_id'] ?? '', PARAM_TEXT);
        $subject = clean_param($claims['subject'] ?? '', PARAM_TEXT);
        $clientid = clean_param($claims['client_id'] ?? '', PARAM_TEXT);
        $userid = clean_param($claims['moodle_user_id'] ?? 0, PARAM_INT);

        if ($issuer !== rtrim($CFG->wwwroot, '/')) {
            api::error('invalid_issuer', 'The LTI issuer does not match this Moodle site.', 403);
        }
        if ($subject === '' || !$userid) {
            api::error('invalid_lti_identity', 'The LTI launch does not contain a Moodle user identity.', 403);
        }
        if (!lti_tool_registry::allows($clientid, $deploymentid)) {
            api::error('invalid_client', 'The LTI launch was not verified for an allowed StudyStash client.', 403);
        }

        return [
            'issuer' => $issuer,
            'clientid' => $clientid,
            'deploymentid' => $deploymentid,
            'subject' => $subject,
            'userid' => $userid,
        ];
    }
}
