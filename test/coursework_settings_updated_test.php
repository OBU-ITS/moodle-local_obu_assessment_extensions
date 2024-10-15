<?php
//**
//  Example Url: /local/obu_assessment_extensions/test/coursework_settings_updated_test.php?$objectid=208&desctype1
//  */

namespace local_obu_assessment_extensions\test;
global $CFG;

require_once(__DIR__ . '/../../../config.php'); // Adjust the path as necessary

defined('MOODLE_INTERNAL') || die();

use local_obu_assessment_extensions\observers\user_profile_updated_observer;
use core\event\user_updated;

if (!is_siteadmin()) {
    // Redirect to the site homepage
    redirect(new \moodle_url('/')); // Redirects to the homepage
    die(); // Ensure the script stops execution after redirect
}

$objectid = required_param('objectid', PARAM_INT);
$descriptiontype = optional_param('desctype', 0, PARAM_INT);

$description = "The user with id '35' has changed the following settings in Coursework with id '$objectid'<br>";
if($descriptiontype == 1) {
    $description = "The user with id '35' has changed the following settings in Coursework with id '$objectid'<br><b>deadline</b>: old value '1 Jan 1970, 01:00' new value '1 Jan 1970, 01:00'<br>";
}
else if ($descriptiontype == 2) {
    $description = "The user with id '35' has changed the following settings in Coursework with id '$objectid'<br><b>Access restriction</b>: old value '' new value 'Not available unless: You belong to <strong>⚠ (DO NOT EDIT) GEOG5003 (202409:1) - CWS2WEEK00-2 OE</strong>'<br>";
}
else if ($descriptiontype == 3) {
    $description = "The user with id '35' has changed the following settings in Coursework with id '$objectid'<br><b>deadline</b>: old value '1 Jan 1970, 01:00' new value '1 Jan 1970, 01:00'<br><b>enabledsubmissiontypes</b>: old value '' new value ''<br><b>Access restriction</b>: old value '' new value 'Not available unless: You belong to <strong>⚠ (DO NOT EDIT) GEOG5003 (202409:1) - CWS2WEEK00-2 OE</strong>'<br>";
}


$trace = new \html_progress_trace();
\local_obu_assessment_extensions\observers\coursemod_access_restriction_or_deadline_changed_observer::coursemod_access_restriction_or_deadline_changed_internal($trace, $objectid, $description);
$trace->finished();