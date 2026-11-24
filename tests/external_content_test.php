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

use local_studystash\external\get_course_content;
use local_studystash\external\list_courses;
use local_studystash\external\search_course_content;

/**
 * Tests the typed content operation contracts.
 *
 * @covers \local_studystash\external\get_course_content
 * @covers \local_studystash\external\list_courses
 * @covers \local_studystash\external\search_course_content
 */
final class external_content_test extends \advanced_testcase {
    /**
     * The course-list contract preserves the existing payload.
     */
    public function test_list_courses_return_contract(): void {
        $response = [
            'courses' => [[
                'id' => '42',
                'name' => 'Foundations of Biology',
                'short_name' => 'BIO101',
                'start_at' => null,
                'end_at' => '2026-12-18T00:00:00+00:00',
            ]],
        ];

        $clean = list_courses::clean_returnvalue(list_courses::execute_returns(), $response);

        $this->assertSame($response, $clean);
    }

    /**
     * The course-content contract preserves the nested content tree.
     */
    public function test_course_content_return_contract(): void {
        $response = [
            'course' => [
                'id' => '42',
                'name' => 'Foundations of Biology',
                'sections' => [[
                    'id' => '7',
                    'parent_id' => null,
                    'name' => 'Week 1 - Cells and microscopy',
                    'modules' => [[
                        'id' => '11',
                        'name' => 'Lecture 1 - Cell structure and function',
                        'type' => 'resource',
                        'files' => [[
                            'id' => '99',
                            'name' => 'cell-structure.pdf',
                            'file_path' => '/',
                            'mime_type' => 'application/pdf',
                            'size_bytes' => 1024,
                            'modified_at' => '2026-08-17T12:00:00+00:00',
                        ]],
                    ]],
                ]],
            ],
        ];

        $clean = get_course_content::clean_returnvalue(get_course_content::execute_returns(), $response);

        $this->assertSame($response, $clean);
    }

    /**
     * The course search contract preserves the paginated content tree.
     */
    public function test_course_search_return_contract(): void {
        $response = [
            'course' => [
                'id' => '42',
                'name' => 'Foundations of Biology',
                'sections' => [],
            ],
            'next_offset' => 40,
        ];

        $clean = search_course_content::clean_returnvalue(search_course_content::execute_returns(), $response);

        $this->assertSame($response, $clean);
    }

    /**
     * Course search rejects query strings that could cause excessive processing.
     */
    public function test_course_search_rejects_oversized_queries(): void {
        $this->expectException(\invalid_parameter_exception::class);
        search_course_content::execute(SITEID, str_repeat('a', 201));
    }

    /**
     * Course search rejects offsets that require excessive repeated traversal.
     */
    public function test_course_search_rejects_oversized_offsets(): void {
        $this->expectException(\invalid_parameter_exception::class);
        search_course_content::execute(SITEID, 'biology', 1001);
    }
}
