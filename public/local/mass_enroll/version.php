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
 * Version details and operational documentation for local_mass_enroll.
 *
 * PLUGIN OPERATIONAL SUMMARY:
 * --------------------------------------------------------------------------------
 * The local_mass_enroll plugin manages bulk course enrolment processing, student list
 * exports, and payment hold restriction notices for the WUB eLearning Portal.
 *
 * Key Responsibilities:
 * 1. Bulk Enrolments & Unenrolments (massenrol.php, massunenrol.php): Admin tools for
 *    enrolling or unenrolling students in bulk via text code or CSV lists.
 * 2. Enrolment Sync Table (enrolled.php, sync.php): Interactive table comparing UMS
 *    students against local Moodle users and executing batch enrolments.
 * 3. Excel Export Generator (export.php, enrolhelper::generateXl): Generates downloadable
 *    XLSX spreadsheets of student course enrolments using PhpSpreadsheet.
 * 4. Payment Hold Notice View (payment_notice.php): Renders user-facing dashboard
 *    restriction notice when student dues exceed the allowable threshold.
 * --------------------------------------------------------------------------------
 *
 * @package    local_mass_enroll
 * @copyright  2021 World University of Bangladesh (CIS)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2026081402;        // The current plugin version (Date: YYYYMMDDXX)
$plugin->requires  = 2023100900;        // Requires Moodle 4.3 onwards
$plugin->component = 'local_mass_enroll';    // Full name of the plugin

$plugin->maturity = MATURITY_STABLE;
$plugin->release = '4.2.2 (Build 2026081402)';
