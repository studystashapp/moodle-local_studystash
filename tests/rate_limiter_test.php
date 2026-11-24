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

use local_studystash\local\installation_credentials;
use local_studystash\local\rate_limiter;

/**
 * Tests authenticated API request limits.
 */
#[\PHPUnit\Framework\Attributes\CoversClass(rate_limiter::class)]
final class rate_limiter_test extends \advanced_testcase {
    /**
     * The configured allowance is enforced without persisting the subject.
     */
    public function test_limit_uses_an_unlinkable_bucket_key(): void {
        global $DB;

        $this->resetAfterTest(true);
        installation_credentials::store_secret(str_repeat('a', 64), true);

        $this->assertNull(rate_limiter::consume('test', 'link', 'sensitive-subject', 2, MINSECS));
        $this->assertNull(rate_limiter::consume('test', 'link', 'sensitive-subject', 2, MINSECS));
        $this->assertGreaterThan(0, rate_limiter::consume('test', 'link', 'sensitive-subject', 2, MINSECS));

        $record = $DB->get_record('local_studystash_limits', [], '*', MUST_EXIST);
        $this->assertSame(64, strlen($record->bucketkey));
        $this->assertStringNotContainsString('sensitive-subject', $record->bucketkey);
        $this->assertSame(3, (int) $record->requestcount);
    }

    /**
     * Subjects and operation scopes receive independent allowances.
     */
    public function test_subjects_and_scopes_are_isolated(): void {
        global $DB;

        $this->resetAfterTest(true);
        installation_credentials::store_secret(str_repeat('b', 64), true);

        rate_limiter::consume('courses', 'link', '1', 1, MINSECS);
        rate_limiter::consume('courses', 'link', '2', 1, MINSECS);
        rate_limiter::consume('files', 'link', '1', 1, MINSECS);

        $this->assertSame(3, $DB->count_records('local_studystash_limits'));
    }
}
