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
