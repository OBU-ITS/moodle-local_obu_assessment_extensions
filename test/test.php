<?php
//**
//  URL REMINDER: http://poodledev/moodle/local/obu_assessment_extensions/test/test.php
//  */

namespace local_obu_assessment_extensions\test;
global $CFG;

require_once(__DIR__ . '/../../../config.php');  // Include Moodle's config.php file
require_once($CFG->dirroot . '/local/obu_assessment_extensions/externallib.php');
require_once($CFG->dirroot . '/local/obu_assessment_extensions/classes/task/process_exceptional_circumstances.php');

defined('MOODLE_INTERNAL') || die();

use core\check\result;
use local_obu_assessment_extensions_external;

if (!is_siteadmin()) {
    // Redirect to the site homepage
    redirect(new \moodle_url('/')); // Redirects to the homepage
    die(); // Ensure the script stops execution after redirect
}

//$result = local_obu_assessment_extensions_external::award_exceptional_circumstance('19017277', 5, 'TESTCOURSEWORK');
//echo "done with result: " . var_dump($result);

//$task = new \local_obu_assessment_extensions\task\process_exceptional_circumstances();
//$task->execute();

//echo "Task executed successfully.";
//die();