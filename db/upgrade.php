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

/**
 * Upgrade the StudyStash plugin database schema.
 *
 * @param int $oldversion Previously installed plugin version.
 * @return bool Always true.
 */
function xmldb_local_studystash_upgrade(int $oldversion): bool {
    global $DB;
    if ($oldversion < 2026081300) {
        $storedsecret = (string) get_config('local_studystash', 'installationsecret');
        $secret = str_starts_with($storedsecret, 'sodium:') ? \core\encryption::decrypt($storedsecret) : $storedsecret;
        if ($secret !== '' && !str_starts_with($storedsecret, 'sodium:')) {
            set_config('installationsecret', \core\encryption::encrypt($secret), 'local_studystash');
        }
        if ($secret !== '') {
            set_config('installationsecretlastfour', substr($secret, -4), 'local_studystash');
        }
        set_config('installationsecretrevealed', 1, 'local_studystash');
        upgrade_plugin_savepoint(true, 2026081300, 'local', 'studystash');
    }
    if ($oldversion < 2026081301) {
        set_config('ltienabled', get_config('local_studystash', 'enabled') ? 1 : 0, 'local_studystash');
        set_config('ltiregistrations', '[]', 'local_studystash');
        upgrade_plugin_savepoint(true, 2026081301, 'local', 'studystash');
    }
    if ($oldversion < 2026081400) {
        $dbman = $DB->get_manager();
        $linktable = new xmldb_table('local_studystash_links');
        $granttype = new xmldb_field('granttype', XMLDB_TYPE_CHAR, '16', null, XMLDB_NOTNULL, null, 'lti', 'subject');
        if (!$dbman->field_exists($linktable, $granttype)) {
            $dbman->add_field($linktable, $granttype);
        }

        $oauthtable = new xmldb_table('local_studystash_oauth');
        if (!$dbman->table_exists($oauthtable)) {
            $oauthtable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $oauthtable->add_field('requesthash', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL);
            $oauthtable->add_field('codehash', XMLDB_TYPE_CHAR, '64');
            $oauthtable->add_field('userid', XMLDB_TYPE_INTEGER, '10');
            $oauthtable->add_field('redirecturi', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
            $oauthtable->add_field('state', XMLDB_TYPE_CHAR, '128', null, XMLDB_NOTNULL);
            $oauthtable->add_field('codechallenge', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL);
            $oauthtable->add_field('timeexpires', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $oauthtable->add_field('usedat', XMLDB_TYPE_INTEGER, '10');
            $oauthtable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $oauthtable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $oauthtable->add_key('useridfk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $oauthtable->add_index('requesthashuniq', XMLDB_INDEX_UNIQUE, ['requesthash']);
            $oauthtable->add_index('codehashuniq', XMLDB_INDEX_UNIQUE, ['codehash']);
            $oauthtable->add_index('oauthexpiryidx', XMLDB_INDEX_NOTUNIQUE, ['timeexpires']);
            $dbman->create_table($oauthtable);
        }
        set_config('oauthenabled', 0, 'local_studystash');
        upgrade_plugin_savepoint(true, 2026081400, 'local', 'studystash');
    }
    if ($oldversion < 2026081401) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_studystash_imports');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('linkid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('tokenhash', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL);
            $table->add_field('fileids', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
            $table->add_field('timeexpires', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('revokedat', XMLDB_TYPE_INTEGER, '10');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('linkidfk', XMLDB_KEY_FOREIGN, ['linkid'], 'local_studystash_links', ['id']);
            $table->add_index('tokenhashuniq', XMLDB_INDEX_UNIQUE, ['tokenhash']);
            $table->add_index('expiryidx', XMLDB_INDEX_NOTUNIQUE, ['timeexpires']);
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026081401, 'local', 'studystash');
    }
    if ($oldversion < 2026081402) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_studystash_links');
        $field = new xmldb_field('clientid', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, 'legacy', 'issuer');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026081402, 'local', 'studystash');
    }
    if ($oldversion < 2026081403) {
        unset_config('ltienabled', 'local_studystash');
        upgrade_plugin_savepoint(true, 2026081403, 'local', 'studystash');
    }
    if ($oldversion < 2026082400) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_studystash_limits');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('bucketkey', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL);
            $table->add_field('requestcount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('timeexpires', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('bucketkeyuniq', XMLDB_INDEX_UNIQUE, ['bucketkey']);
            $table->add_index('limitexpiryidx', XMLDB_INDEX_NOTUNIQUE, ['timeexpires']);
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026082400, 'local', 'studystash');
    }
    return true;
}
