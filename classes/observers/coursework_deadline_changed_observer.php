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
        global $DB;

        $eventData = $event->get_data();
        $cmid = $eventData['objectid'];
        $context = \context_module::instance($cmid);

        if(strtok($context->get_context_name(), ':')  != 'coursework') {
            return;
        }

        $task = new \local_obu_assessment_extensions\task\adhoc_process_exceptional_circumstance();
        $task->set_custom_data(['assessmentId' => $cmid]);
        \core\task\manager::queue_adhoc_task($task);
    }
}