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
 * Language strings for local_wub_landing.
 *
 * @package    local_wub_landing
 * @copyright  2026 WUB eLearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// General.
$string['pluginname'] = 'WUB Landing';
$string['wub_landing:view'] = 'View WUB landing page';

// Landing page.
$string['welcometitle'] = 'Welcome to WUB';
$string['welcomesubtitle'] = 'Click below to access the portal as a student, teacher, or administrative personnel';
$string['student'] = 'Student';
$string['teacher'] = 'Teacher';
$string['administration'] = 'Administration';
$string['coursecatalog'] = 'Course Catalog';
$string['coursecataloginfo'] = 'Click the button below to check out the course catalog';
$string['catalogprompt'] = 'Click the button below to check out the course catalog';
$string['contactus'] = 'Contact Us';
$string['howtoguides'] = 'How-to Guides';
$string['gotodashboard'] = 'Go to Dashboard';
$string['logout'] = 'Logout';
$string['loggedinas'] = 'You are logged in as {$a}';
$string['browsecoursesbelow'] = 'Browse courses or go to your dashboard.';

// Authentication flow.
$string['invalidrole'] = 'Invalid role selection.';
$string['notauthorisedstudent'] = 'Your account is not authorised for the Student area. You do not appear to be enrolled in any courses as a student. Please contact the administrator if you believe this is an error.';
$string['notauthorisedteacher'] = 'Your account is not authorised for the Teacher area. You do not have teaching permissions in any course. Please contact the administrator if you believe this is an error.';
$string['notauthorisedadmin'] = 'You do not have permission to access the Administration area. Please contact a site administrator if you need access.';
$string['authorisationfailed'] = 'Authorisation Failed';
$string['returnlanding'] = 'Return to Landing Page';

// Course catalog.
$string['searchcourses'] = 'Search courses...';
$string['allcategories'] = 'All Categories';
$string['category'] = 'Category';
$string['nocoursesfound'] = 'No courses found matching your criteria.';
$string['viewdetails'] = 'View Details';
$string['searchresultsfor'] = 'Search results for: "{$a}"';
$string['coursesin'] = 'Courses in: {$a}';
$string['allcourses'] = 'All Courses';
$string['search'] = 'Search';
$string['clearfilters'] = 'Clear Filters';

// Course details.
$string['courseinformation'] = 'Course Information';
$string['coursesummary'] = 'Course Summary';
$string['instructor'] = 'Instructor';
$string['instructors'] = 'Instructors';
$string['backtocatalog'] = '← Back to Course Catalog';
$string['logintoacccess'] = 'Login to Access Course';
$string['gotocourse'] = 'Go to Course';
$string['enrolme'] = 'Enrol Me';
$string['nosummary'] = 'No summary available for this course.';
$string['coursecategory'] = 'Course Category';
$string['coursenotfound'] = 'The requested course could not be found or is not available.';

// Settings.
$string['settings_enable'] = 'Enable WUB Landing';
$string['settings_enable_desc'] = 'Enable or disable the WUB Landing page plugin.';
$string['settings_title'] = 'Landing Page Title';
$string['settings_title_desc'] = 'The main title displayed on the landing page.';
$string['settings_subtitle'] = 'Landing Page Subtitle';
$string['settings_subtitle_desc'] = 'The subtitle displayed below the main title.';
$string['settings_student_enabled'] = 'Student Button Enabled';
$string['settings_student_enabled_desc'] = 'Show or hide the Student entry point button.';
$string['settings_teacher_enabled'] = 'Teacher Button Enabled';
$string['settings_teacher_enabled_desc'] = 'Show or hide the Teacher entry point button.';
$string['settings_admin_enabled'] = 'Administration Button Enabled';
$string['settings_admin_enabled_desc'] = 'Show or hide the Administration entry point button.';
$string['settings_catalog_enabled'] = 'Course Catalog Enabled';
$string['settings_catalog_enabled_desc'] = 'Show or hide the Course Catalog section.';
$string['settings_contactus_url'] = 'Contact Us URL';
$string['settings_contactus_url_desc'] = 'The URL for the Contact Us link. Leave empty to hide.';
$string['settings_howtoguides_url'] = 'How-to Guides URL';
$string['settings_howtoguides_url_desc'] = 'The URL for the How-to Guides link. Leave empty to hide.';
$string['settings_courses_per_page'] = 'Courses Per Page';
$string['settings_courses_per_page_desc'] = 'Number of courses displayed per page in the catalog.';
$string['settings_heroimage'] = 'Hero Image Filename';
$string['settings_heroimage_desc'] = 'Enter the filename of the hero background image located in the /local/wub_landing/pix/ folder (e.g., wubImage.jpg). Leave empty to use the default.';

// Privacy.
$string['privacy:metadata'] = 'The WUB Landing plugin does not store any personal data.';