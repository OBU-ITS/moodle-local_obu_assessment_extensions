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
        $courseModuleInstanceId = $event->objectid;
        $eventDescription = $event->get_description();

        $trace = new \null_progress_trace();
        self::coursemod_access_restriction_or_deadline_changed_internal($trace, $courseModuleInstanceId, $eventDescription);
    }

    public static function coursemod_access_restriction_or_deadline_changed_internal($trace, $courseModuleInstanceId, $eventDescription){
        global $DB;

        $description = strtolower($eventDescription);
        if (!strpos($description, 'access restriction') && !strpos($description, 'deadline')) {
            $trace->output("No changes we need");
            $trace->output("Description: $description");
            return;
        }

        local_obu_create_task_for_course_mod_change($trace, $courseModuleInstanceId);
    }
}