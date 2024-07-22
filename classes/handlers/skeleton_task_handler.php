<?php
namespace local_obu_assessment_extensions\handlers;

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

use local_obu_assessment_extensions\services\skeleton_task_service;
use progress_trace;

class skeleton_task_handler
{
    private skeleton_task_service $skeletonTaskService;
    private progress_trace $trace;

    public function __construct($trace)
    {
        $this->skeletonTaskService = skeleton_task_service::getInstance();

        $this->trace = $trace;
    }

    public function handleSkeleton() {
        //TODO: Call functions from service here
    }
}