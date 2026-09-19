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

namespace local_wub_auth\service;

use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * ExamController Integration Trust Boundary.
 *
 * Provides a secure cryptographic boundary for verifying student identity
 * and account status with the future ExamController examination platform
 * without exposing Moodle session cookies, passwords, or direct DB access.
 *
 * @package    local_wub_auth
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class integration_service {
    public const DEFAULT_TOKEN_TTL = 300; // 5 minutes

    /**
     * Get integration shared secret.
     *
     * @return string
     */
    protected function get_secret(): string {
        $secret = get_config('local_wub_auth', 'integration_secret');
        if (empty($secret)) {
            // Derive a deterministic installation secret if unconfigured
            global $CFG;
            $secret = hash('sha256', $CFG->passwordsaltmain ?? 'wub_auth_examcontroller_salt');
        }
        return (string)$secret;
    }

    /**
     * Generate a cryptographically signed identity verification token for ExamController.
     *
     * @param stdClass $user Moodle user object.
     * @param int|null $ttl Lifetime in seconds.
     * @return string Base64URL-encoded token with HMAC-SHA256 signature.
     */
    public function generate_verification_token(stdClass $user, ?int $ttl = null): string {
        $lifetime = $ttl ?? (int)get_config('local_wub_auth', 'token_ttl');
        if ($lifetime <= 0) {
            $lifetime = self::DEFAULT_TOKEN_TTL;
        }

        $now = time();
        $claims = [
            'iss' => 'wub_moodle',
            'aud' => 'wub_examcontroller',
            'sub' => (int)$user->id,
            'iat' => $now,
            'exp' => $now + $lifetime,
            'jti' => bin2hex(random_bytes(16)),
            'user' => $this->get_user_claims($user),
        ];

        $payload = $this->base64url_encode((string)json_encode($claims));
        $signature = $this->base64url_encode(hash_hmac('sha256', $payload, $this->get_secret(), true));

        return $payload . '.' . $signature;
    }

    /**
     * Verify and decode a signed ExamController identity token.
     *
     * @param string $token Encoded token string.
     * @return array|null Decoded claims array or null if invalid/expired/tampered.
     */
    public function verify_token(string $token): ?array {
        $parts = explode('.', trim($token));
        if (count($parts) !== 2) {
            return null;
        }

        [$payload, $signature] = $parts;

        // Verify cryptographic signature
        $expected = $this->base64url_encode(hash_hmac('sha256', $payload, $this->get_secret(), true));
        if (!hash_equals($expected, $signature)) {
            return null; // Signature mismatch / tampered
        }

        $json = $this->base64url_decode($payload);
        $claims = json_decode($json, true);
        if (!is_array($claims)) {
            return null;
        }

        // Verify expiration
        $exp = (int)($claims['exp'] ?? 0);
        if ($exp < time()) {
            return null; // Expired
        }

        // Verify issuer and audience
        if (($claims['iss'] ?? '') !== 'wub_moodle') {
            return null;
        }

        return $claims;
    }

    /**
     * Extract clean non-sensitive identity claims for user.
     *
     * @param stdClass $user
     * @return array
     */
    public function get_user_claims(stdClass $user): array {
        return [
            'moodle_userid' => (int)$user->id,
            'username' => (string)$user->username,
            'institutional_id' => (string)($user->idnumber ?: $user->username),
            'email' => (string)$user->email,
            'fullname' => fullname($user),
            'department' => (string)($user->department ?? ''),
            'suspended' => (bool)($user->suspended ?? false),
            'auth' => (string)($user->auth ?? 'manual'),
        ];
    }

    protected function base64url_encode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    protected function base64url_decode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
