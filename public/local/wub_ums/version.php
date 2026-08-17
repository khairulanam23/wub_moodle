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
 * Version details and operational documentation for local_wub_ums.
 *
 * PLUGIN OPERATIONAL SUMMARY:
 * --------------------------------------------------------------------------------
 * The local_wub_ums plugin acts as the dedicated UMS Integration Service for the
 * WUB eLearning Portal.
 *
 * Key Responsibilities:
 * 1. REST API Client (\local_wub_ums\api_client): Executes cURL HTTP requests to external
 *    UMS backend, handling Digest/Basic authentication, X-API-KEY headers, and retries.
 * 2. Program & Batch Catalog: Fetches academic programs and batch lists for synchronization.
 * 3. Student Account Synchronization (\local_wub_ums\sync_service): Safely creates or updates
 *    Moodle student user accounts in {user} and tracks mappings in {enrol_ums_user}.
 * 4. Dues Verification (wub_ums_check_student_due_status): Checks student payment status with
 *    10-minute session caching. Restricts dashboard access if dues exceed 100 BDT.
 * --------------------------------------------------------------------------------
 *
 * @package    local_wub_ums
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2026081500;
$plugin->requires  = 2022041900; // Moodle 4.0 or later.
$plugin->component = 'local_wub_ums';
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0.0';
