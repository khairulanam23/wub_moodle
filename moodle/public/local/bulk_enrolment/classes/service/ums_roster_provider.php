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
 * UMS roster provider (pluggable provider interface kept for the cohort provider). The enrolment workspace
 * talks to roster_service directly; this adapter exposes the same data through the generic interface and
 * propagates UMS failures instead of hiding them behind empty lists.
 *
 * @package    local_bulk_enrolment
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_bulk_enrolment\service;

defined('MOODLE_INTERNAL') || die();

use local_bulk_enrolment\api_client;

/**
 * UMS-backed provider.
 */
class ums_roster_provider implements roster_provider_interface {
    /**
     * Whether UMS is configured.
     *
     * @return bool
     */
    public function is_available(): bool {
        return (new api_client())->is_configured();
    }

    /**
     * Source id.
     *
     * @return string
     */
    public function get_source_id(): string {
        return 'ums';
    }

    /**
     * Source label.
     *
     * @return string
     */
    public function get_source_label(): string {
        return get_string('source_ums', 'local_bulk_enrolment');
    }

    /**
     * Programs as id/name objects.
     *
     * @return array
     * @throws \local_bulk_enrolment\exception\ums_exception
     */
    public function get_groups(): array {
        return array_map(fn($p) => (object)['id' => $p['id'], 'name' => $p['label']], (new roster_service())->list_programs());
    }

    /**
     * Batches of a program as id/name objects where id IS the batch title (the identifier the roster API needs).
     *
     * @param string $groupid Program id.
     * @return array
     * @throws \local_bulk_enrolment\exception\ums_exception
     */
    public function get_subgroups(string $groupid): array {
        $byprogram = (new roster_service())->list_batches([$groupid]);
        return array_map(fn($b) => (object)['id' => $b['title'], 'name' => $b['title'] . ($b['shift'] !== '' ? ' (' . $b['shift'] . ')' : '')], $byprogram[$groupid] ?? []);
    }

    /**
     * Identity-matched roster rows for a program and optional batch title.
     *
     * @param string $groupid Program id.
     * @param string $subgroupid Batch title ('' or '0' = whole program).
     * @return array
     * @throws \local_bulk_enrolment\exception\ums_exception
     */
    public function get_students(string $groupid, string $subgroupid = ''): array {
        $titles = ($subgroupid === '' || $subgroupid === '0' || strtolower($subgroupid) === 'all') ? [] : [$subgroupid];
        $result = (new roster_service())->query([['program_id' => $groupid, 'batch_titles' => $titles]], [], 1, roster_service::MAX_PER_PAGE);
        return array_map(fn($r) => (object)$r, $result['rows']);
    }
}
