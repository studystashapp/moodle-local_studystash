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

use local_studystash\event\configuration_updated;
use local_studystash\event\file_exported;
use local_studystash\event\user_grant_updated;

/**
 * Tests Moodle-native StudyStash audit events.
 */
final class audit_events_test extends \advanced_testcase {
    /**
     * Administrator actions are attributed to the current Moodle user.
     */
    public function test_configuration_event_records_safe_access_state(): void {
        $this->resetAfterTest(true);
        $admin = get_admin();
        $this->setUser($admin);
        $sink = $this->redirectEvents();

        configuration_updated::create([
            'context' => \context_system::instance(),
            'other' => ['enabled' => 1, 'oauthenabled' => 0],
        ])->trigger();

        $event = $sink->get_events()[0];
        $this->assertInstanceOf(configuration_updated::class, $event);
        $this->assertSame((int) $admin->id, (int) $event->userid);
        $this->assertSame(1, $event->other['enabled']);
        $this->assertStringNotContainsString('secret', strtolower(json_encode($event->other)));
    }

    /**
     * Server-side token actions identify the affected Moodle user.
     */
    public function test_grant_event_records_related_user_without_a_token(): void {
        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $sink = $this->redirectEvents();

        user_grant_updated::create([
            'context' => \context_system::instance(),
            'userid' => 0,
            'relateduserid' => $user->id,
            'other' => ['action' => 'issued', 'granttype' => 'oauth'],
        ])->trigger();

        $event = $sink->get_events()[0];
        $this->assertInstanceOf(user_grant_updated::class, $event);
        $this->assertSame((int) $user->id, (int) $event->relateduserid);
        $this->assertSame(['action' => 'issued', 'granttype' => 'oauth'], $event->other);
    }

    /**
     * File exports identify the file, grant, and affected user without exposing credentials.
     */
    public function test_file_export_event_records_safe_transfer_details(): void {
        $this->resetAfterTest(true);
        $user = $this->getDataGenerator()->create_user();
        $sink = $this->redirectEvents();

        file_exported::create([
            'context' => \context_system::instance(),
            'objectid' => 123,
            'userid' => 0,
            'relateduserid' => $user->id,
            'other' => [
                'importgrantid' => 456,
                'filesize' => 1024,
                'mimetype' => 'application/pdf',
            ],
        ])->trigger();

        $event = $sink->get_events()[0];
        $this->assertInstanceOf(file_exported::class, $event);
        $this->assertSame((int) $user->id, (int) $event->relateduserid);
        $this->assertSame(123, (int) $event->objectid);
        $this->assertSame(456, (int) $event->other['importgrantid']);
        $this->assertStringNotContainsString('token', strtolower(json_encode($event->other)));
    }
}
