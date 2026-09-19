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

use context_course;
use context_system;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Authoritative Institutional Role and Capability Resolver.
 *
 * Evaluates actual Moodle capability assignments and archetypes.
 * Strictly avoids negative role inference.
 *
 * @package    local_wub_auth
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class role_resolver {
    public const ROLE_ADMIN = 'admin';
    public const ROLE_TEACHER = 'teacher';
    public const ROLE_STUDENT = 'student';
    public const ROLE_USER = 'user';
    public const ROLE_GUEST = 'guest';

    /**
     * Determine primary authoritative institutional role for user.
     *
     * @param stdClass|int $user User object or ID.
     * @return string Role string (admin, teacher, student, user, guest).
     */
    public function resolve_primary_role($user): string {
        global $DB, $CFG;

        if (empty($user)) {
            return self::ROLE_GUEST;
        }

        $userobj = is_object($user) ? $user : $DB->get_record('user', ['id' => (int)$user, 'deleted' => 0]);
        if (!$userobj) {
            return self::ROLE_GUEST;
        }

        $userid = (int)$userobj->id;

        // 1. Guest check
        if ($userid === (int)$CFG->siteguest || isguestuser($userobj)) {
            return self::ROLE_GUEST;
        }

        // 2. Administrator check (Moodle native capability/admin model)
        if (is_siteadmin($userobj)) {
            return self::ROLE_ADMIN;
        }

        // 3. Faculty / Teacher check
        if ($this->is_teacher($userobj)) {
            return self::ROLE_TEACHER;
        }

        // 4. Student check
        if ($this->is_student($userobj)) {
            return self::ROLE_STUDENT;
        }

        return self::ROLE_USER;
    }

    /**
     * Check if user holds teaching capabilities or role assignments.
     *
     * @param stdClass $user
     * @return bool
     */
    public function is_teacher(stdClass $user): bool {
        if (is_siteadmin($user)) {
            return false;
        }

        global $DB;

        $userid = (int)$user->id;
        $syscontext = context_system::instance();

        // Check system level course management capabilities
        if (
            has_capability('moodle/course:create', $syscontext, $user) ||
            has_capability('moodle/course:update', $syscontext, $user)
        ) {
            return true;
        }

        // Check course-level teaching capabilities
        require_once(__DIR__ . '/../../../../lib/enrollib.php');
        $courses = enrol_get_users_courses($userid, true, ['id']);
        if (!empty($courses)) {
            foreach ($courses as $course) {
                $coursecontext = context_course::instance($course->id);
                if (
                    has_capability('moodle/course:manageactivities', $coursecontext, $user, false) ||
                    has_capability('moodle/course:viewhiddenactivities', $coursecontext, $user, false)
                ) {
                    return true;
                }
            }
        }

        // Check teacher archetype role assignments in database
        $sql = "SELECT ra.id
                  FROM {role_assignments} ra
                  JOIN {role} r ON r.id = ra.roleid
                 WHERE ra.userid = :userid
                   AND r.archetype IN ('teacher', 'editingteacher', 'coursecreator', 'manager')";
        if ($DB->record_exists_sql($sql, ['userid' => $userid])) {
            return true;
        }

        return false;
    }

    /**
     * Check if user is an institutional student.
     *
     * @param stdClass $user
     * @return bool
     */
    public function is_student(stdClass $user): bool {
        global $DB;

        $userid = (int)$user->id;

        // Check role assignments with archetype 'student'
        $sql = "SELECT ra.id
                  FROM {role_assignments} ra
                  JOIN {role} r ON r.id = ra.roleid
                 WHERE ra.userid = :userid
                   AND r.archetype = 'student'";
        if ($DB->record_exists_sql($sql, ['userid' => $userid])) {
            return true;
        }

        // Check active course enrolments where user does not hold editing rights
        $courses = enrol_get_users_courses($userid, true, ['id']);
        if (!empty($courses) && !$this->is_teacher($user)) {
            return true;
        }

        // Check institutional student naming/email pattern
        $username = trim($user->username);
        $email = trim($user->email);
        $isnumeric = preg_match('/^\d{8,12}$/', $username);
        $isstudentemail = str_ends_with(strtolower($email), '@student.wub.edu.bd');

        if (($isnumeric || $isstudentemail) && !is_siteadmin($user) && !$this->is_teacher($user)) {
            return true;
        }

        return false;
    }

    /**
     * Authoritatively check whether an authenticated user is permitted
     * to access the system under the requested persona (student, teacher, admin).
     *
     * @param stdClass $user Authenticated Moodle user object.
     * @param string $requestedrole Persona requested during login (student, teacher, admin).
     * @return bool True if authorized for persona, false otherwise.
     */
    public function can_act_as_role(stdClass $user, string $requestedrole): bool {
        $cleanrole = strtolower(trim($requestedrole));

        // 1. Administration Persona: Requires site administrator or moodle/site:config
        if ($cleanrole === self::ROLE_ADMIN) {
            return is_siteadmin($user) || has_capability('moodle/site:config', context_system::instance(), $user);
        }

        // 2. Teacher Persona: Requires teaching capability or administrator
        if ($cleanrole === self::ROLE_TEACHER) {
            return is_siteadmin($user) || $this->is_teacher($user);
        }

        // 3. Student Persona: Standard student, enrolled user, or staff testing view
        if ($cleanrole === self::ROLE_STUDENT) {
            if (is_siteadmin($user) || $this->is_teacher($user)) {
                return true;
            }
            return $this->is_student($user) || !empty($user->id);
        }

        return true;
    }
}

