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
 * OBU Assessment Extensions - Database upgrade
 *
 * @package    obu_assessment_extensions
 * @category   local
 * @author     Emir Kamel
 * @copyright  2024, Oxford Brookes University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 */

function xmldb_local_obu_assessment_extensions_upgrade($oldversion = 0) {
    global $DB;
    $dbman = $DB->get_manager();

    $result = true;

    if ($oldversion < 2024082201) {
        // Define the table and the field.
        $table = new xmldb_table('local_obu_assessment_ext');
        $field = new xmldb_field('assessment_id', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, false, null, 'student_id');

        // Check if the field exists and is currently nullable.
        if ($dbman->field_exists($table, $field)) {
            // Update the field to be non-nullable.
            $dbman->change_field_notnull($table, $field);
        }

        // Savepoint reached.
        upgrade_plugin_savepoint(true, 2024082201, 'local', 'obu_assessment_extensions');
    }

    if ($oldversion < 2024100401) {
        $table = new xmldb_table('module_extensions_queue');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('user', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL);
        $table->add_field('course', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
        $table->add_field('assessment', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
        $table->add_field('date', XMLDB_TYPE_CHAR, '16', null, false);
        $table->add_field('timelimit', XMLDB_TYPE_CHAR, '10', null, false, false, null);
        $table->add_field('type', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
        $table->add_field('reason_code', XMLDB_TYPE_CHAR, '10', null, false);
        $table->add_field('reason_desc', XMLDB_TYPE_CHAR, '100', null, false);
        $table->add_field('action', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally create table
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2024101101, 'local', 'obu_assessment_extensions');
    }

    return $result;
}