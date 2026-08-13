<?php
define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/mass_enroll/classes/enrolhelper.php');

global $DB;

echo "=========================================================" . PHP_EOL;
echo "Starting Bulk UMS API to Local Moodle DB Synchronization" . PHP_EOL;
echo "=========================================================" . PHP_EOL;

$enrol_helper = new enrolhelper();

// 1. Fetch Programs from API
echo "Fetching programs from UMS API..." . PHP_EOL;
$programs = $enrol_helper->get_program();

if (empty($programs) || !is_array($programs)) {
    echo "No programs returned from UMS API." . PHP_EOL;
    exit(1);
}

echo "Found " . count($programs) . " programs." . PHP_EOL;

$total_synced_students = 0;
$total_batches_processed = 0;

// Process top programs to populate DB thoroughly
foreach ($programs as $prog) {
    $prog_obj = (object)$prog;
    $program_id = $prog_obj->id ?? '';
    $program_title = $prog_obj->title ?? $prog_obj->short_title ?? $program_id;

    if (empty($program_id)) continue;

    echo PHP_EOL . "---------------------------------------------------------" . PHP_EOL;
    echo "Processing Program [ID: $program_id] - $program_title" . PHP_EOL;
    echo "---------------------------------------------------------" . PHP_EOL;

    // 2. Fetch Batches for Program
    $batches = $enrol_helper->get_batches($program_id);
    if (empty($batches) || !is_array($batches)) {
        echo "   No batches found for program ID $program_id." . PHP_EOL;
        continue;
    }

    echo "   Found " . count($batches) . " batches." . PHP_EOL;

    // Process up to 5 active batches per program to ensure rich data populate
    $batch_count = 0;
    foreach ($batches as $batch) {
        $batch_obj = (object)$batch;
        $batch_name = $batch_obj->batch_title ?? $batch_obj->id ?? '';

        if (empty($batch_name)) continue;

        echo "   -> Syncing batch: $batch_name... ";
        
        $students = $enrol_helper->get_students([
            'program' => $program_id,
            'batch'   => $batch_name
        ]);

        $count = count($students);
        echo "Synced $count students." . PHP_EOL;

        $total_synced_students += $count;
        $total_batches_processed++;
        $batch_count++;

        if ($batch_count >= 5) {
            break;
        }
    }
}

echo PHP_EOL . "=========================================================" . PHP_EOL;
echo "Synchronization Completed Successfully!" . PHP_EOL;
echo "Total Batches Processed: $total_batches_processed" . PHP_EOL;
echo "Total Students Synced:   $total_synced_students" . PHP_EOL;

$user_count = $DB->count_records('user', ['deleted' => 0]);
$ums_user_count = $DB->count_records('enrol_ums_user');

echo "Total active users in mdl_user: $user_count" . PHP_EOL;
echo "Total records in mdl_enrol_ums_user: $ums_user_count" . PHP_EOL;
echo "=========================================================" . PHP_EOL;
