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
 * Evaluations detail view for userstatsadvanced.
 *
 * @package    block_configurable_reports
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../../../../config.php");

$courseid = required_param('courseid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$selectedcmidsraw = optional_param('filter_userstatsadvanced_selectedcmids', '', PARAM_RAW_TRIMMED);
$selectedcmids = userstatsadvanced_show_evaluations_parse_selected_cmids($selectedcmidsraw);

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

$url = new moodle_url('/blocks/configurable_reports/components/columns/userstatsadvanced/show_evaluations.php', [
    'courseid' => $courseid,
    'userid' => $userid,
]);
if ($selectedcmidsraw !== '') {
    $url->param('filter_userstatsadvanced_selectedcmids', implode(',', $selectedcmids));
}

$title = 'Detalle Evaluaciones';

$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_url($url);
$PAGE->set_title($title);
$PAGE->set_heading(format_string($course->fullname));

$evaluations = userstatsadvanced_show_evaluations_get_records($userid, $courseid, $selectedcmids);

$total = count($evaluations);
$completed = 0;
foreach ($evaluations as $evaluation) {
    if (!empty($evaluation->completed)) {
        $completed++;
    }
}
$percentage = ($total > 0) ? (($completed * 100) / $total) : 0.0;
$percentagetext = (abs($percentage - round($percentage)) < 0.00001)
    ? ((int) round($percentage)) . '.00%'
    : format_float($percentage, 2) . '%';
$summarytext = $percentagetext . ' (' . $completed . '/' . $total . ')';

$table = new html_table();
$table->attributes['class'] = 'generaltable table table-striped';
$table->head = ['Nombre y apellidos', strtoupper(fullname($targetuser))];
$table->data = [];

$table->data[] = ['Evaluaciones', $summarytext];

foreach ($evaluations as $evaluation) {
    $label = strtoupper(trim((string) $evaluation->name));
    $datelabel = 'FECHA REALIZACIÓN ' . $label;
    $gradelabel = 'NOTA ' . $label;

    $datetext = (!empty($evaluation->completiontime))
        ? userdate((int) $evaluation->completiontime, '%d/%m/%Y')
        : '-';

    $gradetext = userstatsadvanced_show_evaluations_format_grade(
        $evaluation->finalgrade,
        $evaluation->grademax
    );

    $table->data[] = [$datelabel, $datetext];
    $table->data[] = [$gradelabel, $gradetext];
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);

if (empty($evaluations)) {
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
function userstatsadvanced_show_evaluations_parse_selected_cmids(string $selectedcmidsraw): array {
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
        $id = (int) $part;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }

    return array_values($ids);
}

/**
 * Returns evaluations (quiz/feedback) records ordered by their position in the course.
 *
 * @param int $userid
 * @param int $courseid
 * @param array<int> $selectedcmids
 * @return array
 */
function userstatsadvanced_show_evaluations_get_records(int $userid, int $courseid, array $selectedcmids = []): array {
    global $DB;

    $params = [
        'courseid' => $courseid,
        'userid' => $userid,
        'gradeuserid' => $userid,
    ];

    $selectedwhere = '';
    if (!empty($selectedcmids)) {
        [$insql, $inparams] = $DB->get_in_or_equal($selectedcmids, SQL_PARAMS_NAMED, 'selectedcmid');
        $selectedwhere = " AND cm.id $insql";
        $params = array_merge($params, $inparams);
    }

    $sql = "SELECT cm.id AS coursemoduleid,
                   cm.instance,
                   cm.section,
                   cm.added,
                   m.name AS modname,
                   COALESCE(q.name, f.name) AS name,
                   cmc.completionstate,
                   cmc.timemodified AS completiontime,
                   gi.id AS gradeitemid,
                   gi.grademax,
                   gg.finalgrade,
                   gg.timemodified AS gradetime
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module
         LEFT JOIN {quiz} q ON q.id = cm.instance AND m.name = 'quiz'
         LEFT JOIN {feedback} f ON f.id = cm.instance AND m.name = 'feedback'
         LEFT JOIN {course_modules_completion} cmc
                ON cmc.coursemoduleid = cm.id
               AND cmc.userid = :userid
         LEFT JOIN {grade_items} gi
                ON gi.iteminstance = cm.instance
               AND gi.itemmodule = m.name
               AND gi.courseid = cm.course
               AND gi.itemtype = 'mod'
         LEFT JOIN {grade_grades} gg
                ON gg.itemid = gi.id
               AND gg.userid = :gradeuserid
             WHERE cm.course = :courseid
               AND cm.visible = 1
               AND m.name IN ('quiz', 'feedback')
               $selectedwhere
          ORDER BY cm.section ASC, cm.added ASC, cm.id ASC";

    $records = $DB->get_records_sql($sql, $params);

    $result = [];
    foreach ($records as $record) {
        $record->completed = (!empty($record->completionstate) && (int) $record->completionstate > 0);
        if (empty($record->completiontime) && !empty($record->gradetime)) {
            $record->completiontime = (int) $record->gradetime;
        }
        if (empty($record->name)) {
            $record->name = ($record->modname === 'quiz') ? 'Cuestionario' : 'Encuesta';
        }
        $result[] = $record;
    }

    return $result;
}

/**
 * Formats a grade as a percentage string.
 *
 * @param mixed $finalgrade
 * @param mixed $grademax
 * @return string
 */
function userstatsadvanced_show_evaluations_format_grade($finalgrade, $grademax): string {
    if ($finalgrade === null || $finalgrade === '' || $finalgrade === false) {
        return '-';
    }

    $grademaxvalue = (float) $grademax;
    $finalgradevalue = (float) $finalgrade;
    if ($grademaxvalue <= 0) {
        return format_float($finalgradevalue, 2);
    }

    $percentage = ($finalgradevalue * 100) / $grademaxvalue;
    $percentage = max(0.0, min(100.0, $percentage));

    if (abs($percentage - round($percentage)) < 0.00001) {
        return ((int) round($percentage)) . '.00%';
    }

    return format_float($percentage, 2) . '%';
}
