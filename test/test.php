<?php
namespace local_obu_assessment_extensions\test;
require_once(__DIR__ . '/../../../config.php');  // Include Moodle's config.php file
defined('MOODLE_INTERNAL') || die();

use local_obu_assessment_extensions\observers\coursework_deadline_changed_observer;

// Set up a mock event data
echo "creating event data \n";

$eventData = array(
    'context' => \context_module::instance(2568925), // Replace with a valid context ID
    'objectid' => 2568925 // Replace with a valid course module ID
);
echo "event data created \n";

// Manually invoke the observer
echo "creating observer \n";
$observer = new coursework_deadline_changed_observer();
echo "observer created \n";

echo "calling coursework deadline changed function \n";

$observer::coursework_deadline_changed($eventData);
echo "coursework deadline changed function finished \n";

