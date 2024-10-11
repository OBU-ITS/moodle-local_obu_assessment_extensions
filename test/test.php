<?php
//**
//  URL REMINDER: http://poodledev/moodle/local/obu_assessment_extensions/test/test.php
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

$event = \core\event\user_updated::create_from_userid(190187);
user_profile_updated_observer::user_profile_updated($event);