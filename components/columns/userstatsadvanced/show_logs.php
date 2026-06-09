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
 * Daily logs detail view for userstatsadvanced.
 *
 * @package    block_configurable_reports
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../../../../config.php");
require_once($CFG->dirroot . '/blocks/configurable_reports/components/columns/userstatsadvanced/plugin.class.php');

$courseid = required_param('courseid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$sessionlimit = optional_param('sessionlimit', 4 * 60 * 60, PARAM_INT);
$sessionlimit = ($sessionlimit > 0) ? $sessionlimit : (4 * 60 * 60);
$starttime = optional_param('starttime', 0, PARAM_INT);
$endtime = optional_param('endtime', 0, PARAM_INT);
$reportid = optional_param('reportid', 0, PARAM_INT);
$showdetails = optional_param('details', 0, PARAM_BOOL);
$legacydaily = optional_param('daily', 0, PARAM_BOOL);
$showdetails = !empty($showdetails) || !empty($legacydaily);
$download = optional_param('download', 0, PARAM_BOOL);
$accessscope = optional_param('filter_accessscope', 'courseplatform', PARAM_ALPHA);
$accessscope = userstatsadvanced_show_logs_normalize_access_scope($accessscope);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$targetuser = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
[$starttime, $endtime] = userstatsadvanced_show_logs_resolve_effective_time_range($courseid, $userid, $starttime, $endtime);
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

$url = new moodle_url('/blocks/configurable_reports/components/columns/userstatsadvanced/show_logs.php', [
    'courseid' => $courseid,
    'userid' => $userid,
    'sessionlimit' => $sessionlimit,
    'starttime' => $starttime,
    'endtime' => $endtime,
]);
if ($reportid > 0) {
    $url->param('reportid', $reportid);
}
if ($showdetails) {
    $url->param('details', 1);
}
$url->param('filter_accessscope', $accessscope);
$title = $showdetails ? 'Días distintos de conexión' : 'Registros diarios';

$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_url($url);
$PAGE->set_title($title);
$PAGE->set_heading(format_string($course->fullname));


$formatduration = static function(int $totalseconds): string {
    $totalseconds = max(0, $totalseconds);
    $hours = (int) floor($totalseconds / 3600);
    $minutes = (int) floor(($totalseconds % 3600) / 60);
    $seconds = (int) ($totalseconds % 60);
    return sprintf('%02dh %02dm %02ds', $hours, $minutes, $seconds);
};
$userstatsadvancedplugin = new plugin_userstatsadvanced((object)['id' => 0]);

if ($showdetails) {
    $detaillogs = userstatsadvanced_show_logs_get_detail_logs($userid, $courseid, $starttime, $endtime, $accessscope);
    $detailrows = userstatsadvanced_show_logs_build_detail_rows(
        $detaillogs,
        $targetuser,
        $course,
        $sessionlimit
    );

    if ($download) {
        userstatsadvanced_show_logs_download_detail_rows($detailrows, $targetuser, $accessscope);
    }

    $table = new html_table();
    $table->attributes['class'] = 'generaltable table table-striped';
    $table->head = ['', 'Hora', 'Acción', 'Información', 'Tiempo entre registros<br>(s)'];
    $table->data = [];

    foreach ($detailrows as $row) {
        $table->data[] = [
            (string)$row['number'],
            s($row['date']) . html_writer::empty_tag('br') . s($row['time']),
            s($row['action']),
            s($row['information']),
            ($row['seconds'] !== '') ? s($row['seconds']) : '',
        ];
    }

    $downloadurl = new moodle_url($url);
    $downloadurl->param('download', 1);

    echo $OUTPUT->header();
    echo $OUTPUT->heading($title);
    echo html_writer::div(
        html_writer::link($downloadurl, 'Descargar', ['class' => 'btn btn-secondary btn-sm']),
        'text-center mb-2'
    );
    echo html_writer::div('Usuario: ' . fullname($targetuser), 'mb-3');
    echo html_writer::div(
        'Alcance: ' . (($accessscope === 'courseplatform') ? 'Curso + plataforma' : 'Solo curso'),
        'mb-2'
    );

    if (empty($table->data)) {
        echo $OUTPUT->notification(get_string('norecordsfound', 'block_configurable_reports'));
    }

    echo html_writer::start_div('table-responsive');
    echo html_writer::table($table);
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}
$totalsbyday = $userstatsadvancedplugin->get_time_tracking_daily_connection_totals(
    $userid,
    $courseid,
    $starttime,
    $endtime,
    $sessionlimit,
    $accessscope
);
$totalaccumulated = 0;
foreach ($totalsbyday as $totalseconds) {
    $totalaccumulated += max(0, (int)$totalseconds);
}

$table = new html_table();
$table->attributes['class'] = 'generaltable table table-striped';
$table->head = ['Fecha', 'Información', 'Tiempo diario'];
$table->data = [];

foreach ($totalsbyday as $daybucket => $totalseconds) {
    $daystart = ((int) $daybucket) * 86400;

    $table->data[] = [
        userdate($daystart, '%d/%m/%Y'),
        'Totales diarios:',
        $formatduration($totalseconds),
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
echo html_writer::div('Usuario: ' . fullname($targetuser), 'mb-3');
echo html_writer::div(
    'Alcance: ' . (($accessscope === 'courseplatform') ? 'Curso + plataforma' : 'Solo curso'),
    'mb-2'
);
echo html_writer::div('Tiempo acumulado (alineado): ' . $formatduration($totalaccumulated), 'mb-2');
$detailsurl = new moodle_url($url);
$detailsurl->param('details', 1);
echo html_writer::div(
    html_writer::link($detailsurl, 'Días distintos de conexión', [
        'target' => '_blank',
        'rel' => 'noopener noreferrer',
    ]),
    'text-center mb-3'
);

if (empty($table->data)) {
    echo $OUTPUT->notification(get_string('norecordsfound', 'block_configurable_reports'));
}

echo html_writer::start_div('table-responsive');
echo html_writer::table($table);
echo html_writer::end_div();
echo $OUTPUT->footer();

/**
 * Normalizes access scope parameter.
 *
 * @param string $scope
 * @return string
 */
function userstatsadvanced_show_logs_normalize_access_scope(string $scope): string {
    return ($scope === 'courseonly') ? 'courseonly' : 'courseplatform';
}

/**
 * Resolves effective time range from request selectors or course academic period.
 *
 * @param int $courseid
 * @param int $userid
 * @param int $starttime
 * @param int $endtime
 * @return array{0:int,1:int}
 */
function userstatsadvanced_show_logs_resolve_effective_time_range(
    int $courseid,
    int $userid,
    int $starttime = 0,
    int $endtime = 0
): array {
    global $DB;

    $filterstarttime = optional_param_array('filter_starttime', [], PARAM_RAW);
    $filterendtime = optional_param_array('filter_endtime', [], PARAM_RAW);

    if (!empty($filterstarttime) && isset($filterstarttime['year'], $filterstarttime['month'], $filterstarttime['day'])) {
        $starttime = make_timestamp(
            (int)$filterstarttime['year'],
            (int)$filterstarttime['month'],
            (int)$filterstarttime['day'],
            isset($filterstarttime['hour']) ? (int)$filterstarttime['hour'] : 0,
            isset($filterstarttime['minute']) ? (int)$filterstarttime['minute'] : 0
        );
    }

    if (!empty($filterendtime) && isset($filterendtime['year'], $filterendtime['month'], $filterendtime['day'])) {
        $endtime = make_timestamp(
            (int)$filterendtime['year'],
            (int)$filterendtime['month'],
            (int)$filterendtime['day'],
            isset($filterendtime['hour']) ? (int)$filterendtime['hour'] : 0,
            isset($filterendtime['minute']) ? (int)$filterendtime['minute'] : 0
        );
    }

    if ($starttime <= 0 || $endtime <= 0 || $endtime < $starttime) {
        $course = $DB->get_record('course', ['id' => $courseid], 'id,startdate,enddate', IGNORE_MISSING);
        if ($course) {
            if ($starttime <= 0 && !empty($course->startdate)) {
                $starttime = (int)$course->startdate;
            }
            if ($endtime <= 0 && !empty($course->enddate)) {
                $endtime = (int)$course->enddate;
            }
        }
    }

    if ($starttime < 0) {
        $starttime = 0;
    }
    if ($endtime <= 0) {
        $endtime = 2145938400;
    }
    [$enrolstarttime, $enrolendtime] = userstatsadvanced_show_logs_get_user_enrolment_time_range($userid, $courseid);
    if ($enrolstarttime > 0) {
        $starttime = ($starttime > 0) ? max($starttime, $enrolstarttime) : $enrolstarttime;
    }
    if ($enrolendtime > 0) {
        $endtime = ($endtime > 0) ? min($endtime, $enrolendtime) : $enrolendtime;
    }
    if ($endtime < $starttime) {
        $endtime = $starttime;
    }

    return [$starttime, $endtime];
}

/**
 * Returns active enrolment start/end range for the user in a course.
 *
 * @param int $userid
 * @param int $courseid
 * @return array{0:int,1:int}
 */
function userstatsadvanced_show_logs_get_user_enrolment_time_range(int $userid, int $courseid): array {
    global $DB;

    if ($userid <= 0 || $courseid <= 0) {
        return [0, 0];
    }

    $sql = "SELECT MIN(CASE WHEN ue.timestart > 0 THEN ue.timestart ELSE NULL END) AS mintimestart,
                   MAX(CASE WHEN ue.timeend > 0 THEN ue.timeend ELSE NULL END) AS maxtimeend
              FROM {user_enrolments} ue
              JOIN {enrol} e
                ON e.id = ue.enrolid
             WHERE ue.userid = :userid
               AND e.courseid = :courseid
               AND ue.status = 0
               AND e.status = 0";
    $record = $DB->get_record_sql($sql, [
        'userid' => $userid,
        'courseid' => $courseid,
    ]);
    if (!$record) {
        return [0, 0];
    }

    $mintimestart = !empty($record->mintimestart) ? (int)$record->mintimestart : 0;
    $maxtimeend = !empty($record->maxtimeend) ? (int)$record->maxtimeend : 0;

    return [$mintimestart, $maxtimeend];
}

/**
 * Returns SQL condition for selected scope in log queries.
 *
 * @param array $params
 * @param int $courseid
 * @param string $scope
 * @param string $field
 * @param string|null $eventnamefield
 * @return string
 */
function userstatsadvanced_show_logs_get_scope_where_sql(
    array &$params,
    int $courseid,
    string $scope,
    string $field,
    ?string $eventnamefield = null
): string {
    $params['courseid'] = $courseid;
    if ($scope === 'courseplatform') {
        $params['platformcourseid'] = 0;
        if (!empty($eventnamefield)) {
            $params['platformeventloggedin'] = '\\core\\event\\user_loggedin';
            $params['platformeventloggedout'] = '\\core\\event\\user_loggedout';
            return "(
                {$field} = :courseid
                OR
                (
                    {$field} = :platformcourseid
                    AND (
                        {$eventnamefield} = :platformeventloggedin
                        OR {$eventnamefield} = :platformeventloggedout
                    )
                )
            )";
        }
        return "({$field} = :courseid OR {$field} = :platformcourseid)";
    }

    return "{$field} = :courseid";
}

/**
 * Returns detail logs for the selected user and course.
 *
 * @param int $userid
 * @param int $courseid
 * @param int $starttime
 * @param int $endtime
 * @param string $scope
 * @return array
 */
function userstatsadvanced_show_logs_get_detail_logs(
    int $userid,
    int $courseid,
    int $starttime = 0,
    int $endtime = 0,
    string $scope = 'courseplatform'
): array {
    global $DB, $CFG;
    $scope = userstatsadvanced_show_logs_normalize_access_scope($scope);

    if (!function_exists('cr_logging_info')) {
        require_once($CFG->dirroot . '/blocks/configurable_reports/locallib.php');
    }

    [$uselegacyreader, $useinternalreader, $logtable] = cr_logging_info();
    $params = [
        'userid' => $userid,
    ];

    if ($uselegacyreader) {
        $where = 'l.userid = :userid AND ' .
            userstatsadvanced_show_logs_get_scope_where_sql($params, $courseid, $scope, 'l.course');
        if ($starttime > 0) {
            $where .= ' AND l.time >= :starttime';
            $params['starttime'] = $starttime;
        }
        if ($endtime > 0) {
            $where .= ' AND l.time <= :endtime';
            $params['endtime'] = $endtime;
        }

        $sql = "SELECT l.id,
                       l.time,
                       l.module,
                       l.action,
                       l.info,
                       l.url
                  FROM {log} l
                 WHERE {$where}
              ORDER BY l.time ASC, l.id ASC";
        $logs = $DB->get_records_sql($sql, $params);

        return array_values($logs);
    }

    if ($useinternalreader && $logtable !== '') {
        $where = 'l.userid = :userid AND ' .
            userstatsadvanced_show_logs_get_scope_where_sql($params, $courseid, $scope, 'l.courseid', 'l.eventname');
        if ($starttime > 0) {
            $where .= ' AND l.timecreated >= :starttime';
            $params['starttime'] = $starttime;
        }
        if ($endtime > 0) {
            $where .= ' AND l.timecreated <= :endtime';
            $params['endtime'] = $endtime;
        }

        $sql = "SELECT l.id,
                       l.timecreated AS time,
                       l.eventname,
                       l.component,
                       l.action,
                       l.target,
                       l.objecttable,
                       l.objectid,
                       l.contextlevel,
                       l.contextinstanceid,
                       l.other
                  FROM {" . $logtable . "} l
                 WHERE {$where}
              ORDER BY l.timecreated ASC, l.id ASC";
        $logs = $DB->get_records_sql($sql, $params);

        return array_values($logs);
    }

    return [];
}

/**
 * Builds table rows for detailed connection logs.
 *
 * @param array $logs
 * @param object $targetuser
 * @param object $course
 * @param int $sessionlimit
 * @return array
 */
function userstatsadvanced_show_logs_build_detail_rows(
    array $logs,
    object $targetuser,
    object $course,
    int $sessionlimit
): array {
    $rows = [];
    $previousdaybucket = null;
    $previouslogtime = null;
    $sessionlimit = ($sessionlimit > 0) ? $sessionlimit : (4 * 60 * 60);
    $number = 1;

    foreach ($logs as $log) {
        $logtime = (int)$log->time;
        $daybucket = (int)floor($logtime / 86400);
        $seconds = '';

        if ($previousdaybucket !== null && $daybucket === $previousdaybucket && $previouslogtime !== null) {
            $delta = $logtime - $previouslogtime;
            if ($delta > 0 && $delta <= $sessionlimit && !userstatsadvanced_show_logs_is_login_event($log)) {
                $seconds = (string)$delta;
            }
        }

        [$action, $information] = userstatsadvanced_show_logs_describe_log($log, $targetuser, $course);

        $rows[] = [
            'number' => $number++,
            'date' => userdate($logtime, '%d/%m/%Y'),
            'time' => userdate($logtime, '%H:%M:%S'),
            'action' => $action,
            'information' => $information,
            'seconds' => $seconds,
        ];

        $previousdaybucket = $daybucket;
        $previouslogtime = $logtime;
    }

    return $rows;
}

/**
 * Returns true when the current log row represents a user login event.
 *
 * @param object $log
 * @return bool
 */
function userstatsadvanced_show_logs_is_login_event(object $log): bool {
    $eventname = trim((string)($log->eventname ?? ''));
    if ($eventname !== '') {
        return ($eventname === '\\core\\event\\user_loggedin');
    }

    $module = trim((string)($log->module ?? ''));
    $action = trim((string)($log->action ?? ''));
    return ($module === 'user' && $action === 'login');
}

/**
 * Returns human-readable action and information texts for a log row.
 *
 * @param object $log
 * @param object $targetuser
 * @param object $course
 * @return array
 */
function userstatsadvanced_show_logs_describe_log(object $log, object $targetuser, object $course): array {
    if (isset($log->eventname)) {
        return userstatsadvanced_show_logs_describe_standard_log($log, $targetuser, $course);
    }

    return userstatsadvanced_show_logs_describe_legacy_log($log, $targetuser, $course);
}

/**
 * Returns human-readable texts for a standard Moodle log row.
 *
 * @param object $log
 * @param object $targetuser
 * @param object $course
 * @return array
 */
function userstatsadvanced_show_logs_describe_standard_log(object $log, object $targetuser, object $course): array {
    $eventname = (string)($log->eventname ?? '');
    $shorteventname = userstatsadvanced_show_logs_short_event_name($eventname);
    $fullname = fullname($targetuser);
    $coursename = format_string($course->fullname);

    switch ($shorteventname) {
        case 'user_loggedin':
            return [
                'El usuario ha iniciado sesión',
                "El usuario '{$fullname}' ha entrado en la plataforma.",
            ];
        case 'user_loggedout':
            return [
                'Usuario desconectado',
                "El usuario '{$fullname}' ha salido de la plataforma.",
            ];
        case 'course_viewed':
            return [
                'Curso visto',
                "El usuario '{$fullname}' vio el curso '{$coursename}'.",
            ];
    }

    $action = userstatsadvanced_show_logs_get_event_label($eventname);
    if ($action === '') {
        $action = userstatsadvanced_show_logs_build_generic_action_label($log);
    }

    return [
        $action,
        "El usuario '{$fullname}' registró '{$action}' en el curso '{$coursename}'.",
    ];
}

/**
 * Returns human-readable texts for a legacy Moodle log row.
 *
 * @param object $log
 * @param object $targetuser
 * @param object $course
 * @return array
 */
function userstatsadvanced_show_logs_describe_legacy_log(object $log, object $targetuser, object $course): array {
    $module = (string)($log->module ?? '');
    $actionraw = (string)($log->action ?? '');
    $info = (string)($log->info ?? '');
    $fullname = fullname($targetuser);
    $coursename = format_string($course->fullname);
    $actionlabel = trim($module . ' ' . $actionraw);

    if ($module === 'course' && $actionraw === 'view') {
        return [
            'Curso visto',
            "El usuario '{$fullname}' vio el curso '{$coursename}'.",
        ];
    }

    if ($actionlabel === '') {
        $actionlabel = 'Registro';
    }

    $information = "El usuario '{$fullname}' registró '{$actionlabel}' en el curso '{$coursename}'.";
    if ($info !== '') {
        $information .= ' Información: ' . $info;
    }

    return [$actionlabel, $information];
}

/**
 * Returns the last class name segment for an event name.
 *
 * @param string $eventname
 * @return string
 */
function userstatsadvanced_show_logs_short_event_name(string $eventname): string {
    $eventname = trim($eventname, '\\');
    if ($eventname === '') {
        return '';
    }

    $parts = explode('\\', $eventname);
    return (string)end($parts);
}

/**
 * Returns the localized Moodle event name when available.
 *
 * @param string $eventname
 * @return string
 */
function userstatsadvanced_show_logs_get_event_label(string $eventname): string {
    $eventname = trim($eventname);
    if ($eventname === '') {
        return '';
    }

    try {
        if (
            class_exists($eventname) &&
            is_subclass_of($eventname, '\\core\\event\\base') &&
            is_callable([$eventname, 'get_name'])
        ) {
            return (string)call_user_func([$eventname, 'get_name']);
        }
    } catch (Throwable $t) {
        return '';
    }

    return '';
}

/**
 * Builds a generic action label from standard log fields.
 *
 * @param object $log
 * @return string
 */
function userstatsadvanced_show_logs_build_generic_action_label(object $log): string {
    $target = trim(str_replace('_', ' ', (string)($log->target ?? '')));
    $action = trim(str_replace('_', ' ', (string)($log->action ?? '')));
    $label = trim($target . ' ' . $action);

    if ($label === '') {
        $label = 'Registro';
    }

    return ucfirst($label);
}

/**
 * Downloads detail rows as CSV.
 *
 * @param array $detailrows
 * @param object $targetuser
 * @param string $scope
 * @return void
 */
function userstatsadvanced_show_logs_download_detail_rows(
    array $detailrows,
    object $targetuser,
    string $scope = 'courseonly'
): void {
    $suffix = ($scope === 'courseplatform') ? '_curso_plataforma' : '';
    $filename = clean_filename('dias_distintos_conexion_' . fullname($targetuser) . $suffix . '.csv');

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "\xEF\xBB\xBF";

    $handle = fopen('php://output', 'w');
    fputcsv($handle, ['#', 'Fecha', 'Hora', 'Acción', 'Información', 'Tiempo entre registros (s)'], ';');
    foreach ($detailrows as $row) {
        fputcsv($handle, [
            $row['number'],
            $row['date'],
            $row['time'],
            $row['action'],
            $row['information'],
            $row['seconds'],
        ], ';');
    }
    fclose($handle);
    exit;
}
