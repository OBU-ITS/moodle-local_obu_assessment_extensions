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
    $params = ['groupid' => '%"id":'.$assessmentGroup->id.'%', 'modulename' => 'coursework'];

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
        if (in_array($assessmentGroup->id, $availabilityConditions)) {
            $validModules[] = $cm; // Add to the list of valid modules.
        }
    }

    return $validModules;
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
 * Fetch custom date field values for a course and set default values if unset.
 *
 * @param int $courseId The ID of the course.
 * @param int $defaultDeadline The default deadline (used for fallback dates).
 *
 * @return array An associative array of custom field values with keys:
 *               - 'ssbsect_score_cutoff_date'
 *               - 'ssbsect_reas_score_ctof_date'
 */
function local_obu_assessment_ext_fetch_banner_cutoff_dates($course, $dueDate) {
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
function local_obu_assessment_ext_calculate_harddeadline(string $groupIdnumber, ?string $OECutoffDate, ?string $RECutoffDate): ?string {
    // Determine the hard deadline
    if (substr($groupIdnumber, -2) === 'OE') {
        $hardDeadline = $OECutoffDate;
    } else {
        $hardDeadline = $RECutoffDate;
    }

    return $hardDeadline; // May return null if no valid dates are available
}




