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
 * Version details and operational documentation for local_header.
 *
 * PLUGIN OPERATIONAL SUMMARY:
 * --------------------------------------------------------------------------------
 * The local_header plugin is a specialized, lightweight UI component plugin that
 * renders the World University of Bangladesh (WUB) custom top transparent navigation bar
 * across all public portal pages and custom local plugin controllers.
 *
 * Key Responsibilities:
 * 1. Global Header Rendering: Exposes local_header_render(renderer_base $output) in lib.php.
 * 2. Component Logic: Instantiates \local_header\output\main renderable class.
 * 3. UI Presentation: Exports data to templates/header.mustache and injects custom
 *    styling defined in styles.css.
 * --------------------------------------------------------------------------------
 *
 * @package    local_header
 * @copyright  2026 WUB eLearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_header';
$plugin->version   = 2026082101;
$plugin->requires  = 2025100600; // Moodle 5.1
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0.0';
