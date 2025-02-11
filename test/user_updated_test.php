<?php
//**
//  Example Url: /local/obu_assessment_extensions/test/user_updated_test.php?userid=208
//  */

namespace local_obu_assessment_extensions\test;

require_once(__DIR__ . '/../../../config.php'); // Adjust the path as necessary

defined('MOODLE_INTERNAL') || die();

if (!is_siteadmin()) {
    // Redirect to the site homepage
    redirect(new \moodle_url('/')); // Redirects to the homepage
    die(); // Ensure the script stops execution after redirect
}

$userid = required_param('userid', PARAM_INT);

$trace = new \html_progress_trace();
\local_obu_assessment_extensions\observers\user_profile_updated_observer::user_profile_updated_internal($trace, $userid);
$trace->finished();