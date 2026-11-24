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
 * Shared helpers for the plugin HTTP API.
 */
final class api {
    /**
     * Require the installation to be enabled.
     */
    public static function require_enabled(): void {
        if (!get_config('local_studystash', 'enabled')) {
            self::error('integration_disabled', 'StudyStash content access is disabled.', 503);
        }
    }

    /**
     * Decode the current JSON request body.
     *
     * @return array Decoded request data.
     */
    public static function json_body(): array {
        return self::decode_json(file_get_contents('php://input') ?: '');
    }

    /**
     * Decode a JSON request body that has already been read for signature verification.
     *
     * @param string $raw Raw request body.
     * @return array Decoded request data.
     */
    public static function decode_json(string $raw): array {
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            self::error('invalid_json', 'A JSON request body is required.', 400);
        }
        return $data;
    }

    /**
     * Read the current request body without decoding it.
     *
     * @return string Raw request body.
     */
    public static function raw_body(): string {
        return file_get_contents('php://input') ?: '';
    }

    /**
     * Send a JSON response and terminate the request.
     *
     * @param array $data Response payload.
     * @param int $status HTTP status code.
     */
    public static function respond(array $data, int $status = 200): never {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Validate an operation result against its declared return structure and respond.
     *
     * @param string $operation External API operation class name.
     * @param array $data Operation result.
     */
    public static function respond_external(string $operation, array $data): never {
        self::respond($operation::clean_returnvalue($operation::execute_returns(), $data));
    }

    /**
     * Execute a typed operation and convert parameter errors to the JSON API contract.
     *
     * @param string $operation External API operation class name.
     * @param array $input Request data.
     * @return array Operation result.
     */
    public static function execute_external(string $operation, array $input): array {
        try {
            return $operation::execute($input);
        } catch (\invalid_parameter_exception $error) {
            self::error('invalid_request', 'The request is invalid.', 400);
        }
    }

    /**
     * Send a JSON error response and terminate the request.
     *
     * @param string $code Stable error code.
     * @param string $message Human-readable error message.
     * @param int $status HTTP status code.
     */
    public static function error(string $code, string $message, int $status): never {
        self::respond(['error' => $code, 'message' => $message], $status);
    }

    /**
     * Require the current HTTP request method.
     *
     * @param string $method Expected HTTP method.
     */
    public static function require_method(string $method): void {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
            header('Allow: ' . $method);
            self::error('method_not_allowed', 'Method not allowed.', 405);
        }
    }
}
