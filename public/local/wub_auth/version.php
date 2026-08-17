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
 * Version details and architectural documentation for local_wub_auth plugin.
 *
 * PLUGIN OPERATIONAL SUMMARY:
 * --------------------------------------------------------------------------------
 * The local_wub_auth plugin serves as the primary Authentication & Role Authorization
 * Gate for the WUB eLearning Portal.
 *
 * Key Responsibilities:
 * 1. Role Selection Entry (auth.php): Captures user role intent (Student, Teacher, Admin),
 *    normalizes role parameters via local_wub_policy, stores $SESSION->wub_intended_role,
 *    and verifies 30-day policy acceptance before sending the user to the login form.
 * 2. Post-Login Authorization (postlogin.php): Executes immediately after Moodle core
 *    authenticates the user. Verifies that the user's actual Moodle capabilities and
 *    roles match their declared intent (e.g. confirming a user choosing "Teacher" actually
 *    possesses teaching capabilities).
 * 3. Student Payment Dues Integration: Intercepts authorized student logins and checks
 *    remaining UMS account dues via local_wub_auth_penalty (wub_auth_penalty_check_student_due_status).
 *    Redirects students with dues exceeding 100 BDT to the payment hold notice.
 * 4. Helper API (lib.php): Provides wub_auth_user_is_student() and wub_auth_user_is_teacher()
 *    to evaluate user access across enrolled courses.
 * --------------------------------------------------------------------------------
 *
 * @package    local_wub_auth
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2026081500;
$plugin->requires  = 2022041900; // Moodle 4.0 or later.
$plugin->component = 'local_wub_auth';
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0.0';
