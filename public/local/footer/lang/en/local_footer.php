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
 * Language strings for local_footer.
 *
 * @package    local_footer
 * @copyright  2026 WUB eLearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'WUB Portal Footer';
$string['brandname'] = 'World University of Bangladesh';

// Settings.
$string['showfull'] = 'Show full footer on portal pages';
$string['showfull_desc'] = 'When enabled, the portal footer shows the link columns, contact details, logo and social icons. When disabled (the default) the portal pages carry only the copyright line. This does not affect the Academi theme footer used on logged-in pages.';

$string['linksheading'] = 'Footer link columns';
$string['linksheading_desc'] = 'The three link columns shown in the portal footer. '
    . 'Contact details, social media icons and the copyright line are read from the '
    . 'Academi theme settings (Site administration > Appearance > Themes > Academi) '
    . 'so they are not duplicated here.';
$string['coltitle'] = 'Column {$a} heading';
$string['collinks'] = 'Column {$a} links';
$string['collinks_desc'] = 'One link per line, in the form <code>Label|https://example.com</code>. '
    . 'Lines without a URL are rendered as plain text.';
$string['coltitle4_desc'] = 'Heading for the contact column. Its content comes from the '
    . 'Academi theme address, phone and email settings.';
$string['whatsapp'] = 'WhatsApp number';
$string['whatsapp_desc'] = 'Optional. Shown in the contact column with a WhatsApp link. Leave blank to hide.';

// Footer chrome.
$string['backtotop'] = 'Back to top';
$string['getsocial'] = 'GET SOCIAL';
$string['footernav'] = 'Footer';
$string['poweredby'] = 'Powered by <a href="https://moodle.org" target="_blank" rel="noopener">Moodle</a>';

$string['privacy:metadata'] = 'The WUB Portal Footer plugin does not store any personal data.';
