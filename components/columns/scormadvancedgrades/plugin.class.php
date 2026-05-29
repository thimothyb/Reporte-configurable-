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
 * @copyright  2026
 * @package    block_configurable_reports
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;
require_once($CFG->dirroot . '/blocks/configurable_reports/plugin.class.php');

/**
 * Column plugin: activity/resource advanced statistics (including SCORM grade/progress).
 *
 * @package   block_configurable_reports
 */
class plugin_scormadvancedgrades extends plugin_base {

    /** @var string */
    protected const FORMAT_AUTO = 'auto';
    /** @var string */
    protected const FORMAT_TEXT = 'text';
    /** @var string */
    protected const FORMAT_NUMBER = 'number';
    /** @var string */
    protected const FORMAT_PERCENT = 'percent';
    /** @var string */
    protected const FORMAT_DATETIME = 'datetime';

    /** @var string */
    protected const TYPE_TEXT = 'text';
    /** @var string */
    protected const TYPE_NUMBER = 'number';
    /** @var string */
    protected const TYPE_PERCENT = 'percent';
    /** @var string */
    protected const TYPE_DATETIME = 'datetime';
    /** @var string */
    protected const TYPE_BOOLEAN = 'boolean';

    /**
     * Init.
     *
     * @return void
     */
    public function init(): void {
        $this->fullname = get_string('scormadvancedgrades', 'block_configurable_reports');
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = ['users'];
    }

    /**
     * Summary shown in component list.
     *
     * @param object $data
     * @return string
     */
    public function summary(object $data): string {
        $columnname = !empty($data->columname) ? format_string($data->columname) : $this->fullname;

        // New config path (stat key).
        if (!empty($data->stat)) {
            $options = $this->get_stat_options_for_form();
            if (!empty($options[$data->stat])) {
                return $columnname . ' (' . $options[$data->stat] . ')';
            }
        }

        // Backward compatibility with previous config path (direct scormid).
        if (!empty($data->scormid)) {
            $scormname = $this->get_scorm_name((int)$data->scormid);
            if ($scormname !== '') {
                return $columnname . ' (' . $scormname . ')';
            }
        }

        return $columnname;
    }

    /**
     * Execute column for each user row.
     *
     * @param object $data plugin configuration data.
     * @param object $row complete user row.
     * @param object $user current user.
     * @param int $courseid current report course id.
     * @param int $starttime not used.
     * @param int $endtime not used.
     * @return string
     */
    public function execute($data, $row, $user, $courseid, $starttime = 0, $endtime = 0) {
        $userid = !empty($row->id) ? (int)$row->id : 0;
        if (!$userid) {
            return '';
        }

        // New path: dynamic statistics selector.
        if (!empty($data->stat)) {
            $result = $this->execute_stat_option((string)$data->stat, $userid, (int)$courseid);
            $format = !empty($data->format) ? (string)$data->format : self::FORMAT_AUTO;
            return $this->format_stat_result($result['value'], $result['type'], $format);
        }

        // Backward compatibility: score of a selected SCORM package.
        if (!empty($data->scormid)) {
            $result = $this->execute_scorm_metric($userid, (int)$data->scormid, 'score');
            return $this->format_stat_result($result['value'], $result['type'], self::FORMAT_AUTO);
        }

        return '';
    }

    /**
     * Returns statistic options for form selector.
     *
     * @return array
     */
    public function get_stat_options_for_form(): array {
        global $DB;

        $options = ['0' => get_string('choose')];
        $courseid = !empty($this->report->courseid) ? (int)$this->report->courseid : 0;
        if (!$courseid) {
            return $options;
        }

        $gradeitems = $DB->get_records_select(
            'grade_items',
            "courseid = ? AND itemtype = 'mod' AND itemmodule IS NOT NULL",
            [$courseid],
            '',
            'id,itemmodule,iteminstance'
        );
        $gradeitemindex = [];
        foreach ($gradeitems as $gradeitem) {
            $gradeitemindex[$gradeitem->itemmodule . ':' . $gradeitem->iteminstance] = true;
        }

        $modinfo = get_fast_modinfo($courseid);
        $cms = $modinfo->get_cms();

        foreach ($cms as $cm) {
            if (!empty($cm->deletioninprogress)) {
                continue;
            }

            $modname = (string)$cm->modname;
            $instanceid = (int)$cm->instance;
            $activityname = format_string($cm->name);

            switch ($modname) {
                case 'assign':
                    $options['assign:' . $instanceid . ':score'] = 'Puntuación tarea "' . $activityname . '"';
                    $options['assign:' . $instanceid . ':gradedate'] = 'Fecha de corrección de una tarea "' . $activityname . '"';
                    $options['assign:' . $instanceid . ':submissiondate'] = 'Fecha de entrega de la tarea "' . $activityname . '"';
                    $options['assign:' . $instanceid . ':grader'] = 'Usuario que corrige la tarea "' . $activityname . '"';
                    $options['assign:' . $instanceid . ':feedback'] = 'Feedback en la entrega "' . $activityname . '"';
                    $options['assign:' . $instanceid . ':submitted'] = 'Entrega completada "' . $activityname . '"';
                    $options['assign:' . $instanceid . ':passed'] = 'Entrega aprobada "' . $activityname . '"';
                    break;
                case 'forum':
                    $options['forum:' . $instanceid . ':posts'] = 'Posts del usuario "' . $activityname . '"';
                    $options['forum:' . $instanceid . ':firstpost'] = 'Fecha del primer post "' . $activityname . '"';
                    $options['forum:' . $instanceid . ':lastpost'] = 'Fecha del último post "' . $activityname . '"';
                    break;
                case 'chat':
                    $options['chat:' . $instanceid . ':messages'] = 'Mensajes en chat "' . $activityname . '"';
                    $options['chat:' . $instanceid . ':firstmessage'] = 'Primera fecha y hora en chat "' . $activityname . '"';
                    $options['chat:' . $instanceid . ':lastmessage'] = 'Última fecha y hora en chat "' . $activityname . '"';
                    break;
                case 'scorm':
                    $options['scorm:' . $instanceid . ':score'] = 'Puntuación SCORM "' . $activityname . '"';
                    $options['scorm:' . $instanceid . ':progress'] = 'Progreso SCORM "' . $activityname . '"';
                    $options['scorm:' . $instanceid . ':status'] = 'Estado SCORM "' . $activityname . '"';
                    break;
                case 'customcert':
                case 'certificate':
                case 'simplecertificate':
                    $options[$modname . ':' . $instanceid . ':issueddate'] = 'Fecha de emisión de ' .
                        $this->get_modtype_human_name($modname) . ' "' . $activityname . '"';
                    $options[$modname . ':' . $instanceid . ':issued'] = '¿Se ha emitido ' .
                        $this->get_modtype_human_name($modname) . '? - "' . $activityname . '"';
                    break;
            }

            // Generic grade option for graded activities not explicitly handled above.
            $gradekey = $modname . ':' . $instanceid;
            if (!isset($gradeitemindex[$gradekey]) || in_array($modname, ['assign', 'scorm'], true)) {
                continue;
            }
            $options['grade:' . $modname . ':' . $instanceid] = 'Puntuación ' .
                $this->get_modtype_human_name($modname) . ' "' . $activityname . '"';
        }

        return $options;
    }

    /**
     * Adds statistics selector to form.
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function add_stat_selector_to_form(MoodleQuickForm $mform): void {
        $mform->addElement(
            'select',
            'stat',
            get_string('scormadvancedgrades_stat', 'block_configurable_reports'),
            $this->get_stat_options_for_form()
        );
        $mform->setType('stat', PARAM_RAW_TRIMMED);
        $mform->addRule('stat', get_string('required'), 'required', null, 'server');

        $mform->addElement(
            'select',
            'format',
            get_string('scormadvancedgrades_format', 'block_configurable_reports'),
            $this->get_format_options_for_form()
        );
        $mform->setType('format', PARAM_ALPHANUMEXT);
        $mform->setDefault('format', self::FORMAT_AUTO);
    }

    /**
     * Backward compatibility helper (old form call path).
     *
     * @param MoodleQuickForm $mform
     * @return void
     */
    public function add_scorm_selector_to_form(MoodleQuickForm $mform): void {
        $this->add_stat_selector_to_form($mform);
    }

    /**
     * Format options for form.
     *
     * @return array
     */
    public function get_format_options_for_form(): array {
        return [
            self::FORMAT_AUTO => get_string('scormadvancedgrades_format_auto', 'block_configurable_reports'),
            self::FORMAT_TEXT => get_string('scormadvancedgrades_format_text', 'block_configurable_reports'),
            self::FORMAT_NUMBER => get_string('scormadvancedgrades_format_number', 'block_configurable_reports'),
            self::FORMAT_PERCENT => get_string('scormadvancedgrades_format_percent', 'block_configurable_reports'),
            self::FORMAT_DATETIME => get_string('scormadvancedgrades_format_datetime', 'block_configurable_reports'),
        ];
    }

    /**
     * Executes a statistic option.
     *
     * @param string $statoption
     * @param int $userid
     * @param int $courseid
     * @return array{value:mixed,type:string}
     */
    protected function execute_stat_option(string $statoption, int $userid, int $courseid): array {
        $parts = explode(':', $statoption);
        if (empty($parts[0])) {
            return ['value' => null, 'type' => self::TYPE_TEXT];
        }

        // Generic grade key format: grade:modname:instanceid.
        if ($parts[0] === 'grade' && count($parts) >= 3) {
            return $this->execute_generic_grade_metric($userid, $courseid, (string)$parts[1], (int)$parts[2]);
        }

        // Standard key format: modname:instanceid:metric.
        if (count($parts) < 3) {
            return ['value' => null, 'type' => self::TYPE_TEXT];
        }

        $modname = (string)$parts[0];
        $instanceid = (int)$parts[1];
        $metric = (string)$parts[2];

        switch ($modname) {
            case 'scorm':
                return $this->execute_scorm_metric($userid, $instanceid, $metric);
            case 'assign':
                return $this->execute_assign_metric($userid, $courseid, $instanceid, $metric);
            case 'forum':
                return $this->execute_forum_metric($userid, $instanceid, $metric);
            case 'chat':
                return $this->execute_chat_metric($userid, $instanceid, $metric);
            case 'customcert':
            case 'certificate':
            case 'simplecertificate':
                return $this->execute_certificate_metric($userid, $modname, $instanceid, $metric);
            default:
                return ['value' => null, 'type' => self::TYPE_TEXT];
        }
    }

    /**
     * Executes SCORM metrics.
     *
     * @param int $userid
     * @param int $scormid
     * @param string $metric
     * @return array{value:mixed,type:string}
     */
    protected function execute_scorm_metric(int $userid, int $scormid, string $metric): array {
        $trackvalues = $this->get_latest_scorm_track_values($userid, $scormid);
        if (empty($trackvalues)) {
            return ['value' => null, 'type' => self::TYPE_PERCENT];
        }

        if ($metric === 'status') {
            $status = $this->get_first_text_track_value($trackvalues, ['cmi.completion_status', 'cmi.core.lesson_status']);
            return ['value' => $status, 'type' => self::TYPE_TEXT];
        }

        if ($metric === 'progress') {
            $progressmeasure = $this->get_first_numeric_track_value($trackvalues, ['cmi.progress_measure']);
            if ($progressmeasure !== null) {
                if ($progressmeasure >= 0 && $progressmeasure <= 1) {
                    $progressmeasure *= 100.0;
                }
                return ['value' => $progressmeasure, 'type' => self::TYPE_PERCENT];
            }

            $status = $this->get_first_text_track_value($trackvalues, ['cmi.completion_status', 'cmi.core.lesson_status']);
            if ($status !== null) {
                $normalized = strtolower(trim($status));
                if (in_array($normalized, ['completed', 'passed', 'failed', 'browsed'], true)) {
                    return ['value' => 100.0, 'type' => self::TYPE_PERCENT];
                }
                if (in_array($normalized, ['incomplete', 'not attempted', 'not_attempted'], true)) {
                    return ['value' => 0.0, 'type' => self::TYPE_PERCENT];
                }
            }

            return ['value' => null, 'type' => self::TYPE_PERCENT];
        }

        // Default/score metric.
        $rawscore = $this->get_first_numeric_track_value($trackvalues, ['cmi.score.raw', 'cmi.core.score.raw']);
        if ($rawscore === null) {
            return ['value' => null, 'type' => self::TYPE_PERCENT];
        }

        $minscore = $this->get_first_numeric_track_value($trackvalues, ['cmi.score.min', 'cmi.core.score.min']);
        $maxscore = $this->get_first_numeric_track_value($trackvalues, ['cmi.score.max', 'cmi.core.score.max']);
        if ($minscore !== null && $maxscore !== null && $maxscore > $minscore) {
            $rawscore = (($rawscore - $minscore) / ($maxscore - $minscore)) * 100.0;
        }

        return ['value' => $rawscore, 'type' => self::TYPE_PERCENT];
    }

    /**
     * Executes assignment metrics.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $assignid
     * @param string $metric
     * @return array{value:mixed,type:string}
     */
    protected function execute_assign_metric(int $userid, int $courseid, int $assignid, string $metric): array {
        global $DB;

        $sql = "SELECT gi.id AS gradeitemid,
                       gi.grademin,
                       gi.grademax,
                       gi.gradepass,
                       gg.finalgrade,
                       ag.id AS assigngradeid,
                       ag.timemodified AS gradedate,
                       ag.grader
                  FROM {grade_items} gi
             LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = :userid
             LEFT JOIN {assign_grades} ag ON ag.assignment = gi.iteminstance AND ag.userid = :userid2
                 WHERE gi.courseid = :courseid
                   AND gi.itemtype = 'mod'
                   AND gi.itemmodule = 'assign'
                   AND gi.iteminstance = :assignid";
        $graderec = $DB->get_record_sql($sql, [
            'userid' => $userid,
            'userid2' => $userid,
            'courseid' => $courseid,
            'assignid' => $assignid,
        ]);

        $submission = $DB->get_record('assign_submission', [
            'assignment' => $assignid,
            'userid' => $userid,
        ], 'id,status,timecreated,timemodified', IGNORE_MISSING);

        switch ($metric) {
            case 'gradedate':
                $date = !empty($graderec->gradedate) ? (int)$graderec->gradedate : null;
                return ['value' => $date, 'type' => self::TYPE_DATETIME];
            case 'submissiondate':
                $date = null;
                if (!empty($submission->timemodified)) {
                    $date = (int)$submission->timemodified;
                } else if (!empty($submission->timecreated)) {
                    $date = (int)$submission->timecreated;
                }
                return ['value' => $date, 'type' => self::TYPE_DATETIME];
            case 'grader':
                if (empty($graderec->grader)) {
                    return ['value' => null, 'type' => self::TYPE_TEXT];
                }
                if ($grader = $DB->get_record('user', ['id' => (int)$graderec->grader])) {
                    return ['value' => fullname($grader), 'type' => self::TYPE_TEXT];
                }
                return ['value' => null, 'type' => self::TYPE_TEXT];
            case 'feedback':
                if (empty($graderec->assigngradeid)) {
                    return ['value' => null, 'type' => self::TYPE_TEXT];
                }
                if ($DB->get_manager()->table_exists('assignfeedback_comments')) {
                    $feedback = $DB->get_field('assignfeedback_comments', 'commenttext', [
                        'grade' => (int)$graderec->assigngradeid,
                    ]);
                    return ['value' => $feedback, 'type' => self::TYPE_TEXT];
                }
                return ['value' => null, 'type' => self::TYPE_TEXT];
            case 'submitted':
                if (empty($submission)) {
                    return ['value' => 0, 'type' => self::TYPE_BOOLEAN];
                }
                $issubmitted = in_array((string)$submission->status, ['submitted', 'graded'], true) ? 1 : 0;
                return ['value' => $issubmitted, 'type' => self::TYPE_BOOLEAN];
            case 'passed':
                if (empty($graderec) || $graderec->finalgrade === null) {
                    return ['value' => null, 'type' => self::TYPE_BOOLEAN];
                }
                if ((float)$graderec->gradepass <= 0) {
                    return ['value' => null, 'type' => self::TYPE_BOOLEAN];
                }
                return ['value' => ((float)$graderec->finalgrade >= (float)$graderec->gradepass) ? 1 : 0, 'type' => self::TYPE_BOOLEAN];
            case 'score':
            default:
                if (empty($graderec) || $graderec->finalgrade === null) {
                    return ['value' => null, 'type' => self::TYPE_NUMBER];
                }
                return ['value' => (float)$graderec->finalgrade, 'type' => self::TYPE_NUMBER];
        }
    }

    /**
     * Executes forum metrics.
     *
     * @param int $userid
     * @param int $forumid
     * @param string $metric
     * @return array{value:mixed,type:string}
     */
    protected function execute_forum_metric(int $userid, int $forumid, string $metric): array {
        global $DB;

        $sql = "SELECT COUNT(p.id) AS totalposts,
                       MIN(p.created) AS firstpost,
                       MAX(p.created) AS lastpost
                  FROM {forum_posts} p
                  JOIN {forum_discussions} d ON d.id = p.discussion
                 WHERE d.forum = :forumid
                   AND p.userid = :userid";
        $stats = $DB->get_record_sql($sql, ['forumid' => $forumid, 'userid' => $userid]);
        if (empty($stats)) {
            return ['value' => null, 'type' => self::TYPE_TEXT];
        }

        switch ($metric) {
            case 'firstpost':
                return ['value' => !empty($stats->firstpost) ? (int)$stats->firstpost : null, 'type' => self::TYPE_DATETIME];
            case 'lastpost':
                return ['value' => !empty($stats->lastpost) ? (int)$stats->lastpost : null, 'type' => self::TYPE_DATETIME];
            case 'posts':
            default:
                return ['value' => (int)$stats->totalposts, 'type' => self::TYPE_NUMBER];
        }
    }

    /**
     * Executes chat metrics.
     *
     * @param int $userid
     * @param int $chatid
     * @param string $metric
     * @return array{value:mixed,type:string}
     */
    protected function execute_chat_metric(int $userid, int $chatid, string $metric): array {
        global $DB;

        $sql = "SELECT COUNT(id) AS totalmessages,
                       MIN(timestamp) AS firstmessage,
                       MAX(timestamp) AS lastmessage
                  FROM {chat_messages}
                 WHERE chatid = :chatid
                   AND userid = :userid";
        $stats = $DB->get_record_sql($sql, ['chatid' => $chatid, 'userid' => $userid]);
        if (empty($stats)) {
            return ['value' => null, 'type' => self::TYPE_TEXT];
        }

        switch ($metric) {
            case 'firstmessage':
                return ['value' => !empty($stats->firstmessage) ? (int)$stats->firstmessage : null, 'type' => self::TYPE_DATETIME];
            case 'lastmessage':
                return ['value' => !empty($stats->lastmessage) ? (int)$stats->lastmessage : null, 'type' => self::TYPE_DATETIME];
            case 'messages':
            default:
                return ['value' => (int)$stats->totalmessages, 'type' => self::TYPE_NUMBER];
        }
    }

    /**
     * Executes certificate-like metrics.
     *
     * @param int $userid
     * @param string $modname
     * @param int $instanceid
     * @param string $metric
     * @return array{value:mixed,type:string}
     */
    protected function execute_certificate_metric(int $userid, string $modname, int $instanceid, string $metric): array {
        global $DB;

        $map = [
            'customcert' => ['table' => 'customcert_issues', 'instancefield' => 'customcertid', 'timefield' => 'timecreated'],
            'certificate' => ['table' => 'certificate_issues', 'instancefield' => 'certificateid', 'timefield' => 'timecreated'],
            'simplecertificate' => ['table' => 'simplecertificate_issues', 'instancefield' => 'certificateid', 'timefield' => 'timemodified'],
        ];

        if (empty($map[$modname])) {
            return ['value' => null, 'type' => self::TYPE_TEXT];
        }

        $table = $map[$modname]['table'];
        $instancefield = $map[$modname]['instancefield'];
        $timefield = $map[$modname]['timefield'];

        if (!$DB->get_manager()->table_exists($table)) {
            return ['value' => null, 'type' => self::TYPE_TEXT];
        }

        $sql = "SELECT *
                  FROM {" . $table . "}
                 WHERE " . $instancefield . " = :instanceid
                   AND userid = :userid
              ORDER BY id DESC";
        $issue = $DB->get_record_sql($sql, ['instanceid' => $instanceid, 'userid' => $userid], IGNORE_MULTIPLE);

        if ($metric === 'issued') {
            return ['value' => !empty($issue) ? 1 : 0, 'type' => self::TYPE_BOOLEAN];
        }

        if (empty($issue) || empty($issue->{$timefield})) {
            return ['value' => null, 'type' => self::TYPE_DATETIME];
        }
        return ['value' => (int)$issue->{$timefield}, 'type' => self::TYPE_DATETIME];
    }

    /**
     * Executes generic grade metric for a module instance.
     *
     * @param int $userid
     * @param int $courseid
     * @param string $modname
     * @param int $instanceid
     * @return array{value:mixed,type:string}
     */
    protected function execute_generic_grade_metric(int $userid, int $courseid, string $modname, int $instanceid): array {
        global $DB;

        $sql = "SELECT gg.finalgrade
                  FROM {grade_items} gi
             LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = :userid
                 WHERE gi.courseid = :courseid
                   AND gi.itemtype = 'mod'
                   AND gi.itemmodule = :itemmodule
                   AND gi.iteminstance = :iteminstance";
        $grade = $DB->get_field_sql($sql, [
            'userid' => $userid,
            'courseid' => $courseid,
            'itemmodule' => $modname,
            'iteminstance' => $instanceid,
        ]);

        if ($grade === false || $grade === null) {
            return ['value' => null, 'type' => self::TYPE_NUMBER];
        }
        return ['value' => (float)$grade, 'type' => self::TYPE_NUMBER];
    }

    /**
     * Gets the latest SCORM tracking values per element.
     *
     * @param int $userid
     * @param int $scormid
     * @return array
     */
    protected function get_latest_scorm_track_values(int $userid, int $scormid): array {
        global $DB;

        $attemptsql = "SELECT MAX(st.attempt)
                         FROM {scorm_scoes_track} st
                        WHERE st.userid = :userid
                          AND st.scormid = :scormid";
        $attempt = $DB->get_field_sql($attemptsql, ['userid' => $userid, 'scormid' => $scormid]);
        if ($attempt === false || $attempt === null) {
            return [];
        }

        $elements = [
            'cmi.score.raw',
            'cmi.core.score.raw',
            'cmi.score.min',
            'cmi.core.score.min',
            'cmi.score.max',
            'cmi.core.score.max',
            'cmi.progress_measure',
            'cmi.completion_status',
            'cmi.core.lesson_status',
        ];

        [$insql, $inparams] = $DB->get_in_or_equal($elements, SQL_PARAMS_NAMED, 'el');
        $params = array_merge([
            'userid' => $userid,
            'scormid' => $scormid,
            'attempt' => (int)$attempt,
        ], $inparams);

        $tracksql = "SELECT st.id, st.element, st.value, st.timemodified
                       FROM {scorm_scoes_track} st
                      WHERE st.userid = :userid
                        AND st.scormid = :scormid
                        AND st.attempt = :attempt
                        AND st.element $insql
                   ORDER BY st.timemodified DESC, st.id DESC";
        $trackrows = $DB->get_records_sql($tracksql, $params);
        if (!$trackrows) {
            return [];
        }

        $trackvalues = [];
        foreach ($trackrows as $trackrow) {
            if (!array_key_exists($trackrow->element, $trackvalues)) {
                $trackvalues[$trackrow->element] = (string)$trackrow->value;
            }
        }
        return $trackvalues;
    }

    /**
     * Gets first valid numeric value from a list of SCORM track elements.
     *
     * @param array $trackvalues
     * @param array $elements
     * @return float|null
     */
    protected function get_first_numeric_track_value(array $trackvalues, array $elements): ?float {
        foreach ($elements as $element) {
            if (!array_key_exists($element, $trackvalues)) {
                continue;
            }
            $numericvalue = $this->to_float_or_null($trackvalues[$element]);
            if ($numericvalue !== null) {
                return $numericvalue;
            }
        }
        return null;
    }

    /**
     * Gets first non-empty text value from a list of SCORM track elements.
     *
     * @param array $trackvalues
     * @param array $elements
     * @return string|null
     */
    protected function get_first_text_track_value(array $trackvalues, array $elements): ?string {
        foreach ($elements as $element) {
            if (!array_key_exists($element, $trackvalues)) {
                continue;
            }
            $value = trim((string)$trackvalues[$element]);
            if ($value !== '') {
                return $value;
            }
        }
        return null;
    }

    /**
     * Converts a metric value into final display text.
     *
     * @param mixed $value
     * @param string $valuetype
     * @param string $format
     * @return string
     */
    protected function format_stat_result($value, string $valuetype, string $format): string {
        if ($value === null || $value === '') {
            return '';
        }

        $format = $format ?: self::FORMAT_AUTO;
        if ($format === self::FORMAT_AUTO) {
            switch ($valuetype) {
                case self::TYPE_PERCENT:
                    return $this->format_percentage((float)$value);
                case self::TYPE_DATETIME:
                    return !empty($value) ? userdate((int)$value) : '';
                case self::TYPE_BOOLEAN:
                    return ((int)$value === 1) ? get_string('yes') : get_string('no');
                case self::TYPE_NUMBER:
                    return $this->format_numeric((float)$value);
                case self::TYPE_TEXT:
                default:
                    return (string)$value;
            }
        }

        switch ($format) {
            case self::FORMAT_PERCENT:
                return $this->format_percentage((float)$value);
            case self::FORMAT_DATETIME:
                return !empty($value) ? userdate((int)$value) : '';
            case self::FORMAT_NUMBER:
                return is_numeric($value) ? $this->format_numeric((float)$value) : '';
            case self::FORMAT_TEXT:
                return (string)$value;
            case self::FORMAT_AUTO:
            default:
                return (string)$value;
        }
    }

    /**
     * Formats numeric values.
     *
     * @param float $number
     * @return string
     */
    protected function format_numeric(float $number): string {
        if (abs($number - round($number)) < 0.00001) {
            return (string)((int)round($number));
        }
        return format_float($number, 2);
    }

    /**
     * Formats percentage as string with % suffix.
     *
     * @param float $percentage
     * @return string
     */
    protected function format_percentage(float $percentage): string {
        $percentage = max(0.0, min(100.0, $percentage));
        if (abs($percentage - round($percentage)) < 0.00001) {
            return (string)((int)round($percentage)) . '%';
        }
        return format_float($percentage, 2) . '%';
    }

    /**
     * Parses numeric string into float.
     *
     * @param string $value
     * @return float|null
     */
    protected function to_float_or_null(string $value): ?float {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $normalized = str_replace(',', '.', $value);
        if (!is_numeric($normalized)) {
            return null;
        }

        return (float)$normalized;
    }

    /**
     * Gets SCORM package name by id.
     *
     * @param int $scormid
     * @return string
     */
    protected function get_scorm_name(int $scormid): string {
        global $DB;

        if (!$scormid) {
            return '';
        }

        $name = $DB->get_field('scorm', 'name', ['id' => $scormid]);
        return $name ? format_string($name) : '';
    }

    /**
     * Returns a human readable module type name.
     *
     * @param string $modname
     * @return string
     */
    protected function get_modtype_human_name(string $modname): string {
        $map = [
            'assign' => 'tarea',
            'quiz' => 'cuestionario',
            'forum' => 'foro',
            'chat' => 'chat',
            'scorm' => 'SCORM',
            'customcert' => 'diploma personalizado',
            'certificate' => 'certificado',
            'simplecertificate' => 'certificado',
        ];
        return $map[$modname] ?? $modname;
    }

}
