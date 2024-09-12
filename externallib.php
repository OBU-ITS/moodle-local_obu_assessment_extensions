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
require_once($CFG->dirroot . '/local/obu_assessment_extensions/locallib.php');

class local_obu_assessment_extensions_external extends external_api {

    public static function award_exceptional_circumstance_parameters() {
        return new external_function_parameters(
            array(
                'studentidnumber' => new external_value(PARAM_TEXT, 'Student ID number', true),
                'extensiondays' => new external_value(PARAM_TEXT, 'Number of days in this extension award', true),
                'assessmentidnumber' => new external_value(PARAM_TEXT, 'Assessment ID number', false),
            )
        );
    }

    public static function award_exceptional_circumstance_returns() {
        return new external_single_structure(
            array(
                'result' => new external_value(PARAM_INT, 'Result'),
                'message' => new external_value(PARAM_TEXT, 'Message', false)
            )
        );
    }

    public static function award_exceptional_circumstance($studentidnumber, $extensiondays, $assessmentidnumber=null) {
        global $DB;
        // Context validation
        self::validate_context(context_system::instance());

        // Parameter validation
        self::validate_parameters(
            self::award_exceptional_circumstance_parameters(), array(
                'studentidnumber' => $studentidnumber,
                'extensiondays' => $extensiondays,
                'assessmentidnumber' => $assessmentidnumber,
            )
        );

        if (!($DB->record_exists('user', array('username' => $studentidnumber)))) {
            return array('result' => -3, 'message' => 'Cannot find user with username (' . $studentidnumber . ')');
        }

        if ($assessmentidnumber == null) {
            $assessmentgroups = local_obu_get_assessment_groups_by_user($studentidnumber);

            foreach ($assessmentgroups as $assessmentgroup){
                $assessments = local_obu_get_assessments_by_assessment_group($assessmentgroup);
                foreach ($assessments as $assessment){
                    local_obu_assess_ex_store_known_exceptional_circumstances($studentidnumber, $extensiondays, $assessment->idnumber);
                }
            }
            return array('result' => 1);
        }
        if(local_obu_assess_ex_store_known_exceptional_circumstances($studentidnumber, $extensiondays, $assessmentidnumber)) {
            return array('result' => 1);
        }

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