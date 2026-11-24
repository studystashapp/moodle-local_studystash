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

use local_studystash\event\oauth_authorization_updated;
use local_studystash\event\user_grant_updated;
use local_studystash\local\oauth_service;

/**
 * Tests Moodle account authorization.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(oauth_service::class)]
final class oauth_service_test extends \advanced_testcase {
    public function test_authorization_code_exchange_issues_user_scoped_tokens(): void {
        global $DB;

        $this->resetAfterTest(true);
        set_config('enabled', 1, 'local_studystash');
        set_config('oauthenabled', 1, 'local_studystash');
        $user = $this->getDataGenerator()->create_user();
        $verifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $sink = $this->redirectEvents();

        $created = oauth_service::create_request([
            'redirect_uri' => 'https://studystash.example/api/lms/moodle/oauth/callback',
            'state' => rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='),
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]);
        parse_str((string) parse_url($created['authorization_url'], PHP_URL_QUERY), $query);
        $callback = oauth_service::approve($query['request'], (int) $user->id);
        parse_str((string) parse_url($callback, PHP_URL_QUERY), $callbackquery);
        $pair = oauth_service::exchange([
            'code' => $callbackquery['code'],
            'code_verifier' => $verifier,
            'redirect_uri' => 'https://studystash.example/api/lms/moodle/oauth/callback',
        ]);

        $this->assertSame((string) $user->id, $pair['moodle_user_id']);
        $this->assertSame('Bearer', $pair['token_type']);
        $this->assertSame(64, strlen($pair['access_token']));
        $this->assertSame(64, strlen($pair['refresh_token']));
        $link = $DB->get_record('local_studystash_links', ['userid' => $user->id, 'granttype' => 'oauth']);
        $this->assertNotFalse($link);
        $this->assertSame(2, $DB->count_records('local_studystash_tokens', ['linkid' => $link->id]));
        $events = $sink->get_events();
        $this->assertTrue($this->contains_event($events, oauth_authorization_updated::class));
        $this->assertTrue($this->contains_event($events, user_grant_updated::class));
    }

    public function test_guest_user_cannot_approve_connection(): void {
        $this->resetAfterTest(true);
        set_config('enabled', 1, 'local_studystash');
        set_config('oauthenabled', 1, 'local_studystash');
        $created = oauth_service::create_request([
            'redirect_uri' => 'https://studystash.example/api/lms/moodle/oauth/callback',
            'state' => str_repeat('s', 32),
            'code_challenge' => str_repeat('c', 43),
            'code_challenge_method' => 'S256',
        ]);
        parse_str((string) parse_url($created['authorization_url'], PHP_URL_QUERY), $query);

        $this->expectException(\moodle_exception::class);
        oauth_service::approve($query['request'], (int) guest_user()->id);
    }

    /**
     * Check whether an event list contains an event class.
     *
     * @param array $events Captured Moodle events.
     * @param string $class Event class name.
     * @return bool Whether the event was captured.
     */
    private function contains_event(array $events, string $class): bool {
        foreach ($events as $event) {
            if ($event instanceof $class) {
                return true;
            }
        }
        return false;
    }
}
