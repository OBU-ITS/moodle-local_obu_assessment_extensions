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

class user_profile_updated_observer {
    public static function user_profile_updated(\core\event\user_updated $event) {
        $userId = $event->objectid;

        $trace = new \null_progress_trace();
        self::user_profile_updated_internal($trace, $userId);
    }

    public static function user_profile_updated_internal(\progress_trace $trace, $userId) {
        global $DB;

        $sql = "SELECT uif.shortname, uid.id AS dataid, uid.data
                    FROM {user_info_data} uid
                    JOIN {user_info_field} uif ON uid.fieldid = uif.id
                WHERE uid.userid = :userid
                    AND uif.shortname IN ('extensions', 'exam_extension', 'exam_break')
                    AND uid.data LIKE :changed";
        $params = ['userid' => $userId, 'changed' => '*%'];

        $changedFields = $DB->get_records_sql($sql, $params);

        if (empty($changedFields)) {
            $trace->output('No unprocessed profile field changes detected. Exiting early.');
            return;
        }

        $user = \core_user::get_user($userId, 'id, username');
        if (!$user) {
            $trace->output("User $userId not found; exiting.");
            return;
        }

        $getChangedUserFields = function(string $key) use ($changedFields) {
            return $changedFields[$key] ?? null;
        };

        $extensions = $getChangedUserFields('extensions');
        $examExtension = $getChangedUserFields('exam_extension');
        $examBreak = $getChangedUserFields('exam_break');

        if ($extensions && is_string($extensions->data)) {
            $trace->output('Found unprocessed extensions for user: ' . $user->username);

            $assessmentGroups = local_obu_assessment_ext_get_assessment_groups('by_user', $user->username);

            $assessments = array();

            foreach ($assessmentGroups as $group) {
                $groupAssessments = local_obu_assessment_ext_get_assessments_by_group($group);
                $assessments = array_merge($assessments, $groupAssessments);
            }

            $task = new \local_obu_assessment_extensions\task\adhoc_process_user_service_needs_change();
            $task->set_custom_data(['assessments' => $assessments, 'user' => $user]);
            \core\task\manager::queue_adhoc_task($task);
            $trace->output("Task created");

            $updatedIsp = ltrim($extensions->data, '*');
            $trace->output("Updated ISP: $updatedIsp");

            $updatedRecord = new \stdClass();
            $updatedRecord->id = $extensions->dataid;
            $updatedRecord->data = $updatedIsp;
            $DB->update_record('user_info_data', $updatedRecord);
            $trace->output("Complete");
        }

        if (($examExtension && is_string($examExtension->data)) || ($examBreak && is_string($examBreak->data))) {
            $trace->output('Found unprocessed exam extensions for user: ' . $user->username);

            $assessmentGroups = local_obu_assessment_ext_get_assessment_groups('by_user', $user->username);

            $exams = array();

            foreach ($assessmentGroups as $group) {
                $groupExamAssessments = local_obu_assessment_ext_get_exam_assessments_by_group($group);
                $exams = array_merge($exams, $groupExamAssessments);
            }

            $task = new \local_obu_assessment_extensions\task\adhoc_process_user_service_needs_change();
            $task->set_custom_data(['assessments' => $exams, 'user' => $user]);
            \core\task\manager::queue_adhoc_task($task);
            $trace->output("Task created");

            if ($examExtension) {
                $DB->update_record('user_info_data', (object)[
                    'id'   => $examExtension->dataid,
                    'data' => ltrim((string)$examExtension->data, '*'),
                ]);
            }
            if ($examBreak) {
                $DB->update_record('user_info_data', (object)[
                    'id'   => $examBreak->dataid,
                    'data' => ltrim((string)$examBreak->data, '*'),
                ]);
            }
        }
    }
}