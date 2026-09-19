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

namespace local_bulk_enrolment\event;

defined('MOODLE_INTERNAL') || die();

use core\event\base;
use moodle_url;

/**
 * Event triggered when a bulk enrolment operation is executed.
 *
 * @package    local_bulk_enrolment
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bulk_enrolment_executed extends base {

    /**
     * Initialize event attributes.
     */
    protected function init(): void {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'course';
    }

    /**
     * Localised event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('event_bulk_enrolment_executed', 'local_bulk_enrolment');
    }

    /**
     * Description for Moodle administrator log viewers.
     *
     * @return string
     */
    public function get_description(): string {
        $count = $this->other['enrolled_count'] ?? 0;
        return "The user with id '{$this->userid}' performed a bulk enrolment of {$count} student(s) in course '{$this->courseid}'.";
    }

    /**
     * Relevant Moodle URL for this event.
     *
     * @return moodle_url
     */
    public function get_url(): moodle_url {
        return new moodle_url('/local/bulk_enrolment/index.php', ['tab' => 'enrol']);
    }

    /**
     * Validate event data.
     */
    protected function validate_data(): void {
        parent::validate_data();

        if (empty($this->courseid)) {
            throw new \coding_exception("The 'courseid' must be set.");
        }
    }

    /**
     * Static helper to dispatch the event cleanly.
     *
     * @param int $courseid
     * @param int $enrolledcount
     * @param int $roleid
     * @param array $extra
     * @return self
     */
    public static function log_enrolment(int $courseid, int $enrolledcount, int $roleid = 0, array $extra = []): self {
        global $USER;

        $event = self::create([
            'context' => \context_course::instance($courseid),
            'courseid' => $courseid,
            'userid' => $USER->id ?? 0,
            'other' => array_merge([
                'enrolled_count' => $enrolledcount,
                'roleid' => $roleid,
            ], $extra),
        ]);
        $event->trigger();

        return $event;
    }
}
