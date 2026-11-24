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
 * Manages short-lived file-scoped import grants.
 */
final class import_service {
    /** Import grant lifetime in seconds. */
    private const GRANT_LIFETIME = DAYSECS;
    /** Maximum files permitted in one grant. */
    private const MAX_FILES = 40;

    /**
     * Create an import grant for authorized files.
     *
     * @param array $input Request data.
     * @return array Import token and expiry.
     */
    public static function create(array $input): array {
        global $DB;

        $accesstoken = trim((string) ($input['access_token'] ?? ''));
        $fileids = self::normalize_file_ids($input['file_ids'] ?? null);
        $context = token_service::authenticate_access_token_context($accesstoken, 'import_create');
        $files = [];
        foreach ($fileids as $fileid) {
            $file = content_service::authorized_file($fileid, (int) $context['user']->id);
            $files[] = [
                'id' => (string) $file->get_id(),
                'file_name' => $file->get_filename(),
                'mime_type' => $file->get_mimetype() ?? 'application/octet-stream',
                'size_bytes' => (int) $file->get_filesize(),
                'modified_at' => gmdate('c', $file->get_timemodified()),
            ];
        }

        $token = self::random_token();
        $now = time();
        $grantid = $DB->insert_record('local_studystash_imports', (object) [
            'linkid' => $context['linkid'],
            'tokenhash' => hash('sha256', $token),
            'fileids' => json_encode($fileids),
            'timeexpires' => $now + self::GRANT_LIFETIME,
            'revokedat' => null,
            'timecreated' => $now,
        ]);

        \local_studystash\event\import_grant_updated::create([
            'context' => \context_system::instance(),
            'objectid' => $grantid,
            'userid' => 0,
            'relateduserid' => $context['user']->id,
            'other' => ['action' => 'created', 'filecount' => count($fileids)],
        ])->trigger();

        return [
            'import_token' => $token,
            'expires_in' => self::GRANT_LIFETIME,
            'files' => $files,
        ];
    }

    /**
     * Resolve a file authorized by an import grant.
     *
     * @param string $token Import token.
     * @param int $fileid Moodle file ID.
     * @return \stored_file Authorized stored file.
     */
    public static function authorized_file(string $token, int $fileid): \stored_file {
        global $DB;

        if (!preg_match('/^[A-Za-z0-9_-]{64}$/', $token)) {
            api::error('invalid_import_grant', 'The import grant is invalid or expired.', 401);
        }
        $grant = $DB->get_record('local_studystash_imports', [
            'tokenhash' => hash('sha256', $token),
        ]);
        if (!$grant || $grant->revokedat || $grant->timeexpires <= time()) {
            api::error('invalid_import_grant', 'The import grant is invalid or expired.', 401);
        }
        rate_limiter::enforce_link('import_file', (int) $grant->linkid);
        $fileids = json_decode((string) $grant->fileids, true);
        if (!is_array($fileids) || !in_array($fileid, $fileids, true)) {
            api::error('file_not_granted', 'This file is not included in the import grant.', 403);
        }

        $user = token_service::activate_linked_user_for_link((int) $grant->linkid);
        $file = content_service::authorized_file($fileid, (int) $user->id);
        \local_studystash\event\file_exported::create([
            'context' => \context::instance_by_id($file->get_contextid(), MUST_EXIST),
            'objectid' => $file->get_id(),
            'userid' => 0,
            'relateduserid' => $user->id,
            'other' => [
                'importgrantid' => $grant->id,
                'filesize' => $file->get_filesize(),
                'mimetype' => $file->get_mimetype() ?? 'application/octet-stream',
            ],
        ])->trigger();
        return $file;
    }

    /**
     * Revoke an import grant.
     *
     * @param string $token Import token.
     */
    public static function revoke(string $token): void {
        global $DB;

        if (!preg_match('/^[A-Za-z0-9_-]{64}$/', $token)) {
            return;
        }
        $grant = $DB->get_record('local_studystash_imports', [
            'tokenhash' => hash('sha256', $token),
            'revokedat' => null,
        ]);
        if (!$grant) {
            return;
        }
        $DB->set_field('local_studystash_imports', 'revokedat', time(), ['id' => $grant->id]);
        $link = $DB->get_record('local_studystash_links', ['id' => $grant->linkid]);
        if ($link) {
            $fileids = json_decode((string) $grant->fileids, true);
            \local_studystash\event\import_grant_updated::create([
                'context' => \context_system::instance(),
                'objectid' => $grant->id,
                'userid' => 0,
                'relateduserid' => $link->userid,
                'other' => ['action' => 'revoked', 'filecount' => is_array($fileids) ? count($fileids) : 0],
            ])->trigger();
        }
    }

    /**
     * Validate and normalize requested file IDs.
     *
     * @param mixed $value Raw file ID list.
     * @return array Normalized unique file IDs.
     */
    private static function normalize_file_ids(mixed $value): array {
        if (!is_array($value) || !$value || count($value) > self::MAX_FILES) {
            api::error('invalid_files', 'Select between 1 and 40 files to import.', 400);
        }
        $fileids = [];
        foreach ($value as $fileid) {
            if (!is_int($fileid) && !(is_string($fileid) && ctype_digit($fileid))) {
                api::error('invalid_files', 'Every imported file must have a valid Moodle file ID.', 400);
            }
            $normalized = (int) $fileid;
            if ($normalized < 1) {
                api::error('invalid_files', 'Every imported file must have a valid Moodle file ID.', 400);
            }
            $fileids[$normalized] = $normalized;
        }
        return array_values($fileids);
    }

    /**
     * Generate a cryptographically secure opaque token.
     *
     * @return string Cryptographically secure opaque token.
     */
    private static function random_token(): string {
        return rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    }
}
