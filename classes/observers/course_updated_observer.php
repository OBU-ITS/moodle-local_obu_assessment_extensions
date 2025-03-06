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
            $trace->output("❌ ERROR: Could not retrieve course object for ID: " . $courseId);
            return;
        }

        // Is it a teaching module course, if not move on
        if (!preg_match("/^[0-9]{4}\.[A-Z]{3,4}[0-9]{4}_[A-Z][0-9]{1,2}_[0-9]/", $course->idnumber)) {
            return;
        }

        $handler = \core_customfield\handler::get_handler('core_course', 'course');
        $fields = $handler->get_instance_data($courseId);
        $targetFields = ["ssbsect_score_cutoff_date", "ssbsect_reas_score_ctof_date"];
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

                $existingField = $DB->get_record('customfield_data', [
                    'fieldid' => $field->get_field()->get('id'), // Correct field
                    'instanceid' => $courseId
                ]);

                if ($existingField) {
                    $updateField = new \stdClass();
                    $updateField->id = (int) $existingField->id;
                    $updateField->instanceid = $courseId;
                    $updateField->charvalue = ltrim($value, '*');

                    $updateFields[] = $updateField;
                }
                $trace->output("📢 Relevant custom field change found: $fieldname - Value: " . $value);
            }
        }

        if ($updateCourseworkDeadlines) {
            $sql = "SELECT cm.instance
                FROM {course_modules} cm
                JOIN {modules} m ON cm.module = m.id AND m.name = 'coursework'
                WHERE cm.course = :course";

            $courseModuleInstanceIds = $DB->get_records_sql($sql, ['course' => $courseId]);

            foreach($courseModuleInstanceIds as $courseModuleInstanceId) {
                $trace->output("📢 cmid: " . $courseModuleInstanceId->instance);
                local_obu_create_task_for_course_mod_change($trace, (int) $courseModuleInstanceId->instance);
            }
        }

        if (!empty($updateFields)){
            foreach ($updateFields as $updateField) {
                $trace->output("📢 updateField object: " . print_r($updateField, true));
                try {
                    $DB->update_record('customfield_data', $updateField);
                } catch (\Exception $e) {
                    $trace->output("⚠️ SQL error on update record customfiled data: " . $e->getMessage());
                }
            }
            $trace->output("✅ update completed for " . count($updateFields) . " fields.");
        }
    }
}