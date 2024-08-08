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
    public static function coursework_deadline_changed(\core\event\course_module_updated $event) {
        global $DB;

        $eventData = $event->get_data();
        $cmid = $eventData['objectid'];
        $context = \context_module::instance($cmid);

        if(strtok($context->get_context_name(), ':')  != 'Assignment') {
            return;
        }
        //TODO: process with an ad hoc task passing in assessment id
    }

    //TODO: ADHOC TASK needs to decode access restrictions and loop over groups -> loop over users in groups and recalc due dates for the assessments. Ask for more info
}