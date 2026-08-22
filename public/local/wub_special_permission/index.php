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
 * Administrative interface for searching students and managing temporary special login permissions.
 * Modern, responsive design utilizing standard Bootstrap 4/5 components and HTML5 date picker.
 *
 * @package    local_wub_special_permission
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_wub_special_permission\local\permission_manager;

require_login();

$context = context_system::instance();
require_capability('local/wub_special_permission:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/wub_special_permission/index.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('pluginname', 'local_wub_special_permission'));
$PAGE->set_heading(get_string('heading_title', 'local_wub_special_permission'));

$manager = new permission_manager();

$searchQuery = optional_param('search', '', PARAM_RAW);
$confirmOverwrite = optional_param('confirm_overwrite', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$student = null;
$statusInfo = null;

if (!empty($searchQuery)) {
    $student = $manager->search_student($searchQuery);
    if ($student) {
        $statusInfo = $manager->get_permission_status($student);
    }
}

// Handle Form Submission / POST actions
$defaultDate = ($statusInfo && !empty($statusInfo['permission_date'])) ? $statusInfo['permission_date'] : date('Y-m-d', strtotime('+7 days'));
$hasActive = ($statusInfo && $statusInfo['status'] === 'active');
$showOverwriteWarning = false;
$pendingDate = '';

if ($student && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    // Revoke action
    if (optional_param('revoke_button', null, PARAM_RAW) !== null || $action === 'revoke') {
        $manager->revoke_permission($student, (int)$USER->id);
        \core\notification::success(get_string('msg_permission_revoked', 'local_wub_special_permission', [
            'name' => fullname($student),
            'username' => $student->username
        ]));
        redirect(new moodle_url('/local/wub_special_permission/index.php', ['search' => $student->username]));
    }

    // Grant / Update action
    $submittedDate = optional_param('valid_until_date', '', PARAM_RAW);
    if (empty($submittedDate) && isset($_POST['valid_until_date'])) {
        $submittedDate = trim($_POST['valid_until_date']);
    }

    if (!empty($submittedDate)) {
        $selectedTime = strtotime($submittedDate . ' 23:59:59');
        if ($selectedTime === false || $selectedTime < strtotime('today')) {
            \core\notification::error(get_string('err_invalid_date', 'local_wub_special_permission'));
        } else {
            $expiryDateStr = date('Y-m-d', $selectedTime);

            // Check if active permission exists and overwrite confirmation is needed
            if ($hasActive && $confirmOverwrite === 0 && $expiryDateStr !== $statusInfo['permission_date']) {
                $showOverwriteWarning = true;
                $pendingDate = $expiryDateStr;
            } else {
                $manager->grant_permission($student, $expiryDateStr, (int)$USER->id);
                $formattedDate = userdate($selectedTime, get_string('strftimedatetime', 'langconfig'));
                \core\notification::success(get_string('msg_permission_granted', 'local_wub_special_permission', [
                    'name' => fullname($student),
                    'username' => $student->username,
                    'date' => $formattedDate
                ]));
                redirect(new moodle_url('/local/wub_special_permission/index.php', ['search' => $student->username]));
            }
        }
    }
}

echo $OUTPUT->header();
?>

<style>
.wub-sp-container {
    width: 100%;
    max-width: 1100px;
    margin: 0 auto;
    box-sizing: border-box;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}
.wub-sp-card {
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 4px 12px rgba(10, 36, 106, 0.05);
    margin-bottom: 24px;
    padding: 24px;
    box-sizing: border-box;
    width: 100%;
}
.wub-sp-card-title {
    font-size: 1.2rem;
    font-weight: 700;
    color: #0f172a;
    margin-top: 0;
    margin-bottom: 20px;
    border-bottom: 2px solid #f1f5f9;
    padding-bottom: 12px;
    display: flex;
    align-items: center;
}
.wub-badge-active {
    background-color: #dcfce7;
    color: #15803d;
    border: 1px solid #bbf7d0;
    padding: 8px 16px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
}
.wub-badge-expired {
    background-color: #fef3c7;
    color: #b45309;
    border: 1px solid #fde68a;
    padding: 8px 16px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
}
.wub-badge-none {
    background-color: #f1f5f9;
    color: #64748b;
    border: 1px solid #e2e8f0;
    padding: 8px 16px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
}
.wub-student-meta-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    background: #f8fafc;
    border-radius: 10px;
    padding: 20px;
    border: 1px solid #e2e8f0;
}
.wub-meta-item label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    color: #64748b;
    margin-bottom: 4px;
    display: block;
    font-weight: 600;
}
.wub-meta-item strong {
    font-size: 15px;
    color: #0f172a;
    word-break: break-word;
}
.wub-date-input-box {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 0 8px 8px 0;
    padding: 10px 14px;
    font-size: 16px;
    color: #0f172a;
    font-weight: 600;
    width: 100%;
}
.wub-date-input-box:focus {
    border-color: var(--wub-primary, #0f6cbf);
    box-shadow: 0 0 0 3px rgba(10, 36, 106, 0.15);
    outline: none;
}
</style>

<div class="wub-sp-container">
    <!-- Student Search Card -->
    <div class="wub-sp-card">
        <h3 class="wub-sp-card-title">
            <i class="fa fa-search me-2" style="color: var(--wub-primary, #0f6cbf);"></i>
            <?= get_string('search_student_heading', 'local_wub_special_permission'); ?>
        </h3>
        <form method="get" action="index.php">
            <div class="row g-2 align-items-start">
                <div class="col-12 col-md-8 col-lg-9">
                    <label for="search" class="visually-hidden"><?= get_string('search_input_label', 'local_wub_special_permission'); ?></label>
                    <input type="text" class="form-control form-control-lg" id="search" name="search"
                           value="<?= s($searchQuery); ?>"
                           placeholder="<?= get_string('search_input_label', 'local_wub_special_permission'); ?>" required>
                </div>
                <div class="col-12 col-md-4 col-lg-3">
                    <button type="submit" class="btn btn-primary btn-lg w-100" style="background-color: var(--wub-primary, #0f6cbf); border-color: var(--wub-primary, #0f6cbf);">
                        <i class="fa fa-search me-1"></i> <?= get_string('search_button', 'local_wub_special_permission'); ?>
                    </button>
                </div>
                <div class="col-12">
                    <div class="form-text text-muted mt-1"><?= get_string('search_help', 'local_wub_special_permission'); ?></div>
                </div>
            </div>
        </form>
    </div>

    <?php if (!empty($searchQuery) && !$student): ?>
        <div class="alert alert-warning border-warning shadow-sm">
            <i class="fa fa-exclamation-triangle me-2"></i>
            <?= get_string('student_not_found', 'local_wub_special_permission'); ?>
        </div>
    <?php endif; ?>

    <?php if ($student && $statusInfo): ?>
        <!-- Student Information Card -->
        <div class="wub-sp-card">
            <h3 class="wub-sp-card-title">
                <i class="fa fa-user me-2" style="color: var(--wub-primary, #0f6cbf);"></i>
                <?= get_string('student_details_heading', 'local_wub_special_permission'); ?>
            </h3>
            <div class="wub-student-meta-grid">
                <div class="wub-meta-item">
                    <label><?= get_string('label_student_id', 'local_wub_special_permission'); ?></label>
                    <strong><?= s($student->username); ?></strong>
                </div>
                <div class="wub-meta-item">
                    <label><?= get_string('label_student_name', 'local_wub_special_permission'); ?></label>
                    <strong><?= s(fullname($student)); ?></strong>
                </div>
                <div class="wub-meta-item">
                    <label><?= get_string('label_student_email', 'local_wub_special_permission'); ?></label>
                    <strong><?= s($student->email); ?></strong>
                </div>
                <?php if (!empty($student->department)): ?>
                <div class="wub-meta-item">
                    <label><?= get_string('label_program', 'local_wub_special_permission'); ?></label>
                    <strong><?= s($student->department); ?></strong>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Permission Status Card -->
        <div class="wub-sp-card">
            <h3 class="wub-sp-card-title">
                <i class="fa fa-shield me-2" style="color: var(--wub-primary, #0f6cbf);"></i>
                <?= get_string('permission_status_heading', 'local_wub_special_permission'); ?>
            </h3>
            <div class="d-flex flex-wrap align-items-center gap-3">
                <?php if ($statusInfo['status'] === 'active'): ?>
                    <span class="wub-badge-active">
                        <i class="fa fa-check-circle me-2"></i> <?= get_string('status_active', 'local_wub_special_permission'); ?>
                    </span>
                    <span class="text-dark font-weight-bold fs-6">
                        <?= get_string('valid_until_format', 'local_wub_special_permission', s($statusInfo['formatted_expiry'])); ?>
                    </span>
                <?php elseif ($statusInfo['status'] === 'expired'): ?>
                    <span class="wub-badge-expired">
                        <i class="fa fa-clock-o me-2"></i> <?= get_string('status_expired', 'local_wub_special_permission'); ?>
                    </span>
                    <span class="text-muted fs-6">
                        <?= get_string('expired_on_format', 'local_wub_special_permission', s($statusInfo['formatted_expiry'])); ?>
                    </span>
                <?php else: ?>
                    <span class="wub-badge-none">
                        <i class="fa fa-minus-circle me-2"></i> <?= get_string('status_none', 'local_wub_special_permission'); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Overwrite Confirmation Warning Alert -->
        <?php if ($showOverwriteWarning): ?>
            <div class="alert alert-warning border-warning shadow-sm mb-4 p-4">
                <h4 class="alert-heading font-weight-bold text-dark">
                    <i class="fa fa-exclamation-triangle me-2 text-warning"></i>
                    <?= get_string('overwrite_warning_title', 'local_wub_special_permission'); ?>
                </h4>
                <p class="mb-3 text-dark"><?= get_string('overwrite_warning_msg', 'local_wub_special_permission', s($statusInfo['formatted_expiry'])); ?></p>
                <hr>
                <form method="post" action="index.php?search=<?= urlencode($searchQuery); ?>">
                    <input type="hidden" name="sesskey" value="<?= sesskey(); ?>">
                    <input type="hidden" name="student_id" value="<?= (int)$student->id; ?>">
                    <input type="hidden" name="search" value="<?= s($searchQuery); ?>">
                    <input type="hidden" name="valid_until_date" value="<?= s($pendingDate); ?>">
                    <input type="hidden" name="confirm_overwrite" value="1">
                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-warning font-weight-bold px-4">
                            <i class="fa fa-check me-1"></i> <?= get_string('confirm_grant_button', 'local_wub_special_permission'); ?>
                        </button>
                        <a href="index.php?search=<?= urlencode($searchQuery); ?>" class="btn btn-secondary px-4">Cancel</a>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- Grant / Revoke Form Card -->
        <div class="wub-sp-card">
            <h3 class="wub-sp-card-title">
                <i class="fa fa-calendar-check-o me-2" style="color: var(--wub-primary, #0f6cbf);"></i>
                <?= get_string('grant_permission_heading', 'local_wub_special_permission'); ?>
            </h3>
            
            <form method="post" action="index.php?search=<?= urlencode($searchQuery); ?>" class="w-100">
                <input type="hidden" name="sesskey" value="<?= sesskey(); ?>">
                <input type="hidden" name="student_id" value="<?= (int)$student->id; ?>">
                <input type="hidden" name="search" value="<?= s($searchQuery); ?>">
                
                <label for="valid_until_date" class="form-label font-weight-bold text-dark mb-2">
                    <?= get_string('valid_until_label', 'local_wub_special_permission'); ?>
                </label>
                
                <div class="row g-2 align-items-start">
                    <div class="col-12 col-md-7 col-lg-8">
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-light text-muted border-end-0">
                                <i class="fa fa-calendar"></i>
                            </span>
                            <input type="date" class="form-control form-control-lg border-start-0 ps-2 wub-date-input-box" 
                                   id="valid_until_date" name="valid_until_date" 
                                   value="<?= s($defaultDate); ?>" 
                                   min="<?= date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    
                    <div class="col-12 col-md-5 col-lg-4">
                        <div class="d-flex flex-column flex-sm-row gap-2 w-100">
                            <button type="submit" class="btn btn-primary btn-lg flex-fill font-weight-bold" style="background-color: var(--wub-primary, #0f6cbf); border-color: var(--wub-primary, #0f6cbf);">
                                <i class="fa fa-check-circle me-1"></i> <?= get_string('grant_button', 'local_wub_special_permission'); ?>
                            </button>
                            <?php if ($hasActive): ?>
                                <button type="submit" name="revoke_button" value="1" class="btn btn-outline-danger btn-lg font-weight-bold" onclick="return confirm('Are you sure you want to revoke special permission for this student?');">
                                    <i class="fa fa-trash me-1"></i> <?= get_string('revoke_button', 'local_wub_special_permission'); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="form-text text-muted mt-1">
                            <i class="fa fa-info-circle me-1"></i> Special permission will remain valid until 23:59:59 on the selected date.
                        </div>
                    </div>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php
echo $OUTPUT->footer();
