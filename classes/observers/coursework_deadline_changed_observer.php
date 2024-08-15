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

use mod_hvp\event;

/**
 * Plugin coursework deadline changed event observer
 *
 * @package    local_obu_assessment_extensions
 * @author     Emir Kamel
 * @copyright  2024, Oxford Brookes University {@link http://www.brookes.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class coursework_deadline_changed_observer {
    public static function coursework_deadline_changed(mod_coursework\event\coursework_deadline_changed $event) {
        $eventData = $event->get_data();

        $cmid = $eventData['objectid'];
        $context = \context_module::instance($cmid);

        //TODO:: May need to change context name depending on Co-sector activity types etc
        if (strtok($context->get_context_name(), ':') != 'Assignment') {
            echo "coursework name check failed \n";
            return;
        }

        //courseModule in this case is the activity in the Moodle course(e.g.Coursework)
        $courseModule = get_coursemodule_from_id(null, $cmid, 0, false, MUST_EXIST);

        $courseId = $courseModule->course;
        $courseContext = \context_course::instance($courseId);
        $courseUsers = get_enrolled_users($courseContext);
        $courseModuleUsers = self::filter_course_module_user_list($courseUsers, 'mod/' . $courseModule->modname . ':view', $context);

        $task = new \local_obu_assessment_extensions\task\adhoc_process_deadline_change();
        $task->set_custom_data(['assessment' => $cmid, 'assessmentUsers' => $courseModuleUsers]);
        \core\task\manager::queue_adhoc_task($task);
    }

    private static function filter_course_module_user_list($users, $capability, $context): array {
        $filtered_users = [];

        foreach ($users as $user) {
            // Check if the user has the specified capability in the given context
            if (has_capability($capability, $context, $user->id)) {
                $filtered_users[] = $user;
            }
        }

        return $filtered_users;
    }
}