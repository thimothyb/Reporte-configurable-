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
 * Scenario detector for legacy itop data.
 *
 * Determines which data source to use for a given course:
 *   SCENARIO_LEGACY_FROZEN (1) - Course ended + itop data exists → read-only from itop tables.
 *   SCENARIO_ITOP_LIVE     (2) - Course active + itop data exists → live read from itop tables.
 *   SCENARIO_OWN_PLUGIN    (3) - No itop data → use Configurable Reports own calculations.
 *
 * @package    block_configurable_reports
 * @subpackage legacy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_configurable_reports\legacy;

defined('MOODLE_INTERNAL') || die();

/**
 * Detects which scenario applies for a given course regarding itop legacy data.
 */
class detector {

    /** @var int Course ended and itop data exists — frozen legacy data. */
    const SCENARIO_LEGACY_FROZEN = 1;

    /** @var int Course still active and itop data exists — live read from itop. */
    const SCENARIO_ITOP_LIVE = 2;

    /** @var int No itop data — use own plugin calculations. */
    const SCENARIO_OWN_PLUGIN = 3;

    /** @var string The itop table used to detect data presence. */
    const ITOP_DETECTION_TABLE = 'block_adv_reports_times';

    /** @var array Tables that must exist for itop legacy to be available. */
    const ITOP_REQUIRED_TABLES = [
        'block_adv_reports_times',
        'block_advanced_reports',
    ];

    /** @var bool|null Cached result of whether itop tables exist in DB. */
    private static $tablesexist = null;

    /**
     * Detect the scenario for a given course.
     *
     * @param int $courseid The course ID to check.
     * @return int One of SCENARIO_LEGACY_FROZEN, SCENARIO_ITOP_LIVE, or SCENARIO_OWN_PLUGIN.
     */
    public static function detect(int $courseid): int {
        // If itop tables don't exist at all, it's always scenario 3.
        if (!self::itop_tables_exist()) {
            return self::SCENARIO_OWN_PLUGIN;
        }

        // Check if this course has any data in the itop times table.
        if (!self::course_has_itop_data($courseid)) {
            return self::SCENARIO_OWN_PLUGIN;
        }

        // Course has itop data — check if the course has ended.
        if (self::course_has_ended($courseid)) {
            return self::SCENARIO_LEGACY_FROZEN;
        }

        return self::SCENARIO_ITOP_LIVE;
    }

    /**
     * Get a human-readable label for a scenario constant.
     *
     * @param int $scenario The scenario constant.
     * @return string The lang string key for this scenario.
     */
    public static function get_scenario_label(int $scenario): string {
        switch ($scenario) {
            case self::SCENARIO_LEGACY_FROZEN:
                return get_string('legacy_scenario_frozen', 'block_configurable_reports');
            case self::SCENARIO_ITOP_LIVE:
                return get_string('legacy_scenario_live', 'block_configurable_reports');
            case self::SCENARIO_OWN_PLUGIN:
                return get_string('legacy_scenario_own', 'block_configurable_reports');
            default:
                return get_string('legacy_scenario_unknown', 'block_configurable_reports');
        }
    }

    /**
     * Get scenario info as an object with all relevant metadata.
     *
     * @param int $courseid The course ID.
     * @return \stdClass Object with scenario, label, source, readonly properties.
     */
    public static function get_scenario_info(int $courseid): \stdClass {
        $scenario = self::detect($courseid);

        $info = new \stdClass();
        $info->scenario = $scenario;
        $info->label = self::get_scenario_label($scenario);
        $info->courseid = $courseid;

        switch ($scenario) {
            case self::SCENARIO_LEGACY_FROZEN:
                $info->source = 'itop_legacy';
                $info->readonly = true;
                $info->itop_available = true;
                break;
            case self::SCENARIO_ITOP_LIVE:
                $info->source = 'itop_live';
                $info->readonly = false;
                $info->itop_available = true;
                break;
            case self::SCENARIO_OWN_PLUGIN:
            default:
                $info->source = 'configurable_reports';
                $info->readonly = false;
                $info->itop_available = false;
                break;
        }

        return $info;
    }

    /**
     * Batch detect scenarios for multiple courses at once.
     *
     * @param array $courseids Array of course IDs.
     * @return array Associative array of courseid => scenario constant.
     */
    public static function detect_batch(array $courseids): array {
        global $DB;

        $results = [];

        if (empty($courseids) || !self::itop_tables_exist()) {
            // No itop tables — all courses are scenario 3.
            foreach ($courseids as $cid) {
                $results[(int)$cid] = self::SCENARIO_OWN_PLUGIN;
            }
            return $results;
        }

        // Get all courses that have itop data in one query.
        list($insql, $params) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');
        $sql = "SELECT DISTINCT course FROM {" . self::ITOP_DETECTION_TABLE . "} WHERE course {$insql}";
        $coursesWithData = $DB->get_fieldset_sql($sql, $params);
        $coursesWithDataSet = array_flip($coursesWithData);

        // Get course end dates in one query.
        list($insql2, $params2) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'cid');
        $courses = $DB->get_records_sql(
            "SELECT id, enddate FROM {course} WHERE id {$insql2}",
            $params2
        );

        $now = time();

        foreach ($courseids as $cid) {
            $cid = (int)$cid;
            if (!isset($coursesWithDataSet[$cid])) {
                $results[$cid] = self::SCENARIO_OWN_PLUGIN;
            } else if (isset($courses[$cid]) && $courses[$cid]->enddate > 0 && $courses[$cid]->enddate < $now) {
                $results[$cid] = self::SCENARIO_LEGACY_FROZEN;
            } else {
                $results[$cid] = self::SCENARIO_ITOP_LIVE;
            }
        }

        return $results;
    }

    /**
     * Check whether the required itop tables exist in the database.
     *
     * Results are cached for the duration of the request.
     *
     * @return bool True if all required itop tables exist.
     */
    public static function itop_tables_exist(): bool {
        global $DB;

        if (self::$tablesexist !== null) {
            return self::$tablesexist;
        }

        $dbman = $DB->get_manager();
        self::$tablesexist = true;

        foreach (self::ITOP_REQUIRED_TABLES as $tablename) {
            if (!$dbman->table_exists($tablename)) {
                self::$tablesexist = false;
                break;
            }
        }

        return self::$tablesexist;
    }

    /**
     * Check whether a specific course has any data in the itop times table.
     *
     * @param int $courseid The course ID.
     * @return bool True if records exist for this course.
     */
    public static function course_has_itop_data(int $courseid): bool {
        global $DB;

        return $DB->record_exists(self::ITOP_DETECTION_TABLE, ['course' => $courseid]);
    }

    /**
     * Check whether a course has ended (enddate in the past).
     *
     * A course with enddate = 0 (no end date set) is considered active.
     *
     * @param int $courseid The course ID.
     * @return bool True if the course end date is in the past.
     */
    public static function course_has_ended(int $courseid): bool {
        global $DB;

        $course = $DB->get_record('course', ['id' => $courseid], 'id, enddate');
        if (!$course) {
            return false;
        }

        // enddate = 0 means no end date → course is considered active.
        if (empty($course->enddate)) {
            return false;
        }

        return ($course->enddate < time());
    }

    /**
     * Reset the internal table-existence cache.
     *
     * Useful in unit tests or after plugin install/uninstall events.
     */
    public static function reset_cache(): void {
        self::$tablesexist = null;
    }
}
