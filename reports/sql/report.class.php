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
 * Configurable Reports a Moodle block for creating customizable reports
 *
 * @copyright  2020 Juan Leyva <juan@moodle.com>
 * @package    block_configurable_reports
 * @author     Juan leyva <http://www.twitter.com/jleyvadelgado>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;
defined('BLOCK_CONFIGURABLE_REPORTS_MAX_RECORDS') || define('BLOCK_CONFIGURABLE_REPORTS_MAX_RECORDS', 5000);

/**
 * Class report_sql
 *
 * @package   block_configurable_reports
 * @author    Juan leyva <http://www.twitter.com/jleyvadelgado>
 */
class report_sql extends report_base {

    /**
     * Scope key for course + platform activity.
     */
    private const ACCESS_SCOPE_COURSEPLATFORM = 'courseplatform';

    /**
     * @var bool
     */
    private bool $forexport = false;

    /**
     * set_forexport
     *
     * @param bool $isforexport
     * @return void
     */
    public function set_forexport(bool $isforexport): void {
        $this->forexport = $isforexport;
    }

    /**
     * is_forexport
     *
     * @return bool
     */
    public function is_forexport(): bool {
        return $this->forexport;
    }

    /**
     * Init
     *
     * @return void
     */
    public function init(): void {
        $this->components = [
            'customsql',
            'filters',
            'template',
            'permissions',
            'calcs',
            'plot',
        ];
    }

    /**
     * prepare_sql
     *
     * @param string $sql
     * @return string
     */
    public function prepare_sql(string $sql) {
        global $USER, $CFG, $COURSE;

        // Enable debug mode from SQL query.
        $this->config->debug = strpos($sql, '%%DEBUG%%') !== false;

        // Pass special custom undefined variable as filter.
        // Security warning !!! can be used for sql injection.
        // Use %%FILTER_VAR%% in your sql code with caution.
        $filtervar = optional_param('filter_var', '', PARAM_RAW);
        if (!empty($filtervar)) {
            $sql = str_replace('%%FILTER_VAR%%', $filtervar, $sql);
        }

        $scope = $this->get_requested_access_scope();
        [$academicstart, $academicend] = $this->get_course_academic_range((int) $COURSE->startdate, (int) ($COURSE->enddate ?? 0));
        [$starttime, $endtime] = $this->get_requested_time_range($academicstart, $academicend);
        $scopeliteral = "'" . str_replace("'", "''", $scope) . "'";
        $scopelabelliteral = "'" . str_replace("'", "''", cr_get_access_scope_label($scope)) . "'";

        // SQL helper placeholders for access analytics reports.
        $sql = str_replace('%%ACCESS_SCOPE%%', $scopeliteral, $sql);
        $sql = str_replace('%%ACCESS_SCOPE_LABEL%%', $scopelabelliteral, $sql);

        $sql = preg_replace_callback('/%%FILTER_ACCESSSCOPE_COURSEID:([^%]+)%%/i', function($matches) use ($COURSE, $scope) {
            $field = trim($matches[1]);
            if ($field === '') {
                return '';
            }

            return ' AND ' . $this->build_scope_condition($field, (int) $COURSE->id, $scope);
        }, $sql);

        $sql = preg_replace_callback('/%%ACCESS_SOURCE_EXPR:([^%]+)%%/i', function($matches) {
            $field = trim($matches[1]);
            if ($field === '') {
                return "'course'";
            }

            return "CASE WHEN {$field} = 0 THEN 'platform' ELSE 'course' END";
        }, $sql);

        $sql = preg_replace_callback('/%%ACCESS_EVENTTYPE_EXPR:([^%]+)%%/i', function($matches) {
            $field = trim($matches[1]);
            if ($field === '') {
                return "'activity'";
            }

            return "CASE " .
                "WHEN {$field} LIKE '%user_loggedin' THEN 'login' " .
                "WHEN {$field} LIKE '%user_loggedout' THEN 'logout' " .
                "ELSE 'activity' END";
        }, $sql);

        $sql = preg_replace_callback('/%%FILTER_ACADEMICPERIOD:([^%]+)%%/i', function($matches) use ($academicstart, $academicend) {
            $field = trim($matches[1]);
            if ($field === '') {
                return '';
            }

            return " AND {$field} >= {$academicstart} AND {$field} <= {$academicend}";
        }, $sql);

        // See http://en.wikipedia.org/wiki/Year_2038_problem.
        $sql = str_replace([
            '%%USERID%%',
            '%%COURSEID%%',
            '%%CATEGORYID%%',
            '%%STARTTIME%%',
            '%%ENDTIME%%',
            '%%ACADEMIC_STARTTIME%%',
            '%%ACADEMIC_ENDTIME%%',
            '%%WWWROOT%%',
        ],
            [
                $USER->id,
                $COURSE->id,
                $COURSE->category,
                (string) $starttime,
                (string) $endtime,
                (string) $academicstart,
                (string) $academicend,
                $CFG->wwwroot,
            ],
            $sql);
        $sql = preg_replace('/%{2}[^%]+%{2}/i', '', $sql);

        return str_replace('?', '[[QUESTIONMARK]]', $sql);
    }

    /**
     * Returns validated access scope from filter request.
     *
     * @return string
     */
    private function get_requested_access_scope(): string {
        $scope = optional_param('filter_accessscope', cr_get_access_scope_default(), PARAM_ALPHA);
        return cr_get_access_scope($scope);
    }

    /**
     * Returns selected filter date range as unix timestamps.
     *
     * @return array
     */
    private function get_requested_time_range(int $defaultstarttime = 0, int $defaultendtime = 2145938400): array {
        $starttime = $defaultstarttime;
        $endtime = $defaultendtime;

        $filterstarttime = optional_param_array('filter_starttime', [], PARAM_RAW);
        $filterendtime = optional_param_array('filter_endtime', [], PARAM_RAW);

        if (!empty($filterstarttime) && !empty($filterendtime) &&
            isset($filterstarttime['year'], $filterstarttime['month'], $filterstarttime['day']) &&
            isset($filterendtime['year'], $filterendtime['month'], $filterendtime['day'])) {
            $starttime = make_timestamp(
                $filterstarttime['year'],
                $filterstarttime['month'],
                $filterstarttime['day'],
                $filterstarttime['hour'] ?? 0,
                $filterstarttime['minute'] ?? 0
            );
            $endtime = make_timestamp(
                $filterendtime['year'],
                $filterendtime['month'],
                $filterendtime['day'],
                $filterendtime['hour'] ?? 0,
                $filterendtime['minute'] ?? 0
            );
        }

        return [$starttime, $endtime];
    }

    /**
     * Builds SQL condition for selected access scope.
     *
     * @param string $courseidfield
     * @param int $courseid
     * @param string $scope
     * @return string
     */
    private function build_scope_condition(string $courseidfield, int $courseid, string $scope): string {
        if ($scope === self::ACCESS_SCOPE_COURSEPLATFORM) {
            return "({$courseidfield} = {$courseid} OR {$courseidfield} = 0)";
        }

        return "{$courseidfield} = {$courseid}";
    }

    /**
     * Course academic period range.
     *
     * @param int $coursestart
     * @param int $courseend
     * @return array
     */
    private function get_course_academic_range(int $coursestart, int $courseend): array {
        $start = max(0, $coursestart);
        $end = ($courseend > 0) ? $courseend : 2145938400;

        if ($end < $start) {
            return [$start, 2145938400];
        }

        return [$start, $end];
    }

    /**
     * execute_query
     *
     * @param string $sql
     * @return mixed
     */
    public function execute_query($sql) {
        global $remotedb, $DB, $CFG;

        $sql = preg_replace('/\bprefix_(?=\w+)/i', $CFG->prefix, $sql);

        $reportlimit = get_config('block_configurable_reports', 'reportlimit');
        if (empty($reportlimit) || $reportlimit == '0') {
            $reportlimit = BLOCK_CONFIGURABLE_REPORTS_MAX_RECORDS;
        }

        $starttime = microtime(true);

        if (preg_match('/\b(INSERT|INTO|CREATE)\b/i', $sql) && !empty($CFG->block_configurable_reports_enable_sql_execution)) {
            // Run special (dangerous) queries directly.
            $results = $remotedb->execute($sql);
        } else {
            $results = $remotedb->get_recordset_sql($sql, null, 0, $reportlimit);
        }

        // Update the execution time in the DB.
        $updaterecord = $DB->get_record('block_configurable_reports', ['id' => $this->config->id]);
        $updaterecord->lastexecutiontime = round((microtime(true) - $starttime) * 1000);
        $this->config->lastexecutiontime = $updaterecord->lastexecutiontime;

        $DB->update_record('block_configurable_reports', $updaterecord);

        return $results;
    }

    /**
     * create_report
     *
     * @return bool
     */
    public function create_report(): bool {
        global $CFG;

        $components = cr_unserialize($this->config->components);

        $filters = $components['filters']['elements'] ?? [];
        $calcs = $components['calcs']['elements'] ?? [];

        $tablehead = [];
        $finalcalcs = [];
        $finaltable = [];

        $components = cr_unserialize($this->config->components);
        $config = $components['customsql']['config'] ?? new stdClass;
        $totalrecords = 0;

        $sql = '';
        if (isset($config->querysql)) {
            // Filters.
            $sql = $config->querysql;
            if (!empty($filters)) {
                foreach ($filters as $f) {
                    require_once($CFG->dirroot . '/blocks/configurable_reports/components/filters/' . $f['pluginname'] .
                        '/plugin.class.php');
                    $classname = 'plugin_' . $f['pluginname'];
                    $class = new $classname($this->config);
                    $sql = $class->execute($sql, $f['formdata']);
                }
            }

            $sql = $this->prepare_sql($sql);

            if ($rs = $this->execute_query($sql)) {
                foreach ($rs as $row) {
                    if (empty($finaltable)) {
                        foreach ($row as $colname => $value) {
                            $tablehead[] = $colname;
                        }
                    }
                    $arrayrow = array_values((array) $row);
                    foreach ($arrayrow as $ii => $cell) {
                        if (!$this->is_forexport()) {
                            $cell = format_text($cell, FORMAT_HTML, ['trusted' => true, 'noclean' => true, 'para' => false]);
                        }
                        $arrayrow[$ii] = str_replace('[[QUESTIONMARK]]', '?', $cell);
                    }
                    $totalrecords++;
                    $finaltable[] = $arrayrow;
                }
            }
        }
        $this->sql = $sql;
        $this->totalrecords = $totalrecords;

        // Calcs.

        $finalcalcs = $this->get_calcs($finaltable, $tablehead);

        $table = new stdClass;
        $table->id = 'reporttable';
        $table->data = $finaltable;
        $table->head = $tablehead;

        $calcs = new html_table();
        $calcs->id = 'calcstable';
        $calcs->data = [$finalcalcs];
        $calcs->head = $tablehead;

        if (!$this->finalreport) {
            $this->finalreport = new stdClass;
        }
        $this->finalreport->name = $this->config->name;
        $this->finalreport->table = $table;
        $this->finalreport->calcs = $calcs;

        return true;
    }

}
