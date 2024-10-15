<?php
//**
//  URL REMINDER: http://poodledev/moodle/local/obu_assessment_extensions/test/deadline_changed_test.php
//  */

namespace local_obu_assessment_extensions\deadline_changed_test;
global $DB;
require_once(__DIR__ . '/../../../config.php');  // Include Moodle's config.php file
require_once($CFG->dirroot . '/course/modlib.php'); // Required to create course modules

defined('MOODLE_INTERNAL') || die();

use local_obu_assessment_extensions\observers\coursework_deadline_changed_observer;
use core\event\course_module_updated;

if (!is_siteadmin()) {
    // Redirect to the site homepage
    redirect(new \moodle_url('/')); // Redirects to the homepage
    die(); // Ensure the script stops execution after redirect
}

// Set up the course and module data
$courseId = 72417; // Replace with a valid course ID
$moduleId = 1; // Replace with a valid module ID (e.g., 'assign' module)
$section = 981884; // Section where the module will be added

// Create a dummy module instance (e.g., assignment)
$moduleInstance = new \stdClass();
$moduleInstance->course = $courseId;
$moduleInstance->name = 'Test Assignment in Code';
$moduleInstance->intro = 'This is a test assignment.';
$moduleInstance->introformat = FORMAT_HTML;
$moduleInstance->duedate = time() + 7 * 24 * 60 * 60; // Set due date to one week from now

// Insert the module instance (this will return the instance ID)
$instanceid = $DB->insert_record('assignment', $moduleInstance);

// Create a dummy course module
$courseModuleData = new \stdClass();
$courseModuleData->course = $courseId;
$courseModuleData->module = $moduleId;
$courseModuleData->section = $section;
$courseModuleData->instance = $instanceid;
$courseModuleData->visible = 1;
$courseModuleData->availability = json_encode([
    'op' => '|',
    'c' => [
        [
            'type' => 'group',
            'id' => 143418, // Replace with a valid group ID
        ]
    ],
    'showc' => [1]
]);

// Create the course module using Moodle's modlib.php function
$cmid = add_course_module($courseModuleData);

// Simulate the coursework deadline changed event
$event = course_module_updated::create([
    'context' => \context_module::instance($cmid),
    'objectid' => $cmid,
    'courseid' => $courseId,
    'other' => [
        'modulename' => 'assignment',
        'instanceid' => $instanceid,
        'availability' => null, // Simulating an "old" availability for comparison
        'name'=> $moduleInstance->name,
    ],
]);

// Trigger the observer manually
echo "Calling coursework deadline changed function from observer <br>";

coursework_deadline_changed_observer::coursework_deadline_changed($event);

echo "Coursework deadline changed function finished <br>";
// Output success
echo "Observer triggered successfully!<br>";
die();