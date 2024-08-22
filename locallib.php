<?php
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
 * Plugin local library methods
 *
 * @package    local_obu_assessment_extensions
 * @author     Emir Kamel
 * @copyright  2024, Oxford Brookes University {@link http://www.brookes.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Adds a known exceptional circumstance record to the table
 *
 * @param string $studentIdNumber   The student id number
 * @param string $extensionDays The number of days' extension provided to the student
 * @param string $assessmentIdNumber The assessment id number (Optional)
 * @return bool True if user added successfully or the user is already a
 * member of the group, false otherwise.
 */
function local_obu_assess_ex_store_known_exceptional_circumstances($studentIdNumber, $extensionDays, $assessmentIdNumber=null) {
    global $DB;

    $extension = new stdClass();
    $extension->student_id   = $studentIdNumber;
    $extension->assessment_id    = $assessmentIdNumber;
    $extension->extension_amount = $extensionDays;
    $extension->is_processed = 0;
    $extension->timestamp = time();

    $DB->insert_record('local_obu_assessment_ext', $extension);

    return true;
}

//TODO:: Need more info from cosector on formats of data to go in this table, need to complete this function
function local_obu_submit_due_date_change($user, $assessment, $newDeadline = null) {
    global $DB;
    $courseModule = get_coursemodule_from_id(null, $assessment, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $courseModule->course), '*', MUST_EXIST);

    $dueDateChange = new stdClass();
    $dueDateChange->user   = $user->idnumber;
    $dueDateChange->course    = $course->shortname;
    $dueDateChange->assessment = '';
    $dueDateChange->date_time_value = $newDeadline;
    $dueDateChange->timelimit_value = '';
    $dueDateChange->type = '';
    $dueDateChange->reason_code = '';
    $dueDateChange->reason_desc = '';
    $dueDateChange->action = '';
    $dueDateChange->timestamp = time();

    $DB->insert_record('module_extensions_queue_table', $dueDateChange);

    return true;
}

function local_obu_get_assessment_groups_by_user($user): array {
    global $DB;
    $groups = array();
    $assessmentGroups = array();

    $groupIds = $DB->get_records('groups_members', array('userid' => $user), '', 'groupid');
    if (empty($groupIds)) {
        return $groups;
    }

    $groupIds = array_keys($groupIds);
    if (!empty($groupIds)) {
        list($inSql, $params) = $DB->get_in_or_equal($groupIds, SQL_PARAMS_QM, '', false);
        $groups = $DB->get_records_select('groups', "id $inSql", $params);

        foreach ($groups as $group) {
            if (preg_match("/^\d{4}\..+?_.+?_\d+_\d{6}_\d+_.+?-\d+_\d+_.{1,2}$/", $group->idnumber)) {
                $assessmentGroups[] = $group;
            }
        }
    }

    return $assessmentGroups;
}

function local_obu_get_users_by_assessment_group($assessmentGroup): array {
    global $DB;
    $users = array();

    $userIds = $DB->get_records('groups_members', array('groupid' => $assessmentGroup->id), '', 'userid');
    if (empty($userIds)) {
        return $users;
    }

    $userIds = array_keys($userIds);
    if (!empty($userIds)) {
        list($inSql, $params) = $DB->get_in_or_equal($userIds, SQL_PARAMS_QM, '', false);
        $users = $DB->get_records_select('user', "id $inSql", $params);
    }

    return $users;
}

function local_obu_get_assessments_by_assessment_group($assessmentGroup): array {
    global $DB;

    $sql = "
        SELECT cm.*
        FROM {course_modules} cm
        WHERE cm.availability LIKE :groupid
    ";
    $params = ['groupid' => '%"id":'.$assessmentGroup->id.'%'];

    return $DB->get_records_sql($sql, $params);
}

//TODO:: Function may not be necessary
function local_obu_get_assessment_groups_by_assessment($assessment) {
    return;
}

//assessment in this case is the cmid and the user variable is the user object. Trace is optional
function local_obu_recalculate_due_for_assessment($user, $assessment, $trace = null) {
    global $DB;
    //TODO:: coursework table may need looking at to confirm the names of the fields used here
    $courseworkRecord = $DB->get_record('coursework', array('id' => $assessment), 'deadline, agreed_grade_marking_deadline', MUST_EXIST);
    $deadline = $courseworkRecord->deadline;
    $hardDeadline = $courseworkRecord->agreed_marking_grade_deadline - 604800; //(unix timestamp value of 7 days)
    $newDeadline = 0;
    $userServiceNeeds = 0;

    // Get the field ID for 'service_needs' profile field
    $field = $DB->get_record('user_info_field', ['shortname' => 'service_needs']);

    // Retrieve the service_needs value for the specific user
    if ($field) {
        $serviceNeeds = $DB->get_field('user_info_data', 'data', [
            'userid' => $user->id,
            'fieldid' => $field->id,
        ]);

        echo "Service Needs: " . $serviceNeeds;
    } else {
        echo "Service Needs field not found.";
    }

    $serviceNeedsMapping = [
        'CWON' => 7,
        'CWTW' => 14,
        'CWTH' => 21,
        'CWFO' => 28,
    ];
    $userServiceNeeds = $serviceNeedsMapping[$serviceNeeds] ?? 0;

    $extensionRecord = $DB->get_record('local_obu_assessment_ext', [
        'student_id' => $user->id,
        'assessment_id' => $assessment
    ]);
    if ($extensionRecord) {
        if ($extensionRecord->extension_amount != 0 && $extensionRecord->extension_amount != 1) {
            $newDeadline = $deadline + ($userServiceNeeds * 24 * 3600) + ($extensionRecord->extension_amount * 24 * 3600);
            if ($newDeadline > $hardDeadline) {
                $newDeadline = $hardDeadline;
            }
        } else {
            $newDeadline = null;
        }
    } else { //TODO:: what do we do about service needs if user has no extensions where else do these get processed?
        $newDeadline = $deadline + ($userServiceNeeds * 24 * 3600);
    }

    local_obu_submit_due_date_change($user, $assessment, $newDeadline);
}

function get_groups_from_access_restrictions($decodedRestrictions): array {
    $groupIds = [];

    if (isset($decodedRestrictions['c'])) {
        foreach ($decodedRestrictions['c'] as $condition) {
            if (isset($condition['type']) && $condition['type'] === 'group' && isset($condition['id'])) {
                $groupIds[] = $condition['id'];
            }
        }
    }

    return $groupIds;
}