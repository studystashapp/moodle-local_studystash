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

namespace local_studystash\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\plugin\provider as request_provider;
use core_privacy\local\metadata\provider as metadata_provider;

/**
 * Privacy API provider for StudyStash user data.
 */
class provider implements metadata_provider, request_provider {
    /**
     * Describe personal data stored by the plugin.
     *
     * @param collection $collection Metadata collection.
     * @return collection Updated metadata collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->link_external_location('studystash', [
            'userid' => 'privacy:metadata:studystash:userid',
            'ltiidentity' => 'privacy:metadata:studystash:ltiidentity',
            'grantcredentials' => 'privacy:metadata:studystash:grantcredentials',
            'coursemetadata' => 'privacy:metadata:studystash:coursemetadata',
            'contentmetadata' => 'privacy:metadata:studystash:contentmetadata',
            'filecontent' => 'privacy:metadata:studystash:filecontent',
        ], 'privacy:metadata:studystash');
        $collection->add_database_table('local_studystash_links', [
            'userid' => 'privacy:metadata:local_studystash_links:userid',
            'issuer' => 'privacy:metadata:local_studystash_links:issuer',
            'deploymentid' => 'privacy:metadata:local_studystash_links:deploymentid',
            'clientid' => 'privacy:metadata:local_studystash_links:clientid',
            'subject' => 'privacy:metadata:local_studystash_links:subject',
        ], 'privacy:metadata:local_studystash_links');
        $collection->add_database_table('local_studystash_tokens', [
            'tokenhash' => 'privacy:metadata:local_studystash_tokens',
        ], 'privacy:metadata:local_studystash_tokens');
        $collection->add_database_table('local_studystash_imports', [
            'linkid' => 'privacy:metadata:local_studystash_imports:linkid',
            'fileids' => 'privacy:metadata:local_studystash_imports:fileids',
        ], 'privacy:metadata:local_studystash_imports');
        $collection->add_database_table('local_studystash_oauth', [
            'userid' => 'privacy:metadata:local_studystash_oauth:userid',
        ], 'privacy:metadata:local_studystash_oauth');
        return $collection;
    }

    /**
     * List contexts containing data for a user.
     *
     * @param int $userid Moodle user ID.
     * @return contextlist Contexts containing user data.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        global $DB;
        $hasuserdata = $DB->record_exists('local_studystash_links', ['userid' => $userid])
            || $DB->record_exists('local_studystash_oauth', ['userid' => $userid]);
        if ($hasuserdata) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * Export plugin data for a user.
     *
     * @param approved_contextlist $contextlist Approved export contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        if (!$contextlist->get_contexts()) {
            return;
        }
        $links = $DB->get_records('local_studystash_links', ['userid' => $contextlist->get_user()->id]);
        $tokens = [];
        $imports = [];
        if ($links) {
            $linkids = array_keys($links);
            $tokens = $DB->get_records_list('local_studystash_tokens', 'linkid', $linkids);
            $imports = $DB->get_records_list('local_studystash_imports', 'linkid', $linkids);
        }
        $oauthrequests = $DB->get_records('local_studystash_oauth', [
            'userid' => $contextlist->get_user()->id,
        ]);
        \core_privacy\local\request\writer::with_context(\context_system::instance())
            ->export_data([get_string('pluginname', 'local_studystash')], (object) [
                'links' => array_values($links),
                'tokens' => array_values($tokens),
                'imports' => array_values($imports),
                'oauthrequests' => array_values($oauthrequests),
            ]);
    }

    /**
     * Delete all plugin data in a context.
     *
     * @param \context $context Context to delete data from.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        $DB->delete_records('local_studystash_imports');
        $DB->delete_records('local_studystash_tokens');
        $DB->delete_records('local_studystash_oauth');
        $DB->delete_records('local_studystash_links');
    }

    /**
     * Delete plugin data for a user.
     *
     * @param approved_contextlist $contextlist Approved deletion contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        if (!$contextlist->get_contexts()) {
            return;
        }
        $links = $DB->get_records('local_studystash_links', ['userid' => $contextlist->get_user()->id], '', 'id');
        if ($links) {
            $DB->delete_records_list('local_studystash_imports', 'linkid', array_keys($links));
            $DB->delete_records_list('local_studystash_tokens', 'linkid', array_keys($links));
            $DB->delete_records_list('local_studystash_links', 'id', array_keys($links));
        }
        $DB->delete_records('local_studystash_oauth', ['userid' => $contextlist->get_user()->id]);
    }
}
