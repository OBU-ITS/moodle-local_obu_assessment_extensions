<?php
//**
//  URL REMINDER: http://poodledev/moodle/local/obu_assessment_extensions/test/profile_updated_test.php
//  */

namespace local_obu_assessment_extensions\profile_updated_test;
global $DB;
require_once(__DIR__ . '/../../../config.php');  // Include Moodle's config.php file

defined('MOODLE_INTERNAL') || die();

use core\event\user_updated;
use local_obu_assessment_extensions\observers\user_profile_updated_observer;

if (!is_siteadmin()) {
    // Redirect to the site homepage
    redirect(new \moodle_url('/')); // Redirects to the homepage
    die(); // Ensure the script stops execution after redirect
}

// Set up the user data
$userId = 244224; // Replace with a valid user ID
$user = \core_user::get_user($userId);

// Simulate old and new profiles
$oldProfile = [
    'service_needs' => 0, // Previous value of service_needs
];
$newProfile = [
    'service_needs' => 'CWON', // Updated value of service_needs
];

// Simulate the event data
$event = user_updated::create([
    'context' => \context_user::instance($userId),
    'objectid' => $userId,
    'relateduserid' => $userId,
    'userid' => $userId,
    'other' => [
        'oldprofile' => $oldProfile,
        'profile' => $newProfile,
    ],
]);

// Trigger the observer manually to simulate the profile update
echo "Calling user profile updated function from observer <br>";

user_profile_updated_observer::user_profile_updated($event);

echo "User profile updated function finished\n";
// Output success
echo "Observer triggered successfully!";
die();