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

//TODO:: Need to check values for mitigation/deletions
function local_obu_submit_due_date_change($user, $assessment, $newDeadline = null) {
    global $DB;
    $courseModule = get_coursemodule_from_id(null, $assessment, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id' => $courseModule->course), '*', MUST_EXIST);
    //TODO:: can a user be in multiple assessment groups for the same assessment?
    $assessmentGroups = local_obu_get_assessment_groups_by_assessment($assessment);
    $userAssessmentGroups = local_obu_get_assessment_groups_by_user($user);
    $assessmentGroup = local_obu_find_common_assessment_group($assessmentGroups, $userAssessmentGroups);

    $dueDateChange = new stdClass();
    $dueDateChange->user   = $user->username;
    $dueDateChange->course    = $course->idnumber;
    $dueDateChange->assessment = $assessmentGroup->name;
    $dueDateChange->date = date('d/m/Y H:i', $newDeadline);
    $dueDateChange->timelimit = null;
    $dueDateChange->type = null;
    $dueDateChange->reason_code = null;
    $dueDateChange->reason_desc = null;
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

function local_obu_get_assessment_groups_by_assessment($assessment) {
    global $DB;
    $assessmentGroups = array();
    $courseModule = get_coursemodule_from_id(null, $assessment, 0, false, MUST_EXIST);

    if (!empty($courseModule->availability)) {
        $decodedRestrictions = json_decode($courseModule->availability, true);

        if (!empty($decodedRestrictions['c'])) {
            foreach ($decodedRestrictions['c'] as $condition) {
                if ($condition['type'] === 'group' && !empty($condition['id'])) {
                    $group = $DB->get_record('groups', array('id' => $condition['id']), '*', MUST_EXIST);
                    $assessmentGroups[] = $group;
                }
            }
        }
    }

    return $assessmentGroups;
}

//assessment in this case is the cmid and the user variable is the user object. Trace is optional
function local_obu_recalculate_due_for_assessment($user, $assessment, $trace = null) {
    global $DB;
    $courseworkRecord = $DB->get_record('coursework', array('id' => $assessment), 'deadline, agreedgrademarkingdeadline', MUST_EXIST);
    $deadline = $courseworkRecord->deadline;
    $hardDeadline = $courseworkRecord->agreed_marking_grade_deadline - 604800; //(unix timestamp value of 7 days)

    $field = $DB->get_record('user_info_field', ['shortname' => 'service_needs']);

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

function local_obu_get_groups_from_access_restrictions($decodedRestrictions): array {
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

function local_obu_find_common_assessment_group($assessmentGroups, $userAssessmentGroups) {
    $userGroupIds = array_column($userAssessmentGroups, null, 'id');

    foreach ($assessmentGroups as $assessmentGroup) {
        if (isset($userGroupIds[$assessmentGroup->id])) {
            return $assessmentGroup;
        }
    }

    return null;
}