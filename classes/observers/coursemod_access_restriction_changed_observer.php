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

class coursemod_access_restriction_changed_observer {
    public static function coursemod_access_restriction_changed(\core\event\course_module_updated $event) {
        global $DB;

        $eventData = $event->get_data();
        $legacyEventData = $event->get_data()['other'];

        $cmid = $eventData['objectid'];

        $sql = "SELECT m.*
        FROM {course_modules} cm
        JOIN {modules} m ON cm.module = m.id
        WHERE cm.id = :cmid";

        $moduleRecord = $DB->get_record_sql($sql, ['cmid' => $cmid]);
        //TODO:: May need to change name depending on Co-sector activity types etc
        if (!$moduleRecord || $moduleRecord->name !== 'assignment') {
            return;
        }

        //courseModule in this case is the activity in the Moodle course(e.g.Coursework)
        $courseModule = get_coursemodule_from_id(null, $cmid, 0, false, MUST_EXIST);

        $newRestrictions = $courseModule->availability;
        $oldRestrictions = $legacyEventData['availability'] ?? null;

        if ($newRestrictions !== $oldRestrictions) {
            $decodedRestrictions = json_decode($newRestrictions, true);
            $groups = local_obu_get_groups_from_access_restrictions($decodedRestrictions);
            $courseModuleUsers = array();

            foreach ($groups as $group){
                $groupUsers = local_obu_get_users_by_assessment_group($group);
                $courseModuleUsers = array_merge($courseModuleUsers, $groupUsers);
            }

            $task = new \local_obu_assessment_extensions\task\adhoc_process_deadline_change();
            $task->set_custom_data(['assessment' => $cmid, 'assessmentUsers' => $courseModuleUsers]);
            \core\task\manager::queue_adhoc_task($task);
        }
    }
}