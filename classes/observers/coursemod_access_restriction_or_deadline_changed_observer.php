<?php

namespace local_obu_assessment_extensions\observers;

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
 * Plugin coursework access restriction changed event observer
 *
 * @package    local_obu_assessment_extensions
 * @author     Emir Kamel
 * @copyright  2024, Oxford Brookes University {@link http://www.brookes.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/obu_assessment_extensions/locallib.php');

class coursemod_access_restriction_or_deadline_changed_observer {
    public static function coursemod_access_restriction_or_deadline_changed(\mod_coursework\event\coursework_settings_updated $event) {

        $eventData = $event->get_data();
        $eventDescription = strtolower($event->get_description());
        $objectid = $eventData['objectid'];

        $trace = new \null_progress_trace();
        self::coursemod_access_restriction_or_deadline_changed_internal($trace, $objectid, $eventDescription);
    }

    public static function coursemod_access_restriction_or_deadline_changed_internal($trace, $objectid, $eventDescription){
        global $DB;

        if (!strpos($eventDescription, 'access restriction') && !strpos($eventDescription, 'deadline')) {
            $trace->output("No changes we need");
            $trace->output("Description: $eventDescription");
            return;
        }

        $sql = "SELECT cm.id 
        FROM {course_modules} cm
        JOIN {modules} m ON cm.module = m.id AND m.name = 'coursework'
        WHERE cm.instance = :objectid";

        $cmid = $DB->get_record_sql($sql, ['objectid' => $objectid]);

        if(!($courseModule = get_coursemodule_from_id('coursework', $cmid->id, 0, false, MUST_EXIST))) {
            $trace->output("No courseModule found");
            $trace->output("ObjectId: $objectid");
        }

        $newRestrictions = $courseModule->availability;
        $trace->output("Availablity: $newRestrictions");

        $courseContext = \context_course::instance($courseModule->course);
        $users = get_enrolled_users($courseContext);
        $trace->output("Users on Course: " . count($users));

        $modinfo = get_fast_modinfo($courseModule->course);
        $cm_info = $modinfo->get_cm($objectid);

        $info = new \core_availability\info_module($cm_info);
        $courseModuleUsers = $info->filter_user_list($users);
        $trace->output("Filtered Users: " . count($courseModuleUsers));

//        $decodedRestrictions = json_decode($newRestrictions, true);
//        $groups = local_obu_get_groups_from_access_restrictions($decodedRestrictions);
//        $courseModuleUsers = array();
//
//        foreach ($groups as $group){
//            $groupUsers = local_obu_get_users_by_assessment_group($group);
//            $courseModuleUsers = array_merge($courseModuleUsers, $groupUsers);
//        }

        $task = new \local_obu_assessment_extensions\task\adhoc_process_deadline_change();
        $task->set_custom_data(['assessment' => $cmid, 'assessmentUsers' => $courseModuleUsers]);

        var_dump($task);

        \core\task\manager::queue_adhoc_task($task);

        $trace->output("Task created");
    }
}