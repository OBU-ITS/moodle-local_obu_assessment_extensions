<?php
/**
 *   URL REMINDER: /local/obu_assessment_extensions/test/calc_new_deadline_test.php?deadline=1729856173&extension-days=7
 *
 *   Test 1: /local/obu_assessment_extensions/test/calc_new_deadline_test.php?deadline=1729856173&extension-days=7
 *   Test 2: /local/obu_assessment_extensions/test/calc_new_deadline_test.php?deadline=1729856173&extension-days=7&marking-deadline=1629856173
 *   Test 3: /local/obu_assessment_extensions/test/calc_new_deadline_test.php?deadline=1629856173&extension-days=7
 **/

global $CFG;
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/local/obu_assessment_extensions/locallib.php');

defined('MOODLE_INTERNAL') || die();

if (!is_siteadmin()) {
    redirect(new \moodle_url('/')); // Redirects to the homepage
    die(); // Ensure the script stops execution after redirect
}


$deadline = required_param('deadline', PARAM_INT); // full date & time string
$extensionDays = required_param('extension-days', PARAM_INT);
$hardDeadline = optional_param('marking-deadline', 0, PARAM_INT); // string representation of Oracle date

$trace = new \html_progress_trace();
$newDeadline = calc_new_deadline($trace, $deadline, $extensionDays, $hardDeadline);
$trace->output("New deadline: $newDeadline");
$trace->finished();