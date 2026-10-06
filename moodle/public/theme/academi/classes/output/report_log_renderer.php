<?php
namespace theme_academi\output;

use html_writer;
use moodle_url;
use report_log_renderable;
use stdClass;
use context_system;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/report/log/classes/renderer.php');

/**
 * Custom report_log renderer for theme_academi.
 *
 * Implements searchable user selector autocomplete and optimized filter layouts.
 */
class report_log_renderer extends \report_log_renderer {

    /**
     * Generates and displays the log report selector form with searchable participant autocomplete.
     *
     * @param report_log_renderable $reportlog
     */
    public function report_selector_form(report_log_renderable $reportlog) {
        global $PAGE;

        echo html_writer::start_tag('form', ['class' => 'logselecform', 'action' => $reportlog->url, 'method' => 'get']);
        echo html_writer::start_div('d-flex flex-wrap align-items-center gap-2 logfilter-row');
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'chooselog', 'value' => '1']);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'showusers', 'value' => $reportlog->showusers]);
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'showcourses', 'value' => $reportlog->showcourses]);

        $selectedcourseid = empty($reportlog->sitecoursefilter)
            ? (empty($reportlog->course) ? 0 : $reportlog->course->id)
            : $reportlog->sitecoursefilter;

        // 1. Course selector.
        echo $this->get_course_selector_field($reportlog, $selectedcourseid);

        // 2. Group selector (if available).
        $groups = $reportlog->get_group_list();
        if (!empty($groups)) {
            echo html_writer::label(get_string('selectagroup'), 'menugroup', false, ['class' => 'accesshide']);
            echo html_writer::select($groups, "group", $reportlog->groupid, get_string("allgroups"));
        }

        // 3. Participant selector (Searchable Autocomplete).
        echo html_writer::start_div('logfilter-user-wrapper position-relative');
        $selecteduserid = !empty($reportlog->userid) ? (int)$reportlog->userid : 0;
        $useroptions = [];
        if ($selecteduserid > 0) {
            $userfullname = $reportlog->get_selected_user_fullname();
            $useroptions[$selecteduserid] = $userfullname;
        } else {
            $useroptions[0] = '';
        }

        echo html_writer::label(get_string('user'), 'menuuser', false, ['class' => 'accesshide']);
        echo html_writer::select(
            $useroptions,
            'user',
            $selecteduserid,
            false,
            [
                'id' => 'menuuser',
                'class' => 'select form-select menuuser d-none',
                'style' => 'display: none !important; position: absolute !important; width: 0 !important; height: 0 !important; overflow: hidden !important;',
            ]
        );

        $placeholder = 'Search user...';
        $noselectionstring = '';

        $PAGE->requires->js_call_amd('core/form-autocomplete', 'enhance', [
            '#menuuser',
            false,
            'core_user/form_user_selector',
            $placeholder,
            false,
            true,
            $noselectionstring,
            true,
        ]);
        echo html_writer::end_div();

        // 4. Date selector.
        $dates = $reportlog->get_date_options();
        echo html_writer::label(get_string('date'), 'menudate', false, ['class' => 'accesshide']);
        echo html_writer::select($dates, "date", $reportlog->date, get_string("alldays"));

        // 5. Activity selector.
        echo $this->get_activity_selector_field($reportlog);

        // 6. Actions selector.
        echo html_writer::label(get_string('actions'), 'menumodaction', false, ['class' => 'accesshide']);
        echo html_writer::select($reportlog->get_actions(), 'modaction', $reportlog->action, get_string("allactions"));

        // 7. Sources (Origin).
        $origin = $reportlog->get_origin_options();
        echo html_writer::label(get_string('origin', 'report_log'), 'menuorigin', false, ['class' => 'accesshide']);
        echo html_writer::select($origin, 'origin', $reportlog->origin, false);

        // 8. Events (Edulevel).
        $edulevel = $reportlog->get_edulevel_options();
        echo html_writer::label(get_string('edulevel'), 'menuedulevel', false, ['class' => 'accesshide']);
        echo html_writer::select($edulevel, 'edulevel', $reportlog->edulevel, false) . $this->help_icon('edulevel');

        // 9. Reader option & submit button.
        $readers = $reportlog->get_readers(true);
        if (!empty($readers)) {
            if (count($readers) == 1) {
                $attributes = ['type' => 'hidden', 'name' => 'logreader', 'value' => key($readers)];
                echo html_writer::empty_tag('input', $attributes);
            } else {
                echo html_writer::label(get_string('selectlogreader', 'report_log'), 'menureader', false, ['class' => 'accesshide']);
                echo html_writer::select($readers, 'logreader', $reportlog->selectedlogreader, false);
            }
            echo html_writer::end_div(); // End .logfilter-row
            echo html_writer::start_div('mt-2');
            echo html_writer::empty_tag('input', [
                'type' => 'submit',
                'value' => get_string('gettheselogs'),
                'class' => 'btn btn-primary'
            ]);
            echo html_writer::end_div();
        } else {
            echo html_writer::end_div();
        }

        echo html_writer::end_tag('form');
    }

    /**
     * Generates the course selector field for the log report.
     *
     * @param report_log_renderable $reportlog
     * @param int $selectedcourseid
     * @return string
     */
    protected function get_course_selector_field(report_log_renderable $reportlog, int $selectedcourseid): string {
        if ($reportlog->isactivitypage || $reportlog->iscoursepage) {
            return html_writer::empty_tag(
                'input',
                ['type' => 'hidden', 'name' => 'id', 'value' => $selectedcourseid]
            );
        }

        $result = '';
        $sitecontext = context_system::instance();
        $courses = $reportlog->get_course_list();

        if (!empty($courses) && $reportlog->showcourses) {
            $result .= html_writer::label(get_string('selectacourse'), 'menuid', false, ['class' => 'accesshide']);
            $result .= html_writer::select($courses, "id", $selectedcourseid, null);
            return $result;
        }

        $courses = [];
        $courseinfo = ($selectedcourseid == SITEID) ? ' (' . get_string('site') . ') ' : '';
        $courses[$selectedcourseid] = get_course_display_name_for_list($reportlog->course) . $courseinfo;

        $result .= html_writer::label(get_string('selectacourse'), 'menuid', false, ['class' => 'accesshide']);
        $result .= html_writer::select($courses, "id", $selectedcourseid, false);

        if (has_capability('report/log:view', $sitecontext)) {
            $a = new stdClass();
            $a->url = new moodle_url(
                '/report/log/index.php',
                [
                    'chooselog' => 0,
                    'group' => $reportlog->get_selected_group(),
                    'user' => $reportlog->userid,
                    'id' => $selectedcourseid,
                    'date' => $reportlog->date,
                    'modid' => $reportlog->modid,
                    'showcourses' => 1,
                    'showusers' => $reportlog->showusers,
                ]
            );
            $a->url = $a->url->out(false);
            $result .= get_string('logtoomanycourses', 'moodle', $a);
        }

        return $result;
    }

    /**
     * Generates the activity selector field for the log report.
     *
     * @param report_log_renderable $reportlog
     * @return string
     */
    protected function get_activity_selector_field(report_log_renderable $reportlog): string {
        if ($reportlog->isactivitypage) {
            $result = html_writer::empty_tag(
                'input',
                ['type' => 'hidden', 'name' => 'isactivitypage', 'value' => $reportlog->isactivitypage]
            );
            $result .= html_writer::empty_tag(
                'input',
                ['type' => 'hidden', 'name' => 'modid', 'value' => $reportlog->modid]
            );
            return $result;
        }

        [$activities, $disabled] = $reportlog->get_activities_list();

        $result = html_writer::label(
            text: get_string('activities'),
            for: 'menumodid',
            colonize: false,
            attributes: ['class' => 'accesshide'],
        );
        $result .= html_writer::select(
            options: $activities,
            name: "modid",
            selected: $reportlog->modid,
            nothing: get_string("allactivities"),
            attributes: [],
            disabled: $disabled,
        );
        return $result;
    }

    /**
     * Render the log report with sanitized descriptions for large config values.
     *
     * Overrides the parent to post-process the rendered log table output,
     * replacing excessively large configuration values (e.g. theme_academi/customcss)
     * with concise placeholders. This is a presentation-layer fix only —
     * no stored data, events, or log records are modified.
     *
     * @param report_log_renderable $reportlog object of report_log.
     */
    protected function render_report_log(report_log_renderable $reportlog) {
        if (empty($reportlog->selectedlogreader)) {
            echo $this->output->notification(get_string('nologreaderenabled', 'report_log'), 'notifyproblem');
            return;
        }
        if ($reportlog->showselectorform) {
            $this->report_selector_form($reportlog);
        }

        if ($reportlog->showreport) {
            // Capture the table output so we can sanitize large config values.
            ob_start();
            $reportlog->tablelog->out($reportlog->perpage, true);
            $output = ob_get_clean();

            echo $this->sanitize_large_config_values($output);
        }
    }

    /**
     * Sanitize large configuration values in rendered log output.
     *
     * Handles two cases:
     * 1. Known large-value settings (theme_academi/customcss) — always replaced
     *    with a descriptive placeholder regardless of length.
     * 2. Any other config change description where a quoted value exceeds the
     *    display limit — truncated with an ellipsis indicator.
     *
     * @param string $output The rendered HTML output from the log table.
     * @return string The sanitized output.
     */
    protected function sanitize_large_config_values(string $output): string {
        // Maximum characters to display for a config value before truncating.
        $maxvaluelen = 200;

        // 1. Specifically handle theme_academi/customcss — always omit the value.
        //    Match the description pattern for customcss config changes.
        //    The pattern matches: from 'anything' to 'anything' where component is theme_academi
        //    and setting is customcss.
        $output = preg_replace_callback(
            "/changed the config setting &#039;customcss&#039; for component &#039;theme_academi&#039; from &#039;(.*?)&#039; to &#039;(.*?)&#039;\./s",
            function ($matches) {
                return "changed the config setting &#039;customcss&#039; for component &#039;theme_academi&#039; from &#039;[custom CSS omitted]&#039; to &#039;[custom CSS omitted]&#039;.";
            },
            $output
        );

        // Also handle the plain-text (non-HTML-encoded) variant for download formats.
        $output = preg_replace_callback(
            "/changed the config setting 'customcss' for component 'theme_academi' from '(.*?)' to '(.*?)'\./s",
            function ($matches) {
                return "changed the config setting 'customcss' for component 'theme_academi' from '[custom CSS omitted]' to '[custom CSS omitted]'.";
            },
            $output
        );

        // 2. General protection: truncate any remaining excessively large config values.
        //    This catches other large config settings that aren't specifically handled above.
        //    Match the HTML-encoded quote pattern: &#039;...very long value...&#039;
        //    within config change descriptions.
        $output = preg_replace_callback(
            "/(changed the config setting &#039;[^&]*?&#039; for component &#039;[^&]*?&#039; (?:from|to) &#039;)(.*?)(&#039;)/s",
            function ($matches) use ($maxvaluelen) {
                $value = $matches[2];
                if (mb_strlen($value) > $maxvaluelen) {
                    $truncated = mb_substr($value, 0, $maxvaluelen) . '… [value truncated, ' . mb_strlen($value) . ' chars total]';
                    return $matches[1] . $truncated . $matches[3];
                }
                return $matches[0];
            },
            $output
        );

        // Same for plain-text variant.
        $output = preg_replace_callback(
            "/(changed the config setting '[^']*?' for component '[^']*?' (?:from|to) ')(.*?)(')/s",
            function ($matches) use ($maxvaluelen) {
                $value = $matches[2];
                if (mb_strlen($value) > $maxvaluelen) {
                    $truncated = mb_substr($value, 0, $maxvaluelen) . '… [value truncated, ' . mb_strlen($value) . ' chars total]';
                    return $matches[1] . $truncated . $matches[3];
                }
                return $matches[0];
            },
            $output
        );

        return $output;
    }
}
