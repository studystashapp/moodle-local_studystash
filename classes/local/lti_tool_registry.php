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
 * Synchronizes and validates StudyStash LTI registrations.
 */
final class lti_tool_registry {
    /** Configuration key for synchronized registrations. */
    private const CONFIG_KEY = 'ltiregistrations';

    /**
     * Get synchronized LTI registrations.
     *
     * @return array Synchronized LTI registrations.
     */
    public static function registrations(): array {
        $active = self::active_registrations();
        $synced = self::synced_pairs();

        return array_values(array_filter($active, static function (\stdClass $registration) use ($synced): bool {
            return isset($synced[self::pair_key($registration->clientid, $registration->deploymentid)]);
        }));
    }

    /**
     * Report connection status for StudyStash registrations.
     *
     * @param array $candidates Client and deployment pairs.
     * @return array Status for each pair.
     */
    public static function statuses(array $candidates): array {
        $active = self::active_registration_map();
        $linked = self::synced_pairs();
        $results = [];
        foreach (array_slice($candidates, 0, 100) as $candidate) {
            if (!is_array($candidate)) {
                $results[] = [
                    'client_id' => '',
                    'deployment_id' => '',
                    'status' => 'incomplete',
                ];
                continue;
            }
            $clientid = clean_param($candidate['client_id'] ?? '', PARAM_TEXT);
            $deploymentid = clean_param($candidate['deployment_id'] ?? '', PARAM_TEXT);
            if ($clientid === '' || $deploymentid === '') {
                $results[] = [
                    'client_id' => $clientid,
                    'deployment_id' => $deploymentid,
                    'status' => 'incomplete',
                ];
                continue;
            }

            $key = self::pair_key($clientid, $deploymentid);
            $registration = $active[$key] ?? null;
            $results[] = [
                'client_id' => $clientid,
                'deployment_id' => $deploymentid,
                'status' => !$registration ? 'not_found' : (isset($linked[$key]) ? 'linked' : 'available'),
                'tool_name' => $registration?->name,
            ];
        }
        return $results;
    }

    /**
     * Connect or disconnect one StudyStash registration.
     *
     * @param string $clientid LTI client ID.
     * @param string $deploymentid LTI deployment ID.
     * @param bool $connected Whether the registration should be connected.
     * @return array Updated registration status.
     */
    public static function set_connection(string $clientid, string $deploymentid, bool $connected): array {
        $clientid = clean_param($clientid, PARAM_TEXT);
        $deploymentid = clean_param($deploymentid, PARAM_TEXT);
        if ($clientid === '' || $deploymentid === '') {
            return [
                'client_id' => $clientid,
                'deployment_id' => $deploymentid,
                'status' => 'incomplete',
            ];
        }

        $key = self::pair_key($clientid, $deploymentid);
        $active = self::active_registration_map();
        $registration = $active[$key] ?? null;
        $linked = self::synced_registrations();
        $wasconnected = isset($linked[$key]);
        if ($connected && !$registration) {
            return [
                'client_id' => $clientid,
                'deployment_id' => $deploymentid,
                'status' => 'not_found',
            ];
        }

        if ($connected) {
            $linked[$key] = ['client_id' => $clientid, 'deployment_id' => $deploymentid];
        } else {
            unset($linked[$key]);
        }
        set_config(self::CONFIG_KEY, json_encode(array_values($linked)), 'local_studystash');

        if ($registration && $wasconnected !== $connected) {
            \local_studystash\event\lti_connection_updated::create([
                'context' => \context_system::instance(),
                'userid' => 0,
                'other' => [
                    'clientid' => $clientid,
                    'deploymentid' => $deploymentid,
                    'connected' => $connected,
                ],
            ])->trigger();
        }

        return [
            'client_id' => $clientid,
            'deployment_id' => $deploymentid,
            'status' => !$registration ? 'not_found' : ($connected ? 'linked' : 'available'),
            'tool_name' => $registration?->name,
        ];
    }

    /**
     * Check whether an exact client and deployment pair is allowed.
     *
     * @param string $clientid LTI client ID.
     * @param string $deploymentid LTI deployment ID.
     * @return bool Whether the pair is allowed.
     */
    public static function allows(string $clientid, string $deploymentid): bool {
        foreach (self::registrations() as $registration) {
            if (
                hash_equals($registration->clientid, $clientid)
                    && hash_equals($registration->deploymentid, $deploymentid)
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * List active Moodle LTI registrations.
     *
     * @return array Active Moodle LTI registrations.
     */
    private static function active_registrations(): array {
        global $DB;

        $registrations = [];
        $tools = $DB->get_records(
            'lti_types',
            ['state' => 1],
            'name ASC',
            'id,name,baseurl,ltiversion,clientid'
        );

        foreach ($tools as $tool) {
            if ((string) $tool->clientid === '' || (string) $tool->ltiversion !== '1.3.0') {
                continue;
            }
            foreach (self::deployment_ids((int) $tool->id) as $deploymentid) {
                $registrations[] = (object) [
                    'name' => (string) $tool->name,
                    'clientid' => (string) $tool->clientid,
                    'deploymentid' => $deploymentid,
                    'launchurl' => (string) $tool->baseurl,
                ];
            }
        }

        return $registrations;
    }

    /**
     * Map active registrations by exact client and deployment pair.
     *
     * @return array Active registration map.
     */
    private static function active_registration_map(): array {
        $active = [];
        foreach (self::active_registrations() as $registration) {
            $active[self::pair_key($registration->clientid, $registration->deploymentid)] = $registration;
        }
        return $active;
    }

    /**
     * Get synchronized client and deployment pairs.
     *
     * @return array Synchronized client and deployment pairs.
     */
    private static function synced_pairs(): array {
        return array_fill_keys(array_keys(self::synced_registrations()), true);
    }

    /**
     * Get synchronized registration data keyed by exact pair.
     *
     * @return array Synchronized registrations.
     */
    private static function synced_registrations(): array {
        $decoded = json_decode((string) get_config('local_studystash', self::CONFIG_KEY), true);
        if (!is_array($decoded)) {
            return [];
        }

        $pairs = [];
        foreach ($decoded as $registration) {
            if (!is_array($registration)) {
                continue;
            }
            $clientid = clean_param($registration['client_id'] ?? '', PARAM_TEXT);
            $deploymentid = clean_param($registration['deployment_id'] ?? '', PARAM_TEXT);
            if ($clientid !== '' && $deploymentid !== '') {
                $pairs[self::pair_key($clientid, $deploymentid)] = [
                    'client_id' => $clientid,
                    'deployment_id' => $deploymentid,
                ];
            }
        }
        return $pairs;
    }

    /**
     * Build a stable registration map key.
     *
     * @param string $clientid LTI client ID.
     * @param string $deploymentid LTI deployment ID.
     * @return string Registration map key.
     */
    private static function pair_key(string $clientid, string $deploymentid): string {
        return hash('sha256', $clientid . "\n" . $deploymentid);
    }

    /**
     * Resolve deployment IDs for a Moodle LTI tool.
     *
     * @param int $typeid Moodle LTI type ID.
     * @return array Deployment IDs.
     */
    private static function deployment_ids(int $typeid): array {
        global $DB;

        $ids = [(string) $typeid];
        $table = new \xmldb_table('lti_deployment');
        if ($DB->get_manager()->table_exists($table)) {
            $ids = array_merge($ids, array_map(
                'strval',
                $DB->get_fieldset_select('lti_deployment', 'deploymentid', 'typeid = ?', [$typeid])
            ));
        }

        return array_values(array_unique(array_filter($ids)));
    }
}
