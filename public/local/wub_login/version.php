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
 * Version details and operational documentation for local_wub_login.
 *
 * PLUGIN OPERATIONAL SUMMARY:
 * --------------------------------------------------------------------------------
 * The local_wub_login plugin provides the custom authentication login interface
 * for the WUB eLearning Portal.
 *
 * Key Responsibilities:
 * 1. Custom Login Form (index.php): Renders modern, responsive login interface.
 * 2. Moodle Authentication Dispatch: Passes submitted credentials to Moodle's
 *    authenticate_user_login() and complete_user_login().
 * 3. Username Normalization: Automatically converts short student IDs (e.g. 0326735386)
 *    into full email addresses (0326735386@student.wub.ac.bd) via local_wub_ums.
 * 4. Cookie Management: Manages encrypted "Remember Username" browser cookie.
 * --------------------------------------------------------------------------------
 *
 * @package    local_wub_login
 * @copyright  2026 WUB eLearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_wub_login';
$plugin->version   = 2026081000;
$plugin->requires  = 2025100600; // Moodle 5.1
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0.0';
