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

namespace local_bulk_enrolment\service;

defined('MOODLE_INTERNAL') || die();

use context_system;
use context_course;
use stdClass;

/**
 * Service to enforce Moodle access controls, course-level capabilities, and assignable role rules.
 *
 * CRITICAL SECURITY FIX:
 * Prevents system-level capability escalation by strictly verifying course-context capabilities:
 * - 'enrol/manual:enrol' in context_course
 * - 'moodle/role:assign' in context_course
 *
 * @package    local_bulk_enrolment
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class permission_service {

    /**
     * Check if the current user has global access to the bulk enrolment interface.
     *
     * @param int|null $userid User ID or null for current user
     * @return bool
     */
    public function can_access_ui(?int $userid = null): bool {
        $context = context_system::instance();
        return has_capability('local/bulk_enrolment:view', $context, $userid);
    }

    /**
     * Check if the current user has permission to enrol users into a specific course using manual enrolment.
     *
     * @param int $courseid
     * @param int|null $userid
     * @return bool
     */
    public function can_enrol_in_course(int $courseid, ?int $userid = null): bool {
        if ($courseid <= 0) {
            return false;
        }

        try {
            $context = context_course::instance($courseid);
        } catch (\Throwable $e) {
            return false;
        }

        // Must have permission to manually enrol users in this course context.
        return has_capability('enrol/manual:enrol', $context, $userid);
    }

    /**
     * Check if the current user is permitted to assign the specified role in this course context.
     *
     * @param int $courseid
     * @param int $roleid
     * @param int|null $userid
     * @return bool
     */
    public function can_assign_role_in_course(int $courseid, int $roleid, ?int $userid = null): bool {
        if ($courseid <= 0 || $roleid <= 0) {
            return false;
        }

        try {
            $context = context_course::instance($courseid);
        } catch (\Throwable $e) {
            return false;
        }

        // Must have capability to assign roles in this course context.
        if (!has_capability('moodle/role:assign', $context, $userid)) {
            return false;
        }

        // Role must be in the list of assignable roles for this user in this context.
        $assignableroles = get_assignable_roles($context, ROLENAME_SHORT);
        return array_key_exists($roleid, $assignableroles);
    }

    /**
     * Comprehensive validation of permissions for a course and role.
     *
     * @param int $courseid
     * @param int $roleid
     * @param int|null $userid
     * @return array ['allowed' => bool, 'reason' => string]
     */
    public function validate_enrolment_permission(int $courseid, int $roleid, ?int $userid = null): array {
        if (!$this->can_enrol_in_course($courseid, $userid)) {
            return [
                'allowed' => false,
                'reason' => 'PERMISSION_DENIED',
            ];
        }

        if (!$this->can_assign_role_in_course($courseid, $roleid, $userid)) {
            return [
                'allowed' => false,
                'reason' => 'ROLE_NOT_ASSIGNABLE',
            ];
        }

        return [
            'allowed' => true,
            'reason' => '',
        ];
    }
}
