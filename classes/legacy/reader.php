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
 * Read-only reader for itop (block_advanced_reports) legacy tables.
 *
 * All methods are SELECT-only — no writes, no deletes, no modifications.
 * This class reads data exactly as itop calculated it, preserving the
 * original values for audit/custody purposes.
 *
 * @package    block_configurable_reports
 * @subpackage legacy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_configurable_reports\legacy;

defined('MOODLE_INTERNAL') || die();

/**
 * Read-only accessor for itop advanced_reports legacy data.
 */
class reader {

    /** @var int The course ID this reader operates on. */
    private $courseid;

    /** @var int The detected scenario for this course. */
    private $scenario;

    /**
     * Constructor.
     *
     * @param int $courseid The course ID to read data for.
     */
    public function __construct(int $courseid) {
        $this->courseid = $courseid;
        $this->scenario = detector::detect($courseid);
    }

    /**
     * Get the scenario this reader is operating under.
     *
     * @return int The scenario constant from detector.
     */
    public function get_scenario(): int {
        return $this->scenario;
    }

    /**
     * Get the course ID.
     *
     * @return int
     */
    public function get_courseid(): int {
        return $this->courseid;
    }

    /**
     * Check if this reader can provide itop data.
     *
     * @return bool True for scenarios 1 and 2.
     */
    public function has_itop_data(): bool {
        return $this->scenario !== detector::SCENARIO_OWN_PLUGIN;
    }

    // -------------------------------------------------------------------------
    // Query 1: Dedication time per student.
    // -------------------------------------------------------------------------

    /**
     * Get dedication time per student for this course.
     *
     * Joins block_adv_reports_times with block_adv_reports_chours and
     * block_adv_reports_tmethod to provide complete dedication data.
     *
     * @param int $userid Optional — filter for a specific user. 0 = all users.
     * @return array Array of objects with userid, dedicationtime, graceperiods,
     *               coursehours, passhours, trackingmethod, timemodified.
     */
    public function get_dedication_times(int $userid = 0): array {
        global $DB;

        if (!$this->has_itop_data()) {
            return [];
        }

        $params = ['courseid' => $this->courseid];
        $userwhere = '';

        if ($userid > 0) {
            $userwhere = ' AND t.userid = :userid';
            $params['userid'] = $userid;
        }

        $sql = "SELECT t.id,
                       t.userid,
                       t.dedicationtime,
                       t.graceperiods,
                       t.timemodified,
                       u.firstname,
                       u.lastname,
                       u.email,
                       ch.hours AS coursehours,
                       ch.pass AS passhours,
                       tm.method AS trackingmethod
                  FROM {block_adv_reports_times} t
                  JOIN {user} u ON u.id = t.userid
             LEFT JOIN {block_adv_reports_chours} ch ON ch.courseid = t.course
             LEFT JOIN {block_adv_reports_tmethod} tm ON tm.courseid = t.course
                 WHERE t.course = :courseid
                       {$userwhere}
              ORDER BY u.lastname ASC, u.firstname ASC";

        return $DB->get_records_sql($sql, $params);
    }

    // -------------------------------------------------------------------------
    // Query 2: SCORM breakdown per student.
    // -------------------------------------------------------------------------

    /**
     * Get SCORM activity times breakdown per student.
     *
     * Reads from block_adv_reports_sco_times and block_adv_reports_timesco
     * with module info from course_modules and scorm tables.
     *
     * @param int $userid Optional — filter for a specific user. 0 = all users.
     * @return array Array of objects with SCORM timing details.
     */
    public function get_scorm_times(int $userid = 0): array {
        global $DB;

        if (!$this->has_itop_data()) {
            return [];
        }

        // Check if the SCORM times table exists.
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('block_adv_reports_sco_times')) {
            return [];
        }

        $params = ['courseid' => $this->courseid];
        $userwhere = '';

        if ($userid > 0) {
            $userwhere = ' AND st.userid = :userid';
            $params['userid'] = $userid;
        }

        $sql = "SELECT st.id,
                       st.userid,
                       st.courseid,
                       st.scoid,
                       st.attempt,
                       st.dedicationtime,
                       st.timemodified,
                       st.module,
                       u.firstname,
                       u.lastname,
                       sco.title AS sconame,
                       s.name AS scormname,
                       tc.hours AS scorm_hours
                  FROM {block_adv_reports_sco_times} st
                  JOIN {user} u ON u.id = st.userid
             LEFT JOIN {scorm_scoes} sco ON sco.id = st.scoid
             LEFT JOIN {scorm} s ON s.id = sco.scorm
             LEFT JOIN {block_adv_reports_timesco} tc
                       ON tc.courseid = st.courseid
                       AND tc.scormid = sco.scorm
                 WHERE st.courseid = :courseid
                       AND st.deleted = 0
                       {$userwhere}
              ORDER BY u.lastname ASC, u.firstname ASC, s.name ASC, st.attempt ASC";

        return $DB->get_records_sql($sql, $params);
    }

    // -------------------------------------------------------------------------
    // Query 3: Videoconference attendance.
    // -------------------------------------------------------------------------

    /**
     * Get videoconference attendance data.
     *
     * Reads from block_adv_reports_videoconf and block_adv_reports_videoagg.
     *
     * @param int $userid Optional — filter for a specific user. 0 = all users.
     * @return array Array of objects with videoconference attendance details.
     */
    public function get_videoconference_data(int $userid = 0): array {
        global $DB;

        if (!$this->has_itop_data()) {
            return [];
        }

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('block_adv_reports_videoconf')) {
            return [];
        }

        $params = ['courseid' => $this->courseid];
        $userwhere = '';

        if ($userid > 0) {
            $userwhere = ' AND vc.userid = :userid';
            $params['userid'] = $userid;
        }

        $sql = "SELECT vc.id,
                       vc.userid,
                       vc.cmid,
                       vc.timestart,
                       vc.timeend,
                       vc.duration,
                       vc.type,
                       u.firstname,
                       u.lastname,
                       cm.instance,
                       va.timewithinsession AS agg_duration,
                       va.timewithinsessionslots AS agg_slots
                  FROM {block_adv_reports_videoconf} vc
                  JOIN {user} u ON u.id = vc.userid
                  JOIN {course_modules} cm ON cm.id = vc.cmid
             LEFT JOIN {block_adv_reports_videoagg} va
                       ON va.userid = vc.userid AND va.cmid = vc.cmid
                 WHERE cm.course = :courseid
                       {$userwhere}
              ORDER BY u.lastname ASC, u.firstname ASC, vc.timestart ASC";

        return $DB->get_records_sql($sql, $params);
    }

    // -------------------------------------------------------------------------
    // Query 4: Daily statistics.
    // -------------------------------------------------------------------------

    /**
     * Get daily statistics for this course.
     *
     * Reads from block_adv_reports_daily.
     *
     * @param int $userid Optional — filter for a specific user. 0 = all users.
     * @param string $stat Optional — filter for a specific stat type.
     * @return array Array of daily statistics records.
     */
    public function get_daily_stats(int $userid = 0, string $stat = ''): array {
        global $DB;

        if (!$this->has_itop_data()) {
            return [];
        }

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('block_adv_reports_daily')) {
            return [];
        }

        $params = ['courseid' => $this->courseid];
        $conditions = ['d.courseid = :courseid'];

        if ($userid > 0) {
            $conditions[] = 'd.userid = :userid';
            $params['userid'] = $userid;
        }

        if (!empty($stat)) {
            $conditions[] = 'd.stat = :stat';
            $params['stat'] = $stat;
        }

        $where = implode(' AND ', $conditions);

        $sql = "SELECT d.id,
                       d.userid,
                       d.courseid,
                       d.stat,
                       d.value,
                       d.num,
                       d.timeday,
                       u.firstname,
                       u.lastname
                  FROM {block_adv_reports_daily} d
                  JOIN {user} u ON u.id = d.userid
                 WHERE {$where}
              ORDER BY d.timeday DESC, u.lastname ASC, u.firstname ASC";

        return $DB->get_records_sql($sql, $params);
    }

    // -------------------------------------------------------------------------
    // Query 5: Predefined report values.
    // -------------------------------------------------------------------------

    /**
     * Get predefined report calculated values.
     *
     * Reads from block_adv_reports_values joined with block_advanced_reports
     * to get report names and types.
     *
     * @param int $userid Optional — filter for a specific user. 0 = all users.
     * @param int $reportid Optional — filter for a specific report. 0 = all reports.
     * @return array Array of pre-calculated report values.
     */
    public function get_report_values(int $userid = 0, int $reportid = 0): array {
        global $DB;

        if (!$this->has_itop_data()) {
            return [];
        }

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('block_adv_reports_values')) {
            return [];
        }

        $params = ['courseid' => $this->courseid];
        $conditions = ['v.courseid = :courseid'];

        if ($userid > 0) {
            $conditions[] = 'v.userid = :userid';
            $params['userid'] = $userid;
        }

        if ($reportid > 0) {
            $conditions[] = 'v.reportid = :reportid';
            $params['reportid'] = $reportid;
        }

        $where = implode(' AND ', $conditions);

        $sql = "SELECT v.id,
                       v.userid,
                       v.courseid,
                       v.reportid,
                       v.stat,
                       v.value,
                       u.firstname,
                       u.lastname,
                       ar.name AS reportname,
                       ar.type AS reporttype
                  FROM {block_adv_reports_values} v
                  JOIN {user} u ON u.id = v.userid
             LEFT JOIN {block_advanced_reports} ar ON ar.id = v.reportid
                 WHERE {$where}
              ORDER BY ar.name ASC, u.lastname ASC, u.firstname ASC, v.stat ASC";

        return $DB->get_records_sql($sql, $params);
    }

    // -------------------------------------------------------------------------
    // Query 6: Executive summary.
    // -------------------------------------------------------------------------

    /**
     * Get an executive summary of itop data for this course.
     *
     * Consolidates: total students, average dedication, course hours,
     * tracking method, SCORM count, date range of data.
     *
     * @return \stdClass Object with summary fields, or empty object if no data.
     */
    public function get_executive_summary(): \stdClass {
        global $DB;

        $summary = new \stdClass();
        $summary->courseid = $this->courseid;
        $summary->scenario = $this->scenario;
        $summary->has_data = false;

        if (!$this->has_itop_data()) {
            return $summary;
        }

        // Student count and dedication stats.
        $sql = "SELECT COUNT(DISTINCT t.userid) AS total_students,
                       AVG(t.dedicationtime) AS avg_dedication,
                       SUM(t.dedicationtime) AS total_dedication,
                       MIN(t.timemodified) AS first_record,
                       MAX(t.timemodified) AS last_record
                  FROM {block_adv_reports_times} t
                 WHERE t.course = :courseid";

        $stats = $DB->get_record_sql($sql, ['courseid' => $this->courseid]);

        if ($stats && $stats->total_students > 0) {
            $summary->has_data = true;
            $summary->total_students = (int)$stats->total_students;
            $summary->avg_dedication = round((float)$stats->avg_dedication);
            $summary->total_dedication = (int)$stats->total_dedication;
            $summary->first_record = (int)$stats->first_record;
            $summary->last_record = (int)$stats->last_record;
        }

        // Course hours config.
        $chours = $DB->get_record('block_adv_reports_chours', ['courseid' => $this->courseid]);
        if ($chours) {
            $summary->course_hours = (float)$chours->hours;
            $summary->pass_hours = (float)$chours->pass;
        }

        // Tracking method.
        $tmethod = $DB->get_record('block_adv_reports_tmethod', ['courseid' => $this->courseid]);
        if ($tmethod) {
            $summary->tracking_method = $tmethod->method;
        }

        // SCORM count.
        $dbman = $DB->get_manager();
        if ($dbman->table_exists('block_adv_reports_sco_times')) {
            $scormcount = $DB->count_records_sql(
                "SELECT COUNT(DISTINCT st.scoid) FROM {block_adv_reports_sco_times} st
                  WHERE st.courseid = :courseid
                        AND st.deleted = 0",
                ['courseid' => $this->courseid]
            );
            $summary->scorm_count = (int)$scormcount;
        }

        // Section times count.
        if ($dbman->table_exists('block_adv_reports_sect_times')) {
            $summary->has_section_times = $DB->record_exists(
                'block_adv_reports_sect_times',
                ['courseid' => $this->courseid]
            );
        }

        // Videoconference data count.
        if ($dbman->table_exists('block_adv_reports_videoconf')) {
            $vccount = $DB->count_records_sql(
                "SELECT COUNT(DISTINCT vc.id) FROM {block_adv_reports_videoconf} vc
                   JOIN {course_modules} cm ON cm.id = vc.cmid
                  WHERE cm.course = :courseid",
                ['courseid' => $this->courseid]
            );
            $summary->videoconf_records = (int)$vccount;
        }

        // Course info.
        $course = $DB->get_record('course', ['id' => $this->courseid], 'id, fullname, shortname, startdate, enddate');
        if ($course) {
            $summary->course_fullname = $course->fullname;
            $summary->course_shortname = $course->shortname;
            $summary->course_startdate = (int)$course->startdate;
            $summary->course_enddate = (int)$course->enddate;
        }

        return $summary;
    }

    // -------------------------------------------------------------------------
    // Query: Section times.
    // -------------------------------------------------------------------------

    /**
     * Get section-level time tracking data.
     *
     * @param int $userid Optional — filter for a specific user. 0 = all users.
     * @return array Array of section time records.
     */
    public function get_section_times(int $userid = 0): array {
        global $DB;

        if (!$this->has_itop_data()) {
            return [];
        }

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('block_adv_reports_sect_times')) {
            return [];
        }

        $params = ['courseid' => $this->courseid];
        $userwhere = '';

        if ($userid > 0) {
            $userwhere = ' AND st.userid = :userid';
            $params['userid'] = $userid;
        }

        $sql = "SELECT st.id,
                       st.userid,
                       st.courseid,
                       st.section_timestart,
                       st.section_timeend,
                       (st.section_timeend - st.section_timestart) AS dedicationtime,
                       st.timecreated,
                       u.firstname,
                       u.lastname
                  FROM {block_adv_reports_sect_times} st
                  JOIN {user} u ON u.id = st.userid
                 WHERE st.courseid = :courseid
                       {$userwhere}
              ORDER BY u.lastname ASC, u.firstname ASC, st.section_timestart ASC";

        return $DB->get_records_sql($sql, $params);
    }

    // -------------------------------------------------------------------------
    // Query: User stats (usrstats).
    // -------------------------------------------------------------------------

    /**
     * Get aggregated user statistics from the itop stats table.
     *
     * @param int $userid Optional — filter for a specific user. 0 = all users.
     * @return array Array of user stat records.
     */
    public function get_user_stats(int $userid = 0): array {
        global $DB;

        if (!$this->has_itop_data()) {
            return [];
        }

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('block_adv_reports_usrstats')) {
            return [];
        }

        $params = ['courseid' => $this->courseid];
        $userwhere = '';

        if ($userid > 0) {
            $userwhere = ' AND us.userid = :userid';
            $params['userid'] = $userid;
        }

        $sql = "SELECT us.id,
                       us.userid,
                       us.courseid,
                       us.stat,
                       us.value,
                       us.timecreated,
                       us.dim1,
                       u.firstname,
                       u.lastname
                  FROM {block_adv_reports_usrstats} us
                  JOIN {user} u ON u.id = us.userid
                 WHERE us.courseid = :courseid
                       {$userwhere}
              ORDER BY u.lastname ASC, u.firstname ASC, us.stat ASC";

        return $DB->get_records_sql($sql, $params);
    }

    // -------------------------------------------------------------------------
    // Query: Certificates.
    // -------------------------------------------------------------------------

    /**
     * Get certificate configuration/status records from itop.
     *
     * Note: block_adv_reports_cert is a course-level table (no userid column).
     * It tracks certificate generation status per course.
     *
     * @return array Array of certificate status records for this course.
     */
    public function get_certificates(): array {
        global $DB;

        if (!$this->has_itop_data()) {
            return [];
        }

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('block_adv_reports_cert')) {
            return [];
        }

        $sql = "SELECT c.id,
                       c.courseid,
                       c.timeupdated,
                       c.status
                  FROM {block_adv_reports_cert} c
                 WHERE c.courseid = :courseid
              ORDER BY c.timeupdated DESC";

        return $DB->get_records_sql($sql, ['courseid' => $this->courseid]);
    }

    // -------------------------------------------------------------------------
    // Utility: Format dedication time.
    // -------------------------------------------------------------------------

    /**
     * Format seconds of dedication time into a human-readable string.
     *
     * @param int $seconds Total seconds.
     * @return string Formatted string, e.g. "2h 35m 10s".
     */
    public static function format_dedication(int $seconds): string {
        if ($seconds <= 0) {
            return '0m';
        }

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        $parts = [];
        if ($hours > 0) {
            $parts[] = $hours . 'h';
        }
        if ($minutes > 0) {
            $parts[] = $minutes . 'm';
        }
        if ($secs > 0 && $hours == 0) {
            // Only show seconds when under 1 hour.
            $parts[] = $secs . 's';
        }

        return implode(' ', $parts);
    }
}
