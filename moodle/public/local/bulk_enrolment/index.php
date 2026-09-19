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
 * Unified portal for WUB Bulk Course Enrolment, Unenrolment & UMS Integration Diagnostics.
 *
 * @package    local_bulk_enrolment
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Security & Context.
require_login();
$context = context_system::instance();
require_capability('local/bulk_enrolment:view', $context);

$tab = optional_param('tab', 'enrol', PARAM_ALPHA);
$action = optional_param('action', '', PARAM_ALPHAEXT);

$PAGE->set_url(new moodle_url('/local/bulk_enrolment/index.php', ['tab' => $tab]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'local_bulk_enrolment'));
$PAGE->set_heading(get_string('pluginname', 'local_bulk_enrolment'));
$PAGE->set_pagelayout('admin');

$renderer = $PAGE->get_renderer('local_bulk_enrolment');

// Handle Actions across tabs.
$testresult = null;
$notice = null;
$noticetype = 'info';

// 1. UMS Diagnostics Actions.
if ($tab === 'ums' && $action && confirm_sesskey()) {
    require_capability('local/bulk_enrolment:manage', $context);
    $client = new \local_bulk_enrolment\api_client();

    if ($action === 'test_connection') {
        $testresult = $client->test_connection();
    } else if ($action === 'purge_cache') {
        cache::make('local_bulk_enrolment', 'programs')->purge();
        cache::make('local_bulk_enrolment', 'batches')->purge();
        $notice = get_string('diag_cache_purged', 'local_bulk_enrolment');
        $noticetype = 'success';
    }
}

// 2. Student Synchronization Actions (Sync Tab).
$inspectResult = null;
$inspectQuery = '';
$syncComparison = null;
$syncReport = null;
$selectedProgramId = optional_param('program_id', '', PARAM_RAW_TRIMMED);
$selectedBatchId = optional_param('batch_id', '0', PARAM_RAW_TRIMMED);

if ($tab === 'sync' && $action !== '' && confirm_sesskey()) {
    require_capability('local/bulk_enrolment:manage', $context);
    $syncService = new \local_bulk_enrolment\service\sync_service();

    if ($action === 'inspect_identity') {
        $inspectQuery = optional_param('inspect_query', '', PARAM_RAW_TRIMMED);
        if (!empty($inspectQuery)) {
            $identService = new \local_bulk_enrolment\service\student_identity_service();
            $inspectResult = $identService->resolve_identifier($inspectQuery);
        }
    } else if ($action === 'compare_sync') {
        if (!empty($selectedProgramId)) {
            try {
                $syncComparison = $syncService->compare_students($selectedProgramId, $selectedBatchId);
            } catch (\Throwable $e) {
                $notice = 'Failed to compare UMS students: ' . $e->getMessage();
                $noticetype = 'danger';
            }
        }
    } else if ($action === 'execute_sync') {
        $selectedUsernames = optional_param_array('selected_usernames', [], PARAM_RAW_TRIMMED);
        if (!empty($selectedUsernames) && !empty($selectedProgramId)) {
            try {
                $syncReport = $syncService->execute_sync($selectedUsernames, $selectedProgramId, $selectedBatchId);
                $notice = "Synchronization completed: {$syncReport['created']} account(s) created, {$syncReport['updated']} account(s) updated, {$syncReport['skipped']} skipped.";
                $noticetype = ($syncReport['failed'] > 0) ? 'warning' : 'success';
            } catch (\Throwable $e) {
                $notice = 'Failed to execute synchronization: ' . $e->getMessage();
                $noticetype = 'danger';
            }
        }
    }
}

// 3. Unenrolment Actions.
$unenrolPreview = null;
$unenrolReport = null;
if ($tab === 'unenrol' && $action && confirm_sesskey()) {
    require_capability('local/bulk_enrolment:unenrol', $context);
    $unenrolService = new \local_bulk_enrolment\service\unenrolment_service();
    $targetCourseId = optional_param('course_id', 0, PARAM_INT);

    if ($action === 'preview') {
        $rawText = optional_param('student_identifiers', '', PARAM_RAW);
        $identifiers = preg_split('/[\r\n,]+/', $rawText, -1, PREG_SPLIT_NO_EMPTY);

        // Check if CSV file was uploaded with strict validation.
        if (isset($_FILES['csv_file']) && !empty($_FILES['csv_file']['tmp_name'])) {
            if ($_FILES['csv_file']['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
                $notice = get_string('error_file_upload', 'moodle');
                $noticetype = 'danger';
            } else {
                $filename = $_FILES['csv_file']['name'] ?? '';
                $filesize = (int)($_FILES['csv_file']['size'] ?? 0);
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                if (!in_array($ext, ['csv', 'txt'], true)) {
                    $notice = get_string('invalidfiletype', 'error', $ext);
                    $noticetype = 'danger';
                } else if ($filesize > 2 * 1024 * 1024) {
                    $notice = get_string('maxbytesizes', 'moodle', display_size(2 * 1024 * 1024));
                    $noticetype = 'danger';
                } else {
                    $fileContent = file_get_contents($_FILES['csv_file']['tmp_name']);
                    $csvLines = preg_split('/[\r\n]+/', $fileContent, -1, PREG_SPLIT_NO_EMPTY);
                    foreach ($csvLines as $l) {
                        $cols = str_getcsv($l);
                        if (!empty($cols[0])) {
                            $identifiers[] = trim($cols[0]);
                        }
                    }
                }
            }
        }

        try {
            $unenrolPreview = $unenrolService->preview_unenrolment($targetCourseId, $identifiers);
        } catch (\Throwable $e) {
            $notice = $e->getMessage();
            $noticetype = 'danger';
        }
    } else if ($action === 'execute') {
        $userIds = optional_param_array('user_ids', [], PARAM_INT);
        try {
            $unenrolReport = $unenrolService->execute_unenrolment($targetCourseId, $userIds);
            $notice = "Successfully unenrolled {$unenrolReport['unenrolled']} student(s).";
            $noticetype = 'success';
        } catch (\Throwable $e) {
            $notice = $e->getMessage();
            $noticetype = 'danger';
        }
    }
}

echo $OUTPUT->header();

// Render Unified Navigation Tabs.
$enrolUrl = new moodle_url('/local/bulk_enrolment/index.php', ['tab' => 'enrol']);
$unenrolUrl = new moodle_url('/local/bulk_enrolment/index.php', ['tab' => 'unenrol']);
$syncUrl = new moodle_url('/local/bulk_enrolment/index.php', ['tab' => 'sync']);
$umsUrl = new moodle_url('/local/bulk_enrolment/index.php', ['tab' => 'ums']);
$settingsUrl = new moodle_url('/admin/settings.php', ['section' => 'local_bulk_enrolment']);

echo '<ul class="nav nav-tabs mb-4">';
echo '<li class="nav-item">';
echo '<a class="nav-link ' . ($tab === 'enrol' ? 'active font-weight-bold' : '') . '" href="' . $enrolUrl->out() . '">';
echo '<i class="fa fa-users mr-1"></i> ' . get_string('tab_enrol', 'local_bulk_enrolment');
echo '</a>';
echo '</li>';

echo '<li class="nav-item">';
echo '<a class="nav-link ' . ($tab === 'unenrol' ? 'active font-weight-bold' : '') . '" href="' . $unenrolUrl->out() . '">';
echo '<i class="fa fa-user-minus mr-1"></i> ' . get_string('tab_unenrol', 'local_bulk_enrolment');
echo '</a>';
echo '</li>';

echo '<li class="nav-item">';
echo '<a class="nav-link ' . ($tab === 'sync' ? 'active font-weight-bold' : '') . '" href="' . $syncUrl->out() . '">';
echo '<i class="fa fa-sync-alt mr-1"></i> ' . get_string('tab_sync', 'local_bulk_enrolment');
echo '</a>';
echo '</li>';

echo '<li class="nav-item">';
echo '<a class="nav-link ' . ($tab === 'ums' ? 'active font-weight-bold' : '') . '" href="' . $umsUrl->out() . '">';
echo '<i class="fa fa-network-wired mr-1"></i> ' . get_string('tab_ums', 'local_bulk_enrolment');
echo '</a>';
echo '</li>';

if (has_capability('local/bulk_enrolment:manage', $context)) {
    echo '<li class="nav-item ml-auto">';
    echo '<a class="nav-link text-secondary" href="' . $settingsUrl->out() . '">';
    echo '<i class="fa fa-cog mr-1"></i> Settings';
    echo '</a>';
    echo '</li>';
}
echo '</ul>';

if ($tab === 'ums') {
    // ═══════════════════════════════════════════════════════════════
    // TAB: UMS Integration & Diagnostics Console
    // ═══════════════════════════════════════════════════════════════
    $client = new \local_bulk_enrolment\api_client();
    $templatedata = [
        'sesskey' => sesskey(),
        'action_url' => (new moodle_url('/local/bulk_enrolment/index.php'))->out(false),
        'settings_url' => $settingsUrl->out(false),
        'is_enabled' => (bool)get_config('local_bulk_enrolment', 'enabled'),
        'is_configured' => $client->is_configured(),
        'base_url' => (string)(get_config('local_bulk_enrolment', 'base_url') ?: 'https://api.e-dhrubo.com'),
        'connect_timeout' => (int)(get_config('local_bulk_enrolment', 'connect_timeout') ?: 10),
        'timeout' => (int)(get_config('local_bulk_enrolment', 'timeout') ?: 30),
        'ssl_verify' => (bool)get_config('local_bulk_enrolment', 'ssl_verify'),
        'has_test_result' => ($testresult !== null),
        'test_result' => $testresult,
        'notice' => !empty($notice),
        'notice_message' => $notice,
        'notice_type' => $noticetype,
    ];

    echo $renderer->render_ums_diagnostics($templatedata);

} else if ($tab === 'unenrol') {
    // ═══════════════════════════════════════════════════════════════
    // TAB: Bulk Unenrolment Workspace
    // ═══════════════════════════════════════════════════════════════
    $courses = $DB->get_records_select('course', 'id > 1 AND visible = 1', null, 'fullname ASC', 'id, fullname, shortname');
    $courselist = [];
    $selectedCid = optional_param('course_id', 0, PARAM_INT);
    foreach ($courses as $c) {
        $courselist[] = [
            'id' => (int)$c->id,
            'fullname' => format_string($c->fullname),
            'shortname' => format_string($c->shortname),
            'selected' => ((int)$c->id === $selectedCid),
        ];
    }

    $previewRows = [];
    if (!empty($unenrolPreview['rows'])) {
        $sl = 1;
        foreach ($unenrolPreview['rows'] as $r) {
            $r['sl'] = $sl++;
            $previewRows[] = $r;
        }
    }

    $templatedata = [
        'sesskey' => sesskey(),
        'action_url' => (new moodle_url('/local/bulk_enrolment/index.php'))->out(false),
        'export_template_url' => (new moodle_url('/local/bulk_enrolment/export.php', ['action' => 'template', 'type' => 'unenrol']))->out(false),
        'courses' => $courselist,
        'course_id' => $selectedCid,
        'student_identifiers' => optional_param('student_identifiers', '', PARAM_RAW),
        'notice' => !empty($notice),
        'notice_message' => $notice,
        'notice_type' => $noticetype,
        'has_preview' => ($unenrolPreview !== null),
        'course_name' => $unenrolPreview['course_name'] ?? '',
        'preview_total' => $unenrolPreview['total'] ?? 0,
        'preview_unenrolable' => $unenrolPreview['unenrolable'] ?? 0,
        'preview_not_enrolled' => $unenrolPreview['not_enrolled'] ?? 0,
        'preview_rows' => $previewRows,
        'can_execute' => (!empty($unenrolPreview['unenrolable']) && $unenrolPreview['unenrolable'] > 0),
        'has_report' => ($unenrolReport !== null),
        'report_unenrolled' => $unenrolReport['unenrolled'] ?? 0,
    ];

    echo $renderer->render_unenrolment_page($templatedata);

} else if ($tab === 'sync') {
    // ═══════════════════════════════════════════════════════════════
    // TAB: Student Synchronization & Identity Resolution
    // ═══════════════════════════════════════════════════════════════
    $apiClient = new \local_bulk_enrolment\api_client();
    $programList = [];
    $batchList = [];

    if ($apiClient->is_configured()) {
        try {
            $programs = $apiClient->get_programs();
            foreach ($programs as $p) {
                $programList[] = [
                    'id' => (string)$p->id,
                    'name' => $p->get_label(),
                    'selected' => ($p->id === $selectedProgramId),
                ];
            }

            if (!empty($selectedProgramId)) {
                // The roster API identifies batches by TITLE, so the option value is the title.
                $batches = $apiClient->get_batches($selectedProgramId);
                foreach ($batches as $b) {
                    $batchList[] = [
                        'id' => (string)$b->title,
                        'name' => $b->title . ($b->shift !== '' ? ' (' . $b->shift . ')' : ''),
                        'selected' => (strcasecmp($b->title, (string)$selectedBatchId) === 0),
                    ];
                }
            }
        } catch (\local_bulk_enrolment\exception\ums_exception $e) {
            $notice = get_string('uierr_' . strtolower($e->get_error_code()), 'local_bulk_enrolment');
            $noticetype = 'danger';
        }
    }

    $templatedata = [
        'sesskey' => sesskey(),
        'action_url' => (new moodle_url('/local/bulk_enrolment/index.php'))->out(false),
        'programs' => $programList,
        'batches' => $batchList,
        'selected_program_id' => $selectedProgramId,
        'selected_batch_id' => $selectedBatchId,
        'has_comparison' => ($syncComparison !== null),
        'comparison' => $syncComparison,
        'has_sync_report' => ($syncReport !== null),
        'sync_report' => $syncReport,
        'inspect_query' => $inspectQuery,
        'has_inspect_result' => ($inspectResult !== null),
        'inspect_found' => ($inspectResult !== null && $inspectResult['status'] === 'RESOLVED'),
        'inspect_status' => $inspectResult['status'] ?? '',
        'inspect_user' => $inspectResult['user'] ?? null,
        'inspect_matched_field' => $inspectResult['matched_field'] ?? '',
        'notice' => !empty($notice),
        'notice_message' => $notice,
        'notice_type' => $noticetype,
    ];

    $PAGE->requires->js_call_amd('local_bulk_enrolment/sync_workspace', 'init');
    echo $renderer->render_sync_page($templatedata);

} else {
    // ═══════════════════════════════════════════════════════════════
    // TAB: Bulk Enrolment Workspace (Default)
    // ═══════════════════════════════════════════════════════════════
    // 1. Fetch Course Categories.
    $categories = $DB->get_records('course_categories', null, 'name ASC', 'id, name');
    $catlist = [];
    foreach ($categories as $cat) {
        $catlist[] = [
            'id' => (int)$cat->id,
            'name' => format_string($cat->name),
        ];
    }

    // 2. Fetch all non-frontpage courses (hidden courses are legitimate enrolment targets while being prepared).
    $courses = $DB->get_records_select('course', 'id > 1', null, 'visible DESC, fullname ASC', 'id, fullname, shortname, category, visible');
    $courselist = [];
    foreach ($courses as $c) {
        $courselist[] = [
            'id' => (int)$c->id,
            'fullname' => format_string($c->fullname),
            'shortname' => format_string($c->shortname) . ($c->visible ? '' : ' · hidden'),
            'category' => (int)$c->category,
        ];
    }


    // 4. Fetch Standard Assignable Roles.
    $roles = $DB->get_records('role', ['archetype' => 'student'], 'name ASC', 'id, name, shortname');
    if (empty($roles)) {
        $roles = $DB->get_records('role', null, 'name ASC', 'id, name, shortname');
    }
    $rolelist = [];
    foreach ($roles as $r) {
        $rolelist[] = [
            'id' => (int)$r->id,
            'name' => role_get_name($r),
        ];
    }

    $templatedata = [
        'sesskey' => sesskey(),
        'categories' => $catlist,
        'courses' => $courselist,
        'assignable_roles' => $rolelist,
        'export_template_enrol_url' => (new moodle_url('/local/bulk_enrolment/export.php', ['action' => 'template', 'type' => 'enrol']))->out(false),
    ];

    // Initialize AMD Controller.
    $PAGE->requires->js_call_amd('local_bulk_enrolment/workspace', 'init');

    echo $renderer->render_enrolment_page($templatedata);
}

echo $OUTPUT->footer();
