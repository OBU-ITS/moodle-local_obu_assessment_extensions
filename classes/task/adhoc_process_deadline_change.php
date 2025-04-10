<?php

namespace local_obu_assessment_extensions\task;

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
 * Adhoc task to process exceptional circumstances
 *
 * @package    local_obu_assessment_extensions
 * @author     Emir Kamel
 * @copyright  2024, Oxford Brookes University {@link http://www.brookes.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/obu_assessment_extensions/locallib.php');

class adhoc_process_deadline_change extends \core\task\adhoc_task {
    public function execute() {
        $customData = $this->get_custom_data();
        $courseModuleId = $customData->courseModuleId;
        $courseModuleUsers = $customData->courseModuleUsers;

        $trace = new \text_progress_trace();
        foreach ($courseModuleUsers as $user) {
            local_obu_assessment_ext_recalculate_due_for_assessment($trace, $user, $courseModuleId);
        }
        $trace->finished();
    }
}