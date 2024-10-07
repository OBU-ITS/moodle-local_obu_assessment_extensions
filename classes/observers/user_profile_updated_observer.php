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
 * Plugin user profile updated event observer
 *
 * @package    local_obu_assessment_extensions
 * @author     Emir Kamel
 * @copyright  2024, Oxford Brookes University {@link http://www.brookes.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/obu_assessment_extensions/locallib.php');

class user_profile_updated_observer {
    public static function user_profile_updated(\core\event\user_updated $event) {
        $eventData = $event->get_data();

        $oldProfile = $eventData['other']['oldprofile'] ?? [];
        $newProfile = $eventData['other']['profile'] ?? [];

        error_log('Old service_needs: ' . ($oldProfile['service_needs'] ?? 'none'));
        error_log('New service_needs: ' . ($newProfile['service_needs'] ?? 'none'));

        if (isset($oldProfile['service_needs']) && isset($newProfile['service_needs']) && $oldProfile['service_needs'] !== $newProfile['service_needs']) {
            $userId = $eventData['userid'];
            $user = \core_user::get_user($userId);

            $assessmentGroups = local_obu_get_assessment_groups_by_user($userId);
            $assessments = array();

            foreach ($assessmentGroups as $group) {
                $groupAssessments = local_obu_get_assessments_by_assessment_group($group);
                $assessments = array_merge($assessments, $groupAssessments);
            }

            $task = new \local_obu_assessment_extensions\task\adhoc_process_user_service_needs_change();
            $task->set_custom_data(['assessments' => $assessments, 'user' => $user]);
            \core\task\manager::queue_adhoc_task($task);
        }
    }
}