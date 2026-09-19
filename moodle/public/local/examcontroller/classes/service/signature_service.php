<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_examcontroller\service;

use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Server-to-Server HMAC-SHA256 cryptographic signature service.
 *
 * Enforces:
 * 1. Matching Key ID for rotation.
 * 2. Strict clock-skew tolerance (+/- 300 seconds).
 * 3. Single-use nonces to eliminate replay attacks.
 * 4. Constant-time hash verification.
 *
 * @package    local_examcontroller
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class signature_service {
    public const MAX_CLOCK_SKEW = 300; // 5 minutes

    /**
     * Get configured shared secret.
     *
     * @return string
     */
    public static function get_secret(): string {
        $secret = get_config('local_examcontroller', 'integration_secret');
        if (empty($secret)) {
            // Also check local_wub_auth setting if available
            $secret = get_config('local_wub_auth', 'integration_secret');
        }
        if (empty($secret)) {
            throw new \moodle_exception('error_missing_secret', 'local_examcontroller', '', null, 'ExamController integration shared secret is not configured.');
        }
        return (string)$secret;
    }

    /**
     * Get configured active Key ID.
     *
     * @return string
     */
    public static function get_key_id(): string {
        $kid = get_config('local_examcontroller', 'integration_key_id');
        return !empty($kid) ? (string)$kid : 'default';
    }

    /**
     * Normalize query parameters deterministically for canonical signature.
     *
     * @param array $params
     * @return string
     */
    public static function normalize_query(array $params): string {
        if (empty($params)) {
            return '';
        }
        ksort($params);
        return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Build canonical message string for HMAC-SHA256 signature.
     *
     * @param string $method
     * @param string $path
     * @param string $queryString
     * @param int $timestamp
     * @param string $nonce
     * @param string $rawBody
     * @return string
     */
    public static function get_canonical_string(string $method, string $path, string $queryString, int $timestamp, string $nonce, string $rawBody): string {
        return strtoupper($method) . "\n" . $path . "\n" . $queryString . "\n" . $timestamp . "\n" . $nonce . "\n" . hash('sha256', $rawBody);
    }

    /**
     * Verify incoming request signature and replay protection.
     *
     * @param string $method HTTP method (POST, GET, etc.)
     * @param string $path Requested path / endpoint
     * @param string $rawBody Raw request body string
     * @param array $headers Associative array of HTTP headers
     * @param string $queryString Deterministically normalized query string
     * @return array Result array: ['valid' => bool, 'error' => string|null, 'code' => int]
     */
    public static function verify_request(string $method, string $path, string $rawBody, array $headers, string $queryString = ''): array {
        global $DB;

        // Extract required headers (case-insensitive search)
        $normalizedHeaders = [];
        foreach ($headers as $k => $v) {
            $normalizedHeaders[strtolower(str_replace('_', '-', $k))] = $v;
        }

        $keyId = $normalizedHeaders['x-wub-key-id'] ?? '';
        $timestamp = (int)($normalizedHeaders['x-wub-timestamp'] ?? 0);
        $nonce = trim((string)($normalizedHeaders['x-wub-nonce'] ?? ''));
        $signature = trim((string)($normalizedHeaders['x-wub-signature'] ?? ''));

        if (empty($keyId) || empty($timestamp) || empty($nonce) || empty($signature)) {
            return [
                'valid' => false,
                'error' => 'Missing required cryptographic authentication headers (X-WUB-Key-Id, X-WUB-Timestamp, X-WUB-Nonce, X-WUB-Signature).',
                'code' => 401,
            ];
        }

        // 1. Verify Key ID
        $expectedKeyId = self::get_key_id();
        if ($keyId !== $expectedKeyId) {
            return [
                'valid' => false,
                'error' => 'Invalid or unknown Key ID.',
                'code' => 401,
            ];
        }

        // 2. Clock-skew verification
        $now = time();
        if (abs($now - $timestamp) > self::MAX_CLOCK_SKEW) {
            return [
                'valid' => false,
                'error' => 'Timestamp skew exceeds allowed tolerance (+/- 300s). Server time: ' . $now . ', received: ' . $timestamp,
                'code' => 401,
            ];
        }

        // 3. Replay protection (Check & record nonce)
        $exists = $DB->record_exists('examcontroller_nonces', ['nonce' => $nonce]);
        if ($exists) {
            return [
                'valid' => false,
                'error' => 'Replay attack detected. Nonce has already been consumed.',
                'code' => 401,
            ];
        }

        // 4. Verify HMAC-SHA256 signature
        $secret = self::get_secret();
        $canonicalString = self::get_canonical_string($method, $path, $queryString, $timestamp, $nonce, $rawBody);
        $expectedSignature = hash_hmac('sha256', $canonicalString, $secret);

        if (!hash_equals($expectedSignature, $signature)) {
            return [
                'valid' => false,
                'error' => 'Invalid cryptographic signature.',
                'code' => 401,
            ];
        }

        // Record consumed nonce with expiration (race-safe against concurrent replay)
        try {
            $nonceRecord = new stdClass();
            $nonceRecord->nonce = $nonce;
            $nonceRecord->expires_at = $timestamp + self::MAX_CLOCK_SKEW;
            $nonceRecord->timecreated = $now;
            $DB->insert_record('examcontroller_nonces', $nonceRecord);
        } catch (\dml_exception $e) {
            return [
                'valid' => false,
                'error' => 'Replay attack detected. Nonce has already been consumed.',
                'code' => 401,
            ];
        }

        return ['valid' => true, 'error' => null, 'code' => 200];
    }

    /**
     * Prune expired nonces from the database.
     *
     * @return int Number of expired nonces removed.
     */
    public static function prune_expired_nonces(): int {
        global $DB;
        $now = time();
        $count = $DB->count_records_select('examcontroller_nonces', 'expires_at < :now', ['now' => $now]);
        if ($count > 0) {
            $DB->delete_records_select('examcontroller_nonces', 'expires_at < :now', ['now' => $now]);
        }
        return $count;
    }

    /**
     * Compute signature for an outgoing request (used for testing or outgoing push).
     *
     * @param string $method
     * @param string $path
     * @param string $queryString
     * @param string $rawBody
     * @param int $timestamp
     * @param string $nonce
     * @return string
     */
    public static function sign_request(string $method, string $path, string $queryString, string $rawBody, int $timestamp, string $nonce): string {
        $secret = self::get_secret();
        $canonicalString = self::get_canonical_string($method, $path, $queryString, $timestamp, $nonce, $rawBody);
        return hash_hmac('sha256', $canonicalString, $secret);
    }
}
