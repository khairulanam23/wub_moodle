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
 * Admin settings for local_footer.
 *
 * Link columns live here because the Academi theme footer only models a single
 * link list, while the WUB site footer has three. Contact details, social media
 * and the copyright line are NOT duplicated here -- they are read from the
 * Academi theme settings so there is one place to edit them.
 *
 * @package    local_footer
 * @copyright  2026 WUB eLearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    $settings = new admin_settingpage('local_footer', get_string('pluginname', 'local_footer'));
    $ADMIN->add('localplugins', $settings);

    // Off by default: the public portal pages carry only the copyright line.
    // The Academi theme footer still shows its own columns on logged-in pages.
    $settings->add(new admin_setting_configcheckbox(
        'local_footer/showfull',
        get_string('showfull', 'local_footer'),
        get_string('showfull_desc', 'local_footer'),
        0
    ));

    $settings->add(new admin_setting_heading(
        'local_footer/linksheading',
        get_string('linksheading', 'local_footer'),
        get_string('linksheading_desc', 'local_footer')
    ));

    // Column 1 -- defaults mirror https://wub.edu.bd/ "IMPORTANT LINKS".
    $settings->add(new admin_setting_configtext(
        'local_footer/coltitle1',
        get_string('coltitle', 'local_footer', 1),
        '',
        'IMPORTANT LINKS',
        PARAM_TEXT
    ));
    $settings->add(new admin_setting_configtextarea(
        'local_footer/collinks1',
        get_string('collinks', 'local_footer', 1),
        get_string('collinks_desc', 'local_footer'),
        "Admission Period|https://admission.wub.edu.bd/\n"
        . "Program|https://admission.wub.edu.bd/admission/programs\n"
        . "Admission Eligibilities|https://admission.wub.edu.bd/admission/admission_eligibilities\n"
        . "Tuition Fees|https://admission.wub.edu.bd/admission/tuition_fees\n"
        . "Scholarship and Waiver|https://admission.wub.edu.bd/admission/scholarship\n"
        . "How to apply|https://admission.wub.edu.bd/admission/how_to_apply\n"
        . "Notice|https://wub.edu.bd/main/all_notice\n"
        . "Feedback Form|https://wub.edu.bd/contact\n"
        . "Visitor Appointment|https://wub.edu.bd/visitor",
        PARAM_RAW
    ));

    // Column 2 -- "EXTERNAL LINKS".
    $settings->add(new admin_setting_configtext(
        'local_footer/coltitle2',
        get_string('coltitle', 'local_footer', 2),
        '',
        'EXTERNAL LINKS',
        PARAM_TEXT
    ));
    $settings->add(new admin_setting_configtextarea(
        'local_footer/collinks2',
        get_string('collinks', 'local_footer', 2),
        get_string('collinks_desc', 'local_footer'),
        "University Grants Commission|http://www.ugc.gov.bd/\n"
        . "Ministry of Education|https://moedu.gov.bd/\n"
        . "IJEDS|http://ijeds.org/index.php?journal=ijeds\n"
        . "grammarly.com|https://www.grammarly.com/\n"
        . "scholar.google.com|https://scholar.google.com/",
        PARAM_RAW
    ));

    // Column 3 -- "USEFUL LINKS".
    $settings->add(new admin_setting_configtext(
        'local_footer/coltitle3',
        get_string('coltitle', 'local_footer', 3),
        '',
        'USEFUL LINKS',
        PARAM_TEXT
    ));
    $settings->add(new admin_setting_configtextarea(
        'local_footer/collinks3',
        get_string('collinks', 'local_footer', 3),
        get_string('collinks_desc', 'local_footer'),
        "Academic Calendar|https://wub.edu.bd/academics/academic_calendar\n"
        . "Student Affairs|https://dsa.wub.edu.bd/\n"
        . "Convocation|https://convocation.wub.edu.bd/\n"
        . "Our Teachers|https://wub.edu.bd/main/wub_teachers\n"
        . "Activities|https://wub.edu.bd/all_activities\n"
        . "Jobs @WUB|https://jobs.wub.edu.bd/\n"
        . "IQAC|https://wub.edu.bd/main/about_iqac\n"
        . "Waste Disposal Policy|https://wub.edu.bd/main/waste_disposal_policy",
        PARAM_RAW
    ));

    // Column 4 -- contact. Values come from the Academi theme settings.
    $settings->add(new admin_setting_configtext(
        'local_footer/coltitle4',
        get_string('coltitle', 'local_footer', 4),
        get_string('coltitle4_desc', 'local_footer'),
        'CONTACT US',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_footer/whatsapp',
        get_string('whatsapp', 'local_footer'),
        get_string('whatsapp_desc', 'local_footer'),
        '+8801404-400217',
        PARAM_TEXT
    ));
}
