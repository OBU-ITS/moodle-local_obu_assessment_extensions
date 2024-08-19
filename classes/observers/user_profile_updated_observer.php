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
use core_calendar\local\event\entities\event;
use mod_forum\local\exporters\group;

/**
 * Plugin user profile updated event observer
 *
 * @package    local_obu_assessment_extensions
 * @author     Emir Kamel
 * @copyright  2024, Oxford Brookes University {@link http://www.brookes.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
class user_profile_updated_observer {
    public static function user_profile_updated(\core\event\user_updated $event) {
        // Get event data
        $eventData = $event->get_data();

        // Extract old and new profile data
        $oldProfile = $eventData['other']['oldprofile'] ?? [];
        $newProfile = $eventData['other']['profile'] ?? [];

        //TODO:: more info on how service needs profile field works (is it default null and changed later or can it start with a specific need?)

        // Check if the 'service_needs' profile field was updated
        if (isset($oldProfile['service_needs']) && isset($newProfile['service_needs']) && $oldProfile['service_needs'] !== $newProfile['service_needs']) {
            $userId = $eventData['userid'];
            $assessmentGroups = local_obu_get_assessment_groups_by_user($userId);
            $assessments = array();

            foreach ($assessmentGroups as $group) {
                $groupAssessments = local_obu_get_assessments_by_assessment_group($group);
                $assessments = array_merge($assessments, $groupAssessments);
            }

            //TODO:: Create adhoc task to process deadline change for the assessments

            // Process the updated field
            self::process_service_needs_update($userId, $newServiceNeeds);
        }


    }
}