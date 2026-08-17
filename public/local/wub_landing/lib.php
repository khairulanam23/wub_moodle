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
 * Library functions for local_wub_landing.
 *
 * @package    local_wub_landing
 * @copyright  2026 WUB eLearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Extends the global navigation tree by adding a WUB Landing node.
 *
 * @param global_navigation $navigation The global navigation tree.
 */
function local_wub_landing_extend_navigation(global_navigation $navigation) {
    $enabled = get_config('local_wub_landing', 'enabled');
    if ($enabled === false || $enabled) {
        $landingurl = new moodle_url('/local/wub_landing/index.php');
        $node = navigation_node::create(
            get_string('pluginname', 'local_wub_landing'),
            $landingurl,
            navigation_node::TYPE_CUSTOM,
            null,
            'wub_landing',
            new pix_icon('i/home', '')
        );
        $node->showinflatnavigation = true;
        $navigation->add_node($node);
    }
}

/**
 * Check whether a user has student-level access.
 *
 * @param int $userid The Moodle user ID to check.
 * @return bool True if the user is authorized as a student.
 */
function wub_landing_user_is_student(int $userid): bool {
    if (file_exists(__DIR__ . '/../wub_auth/lib.php')) {
        require_once(__DIR__ . '/../wub_auth/lib.php');
        return wub_auth_user_is_student($userid);
    }
    if (is_siteadmin($userid)) {
        return false;
    }
    if (wub_landing_user_is_teacher($userid)) {
        return false;
    }
    return true;
}

/**
 * Check whether a user has teacher-level access across enrolled courses.
 *
 * @param int $userid The Moodle user ID to check.
 * @return bool True if the user has teaching capabilities in any course.
 */
function wub_landing_user_is_teacher(int $userid): bool {
    if (file_exists(__DIR__ . '/../wub_auth/lib.php')) {
        require_once(__DIR__ . '/../wub_auth/lib.php');
        return wub_auth_user_is_teacher($userid);
    }
    $courses = enrol_get_users_courses($userid, true, ['id']);
    if (empty($courses)) {
        return false;
    }

    foreach ($courses as $course) {
        $coursecontext = context_course::instance($course->id);
        if (has_capability('moodle/course:manageactivities', $coursecontext, $userid, false) ||
            has_capability('moodle/course:viewhiddenactivities', $coursecontext, $userid, false)) {
            return true;
        }
    }

    return false;
}

