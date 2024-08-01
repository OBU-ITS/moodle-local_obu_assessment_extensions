<?php
namespace local_obu_assessment_extensions\services;

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
 * @package    local_obu_assessment_extensions
 * @author     Emir Kamel
 * @copyright  2024, Oxford Brookes University {@link http://www.brookes.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class local_obu_process_exceptional_circumstances_service {

    private static ?local_obu_process_exceptional_circumstances_service $instance = null;
    public static function getInstance() : local_obu_process_exceptional_circumstances_service
    {
        if (self::$instance == null)
        {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Retrieves all unprocessed extensions from the database.
     *
     * @return array An array of unprocessed extension records.
     */
    public function get_unprocessed_extensions() {
        global $DB;

        $sql = "SELECT * FROM {local_obu_assessment_ext} WHERE is_processed = 0";

        return $DB->get_records_sql($sql);
    }

    public function process_extensions($unprocessedExtensions){
        global $DB;

        foreach ($unprocessedExtensions as $unprocessedExtension) {
            $assessmentGroups = local_obu_get_assessment_groups_by_user($unprocessedExtension->student_id);
            foreach ($assessmentGroups as $assessmentGroup) {
                $assessments = local_obu_get_assessments_by_assessment_group($assessmentGroup);
                foreach ($assessments as $assessment) {
                    local_obu_recalculate_due_for_assessment($unprocessedExtension->student_id, $assessment, $unprocessedExtension->extension_amount);
                    $DB->set_field('local_obu_assessment_ext', 'is_processed', 1);

                }
            }
        }
    }

}