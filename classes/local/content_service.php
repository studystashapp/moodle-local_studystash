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
 * Provides user-scoped Moodle course and file content.
 */
final class content_service {
    /** Supported Moodle file components and areas. */
    private const SUPPORTED_FILE_AREAS = [
        'mod_resource' => ['content'],
        'mod_folder' => ['content'],
    ];

    /**
     * List courses visible to a Moodle user.
     *
     * @param int $userid Moodle user ID.
     * @return array Visible courses.
     */
    public static function courses(int $userid): array {
        $courses = enrol_get_users_courses($userid, true, 'id,fullname,shortname,visible,startdate,enddate');
        $result = [];
        foreach ($courses as $course) {
            if ((int) $course->id === SITEID) {
                continue;
            }
            $context = \context_course::instance($course->id);
            if (!$course->visible && !has_capability('moodle/course:viewhiddencourses', $context, $userid)) {
                continue;
            }
            $result[] = [
                'id' => (string) $course->id,
                'name' => format_string($course->fullname, true, ['context' => $context]),
                'short_name' => format_string($course->shortname, true, ['context' => $context]),
                'start_at' => $course->startdate ? gmdate(DATE_ATOM, $course->startdate) : null,
                'end_at' => $course->enddate ? gmdate(DATE_ATOM, $course->enddate) : null,
            ];
        }
        usort($result, fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));
        return $result;
    }

    /**
     * List visible sections, modules, and files in a course.
     *
     * @param int $courseid Moodle course ID.
     * @param int $userid Moodle user ID.
     * @param int|null $sectionid Optional Moodle section ID to expand.
     * @param bool $recursive Whether to include descendant sections.
     * @return array Course content tree.
     */
    public static function course_content(
        int $courseid,
        int $userid,
        ?int $sectionid = null,
        bool $recursive = false
    ): array {
        self::require_course_access($courseid, $userid);
        $course = get_course($courseid);
        $context = \context_course::instance($courseid);
        $modinfo = get_fast_modinfo($course, $userid);
        $subsectionparents = [];
        foreach ($modinfo->get_instances_of('subsection') as $subsection) {
            $subsectionparents[(int) $subsection->instance] = (int) $subsection->sectionnum;
        }
        $visiblesections = [];
        $includedsectionids = [];
        $visiblecmsbysection = [];
        foreach ($modinfo->get_section_info_all() as $sectioninfo) {
            if ($sectioninfo->uservisible) {
                $visiblesections[(int) $sectioninfo->section] = $sectioninfo;
            }
        }
        foreach ($modinfo->get_cms() as $cm) {
            if ($cm->uservisible && self::supports_module($cm) && isset($visiblesections[(int) $cm->sectionnum])) {
                $includedsectionids[(int) $visiblesections[(int) $cm->sectionnum]->id] = true;
                $visiblecmsbysection[(int) $cm->sectionnum][] = $cm;
            }
        }
        do {
            $changed = false;
            foreach ($visiblesections as $sectioninfo) {
                if (!isset($includedsectionids[(int) $sectioninfo->id])) {
                    continue;
                }
                $parentid = self::section_parent_id($modinfo, $sectioninfo, $subsectionparents);
                if ($parentid !== null && !isset($includedsectionids[(int) $parentid])) {
                    $includedsectionids[(int) $parentid] = true;
                    $changed = true;
                }
            }
        } while ($changed);
        $requestedsectionids = [];
        if ($sectionid !== null) {
            $requestedsectionids[$sectionid] = true;
            if ($recursive) {
                do {
                    $changed = false;
                    foreach ($visiblesections as $sectioninfo) {
                        $parentid = self::section_parent_id($modinfo, $sectioninfo, $subsectionparents);
                        if (
                            $parentid !== null && isset($requestedsectionids[(int) $parentid]) &&
                            !isset($requestedsectionids[(int) $sectioninfo->id])
                        ) {
                            $requestedsectionids[(int) $sectioninfo->id] = true;
                            $changed = true;
                        }
                    }
                } while ($changed);
            }
        }
        $sections = [];

        foreach ($visiblesections as $sectioninfo) {
            if ($sectionid === null && !isset($includedsectionids[(int) $sectioninfo->id])) {
                continue;
            }
            if (
                $sectionid !== null && (!isset($requestedsectionids[(int) $sectioninfo->id]) ||
                !isset($includedsectionids[(int) $sectioninfo->id]))
            ) {
                continue;
            }
            $parentid = self::section_parent_id($modinfo, $sectioninfo, $subsectionparents);
            $modules = [];
            if ($sectionid !== null || $parentid === null) {
                foreach ($visiblecmsbysection[(int) $sectioninfo->section] ?? [] as $cm) {
                    $files = self::module_files($cm);
                    if (!$files) {
                        continue;
                    }
                    $modules[] = self::module_result($cm, $files);
                }
            }
            $sections[] = [
                'id' => (string) $sectioninfo->id,
                'parent_id' => $parentid,
                'name' => get_section_name($course, $sectioninfo),
                'modules' => $modules,
            ];
        }

        return [
            'id' => (string) $course->id,
            'name' => format_string($course->fullname, true, ['context' => $context]),
            'sections' => $sections,
        ];
    }

    /**
     * Search visible course files without returning the whole content tree.
     *
     * @param int $courseid Moodle course ID.
     * @param int $userid Moodle user ID.
     * @param string $query Normalized search query.
     * @param int $offset Number of matching files to skip.
     * @param int $limit Maximum matching files to return.
     * @return array Paginated course content tree.
     */
    public static function search_course_content(
        int $courseid,
        int $userid,
        string $query,
        int $offset,
        int $limit
    ): array {
        self::require_course_access($courseid, $userid);
        $course = get_course($courseid);
        $context = \context_course::instance($courseid);
        $modinfo = get_fast_modinfo($course, $userid);
        $sectionsbynumber = [];
        $sectionsbyid = [];
        $subsectionparents = [];
        foreach ($modinfo->get_instances_of('subsection') as $subsection) {
            $subsectionparents[(int) $subsection->instance] = (int) $subsection->sectionnum;
        }
        foreach ($modinfo->get_section_info_all() as $sectioninfo) {
            if ($sectioninfo->uservisible) {
                $sectionsbynumber[(int) $sectioninfo->section] = $sectioninfo;
                $sectionsbyid[(int) $sectioninfo->id] = $sectioninfo;
            }
        }

        $normalizedquery = \core_text::strtolower(trim($query));
        $matches = [];
        $matchedcount = 0;
        $returnedcount = 0;
        $hasmore = false;
        foreach ($modinfo->get_cms() as $cm) {
            $sectioninfo = $sectionsbynumber[(int) $cm->sectionnum] ?? null;
            if (!$sectioninfo || !$cm->uservisible) {
                continue;
            }
            $sectionname = get_section_name($course, $sectioninfo);
            $modulename = format_string($cm->name, true, ['context' => $cm->context]);
            foreach (self::module_files($cm) as $file) {
                $haystack = \core_text::strtolower(implode(' ', [
                    $sectionname,
                    $modulename,
                    $file['file_path'],
                    $file['name'],
                ]));
                if (\core_text::strpos($haystack, $normalizedquery) === false) {
                    continue;
                }
                if ($matchedcount++ < $offset) {
                    continue;
                }
                if ($returnedcount >= $limit) {
                    $hasmore = true;
                    break 2;
                }
                $sectionkey = (int) $sectioninfo->id;
                if (!isset($matches[$sectionkey])) {
                    $matches[$sectionkey] = [
                        'id' => (string) $sectioninfo->id,
                        'parent_id' => self::section_parent_id($modinfo, $sectioninfo, $subsectionparents),
                        'name' => $sectionname,
                        'modules' => [],
                    ];
                }
                $modulekey = (int) $cm->id;
                if (!isset($matches[$sectionkey]['modules'][$modulekey])) {
                    $matches[$sectionkey]['modules'][$modulekey] = self::module_result($cm, []);
                }
                $matches[$sectionkey]['modules'][$modulekey]['files'][] = $file;
                $returnedcount++;
            }
        }
        self::add_parent_sections($matches, $sectionsbyid, $modinfo, $subsectionparents, $course);
        foreach ($matches as &$section) {
            $section['modules'] = array_values($section['modules']);
        }
        unset($section);

        return [
            'course' => [
                'id' => (string) $course->id,
                'name' => format_string($course->fullname, true, ['context' => $context]),
                'sections' => array_values($matches),
            ],
            'next_offset' => $hasmore ? $offset + $returnedcount : null,
        ];
    }

    /**
     * Resolve a file after checking the user's current access.
     *
     * @param int $fileid Moodle file ID.
     * @param int $userid Moodle user ID.
     * @return \stored_file Authorized stored file.
     */
    public static function authorized_file(int $fileid, int $userid): \stored_file {
        $file = get_file_storage()->get_file_by_id($fileid);
        if (!$file || $file->is_directory()) {
            api::error('file_not_found', 'File not found.', 404);
        }
        $areas = self::SUPPORTED_FILE_AREAS[$file->get_component()] ?? [];
        if (!in_array($file->get_filearea(), $areas, true)) {
            api::error('unsupported_file', 'This Moodle file area is not available to StudyStash.', 403);
        }
        $context = \context::instance_by_id($file->get_contextid(), MUST_EXIST);
        if ($context->contextlevel !== CONTEXT_MODULE) {
            api::error('invalid_file_context', 'The file is not attached to a course module.', 403);
        }
        $cm = get_coursemodule_from_id('', $context->instanceid, 0, false, MUST_EXIST);
        self::require_course_access((int) $cm->course, $userid);
        $modinfo = get_fast_modinfo((int) $cm->course, $userid);
        $cmaccess = $modinfo->get_cm($cm->id);
        if (!$cmaccess->uservisible) {
            api::error('file_not_available', 'The file is not visible to this user.', 403);
        }
        return $file;
    }

    /**
     * List supported files attached to a course module.
     *
     * @param \cm_info $cm Course module information.
     * @return array Supported files.
     */
    private static function module_files(\cm_info $cm): array {
        $areas = self::SUPPORTED_FILE_AREAS['mod_' . $cm->modname] ?? [];
        if (!$areas) {
            return [];
        }
        $files = [];
        foreach ($areas as $filearea) {
            $storedfiles = get_file_storage()->get_area_files(
                $cm->context->id,
                'mod_' . $cm->modname,
                $filearea,
                0,
                'filename ASC',
                false
            );
            foreach ($storedfiles as $file) {
                $files[] = [
                    'id' => (string) $file->get_id(),
                    'name' => $file->get_filename(),
                    'file_path' => $file->get_filepath(),
                    'mime_type' => $file->get_mimetype(),
                    'size_bytes' => (int) $file->get_filesize(),
                    'modified_at' => gmdate(DATE_ATOM, $file->get_timemodified()),
                ];
            }
        }
        return $files;
    }

    /**
     * Check whether a module can expose files to StudyStash.
     *
     * @param \cm_info $cm Course module information.
     * @return bool
     */
    private static function supports_module(\cm_info $cm): bool {
        return !empty(self::SUPPORTED_FILE_AREAS['mod_' . $cm->modname]);
    }

    /**
     * Add missing ancestor sections so search results retain the course hierarchy.
     *
     * @param array $matches Matched sections keyed by section ID.
     * @param array $sectionsbyid Visible sections keyed by section ID.
     * @param \course_modinfo $modinfo Course module information.
     * @param array $subsectionparents Subsection instance IDs mapped to parent section numbers.
     * @param \stdClass $course Moodle course.
     */
    private static function add_parent_sections(
        array &$matches,
        array $sectionsbyid,
        \course_modinfo $modinfo,
        array $subsectionparents,
        \stdClass $course
    ): void {
        $sectionids = array_keys($matches);
        foreach ($sectionids as $sectionid) {
            $sectioninfo = $sectionsbyid[$sectionid] ?? null;
            while ($sectioninfo) {
                $parentid = self::section_parent_id($modinfo, $sectioninfo, $subsectionparents);
                if ($parentid === null) {
                    break;
                }
                $parentinfo = $sectionsbyid[(int) $parentid] ?? null;
                if (!$parentinfo) {
                    break;
                }
                if (!isset($matches[(int) $parentid])) {
                    $matches[(int) $parentid] = [
                        'id' => (string) $parentinfo->id,
                        'parent_id' => self::section_parent_id($modinfo, $parentinfo, $subsectionparents),
                        'name' => get_section_name($course, $parentinfo),
                        'modules' => [],
                    ];
                }
                $sectioninfo = $parentinfo;
            }
        }

        uksort($matches, static function (int $left, int $right) use ($sectionsbyid): int {
            return ((int) $sectionsbyid[$left]->section) <=> ((int) $sectionsbyid[$right]->section);
        });
    }

    /**
     * Build a module response.
     *
     * @param \cm_info $cm Course module information.
     * @param array $files Module files.
     * @return array Module response.
     */
    private static function module_result(\cm_info $cm, array $files): array {
        return [
            'id' => (string) $cm->id,
            'name' => format_string($cm->name, true, ['context' => $cm->context]),
            'type' => $cm->modname,
            'files' => $files,
        ];
    }

    /**
     * Resolve the parent course section for a delegated subsection.
     *
     * @param \course_modinfo $modinfo Course module information.
     * @param \section_info $sectioninfo Current section information.
     * @param array $subsectionparents Subsection instance IDs mapped to parent section numbers.
     * @return string|null Parent section ID.
     */
    private static function section_parent_id(
        \course_modinfo $modinfo,
        \section_info $sectioninfo,
        array $subsectionparents
    ): ?string {
        if ($sectioninfo->component !== 'mod_subsection' || !$sectioninfo->itemid) {
            return null;
        }
        $parentsectionnum = $subsectionparents[(int) $sectioninfo->itemid] ?? null;
        if ($parentsectionnum === null) {
            return null;
        }
        $parentsection = $modinfo->get_section_info($parentsectionnum);
        return $parentsection ? (string) $parentsection->id : null;
    }

    /**
     * Require active enrolment and course visibility.
     *
     * @param int $courseid Moodle course ID.
     * @param int $userid Moodle user ID.
     */
    private static function require_course_access(int $courseid, int $userid): void {
        $context = \context_course::instance($courseid, IGNORE_MISSING);
        if (!$context || !is_enrolled($context, $userid, '', true)) {
            api::error('course_not_available', 'The course is not available to this user.', 403);
        }
        $course = get_course($courseid);
        if (!$course->visible && !has_capability('moodle/course:viewhiddencourses', $context, $userid)) {
            api::error('course_not_available', 'The course is not available to this user.', 403);
        }
    }
}
