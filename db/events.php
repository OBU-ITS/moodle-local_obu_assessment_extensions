<?php
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
 * Plugin event observers
 *
 * @package    local_obu_assessment_extensions
 * @author     Emir Kamel
 * @copyright  2024, Oxford Brookes University {@link http://www.brookes.ac.uk/}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => 'mod_coursework\event\coursework_deadline_changed',
        'callback'  => 'local_obu_coursework_deadline_changed_observer::coursework_deadline_changed',
        'includefile' => '/local/obu_assessment_extensions/observers/coursework_deadline_changed_observer.php',
        'priority'    => 1000,
        'internal'    => false,
    ],
    [
        'eventname' => '\core\event\course_module_updated',
        'callback'  => 'local_obu_coursemod_access_restriction_changed_observer::coursemod_access_restriction_changed',
        'includefile' => '/local/obu_assessment_extensions/observers/coursemod_access_restriction_changed_observer.php',
        'priority'    => 1000,
        'internal'    => false,
    ],
    [
        'eventname' => '\core\event\course_module_created',
        'callback'  => 'local_obu_coursemod_created_observer::coursemod_created',
        'includefile' => '/local/obu_assessment_extensions/observers/coursemod_created_observer.php',
        'priority'    => 1000,
        'internal'    => false,
    ],
    [
        'eventname' => '\core\event\user_updated',
        'callback'  => 'local_user_profile_updated_observer::user_profile_updated',
        'includefile' => '/local/obu_assessment_extensions/observers/user_profile_updated_observer.php',
        'priority'    => 1000,
        'internal'    => false,
    ],
];