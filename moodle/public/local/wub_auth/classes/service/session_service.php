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

use moodle_url;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Secure Session and Safe Redirect Service.
 *
 * Wraps Moodle's native complete_user_login() with session regeneration,
 * remember-me cookie handling, policy binding, and strict open-redirect mitigation.
 *
 * @package    local_wub_auth
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class session_service {
    protected policy_service $policyservice;

    public function __construct(?policy_service $policyservice = null) {
        $this->policyservice = $policyservice ?? new policy_service();
    }

    /**
     * Establish authenticated Moodle session.
     *
     * @param stdClass $user Moodle user object.
     * @param bool $rememberusername Whether to persist username in cookie.
     */
    public function establish_session(stdClass $user, bool $rememberusername = false): void {
        global $CFG;

        // Log in user through Moodle core authentication API
        complete_user_login($user);

        // Handle remember-me cookie
        if (empty($CFG->nolastloggedin)) {
            if ($rememberusername) {
                set_moodle_cookie($user->username);
            } else {
                set_moodle_cookie('');
            }
        }

        // Bind any pre-login policy acceptance to the newly authenticated user
        $this->policyservice->bind_device_acceptance((int)$user->id);
    }

    /**
     * Safely terminate user session.
     */
    public function terminate_session(): void {
        global $SESSION;

        // Clear plugin-specific session variables
        unset($SESSION->wub_intended_role);
        unset($SESSION->wub_return_url);
        unset($SESSION->wub_due_warning);

        require_logout();
    }

    /**
     * Check if a redirect destination is safe against open redirect injection.
     *
     * @param string|null $url
     * @return bool
     */
    public function is_safe_redirect(?string $url): bool {
        global $CFG;

        if (empty($url)) {
            return false;
        }

        $trimmed = trim($url);

        // Block protocol-relative URLs (//evil.com)
        if (str_starts_with($trimmed, '//')) {
            return false;
        }

        // Block javascript: or data: URIs
        if (preg_match('/^(javascript|data|vbscript):/i', $trimmed)) {
            return false;
        }

        // Allow relative internal paths (/my/, /course/view.php?id=2)
        if (str_starts_with($trimmed, '/') && !str_starts_with($trimmed, '/\\')) {
            return true;
        }

        // If absolute URL, ensure it belongs to this Moodle site
        $wwwroot = rtrim($CFG->wwwroot, '/');
        if (str_starts_with($trimmed, $wwwroot)) {
            return true;
        }

        return false;
    }

    /**
     * Resolve a safe redirect destination.
     *
     * @param string|null $returnurl User-provided return URL.
     * @param string $default Fallback destination (e.g. '/my/').
     * @return moodle_url
     */
    public function get_safe_redirect_url(?string $returnurl, string $default = '/my/'): moodle_url {
        if (!empty($returnurl) && $this->is_safe_redirect($returnurl)) {
            return new moodle_url($returnurl);
        }
        return new moodle_url($default);
    }
}
