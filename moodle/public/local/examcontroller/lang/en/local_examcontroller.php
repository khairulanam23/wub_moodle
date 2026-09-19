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
 * Language strings for local_examcontroller.
 *
 * @package    local_examcontroller
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'WUB ExamController Production API Integration';
$string['setting_header'] = 'ExamController Cryptographic Trust Boundary Settings';
$string['setting_integration_secret'] = 'Shared Integration Secret';
$string['setting_integration_secret_desc'] = 'Shared secret used for HMAC-SHA256 request signing between Moodle and ExamController backend.';
$string['setting_integration_key_id'] = 'Integration Key ID';
$string['setting_integration_key_id_desc'] = 'Identifier of the active cryptographic key pair for rotation support.';
$string['setting_token_ttl'] = 'Identity Token TTL (Seconds)';
$string['setting_token_ttl_desc'] = 'Validity window for issued identity verification tokens (default 300 seconds).';
$string['prunenoncestask'] = 'Prune expired ExamController API nonces';

