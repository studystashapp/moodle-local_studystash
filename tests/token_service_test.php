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

use local_studystash\local\token_service;

/**
 * Tests user-scoped token lifecycle controls.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(token_service::class)]
final class token_service_test extends \advanced_testcase {
    /**
     * Refresh rotation revokes the previous token family before committing a replacement.
     */
    public function test_refresh_rotates_the_active_token_pair(): void {
        global $DB;

        $this->resetAfterTest(true);
        set_config('oauthenabled', 1, 'local_studystash');
        $user = $this->getDataGenerator()->create_user();
        $original = token_service::issue_oauth_pair((int) $user->id);
        $link = $DB->get_record('local_studystash_links', ['userid' => $user->id, 'granttype' => 'oauth']);
        $this->assertNotFalse($link);
        $legacyrefreshid = $DB->insert_record('local_studystash_tokens', (object) [
            'linkid' => $link->id,
            'tokentype' => 'refresh',
            'tokenhash' => hash('sha256', 'legacy-concurrent-refresh-token'),
            'timeexpires' => time() + DAYSECS,
            'revokedat' => null,
            'timecreated' => time(),
        ]);

        $replacement = token_service::refresh($original['refresh_token']);

        $oldrefresh = $DB->get_record('local_studystash_tokens', [
            'tokenhash' => hash('sha256', $original['refresh_token']),
        ]);
        $legacyrefresh = $DB->get_record('local_studystash_tokens', ['id' => $legacyrefreshid]);
        $this->assertNotFalse($oldrefresh);
        $this->assertNotFalse($legacyrefresh);
        $this->assertNotSame($original['access_token'], $replacement['access_token']);
        $this->assertNotSame($original['refresh_token'], $replacement['refresh_token']);
        $this->assertNotNull($oldrefresh->revokedat);
        $this->assertNotNull($legacyrefresh->revokedat);
        $this->assertSame(1, $DB->count_records('local_studystash_tokens', [
            'linkid' => $link->id,
            'tokentype' => 'access',
            'revokedat' => null,
        ]));
        $this->assertSame(1, $DB->count_records('local_studystash_tokens', [
            'linkid' => $link->id,
            'tokentype' => 'refresh',
            'revokedat' => null,
        ]));
    }
}
