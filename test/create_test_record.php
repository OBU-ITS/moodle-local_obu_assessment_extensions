<?php
//**
//  Example Url: /local/obu_assessment_extensions/test/create_test_record.php
//  Example Url: /local/obu_assessment_extensions/test/create_test_record.php?action=insert&date=20%2F12%2F2024%2017%3A00
//  Example Url: /local/obu_assessment_extensions/test/create_test_record.php?action=insert&date=20%2F12%2F2024%2017%3A00&type=coursework_temporary_exemption
//  */

namespace local_obu_assessment_extensions\test;

require_once(__DIR__ . '/../../../config.php'); // Adjust the path as necessary

defined('MOODLE_INTERNAL') || die();

if (!is_siteadmin()) {
    // Redirect to the site homepage
    redirect(new \moodle_url('/')); // Redirects to the homepage
    die(); // Ensure the script stops execution after redirect
}

$action = optional_param('action', 'delete', PARAM_TEXT); // insert, update, delete
$date = optional_param('date', null, PARAM_TEXT);
$dateText = $date ?? 'No Date';
$type = optional_param('type', 'coursework_mitigations', PARAM_TEXT); // coursework_mitigations, coursework_temporary_exemption

$trace = new \html_progress_trace();

$trace->output("Action: $action");
$trace->output("Date: $dateText");
$trace->output("Type: $type");

$dueDateChange = new \stdClass();
$dueDateChange->user  = '19001001';
$dueDateChange->course  = '2024.ACFI6015_S12_1';
$dueDateChange->assessment = '2024.ACFI6015_S12_1_202409_76487_CWS1WEEK08-1_70179_OE';
$dueDateChange->date = $date;
$dueDateChange->timelimit = null;
$dueDateChange->type = $type;
$dueDateChange->reason_code = null;
$dueDateChange->reason_desc = null;
$dueDateChange->action = $action;
$dueDateChange->timecreated = time();

global $DB;

try {
    $id = $DB->insert_record('module_extensions_queue', $dueDateChange);
    $trace->output("Row created with ID: $id");
}
catch (\moodle_exception $e) {
    $trace->output($e->getMessage());
    $trace->output($e->getFile());
    $trace->output($e->getTraceAsString());
    $trace->output($e->debuginfo);
}

$trace->output("Completed");

$trace->finished();