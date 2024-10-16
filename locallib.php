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
    $extension->assessment_id    = $assessmentIdNumber; // course module id
    $extension->extension_amount = $extensionDays;
    $extension->is_processed = 0;
    $extension->timestamp = time();

    $DB->insert_record('local_obu_assessment_ext', $extension);

    return true;
}

function local_obu_submit_due_date_change($user, $assessment, $newDeadline, \progress_trace $trace = null) {
    global $DB;

    $sql = "SELECT * FROM {course_modules} WHERE id = :cmid";
    $courseModule = $DB->get_record_sql($sql, ['cmid' => $assessment]);
    $course = $DB->get_record('course', array('id' => $courseModule->course), '*', MUST_EXIST);

    $assessmentGroups = local_obu_get_assessment_groups_by_assessment($assessment);
    $userAssessmentGroups = local_obu_get_assessment_groups_by_user($user->username);
    $assessmentGroup = local_obu_find_common_assessment_group($assessmentGroups, $userAssessmentGroups);

    if ($newDeadline == 0) {
        $conditions = [
            'student_id' => $user->username,
            'assessment_id' => $assessment,
            'extension_amount' => 0
        ];
        $existingExtension = $DB->get_record_select(
            'local_obu_assessment_ext',
            'student_id = :student_id AND assessment_id = :assessment_id AND extension_amount = :extension_amount',
            $conditions
        );
    } else {
        $conditions = [
            'student_id' => $user->username,
            'assessment_id' => $assessment,
        ];
        $existingExtension = $DB->get_record_select(
            'local_obu_assessment_ext',
            'student_id = :student_id AND assessment_id = :assessment_id AND extension_amount != 0 AND extension_amount != -1',
            $conditions
        );
    }

    if ($newDeadline == 0) {
        $date = "temporary";
        $type = "coursework_temporary_exemption";
        if ($existingExtension){
            $action = "update";
        } else {
            $action = "insert";
        }
    } elseif ($newDeadline == -1) {
        $date = null;
        $type = "coursework_temporary_exemption";
        $action = "delete";
    } else {
        $date = date('d/m/Y H:i', $newDeadline);
        $type = "coursework_mitigations";
        if ($existingExtension){
            $action = "update";
        } else {
            $action = "insert";
        }
    }

    $dueDateChange = new stdClass();
    $dueDateChange->user   = $user->username;
    $dueDateChange->course    = $course->idnumber;
    $dueDateChange->assessment = $assessmentGroup->name;
    $dueDateChange->date = $date;
    $dueDateChange->timelimit = null;
    $dueDateChange->type = $type;
    $dueDateChange->reason_code = null;
    $dueDateChange->reason_desc = null;
    $dueDateChange->action = $action;
    $dueDateChange->timecreated = time();

    try {
        $DB->insert_record('module_extensions_queue', $dueDateChange);
    }
    catch (\moodle_exception $e) {
        $trace->output($e->getMessage());
        $trace->output($e->getFile());
        $trace->output($e->getTraceAsString());
        $trace->output($e->debuginfo);

        throw new \moodle_exception("Error storing module extensions queue");
    }
}

function local_obu_get_assessment_groups_by_user($user): array {
    global $DB;
    $groups = array();
    $assessmentGroups = array();

    $userobj = $DB->get_record('user', array('username' => $user), 'id');
    $groupIds = $DB->get_records('groups_members', array('userid' => $userobj->id), '', 'groupid');

    if (empty($groupIds)) {
        return $groups;
    }

    $groupIds = array_keys($groupIds);
    if (!empty($groupIds)) {
        list($inSql, $params) = $DB->get_in_or_equal($groupIds, SQL_PARAMS_QM, '', true);
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
    $userIds = $DB->get_records('groups_members', array('groupid' => $assessmentGroup), '', 'userid');

    if (empty($userIds)) {
        return $users;
    }

    $userIds = array_keys($userIds);

    if (!empty($userIds)) {
        list($inSql, $params) = $DB->get_in_or_equal($userIds, SQL_PARAMS_QM, '', true);
        $users = $DB->get_records_select('user', "id $inSql", $params);
    }

    return $users;
}

function local_obu_get_assessments_by_assessment_group($assessmentGroup): array {
    global $DB;

    $sql = "
        SELECT cm.*
        FROM {course_modules} cm
        JOIN {modules} m ON cm.module = m.id
        WHERE cm.availability LIKE :groupid
        AND m.name = :modulename
    ";

    $params = ['groupid' => '%"id":'.$assessmentGroup->id.'%', 'modulename' => 'coursework'];

    return $DB->get_records_sql($sql, $params);
}

function local_obu_get_assessment_groups_by_assessment($courseModuleId) {
    global $DB;
    $assessmentGroups = array();
    $sql = "SELECT * FROM {course_modules} WHERE id = :cmid";
    $courseModule = $DB->get_record_sql($sql, ['cmid' => $courseModuleId]);

    if (!empty($courseModule->availability)) {

        $pattern = '/"group","id":(\d+)/';
        preg_match_all($pattern, $courseModule->availability, $matches);
        $groupids = $matches[1];

        foreach ($groupids as $groupid){
            $group = $DB->get_record('groups', array('id' => $groupid), '*', IGNORE_MISSING);
            $assessmentGroups[] = $group;
        }

//        $decodedRestrictions = json_decode($courseModule->availability, true);
//
//        if (!empty($decodedRestrictions['c'])) {
//            foreach ($decodedRestrictions['c'] as $condition) {
//                if ($condition['type'] === 'group' && !empty($condition['id'])) {
//                    $group = $DB->get_record('groups', array('id' => $condition['id']), '*', MUST_EXIST);
//                    $assessmentGroups[] = $group;
//                }
//            }
//        }
    }

    return $assessmentGroups;
}

//assessment in this case is the cmid and the user variable is the user object. Trace is optional
function local_obu_recalculate_due_for_assessment($user, $assessment, $trace = null) {
    global $DB;

    // GET course module record
    $coursemodule = $DB->get_record('course_modules', array('id' => $assessment), 'instance', MUST_EXIST);
    $courseworkRecord = $DB->get_record('coursework', array('id' => $coursemodule->instance), 'deadline, initialmarkingdeadline', MUST_EXIST);

    $deadline = $courseworkRecord->deadline;
    $hardDeadline = $courseworkRecord->initialmarkingdeadline - 604800; //(unix timestamp value of 7 days)
    $hardDeadline = $hardDeadline < 0 ? $deadline + 2419200 : $hardDeadline; // IF markingDeadline is 0 then set it to 4wks over cw deadline

    $sql = "SELECT uid.data
        FROM {user_info_data} uid
        JOIN {user_info_field} uif ON uid.fieldid = uif.id
        WHERE uid.userid = :userid
        AND uif.shortname = 'extensions'";

    $userExtensionWeeks = $DB->get_record_sql($sql, ['userid' => $user->id]);
    $userServiceNeeds = $userExtensionWeeks->data * 7;

    $extensionRecord = $DB->get_record_sql(
        "SELECT extension_amount
            FROM {local_obu_assessment_ext}
            WHERE " . $DB->sql_compare_text('student_id') . " = ?
            AND " . $DB->sql_compare_text('assessment_id') . " = ?
            AND is_processed = true
            AND extension_amount > 0
            ORDER BY id DESC
            LIMIT 1",
            [$user->username, $assessment]);

    if ($extensionRecord) {
        if ($extensionRecord->extension_amount != 0 && $extensionRecord->extension_amount != -1) {
            $newDeadline = $deadline + ($userServiceNeeds * 24 * 3600) + ($extensionRecord->extension_amount * 24 * 3600);
            if ($newDeadline > $hardDeadline) {
                $newDeadline = $hardDeadline;
            }
        } else {
            $newDeadline = $extensionRecord->extension_amount;
        }
    } else {
        $newDeadline = $deadline + ($userServiceNeeds * 24 * 3600);
        if ($newDeadline > $hardDeadline) {
            $newDeadline = $hardDeadline;
        }
    }

    local_obu_submit_due_date_change($user, $assessment, $newDeadline, $trace);
}

function local_obu_recalculate_due_for_assessment_with_unprocessed_extensions($user, $assessment, $extensionAmount, $trace = null) {
    global $DB;

    // GET course module record
    $coursemodule = $DB->get_record('course_modules', array('id' => $assessment), 'instance', MUST_EXIST);
    $courseworkRecord = $DB->get_record('coursework', array('id' => $coursemodule->instance), 'deadline, initialmarkingdeadline', MUST_EXIST);

    $deadline = $courseworkRecord->deadline;
    $hardDeadline = $courseworkRecord->initialmarkingdeadline - 604800; //(unix timestamp value of 7 days)
    $hardDeadline = $hardDeadline < 0 ? $deadline + 2419200 : $hardDeadline; // IF markingDeadline is 0 then set it to 4wks over cw deadline

    $sql = "SELECT uid.data
        FROM {user_info_data} uid
        JOIN {user_info_field} uif ON uid.fieldid = uif.id
        WHERE uid.userid = :userid
        AND uif.shortname = 'extensions'";

    $userExtensionWeeks = $DB->get_record_sql($sql, ['userid' => $user->id]);
    $userServiceNeeds = $userExtensionWeeks->data * 7;

    if ($extensionAmount != 0 && $extensionAmount != -1) {
        $newDeadline = $deadline + ($userServiceNeeds * 24 * 3600) + ($extensionAmount * 24 * 3600);
        if ($newDeadline > $hardDeadline) {
            $newDeadline = $hardDeadline;
        }
    } else {
        $newDeadline = $extensionAmount;
    }

    local_obu_submit_due_date_change($user, $assessment, $newDeadline, $trace);
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
    $userGroupIds = array();
    foreach ($userAssessmentGroups as $group) {
        $userGroupIds[$group->id] = $group;
    }

    foreach ($assessmentGroups as $assessmentGroup) {
        if (isset($userGroupIds[$assessmentGroup->id])) {
            return $assessmentGroup;
        }
    }

    return null;
}