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

use local_studystash\local\lti_tool_registry;

/**
 * Tests exact LTI registration connections.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(lti_tool_registry::class)]
final class lti_tool_registry_test extends \advanced_testcase {
    public function test_connects_and_disconnects_an_exact_registration(): void {
        $this->resetAfterTest(true);

        $typeid = $this->insert_lti_type([
            'name' => 'Any external tool name',
            'baseurl' => 'https://example.com/lti/launch',
            'clientid' => 'studystash-client',
        ]);
        $available = lti_tool_registry::statuses([[
            'client_id' => 'studystash-client',
            'deployment_id' => (string) $typeid,
        ]]);
        $connected = lti_tool_registry::set_connection('studystash-client', (string) $typeid, true);

        $this->assertSame('available', $available[0]['status']);
        $this->assertSame('linked', $connected['status']);
        $this->assertTrue(lti_tool_registry::allows('studystash-client', (string) $typeid));
        $this->assertFalse(lti_tool_registry::allows('studystash-client', 'different-deployment'));
        $this->assertFalse(lti_tool_registry::allows('different-client', (string) $typeid));

        $disconnected = lti_tool_registry::set_connection('studystash-client', (string) $typeid, false);
        $this->assertSame('available', $disconnected['status']);
        $this->assertFalse(lti_tool_registry::allows('studystash-client', (string) $typeid));
    }

    public function test_disconnecting_one_registration_preserves_other_connections(): void {
        $this->resetAfterTest(true);
        $firsttypeid = $this->insert_lti_type(['clientid' => 'first-client']);
        $secondtypeid = $this->insert_lti_type(['clientid' => 'second-client']);

        lti_tool_registry::set_connection('first-client', (string) $firsttypeid, true);
        lti_tool_registry::set_connection('second-client', (string) $secondtypeid, true);
        lti_tool_registry::set_connection('first-client', (string) $firsttypeid, false);

        $this->assertFalse(lti_tool_registry::allows('first-client', (string) $firsttypeid));
        $this->assertTrue(lti_tool_registry::allows('second-client', (string) $secondtypeid));
    }

    public function test_does_not_connect_a_registration_missing_from_moodle(): void {
        $this->resetAfterTest(true);
        $result = lti_tool_registry::set_connection('unknown-client', 'unknown-deployment', true);

        $this->assertSame('not_found', $result['status']);
        $this->assertFalse(lti_tool_registry::allows('unknown-client', 'unknown-deployment'));
    }

    /**
     * Insert a Moodle LTI type for a test.
     *
     * @param array $overrides Record value overrides.
     * @return int Inserted type ID.
     */
    private function insert_lti_type(array $overrides): int {
        global $DB;

        $now = time();
        return $DB->insert_record('lti_types', (object) array_merge([
            'name' => 'StudyStash',
            'baseurl' => 'https://studystash.example/lti/launch',
            'tooldomain' => 'studystash.example',
            'state' => 1,
            'course' => 1,
            'coursevisible' => 1,
            'ltiversion' => '1.3.0',
            'clientid' => 'studystash-client',
            'createdby' => 2,
            'timecreated' => $now,
            'timemodified' => $now,
        ], $overrides));
    }
}
