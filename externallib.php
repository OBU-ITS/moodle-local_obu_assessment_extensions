<?php

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
 * OBU Assessment extensions - external library
 *
 * @package    local_obu_assessment_extensions
 * @author     Emir Kamel
 * @copyright  2024, Oxford Brookes University {@link http://www.brookes.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->libdir . "/externallib.php");

class local_obu_assessment_extensions_external extends external_api {

    public static function api_parameters() {
        return new external_function_parameters(
            array(
                'assessmentIdNumber' => new external_value(PARAM_TEXT, 'Assessment ID number'),
                'studentIdNumber' => new external_value(PARAM_TEXT, 'Student ID number'),
                'extensionDays' => new external_value(PARAM_TEXT, 'Number of days in this extension award'),
                'processedInd' => new external_value(PARAM_BOOL, 'Processed indicator'),
            )
        );
    }

    public static function api_returns() {
        return new external_single_structure(
            array(
                'result' => new external_value(PARAM_INT, 'Result')
            )
        );
    }

    public static function award_exceptional_circumstance($assessmentIdNumber, $studentIdNumber, $extensionDays, $processedInd) {
        global $DB;

        // Context validation
        self::validate_context(context_system::instance());

        // Parameter validation
        self::validate_parameters(
            self::add_session_parameters(), array(
                'assessmentIdNumber' => $assessmentIdNumber,
                'studentIdNumber' => $studentIdNumber,
                'extensionDays' => $extensionDays,
                'processedInd' => $processedInd,
            )
        );

        if (strlen($assessmentIdNumber) == 0) {
            return array('result' => -1);
        }

        //TODO: Validate assessmentIdNumber here when you find out where we are storing them
//        if (!($courseRecord = $DB->get_record('course', array('idnumber' => $courseIdNumber)))) {
//            return array('result' => -2);
//        }

        if (!($userRecord = $DB->get_record('user', array('username' => $studentIdNumber)))) {
            return array('result' => -3);
        }

        //TODO: Modify this bit to do the actual thingy
//        $group = local_obu_timetable_usergroups_get_group($courseRecord->id, $courseRecord->idnumber, $courseRecord->shortname, $instanceName, $groupName);
//        if(groups_add_member($group->id, $userRecord->id, 'local_obu_timetable_usergroups'))
//        {
//            return array('result' => 1);
//        }

        return array('result' => -9);
    }

    public static function get_settings_parameters() {
        return new external_function_parameters(
            array(
            )
        );
    }

    public static function get_settings_returns() {
        return new external_single_structure(
            array(
                'enabled' => new external_value(PARAM_BOOL, 'Enabled')
            )
        );
    }

    public static function get_settings(){
        $enabled = get_config('local_obu_assessment_extensions', 'enable');
        return array('enabled' => $enabled);
    }
}