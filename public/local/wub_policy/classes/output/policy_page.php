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
 * Renderable class for WUB Policy page.
 *
 * @package    local_wub_policy
 * @copyright  2026 WUB eLearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_wub_policy\output;

defined('MOODLE_INTERNAL') || die();

use renderable;
use templatable;
use renderer_base;
use moodle_url;
use stdClass;

/**
 * Policy page renderable.
 */
class policy_page implements renderable, templatable {

    /** @var string The normalized user role. */
    private string $role;

    /** @var string|null Optional error message. */
    private ?string $error;

    /** @var string Optional return URL. */
    private string $returnurl;

    /**
     * Constructor.
     *
     * @param string $role User role (student, teacher, admin)
     * @param string|null $error Error message if submission failed
     * @param string $returnurl Return URL after acceptance
     */
    public function __construct(string $role, ?string $error = null, string $returnurl = '') {
        $this->role = $role;
        $this->error = $error;
        $this->returnurl = $returnurl;
    }

    /**
     * Export data for Mustache template.
     *
     * @param renderer_base $output The renderer.
     * @return stdClass Template data.
     */
    public function export_for_template(renderer_base $output): stdClass {
        $data = new stdClass();

        $data->role = $this->role;
        $data->returnurl = $this->returnurl;
        $data->sesskey = sesskey();
        $data->formaction = (new moodle_url('/local/wub_policy/index.php'))->out(false);
        $data->cancelurl = (new moodle_url('/local/wub_landing/index.php'))->out(false);

        // Error message.
        $data->haserror = !empty($this->error);
        $data->errormessage = $this->error;

        // Role details and styling badge.
        $data->roletitle = get_string('role_' . $this->role, 'local_wub_policy');
        $data->roleclass = 'wub-role-badge-' . $this->role;
        $data->rolenotice = get_string('role_notice_' . $this->role, 'local_wub_policy');

        // Metadata badges.
        $data->policyversion = wub_policy_get_version();
        $data->policyvalidity = get_string('policyvalidityvalue', 'local_wub_policy');

        // Headers & Labels.
        $data->str_policyheader = get_string('policyheader', 'local_wub_policy');
        $data->str_policysubtitle = get_string('policysubtitle', 'local_wub_policy');
        $data->str_agreecheckbox = get_string('agreecheckbox', 'local_wub_policy');
        $data->str_continuebtn = get_string('continuebtn', 'local_wub_policy');
        $data->str_cancelbtn = get_string('cancelbtn', 'local_wub_policy');
        $data->str_tableofcontents = get_string('tableofcontents', 'local_wub_policy');
        $data->str_versionlabel = get_string('policyversionlabel', 'local_wub_policy');
        $data->str_validitylabel = get_string('policyvaliditylabel', 'local_wub_policy');

        // Structured 20 Detailed Policies grouped in 4 Parts.
        $categories = [
            [
                'id' => 'category-1',
                'title' => get_string('category_account_security', 'local_wub_policy'),
                'icon' => 'shield-lock',
                'policies' => [
                    ['num' => 1, 'title' => get_string('policy_1_title', 'local_wub_policy'), 'content' => get_string('policy_1_content', 'local_wub_policy')],
                    ['num' => 2, 'title' => get_string('policy_2_title', 'local_wub_policy'), 'content' => get_string('policy_2_content', 'local_wub_policy')],
                    ['num' => 3, 'title' => get_string('policy_3_title', 'local_wub_policy'), 'content' => get_string('policy_3_content', 'local_wub_policy')],
                    ['num' => 4, 'title' => get_string('policy_4_title', 'local_wub_policy'), 'content' => get_string('policy_4_content', 'local_wub_policy')],
                ],
            ],
            [
                'id' => 'category-2',
                'title' => get_string('category_academic_assessments', 'local_wub_policy'),
                'icon' => 'graduation-cap',
                'policies' => [
                    ['num' => 5, 'title' => get_string('policy_5_title', 'local_wub_policy'), 'content' => get_string('policy_5_content', 'local_wub_policy')],
                    ['num' => 6, 'title' => get_string('policy_6_title', 'local_wub_policy'), 'content' => get_string('policy_6_content', 'local_wub_policy')],
                    ['num' => 7, 'title' => get_string('policy_7_title', 'local_wub_policy'), 'content' => get_string('policy_7_content', 'local_wub_policy')],
                    ['num' => 8, 'title' => get_string('policy_8_title', 'local_wub_policy'), 'content' => get_string('policy_8_content', 'local_wub_policy')],
                ],
            ],
            [
                'id' => 'category-3',
                'title' => get_string('category_conduct_communication', 'local_wub_policy'),
                'icon' => 'comments',
                'policies' => [
                    ['num' => 9, 'title' => get_string('policy_9_title', 'local_wub_policy'), 'content' => get_string('policy_9_content', 'local_wub_policy')],
                    ['num' => 10, 'title' => get_string('policy_10_title', 'local_wub_policy'), 'content' => get_string('policy_10_content', 'local_wub_policy')],
                    ['num' => 11, 'title' => get_string('policy_11_title', 'local_wub_policy'), 'content' => get_string('policy_11_content', 'local_wub_policy')],
                    ['num' => 12, 'title' => get_string('policy_12_title', 'local_wub_policy'), 'content' => get_string('policy_12_content', 'local_wub_policy')],
                ],
            ],
            [
                'id' => 'category-4',
                'title' => get_string('category_ip_privacy_governance', 'local_wub_policy'),
                'icon' => 'gavel',
                'policies' => [
                    ['num' => 13, 'title' => get_string('policy_13_title', 'local_wub_policy'), 'content' => get_string('policy_13_content', 'local_wub_policy')],
                    ['num' => 14, 'title' => get_string('policy_14_title', 'local_wub_policy'), 'content' => get_string('policy_14_content', 'local_wub_policy')],
                    ['num' => 15, 'title' => get_string('policy_15_title', 'local_wub_policy'), 'content' => get_string('policy_15_content', 'local_wub_policy')],
                    ['num' => 16, 'title' => get_string('policy_16_title', 'local_wub_policy'), 'content' => get_string('policy_16_content', 'local_wub_policy')],
                    ['num' => 17, 'title' => get_string('policy_17_title', 'local_wub_policy'), 'content' => get_string('policy_17_content', 'local_wub_policy')],
                    ['num' => 18, 'title' => get_string('policy_18_title', 'local_wub_policy'), 'content' => get_string('policy_18_content', 'local_wub_policy')],
                    ['num' => 19, 'title' => get_string('policy_19_title', 'local_wub_policy'), 'content' => get_string('policy_19_content', 'local_wub_policy')],
                    ['num' => 20, 'title' => get_string('policy_20_title', 'local_wub_policy'), 'content' => get_string('policy_20_content', 'local_wub_policy')],
                ],
            ],
        ];

        $data->categories = $categories;

        return $data;
    }
}
