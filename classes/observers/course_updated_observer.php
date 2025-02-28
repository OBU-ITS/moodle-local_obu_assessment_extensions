<?php

namespace local_obu_assessment_extensions\observers;

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
use core_reportbuilder\local\helpers\custom_fields;

/**
 * Plugin user profile updated event observer
 *
 * @package    local_obu_assessment_extensions
 * @author     Emir Kamel
 * @copyright  2024, Oxford Brookes University {@link http://www.brookes.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/obu_assessment_extensions/locallib.php');

class course_updated_observer {
    public static function course_updated(\core\event\course_updated $event) {
        $courseId = $event->objectid;
        $trace = new \null_progress_trace();
        self::course_updated_internal($trace, $courseId);
    }

    public static function course_updated_internal(\progress_trace $trace, $courseId) {
        global $DB;

        $course = get_course($courseId);

        if (!$course) {
            error_log("❌ ERROR: Could not retrieve course object for ID: " . $courseId);
            return;
        }

        // Is it a teaching module course, if not move on
        if (!preg_match("/^[0-9]{4}\.[A-Z]{3,4}[0-9]{4}_[A-Z][0-9]{1,2}_[0-9]/", $course->idnumber)) {
            return;
        }

        $handler = \core_customfield\handler::get_handler('core_course', 'course');
        $fields = $handler->get_instance_data($courseId);
        $targetFields = ["ap_code"]; //["ssbsect_score_cutoff_date", "ssbsect_reas_score_ctof_date"];
        $updateFields = [];
        $updateCourseworkDeadlines = false;

        foreach ($fields as $field) {
            $fieldname = $field->get_field()->get('shortname');

            if (!in_array($fieldname, $targetFields)) {
                continue;
            }

            $value = $field->get_value();
            if (strpos($value, '*') === 0) {
                $updateCourseworkDeadlines = true;

                $updateFields[] = (object)[
                    'id' => $field->get_field()->get('id'),
                    'instanceid' => $courseId,
                    'value' => ltrim($value, '*')
                ];

                error_log("📢 Relevant custom field change found: $fieldname - Value: " . $value);
            }
        }

        if ($updateCourseworkDeadlines && $DB->record_exists('coursework', ['course' => $courseId])) {
            $sql = "SELECT cm.instance
                FROM {course_modules} cm
                JOIN {modules} m ON cm.module = m.id AND m.name = 'coursework'
                WHERE course = :courseId";

            $courseModuleInstanceIds = $DB->get_record_sql($sql, ['courseId' => $courseId]);

            foreach($courseModuleInstanceIds as $courseModuleInstanceId) {
                local_obu_create_task_for_course_mod_change($trace, $courseModuleInstanceId->instance);
            }
        }

        if (!empty($updateFields)){
            $transaction = $DB->start_delegated_transaction();
            foreach ($updateFields as $updateField) {
                $DB->update_record('customfield_data', $updateField);
            }
            $transaction->allow_commit();

            error_log("✅ Bulk update completed for " . count($updateFields) . " fields.");
        }


    }
}