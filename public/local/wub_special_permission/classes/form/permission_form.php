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

namespace local_wub_special_permission\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use moodleform;

/**
 * QuickForm definition for granting or modifying student special login permission.
 *
 * @package    local_wub_special_permission
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class permission_form extends moodleform {

    /**
     * Form definition.
     */
    protected function definition() {
        $mform = $this->_form;
        $customdata = $this->_customdata;

        $studentId = $customdata['student_id'] ?? 0;
        $defaultDate = $customdata['default_date'] ?? date('Y-m-d', strtotime('+7 days'));
        $hasActive = $customdata['has_active'] ?? false;

        $mform->addElement('hidden', 'student_id', $studentId);
        $mform->setType('student_id', PARAM_INT);

        $mform->addElement('hidden', 'search', $customdata['search_query'] ?? '');
        $mform->setType('search', PARAM_RAW);

        $mform->addElement('header', 'grant_hdr', get_string('grant_permission_heading', 'local_wub_special_permission'));

        // Calendar date selector
        $mform->addElement('date_selector', 'valid_until', get_string('valid_until_label', 'local_wub_special_permission'), [
            'startyear' => date('Y'),
            'stopyear' => date('Y') + 5,
            'timezone' => 99,
            'applyunixtime' => true
        ]);
        $mform->setDefault('valid_until', strtotime($defaultDate));

        if ($hasActive) {
            $mform->addElement('hidden', 'confirm_overwrite', '0');
            $mform->setType('confirm_overwrite', PARAM_INT);
        }

        $buttonarray = [];
        $buttonarray[] = $mform->createElement('submit', 'submitbutton', get_string('grant_button', 'local_wub_special_permission'), ['class' => 'btn btn-primary']);
        if ($hasActive) {
            $buttonarray[] = $mform->createElement('submit', 'revoke_button', get_string('revoke_button', 'local_wub_special_permission'), ['class' => 'btn btn-outline-danger']);
        }
        $mform->addGroup($buttonarray, 'buttonar', '', [' '], false);
    }

    /**
     * Form validation.
     *
     * @param array $data Form submission data.
     * @param array $files
     * @return array Validation errors.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!empty($data['valid_until'])) {
            $selectedTime = (int)$data['valid_until'];
            if ($selectedTime <= time() - 86400) {
                $errors['valid_until'] = get_string('err_invalid_date', 'local_wub_special_permission');
            }
        }

        return $errors;
    }
}
