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
 * Learning activities detail view for userstatsadvanced.
 *
 * @package    block_configurable_reports
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../../../../config.php");

$courseid = required_param('courseid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$selectedcmidsraw = optional_param('filter_userstatsadvanced_selectedcmids', '', PARAM_RAW_TRIMMED);
$selectedcmids = userstatsadvanced_show_activities_parse_selected_cmids($selectedcmidsraw);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$targetuser = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
if (!empty($targetuser->deleted)) {
    throw new moodle_exception('invaliduser');
}

// Force user login in course (SITE or Course).
if ((int) $course->id === SITEID) {
    require_login();
    $context = context_system::instance();
} else {
    require_login($course);
    $context = context_course::instance($course->id);
}

$url = new moodle_url('/blocks/configurable_reports/components/columns/userstatsadvanced/show_activities.php', [
    'courseid' => $courseid,
    'userid' => $userid,
]);
if ($selectedcmidsraw !== '') {
    $url->param('filter_userstatsadvanced_selectedcmids', implode(',', $selectedcmids));
}
$title = 'Actividades de aprendizaje';
$params = [
    'userid' => $userid,
    'courseid' => $courseid,
    'modname' => 'assign',
];
$selectedwhere = '';
if (!empty($selectedcmids)) {
    [$selectedinsql, $selectedinparams] = $DB->get_in_or_equal($selectedcmids, SQL_PARAMS_NAMED, 'selectedcmid');
    $selectedwhere = " AND cm.id $selectedinsql";
    $params = array_merge($params, $selectedinparams);
}

$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_url($url);
$PAGE->set_title($title);
$PAGE->set_heading(format_string($course->fullname));

$activitysql = "SELECT cm.id AS coursemoduleid,
                       a.name AS activityname,
                       cmc.completionstate,
                       cmc.timemodified
                  FROM {course_modules} cm
                  JOIN {modules} m
                    ON m.id = cm.module
                  JOIN {assign} a
                    ON a.id = cm.instance
             LEFT JOIN {course_modules_completion} cmc
                    ON cmc.coursemoduleid = cm.id
                   AND cmc.userid = :userid
                 WHERE cm.course = :courseid
                   AND m.name = :modname
                   $selectedwhere
              ORDER BY a.name ASC, cm.id ASC";
$activities = $DB->get_records_sql($activitysql, $params);

$table = new html_table();
$table->attributes['class'] = 'generaltable table table-striped';
$table->head = ['Actividad', 'Estado', 'Última actualización'];
$table->data = [];

foreach ($activities as $activity) {
    $state = ((int) $activity->completionstate > 0) ? 'Completada' : 'Pendiente';
    $timemodified = (!empty($activity->timemodified)) ? userdate((int) $activity->timemodified) : '-';

    $table->data[] = [
        format_string($activity->activityname),
        $state,
        $timemodified,
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
echo html_writer::div('Usuario: ' . fullname($targetuser), 'mb-3');

if (empty($table->data)) {
    echo $OUTPUT->notification(get_string('norecordsfound', 'block_configurable_reports'));
}

echo html_writer::start_div('table-responsive');
echo html_writer::table($table);
echo html_writer::end_div();
echo $OUTPUT->footer();

/**
 * Parses selected course module IDs from CSV text.
 *
 * @param string $selectedcmidsraw
 * @return array<int>
 */
function userstatsadvanced_show_activities_parse_selected_cmids(string $selectedcmidsraw): array {
    $selectedcmidsraw = trim($selectedcmidsraw);
    if ($selectedcmidsraw === '') {
        return [];
    }

    $parts = preg_split('/[\s,;]+/', $selectedcmidsraw, -1, PREG_SPLIT_NO_EMPTY);
    if (empty($parts)) {
        return [];
    }

    $ids = [];
    foreach ($parts as $part) {
        $id = (int)$part;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }

    return array_values($ids);
}
