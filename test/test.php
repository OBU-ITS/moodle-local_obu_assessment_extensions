<?php
//**
//  URL REMINDER: http://poodledev/moodle/local/obu_assessment_extensions/test/test.php
//  */

namespace local_obu_assessment_extensions\test;
global $CFG, $DB;

require_once(__DIR__ . '/../../../config.php'); // Adjust the path as necessary
require_once($CFG->dirroot . '/local/obu_assessment_extensions/locallib.php');


defined('MOODLE_INTERNAL') || die();

if (!is_siteadmin()) {
    // Redirect to the site homepage
    redirect(new \moodle_url('/')); // Redirects to the homepage
    die(); // Ensure the script stops execution after redirect
}

//$sql = "SELECT cm.instance
//            FROM {course_modules} cm
//            JOIN {modules} m ON cm.module = m.id AND m.name = 'coursework'";
//
//$trace = new \null_progress_trace();
//
//$courseModuleInstanceIds = $DB->get_records_sql($sql);
//foreach($courseModuleInstanceIds as $courseModuleInstanceId) {
//    local_obu_assessment_ext_create_task_for_course_mod_change($trace, (int) $courseModuleInstanceId->instance);
//}


