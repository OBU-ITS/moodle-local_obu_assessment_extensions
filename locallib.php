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

// Saves each extension to the table of what we've sent to CoSector?
function local_obu_assess_ex_store_known_exceptional_circumstances($studentIdNumber, $extensionDays, $courseModuleId=null) {
    global $DB;

    $extension = new stdClass();
    $extension->student_id   = $studentIdNumber;
    $extension->assessment_id    = $courseModuleId; // course module id
    $extension->extension_amount = $extensionDays;
    $extension->is_processed = 0;
    $extension->timestamp = time();

    $DB->insert_record('local_obu_assessment_ext', $extension);

    return true;
}

/**
 * Retrieve all students db or meta enrolled in a given course with the 'student' (roleid = 5) role.
 *
 * @param int $courseid The course ID to fetch enrolled students for.
 * @return array An array of enrolled students (user id and username).
 */
function local_obu_assess_ex_get_enrolled_students($courseid) : array {
    global $DB;

    $sql = "SELECT DISTINCT u.id, u.username
               FROM {enrol} e 
               JOIN {user_enrolments} ue ON e.id = ue.enrolid
               JOIN {user} u ON u.id = ue.userid
               JOIN {role_assignments} ra ON ra.userid = ue.userid AND ra.roleid = 5
               JOIN {context} c ON c.id = ra.contextid AND c.instanceid = e.courseid AND c.contextlevel = 50
               WHERE e.enrol IN ('database', 'meta')
                 AND e.courseid = ?";

    return $DB->get_records_sql($sql, [$courseid]);
}

/**
 * Fetch custom date field values for a course and set default values if unset.
 *
 * @param int $courseId The ID of the course.
 * @param int $defaultDeadline The default deadline (used for fallback dates).
 *
 * @return array An associative array of custom field values with keys:
 *               - 'ssbsect_score_cutoff_date'
 *               - 'ssbsect_reas_score_ctof_date'
 */
function local_obu_assess_ex_fetch_banner_cutoff_dates($course, $dueDate) {
    global $DB;

    // Define default date as 35 days after baseline deadline
    $defaultDate = strtotime('+35 days', $dueDate);
    $defaultDateFormatted = date('d-M-y', $defaultDate);

    // Default field values
    $customFieldValues = [
        'ssbsect_score_cutoff_date' => $defaultDateFormatted,
        'ssbsect_reas_score_ctof_date' => $defaultDateFormatted,
    ];

    // SQL query to fetch custom field values
    $sql = "SELECT cfd.value, cff.shortname
            FROM {customfield_data} cfd
            JOIN {customfield_field} cff ON cfd.fieldid = cff.id
            WHERE cfd.instanceid = :instanceid
            AND cff.shortname IN ('ssbsect_score_cutoff_date', 'ssbsect_reas_score_ctof_date')";

    $result = $DB->get_records_sql($sql, ['instanceid' => $course]);
    foreach ($result as $field) {
        if ($field->shortname === 'ssbsect_score_cutoff_date') {
            $customFieldValues['ssbsect_score_cutoff_date'] = $field->value;
        } elseif ($field->shortname === 'ssbsect_reas_score_ctof_date') {
            $customFieldValues['ssbsect_reas_score_ctof_date'] = $field->value;
        }
    }

    return $customFieldValues;
}

/**
 * Calculate the hard deadline for an assessment group.
 *
 * @param string $groupIdnumber The idnumber of the assessment group.
 * @param string $OECutoffDate The score cutoff date for original assessment.
 * @param string $RECutoffDate The score cutoff date for resit assessment.
 *
 * @return array An array containing 'hardDeadline' and 'deadline'.
 */
function local_obu_assess_ex_calculate_harddeadline($groupIdnumber, $OECutoffDate, $RECutoffDate) {
    // Determine the hard deadline
    if (substr($groupIdnumber, -2) === 'OE') {
        $hardDeadline = $OECutoffDate;
    } else {
        $hardDeadline = $RECutoffDate;
    }

    // In this case, assume directly using hardDeadline as "deadline" (can differ if needed)
    return $hardDeadline;
}


function local_obu_submit_due_date_change(\progress_trace $trace, $user, $courseModuleId, $newDeadline, $temporaryExemption = null, $deletion = null, $deleteExisting = null) {
    global $DB;

    $sql = "SELECT course FROM {course_modules} WHERE id = :cmid";
    $courseModule = $DB->get_record_sql($sql, ['cmid' => $courseModuleId]);
    $course = $DB->get_record('course', array('id' => $courseModule->course), 'idnumber', MUST_EXIST);

    if (!$course->idnumber) {
        $trace->output("Skipping due date change for course with no idnumber");
        return;
    }

    $assessmentGroups = local_obu_get_assessment_groups_by_assessment($courseModuleId);
    $userAssessmentGroups = local_obu_get_assessment_groups_by_user($user->username);
    $assessmentGroup = local_obu_find_common_assessment_group($assessmentGroups, $userAssessmentGroups);

    if ($temporaryExemption) {
        $conditions = [
            'student_id' => $user->username,
            'assessment_id' => $courseModuleId,
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
            'assessment_id' => $courseModuleId,
        ];
        $existingExtension = $DB->get_record_select(
            'local_obu_assessment_ext',
            'student_id = :student_id AND assessment_id = :assessment_id AND extension_amount != 0 AND extension_amount != -1',
            $conditions
        );
    }

    if ($temporaryExemption) {
        $date = "temporary";
        $type = "coursework_temporary_exemption";
        if ($existingExtension){
            $action = "update";
        } else {
            $action = "insert";
        }
    } elseif ($deletion) {
        $date = null;
        $type = "coursework_temporary_exemption";
        $action = "delete";
    } elseif ($deleteExisting) {
        $date = null;
        $type = "coursework_mitigations";
        $action = "delete";
    } else {
        $date = $newDeadline;
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

function local_obu_get_assessment_groups_by_user($userIdNumber): array {
    global $DB;
    $groups = array();
    $assessmentGroups = array();

    $userobj = $DB->get_record('user', array('username' => $userIdNumber), 'id');
    $groupIds = $DB->get_records('groups_members', array('userid' => $userobj->id), '', 'groupid');

    if (empty($groupIds)) {
        return $groups;
    }

    $groupIds = array_keys($groupIds);
    if (!empty($groupIds)) {
        [$inSql, $params] = $DB->get_in_or_equal($groupIds, SQL_PARAMS_QM, '', true);
        $groups = $DB->get_records_select('groups', "id $inSql", $params);
        foreach ($groups as $group) {
            if (preg_match("/^\d{4}\..+?_.+?_\d+_\d{6}_\d+_.+?-\d+_\d+_.{1,2}$/", $group->idnumber)) {
                $assessmentGroups[] = $group;
            }
        }
    }
    return $assessmentGroups;
}

function local_obu_get_users_by_assessment_group($assessmentGroupId): array {
    global $DB;
    $users = array();
    $userIds = $DB->get_records('groups_members', array('groupid' => $assessmentGroupId), '', 'userid');

    if (empty($userIds)) {
        return $users;
    }

    $userIds = array_keys($userIds);

    if (!empty($userIds)) {
        [$inSql, $params] = $DB->get_in_or_equal($userIds, SQL_PARAMS_QM, '', true);
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
        JOIN {course} c ON c.id = cm.course AND c.idnumber <> '' AND c.idnumber IS NOT NULL
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
function local_obu_recalculate_due_for_assessment(\progress_trace $trace, $user, $courseModuleId) {
    global $DB;

    // GET course module record
    $coursemodule = $DB->get_record('course_modules', ['id' => $courseModuleId], 'instance, course', MUST_EXIST);

    // Get the coursework record to retrieve the deadline
    $courseworkRecord = $DB->get_record('coursework', ['id' => $coursemodule->instance], 'deadline', MUST_EXIST);
    $deadline = $courseworkRecord->deadline;

    $customFields = local_obu_assess_ex_fetch_banner_cutoff_dates($coursemodule->course, $deadline);
    $trace->output('Custom fields: ' . json_encode($customFields));

    $pattern = '/"group","id":(\d+)/';
    preg_match_all($pattern, $coursemodule->availability, $matches);
    $groupid = $matches[1];
    $trace->output('Extracted Group ID: ' . json_encode($groupid));
    $assessmentGroup = $DB->get_record('groups', ['id' => $groupid], 'id, idnumber', IGNORE_MISSING);
    $trace->output('Assessment Group IDNumber: ' . json_encode($assessmentGroup->idnumber));


    // Use the retrieved custom field values to determine hard deadlines
    if (substr($assessmentGroup->idnumber, -2) === 'OE') {
        $hardDeadline = $ssbsect_score_cutoff_date;
    } else {
        $hardDeadline = $ssbsect_reas_score_ctof_date;
    }
    $trace->output("Hard Deadline: $hardDeadline");

    $sql = "SELECT uid.data
        FROM {user_info_data} uid
        JOIN {user_info_field} uif ON uid.fieldid = uif.id
        WHERE uid.userid = :userid
        AND uif.shortname = 'extensions'";

    $userExtensionWeeks = $DB->get_record_sql($sql, ['userid' => $user->id]);
    $userServiceNeedsDays = $userExtensionWeeks->data * 7;

    $sql = '
    SELECT extension_amount
    FROM {local_obu_assessment_ext}
    WHERE ' . $DB->sql_compare_text('student_id') . ' = ?
      AND ' . $DB->sql_compare_text('assessment_id') . ' = ?
      AND is_processed = true
      AND extension_amount > 0
    ORDER BY id DESC
    LIMIT 1';

    $params = [$user->username, $courseModuleId];

    $extensionRecord = $DB->get_record_sql($sql, $params);

    if ($extensionRecord) {

        if ($extensionRecord->extension_amount == 0) {
            $temporaryExemption = true;
        } else if ($extensionRecord->extension_amount == -1) {
            $deletion = true;
        } else {
            local_obu_submit_due_date_change($trace, $user, $courseModuleId, null, false, false, true);
            $additionalDays = $userServiceNeedsDays + $extensionRecord->extension_amount;
            $newDeadline = calc_new_deadline($trace, $deadline, $additionalDays, $hardDeadline);
        }
    } else {
        local_obu_submit_due_date_change($trace, $user, $courseModuleId, null, false, false, true);
        $newDeadline = calc_new_deadline($trace, $deadline, $userServiceNeedsDays, $hardDeadline);
    }
    $trace->output("New Deadline is $newDeadline");

    $dateTime = DateTime::createFromFormat('d/m/Y H:i', $newDeadline);
    if ($dateTime === false) {
        $trace->output('Failed to parse date. Please check the format.');
    } else {
        $newDeadlineTimestamp = $dateTime->getTimestamp();
        $trace->output("New deadline timestamp = $newDeadlineTimestamp");
    }
    $trace->output("Deadline timestamp = $deadline");
    if ($newDeadlineTimestamp == $deadline) {
        // Deadlines are the same, skip extension submission
        $trace->output("No change in deadline, skipping submission");
        return;
    }

    local_obu_submit_due_date_change($trace, $user, $courseModuleId, $newDeadline, $temporaryExemption, $deletion);
}

function calc_new_deadline(\progress_trace $trace, $deadlineTimestamp, $additionalDays, $hardDeadline) {
    // Convert deadline timestamp into DateTime object
    $deadlineDate = (new DateTime())->setTimestamp($deadlineTimestamp);
    $trace->output('Current Deadline: ' . $deadlineDate->format('d/m/Y H:i'));

    // Clone and modify the deadline to calculate the new deadline
    $newDeadlineDate = clone $deadlineDate;
    $newDeadlineDate->modify("+$additionalDays days"); // Add additional days
    $newDeadline = $newDeadlineDate->format('d/m/Y H:i');
    $trace->output("New Deadline: $newDeadline");

    $hardDeadlineDate = DateTime::createFromFormat('d-M-y H:i', $hardDeadline . ' 00:00');

    if (!$hardDeadlineDate) {
        // If parsing fails, log an error and return the new deadline
        $trace->output("Invalid Hard Deadline format: $hardDeadline");
        return $newDeadline;
    }

    // Set the time of the hard deadline to match the original deadline's time
    $hardDeadlineDate->setTime((int) $deadlineDate->format('H'), (int) $deadlineDate->format('i'));

    $trace->output('Hard Deadline: ' . $hardDeadlineDate->format('d/m/Y H:i'));

    // Compare new deadline with hard deadline
    if ($newDeadlineDate > $hardDeadlineDate) {
        $trace->output('New Deadline exceeds Hard Deadline. Adjusting to Hard Deadline.');
        // If the new deadline exceeds the hard deadline, set the new deadline to the hard deadline
        $newDeadlineDate = $hardDeadlineDate; // Use hard deadline
        $newDeadline = $newDeadlineDate->format('d/m/Y H:i');
    }

    return $newDeadline;
}

function local_obu_recalculate_due_for_assessment_with_unprocessed_extensions(\progress_trace $trace, $user, $courseModuleId,
    $extensionAmount) {
    global $DB;

    // GET course module record
    $courseModule = $DB->get_record('course_modules', ['id' => $courseModuleId], 'instance, course', MUST_EXIST);

    // Get the coursework record to retrieve the deadline
    $courseworkRecord = $DB->get_record('coursework', ['id' => $courseModule->instance], 'deadline', MUST_EXIST);

    // Fetch custom field values from mdl_customfield_data
    $sql = "SELECT cfd.value, cff.shortname
            FROM {customfield_data} cfd
            JOIN {customfield_field} cff ON cfd.fieldid = cff.id
            WHERE cfd.instanceid = :instanceid
            AND cff.shortname IN ('ssbsect_score_cutoff_date', 'ssbsect_reas_score_ctof_date')";

    $customFields = $DB->get_records_sql($sql, ['instanceid' => $courseModule->course]);
    $trace ->output("Custom fields: " . json_encode($customFields));

    // Calculate default date: courseworkRecord->deadline + 35 days in case the custom fields are unpopulated
    $defaultDate = strtotime('+35 days', $courseworkRecord->deadline);
    $defaultDateFormatted = date('d-M-y', $defaultDate);

    // Set default values for custom fields
    $ssbsect_score_cutoff_date = $defaultDateFormatted;
    $ssbsect_reas_score_ctof_date = $defaultDateFormatted;

    $trace->output("ssbsect_score_cutoff_date: $ssbsect_score_cutoff_date");
    $trace->output("ssbsect_reas_score_ctof_date: $ssbsect_reas_score_ctof_date");

    foreach ($customFields as $field) {
        if ($field->shortname === 'ssbsect_score_cutoff_date') {
            $ssbsect_score_cutoff_date = $field->value;
        } else if ($field->shortname === 'ssbsect_reas_score_ctof_date') {
            $ssbsect_reas_score_ctof_date = $field->value;
        }
    }

    $pattern = '/"group","id":(\d+)/';
    preg_match_all($pattern, $courseModule->availability, $matches);
    $groupid = $matches[1];
    $assessmentGroup = $DB->get_record('groups', ['id' => $groupid], 'id, idnumber', IGNORE_MISSING);

    $deadline = $courseworkRecord->deadline;

    // Use the retrieved custom field values to determine hard deadlines
    if (substr($assessmentGroup->idnumber, -2) === 'OE') {
        $hardDeadline = $ssbsect_score_cutoff_date;
    } else {
        $hardDeadline = $ssbsect_reas_score_ctof_date;
    }

    $sql = "SELECT uid.data
        FROM {user_info_data} uid
        JOIN {user_info_field} uif ON uid.fieldid = uif.id
        WHERE uid.userid = :userid
        AND uif.shortname = 'extensions'";

    $userExtensionWeeksRecord = $DB->get_record_sql($sql, ['userid' => $user->id]);
    $userServiceNeedsDays = $userExtensionWeeksRecord->data * 7;

    if ($extensionAmount == 0) {
        $temporaryExemption = true;
    } else if ($extensionAmount == -1) {
        $deletion = true;
    } else {
        local_obu_submit_due_date_change($trace, $user, $courseModuleId, null, false, false, true);
        $additionalDays = $userServiceNeedsDays + $extensionAmount;
        $newDeadline = calc_new_deadline($trace, $deadline, $additionalDays, $hardDeadline);
    }
    $trace->output("New Deadline is $newDeadline");

    $dateTime = DateTime::createFromFormat('d/m/Y H:i', $newDeadline);
    if ($dateTime === false) {
        $trace->output('Failed to parse date. Please check the format.');
    } else {
        $newDeadlineTimestamp = $dateTime->getTimestamp();
        $trace->output("New deadline timestamp = $newDeadlineTimestamp");
    }
    $trace->output("Deadline timestamp = $deadline");
    if ($newDeadlineTimestamp == $deadline) {
        // Deadlines are the same, skip extension submission
        $trace->output('No change in deadline, skipping submission');
        return;
    }

    local_obu_submit_due_date_change($trace, $user, $courseModuleId, $newDeadline, $temporaryExemption, $deletion);
}


//function local_obu_get_groups_from_access_restrictions($decodedRestrictions): array {
//    $groupIds = [];
//
//    if (isset($decodedRestrictions['c'])) {
//        foreach ($decodedRestrictions['c'] as $condition) {
//            if (isset($condition['type']) && $condition['type'] === 'group' && isset($condition['id'])) {
//                $groupIds[] = $condition['id'];
//            }
//        }
//    }
//
//    return $groupIds;
//}

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

function local_obu_create_task_for_course_mod_change($trace, $courseModuleInstanceId) {
    global $DB;

    // Get the relevant course module details for this instance ID.
    $sql = "SELECT cm.id, cm.course, cm.availability
            FROM {course_modules} cm
            JOIN {modules} m ON cm.module = m.id AND m.name = 'coursework'
            WHERE cm.instance = :instanceid";
    $courseModule = $DB->get_record_sql($sql, ['instanceid' => $courseModuleInstanceId]);

    // Get course information and check if idnumber exists (external system identifier).
    $course = $DB->get_record('course', ['id' => $courseModule->course], 'idnumber', MUST_EXIST);
    if (!$course->idnumber) {
        return; // Exit if there's no idnumber.
    }

    if (!$courseModule) {
        $trace->output("No course module found for instance ID: $courseModuleInstanceId");
        return;
    }

    $newRestrictions = $courseModule->availability;
    $trace->output("Availability: $newRestrictions");

    $courseContext = \context_course::instance($courseModule->course);
    $users = local_obu_assess_ex_get_enrolled_students($courseModule->course);
    $trace->output('Users on Course: ' . count($users));

    $modinfo = get_fast_modinfo($courseModule->course);
    $courseModuleUsers = [];

    try {
        $cm_info = $modinfo->get_cm($courseModule->id);
        $info = new \core_availability\info_module($cm_info);
        $courseModuleUsers = $info->filter_user_list($users);
    } catch (\moodle_exception $e) {
        $trace->output('Availability API error: ' . $e->errorcode);
        $groupIds = [];
        preg_match_all('/"group","id":(\d+)/', $newRestrictions, $matches);
        $groupIds = $matches[1];

        foreach ($groupIds as $groupId) {
            $groupUsers = local_obu_get_users_by_assessment_group($groupId);
            $courseModuleUsers = array_merge($courseModuleUsers, $groupUsers);
        }
    }

    if (empty($courseModuleUsers)) {
        $trace->output('No valid users found with permissions; task creation aborted.');
        return;
    }

    $trace->output('Filtered Users: ' . count($courseModuleUsers));
    $trace->output('Filtered User IDs: ' . implode(', ', array_map(function($user) {
            return $user->id;
        }, $courseModuleUsers)));

    $task = new \local_obu_assessment_extensions\task\adhoc_process_deadline_change();
    $task->set_custom_data(['courseModuleId' => $courseModule->id, 'courseModuleUsers' => $courseModuleUsers]);

    $trace->output('Task created');
    \core\task\manager::queue_adhoc_task($task);
    $trace->output('Task queued');
}