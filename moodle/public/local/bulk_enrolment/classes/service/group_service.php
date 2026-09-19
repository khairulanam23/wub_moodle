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

use context_course;
use moodle_exception;

global $CFG;
require_once($CFG->dirroot . '/group/lib.php');

/**
 * Service for course group resolution, creation, and membership assignment.
 *
 * CRITICAL ARCHITECTURAL CONFORMANCE:
 * - Uses supported Moodle Core Group APIs (groups_get_group_by_name, groups_create_group, groups_add_member).
 * - Never directly writes raw SQL to mdl_groups or mdl_groups_members.
 * - Enforces course-context scoping and capability verification (moodle/course:managegroups).
 * - Only creates missing groups when explicitly permitted by user intent.
 *
 * @package    local_bulk_enrolment
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class group_service {

    /**
     * Resolve or optionally create a course group by name.
     *
     * @param int $courseid Target Moodle Course ID
     * @param string $groupname Desired Group Name
     * @param bool $allowcreate Whether to create the group if it does not exist
     * @return array [
     *     'success' => bool,
     *     'groupid' => int,
     *     'created' => bool,
     *     'error' => string
     * ]
     */
    public function resolve_or_create_group(int $courseid, string $groupname, bool $allowcreate = false): array {
        $cleanName = trim($groupname);
        if (empty($cleanName) || $courseid <= 0) {
            return [
                'success' => false,
                'groupid' => 0,
                'created' => false,
                'error' => 'Invalid course or group parameters.',
            ];
        }

        // 1. Look up existing group by name in course.
        $existingGroupId = groups_get_group_by_name($courseid, $cleanName);
        if ($existingGroupId) {
            return [
                'success' => true,
                'groupid' => (int)$existingGroupId,
                'created' => false,
                'error' => '',
            ];
        }

        // 2. If not found and creation not allowed, return missing error.
        if (!$allowcreate) {
            return [
                'success' => false,
                'groupid' => 0,
                'created' => false,
                'error' => "Group '{$cleanName}' does not exist in course ID {$courseid}.",
            ];
        }

        // 3. Verify group creation capability in course context.
        $context = context_course::instance($courseid);
        if (!has_capability('moodle/course:managegroups', $context)) {
            return [
                'success' => false,
                'groupid' => 0,
                'created' => false,
                'error' => 'Permission denied: Cannot create groups in this course.',
            ];
        }

        // 4. Create group via core API.
        $groupdata = (object)[
            'courseid' => $courseid,
            'name' => $cleanName,
            'description' => 'Automatically created via WUB Bulk Enrolment.',
            'descriptionformat' => FORMAT_PLAIN,
        ];

        try {
            $newGroupId = groups_create_group($groupdata);
            return [
                'success' => true,
                'groupid' => (int)$newGroupId,
                'created' => true,
                'error' => '',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'groupid' => 0,
                'created' => false,
                'error' => 'Failed to create group: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Add a student user to a course group.
     *
     * @param int $groupid Target Group ID
     * @param int $userid Target Moodle User ID
     * @return bool
     */
    public function add_member(int $groupid, int $userid): bool {
        if ($groupid <= 0 || $userid <= 0) {
            return false;
        }

        // Check if already a member.
        if (groups_is_member($groupid, $userid)) {
            return true;
        }

        return groups_add_member($groupid, $userid);
    }

    /**
     * Helper to resolve/create a group and add a student member in one operation.
     *
     * @param int $courseid Target Course ID
     * @param string $groupname Target Group Name
     * @param int $userid Target User ID
     * @param bool $allowcreate Whether to auto-create missing group
     * @return array
     */
    public function ensure_group_and_add_member(int $courseid, string $groupname, int $userid, bool $allowcreate = true): array {
        $res = $this->resolve_or_create_group($courseid, $groupname, $allowcreate);
        if (!$res['success'] || $res['groupid'] <= 0) {
            return $res;
        }

        $added = $this->add_member($res['groupid'], $userid);
        return [
            'success' => $added,
            'groupid' => $res['groupid'],
            'created' => $res['created'],
            'error' => $added ? '' : 'Failed to add member to group.',
        ];
    }
}
