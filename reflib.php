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

function local_obu_assess_ex_create_task_for_course_mod_change($trace, $courseModuleInstanceId) {
global $DB;

// Get the relevant course module details for this instance ID.
$sql = "SELECT cm.id, cm.course, cm.availability
FROM {course_modules} cm
JOIN {modules} m ON cm.module = m.id AND m.name = 'coursework'
WHERE cm.instance = :instanceid";
$courseModule = $DB->get_record_sql($sql, ['instanceid' => $courseModuleInstanceId]);

// Get course information and check if idnumber exists (external system identifier).
$course = $DB->get_record('course', ['id' => $courseModule->course], 'idnumber', MUST_EXIST);
if (!$course->idnumber) {
return; // Exit if there's no idnumber.
}

if (!$courseModule) {
$trace->output("No course module found for instance ID: $courseModuleInstanceId");
return;
}

$newRestrictions = $courseModule->availability;
$trace->output("Availability: $newRestrictions");

$courseContext = \context_course::instance($courseModule->course);
$users = local_obu_assess_ext_get_enrolled_students($courseModule->course);
$trace->output('Users on Course: ' . count($users));

$modinfo = get_fast_modinfo($courseModule->course);
$courseModuleUsers = [];

try {
$cm_info = $modinfo->get_cm($courseModule->id);
$info = new \core_availability\info_module($cm_info);
$courseModuleUsers = $info->filter_user_list($users);
} catch (\moodle_exception $e) {
$trace->output('Availability API error: ' . $e->errorcode);
$groupIds = [];
preg_match_all('/"group","id":(\d+)/', $newRestrictions, $matches);
$groupIds = $matches[1];

foreach ($groupIds as $groupId) {
$groupUsers = local_obu_get_users_by_assessment_group($groupId);
$courseModuleUsers = array_merge($courseModuleUsers, $groupUsers);
}
}

if (empty($courseModuleUsers)) {
$trace->output('No valid users found with permissions; task creation aborted.');
return;
}

$trace->output('Filtered Users: ' . count($courseModuleUsers));
$trace->output('Filtered User IDs: ' . implode(', ', array_map(function($user) {
return $user->id;
}, $courseModuleUsers)));

$task = new \local_obu_assessment_extensions\task\adhoc_process_deadline_change();
$task->set_custom_data(['courseModuleId' => $courseModule->id, 'courseModuleUsers' => $courseModuleUsers]);

$trace->output('Task created');
\core\task\manager::queue_adhoc_task($task);
$trace->output('Task queued');
}