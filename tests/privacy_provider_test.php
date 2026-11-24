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

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\types\external_location;
use local_studystash\privacy\provider;

/**
 * Tests StudyStash Privacy API metadata.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class privacy_provider_test extends \advanced_testcase {
    /**
     * The provider declares every category of personal data sent to StudyStash.
     */
    public function test_external_data_location_is_declared(): void {
        $items = provider::get_metadata(new collection('local_studystash'))->get_collection();
        $external = array_values(array_filter(
            $items,
            static fn($item): bool => $item instanceof external_location
        ));

        $this->assertCount(1, $external);
        $this->assertSame('studystash', $external[0]->get_name());
        $this->assertEqualsCanonicalizing(
            ['userid', 'ltiidentity', 'grantcredentials', 'coursemetadata', 'contentmetadata', 'filecontent'],
            array_keys($external[0]->get_privacy_fields())
        );
    }
}
