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

namespace local_examcontroller\task;

use core\task\scheduled_task;
use local_examcontroller\service\signature_service;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task to prune expired ExamController HMAC nonces.
 *
 * @package    local_examcontroller
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class prune_nonces_task extends scheduled_task {
    /**
     * Return the task's name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('prunenoncestask', 'local_examcontroller');
    }

    /**
     * Execute the task.
     *
     * @return void
     */
    public function execute(): void {
        $count = signature_service::prune_expired_nonces();
        if ($count > 0) {
            mtrace("Pruned {$count} expired ExamController replay nonces.");
        } else {
            mtrace("No expired ExamController replay nonces to prune.");
        }
    }
}
