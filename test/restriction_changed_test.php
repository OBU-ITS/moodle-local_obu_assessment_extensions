<?php
//**
//  URL REMINDER: http://poodledev/moodle/local/obu_assessment_extensions/test/restriction_changed_test.php
//  */

namespace local_obu_assessment_extensions\restriction_changed_test;
global $DB;
require_once(__DIR__ . '/../../../config.php');  // Include Moodle's config.php file
require_once($CFG->dirroot . '/course/modlib.php'); // Required to create course modules

defined('MOODLE_INTERNAL') || die();

use local_obu_assessment_extensions\observers\coursemod_access_restriction_changed_observer;
use core\event\course_module_updated;

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
    'op' => 'OR',
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

// Trigger the event manually to simulate the update
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

// $event->trigger(); // We are manually calling the observer, so no need to trigger the event here.

echo "Creating observer for access restriction changed \n";
echo "Calling access restriction changed function from observer \n";

coursemod_access_restriction_changed_observer::coursemod_access_restriction_changed($event);

echo "Access restriction changed function finished \n";
// Output success
echo "Observer triggered successfully!";