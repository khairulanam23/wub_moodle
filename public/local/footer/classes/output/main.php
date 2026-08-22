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
 * Renderable class for the WUB portal footer.
 *
 * Mirrors the footer on https://wub.edu.bd/ : three link columns plus a contact
 * column over a copyright bar carrying the logo and social icons.
 *
 * Content sourcing is deliberate. Link columns come from this plugin's own
 * settings (the Academi theme footer only models one link list). Contact
 * details, social media and the copyright line are read from the Academi theme
 * settings so they are edited in exactly one place for both footers.
 *
 * @package    local_footer
 * @copyright  2026 WUB eLearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_footer\output;

defined('MOODLE_INTERNAL') || die();

use renderable;
use templatable;
use renderer_base;
use moodle_url;
use stdClass;

/**
 * Footer renderable class.
 */
class main implements renderable, templatable {

    /**
     * Export data for Mustache template.
     *
     * @param renderer_base $output The renderer.
     * @return stdClass Data for Mustache.
     */
    public function export_for_template(renderer_base $output): stdClass {
        $data = new stdClass();

        $data->logourl = (new moodle_url('/local/footer/pix/wub-logo.png'))->out(false);
        $data->sitename = get_string('brandname', 'local_footer');
        $data->logolinkurl = (new moodle_url('/'))->out(false);

        $data->showfull = (bool) get_config('local_footer', 'showfull');
        $data->copyright = $this->build_copyright();
        $data->year = userdate(time(), '%Y');

        // Compact mode: the portal pages carry only the copyright line.
        if (!$data->showfull) {
            $data->columns = [];
            $data->hascontact = false;
            $data->hassocial = false;
            return $data;
        }

        // Three link columns from this plugin's settings.
        $data->columns = [];
        for ($i = 1; $i <= 3; $i++) {
            $links = $this->parse_links(get_config('local_footer', 'collinks' . $i));
            if (empty($links)) {
                continue;
            }
            $data->columns[] = [
                'title' => get_config('local_footer', 'coltitle' . $i),
                'links' => $links,
            ];
        }

        // Contact column, sourced from the Academi theme settings.
        $data->contacttitle = get_config('local_footer', 'coltitle4');
        $data->contact = $this->build_contact();
        $data->hascontact = !empty($data->contact);

        // Social icons, also from the Academi theme settings.
        $data->social = $this->build_social();
        $data->hassocial = !empty($data->social);
        $data->getsocial = get_string('getsocial', 'local_footer');

        return $data;
    }

    /**
     * Parse a "Label|url" per line setting into template rows.
     *
     * @param string|false $raw The raw setting value.
     * @return array List of ['text' => string, 'url' => string, 'haslink' => bool].
     */
    protected function parse_links($raw): array {
        if (empty($raw)) {
            return [];
        }

        $links = [];
        foreach (preg_split('/\R/', $raw) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = array_map('trim', explode('|', $line, 2));
            $text = $parts[0];
            if ($text === '') {
                continue;
            }
            $url = $parts[1] ?? '';
            $links[] = [
                'text' => $text,
                'url' => $url,
                'haslink' => ($url !== ''),
                'isexternal' => (strpos($url, 'http') === 0),
            ];
        }
        return $links;
    }

    /**
     * Build the contact column from the Academi theme settings.
     *
     * @return array List of ['icon' => string, 'text' => string, 'url' => string, 'haslink' => bool].
     */
    protected function build_contact(): array {
        $rows = [];

        if (!function_exists('theme_academi_get_setting')) {
            return $rows;
        }

        $email = theme_academi_get_setting('emailid');
        if (!empty($email)) {
            $rows[] = ['icon' => 'fa fa-envelope', 'text' => $email, 'url' => 'mailto:' . $email, 'haslink' => true];
        }

        $phoneno = theme_academi_get_setting('phoneno');
        if (!empty($phoneno)) {
            // The theme stores several numbers in one field, separated by "|".
            foreach (explode('|', $phoneno) as $phone) {
                $phone = trim($phone);
                if ($phone === '') {
                    continue;
                }
                $rows[] = [
                    'icon' => 'fa fa-phone',
                    'text' => $phone,
                    'url' => 'tel:' . preg_replace('/[^0-9+]/', '', $phone),
                    'haslink' => true,
                ];
            }
        }

        $whatsapp = get_config('local_footer', 'whatsapp');
        if (!empty($whatsapp)) {
            $rows[] = [
                'icon' => 'fab fa-whatsapp',
                'text' => $whatsapp,
                'url' => 'https://api.whatsapp.com/send?phone=' . preg_replace('/[^0-9]/', '', $whatsapp),
                'haslink' => true,
            ];
        }

        $address = theme_academi_get_setting('address');
        if (!empty($address)) {
            $rows[] = ['icon' => 'fa fa-map-marker', 'text' => $address, 'url' => '', 'haslink' => false];
        }

        return $rows;
    }

    /**
     * Build the social icon list from the Academi theme settings.
     *
     * @return array List of ['icon' => string, 'url' => string, 'color' => string].
     */
    protected function build_social(): array {
        $social = [];

        if (!function_exists('theme_academi_get_setting')) {
            return $social;
        }

        $count = (int) theme_academi_get_setting('numofsocialmedia');
        for ($i = 1; $i <= $count; $i++) {
            if (!theme_academi_get_setting('socialmedia' . $i . '_status')) {
                continue;
            }
            $icon = theme_academi_get_setting('socialmedia' . $i . '_icon');
            $url = theme_academi_get_setting('socialmedia' . $i . '_url');
            if (empty($icon) || empty($url)) {
                continue;
            }
            $social[] = [
                'icon' => 'fab fa-' . $icon,
                'url' => $url,
                'color' => theme_academi_get_setting('socialmedia' . $i . '_iconcolor'),
            ];
        }

        return $social;
    }

    /**
     * Resolve the copyright line, preferring the Academi theme setting so the
     * portal footer and the LMS footer never drift apart.
     *
     * @return string HTML for the copyright line.
     */
    protected function build_copyright(): string {
        if (function_exists('theme_academi_get_setting')) {
            $copyright = theme_academi_get_setting('copyright_footer', 'format_html');
            if (!empty($copyright)) {
                return $copyright;
            }
        }

        return get_string(
            'poweredby',
            'local_footer'
        );
    }
}
