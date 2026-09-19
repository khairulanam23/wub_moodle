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

use moodle_exception;

/**
 * Registry and factory for academic roster providers.
 *
 * @package    local_bulk_enrolment
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class roster_manager {

    /** @var roster_provider_interface[] */
    protected array $providers = [];

    public function __construct() {
        $this->register_provider(new ums_roster_provider());
        $this->register_provider(new cohort_roster_provider());
    }

    public function register_provider(roster_provider_interface $provider): void {
        $this->providers[$provider->get_source_id()] = $provider;
    }

    public function get_provider(string $sourceid): roster_provider_interface {
        if (!isset($this->providers[$sourceid])) {
            throw new moodle_exception('error_invalid_source', 'local_bulk_enrolment', '', $sourceid);
        }
        return $this->providers[$sourceid];
    }

    /**
     * Retrieve all registered providers with availability flags for UI rendering.
     *
     * @return array Array of objects with 'id', 'name', 'available'
     */
    public function get_available_sources(): array {
        $sources = [];
        foreach ($this->providers as $id => $p) {
            $isAvailable = $p->is_available();
            $label = $p->get_source_label();
            if (!$isAvailable && $id === 'ums') {
                $label .= ' (Not Configured)';
            }
            $sources[] = (object)[
                'id' => $id,
                'name' => $label,
                'available' => $isAvailable,
            ];
        }
        return $sources;
    }
}
