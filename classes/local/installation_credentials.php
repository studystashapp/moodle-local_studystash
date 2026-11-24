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
 * Manages the installation pairing credentials.
 */
final class installation_credentials {
    /** Plugin component name. */
    private const COMPONENT = 'local_studystash';

    /**
     * Get the public installation identifier.
     *
     * @return string Public installation identifier.
     */
    public static function id(): string {
        return (string) get_config(self::COMPONENT, 'installationid');
    }

    /**
     * Get the decrypted installation secret.
     *
     * @return string Decrypted installation secret.
     */
    public static function secret(): string {
        $stored = (string) get_config(self::COMPONENT, 'installationsecret');
        if ($stored === '') {
            return '';
        }
        return str_starts_with($stored, 'sodium:') ? \core\encryption::decrypt($stored) : $stored;
    }

    /**
     * Get the masked secret suffix.
     *
     * @return string Last four characters of the installation secret.
     */
    public static function secret_last_four(): string {
        $stored = (string) get_config(self::COMPONENT, 'installationsecretlastfour');
        return $stored !== '' ? $stored : substr(self::secret(), -4);
    }

    /**
     * Check whether the secret can still be revealed.
     *
     * @return bool Whether the secret can still be revealed.
     */
    public static function can_reveal(): bool {
        return !get_config(self::COMPONENT, 'installationsecretrevealed');
    }

    /**
     * Reveal the installation secret once.
     *
     * @return null|string Secret when available for its one-time reveal.
     */
    public static function reveal_once(): ?string {
        if (!self::can_reveal()) {
            return null;
        }
        set_config('installationsecretrevealed', 1, self::COMPONENT);
        return self::secret();
    }

    /**
     * Rotate the installation secret.
     *
     * @return string Newly generated installation secret.
     */
    public static function rotate(): string {
        $secret = bin2hex(random_bytes(32));
        self::store_secret($secret, true);
        return $secret;
    }

    /**
     * Encrypt and persist an installation secret.
     *
     * @param string $secret Plain-text secret.
     * @param bool $revealed Whether the secret has already been revealed.
     */
    public static function store_secret(string $secret, bool $revealed): void {
        set_config('installationsecret', \core\encryption::encrypt($secret), self::COMPONENT);
        set_config('installationsecretlastfour', substr($secret, -4), self::COMPONENT);
        set_config('installationsecretrevealed', $revealed ? 1 : 0, self::COMPONENT);
    }
}
