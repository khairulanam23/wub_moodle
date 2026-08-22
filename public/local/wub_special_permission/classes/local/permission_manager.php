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

namespace local_wub_special_permission\local;

defined('MOODLE_INTERNAL') || die();

use stdClass;
use local_wub_special_permission\service\special_permission_service;

/**
 * Backward compatibility Facade Manager for WUB Student Special Login Permission Management.
 *
 * Delegates all operations to \local_wub_special_permission\service\special_permission_service.
 *
 * @package    local_wub_special_permission
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class permission_manager {

    /**
     * Preference key for legacy fallback compatibility.
     */
    const PERMISSION_PREFERENCE_KEY = special_permission_service::PERMISSION_PREFERENCE_KEY;

    /** @var special_permission_service */
    protected special_permission_service $service;

    /**
     * Constructor.
     *
     * @param special_permission_service|null $service
     */
    public function __construct(?special_permission_service $service = null) {
        $this->service = $service ?? new special_permission_service();
    }

    /**
     * Search Moodle user database for student record by username, ID number, or email.
     *
     * @param string $query User input identifier string.
     * @return stdClass|null User record if found, or null.
     */
    public function search_student(string $query): ?stdClass {
        return $this->service->search_student($query);
    }

    /**
     * Retrieve current special login permission status for student.
     *
     * @param stdClass $user Moodle user record.
     * @return array Status array
     */
    public function get_permission_status(stdClass $user): array {
        return $this->service->get_permission_status($user);
    }

    /**
     * Grant or update special login permission for student.
     *
     * @param stdClass $student Student user record.
     * @param string $expiryDate Date string in 'YYYY-MM-DD' format.
     * @param int $adminUserId Moodle user ID of administering user.
     * @return bool True on success.
     */
    public function grant_permission(stdClass $student, string $expiryDate, int $adminUserId): bool {
        return $this->service->grant_permission($student, $expiryDate, $adminUserId);
    }

    /**
     * Revoke special login permission for student.
     *
     * @param stdClass $student Student user record.
     * @param int $adminUserId Moodle user ID of administering user.
     * @return bool True on success.
     */
    public function revoke_permission(stdClass $student, int $adminUserId): bool {
        return $this->service->revoke_permission($student, $adminUserId);
    }
}
