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
 * Version details for local_bulk_enrolment.
 *
 * Unified production plugin merging authoritative WUB UMS API integration
 * with WUB bulk course enrolment and validation matrix workflows.
 *
 * @package    local_bulk_enrolment
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_bulk_enrolment';
$plugin->version = 2026091402;
$plugin->requires  = 2024042200; // Moodle 4.4+ (Full Moodle 5.2 compatibility).
$plugin->maturity  = MATURITY_STABLE;
$plugin->release = 'v3.0 (UMS contract verified, workspace rebuilt)';
