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

/**
 * Helper library for local_wub_policy.
 *
 * @package    local_wub_policy
 * @copyright  2026 WUB eLearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Check if the policy for a given role has been accepted in the current session.
 *
 * @param string $role Role name (student, teacher, admin)
 * @return bool True if accepted in current session.
 */
function wub_policy_is_accepted(string $role): bool {
    global $SESSION;

    if (!isset($SESSION->wub_policy_accepted) || !is_array($SESSION->wub_policy_accepted)) {
        return false;
    }

    return !empty($SESSION->wub_policy_accepted[$role]);
}

/**
 * Record policy acceptance for a given role in the current session.
 *
 * @param string $role Role name (student, teacher, admin)
 */
function wub_policy_record_acceptance(string $role): void {
    global $SESSION;

    if (!isset($SESSION->wub_policy_accepted) || !is_array($SESSION->wub_policy_accepted)) {
        $SESSION->wub_policy_accepted = [];
    }

    $SESSION->wub_policy_accepted[$role] = true;
}
