<?php

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
 * OBU Assessment Extensions - service functions
 * @package   local_obu_assessment_extensions
 * @author    Emir Kamel
 * @copyright 2024, Oxford Brookes University {@link http://www.brookes.ac.uk/}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Define the web service functions to install.
$functions = array(
    'local_obu_assessment_extensions_external_award_exceptional_circumstance' => array(
        'classname'   => 'local_obu_assessment_extensions_external',
        'methodname'  => 'award_exceptional_circumstance',
        'classpath'   => 'local/obu_assessment_extensions/externallib.php',
        'description' => 'Takes in a student id, assessment id and number of days to extend and processes that information to be stored in a table',
        'type'        => 'write',
        'capabilities'=> ''
    )
);

// Define the services to install as pre-build services.
$services = array(
    'OBU Assessment Extensions' => array(
        'shortname' => 'obu_assessment_extensions',
        'functions' => array(
            'local_obu_assessment_extensions_external_award_exceptional_circumstance'
        ),
        'restrictedusers' => 1,
        'enabled' => 1
    )
);
