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
 * Column plugin: allows reading standard and custom course fields from a users report.
 *
 * This plugin acts as a bridge between user rows and course metadata:
 * it takes the current report course and repeats the selected course value
 * for each user row.
 *
 * @package   block_configurable_reports
 */
class plugin_coursecustomfield extends plugin_base {

    /** @var string Prefix used for fields from {course}. */
    protected const FIELD_PREFIX_COURSE = 'course:';
    /** @var string Prefix used for fields from customfield tables. */
    protected const FIELD_PREFIX_CUSTOM = 'customfield:';
    /** @var string Custom field component for course custom fields. */
    protected const CUSTOMFIELD_COMPONENT = 'core_course';
    /** @var string Custom field area for course custom fields. */
    protected const CUSTOMFIELD_AREA = 'course';

    /**
     * Init.
     *
     * @return void
     */
    public function init(): void {
        $this->fullname = $this->get_plugin_title_label();
        $this->type = 'undefined';
        $this->form = true;
        $this->reporttypes = ['users'];
    }

    /**
     * Returns strict map for common standard course fields in UI select.
     *
     * @return array
     */
    protected function get_standard_course_fields_map(): array {
        return [
            'fullname' => 'Nombre del curso',
            'shortname' => 'Nombre corto del curso',
            'idnumber' => 'Idnumber del curso',
            'startdate' => 'Fecha de inicio del curso',
            'enddate' => 'Fecha de fin del curso',
            'category' => 'Nombre categoría del curso',
        ];
    }

    /**
     * Checks if a field key exists in select options (supports optgroups).
     *
     * @param string $selectedfield
     * @param array $options
     * @return bool
     */
    protected function option_exists_in_select_options(string $selectedfield, array $options): bool {
        foreach ($options as $key => $value) {
            if (is_array($value)) {
                if ($this->option_exists_in_select_options($selectedfield, $value)) {
                    return true;
                }
                continue;
            }

            if ((string)$key === $selectedfield) {
                return true;
            }
        }

        return false;
    }

    /**
     * Summary shown in component list.
     *
     * @param object $data
     * @return string
     */
    public function summary(object $data): string {
        if (!empty($data->columname)) {
            return format_string($data->columname);
        }

        return $this->fullname;
    }

    /**
     * Execute column logic for each users-report row.
     *
     * @param object $data plugin configuration.
     * @param object $row current report row.
     * @param object $user current user.
     * @param int $courseid report course id.
     * @param int $starttime not used.
     * @param int $endtime not used.
     * @return string
     */
    public function execute($data, $row, $user, $courseid, $starttime = 0, $endtime = 0) {
        return $this->get_value($data, $row, $courseid);
    }

    /**
     * Returns the configured field value for the current report course.
     *
     * @param object $data plugin configuration.
     * @param object $row current row (user row in this report type).
     * @param int $courseid report course id.
     * @return string
     */
    public function get_value($data, $row, int $courseid): string {
        $selectedfield = isset($data->field) ? (string)$data->field : '';
        if ($selectedfield === '') {
            return '';
        }

        $resolvedcourseid = $this->resolve_courseid($row, $courseid);
        if ($resolvedcourseid <= 0) {
            return '';
        }

        $cachekey = $resolvedcourseid . ':' . $selectedfield;
        if (array_key_exists($cachekey, $this->cache)) {
            return (string)$this->cache[$cachekey];
        }

        $value = '';
        if (strpos($selectedfield, self::FIELD_PREFIX_COURSE) === 0) {
            $fieldname = substr($selectedfield, strlen(self::FIELD_PREFIX_COURSE));
            $value = $this->get_standard_course_field_value($resolvedcourseid, $fieldname);
        } else if (strpos($selectedfield, self::FIELD_PREFIX_CUSTOM) === 0) {
            $fieldid = (int)substr($selectedfield, strlen(self::FIELD_PREFIX_CUSTOM));
            $value = $this->get_custom_course_field_value($resolvedcourseid, $fieldid);
        }

        // Cache the value because it is the same for every row in the report course.
        $this->cache[$cachekey] = $value;

        return $value;
    }

    /**
     * Returns field options for the form select.
     *
     * It includes only custom fields defined for course entity.
     *
     * @return array
     */
    public function get_fields_for_form(): array {
        global $DB;

        // Cache options per request.
        if (isset($this->cache['fields_for_form']) && is_array($this->cache['fields_for_form'])) {
            return $this->cache['fields_for_form'];
        }

        $options = ['' => get_string('choose')];

        $customsourcelabel = $this->get_plugin_string('coursecustomfield_source_custom', 'Course custom field');

        // Course custom fields (Moodle 3.11+ custom fields API).
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('customfield_field') || !$dbman->table_exists('customfield_category')) {
            $this->cache['fields_for_form'] = $options;
            return $options;
        }
        $customoptions = [];
        $sql = "SELECT f.id, f.shortname, f.name
                  FROM {customfield_field} f
                  JOIN {customfield_category} c ON c.id = f.categoryid
                 WHERE c.component = :component
                   AND c.area = :area
              ORDER BY f.sortorder ASC, f.id ASC";
        $customfields = $DB->get_records_sql($sql, [
            'component' => self::CUSTOMFIELD_COMPONENT,
            'area' => self::CUSTOMFIELD_AREA,
        ]);

        foreach ($customfields as $customfield) {
            $label = $this->get_custom_field_display_name($customfield);

            $key = self::FIELD_PREFIX_CUSTOM . $customfield->id;
            $customoptions[$key] = $customsourcelabel . ': ' . $label;
        }
        if (!empty($customoptions)) {
            asort($customoptions, SORT_NATURAL | SORT_FLAG_CASE);
            $options += $customoptions;
        }

        $this->cache['fields_for_form'] = $options;

        return $options;
    }

    /**
     * Checks if a selected field exists in current form options.
     *
     * @param string $selectedfield
     * @return bool
     */
    public function is_valid_field_selection(string $selectedfield): bool {
        if ($selectedfield === '') {
            return false;
        }

        $options = $this->get_fields_for_form();
        return $this->option_exists_in_select_options($selectedfield, $options);
    }

    /**
     * Resolves report course id in a robust way.
     *
     * @param object $row
     * @param int $courseid
     * @return int
     */
    protected function resolve_courseid($row, int $courseid): int {
        if ($courseid > 0) {
            return $courseid;
        }

        if (!empty($row->courseid)) {
            return (int)$row->courseid;
        }

        if (!empty($this->report->courseid)) {
            return (int)$this->report->courseid;
        }

        return 0;
    }

    /**
     * Returns the value for a standard {course} field.
     *
     * @param int $courseid
     * @param string $fieldname
     * @return string
     */
    protected function get_standard_course_field_value(int $courseid, string $fieldname): string {
        global $DB;

        if ($courseid <= 0 || $fieldname === '') {
            return '';
        }

        // Safety: only allow fields that really exist in {course}.
        $columns = $DB->get_columns('course');
        if (!isset($columns[$fieldname])) {
            return '';
        }

        if ($fieldname === 'category') {
            $sql = "SELECT cc.name
                      FROM {course} c
                      JOIN {course_categories} cc ON cc.id = c.category
                     WHERE c.id = :courseid";
            $value = $DB->get_field_sql($sql, ['courseid' => $courseid]);
            if ($value === false || $value === null) {
                return '';
            }

            return (string)$value;
        }

        $value = $DB->get_field('course', $fieldname, ['id' => $courseid]);
        if ($value === false || $value === null) {
            return '';
        }

        if (in_array($fieldname, ['startdate', 'enddate'], true)) {
            return ((int)$value > 0) ? userdate((int)$value) : '';
        }

        return (string)$value;
    }

    /**
     * Returns the value for a course custom field.
     *
     * @param int $courseid
     * @param int $fieldid
     * @return string
     */
    protected function get_custom_course_field_value(int $courseid, int $fieldid): string {
        global $DB;

        if ($courseid <= 0 || $fieldid <= 0) {
            return '';
        }

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('customfield_field') ||
            !$dbman->table_exists('customfield_category') ||
            !$dbman->table_exists('customfield_data')) {
            return '';
        }

        // Join field definition + field data for the current course instance.
        $sql = "SELECT f.id,
                       f.shortname,
                       d.intvalue,
                       d.decvalue,
                       d.shortcharvalue,
                       d.charvalue,
                       d.value
                  FROM {customfield_field} f
                  JOIN {customfield_category} c ON c.id = f.categoryid
             LEFT JOIN {customfield_data} d
                    ON d.fieldid = f.id
                   AND d.instanceid = :instanceid
                 WHERE f.id = :fieldid
                   AND c.component = :component
                   AND c.area = :area";
        $record = $DB->get_record_sql($sql, [
            'instanceid' => $courseid,
            'fieldid' => $fieldid,
            'component' => self::CUSTOMFIELD_COMPONENT,
            'area' => self::CUSTOMFIELD_AREA,
        ]);

        if (!$record) {
            return '';
        }

        return $this->extract_custom_field_value($record);
    }

    /**
     * Extracts first non-empty value from custom field data columns.
     *
     * @param stdClass $record
     * @return string
     */
    protected function extract_custom_field_value(stdClass $record): string {
        $candidates = ['charvalue', 'shortcharvalue', 'value', 'intvalue', 'decvalue'];
        foreach ($candidates as $property) {
            if (!property_exists($record, $property)) {
                continue;
            }
            if ($record->{$property} === null || $record->{$property} === '') {
                continue;
            }

            return (string)$record->{$property};
        }

        return '';
    }

    /**
     * Returns a clean, user-facing label for a course custom field.
     *
     * @param stdClass $customfield
     * @return string
     */
    protected function get_custom_field_display_name(stdClass $customfield): string {
        $name = trim((string)$customfield->name);
        if ($name !== '') {
            return $name;
        }

        $shortname = trim((string)$customfield->shortname);
        if ($shortname !== '') {
            return $this->capitalize_label($shortname);
        }

        return (string)$customfield->id;
    }

    /**
     * Capitalizes the first character of a label.
     *
     * @param string $label
     * @return string
     */
    protected function capitalize_label(string $label): string {
        if ($label === '') {
            return '';
        }

        if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
            return mb_strtoupper(mb_substr($label, 0, 1)) . mb_substr($label, 1);
        }

        return ucfirst($label);
    }

    /**
     * Returns plugin title label for forms and UI.
     *
     * @return string
     */
    public function get_plugin_title_label(): string {
        return $this->get_plugin_string('coursecustomfield', 'Course custom field');
    }

    /**
     * Returns field selector label for forms and UI.
     *
     * @return string
     */
    public function get_plugin_field_label(): string {
        return $this->get_plugin_string('coursecustomfield_field', 'Field');
    }

    /**
     * Returns a plugin string with fallback to plain text when identifier is missing.
     *
     * @param string $identifier
     * @param string $fallback
     * @return string
     */
    protected function get_plugin_string(string $identifier, string $fallback): string {
        $stringmanager = get_string_manager();
        if ($stringmanager->string_exists($identifier, 'block_configurable_reports')) {
            return get_string($identifier, 'block_configurable_reports');
        }

        // Backward compatibility with legacy keys that used underscores.
        $legacykeys = [
            'coursecustomfield' => 'course_custom_fields',
            'coursecustomfield_field' => 'course_custom_fields_field',
            'coursecustomfield_group_standard' => 'course_custom_fields_group_standard',
            'coursecustomfield_group_custom' => 'course_custom_fields_group_custom',
            'coursecustomfield_source_course' => 'course_custom_fields_source_course',
            'coursecustomfield_source_custom' => 'course_custom_fields_source_custom',
        ];
        if (!empty($legacykeys[$identifier]) &&
            $stringmanager->string_exists($legacykeys[$identifier], 'block_configurable_reports')) {
            return get_string($legacykeys[$identifier], 'block_configurable_reports');
        }

        return $fallback;
    }

}
