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
 * Version details and operational documentation for local_wub_policy.
 *
 * PLUGIN OPERATIONAL SUMMARY:
 * --------------------------------------------------------------------------------
 * The local_wub_policy plugin manages institutional terms and policy agreements for the
 * WUB eLearning Portal.
 *
 * Key Responsibilities:
 * 1. Policy Agreement Interface (index.php): Displays 20 detailed university terms grouped
 *    into 4 parts with sesskey CSRF validation.
 * 2. 30-Day Policy Persistence Engine (lib.php):
 *    - Session Cache: Immediate PHP memory lookup.
 *    - Database Tracking: Stores agreement in {local_wub_policy_accept} table.
 *    - Device Token Cookie: Sets 60-day secure cookie (wub_policy_device) surviving logout.
 * 3. User Binding (wub_policy_bind_user_acceptance): Binds pre-login device tokens to
 *    authenticated user IDs upon successful login.
 * 4. Policy Verification API: Exposes wub_policy_is_accepted() for local_wub_auth.
 * --------------------------------------------------------------------------------
 *
 * @package    local_wub_policy
 * @copyright  2026 WUB eLearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_wub_policy';
$plugin->version   = 2026081400;
$plugin->requires  = 2025100600; // Moodle 5.1
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '2.0.0';
