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
 * Configurable Reports a Moodle block for creating customizable reports.
 *
 * @package    block_configurable_reports
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;
require_once($CFG->dirroot . '/blocks/configurable_reports/plugin.class.php');

/**
 * Class plugin_userstatsadvanced
 *
 * @package   block_configurable_reports
 */
class plugin_userstatsadvanced extends plugin_base {

    /**
     * Init.
     *
     * @return void
     */
    public function init(): void {
        $this->fullname = $this->get_localized_label('userstatsadvanced', 'user_stats_advanced');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = ['users'];
    }

    /**
     * Returns a localized label if it exists; otherwise fallback text.
     *
     * @param string $identifier
     * @param string $fallback
     * @return string
     */
    protected function get_localized_label(string $identifier, string $fallback): string {
        $stringmanager = get_string_manager();
        if ($stringmanager->string_exists($identifier, 'block_configurable_reports')) {
            return get_string($identifier, 'block_configurable_reports');
        }
        return $fallback;
    }

    /**
     * Summary shown in component list.
     *
     * @param object $data
     * @return string
     */
    public function summary(object $data): string {
        return !empty($data->columname) ? format_string($data->columname) : $this->fullname;
    }

    /**
     * Execute per row.
     *
     * @param object $data
     * @param object $row
     * @param object $user
     * @param int $courseid
     * @param int $starttime
     * @param int $endtime
     * @return int|string
     */
    public function execute($data, $row, $user, $courseid, $starttime = 0, $endtime = 0) {
        $stat_type = !empty($data->stat_type) ? (string)$data->stat_type : '';
        $sessionlimittime = !empty($data->sessionlimittime) ? (int)$data->sessionlimittime : (30 * 60);
        $selectedcmidsraw = !empty($data->selectedcmids) ? (string)$data->selectedcmids : '';
        $selectedcmidsraw = $this->resolve_selected_cmids_raw($selectedcmidsraw, $stat_type);
        $displayformat = !empty($data->displayformat) ? (string)$data->displayformat : 'numdenum_percent';
        $maxdisplayvalue = isset($data->maxdisplayvalue) ? (int)$data->maxdisplayvalue : 100;
        $modalreportid = !empty($data->modalreportid) ? (int)$data->modalreportid : 0;

        return $this->get_value(
            $row,
            (int)$courseid,
            $stat_type,
            $starttime,
            $endtime,
            $sessionlimittime,
            $selectedcmidsraw,
            $displayformat,
            $maxdisplayvalue,
            $modalreportid
        );
    }

    /**
     * Resolve value for selected stat.
     *
     * @param object $row
     * @param int $courseid
     * @param string $stat_type
     * @param int $starttime
     * @param int $endtime
     * @param int $sessionlimittime
     * @param string $selectedcmidsraw
     * @param string $displayformat
     * @param int $maxdisplayvalue
     * @param int $modalreportid
     * @return int|string
     */
    public function get_value(
        $row,
        int $courseid,
        string $stat_type,
        int $starttime = 0,
        int $endtime = 0,
        int $sessionlimittime = 1800,
        string $selectedcmidsraw = '',
        string $displayformat = 'numdenum_percent',
        int $maxdisplayvalue = 100,
        int $modalreportid = 0
    ) {
        global $DB, $CFG;
        $courseid = $this->resolve_courseid_for_course_metrics((int)$courseid, $row);

        $userid = 0;
        if (!empty($row->id)) {
            $userid = (int)$row->id;
        } else if (!empty($row->userid)) {
            $userid = (int)$row->userid;
        }

        if (!$userid || !$courseid) {
            return $this->get_metric_default_value($stat_type);
        }

        switch ($stat_type) {
            case 'actividades_aprendizaje':
                $selectedcmids = $this->parse_selected_cmids($selectedcmidsraw);
                $summary = $this->get_actividades_aprendizaje_completion_summary($userid, $courseid, $selectedcmids);
                $formattedvalue = $this->format_completion_summary($summary, $displayformat, $maxdisplayvalue);
                return $this->append_linked_modal_report_button(
                    $formattedvalue,
                    $modalreportid,
                    $courseid,
                    $userid,
                    $stat_type,
                    $selectedcmidsraw
                );

            case 'tareas_entregadas':
                return $this->get_completion_fraction_by_modname($userid, $courseid, 'assign');

            case 'evaluaciones':
                $selectedcmids = $this->parse_selected_cmids($selectedcmidsraw);
                $summary = $this->get_evaluaciones_completion_summary($userid, $courseid, $selectedcmids);
                $formattedvalue = $this->format_completion_summary($summary, $displayformat, $maxdisplayvalue);
                $showevaluationsurl = $this->build_show_evaluations_url($courseid, $userid, $selectedcmidsraw);
                $showevaluationsbutton = '<a class="btn btn-info btn-sm" href="' . s($showevaluationsurl) .
                    '" target="_blank" rel="noopener noreferrer" onclick="window.open(this.href, ' .
                    '\'userstatsadvancedevaluations\', \'width=1000,height=700,scrollbars=yes,resizable=yes\'); ' .
                    'return false;">Ver</a>';
                $valuewithbutton = $formattedvalue . '<br>' . $showevaluationsbutton;
                return $this->append_linked_modal_report_button(
                    $valuewithbutton,
                    $modalreportid,
                    $courseid,
                    $userid,
                    $stat_type,
                    $selectedcmidsraw
                );
            case 'intentos_cuestionario':
                return $this->get_completion_fraction_by_modname($userid, $courseid, 'quiz');

            case 'contenidos_visualizados':
                $selectedcmids = $this->parse_selected_cmids($selectedcmidsraw);
                return $this->get_logged_content_progress($userid, $courseid, $selectedcmids);

            case 'finalizacion_cruzada':
                return $this->get_course_completion_progress($userid, $courseid);

            case 'correos':
            case 'mensajes_tutor':
                $totalmessages = (string)$this->count_messages_by_course_group(
                    $userid,
                    $courseid,
                    ['editingteacher', 'teacher']
                );
                return $this->append_show_messages_button($totalmessages, $courseid, $userid, $stat_type);

            case 'mensajes_alumnos':
                $totalmessages = (string)$this->count_messages_by_course_group($userid, $courseid, ['student']);
                return $this->append_show_messages_button($totalmessages, $courseid, $userid, $stat_type);

            case 'registros':
            case 'logs_integracion':
                $total = (string)$this->get_time_tracking_logs_count($userid, $courseid, $starttime, $endtime);
                $url = $this->build_show_logs_url($courseid, $userid, $sessionlimittime, $starttime, $endtime);
                $button = '<a class="btn btn-info btn-sm" href="' . s($url) .
                    '" target="_blank" rel="noopener noreferrer" onclick="window.open(this.href, \'userstatsadvancedlogs\', ' .
                    '\'width=1000,height=700,scrollbars=yes,resizable=yes\'); return false;">Ver días</a>';
                return $total . '<br>' . $button;

            case 'dias_conexion':
                $totaldays = (string)$this->get_time_tracking_days_count($userid, $courseid, $starttime, $endtime);
                $url = $this->build_show_logs_url($courseid, $userid, $sessionlimittime, $starttime, $endtime);
                $button = '<a class="btn btn-info btn-sm" href="' . s($url) .
                    '" target="_blank" rel="noopener noreferrer" onclick="window.open(this.href, \'userstatsadvancedlogs\', ' .
                    '\'width=1000,height=700,scrollbars=yes,resizable=yes\'); return false;">Ver días</a>';
                return $totaldays . '<br>' . $button;

            case 'interacciones_foros':
            case 'mensajes_foro':
            case 'foros_publicados':
                $sql = "SELECT COUNT(1)
                          FROM {forum_posts} fp
                          JOIN {forum_discussions} fd ON fd.id = fp.discussion
                          JOIN {forum} f ON f.id = fd.forum
                         WHERE fp.userid = :userid
                           AND f.course = :courseid";
                $totalposts = $DB->get_field_sql($sql, ['userid' => $userid, 'courseid' => $courseid]);
                $totalposts = (string)(($totalposts !== false && $totalposts !== null) ? (int)$totalposts : 0);
                return $this->append_show_messages_button($totalposts, $courseid, $userid, $stat_type);

            case 'ips_utilizadas':
                $sql = "SELECT COUNT(DISTINCT l.ip)
                          FROM {logstore_standard_log} l
                         WHERE l.userid = :userid
                           AND l.courseid = :courseid
                           AND l.ip IS NOT NULL
                           AND l.ip <> ''";
                $totalips = $DB->get_field_sql($sql, ['userid' => $userid, 'courseid' => $courseid]);
                return (string)(($totalips !== false && $totalips !== null) ? (int)$totalips : 0);

            case 'ultima_ip':
                $sql = "SELECT l.id, l.ip
                          FROM {logstore_standard_log} l
                         WHERE l.userid = :userid
                           AND l.courseid = :courseid
                           AND l.ip IS NOT NULL
                           AND l.ip <> ''
                      ORDER BY l.timecreated DESC, l.id DESC";
                $record = $DB->get_record_sql($sql, ['userid' => $userid, 'courseid' => $courseid], IGNORE_MULTIPLE);
                return (!empty($record->ip)) ? (string)$record->ip : '-';

            case 'nota_final':
                $sql = "SELECT gg.finalgrade
                          FROM {grade_items} gi
                     LEFT JOIN {grade_grades} gg
                            ON gg.itemid = gi.id
                           AND gg.userid = :userid
                         WHERE gi.courseid = :courseid
                           AND gi.itemtype = 'course'
                      ORDER BY gi.id ASC";
                $finalgrade = $DB->get_field_sql($sql, ['userid' => $userid, 'courseid' => $courseid], IGNORE_MISSING);
                if ($finalgrade === false || $finalgrade === null || $finalgrade === '') {
                    return '0.00';
                }
                return format_float((float)$finalgrade, 2);

            case 'primer_acceso':
                $sql = "SELECT MIN(l.timecreated)
                          FROM {logstore_standard_log} l
                         WHERE l.userid = :userid
                           AND l.courseid = :courseid";
                $firstaccess = $DB->get_field_sql($sql, ['userid' => $userid, 'courseid' => $courseid]);
                return ($firstaccess !== false && $firstaccess !== null)
                    ? $this->format_access_datetime((int)$firstaccess)
                    : 'No visitado';

            case 'ultimo_acceso':
                $sql = "SELECT MAX(l.timecreated)
                          FROM {logstore_standard_log} l
                         WHERE l.userid = :userid
                           AND l.courseid = :courseid";
                $lastaccess = $DB->get_field_sql($sql, ['userid' => $userid, 'courseid' => $courseid]);
                return ($lastaccess !== false && $lastaccess !== null)
                    ? $this->format_access_datetime((int)$lastaccess)
                    : 'No visitado';

            case 'primer_acceso_scorm':
                $sql = "SELECT MIN(l.timecreated)
                          FROM {logstore_standard_log} l
                         WHERE l.userid = :userid
                           AND l.courseid = :courseid
                           AND l.component = :component";
                $firstscormaccess = $DB->get_field_sql($sql, [
                    'userid' => $userid,
                    'courseid' => $courseid,
                    'component' => 'mod_scorm',
                ]);
                return ($firstscormaccess !== false && $firstscormaccess !== null) ? userdate((int)$firstscormaccess) : '-';

            case 'matricula_activa':
                $now = time();
                $sql = "SELECT COUNT(1)
                          FROM {user_enrolments} ue
                          JOIN {enrol} e ON e.id = ue.enrolid
                         WHERE ue.userid = :userid
                           AND e.courseid = :courseid
                           AND ue.status = 0
                           AND e.status = 0
                           AND (ue.timestart = 0 OR ue.timestart <= :nowstart)
                           AND (ue.timeend = 0 OR ue.timeend >= :nowend)";
                $activecount = $DB->get_field_sql($sql, [
                    'userid' => $userid,
                    'courseid' => $courseid,
                    'nowstart' => $now,
                    'nowend' => $now,
                ]);
                return ((int)$activecount > 0) ? 'Sí' : 'No';

            case 'scorm_completados':
                $sql = "SELECT COUNT(1) AS total
                          FROM {scorm_scoes_track} st
                          JOIN {scorm} s ON s.id = st.scormid
                         WHERE st.userid = :userid
                           AND s.course = :courseid
                           AND st.value IN ('completed', 'passed')";
                $record = $DB->get_record_sql($sql, ['userid' => $userid, 'courseid' => $courseid]);
                return !empty($record->total) ? (int)$record->total : 0;

            case 'tiempo_total':
                $totalseconds = $this->get_total_connection_seconds(
                    $userid,
                    $courseid,
                    $starttime,
                    $endtime,
                    $sessionlimittime
                );
                return $this->format_duration_hms((int)$totalseconds, true);

            case 'tiempos_conexion_diarios_html':
                return $this->render_daily_connection_times_table_html(
                    $userid,
                    $courseid,
                    $starttime,
                    $endtime,
                    $sessionlimittime
                );

            default:
                return 'En desarrollo...';
        }
    }

    /**
     * Returns module completion summary using Moodle completion criterion.
     *
     * @param int $userid
     * @param int $courseid
     * @param string $modname
     * @param array<int> $selectedcmids
     * @return array{completed:int,total:int,percentage:float}
     */
    protected function get_module_completion_summary(
        int $userid,
        int $courseid,
        string $modname,
        array $selectedcmids = []
    ): array {
        return $this->get_modules_completion_summary($userid, $courseid, [$modname], $selectedcmids, false);
    }

    /**
     * Returns completion summary using Moodle completion criterion for multiple module types.
     *
     * @param int $userid
     * @param int $courseid
     * @param array<int,string> $modnames
     * @param array<int> $selectedcmids
     * @param bool $countselectedwithoutcompletion
     * @param bool $countwithoutcompletionwithoutselection
     * @return array{completed:int,total:int,percentage:float}
     */
    protected function get_modules_completion_summary(
        int $userid,
        int $courseid,
        array $modnames,
        array $selectedcmids = [],
        bool $countselectedwithoutcompletion = false,
        bool $countwithoutcompletionwithoutselection = false
    ): array {
        global $DB;
        $modnames = array_values(array_unique(array_filter($modnames, static function($modnamevalue) {
            return is_string($modnamevalue) && trim($modnamevalue) !== '';
        })));
        if (empty($modnames)) {
            return [
                'completed' => 0,
                'total' => 0,
                'percentage' => 0.0,
            ];
        }

        [$modinsql, $modinparams] = $DB->get_in_or_equal($modnames, SQL_PARAMS_NAMED, 'modname');

        $params = [
            'courseid' => $courseid,
            'userid' => $userid,
        ];
        $params = array_merge($params, $modinparams);
        $selectedwhere = '';
        if (!empty($selectedcmids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($selectedcmids, SQL_PARAMS_NAMED, 'selectedcmid');
            $selectedwhere = " AND cm.id $insql";
            $params = array_merge($params, $inparams);
        }
        $totalcompletionwhere = ' AND cm.completion > 0';
        if (
            $countselectedwithoutcompletion &&
            (!empty($selectedcmids) || $countwithoutcompletionwithoutselection)
        ) {
            $totalcompletionwhere = '';
        }

        $totalsql = "SELECT COUNT(DISTINCT cm.id)
                       FROM {course_modules} cm
                       JOIN {modules} m ON m.id = cm.module
                      WHERE cm.course = :courseid
                        AND cm.visible = 1
                        $totalcompletionwhere
                        AND m.name $modinsql" . $selectedwhere;
        $total = $DB->get_field_sql($totalsql, $params);
        $total = ($total !== false && $total !== null) ? (int)$total : 0;
        if ($total <= 0) {
            return [
                'completed' => 0,
                'total' => 0,
                'percentage' => 0.0,
            ];
        }

        $completedsql = "SELECT COUNT(DISTINCT cm.id)
                           FROM {course_modules} cm
                           JOIN {modules} m ON m.id = cm.module
                           JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id
                          WHERE cm.course = :courseid
                            AND cm.visible = 1
                            AND cm.completion > 0
                            AND m.name $modinsql
                            AND cmc.userid = :userid
                            AND cmc.completionstate > 0" . $selectedwhere;
        $completed = $DB->get_field_sql($completedsql, $params);
        $completed = ($completed !== false && $completed !== null) ? (int)$completed : 0;
        if ($completed > $total) {
            $completed = $total;
        }

        $percentage = ($total > 0) ? (($completed * 100) / $total) : 0.0;
        return [
            'completed' => $completed,
            'total' => $total,
            'percentage' => (float)$percentage,
        ];
    }

    /**
     * Returns evaluaciones completion summary using Moodle completion criterion.
     * Evaluaciones include quiz and feedback activities.
     *
     * @param int $userid
     * @param int $courseid
     * @param array<int> $selectedcmids
     * @return array{completed:int,total:int,percentage:float}
     */
    protected function get_evaluaciones_completion_summary(int $userid, int $courseid, array $selectedcmids = []): array {
        return $this->get_modules_completion_summary(
            $userid,
            $courseid,
            ['quiz', 'feedback'],
            $selectedcmids,
            true,
            false
        );
    }

    /**
     * Returns actividades_aprendizaje completion summary using Moodle completion criterion.
     *
     * @param int $userid
     * @param int $courseid
     * @param array<int> $selectedcmids
     * @return array{completed:int,total:int,percentage:float}
     */
    protected function get_actividades_aprendizaje_completion_summary(
        int $userid,
        int $courseid,
        array $selectedcmids = []
    ): array {
        return $this->get_modules_completion_summary(
            $userid,
            $courseid,
            ['assign'],
            $selectedcmids,
            true,
            true
        );
    }

    /**
     * Returns quiz completion summary using Moodle completion criterion.
     *
     * @param int $userid
     * @param int $courseid
     * @param array<int> $selectedcmids
     * @return array{completed:int,total:int,percentage:float}
     */
    protected function get_quiz_completion_summary(int $userid, int $courseid, array $selectedcmids = []): array {
        return $this->get_module_completion_summary($userid, $courseid, 'quiz', $selectedcmids);
    }

    /**
     * Formats completion summary according to display format and max percentage value.
     *
     * @param array{completed:int,total:int,percentage:float} $summary
     * @param string $displayformat
     * @param int $maxdisplayvalue
     * @return string
     */
    protected function format_completion_summary(array $summary, string $displayformat, int $maxdisplayvalue): string {
        $completed = (int)($summary['completed'] ?? 0);
        $total = (int)($summary['total'] ?? 0);
        $percentage = (float)($summary['percentage'] ?? 0.0);
        $displayedpercentage = $this->apply_max_display_value_to_percentage($percentage, $maxdisplayvalue);

        switch ($displayformat) {
            case 'percent':
                return $this->format_percentage_value($displayedpercentage);
            case 'progressbar':
                return $this->render_percentage_progress_bar($displayedpercentage, $completed, $total);
            case 'numdenum_percent':
            default:
                return $completed . ' / ' . $total . ' (' . $this->format_percentage_value($displayedpercentage) . ')';
        }
    }

    /**
     * Backward-compatible wrapper for quiz completion formatter.
     *
     * @param array{completed:int,total:int,percentage:float} $summary
     * @param string $displayformat
     * @param int $maxdisplayvalue
     * @return string
     */
    protected function format_quiz_completion_summary(array $summary, string $displayformat, int $maxdisplayvalue): string {
        return $this->format_completion_summary($summary, $displayformat, $maxdisplayvalue);
    }

    /**
     * Resolves selected course module IDs, allowing linked reports to override them via request params.
     *
     * @param string $selectedcmidsraw
     * @param string $stattype
     * @return string
     */
    protected function resolve_selected_cmids_raw(string $selectedcmidsraw, string $stattype): string {
        $requestselectedcmidsraw = optional_param('filter_userstatsadvanced_selectedcmids', '', PARAM_RAW_TRIMMED);
        if ($requestselectedcmidsraw === '') {
            return $selectedcmidsraw;
        }

        $requeststattype = optional_param('filter_userstatsadvanced_stattype', '', PARAM_ALPHANUMEXT);
        if ($requeststattype !== '' && $requeststattype !== $stattype) {
            return $selectedcmidsraw;
        }

        $requestselectedcmids = $this->parse_selected_cmids($requestselectedcmidsraw);
        if (empty($requestselectedcmids)) {
            return '';
        }

        return implode(',', $requestselectedcmids);
    }

    /**
     * Applies max display value (0-100) to a percentage.
     *
     * @param float $percentage
     * @param int $maxdisplayvalue
     * @return float
     */
    protected function apply_max_display_value_to_percentage(float $percentage, int $maxdisplayvalue): float {
        $percentage = max(0.0, min(100.0, $percentage));
        $maxdisplayvalue = max(0, min(100, $maxdisplayvalue));
        return min($percentage, (float)$maxdisplayvalue);
    }

    /**
     * Formats a percentage value for display.
     *
     * @param float $percentage
     * @return string
     */
    protected function format_percentage_value(float $percentage): string {
        if (abs($percentage - round($percentage)) < 0.00001) {
            return (string)((int)round($percentage)) . '%';
        }
        return format_float($percentage, 2) . '%';
    }

    /**
     * Renders a progress bar HTML with percentage and fraction details.
     *
     * @param float $percentage
     * @param int $completed
     * @param int $total
     * @return string
     */
    protected function render_percentage_progress_bar(float $percentage, int $completed, int $total): string {
        $percentage = max(0.0, min(100.0, $percentage));
        $percenttext = $this->format_percentage_value($percentage);
        $width = number_format($percentage, 2, '.', '');
        $fractiontext = $completed . ' / ' . $total;

        $html = '<div style="min-width:150px;max-width:240px;">';
        $html .= '<div style="height:14px;background:#e9ecef;border-radius:999px;overflow:hidden;">';
        $html .= '<div style="height:14px;width:' . s($width) . '%;background:#0d6efd;"></div>';
        $html .= '</div>';
        $html .= '<div style="display:flex;justify-content:space-between;font-size:11px;margin-top:2px;">';
        $html .= '<span>' . s($fractiontext) . '</span>';
        $html .= '<span>' . s($percenttext) . '</span>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Appends a button that opens a linked report in a popup window, if configured.
     *
     * @param string $value
     * @param int $modalreportid
     * @param int $courseid
     * @param int $userid
     * @return string
     */
    protected function append_linked_modal_report_button(
        string $value,
        int $modalreportid,
        int $courseid,
        int $userid,
        string $stattype = '',
        string $selectedcmidsraw = ''
    ): string {
        if ($modalreportid <= 0 || $courseid <= 0 || $userid <= 0) {
            return $value;
        }

        $reportname = $this->get_report_name_for_modal_link($modalreportid, $courseid);
        if ($reportname === '') {
            return $value;
        }

        $urlparams = [
            'id' => $modalreportid,
            'courseid' => $courseid,
            'filter_users' => $userid,
            'embed' => 1,
        ];
        $selectedcmids = $this->parse_selected_cmids($selectedcmidsraw);
        if (!empty($selectedcmids)) {
            $urlparams['filter_userstatsadvanced_selectedcmids'] = implode(',', $selectedcmids);
        }
        if ($stattype !== '') {
            $urlparams['filter_userstatsadvanced_stattype'] = $stattype;
        }

        $url = new moodle_url('/blocks/configurable_reports/viewreport.php', $urlparams);

        $buttonlabel = $this->get_localized_label('userstatsadvanced_modalreport_open', 'Ver detalle');
        $button = '<a class="btn btn-info btn-sm" href="' . s($url->out(false)) .
            '" target="_blank" rel="noopener noreferrer" onclick="window.open(this.href, \'userstatsadvancedlinkedreport' .
            (int)$modalreportid .
            '\', \'width=1100,height=750,scrollbars=yes,resizable=yes\'); return false;">' . s($buttonlabel) . '</a>';

        if ($value === '') {
            return $button;
        }

        return $value . '<br>' . $button;
    }

    /**
     * Returns report name for modal link if report is valid for current course/global context.
     *
     * @param int $reportid
     * @param int $courseid
     * @return string
     */
    protected function get_report_name_for_modal_link(int $reportid, int $courseid): string {
        global $DB;

        if ($reportid <= 0) {
            return '';
        }

        $report = $DB->get_record('block_configurable_reports', ['id' => $reportid], 'id,name,courseid,global');
        if (empty($report)) {
            return '';
        }

        if ((int)$report->courseid !== $courseid && empty($report->global)) {
            return '';
        }

        return format_string((string)$report->name);
    }

    /**
     * Returns default value for a metric when there is no data.
     *
     * @param string $stat_type
     * @return string
     */
    protected function get_metric_default_value(string $stat_type): string {
        switch ($stat_type) {
            case 'nota_final':
                return '0.00';
            case 'ultima_ip':
            case 'primer_acceso':
            case 'ultimo_acceso':
            case 'primer_acceso_scorm':
                return '-';
            case 'matricula_activa':
                return 'No';
            case 'tiempo_total':
                return '00h 00m 00s';
            case 'contenidos_visualizados':
            case 'finalizacion_cruzada':
                return '0 / 0 (0.00%)';
            case 'actividades_aprendizaje':
            case 'evaluaciones':
            case 'tareas_entregadas':
            case 'intentos_cuestionario':
                return '0 / 0';
            case 'scorm_completados':
            case 'correos':
            case 'mensajes_tutor':
            case 'mensajes_alumnos':
            case 'tiempos_conexion_diarios_html':
            case 'registros':
            case 'logs_integracion':
            case 'dias_conexion':
            case 'interacciones_foros':
            case 'mensajes_foro':
            case 'foros_publicados':
            case 'ips_utilizadas':
                return '0';
            default:
                return 'En desarrollo...';
        }
    }

    /**
     * Builds URL for daily logs popup aligned with tiempo_total settings.
     *
     * @param int $courseid
     * @param int $userid
     * @param int $fallbacksessionlimit
     * @param int $starttime
     * @param int $endtime
     * @return string
     */
    protected function build_show_logs_url(
        int $courseid,
        int $userid,
        int $fallbacksessionlimit,
        int $starttime = 0,
        int $endtime = 0
    ): string {
        $sessionlimit = $this->resolve_show_logs_session_limit($courseid, $fallbacksessionlimit);
        $params = [
            'courseid' => $courseid,
            'userid' => $userid,
            'sessionlimit' => $sessionlimit,
        ];
        if ($starttime > 0) {
            $params['starttime'] = $starttime;
        }
        if ($endtime > 0) {
            $params['endtime'] = $endtime;
        }
        $reportid = optional_param('id', 0, PARAM_INT);
        if ($reportid > 0) {
            $params['reportid'] = $reportid;
        }

        $url = new moodle_url('/blocks/configurable_reports/components/columns/userstatsadvanced/show_logs.php', $params);
        return $url->out(false);
    }

    /**
     * Builds URL for evaluations detail popup.
     *
     * @param int $courseid
     * @param int $userid
     * @param string $selectedcmidsraw
     * @return string
     */
    protected function build_show_evaluations_url(
        int $courseid,
        int $userid,
        string $selectedcmidsraw = ''
    ): string {
        $params = [
            'courseid' => $courseid,
            'userid' => $userid,
        ];
        $selectedcmids = $this->parse_selected_cmids($selectedcmidsraw);
        if (!empty($selectedcmids)) {
            $params['filter_userstatsadvanced_selectedcmids'] = implode(',', $selectedcmids);
        }
        $reportid = optional_param('id', 0, PARAM_INT);
        if ($reportid > 0) {
            $params['reportid'] = $reportid;
        }

        $url = new moodle_url(
            '/blocks/configurable_reports/components/columns/userstatsadvanced/show_evaluations.php',
            $params
        );
        return $url->out(false);
    }

    /**
     * Builds URL for messages detail popup.
     *
     * @param int $courseid
     * @param int $userid
     * @param string $stattype
     * @return string
     */
    protected function build_show_messages_url(int $courseid, int $userid, string $stattype): string {
        $params = [
            'courseid' => $courseid,
            'userid' => $userid,
            'stat_type' => $stattype,
        ];
        $reportid = optional_param('id', 0, PARAM_INT);
        if ($reportid > 0) {
            $params['reportid'] = $reportid;
        }

        $url = new moodle_url(
            '/blocks/configurable_reports/components/columns/userstatsadvanced/show_messages.php',
            $params
        );
        return $url->out(false);
    }

    /**
     * Appends a button that opens the messages detail popup.
     *
     * @param string $value
     * @param int $courseid
     * @param int $userid
     * @param string $stattype
     * @return string
     */
    protected function append_show_messages_button(string $value, int $courseid, int $userid, string $stattype): string {
        if ($courseid <= 0 || $userid <= 0 || $stattype === '') {
            return $value;
        }

        $url = $this->build_show_messages_url($courseid, $userid, $stattype);
        $buttonlabel = $this->get_localized_label('userstatsadvanced_view_message', 'Ver mensaje');
        $windowname = preg_replace('/[^A-Za-z0-9_-]/', '', 'userstatsadvancedmessages_' . $stattype);
        if ($windowname === '') {
            $windowname = 'userstatsadvancedmessages';
        }

        $button = '<a class="btn btn-info btn-sm" href="' . s($url) .
            '" target="_blank" rel="noopener noreferrer" onclick="window.open(this.href, \'' .
            $windowname .
            '\', \'width=1200,height=760,scrollbars=yes,resizable=yes\'); return false;">' .
            s($buttonlabel) . '</a>';

        if ($value === '') {
            return $button;
        }

        return $value . '<br>' . $button;
    }

    /**
     * Resolves a session limit for the daily logs popup.
     * Priority: tiempo_total column in current report > provided fallback > 30m default.
     *
     * @param int $courseid
     * @param int $fallbacksessionlimit
     * @return int
     */
    protected function resolve_show_logs_session_limit(int $courseid, int $fallbacksessionlimit): int {
        global $DB, $CFG;

        $reportid = optional_param('id', 0, PARAM_INT);
        if ($reportid > 0) {
            $report = $DB->get_record('block_configurable_reports', ['id' => $reportid], 'id,courseid,global,components');
            if (!empty($report) && ((int)$report->courseid === $courseid || !empty($report->global))) {
                if (!function_exists('cr_unserialize')) {
                    require_once($CFG->dirroot . '/blocks/configurable_reports/locallib.php');
                }
                try {
                    $components = cr_unserialize((string)$report->components);
                    if (is_array($components)) {
                        $columnselements = $components['columns']['elements'] ?? [];
                        if (is_array($columnselements)) {
                            foreach ($columnselements as $column) {
                                $pluginname = $column['pluginname'] ?? '';
                                if (is_array($pluginname)) {
                                    $pluginname = reset($pluginname);
                                }
                                $formdata = $column['formdata'] ?? null;
                                if (is_array($formdata)) {
                                    $formdata = (object)$formdata;
                                }
                                if (!is_object($formdata)) {
                                    continue;
                                }
                                if (
                                    (string)$pluginname === 'userstatsadvanced' &&
                                    !empty($formdata->stat_type) &&
                                    (string)$formdata->stat_type === 'tiempo_total' &&
                                    !empty($formdata->sessionlimittime)
                                ) {
                                    $limit = (int)$formdata->sessionlimittime;
                                    if ($limit > 0) {
                                        return $limit;
                                    }
                                }
                            }
                        }
                    }
                } catch (Throwable $t) {
                    // Fall back below.
                }
            }
        }

        if ($fallbacksessionlimit > 0) {
            return $fallbacksessionlimit;
        }

        return 30 * 60;
    }
    /**
     * Builds a shared WHERE clause for time-tracking logs.
     *
     * @param array $params
     * @param int $userid
     * @param int $courseid
     * @param int $starttime
     * @param int $endtime
     * @return string
     */
    protected function get_time_tracking_logs_where_sql(
        array &$params,
        int $userid,
        int $courseid,
        int $starttime = 0,
        int $endtime = 0
    ): string {
        $params = [
            'userid' => $userid,
            'courseid' => $courseid,
        ];

        $where = "l.userid = :userid
                    AND l.courseid = :courseid";
        if ($starttime > 0) {
            $where .= " AND l.timecreated >= :starttime";
            $params['starttime'] = $starttime;
        }
        if ($endtime > 0) {
            $where .= " AND l.timecreated <= :endtime";
            $params['endtime'] = $endtime;
        }
        $where .= $this->get_time_tracking_mixed_event_filter_sql($params);

        return $where;
    }

    /**
     * Returns total number of filtered logs used for time tracking.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $starttime
     * @param int $endtime
     * @return int
     */
    protected function get_time_tracking_logs_count(
        int $userid,
        int $courseid,
        int $starttime = 0,
        int $endtime = 0
    ): int {
        global $DB;

        $params = [];
        $where = $this->get_time_tracking_logs_where_sql($params, $userid, $courseid, $starttime, $endtime);
        $sql = "SELECT COUNT(1)
                  FROM {logstore_standard_log} l
                 WHERE $where";
        $total = $DB->get_field_sql($sql, $params);

        return ($total !== false && $total !== null) ? (int)$total : 0;
    }

    /**
     * Returns total number of distinct days with filtered logs used for time tracking.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $starttime
     * @param int $endtime
     * @return int
     */
    protected function get_time_tracking_days_count(
        int $userid,
        int $courseid,
        int $starttime = 0,
        int $endtime = 0
    ): int {
        global $DB;

        $params = [];
        $where = $this->get_time_tracking_logs_where_sql($params, $userid, $courseid, $starttime, $endtime);
        $sql = "SELECT COUNT(DISTINCT FLOOR(l.timecreated / 86400))
                  FROM {logstore_standard_log} l
                 WHERE $where";
        $totaldays = $DB->get_field_sql($sql, $params);

        return ($totaldays !== false && $totaldays !== null) ? (int)$totaldays : 0;
    }

    /**
     * Public wrapper to expose daily connection totals using central time-tracking logic.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $starttime
     * @param int $endtime
     * @param int $sessionlimittime
     * @return array<int,int>
     */
    public function get_time_tracking_daily_connection_totals(
        int $userid,
        int $courseid,
        int $starttime = 0,
        int $endtime = 0,
        int $sessionlimittime = 1800
    ): array {
        return $this->get_daily_connection_totals(
            $userid,
            $courseid,
            $starttime,
            $endtime,
            $sessionlimittime
        );
    }

    /**
     * Returns adjusted daily totals from the consolidated table when available.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $starttime
     * @param int $endtime
     * @return array<int,int>
     */
    protected function get_consolidated_daily_connection_totals(
        int $userid,
        int $courseid,
        int $starttime = 0,
        int $endtime = 0
    ): array {
        global $DB;

        try {
            if (!$DB->get_manager()->table_exists('configurable_reports_diario')) {
                return [];
            }
        } catch (Throwable $t) {
            return [];
        }

        $params = [
            'userid' => $userid,
            'courseid' => $courseid,
        ];
        $where = 'userid = :userid AND courseid = :courseid';

        if ($starttime > 0) {
            $where .= ' AND logdate >= :startdaybucket';
            $params['startdaybucket'] = (int)floor($starttime / 86400);
        }
        if ($endtime > 0) {
            $where .= ' AND logdate <= :enddaybucket';
            $params['enddaybucket'] = (int)floor($endtime / 86400);
        }

        try {
            $records = $DB->get_records_sql(
                "SELECT logdate, SUM(tiempo_diario) AS totalseconds
                   FROM {configurable_reports_diario}
                  WHERE {$where}
               GROUP BY logdate
               ORDER BY logdate ASC",
                $params
            );
        } catch (Throwable $t) {
            return [];
        }

        if (empty($records)) {
            return [];
        }

        $totalsbyday = [];
        foreach ($records as $record) {
            $daybucket = (int)$record->logdate;
            if ($daybucket <= 0) {
                continue;
            }
            $totalsbyday[$daybucket] = max(0, (int)$record->totalseconds);
        }

        ksort($totalsbyday, SORT_NUMERIC);
        return $totalsbyday;
    }
    /**
     * Returns total tracked seconds grouped by day bucket.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $starttime
     * @param int $endtime
     * @param int $sessionlimittime
     * @return array<int,int>
     */
    protected function get_daily_connection_totals(
        int $userid,
        int $courseid,
        int $starttime = 0,
        int $endtime = 0,
        int $sessionlimittime = 1800
    ): array {
        global $DB;
        $consolidatedtotals = $this->get_consolidated_daily_connection_totals($userid, $courseid, $starttime, $endtime);
        if (!empty($consolidatedtotals)) {
            return $consolidatedtotals;
        }
        $params = [];
        $where = $this->get_time_tracking_logs_where_sql($params, $userid, $courseid, $starttime, $endtime);

        $sql = "SELECT l.id, l.timecreated, FLOOR(l.timecreated / 86400) AS daybucket
                  FROM {logstore_standard_log} l
                 WHERE $where
              ORDER BY l.timecreated ASC, l.id ASC";
        $logs = $DB->get_records_sql($sql, $params);

        if (!$logs) {
            return [];
        }

        $limitinseconds = ($sessionlimittime > 0) ? $sessionlimittime : (30 * 60);
        $totalsbyday = [];
        $previousdaybucket = null;
        $previoustime = null;

        foreach ($logs as $log) {
            $daybucket = (int)$log->daybucket;
            $currenttime = (int)$log->timecreated;

            if (!array_key_exists($daybucket, $totalsbyday)) {
                $totalsbyday[$daybucket] = 0;
            }

            if ($previousdaybucket !== null && $daybucket === $previousdaybucket && $previoustime !== null) {
                $delta = $currenttime - $previoustime;
                if ($delta > 0 && $delta <= $limitinseconds) {
                    $totalsbyday[$daybucket] += $delta;
                }
            }

            $previousdaybucket = $daybucket;
            $previoustime = $currenttime;
        }

        ksort($totalsbyday, SORT_NUMERIC);
        return $totalsbyday;
    }

    /**
     * Returns total tracked seconds in the selected period.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $starttime
     * @param int $endtime
     * @param int $sessionlimittime
     * @return int
     */
    protected function get_total_connection_seconds(
        int $userid,
        int $courseid,
        int $starttime = 0,
        int $endtime = 0,
        int $sessionlimittime = 1800
    ): int {
        $totalsbyday = $this->get_time_tracking_daily_connection_totals(
            $userid,
            $courseid,
            $starttime,
            $endtime,
            $sessionlimittime
        );

        if (empty($totalsbyday)) {
            return 0;
        }
        $realtotal = 0;
        foreach ($totalsbyday as $totalseconds) {
            $realtotal += max(0, (int)$totalseconds);
        }

        return (int)$realtotal;
    }

    /**
     * Returns SQL filter used in time tracking metrics.
     * Counts only events that represent user navigation/work inside a specific course:
     * - Course page views.
     * - Activity/module interactions (mod_*) in module context.
     *
     * @param array $params
     * @return string
     */
    protected function get_time_tracking_mixed_event_filter_sql(array &$params): string {
        global $DB;
        $params['ttcontextcourse'] = CONTEXT_COURSE;
        $params['ttcontextmodule'] = CONTEXT_MODULE;
        $params['tttargetcourse'] = 'course';
        $params['ttactionviewedcourse'] = 'viewed';
        $params['ttactionviewedmodule'] = 'viewed';
        $params['ttcrudread'] = 'r';
        $params['ttcrudcreate'] = 'c';
        $params['ttcrudupdate'] = 'u';
        $params['ttmodcomponentlike'] = 'mod\_%';

        return " AND (
                    (
                        l.contextlevel = :ttcontextcourse
                        AND l.target = :tttargetcourse
                        AND l.action = :ttactionviewedcourse
                    )
                    OR
                    (
                        l.contextlevel = :ttcontextmodule
                        AND " . $DB->sql_like('l.component', ':ttmodcomponentlike', false, false) . "
                        AND (
                            l.action = :ttactionviewedmodule
                            OR l.crud = :ttcrudread
                            OR l.crud = :ttcrudcreate
                            OR l.crud = :ttcrudupdate
                        )
                    )
                )";
    }

    /**
     * Renders the daily connection times table as HTML.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $starttime
     * @param int $endtime
     * @param int $sessionlimittime
     * @return string
     */
    protected function render_daily_connection_times_table_html(
        int $userid,
        int $courseid,
        int $starttime = 0,
        int $endtime = 0,
        int $sessionlimittime = 1800
    ): string {
        $totalsbyday = $this->get_time_tracking_daily_connection_totals(
            $userid,
            $courseid,
            $starttime,
            $endtime,
            $sessionlimittime
        );
        if (empty($totalsbyday)) {
            return '<div style="font-size:12px;">Sin registros de conexión.</div>';
        }

        $html = '<table style="width:100%;border-collapse:collapse;font-size:12px;">';
        $html .= '<thead><tr>';
        $html .= '<th style="border:1px solid #222;padding:4px;text-align:center;font-weight:bold;">Fecha</th>';
        $html .= '<th style="border:1px solid #222;padding:4px;text-align:center;font-weight:bold;">Información</th>';
        $html .= '<th style="border:1px solid #222;padding:4px;text-align:center;font-weight:bold;">Tiempo diario</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($totalsbyday as $daybucket => $totalseconds) {
            $daystart = ((int)$daybucket) * 86400;
            $html .= '<tr>';
            $html .= '<td style="border:1px solid #222;padding:4px;text-align:center;">' .
                s(userdate($daystart, '%d/%m/%Y')) . '</td>';
            $html .= '<td style="border:1px solid #222;padding:4px;text-align:center;">Totales diarios:</td>';
            $html .= '<td style="border:1px solid #222;padding:4px;text-align:center;">' .
                s($this->format_duration_hms((int)$totalseconds, true)) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        return $html;
    }

    /**
     * Formats seconds as hour/minute/second text.
     *
     * @param int $totalseconds
     * @param bool $zeropadded
     * @return string
     */
    protected function format_duration_hms(int $totalseconds, bool $zeropadded = false): string {
        $totalseconds = max(0, $totalseconds);
        $hours = (int)floor($totalseconds / 3600);
        $minutes = (int)floor(($totalseconds % 3600) / 60);
        $seconds = (int)($totalseconds % 60);

        if ($zeropadded) {
            return sprintf('%02dh %02dm %02ds', $hours, $minutes, $seconds);
        }

        return $hours . 'h ' . $minutes . 'm ' . $seconds . 's';
    }

    /**
     * Formats an access timestamp in two lines: date and time.
     *
     * @param int $timestamp
     * @return string
     */
    protected function format_access_datetime(int $timestamp): string {
        return userdate($timestamp, '%d/%m/%Y') . '<br>' . userdate($timestamp, '%H:%M');
    }
    /**
     * Returns completion fraction for a module type in a course.
     *
     * @param int $userid
     * @param int $courseid
     * @param string $modname
     * @return string
     */
    protected function get_completion_fraction_by_modname(int $userid, int $courseid, string $modname): string {
        global $DB;

        $totalsql = "SELECT COUNT(1)
                       FROM {course_modules} cm
                       JOIN {modules} m ON m.id = cm.module
                      WHERE cm.course = :courseid
                        AND :userid > 0
                        AND m.name = :modname";
        $total = $DB->get_field_sql($totalsql, ['courseid' => $courseid, 'userid' => $userid, 'modname' => $modname]);
        $total = ($total !== false && $total !== null) ? (int)$total : 0;

        if (!$total) {
            return '0 / 0';
        }

        $completedsql = "SELECT COUNT(DISTINCT cm.id)
                           FROM {course_modules} cm
                           JOIN {modules} m ON m.id = cm.module
                           JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id
                          WHERE cm.course = :courseid
                            AND m.name = :modname
                            AND cmc.userid = :userid
                            AND cmc.completionstate > 0";
        $completed = $DB->get_field_sql($completedsql, [
            'courseid' => $courseid,
            'modname' => $modname,
            'userid' => $userid,
        ]);
        $completed = ($completed !== false && $completed !== null) ? (int)$completed : 0;

        if ($completed > $total) {
            $completed = $total;
        }

        return $completed . ' / ' . $total;
    }

    /**
     * Parses selected course module IDs from CSV text.
     *
     * @param string $selectedcmidsraw
     * @return array<int>
     */
    protected function parse_selected_cmids(string $selectedcmidsraw): array {
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

    /**
     * Returns viewed content progress based on user logs: X / Y (Z%).
     * X = distinct visible course modules viewed/interacted by user.
     * Y = distinct visible course modules in the course (excluding labels).
     *
     * @param int $userid
     * @param int $courseid
     * @param array<int> $selectedcmids
     * @return string
     */
    protected function get_logged_content_progress(int $userid, int $courseid, array $selectedcmids = []): string {
        $total = $this->get_total_viewable_course_modules($courseid, $selectedcmids);
        if ($total <= 0) {
            return '0 / 0 (0.00%)';
        }

        $viewed = $this->get_viewed_course_modules_by_logs($userid, $courseid, true, $selectedcmids);
        if ($viewed <= 0) {
            // Fallback for modules/events where read/view actions are not recorded consistently.
            $viewed = $this->get_viewed_course_modules_by_logs($userid, $courseid, false, $selectedcmids);
        }

        if ($viewed > $total) {
            $viewed = $total;
        }

        $percentage = ($total > 0) ? (($viewed * 100) / $total) : 0;
        return $viewed . ' / ' . $total . ' (' . format_float($percentage, 2) . '%)';
    }

    /**
     * Returns total visible modules in course considered as viewable content.
     *
     * @param int $courseid
     * @param array<int> $selectedcmids
     * @return int
     */
    protected function get_total_viewable_course_modules(int $courseid, array $selectedcmids = []): int {
        global $DB;

        $params = [
            'courseid' => $courseid,
            'labelmodname' => 'label',
        ];
        $selectedwhere = '';
        if (!empty($selectedcmids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($selectedcmids, SQL_PARAMS_NAMED, 'cmid');
            $selectedwhere = " AND cm.id $insql";
            $params = array_merge($params, $inparams);
        }

        $sql = "SELECT COUNT(DISTINCT cm.id)
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                 WHERE cm.course = :courseid
                   AND cm.visible = 1
                   AND m.name <> :labelmodname" . $selectedwhere;
        $total = $DB->get_field_sql($sql, $params);

        return ($total !== false && $total !== null) ? (int)$total : 0;
    }

    /**
     * Returns number of distinct course modules viewed/interacted by user in logs.
     *
     * @param int $userid
     * @param int $courseid
     * @param bool $strictreadonly If true, only read/view actions are counted.
     * @param array<int> $selectedcmids
     * @return int
     */
    protected function get_viewed_course_modules_by_logs(
        int $userid,
        int $courseid,
        bool $strictreadonly = true,
        array $selectedcmids = []
    ): int {
        global $DB;

        $params = [
            'userid' => $userid,
            'logcourseid' => $courseid,
            'cmcourseid' => $courseid,
            'contextmodule' => CONTEXT_MODULE,
            'labelmodname' => 'label',
        ];

        $strictwhere = '';
        if ($strictreadonly) {
            $strictwhere = " AND (l.action = :actionviewed OR l.crud = :crudread)";
            $params['actionviewed'] = 'viewed';
            $params['crudread'] = 'r';
        }

        $selectedwhere = '';
        if (!empty($selectedcmids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($selectedcmids, SQL_PARAMS_NAMED, 'selcmid');
            $selectedwhere = " AND cm.id $insql";
            $params = array_merge($params, $inparams);
        }

        $sql = "SELECT COUNT(DISTINCT cm.id)
                  FROM {logstore_standard_log} l
                  JOIN {course_modules} cm
                    ON cm.id = l.contextinstanceid
                   AND cm.course = :cmcourseid
                  JOIN {modules} m ON m.id = cm.module
                 WHERE l.userid = :userid
                   AND l.courseid = :logcourseid
                   AND l.contextlevel = :contextmodule
                   AND cm.visible = 1
                   AND m.name <> :labelmodname" . $strictwhere . $selectedwhere;
        $viewed = $DB->get_field_sql($sql, $params);

        return ($viewed !== false && $viewed !== null) ? (int)$viewed : 0;
    }

    /**
     * Returns global module completion summary: X / Y (Z%).
     *
     * @param int $userid
     * @param int $courseid
     * @return string
     */
    protected function get_course_completion_progress(int $userid, int $courseid): string {
        global $DB;

        $totalsql = "SELECT COUNT(1)
                       FROM {course_modules} cm
                      WHERE cm.course = :courseid
                        AND :userid > 0
                        AND cm.completion > 0";
        $total = $DB->get_field_sql($totalsql, ['courseid' => $courseid, 'userid' => $userid]);
        $total = ($total !== false && $total !== null) ? (int)$total : 0;

        if (!$total) {
            return '0 / 0 (0.00%)';
        }

        $completedsql = "SELECT COUNT(DISTINCT cm.id)
                           FROM {course_modules} cm
                           JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id
                          WHERE cm.course = :courseid
                            AND cm.completion > 0
                            AND cmc.userid = :userid
                            AND cmc.completionstate > 0";
        $completed = $DB->get_field_sql($completedsql, [
            'courseid' => $courseid,
            'userid' => $userid,
        ]);
        $completed = ($completed !== false && $completed !== null) ? (int)$completed : 0;

        if ($completed > $total) {
            $completed = $total;
        }

        $percentage = ($total > 0) ? (($completed * 100) / $total) : 0;

        return $completed . ' / ' . $total . ' (' . format_float($percentage, 2) . '%)';
    }

    /**
     * Returns distinct user IDs assigned to roles with given archetypes in a course context.
     *
     * @param int $courseid
     * @param array $archetypes
     * @param int $excludeuserid
     * @return array
     */
    protected function get_course_user_ids_by_archetypes(int $courseid, array $archetypes, int $excludeuserid = 0): array {
        global $DB;

        if (empty($archetypes)) {
            return [];
        }

        [$insql, $inparams] = $DB->get_in_or_equal($archetypes, SQL_PARAMS_NAMED, 'arc');
        $params = array_merge([
            'contextlevel' => CONTEXT_COURSE,
            'courseid' => $courseid,
        ], $inparams);

        $sql = "SELECT DISTINCT ra.userid
                  FROM {role_assignments} ra
                  JOIN {context} ctx ON ctx.id = ra.contextid
                  JOIN {role} r ON r.id = ra.roleid
                 WHERE ctx.contextlevel = :contextlevel
                   AND ctx.instanceid = :courseid
                   AND r.archetype $insql";
        $records = $DB->get_records_sql($sql, $params);

        if (!$records) {
            return [];
        }

        $userids = [];
        foreach ($records as $record) {
            $id = (int)$record->userid;
            if ($id > 0 && $id !== $excludeuserid) {
                $userids[$id] = $id;
            }
        }

        return array_values($userids);
    }

    /**
     * Counts user messages exchanged with a role group in the current course.
     *
     * @param int $userid
     * @param int $courseid
     * @param array $targetarchetypes
     * @return int
     */
    protected function count_messages_by_course_group(int $userid, int $courseid, array $targetarchetypes): int {
        global $DB;

        $targetids = $this->get_course_user_ids_by_archetypes($courseid, $targetarchetypes, $userid);
        if (empty($targetids)) {
            return 0;
        }

        $dbman = $DB->get_manager();
        [$insql, $inparams] = $DB->get_in_or_equal($targetids, SQL_PARAMS_NAMED, 'tg1');
        [$insql2, $inparams2] = $DB->get_in_or_equal($targetids, SQL_PARAMS_NAMED, 'tg2');

        if ($dbman->table_exists('message_messages') && $dbman->table_exists('message_conversation_members')) {
            $sql = "SELECT COUNT(DISTINCT mm.id) AS total
                      FROM {message_messages} mm
                      JOIN {message_conversation_members} me
                        ON me.conversationid = mm.conversationid
                       AND me.userid = :userid
                      JOIN {message_conversation_members} mt
                        ON mt.conversationid = mm.conversationid
                       AND mt.userid $insql
                     WHERE (mm.useridfrom = :useridfrom OR mm.useridfrom $insql2)";
            $params = array_merge(
                ['userid' => $userid, 'useridfrom' => $userid],
                $inparams,
                $inparams2
            );
            try {
                $record = $DB->get_record_sql($sql, $params);
                return !empty($record->total) ? (int)$record->total : 0;
            } catch (dml_exception $e) {
                return 0;
            }
        }

        if ($dbman->table_exists('message')) {
            $sql = "SELECT COUNT(1) AS total
                      FROM {message} m
                     WHERE ((m.useridfrom = :useridfrom AND m.useridto $insql)
                        OR (m.useridto = :useridto AND m.useridfrom $insql2))";
            $params = array_merge(
                ['useridfrom' => $userid, 'useridto' => $userid],
                $inparams,
                $inparams2
            );
            try {
                $record = $DB->get_record_sql($sql, $params);
                return !empty($record->total) ? (int)$record->total : 0;
            } catch (dml_exception $e) {
                return 0;
            }
        }

        if ($dbman->table_exists('messages')) {
            $sql = "SELECT COUNT(1) AS total
                      FROM {messages} m
                     WHERE ((m.useridfrom = :useridfrom AND m.useridto $insql)
                        OR (m.useridto = :useridto AND m.useridfrom $insql2))";
            $params = array_merge(
                ['useridfrom' => $userid, 'useridto' => $userid],
                $inparams,
                $inparams2
            );
            try {
                $record = $DB->get_record_sql($sql, $params);
                return !empty($record->total) ? (int)$record->total : 0;
            } catch (dml_exception $e) {
                return 0;
            }
        }

        return 0;
    }
    /**
     * Resolve a concrete course ID for metrics that must run inside a course.
     * Priority: filter_courses > courseid parameter > current row course fields > report course.
     *
     * @param int $courseid
     * @param object $row
     * @return int
     */
    protected function resolve_courseid_for_course_metrics(int $courseid, object $row): int {
        $filteredcourseid = optional_param('filter_courses', 0, PARAM_INT);
        if ($filteredcourseid > SITEID) {
            return $filteredcourseid;
        }

        $requestcourseid = optional_param('courseid', 0, PARAM_INT);
        if ($requestcourseid > SITEID) {
            return $requestcourseid;
        }

        if (!empty($row->courseid) && (int)$row->courseid > SITEID) {
            return (int)$row->courseid;
        }

        if (!empty($row->course) && (int)$row->course > SITEID) {
            return (int)$row->course;
        }

        return ($courseid > SITEID) ? $courseid : 0;
    }

}
