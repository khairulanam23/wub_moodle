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
 * Payment Hold Notice Page for Students with Outstanding Dues.
 *
 * @package    local_mass_enroll
 * @copyright  2026 World University of Bangladesh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/mass_enroll/classes/enrolhelper.php');

require_login();
global $USER, $PAGE, $OUTPUT, $CFG;

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/mass_enroll/payment_notice.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('payment_hold_title', 'local_mass_enroll'));
$PAGE->set_heading('');

$helper = new \enrolhelper();
$check = $helper->check_student_due_status((int)$USER->id);

// If user is allowed (admin, teacher, or dues <= 100), redirect to dashboard.
if (!empty($check) && isset($check['allowed']) && $check['allowed'] === true) {
    redirect(new moodle_url('/my/'));
}

$due_amount = isset($check['due']) ? (float)$check['due'] : 0.0;
$reason = !empty($check['reason']) ? $check['reason'] : get_string('payment_hold_message', 'local_mass_enroll');

$logout_url = new moodle_url('/login/logout.php', ['sesskey' => sesskey()]);
$landing_url = new moodle_url('/local/wub_landing/index.php');

echo $OUTPUT->header();
?>

<style>
.wub-payment-hold-container {
    max-width: 800px;
    margin: 40px auto;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}
.wub-payment-card {
    background: #ffffff;
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(10, 36, 106, 0.08);
    border: 1px solid #e2e8f0;
    overflow: hidden;
}
.wub-payment-header {
    background: linear-gradient(135deg, #0A246A 0%, #1e3a8a 100%);
    color: #ffffff;
    padding: 32px 28px;
    text-align: center;
}
.wub-payment-icon {
    width: 64px;
    height: 64px;
    background: rgba(239, 68, 68, 0.2);
    color: #fca5a5;
    border: 2px solid #ef4444;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    margin-bottom: 16px;
}
.wub-payment-body {
    padding: 32px 28px;
}
.wub-due-badge-box {
    background: #fff1f2;
    border: 1px solid #fecdd3;
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    margin-bottom: 24px;
}
.wub-due-amount {
    font-size: 32px;
    font-weight: 700;
    color: #e11d48;
    margin: 8px 0;
}
.wub-student-meta {
    background: #f8fafc;
    border-radius: 10px;
    padding: 16px 20px;
    margin-bottom: 24px;
    border: 1px solid #e2e8f0;
}
.wub-student-meta-row {
    display: flex;
    justify-content: space-between;
    padding: 6px 0;
    border-bottom: 1px dashed #cbd5e1;
    font-size: 14px;
}
.wub-student-meta-row:last-child {
    border-bottom: none;
}
.wub-instructions {
    color: #475569;
    font-size: 15px;
    line-height: 1.6;
    margin-bottom: 28px;
}
.wub-btn-group {
    display: flex;
    gap: 12px;
    justify-content: center;
    flex-wrap: wrap;
}
.wub-btn-primary {
    background: #0A246A;
    color: #ffffff;
    padding: 12px 24px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    transition: background 0.2s;
    display: inline-flex;
    align-items: center;
}
.wub-btn-primary:hover {
    background: #1e3a8a;
    color: #ffffff;
}
.wub-btn-outline {
    background: transparent;
    color: #475569;
    border: 1px solid #cbd5e1;
    padding: 12px 24px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
}
.wub-btn-outline:hover {
    background: #f1f5f9;
    color: #0f172a;
}
</style>

<div class="wub-payment-hold-container">
    <div class="wub-payment-card">
        <div class="wub-payment-header">
            <div class="wub-payment-icon">
                <i class="fa fa-exclamation-triangle"></i>
            </div>
            <h2 style="margin: 0; font-size: 24px; font-weight: 700; color: #ffffff;">Dashboard Access Restricted</h2>
            <p style="margin: 8px 0 0 0; color: #cbd5e1; font-size: 15px;">Outstanding Payment Required</p>
        </div>

        <div class="wub-payment-body">
            <div class="wub-due-badge-box">
                <div style="font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: #9f1239;">Remaining Dues in UMS</div>
                <div class="wub-due-amount"><?= number_format($due_amount, 2); ?> BDT</div>
                <div style="font-size: 13px; color: #881337;">Allowable Threshold: &le; 100.00 BDT</div>
            </div>

            <div class="wub-student-meta">
                <div class="wub-student-meta-row">
                    <span style="color: #64748b;">Student Name</span>
                    <strong style="color: #0f172a;"><?= s($USER->firstname . ' ' . $USER->lastname); ?></strong>
                </div>
                <div class="wub-student-meta-row">
                    <span style="color: #64748b;">Moodle Username</span>
                    <strong style="color: #0f172a;"><?= s($USER->username); ?></strong>
                </div>
                <?php if (!empty($USER->department)): ?>
                <div class="wub-student-meta-row">
                    <span style="color: #64748b;">Program</span>
                    <span style="color: #0f172a;"><?= s($USER->department); ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($USER->institution)): ?>
                <div class="wub-student-meta-row">
                    <span style="color: #64748b;">Batch</span>
                    <span style="color: #0f172a;"><?= s($USER->institution); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <div class="wub-instructions">
                <p><strong>Why is my dashboard locked?</strong></p>
                <p><?= s($reason); ?></p>
                <p>Under university guidelines, student access to the Moodle eLearning dashboard is restricted whenever outstanding fees exceed <strong>100 BDT</strong>. Your course enrolments remain secure in the system and will become immediately visible on your dashboard once your dues are settled.</p>
                <p><strong>How to restore dashboard access:</strong></p>
                <ol style="margin-left: 20px; padding-left: 0;">
                    <li>Log into your <strong><a href="https://wub.e-dhrubo.com/users" target="_blank" style="color: #0A246A; text-decoration: underline;">WUB UMS Portal</a></strong> or visit the Accounts Office.</li>
                    <li>Clear the outstanding balance of <strong><?= number_format($due_amount, 2); ?> BDT</strong>.</li>
                    <li>Once payment is recorded in UMS, log back into Moodle to access your dashboard and enrolled courses.</li>
                </ol>
            </div>

            <div class="wub-btn-group">
                <a href="https://wub.e-dhrubo.com/users" target="_blank" class="wub-btn-primary">
                    <i class="fa fa-external-link me-2"></i> Open UMS Portal
                </a>
                <a href="<?= $landing_url; ?>" class="wub-btn-outline">
                    <i class="fa fa-home me-2"></i> Home Page
                </a>
                <a href="<?= $logout_url; ?>" class="wub-btn-outline" style="color: #e11d48; border-color: #fecdd3;">
                    <i class="fa fa-sign-out me-2"></i> Log Out
                </a>
            </div>
        </div>
    </div>
</div>

<?php
echo $OUTPUT->footer();
