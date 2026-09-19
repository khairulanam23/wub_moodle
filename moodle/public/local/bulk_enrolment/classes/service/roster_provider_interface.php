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

namespace local_bulk_enrolment\service;

defined('MOODLE_INTERNAL') || die();

/**
 * Interface contract for academic roster data sources.
 *
 * Enables pluggable student roster selection (e.g. UMS API vs Moodle Academic Cohorts).
 *
 * @package    local_bulk_enrolment
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface roster_provider_interface {

    /**
     * Check if this roster provider is currently available and configured.
     *
     * @return bool
     */
    public function is_available(): bool;

    /**
     * Unique alphanumeric identifier for this source.
     *
     * @return string (e.g. 'ums', 'cohort')
     */
    public function get_source_id(): string;

    /**
     * Human-readable label for UI display.
     *
     * @return string
     */
    public function get_source_label(): string;

    /**
     * Retrieve level-1 organizational groups (e.g. Programs or Cohorts).
     *
     * @return array Array of objects with 'id' and 'name'
     */
    public function get_groups(): array;

    /**
     * Retrieve level-2 sub-groups if applicable (e.g. Batches under a Program).
     *
     * @param string $groupid
     * @return array Array of objects with 'id' and 'name'
     */
    public function get_subgroups(string $groupid): array;

    /**
     * Fetch students associated with the group and optional subgroup.
     *
     * Returns normalized student objects containing Moodle user details:
     * - id (int, Moodle userid, or 0 if not provisioned in Moodle)
     * - username (string)
     * - fullname (string)
     * - email (string)
     * - idnumber (string)
     * - department (string)
     * - institution (string)
     *
     * @param string $groupid
     * @param string $subgroupid
     * @return array
     */
    public function get_students(string $groupid, string $subgroupid = ''): array;
}
