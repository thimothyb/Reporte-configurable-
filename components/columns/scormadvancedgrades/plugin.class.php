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
     * Cache for resolved stat options by course/stat/column tuple.
     *
     * @var array<string,string>
     */
    protected array $resolvedstatcache = [];

    /**
     * Cache for ordered assignment instance ids used as "ACTIVIDAD N" reference list.
     *
     * @var array<string,array<int>>
     */
    protected array $referenceassigninstancescache = [];

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
        if (!empty($data->columname)) {
            return format_string((string)$data->columname);
        }

        $columnname = $this->fullname;

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

        $effectivecourseid = $this->resolve_effective_courseid((int)$courseid);
        $columnname = !empty($data->columname) ? (string)$data->columname : '';
        $format = !empty($data->format) ? (string)$data->format : self::FORMAT_AUTO;

        // New path: dynamic statistics selector.
        if (!empty($data->stat)) {
            $statoption = (string)$data->stat;
            $cachekey = $effectivecourseid . '|' . $statoption . '|' . $columnname;
            if (!isset($this->resolvedstatcache[$cachekey])) {
                $this->resolvedstatcache[$cachekey] = $this->resolve_stat_option_for_course(
                    $statoption,
                    $effectivecourseid,
                    $columnname
                );
            }
            $resolvedstat = $this->resolvedstatcache[$cachekey];
            $result = $this->execute_stat_option($resolvedstat, $userid, $effectivecourseid);
            $formatted = $this->format_stat_result($result['value'], $result['type'], $format);
            if ($formatted !== '') {
                return $formatted;
            }

            // Legacy safety net for imported columns ("NOTA ACTIVIDAD ...", "FECHA DE ENTREGA ...").
            $fallback = $this->try_assignment_metric_by_column_name(
                $userid,
                $effectivecourseid,
                $columnname
            );
            if ($fallback !== null) {
                return $this->format_stat_result($fallback['value'], $fallback['type'], $format);
            }

            return '';
        }

        // Backward compatibility: score of a selected SCORM package.
        if (!empty($data->scormid)) {
            $rawid = (int)$data->scormid;

            // First, attempt legacy assignment interpretation for old imported columns.
            $fallback = $this->try_assignment_metric_by_column_name(
                $userid,
                $effectivecourseid,
                $columnname,
                $rawid
            );
            if ($fallback !== null) {
                return $this->format_stat_result($fallback['value'], $fallback['type'], $format);
            }

            $scormid = $effectivecourseid > 0
                ? $this->resolve_module_instance_id($effectivecourseid, 'scorm', $rawid)
                : $rawid;
            $result = $this->execute_scorm_metric($userid, $scormid, 'score');
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
                case 'quiz':
                    $options['quiz:' . $instanceid . ':completiondate'] = 'Fecha de realizacion cuestionario "' . $activityname . '"';
                    $options['quiz:' . $instanceid . ':score'] = 'Puntuacion cuestionario "' . $activityname . '"';
                    $options['quiz:' . $instanceid . ':dedicationtime'] = 'Tiempo de dedicacion cuestionario "' . $activityname . '"';
                    $options['quiz:' . $instanceid . ':opendate'] = 'Fecha de apertura cuestionario "' . $activityname . '"';
                    $options['quiz:' . $instanceid . ':firstpassattempt'] = 'Primer intento aprobado cuestionario "' . $activityname . '"';
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
                    $options['scorm:' . $instanceid . ':completiondate'] = 'Fecha de finalización SCORM "' . $activityname . '"';
                    $options['scorm:' . $instanceid . ':scocompleted'] = 'Objetos SCO completados SCORM "' . $activityname . '"';
                    $options['scorm:' . $instanceid . ':dedicationtime'] = 'Tiempo de dedicación SCORM "' . $activityname . '"';
                    $options['scorm:' . $instanceid . ':lastaccess'] = 'Último acceso SCORM "' . $activityname . '"';
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
            if (!isset($gradeitemindex[$gradekey]) || in_array($modname, ['assign', 'quiz', 'scorm'], true)) {
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
            $modname = (string)$parts[1];
            $instanceid = $this->resolve_module_instance_id($courseid, $modname, (int)$parts[2]);
            return $this->execute_generic_grade_metric($userid, $courseid, $modname, $instanceid);
        }

        // Standard key format: modname:instanceid:metric.
        if (count($parts) < 3) {
            return ['value' => null, 'type' => self::TYPE_TEXT];
        }

        $modname = (string)$parts[0];
        $instanceid = $this->resolve_module_instance_id($courseid, $modname, (int)$parts[1]);
        $metric = (string)$parts[2];

        switch ($modname) {
            case 'scorm':
                return $this->execute_scorm_metric($userid, $instanceid, $metric);
            case 'assign':
                return $this->execute_assign_metric($userid, $courseid, $instanceid, $metric);
            case 'quiz':
                return $this->execute_quiz_metric($userid, $courseid, $instanceid, $metric);
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
     * Resolves legacy stat references (old instance IDs / cmids) for current course.
     * Falls back to column-name activity matching when needed.
     *
     * @param string $statoption
     * @param int $courseid
     * @param string $columnname
     * @return string
     */
    protected function resolve_stat_option_for_course(string $statoption, int $courseid, string $columnname = ''): string {
        $parts = explode(':', $statoption);
        if (empty($parts[0])) {
            return $statoption;
        }

        $isgenericgrade = ($parts[0] === 'grade' && count($parts) >= 3);
        if (!$isgenericgrade && count($parts) < 3) {
            return $statoption;
        }

        if ($isgenericgrade) {
            $modname = (string)$parts[1];
            $rawid = (int)$parts[2];
            $metric = '';
        } else {
            $modname = (string)$parts[0];
            $rawid = (int)$parts[1];
            $metric = (string)$parts[2];
        }

        if ($courseid <= 0 || $rawid <= 0 || $modname === '') {
            return $statoption;
        }

        $resolvedid = $this->resolve_module_instance_id($courseid, $modname, $rawid);
        $activityname = $this->extract_activity_name_from_column_name($columnname);
        $activityindex = $this->extract_activity_index_from_column_name($columnname);
        $bynameid = 0;
        if ($activityname !== '') {
            $bynameid = $this->find_module_instance_id_by_name($courseid, $modname, $activityname);
        }
        $byindexid = 0;
        if ($activityindex > 0) {
            $byindexid = $this->find_module_instance_id_by_position($courseid, $modname, $activityindex);
        }

        // If id is invalid in current course, recover by activity name in column title.
        if (!$this->is_valid_module_instance_in_course($courseid, $modname, $resolvedid)) {
            if ($bynameid > 0) {
                $resolvedid = $bynameid;
            } else if ($byindexid > 0) {
                $resolvedid = $byindexid;
            }
        } else if (
            $byindexid > 0 &&
            $byindexid !== $resolvedid &&
            !$this->module_instance_matches_activity_position($courseid, $modname, $resolvedid, $activityindex)
        ) {
            // Prefer explicit "ACTIVIDAD N" mapping when present.
            $resolvedid = $byindexid;
        } else if (
            $bynameid > 0 &&
            $bynameid !== $resolvedid &&
            !$this->module_instance_matches_activity_name($courseid, $modname, $resolvedid, $activityname)
        ) {
            // Legacy imports may point to a valid but wrong instance; prefer the one matching column activity name.
            $resolvedid = $bynameid;
        }

        if ($resolvedid <= 0 || $resolvedid === $rawid) {
            return $statoption;
        }

        if ($isgenericgrade) {
            return 'grade:' . $modname . ':' . $resolvedid;
        }

        return $modname . ':' . $resolvedid . ':' . $metric;
    }

    /**
     * Checks whether a module instance name matches the activity label from column title.
     *
     * @param int $courseid
     * @param string $modname
     * @param int $instanceid
     * @param string $activityname
     * @return bool
     */
    protected function module_instance_matches_activity_name(
        int $courseid,
        string $modname,
        int $instanceid,
        string $activityname
    ): bool {
        if ($courseid <= 0 || $instanceid <= 0 || $modname === '' || trim($activityname) === '') {
            return false;
        }

        try {
            $modinfo = get_fast_modinfo($courseid);
        } catch (Throwable $t) {
            return false;
        }

        $target = $this->normalize_activity_label($activityname);
        if ($target === '') {
            return false;
        }

        foreach ($modinfo->get_cms() as $cm) {
            if (!empty($cm->deletioninprogress) || (string)$cm->modname !== $modname) {
                continue;
            }
            if ((int)$cm->instance !== $instanceid) {
                continue;
            }

            $cmname = $this->normalize_activity_label((string)$cm->name);
            if ($cmname === '') {
                return false;
            }

            return $cmname === $target || strpos($cmname, $target) !== false || strpos($target, $cmname) !== false;
        }

        return false;
    }

    /**
     * Checks whether a module instance is the N-th activity of its type in the course.
     *
     * @param int $courseid
     * @param string $modname
     * @param int $instanceid
     * @param int $activityindex
     * @return bool
     */
    protected function module_instance_matches_activity_position(
        int $courseid,
        string $modname,
        int $instanceid,
        int $activityindex
    ): bool {
        if ($activityindex <= 0) {
            return false;
        }

        $candidate = $this->find_module_instance_id_by_position($courseid, $modname, $activityindex);
        return $candidate > 0 && $candidate === $instanceid;
    }

    /**
     * Resolves a module identifier that might be stored as cmid or instanceid.
     * Returns a valid instanceid for the current course when possible.
     *
     * @param int $courseid
     * @param string $modname
     * @param int $rawid
     * @return int
     */
    protected function resolve_module_instance_id(int $courseid, string $modname, int $rawid): int {
        global $DB;

        if ($courseid <= 0 || $rawid <= 0 || $modname === '') {
            return $rawid;
        }

        // If already a valid instance id for this course/module, keep it.
        $existsql = "SELECT 1
                       FROM {course_modules} cm
                       JOIN {modules} m ON m.id = cm.module
                      WHERE cm.course = :courseid
                        AND m.name = :modname
                        AND cm.instance = :instanceid";
        if ($DB->record_exists_sql($existsql, [
            'courseid' => $courseid,
            'modname' => $modname,
            'instanceid' => $rawid,
        ])) {
            return $rawid;
        }

        // Fallback: treat raw id as course module id (cmid).
        $cmidsql = "SELECT cm.instance
                      FROM {course_modules} cm
                      JOIN {modules} m ON m.id = cm.module
                     WHERE cm.id = :cmid
                       AND cm.course = :courseid
                       AND m.name = :modname";
        $instanceid = $DB->get_field_sql($cmidsql, [
            'cmid' => $rawid,
            'courseid' => $courseid,
            'modname' => $modname,
        ]);
        if ($instanceid !== false && $instanceid !== null && (int)$instanceid > 0) {
            return (int)$instanceid;
        }

        return $rawid;
    }

    /**
     * Checks whether module instance exists in current course.
     *
     * @param int $courseid
     * @param string $modname
     * @param int $instanceid
     * @return bool
     */
    protected function is_valid_module_instance_in_course(int $courseid, string $modname, int $instanceid): bool {
        global $DB;

        if ($courseid <= 0 || $instanceid <= 0 || $modname === '') {
            return false;
        }

        $sql = "SELECT 1
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                 WHERE cm.course = :courseid
                   AND m.name = :modname
                   AND cm.instance = :instanceid";
        return $DB->record_exists_sql($sql, [
            'courseid' => $courseid,
            'modname' => $modname,
            'instanceid' => $instanceid,
        ]);
    }

    /**
     * Tries to extract activity name from column title patterns.
     *
     * @param string $columnname
     * @return string
     */
    protected function extract_activity_name_from_column_name(string $columnname): string {
        $label = trim(strip_tags($columnname));
        if ($label === '') {
            return '';
        }

        if (preg_match('/ACTIVIDAD\\s+\\d+\\s*[\\.\\:\\-]\\s*(.+)$/iu', $label, $matches)) {
            return trim((string)$matches[1]);
        }

        if (preg_match('/ACTIVIDAD\\s+\\d+\\s+(.+)$/iu', $label, $matches)) {
            return trim((string)$matches[1]);
        }

        return $label;
    }

    /**
     * Extracts "ACTIVIDAD N" index from column title.
     *
     * @param string $columnname
     * @return int
     */
    protected function extract_activity_index_from_column_name(string $columnname): int {
        $label = trim(strip_tags($columnname));
        if ($label === '') {
            return 0;
        }

        if (preg_match('/ACTIVIDAD\\s+(\\d+)/iu', $label, $matches)) {
            return (int)$matches[1];
        }

        return 0;
    }

    /**
     * Finds module instance by activity name in a course.
     *
     * @param int $courseid
     * @param string $modname
     * @param string $activityname
     * @return int
     */
    protected function find_module_instance_id_by_name(int $courseid, string $modname, string $activityname): int {
        if ($courseid <= 0 || $modname === '' || trim($activityname) === '') {
            return 0;
        }

        $target = $this->normalize_activity_label($activityname);
        if ($target === '') {
            return 0;
        }

        try {
            $modinfo = get_fast_modinfo($courseid);
        } catch (Throwable $t) {
            return 0;
        }

        $allowedinstances = [];
        if ($modname === 'assign') {
            $referenceinstances = $this->get_reference_assign_instance_ids($courseid);
            if (!empty($referenceinstances)) {
                $allowedinstances = array_fill_keys($referenceinstances, true);
            }
        }

        $partialmatch = 0;
        foreach ($modinfo->get_cms() as $cm) {
            if (!empty($cm->deletioninprogress) || (string)$cm->modname !== $modname) {
                continue;
            }
            if (!empty($allowedinstances) && !isset($allowedinstances[(int)$cm->instance])) {
                continue;
            }

            $cmname = $this->normalize_activity_label((string)$cm->name);
            if ($cmname === '') {
                continue;
            }

            if ($cmname === $target) {
                return (int)$cm->instance;
            }

            if ($partialmatch <= 0 && (strpos($cmname, $target) !== false || strpos($target, $cmname) !== false)) {
                $partialmatch = (int)$cm->instance;
            }
        }

        return $partialmatch;
    }

    /**
     * Finds module instance id by visible order position in course sections.
     *
     * @param int $courseid
     * @param string $modname
     * @param int $position
     * @return int
     */
    protected function find_module_instance_id_by_position(int $courseid, string $modname, int $position): int {
        if ($courseid <= 0 || $modname === '' || $position <= 0) {
            return 0;
        }

        if ($modname === 'assign') {
            $referenceinstances = $this->get_reference_assign_instance_ids($courseid);
            if (!empty($referenceinstances)) {
                if (count($referenceinstances) < $position) {
                    return 0;
                }
                return (int)$referenceinstances[$position - 1];
            }
        }

        try {
            $modinfo = get_fast_modinfo($courseid);
        } catch (Throwable $t) {
            return 0;
        }

        $orderedinstances = [];
        foreach ($modinfo->get_sections() as $sectioncmids) {
            foreach ($sectioncmids as $cmid) {
                if (empty($modinfo->cms[$cmid])) {
                    continue;
                }
                $cm = $modinfo->cms[$cmid];
                if (!empty($cm->deletioninprogress) || (string)$cm->modname !== $modname) {
                    continue;
                }
                $orderedinstances[] = (int)$cm->instance;
            }
        }

        if (count($orderedinstances) < $position) {
            return 0;
        }

        return (int)$orderedinstances[$position - 1];
    }

    /**
     * Gets ordered assignment instance ids used by "ACTIVIDAD N" mapping.
     * Priority:
     * 1) filter_userstatsadvanced_selectedcmids from current request.
     * 2) selectedcmids from linked "Actividades de aprendizaje" column that points to this report.
     * 3) all visible assignments in course.
     *
     * @param int $courseid
     * @return array<int>
     */
    protected function get_reference_assign_instance_ids(int $courseid): array {
        global $DB;

        if ($courseid <= 0) {
            return [];
        }

        $currentreportid = optional_param('id', 0, PARAM_INT);
        $requestselectedraw = optional_param('filter_userstatsadvanced_selectedcmids', '', PARAM_RAW_TRIMMED);
        $cachekey = $courseid . '|' . $currentreportid . '|' . trim($requestselectedraw);
        if (isset($this->referenceassigninstancescache[$cachekey])) {
            return $this->referenceassigninstancescache[$cachekey];
        }

        $selectedcmids = $this->parse_selected_cmids_csv($requestselectedraw);
        if (empty($selectedcmids)) {
            $selectedcmids = $this->resolve_selected_cmids_from_linked_learning_activities_column($courseid, $currentreportid);
        }

        $params = [
            'courseid' => $courseid,
            'modname' => 'assign',
        ];
        $selectedwhere = '';
        if (!empty($selectedcmids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($selectedcmids, SQL_PARAMS_NAMED, 'selcmid');
            $selectedwhere = " AND cm.id $insql";
            $params = array_merge($params, $inparams);
        }

        $sql = "SELECT cm.instance
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                 WHERE cm.course = :courseid
                   AND cm.visible = 1
                   AND m.name = :modname
                   $selectedwhere
              ORDER BY cm.section ASC, cm.added ASC, cm.id ASC";
        $records = $DB->get_records_sql($sql, $params);

        $instances = [];
        foreach ($records as $record) {
            $instanceid = (int)$record->instance;
            if ($instanceid > 0) {
                $instances[] = $instanceid;
            }
        }

        $this->referenceassigninstancescache[$cachekey] = $instances;
        return $instances;
    }

    /**
     * Resolves selected cmids from linked userstatsadvanced "actividades_aprendizaje" column.
     *
     * @param int $courseid
     * @param int $targetreportid
     * @return array<int>
     */
    protected function resolve_selected_cmids_from_linked_learning_activities_column(int $courseid, int $targetreportid): array {
        global $DB, $CFG;

        if ($courseid <= 0 || $targetreportid <= 0) {
            return [];
        }

        if (!function_exists('cr_unserialize')) {
            require_once($CFG->dirroot . '/blocks/configurable_reports/locallib.php');
        }

        $reports = $DB->get_records_select(
            'block_configurable_reports',
            '(courseid = :courseid OR global = 1)',
            ['courseid' => $courseid],
            '',
            'id,courseid,global,components'
        );

        foreach ($reports as $report) {
            try {
                $components = cr_unserialize((string)$report->components);
            } catch (Throwable $t) {
                continue;
            }
            if (!is_array($components)) {
                continue;
            }

            $columnselements = $components['columns']['elements'] ?? [];
            if (!is_array($columnselements)) {
                continue;
            }

            foreach ($columnselements as $column) {
                $pluginname = $column['pluginname'] ?? '';
                if (is_array($pluginname)) {
                    $pluginname = reset($pluginname);
                }
                if ((string)$pluginname !== 'userstatsadvanced') {
                    continue;
                }

                $formdata = $column['formdata'] ?? null;
                if (is_array($formdata)) {
                    $formdata = (object)$formdata;
                }
                if (!is_object($formdata)) {
                    continue;
                }

                if (empty($formdata->stat_type) || (string)$formdata->stat_type !== 'actividades_aprendizaje') {
                    continue;
                }

                $modalreportid = !empty($formdata->modalreportid) ? (int)$formdata->modalreportid : 0;
                if ($modalreportid !== $targetreportid) {
                    continue;
                }

                $selectedraw = !empty($formdata->selectedcmids) ? (string)$formdata->selectedcmids : '';
                $selectedcmids = $this->parse_selected_cmids_csv($selectedraw);
                if (!empty($selectedcmids)) {
                    return $selectedcmids;
                }
            }
        }

        return [];
    }

    /**
     * Parses csv/whitespace list of selected course-module ids.
     *
     * @param string $selectedcmidsraw
     * @return array<int>
     */
    protected function parse_selected_cmids_csv(string $selectedcmidsraw): array {
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
     * Normalizes activity labels for loose matching.
     *
     * @param string $label
     * @return string
     */
    protected function normalize_activity_label(string $label): string {
        $label = core_text::strtolower(trim($label));
        if ($label === '') {
            return '';
        }

        // Keep letters/numbers/spaces (unicode-safe), then collapse spaces.
        $label = preg_replace('/[^\\p{L}\\p{N}\\s]+/u', ' ', $label);
        $label = preg_replace('/\\s+/u', ' ', (string)$label);
        return trim((string)$label);
    }

    /**
     * Resolves effective course id for execution context.
     *
     * @param int $courseid
     * @return int
     */
    protected function resolve_effective_courseid(int $courseid): int {
        if ($courseid > 0) {
            return $courseid;
        }

        if (!empty($this->report->courseid)) {
            return (int)$this->report->courseid;
        }

        $requestcourseid = optional_param('courseid', 0, PARAM_INT);
        return $requestcourseid > 0 ? $requestcourseid : 0;
    }

    /**
     * Tries to compute an assignment metric from legacy column naming.
     *
     * @param int $userid
     * @param int $courseid
     * @param string $columnname
     * @param int $rawid optional legacy id (instance/cmid).
     * @return array{value:mixed,type:string}|null
     */
    protected function try_assignment_metric_by_column_name(
        int $userid,
        int $courseid,
        string $columnname,
        int $rawid = 0
    ): ?array {
        if ($userid <= 0 || $courseid <= 0 || trim($columnname) === '') {
            return null;
        }

        $metric = $this->infer_assignment_metric_from_column_name($columnname);
        if ($metric === '') {
            return null;
        }

        $assignid = 0;
        $activityname = $this->extract_activity_name_from_column_name($columnname);
        $activityindex = $this->extract_activity_index_from_column_name($columnname);
        if ($activityindex > 0) {
            $assignid = $this->find_module_instance_id_by_position($courseid, 'assign', $activityindex);
        }

        if ($activityname !== '') {
            $bynameid = $this->find_module_instance_id_by_name($courseid, 'assign', $activityname);
            if ($bynameid > 0) {
                $assignid = $bynameid;
            }
        }

        if ($assignid <= 0 && $rawid > 0) {
            $resolved = $this->resolve_module_instance_id($courseid, 'assign', $rawid);
            if ($this->is_valid_module_instance_in_course($courseid, 'assign', $resolved)) {
                $assignid = $resolved;
            }
        }

        if ($assignid <= 0) {
            return null;
        }

        return $this->execute_assign_metric($userid, $courseid, $assignid, $metric);
    }

    /**
     * Infers assign metric key from column title.
     *
     * @param string $columnname
     * @return string
     */
    protected function infer_assignment_metric_from_column_name(string $columnname): string {
        $label = $this->normalize_metric_label($columnname);
        if ($label === '') {
            return '';
        }

        if (strpos($label, 'fecha de entrega') !== false) {
            return 'submissiondate';
        }

        if (strpos($label, 'fecha de correccion') !== false || strpos($label, 'fecha de calificacion') !== false) {
            return 'gradedate';
        }

        if (preg_match('/\\bnota\\b|\\bpuntuacion\\b|\\bcalificacion\\b/u', $label)) {
            return 'score';
        }

        return '';
    }

    /**
     * Normalizes labels for metric detection using accent-insensitive matching.
     *
     * @param string $label
     * @return string
     */
    protected function normalize_metric_label(string $label): string {
        $label = core_text::strtolower(trim(strip_tags($label)));
        if ($label === '') {
            return '';
        }

        $label = strtr($label, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);
        $label = preg_replace('/\\s+/u', ' ', (string)$label);
        return trim((string)$label);
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
        global $DB;

        static $tracktableexists = null;
        if ($tracktableexists === null) {
            $tracktableexists = $DB->get_manager()->table_exists('scorm_scoes_track');
        }
        if (!$tracktableexists) {
            return ['value' => null, 'type' => self::TYPE_TEXT];
        }

        if ($metric === 'lastaccess') {
            $ts = $DB->get_field_sql(
                "SELECT MAX(st.timemodified) FROM {scorm_scoes_track} st
                  WHERE st.userid = :userid AND st.scormid = :scormid",
                ['userid' => $userid, 'scormid' => $scormid]
            );
            return ['value' => $ts ? (int)$ts : null, 'type' => self::TYPE_DATETIME];
        }

        if ($metric === 'completiondate') {
            $attempt = $this->get_scorm_max_attempt($userid, $scormid);
            if ($attempt === null) {
                return ['value' => null, 'type' => self::TYPE_DATETIME];
            }
            $ts = $DB->get_field_sql(
                "SELECT MAX(st.timemodified)
                   FROM {scorm_scoes_track} st
                  WHERE st.userid = :userid
                    AND st.scormid = :scormid
                    AND st.attempt = :attempt
                    AND (st.element = :elcomp OR st.element = :elcomp2)
                    AND (st.value = :valcomp OR st.value = :valcomp2)",
                ['userid' => $userid, 'scormid' => $scormid, 'attempt' => $attempt,
                 'elcomp' => 'cmi.completion_status', 'elcomp2' => 'cmi.core.lesson_status',
                 'valcomp' => 'completed', 'valcomp2' => 'passed']
            );
            return ['value' => $ts ? (int)$ts : null, 'type' => self::TYPE_DATETIME];
        }

        if ($metric === 'scocompleted') {
            $attempt = $this->get_scorm_max_attempt($userid, $scormid);
            if ($attempt === null) {
                return ['value' => 0, 'type' => self::TYPE_NUMBER];
            }
            $count = $DB->get_field_sql(
                "SELECT COUNT(DISTINCT st.scoid)
                   FROM {scorm_scoes_track} st
                  WHERE st.userid = :userid
                    AND st.scormid = :scormid
                    AND st.attempt = :attempt
                    AND (st.element = :elsc OR st.element = :elsc2)
                    AND (st.value = :valsc OR st.value = :valsc2)",
                ['userid' => $userid, 'scormid' => $scormid, 'attempt' => $attempt,
                 'elsc' => 'cmi.completion_status', 'elsc2' => 'cmi.core.lesson_status',
                 'valsc' => 'completed', 'valsc2' => 'passed']
            );
            return ['value' => (int)$count, 'type' => self::TYPE_NUMBER];
        }

        if ($metric === 'dedicationtime') {
            $attempt = $this->get_scorm_max_attempt($userid, $scormid);
            if ($attempt === null) {
                return ['value' => null, 'type' => self::TYPE_TEXT];
            }
            $timerows = $DB->get_records_sql(
                "SELECT st.id, st.value
                   FROM {scorm_scoes_track} st
                  WHERE st.userid = :userid
                    AND st.scormid = :scormid
                    AND st.attempt = :attempt
                    AND (st.element = :eldt OR st.element = :eldt2)",
                ['userid' => $userid, 'scormid' => $scormid, 'attempt' => $attempt,
                 'eldt' => 'cmi.core.total_time', 'eldt2' => 'cmi.total_time']
            );
            $totalseconds = 0;
            foreach ($timerows as $row) {
                $totalseconds += $this->parse_scorm_time_to_seconds((string)$row->value);
            }
            if ($totalseconds === 0) {
                return ['value' => null, 'type' => self::TYPE_TEXT];
            }
            $h = (int)floor($totalseconds / 3600);
            $m = (int)floor(($totalseconds % 3600) / 60);
            $s = (int)($totalseconds % 60);
            return ['value' => sprintf('%02d:%02d:%02d', $h, $m, $s), 'type' => self::TYPE_TEXT];
        }

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
                       a.duedate,
                       ag.id AS assigngradeid,
                       ag.grade AS assigngrade,
                       ag.timemodified AS gradedate,
                       ag.grader
                  FROM {grade_items} gi
             LEFT JOIN {assign} a ON a.id = gi.iteminstance
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

        $duedate = null;
        if (!empty($graderec->duedate)) {
            $duedate = (int)$graderec->duedate;
        } else {
            $rawduedate = $DB->get_field('assign', 'duedate', ['id' => $assignid], IGNORE_MISSING);
            if ($rawduedate !== false && $rawduedate !== null && (int)$rawduedate > 0) {
                $duedate = (int)$rawduedate;
            }
        }

        switch ($metric) {
            case 'gradedate':
                $date = !empty($graderec->gradedate) ? (int)$graderec->gradedate : null;
                if ($date === null && !empty($submission->timemodified)) {
                    $date = (int)$submission->timemodified;
                }
                if ($date === null && !empty($submission->timecreated)) {
                    $date = (int)$submission->timecreated;
                }
                if ($date === null && $duedate !== null) {
                    $date = $duedate;
                }
                return ['value' => $date, 'type' => self::TYPE_DATETIME];
            case 'submissiondate':
                $date = null;
                if (!empty($submission->timemodified)) {
                    $date = (int)$submission->timemodified;
                } else if (!empty($submission->timecreated)) {
                    $date = (int)$submission->timecreated;
                } else if (!empty($graderec->gradedate)) {
                    $date = (int)$graderec->gradedate;
                } else if ($duedate !== null) {
                    $date = $duedate;
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
                if (empty($graderec)) {
                    return ['value' => 0.0, 'type' => self::TYPE_PERCENT];
                }
                $rawscore = null;
                if ($graderec->finalgrade !== null) {
                    $rawscore = (float)$graderec->finalgrade;
                } else if ($graderec->assigngrade !== null && (float)$graderec->assigngrade >= 0) {
                    $rawscore = (float)$graderec->assigngrade;
                }

                if ($rawscore === null) {
                    return ['value' => 0.0, 'type' => self::TYPE_PERCENT];
                }

                $score = $rawscore;
                if (
                    isset($graderec->grademax, $graderec->grademin) &&
                    $graderec->grademax !== null &&
                    $graderec->grademin !== null &&
                    (float)$graderec->grademax > (float)$graderec->grademin
                ) {
                    $score = (($rawscore - (float)$graderec->grademin) /
                        ((float)$graderec->grademax - (float)$graderec->grademin)) * 100.0;
                }

                $score = max(0.0, min(100.0, $score));
                return ['value' => $score, 'type' => self::TYPE_PERCENT];
        }
    }

    /**
     * Executes quiz metrics.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $quizid
     * @param string $metric
     * @return array{value:mixed,type:string}
     */
    protected function execute_quiz_metric(int $userid, int $courseid, int $quizid, string $metric): array {
        global $DB;

        $quiz = $DB->get_record('quiz', ['id' => $quizid], 'id,course,timeopen,grade,sumgrades', IGNORE_MISSING);
        if (empty($quiz) || (int)$quiz->course !== (int)$courseid) {
            return ['value' => null, 'type' => self::TYPE_TEXT];
        }

        $gradeinfo = $DB->get_record_sql(
            "SELECT gi.id AS gradeitemid,
                    gi.grademin,
                    gi.grademax,
                    gi.gradepass,
                    gg.finalgrade
               FROM {grade_items} gi
          LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = :userid
              WHERE gi.courseid = :courseid
                AND gi.itemtype = 'mod'
                AND gi.itemmodule = 'quiz'
                AND gi.iteminstance = :quizid",
            [
                'userid' => $userid,
                'courseid' => $courseid,
                'quizid' => $quizid,
            ]
        );

        switch ($metric) {
            case 'completiondate':
                $cmid = $DB->get_field_sql(
                    "SELECT cm.id
                       FROM {course_modules} cm
                       JOIN {modules} m ON m.id = cm.module
                      WHERE cm.course = :courseid
                        AND cm.instance = :quizid
                        AND m.name = :modname",
                    [
                        'courseid' => $courseid,
                        'quizid' => $quizid,
                        'modname' => 'quiz',
                    ]
                );

                $completiondate = null;
                if (!empty($cmid)) {
                    $completiondate = $DB->get_field_sql(
                        "SELECT MAX(cmc.timemodified)
                           FROM {course_modules_completion} cmc
                          WHERE cmc.coursemoduleid = :cmid
                            AND cmc.userid = :userid
                            AND cmc.completionstate > 0",
                        [
                            'cmid' => (int)$cmid,
                            'userid' => $userid,
                        ]
                    );
                }

                if (empty($completiondate)) {
                    $completiondate = $DB->get_field_sql(
                        "SELECT MAX(qa.timefinish)
                           FROM {quiz_attempts} qa
                          WHERE qa.quiz = :quizid
                            AND qa.userid = :userid
                            AND qa.timefinish > 0",
                        [
                            'quizid' => $quizid,
                            'userid' => $userid,
                        ]
                    );
                }

                return [
                    'value' => !empty($completiondate) ? (int)$completiondate : null,
                    'type' => self::TYPE_DATETIME,
                ];

            case 'dedicationtime':
                $totalseconds = $DB->get_field_sql(
                    "SELECT COALESCE(SUM(CASE
                                WHEN qa.timefinish > qa.timestart THEN qa.timefinish - qa.timestart
                                ELSE 0
                            END), 0)
                       FROM {quiz_attempts} qa
                      WHERE qa.quiz = :quizid
                        AND qa.userid = :userid
                        AND qa.timefinish > 0",
                    [
                        'quizid' => $quizid,
                        'userid' => $userid,
                    ]
                );
                $totalseconds = max(0, (int)$totalseconds);
                return [
                    'value' => $this->format_duration_hms($totalseconds),
                    'type' => self::TYPE_TEXT,
                ];

            case 'opendate':
                return [
                    'value' => !empty($quiz->timeopen) ? (int)$quiz->timeopen : null,
                    'type' => self::TYPE_DATETIME,
                ];

            case 'firstpassattempt':
                if (empty($gradeinfo) || !isset($gradeinfo->gradepass) || (float)$gradeinfo->gradepass <= 0.0) {
                    return ['value' => null, 'type' => self::TYPE_NUMBER];
                }

                $attempts = $DB->get_records_sql(
                    "SELECT qa.attempt,
                            qa.sumgrades
                       FROM {quiz_attempts} qa
                      WHERE qa.quiz = :quizid
                        AND qa.userid = :userid
                        AND qa.state = :state
                        AND qa.sumgrades IS NOT NULL
                   ORDER BY qa.attempt ASC, qa.timefinish ASC, qa.id ASC",
                    [
                        'quizid' => $quizid,
                        'userid' => $userid,
                        'state' => 'finished',
                    ]
                );

                $quizsumgrades = isset($quiz->sumgrades) ? (float)$quiz->sumgrades : 0.0;
                $quizmaxgrade = isset($quiz->grade) ? (float)$quiz->grade : 0.0;
                $requiredpass = (float)$gradeinfo->gradepass;

                foreach ($attempts as $attempt) {
                    if ($attempt->sumgrades === null) {
                        continue;
                    }
                    $scaledgrade = $this->scale_quiz_attempt_to_grade((float)$attempt->sumgrades, $quizsumgrades, $quizmaxgrade);
                    if ($scaledgrade >= $requiredpass) {
                        return ['value' => (int)$attempt->attempt, 'type' => self::TYPE_NUMBER];
                    }
                }

                return ['value' => null, 'type' => self::TYPE_NUMBER];

            case 'score':
            default:
                $rawscore = null;
                if (!empty($gradeinfo) && $gradeinfo->finalgrade !== null) {
                    $rawscore = (float)$gradeinfo->finalgrade;
                } else {
                    $lastattemptsumgrades = $DB->get_field_sql(
                        "SELECT qa.sumgrades
                           FROM {quiz_attempts} qa
                          WHERE qa.quiz = :quizid
                            AND qa.userid = :userid
                            AND qa.sumgrades IS NOT NULL
                       ORDER BY qa.attempt DESC, qa.timefinish DESC, qa.id DESC",
                        [
                            'quizid' => $quizid,
                            'userid' => $userid,
                        ],
                        IGNORE_MULTIPLE
                    );
                    if ($lastattemptsumgrades !== false && $lastattemptsumgrades !== null) {
                        $rawscore = $this->scale_quiz_attempt_to_grade(
                            (float)$lastattemptsumgrades,
                            (float)($quiz->sumgrades ?? 0),
                            (float)($quiz->grade ?? 0)
                        );
                    }
                }

                if ($rawscore === null) {
                    return ['value' => 0.0, 'type' => self::TYPE_PERCENT];
                }

                $scorepercent = $rawscore;
                if (
                    !empty($gradeinfo) &&
                    isset($gradeinfo->grademin, $gradeinfo->grademax) &&
                    $gradeinfo->grademin !== null &&
                    $gradeinfo->grademax !== null &&
                    (float)$gradeinfo->grademax > (float)$gradeinfo->grademin
                ) {
                    $scorepercent = (($rawscore - (float)$gradeinfo->grademin) /
                        ((float)$gradeinfo->grademax - (float)$gradeinfo->grademin)) * 100.0;
                } else if (!empty($quiz->grade) && (float)$quiz->grade > 0.0) {
                    $scorepercent = ($rawscore / (float)$quiz->grade) * 100.0;
                }

                $scorepercent = max(0.0, min(100.0, $scorepercent));
                return ['value' => $scorepercent, 'type' => self::TYPE_PERCENT];
        }
    }

    /**
     * Converts quiz attempt raw sumgrades to the grade scale configured in quiz.
     *
     * @param float $sumgrades
     * @param float $quizsumgrades
     * @param float $quizmaxgrade
     * @return float
     */
    protected function scale_quiz_attempt_to_grade(float $sumgrades, float $quizsumgrades, float $quizmaxgrade): float {
        if ($quizsumgrades > 0 && $quizmaxgrade > 0) {
            return ($sumgrades / $quizsumgrades) * $quizmaxgrade;
        }
        return $sumgrades;
    }

    /**
     * Formats duration seconds as HH:MM:SS.
     *
     * @param int $seconds
     * @return string
     */
    protected function format_duration_hms(int $seconds): string {
        $seconds = max(0, $seconds);
        $hours = (int)floor($seconds / 3600);
        $minutes = (int)floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
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

        if ($chatid <= 0) {
            return ['value' => null, 'type' => self::TYPE_TEXT];
        }

        $sql = "SELECT COUNT(cm.id) AS totalmessages,
                       MIN(cm.timestamp) AS firstmessage,
                       MAX(cm.timestamp) AS lastmessage
                  FROM {chat_messages} cm
                 WHERE cm.chatid = :chatid
                   AND cm.userid = :userid
                   AND cm.issystem = 0";
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
     * Returns the max attempt number for a user/scorm pair, or null if none.
     *
     * @param int $userid
     * @param int $scormid
     * @return int|null
     */
    protected function get_scorm_max_attempt(int $userid, int $scormid): ?int {
        global $DB;
        $attempt = $DB->get_field_sql(
            "SELECT MAX(st.attempt) FROM {scorm_scoes_track} st WHERE st.userid = :userid AND st.scormid = :scormid",
            ['userid' => $userid, 'scormid' => $scormid]
        );
        return ($attempt === false || $attempt === null) ? null : (int)$attempt;
    }

    /**
     * Parses a SCORM time string (SCORM 1.2 HH:MM:SS.ss or SCORM 2004 PTxHxMxS) to integer seconds.
     *
     * @param string $timestr
     * @return int
     */
    protected function parse_scorm_time_to_seconds(string $timestr): int {
        $timestr = trim($timestr);
        if ($timestr === '') {
            return 0;
        }
        // SCORM 2004: PT#H#M#S
        if (preg_match('/^PT(?:(\d+(?:\.\d+)?)H)?(?:(\d+(?:\.\d+)?)M)?(?:(\d+(?:\.\d+)?)S)?$/i', $timestr, $m)) {
            $hours = isset($m[1]) && $m[1] !== '' ? (float)$m[1] : 0.0;
            $minutes = isset($m[2]) && $m[2] !== '' ? (float)$m[2] : 0.0;
            $seconds = isset($m[3]) && $m[3] !== '' ? (float)$m[3] : 0.0;
            return (int)round($hours * 3600 + $minutes * 60 + $seconds);
        }
        // SCORM 1.2: HH:MM:SS.ss or HH:MM:SS
        if (preg_match('/^(\d+):(\d{1,2}):(\d{1,2}(?:\.\d+)?)$/', $timestr, $m)) {
            return (int)round((float)$m[1] * 3600 + (float)$m[2] * 60 + (float)$m[3]);
        }
        return 0;
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

        if (!$DB->get_manager()->table_exists('scorm_scoes_track')) {
            return [];
        }

        $attempt = $this->get_scorm_max_attempt($userid, $scormid);
        if ($attempt === null) {
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
