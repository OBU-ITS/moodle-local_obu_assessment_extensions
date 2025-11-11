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
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Fetch course module details based on a given course module ID.
 *
 * @param int $courseModuleId The ID of the course module to fetch.
 * @param string $fields A comma-separated list of fields to return (default is '*').
 *
 * @return stdClass|null The course module as an object, or null if not found.
 */
function local_obu_assessment_ext_fetch_course_module($courseModuleId, $fields = '*'): ?stdClass {
    global $DB;
    return $DB->get_record('course_modules', ['id' => $courseModuleId], $fields, MUST_EXIST);
}

/**
 * Fetch course details, including custom field data, based on a given course ID.
 *
 * @param int $courseId The ID of the course to fetch.
 * @param string $fields A comma-separated list of fields to fetch from the 'course' table (default: 'id, idnumber').
 *
 * @return stdClass|null An object containing the course details. Additional keys:
 *                       - 'score_cutoff_date' (optional): Custom date field for score cutoff.
 *                       - 'reas_score_cutoff_date' (optional): Custom date field for reassessment score cutoff.
 *                       Returns null if the course is not found.
 */
function local_obu_assessment_ext_fetch_course_details($courseId, $fields = 'id, idnumber'): ?stdClass {
    global $DB;

    $course = $DB->get_record('course', ['id' => $courseId], $fields);

    if (!$course) {
        return null; // Course not found
    }

    // Fetch custom field data with explicit SQL
    $sql = "SELECT cfd.value, cff.shortname
            FROM {customfield_data} cfd
            JOIN {customfield_field} cff ON cfd.fieldid = cff.id
            WHERE cfd.instanceid = :courseid
            AND cff.shortname IN ('ssbsect_score_cutoff_date', 'ssbsect_reas_score_ctof_date')";

    $customFields = $DB->get_records_sql($sql, ['courseid' => $courseId]);

    // Attach custom field values to the course object
    foreach ($customFields as $field) {
        if ($field->shortname === 'ssbsect_score_cutoff_date') {
            $course->score_cutoff_date = $field->value;
        } elseif ($field->shortname === 'ssbsect_reas_score_ctof_date') {
            $course->reas_score_cutoff_date = $field->value;
        }
    }

    return $course;
}

/**
 * Determine if a course module is an exam by its idnumber code (segment 3 starts with 'OA').
 */
function local_obu_assessment_ext_is_exam(?string $courseModuleIdNumber): bool {
    if (empty($courseModuleIdNumber)) {
        return false;
    }

    $parts = array_map('trim', explode('|', $courseModuleIdNumber, 3));
    if (count($parts) < 3) {
        return false;
    }

    $code = $parts[2];
    return strncasecmp($code, 'OA', 2) === 0;
}

/**
 * Fetches the coursework details for a course module.
 *
 * @param int $instanceId The instance ID of the course module.
 * @return stdClass The coursework record.
 * @throws dml_missing_record_exception If no record is found.
 */
function local_obu_assessment_ext_fetch_coursework($instanceId): stdClass {
    global $DB;

    return $DB->get_record('coursework', ['id' => $instanceId], 'id, deadline', MUST_EXIST);
}

/**
 * Fetches the course module ID for a given coursework activity ID.
 *
 * @param int $courseworkactivityid The coursework activity ID.
 * @return int|null The course module ID, or null if not found.
 */
function local_obu_assessment_ext_get_coursemodule_id($courseworkactivityid) {
    global $DB;

    // Query to find the course module ID for the given coursework ID
    $sql = 'SELECT cm.id
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module
              WHERE cm.instance = :activityid
                AND m.name = :modulename';

    // Execute query with parameters
    $params = [
        'activityid' => $courseworkactivityid,
        'modulename' => 'coursework' // Assuming 'coursework' is the name of the module
    ];

    return $DB->get_field_sql($sql, $params);
}

/**
 * Check if a group ID number matches the format for assessment groups.
 *
 * @param string $idnumber The group ID number to validate.
 *
 * @return bool True if the ID number matches the assessment group format, false otherwise.
 */
function local_obu_assessment_ext_is_assessment_group_idnumber($idnumber): bool {
    return preg_match('/^\d{4}\..+?_.+?_\d+_\d{6}_\d+_.+?-\d+_\d+_.{1,2}$/', $idnumber) === 1;
}

/**
 * Extract and validate group conditions from the first block of availability JSON.
 *
 * This function handles cases where the top-level operator may be "AND" ("&") or "OR" ("|").
 * It retrieves group IDs from the first matching "OR" block of conditions regardless of
 * root operator, validating IDs against their ID numbers to ensure they are assessment groups.
 *
 * @param string|null $availabilityJson The availability JSON string.
 *
 * @return array Array of validated assessment group IDs, or an empty array if none found.
 */
function local_obu_assessment_ext_extract_group_conditions(?string $availabilityJson): array {
    global $DB;

    // Decode JSON string into an associative array.
    $availabilityData = json_decode($availabilityJson, true);

    // Prepare an array to store valid assessment group IDs.
    $validGroupIds = [];

    // Ensure the root level contains an operator ('op') with conditions ('c').
    if (isset($availabilityData['op']) && !empty($availabilityData['c'])) {
        // Check if the top-level operator is valid ("&" or "|").
        $isAnd = ($availabilityData['op'] === '&');
        $isOr = ($availabilityData['op'] === '|');

        if ($isAnd || $isOr) {
            // Traverse the top-level conditions.
            foreach ($availabilityData['c'] as $block) {
                // Process the **first** OR block containing group conditions.
                if (isset($block['op']) && $block['op'] === '|' && !empty($block['c'])) {
                    foreach ($block['c'] as $condition) {
                        // Check if it’s a group condition.
                        if (isset($condition['type']) && $condition['type'] === 'group' && !empty($condition['id'])) {
                            $groupId = $condition['id'];

                            // Fetch the group ID number from the database.
                            $idnumber = $DB->get_field('groups', 'idnumber', ['id' => $groupId]);

                            // Validate the ID number to check if it's an assessment group.
                            if ($idnumber !== false && local_obu_assessment_ext_is_assessment_group_idnumber($idnumber)) {
                                $validGroupIds[] = $groupId;
                            }
                        }
                    }
                    // Stop processing after this first relevant OR block.
                    break;
                }
            }
        }
    }

    // Return the list of unique valid assessment group IDs.
    return array_unique($validGroupIds);
}

/**
 * Fetch assessment groups associated with either a course module or a user.
 *
 * @param string $context The context for the fetch ('by_assessment' or 'by_user').
 * @param int|string $identifier The course module ID (for 'by_assessment') or user ID number (for 'by_user').
 *
 * @return array An array of assessment groups. Each group contains:
 *               - 'id': The group ID.
 *               - 'idnumber': The group ID number.
 *               - 'name': The group name.
 */
function local_obu_assessment_ext_get_assessment_groups($context, $identifier): array {
    global $DB;

    $assessmentGroups = [];

    if ($context === 'by_assessment') {
        // Fetch groups by course module ID
        $courseModuleId = $identifier;

        // Fetch the course module data
        $courseModule = local_obu_assessment_ext_fetch_course_module($courseModuleId, $fields = 'id, availability');
        if (empty($courseModule->availability)) {
            return $assessmentGroups;
        }

        // Use helper to extract group IDs from the availability JSON
        $groupIds = local_obu_assessment_ext_extract_group_conditions($courseModule->availability);

        // Fetch group details and filter assessment groups
        foreach ($groupIds as $groupId) {
            $group = $DB->get_record('groups', ['id' => $groupId], 'id, idnumber, name', IGNORE_MISSING);

            if ($group && local_obu_assessment_ext_is_assessment_group_idnumber($group->idnumber)) {
                $assessmentGroups[] = [
                    'id' => $group->id,
                    'idnumber' => $group->idnumber,
                    'name' => $group->name,
                ];
            }
        }
    }
    elseif ($context === 'by_user') {
        // Fetch groups by user ID number
        $userIdNumber = $identifier;

        // Fetch user record
        $user = $DB->get_record('user', ['username' => $userIdNumber], 'id');
        if (!$user) {
            return $assessmentGroups;
        }

        // Fetch group IDs the user is a member of
        $groupIds = $DB->get_records('groups_members', ['userid' => $user->id], '', 'groupid');

        if (!empty($groupIds)) {
            $groupIds = array_keys($groupIds);
            [$inSql, $params] = $DB->get_in_or_equal($groupIds, SQL_PARAMS_QM, '', true);
            $groups = $DB->get_records_select('groups', "id $inSql", $params, '', 'id, idnumber, name');

            foreach ($groups as $group) {
                if (local_obu_assessment_ext_is_assessment_group_idnumber($group->idnumber)) {
                    $assessmentGroups[] = [
                        'id' => $group->id,
                        'idnumber' => $group->idnumber,
                        'name' => $group->name,
                    ];
                }
            }
        }
    }

    return $assessmentGroups;
}

/**
 * Fetches the assessment group a user belongs to for a given course module.
 *
 * @param object $user The user object.
 * @param int $courseModuleId The course module ID.
 * @param \progress_trace $trace The progress trace object for logging.
 * @return array|null Returns the user's assessment group as an associative array, or null if none is found.
 */
function local_obu_assessment_ext_get_user_assessment_group($user, $courseModuleId, \progress_trace $trace): ?array {
    // Fetch assessment groups for the course module
    $assessmentGroups = local_obu_assessment_ext_get_assessment_groups('by_assessment', $courseModuleId);

    if (empty($assessmentGroups)) {
        $trace->output('No assessment groups found for this course module.');
        return null;
    }

    // Fetch groups the user belongs to
    $userGroups = local_obu_assessment_ext_get_assessment_groups('by_user', $user->username);

    if (empty($userGroups)) {
        $trace->output('User does not belong to any assessment groups.');
        return null;
    }

    // Find the intersection of groups between assessment groups and user's groups
    foreach ($userGroups as $userGroup) {
        foreach ($assessmentGroups as $assessmentGroup) {
            if ($userGroup['id'] === $assessmentGroup['id']) {
                $trace->output("User's assessment group IDNumber: {$userGroup['idnumber']}");
                return $userGroup;
            }
        }
    }

    $trace->output('User is not a member of any valid assessment group.');
    return null;
}

/**
 * Get all assessment modules that use a specific assessment group.
 *
 * This function searches through the availability strings in `course_modules`,
 * ensuring the given assessment group's ID is present in the first "OR" block
 * of valid group conditions.
 *
 * @param stdClass $assessmentGroup The assessment group whose ID to search for.
 *
 * @return array Matching course module records that use the given assessment group.
 */
function local_obu_assessment_ext_get_assessments_by_group($assessmentGroup): array {
    global $DB;

    // Initial query to retrieve potential matches (filtering by "LIKE").
    $sql = "
        SELECT cm.id, cm.availability
        FROM {course_modules} cm
        JOIN {modules} m ON cm.module = m.id
        JOIN {course} c ON c.id = cm.course AND c.idnumber <> '' AND c.idnumber IS NOT NULL
        WHERE cm.availability LIKE :groupid
        AND m.name = :modulename
    ";
    $params = ['groupid' => '%"id":'.$assessmentGroup['id'].'%', 'modulename' => 'coursework'];

    // Fetch initial candidate course modules.
    $potentialMatches = $DB->get_records_sql($sql, $params);

    // Prepare the list of valid course modules.
    $validModules = [];

    // Parse and validate the availability conditions for each match.
    foreach ($potentialMatches as $cm) {
        // Decode the availability JSON string.
        $availabilityJson = $cm->availability;
        $availabilityConditions = local_obu_assessment_ext_extract_group_conditions($availabilityJson);

        // Check if the given assessment group ID is present in the valid conditions.
        if (in_array($assessmentGroup['id'], $availabilityConditions)) {
            $validModules[] = $cm; // Add to the list of valid modules.
        }
    }

    return $validModules;
}

/**
 * Get all **exam** assessment modules that use a specific assessment group.
 *
 * This function searches through the availability strings in `course_modules`,
 * ensuring the given assessment group's ID is present in the first "OR" block
 * of valid group conditions. It then filters results to include only those
 * modules whose `idnumber` identifies them as exams (third segment begins with "OA").
 *
 * @param stdClass $assessmentGroup The assessment group whose ID to search for.
 * @return array Matching **exam** course module records that use the given assessment group.
 */
function local_obu_assessment_ext_get_exam_assessments_by_group($assessmentGroup): array {
    global $DB;

    $sql = "
        SELECT cm.id, cm.availability, cm.idnumber
          FROM {course_modules} cm
          JOIN {modules} m ON cm.module = m.id
          JOIN {course} c ON c.id = cm.course AND c.idnumber <> '' AND c.idnumber IS NOT NULL
         WHERE cm.availability LIKE :groupid
           AND m.name = :modulename
    ";
    $params = [
        'groupid' => '%\"id\":' . $assessmentGroup->id . '%',
        'modulename' => 'coursework'
    ];

    $potentialMatches = $DB->get_records_sql($sql, $params);

    $validModules = [];

    foreach ($potentialMatches as $cm) {
        $availabilityConditions = local_obu_assessment_ext_extract_group_conditions($cm->availability);

        if (in_array($assessmentGroup->id, $availabilityConditions)) {
            if (local_obu_assessment_ext_is_exam($cm->idnumber ?? null)) {
                $validModules[] = $cm;
            }
        }
    }

    return $validModules;
}

/**
 * Compute base exam minutes from start/close timestamps.
 */
function local_obu_assessment_ext_exam_base_minutes(int $startTimestamp, int $closeTimestamp): int {
    if ($startTimestamp <= 0 || $closeTimestamp <= 0 || $closeTimestamp <= $startTimestamp) return 0;
    return (int)ceil(($closeTimestamp - $startTimestamp) / 60);
}

/**
 * Added minutes from ET code (ET25, ET33, ET50, ET60, ETX2).
 * Safe to pass values with a leading '*'.
 */
function local_obu_assessment_ext_exam_added_minutes_from_extension(int $baseMinutes, ?string $raw): int {
    if ($baseMinutes <= 0) return 0;
    $code = strtoupper(ltrim(trim((string)$raw), '*'));
    if ($code === '') return 0;

    $map = [
        'ET25' => 1.25,
        'ET33' => 1.33,
        'ET50' => 1.50,
        'ET60' => 1.66,
        'ETX2' => 2.00,
    ];
    $mult = $map[$code] ?? 1.0;
    return (int)ceil($baseMinutes * max(0.0, $mult - 1.0));
}


/**
 * Added minutes from EB code (EB5, EB10, EB15, EB20, EB30, EB60),
 * applied per STARTED hour AFTER the first, based on *effective* minutes
 * (i.e., base + ET_added).
 *
 * Safe to pass values with a leading '*'.
 */
function local_obu_assessment_ext_exam_added_minutes_from_break_effective(int $effectiveMinutes, ?string $raw): int {
    // No break up to and including 60 minutes.
    if ($effectiveMinutes <= 60) return 0;

    $code = strtoupper(ltrim(trim((string)$raw), '*'));
    if ($code === '') return 0;

    $map = [
        'EB5'  => 5,
        'EB10' => 10,
        'EB15' => 15,
        'EB20' => 20,
        'EB30' => 30,
        'EB60' => 60,
    ];
    $minsPerHour = $map[$code] ?? 0;
    if ($minsPerHour <= 0) return 0;

    // Started hours AFTER the first:
    // e.g. 1h01–2h00 → 1 unit, 2h01–3h00 → 2 units, etc.
    $eligibleUnits = (int)max(ceil(($effectiveMinutes - 60) / 60), 0);
    return $eligibleUnits * $minsPerHour;
}

/**
 * Fetch users enrolled in a course with a student role via database or meta enrolment methods.
 *
 * @param int $courseid The ID of the course to fetch enrolled students for.
 *
 * @return array An array of enrolled users. Each entry contains:
 *               - 'id': The user ID.
 *               - 'username': The username of the enrolled user.
 */
function local_obu_assessment_ext_get_enrolled_students($courseid): array {
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
 * Calculate the hard deadline for a given group, based on cutoff dates.
 *
 * This function returns the appropriate hard deadline based on the group's ID number
 * and the provided cutoff dates for Online Exams (OE) and Reassessments (RE).
 *
 * @param string $groupIdnumber The ID number of the group (used to determine deadline type).
 * @param string|null $OECutoffDate The cutoff date for Online Exams (OE), or null if not set.
 * @param string|null $RECutoffDate The cutoff date for Reassessments (RE), or null if not set.
 *
 * @return string|null The calculated hard deadline as a date string, or null if both dates are missing.
 */
function local_obu_assessment_ext_calculate_harddeadline(string $assessmentType, ?string $OECutoffDate, ?string $RECutoffDate): ?string {
    // Determine the hard deadline
    if ($assessmentType === 'original') {
        $hardDeadline = $OECutoffDate;
    } else {
        $hardDeadline = $RECutoffDate;
    }

    return $hardDeadline; // May return null if no valid dates are available
}

/**
 * Calculate a new deadline based on the original deadline, additional days, and hard deadline.
 *
 * This function takes the original deadline timestamp, adds a specified number of days,
 * and checks against a hard deadline. If the new deadline exceeds the hard deadline,
 * it adjusts the new deadline to match the hard deadline.
 *
 * @param \progress_trace $trace The progress trace object for logging.
 * @param int $deadlineTimestamp The original deadline timestamp.
 * @param int $additionalDays The number of additional days to add to the original deadline.
 * @param string $hardDeadline The hard deadline date string (format: 'd-M-y H:i').
 *
 * @return string The calculated new deadline as a formatted date string (format: 'd/m/Y H:i').
 */
function local_obu_assessment_ext_calc_new_deadline(\progress_trace $trace, $deadlineTimestamp, $additionalDays, $hardDeadline) {
    // Convert deadline timestamp into DateTime object
    $deadlineDate = (new DateTime())->setTimestamp($deadlineTimestamp);
    $trace->output('Current Deadline: ' . $deadlineDate->format('d/m/Y H:i'));

    // Clone and modify the deadline to calculate the new deadline
    $newDeadlineDate = clone $deadlineDate;
    $newDeadlineDate->modify("+$additionalDays days"); // Add additional days
    $newDeadline = $newDeadlineDate->format('d/m/Y H:i');
    $trace->output("New Deadline: $newDeadline");

    // Remove asterisk prefix if present
    if (strpos($hardDeadline, '*') === 0) {
        $hardDeadline = substr($hardDeadline, 1);
        $trace->output("Removed asterisk from hard deadline: $hardDeadline");
    }

    $hardDeadlineDate = DateTime::createFromFormat('d-M-y H:i', $hardDeadline . ' 00:00');

    if (!$hardDeadlineDate) {
        // If parsing fails, log an error and return the new deadline
        $trace->output("Invalid Hard Deadline format: $hardDeadline");
        return $newDeadline;
    }

    // Set the time of the hard deadline to match the original deadline's time
    $hardDeadlineDate->setTime((int) $deadlineDate->format('H'), (int) $deadlineDate->format('i'));
    $hardDeadlineDate->modify('-7 days');
    $trace->output('Hard Deadline (after 7 day reduction): ' . $hardDeadlineDate->format('d/m/Y H:i'));

    // Compare new deadline with hard deadline
    if ($newDeadlineDate > $hardDeadlineDate) {
        $trace->output('New Deadline exceeds Hard Deadline. Adjusting to Hard Deadline.');
        // If the new deadline exceeds the hard deadline, set the new deadline to the hard deadline
        $newDeadlineDate = $hardDeadlineDate; // Use hard deadline
        $newDeadline = $newDeadlineDate->format('d/m/Y H:i');
    }

    return $newDeadline;
}

/**
 * Recalculate the due date for an assessment, considering user extensions and course deadlines.
 *
 * This function determines the appropriate assessment group the user belongs to, calculates the
 * new deadline based on the supplied course and user extensions, and updates the due date if it differs.
 *
 * If no valid hard deadline is available, it defaults to 35 days after the initial coursework deadline
 * or a fallback value ('31-DEC-99') if the coursework deadline is not set.
 *
 * @param \progress_trace $trace         An object to handle trace output for debugging or logging.
 * @param object          $user          User object containing user data (e.g., id, username).
 * @param int             $courseModuleId The ID of the course module to process.
 *
 * @throws Exception If there are issues parsing or calculating dates.
 */
function local_obu_assessment_ext_recalculate_due_for_assessment(\progress_trace $trace, $user, $courseModuleId) {
    global $DB;

    // GET course module record
    $coursemodule = local_obu_assessment_ext_fetch_course_module($courseModuleId);
    // GET course record
    $course = local_obu_assessment_ext_fetch_course_details($coursemodule->course);
    // Get the coursework record to retrieve the deadline
    $courseworkRecord = local_obu_assessment_ext_fetch_coursework($coursemodule->instance);
    $deadline = $courseworkRecord->deadline;

    // EARLY EXAM PATH (before coursework extension logic)
    if (local_obu_assessment_ext_is_exam($coursemodule->idnumber ?? null)) {
        $trace->output("Exam detected via CM idnumber: {$coursemodule->idnumber}");

        $startTimestamp = (int)($courseworkRecord->starttime ?? 0);
        $closeTimestamp = (int)($courseworkRecord->deadline  ?? 0);
        $baseMinutes = local_obu_assessment_ext_exam_base_minutes($startTimestamp, $closeTimestamp);

        if ($baseMinutes <= 0 || $closeTimestamp <= 0) {
            $trace->output('Exam: invalid start/deadline; skipping.');
            return;
        }

        $rows = $DB->get_records_sql("
        SELECT uif.shortname, uid.data
          FROM {user_info_field} uif
          JOIN {user_info_data} uid ON uid.fieldid = uif.id
         WHERE uid.userid = :uid
           AND uif.shortname IN ('exam_extension','exam_break')",
            ['uid' => $user->id]
        );
        $extRaw = $rows['exam_extension']->data ?? '';
        $ebRaw  = $rows['exam_break']->data ?? '';


        $addExt = local_obu_assessment_ext_exam_added_minutes_from_extension($baseMinutes, $extRaw);


        $effectiveMinutes = $baseMinutes + $addExt;
        $addBreak = local_obu_assessment_ext_exam_added_minutes_from_break_effective($effectiveMinutes, $ebRaw);

        $addedTotal = $addExt + $addBreak;
        if ($addedTotal <= 0) {
            $trace->output('Exam: no effective change from ET/EB; skipping.');
            return;
        }

        $newCloseTimestamp  = $closeTimestamp + ($addedTotal * 60);
        $newDeadline = (new \DateTime("@$newCloseTimestamp"))->format('d/m/Y H:i');

        $userAssessmentGroup = local_obu_assessment_ext_get_user_assessment_group($user, $courseModuleId, $trace);
        if (!$userAssessmentGroup) {
            $trace->output('No user assessment group found; skipping exam update.');
            return;
        }

        // Clear then set new deadline (reuse your existing queue)
        local_obu_assessment_ext_submit_due_date_change($trace, $user, $courseModuleId, null, false, false, true,  $userAssessmentGroup);
        local_obu_assessment_ext_submit_due_date_change($trace, $user, $courseModuleId, $newDeadline, false, false, false, $userAssessmentGroup);

        $trace->output("Exam: base {$baseMinutes}m → +{$addExt}m (ET) → effective {$effectiveMinutes}m → +{$addBreak}m (EB) → new close {$newDeadline}");
        return; // skip coursework path
    }

    // Get the group in which this user is enrolled
    $userAssessmentGroup = local_obu_assessment_ext_get_user_assessment_group($user, $courseModuleId, $trace);
    if (!$userAssessmentGroup) {
        $trace->output('No user assessment group found for user or course module ID.');
        return;
    }

    $trace->output('Assessment Group IDNumber: ' . $userAssessmentGroup['idnumber']);

    $assessmentTypeCode = substr($userAssessmentGroup['idnumber'], -2);
    if (!empty($assessmentTypeCode)) {
        if ($assessmentTypeCode === 'OE') {
            $assessmentType = 'original';
        } else if ($assessmentTypeCode === 'RE' || $assessmentTypeCode === 'UR') {
            $trace->output("❌ Skipping extension processing for resits (RE/UR)");
            return;
        }
        $hardDeadline = local_obu_assessment_ext_calculate_harddeadline($assessmentType, $course->score_cutoff_date,
            $course->reas_score_cutoff_date);
    }
    if (empty($hardDeadline)) {
        $trace->output('Hard Deadline not set, using default');

        if (isset($deadline)) {
            // Create a DateTime object from the $deadline UNIX timestamp
            $date = (new \DateTime())->setTimestamp($deadline);

            // Add 35 days
            $date->add(new \DateInterval('P35D'));

            // Format the date as DD-MMM-YY (e.g., 10-APR-25)
            $hardDeadline = strtoupper($date->format('d-M-y'));
        } else {
            $hardDeadline = '31-DEC-99'; // Fallback if $deadline is not set
        }
    }
    $trace->output("Hard Deadline: $hardDeadline");

    $sql = "SELECT uid.data
        FROM {user_info_data} uid
        JOIN {user_info_field} uif ON uid.fieldid = uif.id
        WHERE uid.userid = :userid
        AND uif.shortname = 'extensions'";

    $userExtensionWeeks = $DB->get_record_sql($sql, ['userid' => $user->id]);
    $userServiceNeedsDays = 0;

    if ($userExtensionWeeks && isset($userExtensionWeeks->data)) {
        $data = $userExtensionWeeks->data;

        if (is_string($data) && str_starts_with($data, '*')) {
            $data = substr($data, 1);
        }

        if (is_numeric($data)) {
            $userServiceNeedsDays = (int)$data * 7;
        }
    }

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
            local_obu_assessment_ext_submit_due_date_change($trace, $user, $courseModuleId, null, false, false, true, $userAssessmentGroup);
            $additionalDays = $userServiceNeedsDays + $extensionRecord->extension_amount;
            $newDeadline = local_obu_assessment_ext_calc_new_deadline($trace, $deadline, $additionalDays, $hardDeadline);
        }
    } else {
        local_obu_assessment_ext_submit_due_date_change($trace, $user, $courseModuleId, null, false, false, true, $userAssessmentGroup);
        $newDeadline = local_obu_assessment_ext_calc_new_deadline($trace, $deadline, $userServiceNeedsDays, $hardDeadline);
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

    local_obu_assessment_ext_submit_due_date_change($trace, $user, $courseModuleId, $newDeadline, $temporaryExemption, $deletion, false,
        $userAssessmentGroup);
}

/**
 * Recalculate the due date for an assessment with unprocessed user extensions.
 *
 * This function calculates the due date for a user, considering unprocessed extensions and any defined
 * service needs. It ensures the deadline is adjusted and logs any changes made.
 *
 * The function applies a fallback (`31-DEC-99`) if the hard deadline is not set and also ensures deadlines
 * differ before submitting updates.
 *
 * @param \progress_trace $trace          An object to handle trace output for debugging or logging.
 * @param object          $user           User object containing user data (e.g., id, username).
 * @param int             $courseModuleId The ID of the course module to process.
 * @param int             $extensionAmount The extension amount in days to be applied to the assessment deadline.
 *
 * @throws Exception If there are issues parsing or calculating dates.
 */
function local_obu_assessment_ext_recalculate_due_for_assessment_with_unprocessed_extensions(\progress_trace $trace, $user, $courseModuleId,
    $extensionAmount) {
    global $DB;

    // GET course module record
    $coursemodule = local_obu_assessment_ext_fetch_course_module($courseModuleId);
    // GET course record
    $course = local_obu_assessment_ext_fetch_course_details($coursemodule->course);
    // Get the coursework record to retrieve the deadline
    $courseworkRecord = local_obu_assessment_ext_fetch_coursework($coursemodule->instance);
    $deadline = $courseworkRecord->deadline;
    // Get the group in which this user is enrolled
    $userAssessmentGroup = local_obu_assessment_ext_get_user_assessment_group($user, $courseModuleId, $trace);
    if (!$userAssessmentGroup) {
        $trace->output('No user assessment group found for user or course module ID.');
        return;
    }

    $trace->output('Assessment Group IDNumber: ' . $userAssessmentGroup['idnumber']);

    $assessmentTypeCode = substr($userAssessmentGroup['idnumber'], -2);
    if (!empty($assessmentTypeCode)) {
        if ($assessmentTypeCode === 'OE') {
            $assessmentType = 'original';
        } else if ($assessmentTypeCode === 'RE' || $assessmentTypeCode === 'UR') {
            $trace->output("❌ Skipping extension processing for resits (RE/UR)");
            return;
        }
        $hardDeadline = local_obu_assessment_ext_calculate_harddeadline($assessmentType, $course->score_cutoff_date,
            $course->reas_score_cutoff_date);
    }
    if (empty($hardDeadline)) {
        $trace->output('Hard Deadline not set, using default');

        if (isset($deadline)) {
            // Create a DateTime object from the $deadline UNIX timestamp
            $date = (new \DateTime())->setTimestamp($deadline);

            // Add 35 days
            $date->add(new \DateInterval('P35D'));

            // Format the date as DD-MMM-YY (e.g., 10-APR-25)
            $hardDeadline = strtoupper($date->format('d-M-y'));
        } else {
            $hardDeadline = '31-DEC-99'; // Fallback if $deadline is not set
        }
    }
    $trace->output("Hard Deadline: $hardDeadline");

    $sql = "SELECT uid.data
        FROM {user_info_data} uid
        JOIN {user_info_field} uif ON uid.fieldid = uif.id
        WHERE uid.userid = :userid
        AND uif.shortname = 'extensions'";

    $userExtensionWeeksRecord = $DB->get_record_sql($sql, ['userid' => $user->id]);
    $userServiceNeedsDays = 0;

    if ($userExtensionWeeksRecord && isset($userExtensionWeeksRecord->data)) {
        $data = $userExtensionWeeksRecord->data;

        if (is_string($data) && str_starts_with($data, '*')) {
            $data = substr($data, 1);
        }

        if (is_numeric($data)) {
            $userServiceNeedsDays = (int)$data * 7;
        }
    }

    if ($extensionAmount == 0) {
        $temporaryExemption = true;
    } else if ($extensionAmount == -1) {
        $deletion = true;
    } else {
        local_obu_assessment_ext_submit_due_date_change($trace, $user, $courseModuleId, null, false, true, true, $userAssessmentGroup);
        $additionalDays = $userServiceNeedsDays + $extensionAmount;
        $newDeadline = local_obu_assessment_ext_calc_new_deadline($trace, $deadline, $additionalDays, $hardDeadline);
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

    local_obu_assessment_ext_submit_due_date_change($trace, $user, $courseModuleId, $newDeadline, $temporaryExemption, $deletion, false, $userAssessmentGroup);
}

/**
 * Store known exceptional circumstances for a student.
 *
 * This function inserts a record into the 'local_obu_assessment_ext' table to track
 * exceptional circumstances for a student, including the number of extension days
 * and the course module ID.
 *
 * @param string $studentIdNumber The ID number of the student.
 * @param int $extensionDays The number of extension days granted.
 * @param int|null $courseModuleId The ID of the course module (optional).
 *
 * @return bool True on success, false on failure.
 */
function local_obu_assessment_ext_store_known_exceptional_circumstances($studentIdNumber, $extensionDays, $courseModuleId=null) {
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
 * Submit a due date change for a course module.
 *
 * This function prepares and submits a due date change request for a specific course module,
 * including the user, course, assessment group, and new deadline.
 *
 * @param \progress_trace $trace The progress trace object for logging.
 * @param object $user The user object containing user data (e.g., id, username).
 * @param int $courseModuleId The ID of the course module to process.
 * @param string|null $newDeadline The new deadline date string (format: 'd-M-y H:i').
 * @param bool|null $temporaryExemption Indicates if this is a temporary exemption.
 * @param bool|null $deletion Indicates if this is a deletion request.
 * @param bool|null $deleteExisting Indicates if existing extensions should be deleted.
 * @param array|null $assessmentGroup The assessment group details.
 */
function local_obu_assessment_ext_submit_due_date_change(\progress_trace $trace, $user, $courseModuleId, $newDeadline, $temporaryExemption = null,
    $deletion = null, $deleteExisting = null, $userAssessmentGroup = null) {
    global $DB;

    $courseModule = local_obu_assessment_ext_fetch_course_module($courseModuleId, 'course');
    $course = local_obu_assessment_ext_fetch_course_details($courseModule->course);

    $conditions = [
        'student_id' => $user->username,
        'assessment_id' => $courseModuleId,
    ];

    $select = $temporaryExemption
        ? 'student_id = :student_id AND assessment_id = :assessment_id AND extension_amount = 0'
        : 'student_id = :student_id AND assessment_id = :assessment_id AND extension_amount NOT IN (0, -1)';

    try {
        $existingExtension = $DB->record_exists_select(
            'local_obu_assessment_ext',
            $select,
            $conditions
        );
    } catch (dml_exception $e) {
        debugging('Error checking assessment extension existence: ' . $e->getMessage(), DEBUG_DEVELOPER);
        $existingExtension = false;
    }

    switch (true) {
        case $temporaryExemption:
            $date = 'temporary';
            $type = 'coursework_temporary_exemption';
            $action = $existingExtension ? 'update' : 'insert';
            break;

        case $deletion:
            $date = null;
            $type = 'coursework_temporary_exemption';
            $action = 'delete';
            break;

        case $deleteExisting:
            $date = null;
            $type = 'coursework_mitigations';
            $action = 'delete';
            break;

        default:
            $date = $newDeadline;
            $type = 'coursework_mitigations';
            $action = $existingExtension ? 'update' : 'insert';
            break;
    }

    $dueDateChange = new stdClass();
    $dueDateChange->user = $user->username;
    $dueDateChange->course = $course->idnumber;
    $dueDateChange->assessment = $userAssessmentGroup['name'];
    $dueDateChange->date = $date;
    $dueDateChange->timelimit = null;
    $dueDateChange->type = $type;
    $dueDateChange->reason_code = null;
    $dueDateChange->reason_desc = null;
    $dueDateChange->action = $action;
    $dueDateChange->timecreated = time();

    try {
        $DB->insert_record('module_extensions_queue', $dueDateChange);
    } catch (\moodle_exception $e) {
        $trace->output($e->getMessage());
        $trace->output($e->getFile());
        $trace->output($e->getTraceAsString());
        $trace->output($e->debuginfo);

        throw new \moodle_exception('Error storing module extensions queue');
    }
}

/**
 * Create process_deadline_change Task
 *
 *
 *
 * @param \progress_trace $trace The progress trace object for logging.
 * @param int $courseworkInstanceId The ID of the coursework row
 *
 * @return void
 */
function local_obu_assessment_ext_create_task_for_course_mod_change($trace, $courseworkInstanceId) {
    // Convert coursework instance ID to course module ID.
    $courseModuleId = local_obu_assessment_ext_get_coursemodule_id($courseworkInstanceId);

    if (!$courseModuleId) {
        $trace->output("No course module found for coursework instance ID: $courseworkInstanceId");
        return;
    }

    $courseModule = local_obu_assessment_ext_fetch_course_module($courseModuleId);
    if (!$courseModule) {
        $trace->output("No course module found with ID: $courseModuleId");
        return;
    }

    $course = local_obu_assessment_ext_fetch_course_details($courseModule->course);
    if (!$course->idnumber) {
        $trace->output("Skipping course '{$course->fullname}' (id: {$course->id}) — no idnumber set.");
        return;
    }

    $newRestrictions = $courseModule->availability;
    $trace->output("Availability: $newRestrictions");

    $users = local_obu_assessment_ext_get_enrolled_students($courseModule->course);
    $trace->output('Users on Course: ' . count($users));

    $modinfo = get_fast_modinfo($courseModule->course);
    $courseModuleUsers = [];

    try {
        $cm_info = $modinfo->get_cm($courseModule->id);
        $info = new \core_availability\info_module($cm_info);
        $courseModuleUsers = $info->filter_user_list($users);
    } catch (\moodle_exception $e) {
        $trace->output('Availability API error: ' . $e->errorcode);
        preg_match_all('/"group","id":(\d+)/', $newRestrictions, $matches);
        $groupIds = $matches[1];

        foreach ($groupIds as $groupId) {
            $groupUsers = local_obu_assessment_ext_get_users_by_group($groupId);
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

/**
 * Get users by assessment user group
 *
 * @param $assessmentGroupId
 * @return array of users with UserId and Username
 */
function local_obu_assessment_ext_get_users_by_group($assessmentGroupId): array {
    global $DB;
    $users = array();
    $userIds = $DB->get_records('groups_members', array('groupid' => $assessmentGroupId), '', 'userid');

    if (empty($userIds)) {
        return $users;
    }

    $userIds = array_keys($userIds);

    if (!empty($userIds)) {
        [$inSql, $params] = $DB->get_in_or_equal($userIds, SQL_PARAMS_QM, '', true);

        $sql = "SELECT id, username
            FROM {user}
            WHERE id $inSql";

        $users = $DB->get_records_sql($sql, $params);
    }

    return $users;
}