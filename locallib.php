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

    $extension = new stdClass();
    $extension->student_id   = $studentIdNumber;
    $extension->assessment_id    = $assessmentIdNumber;
    $extension->extension_amount = $extensionDays;
    $extension->is_processed = 0;
    $extension->timestamp = time();

    $DB->insert_record('local_obu_assessment_ext', $extension);

    return true;
}

//TODO:: Same function as above for cosector table in doc, dont worry about variables you dont have yet, need to come from Jock
function local_obu_submit_due_date_change($studentIdNumber, $extensionDays, $assessmentIdNumber=null) {

    $dueDateChange = new stdClass();
    $dueDateChange->user   = $studentIdNumber;
    $dueDateChange->course    = '';
    $dueDateChange->assessment = '';
    $dueDateChange->date_time_value = '';
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
    //TODO:: find out user's moodle courses and iterate over them to get user groups and find out which groups are for assessments and return those
    return groups_get_user_groups('', $user);
}

//TODO :: get users by assesment group
function local_obu_get_users_by_assessment_group($assessmentGroup): array {
    $users = array();
    return $users;
}

function local_obu_get_assessments_by_assessment_group($assessmentGroup) {
    //TODO:: get assessments out of the assessment group
    return;
}

function local_obu_get_assessment_groups_by_assessment($assessment) {
    //TODO:: get assessment groups using an assessment object/id
    return;
}

function local_obu_recalculate_due_for_assessment($user, $assessment, $trace = null) {
    echo ("Recalulating due date for user: " . $user->firstname . " and assessment: " . $assessment);
    //TODO:: recalculate due date using params, take assessment deadline and add extension days from db table that stores this information. Send new date to submit_due_date_change
}

function get_groups_from_access_restrictions($accessRestrictions): array {
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