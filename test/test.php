<?php
namespace local_obu_assessment_extensions\test;
global $DB;
require_once(__DIR__ . '/../../../config.php');  // Include Moodle's config.php file
require_once($CFG->dirroot . '/course/modlib.php'); // Required to create course modules

defined('MOODLE_INTERNAL') || die();

use local_obu_assessment_extensions\observers\coursemod_created_observer;
use core\event\course_module_created;

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

// Trigger the event manually to simulate the creation
$event = course_module_created::create([
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

//$event->trigger();

echo "creating observer \n";


echo "calling coursework deadline changed function from observer \n";
coursemod_created_observer::coursemod_created($event);
echo "coursework deadline changed function finished \n";
// Output success
echo "Observer triggered successfully!";