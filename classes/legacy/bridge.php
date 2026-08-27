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
 * Bridge between plugin_userstatsadvanced and legacy iTOP cached data.
 *
 * When a course has legacy data in block_adv_reports_values (the iTOP cache),
 * this bridge intercepts get_value() calls and returns the cached value
 * instead of recalculating from logstore.
 *
 * The iTOP cache stores pre-formatted strings (HTML with buttons, formatted
 * durations like "10h 16m 33s", fractions like "28 / 39 (71.79%)") that
 * match the output format of plugin_userstatsadvanced, so values are
 * returned as-is without parsing.
 *
 * @package    block_configurable_reports
 * @subpackage legacy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_configurable_reports\legacy;

defined('MOODLE_INTERNAL') || die();

/**
 * Bridges stat_type requests to legacy iTOP cached values.
 */
class bridge {

    /** @var string The iTOP cache table. */
    const CACHE_TABLE = 'block_adv_reports_values';

    /**
     * Mapping from plugin stat_type → iTOP stat name in block_adv_reports_values.
     *
     * Each stat_type can map to one or more iTOP stats (tried in order).
     * The bridge picks the first one that has a non-empty value for the user.
     *
     * @var array<string, string[]>
     */
    const STAT_MAP = [
        'tiempo_total'           => ['platformdedicationtime'],
        'actividades_aprendizaje' => ['assignment_num_completed_vs_total'],
        'contenidos_visualizados' => ['course_modules_visited'],
        'evaluaciones'           => ['quiz_completed_vs_total_moodle_criteria'],
        'dias_conexion'          => ['distinct_days_connection'],
        'primer_acceso'          => ['first_connection'],
        'ultimo_acceso'          => ['last_connection'],
        'interacciones_foros'    => ['interactions_with_forums'],
        'mensajes_foro'          => ['interactions_with_forums'],
        'foros_publicados'       => ['interactions_with_forums'],
        'correos'                => ['teacher_num_messages_with_students'],
        'mensajes_tutor'         => ['teacher_num_messages_with_students'],
        'mensajes_alumnos'       => ['total_messages'],
        'ips_utilizadas'         => ['distinct_ips'],
        'ultima_ip'              => ['last_ip'],
        'registros'              => ['distinct_days_connection'],
    ];

    /**
     * Stat types that should NOT be bridged even when legacy data exists.
     *
     * These are either live-only metrics (enrollment status, grade) or
     * complex rendered outputs that don't have a cached equivalent.
     *
     * @var string[]
     */
    const EXCLUDED_STATS = [
        'matricula_activa',
        'nota_final',
        'primer_acceso_scorm',
        'scorm_completados',
        'tiempos_conexion_diarios_html',
        'informe_pdf',
        'informe_detallado',
        'tareas_entregadas',
        'intentos_cuestionario',
        'recursos_completados',
        'finalizacion_cruzada',
        'logs_integracion',
    ];

    /** @var array<int, bool> Cache: courseid → has legacy data. */
    private static $coursehasdata = [];

    /** @var array<string, array> Cache: "courseid_reportids" → preloaded values. */
    private static $preloaded = [];

    /**
     * Try to resolve a stat_type from legacy cache.
     *
     * Returns the cached value string if legacy data exists for this
     * course+user+stat, or null if the bridge cannot handle it
     * (no legacy data, excluded stat, no mapping, no cached value).
     *
     * @param int    $courseid  The course ID.
     * @param int    $userid    The user ID.
     * @param string $stat_type The stat_type from plugin_userstatsadvanced.
     * @return string|null The cached value, or null to fall through to own calculation.
     */
    public static function resolve(int $courseid, int $userid, string $stat_type): ?string {
        // Excluded stats always fall through.
        if (in_array($stat_type, self::EXCLUDED_STATS, true)) {
            return null;
        }

        // No mapping for this stat_type.
        if (!isset(self::STAT_MAP[$stat_type])) {
            return null;
        }

        // Check if course has any legacy cached data.
        if (!self::course_has_cached_data($courseid)) {
            return null;
        }

        // Try each mapped iTOP stat name.
        $itopstats = self::STAT_MAP[$stat_type];
        foreach ($itopstats as $itopstat) {
            $value = self::get_cached_value($courseid, $userid, $itopstat);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        // Legacy data exists for this course but not for this user+stat.
        // Return null to let own calculation handle it.
        return null;
    }

    /**
     * Check if a course has any data in the iTOP cache table.
     *
     * Results are cached per-request.
     *
     * @param int $courseid The course ID.
     * @return bool True if cache data exists.
     */
    public static function course_has_cached_data(int $courseid): bool {
        global $DB;

        if (isset(self::$coursehasdata[$courseid])) {
            return self::$coursehasdata[$courseid];
        }

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists(self::CACHE_TABLE)) {
            self::$coursehasdata[$courseid] = false;
            return false;
        }

        self::$coursehasdata[$courseid] = $DB->record_exists(
            self::CACHE_TABLE,
            ['courseid' => $courseid]
        );

        return self::$coursehasdata[$courseid];
    }

    /**
     * Get a single cached value for a course+user+stat.
     *
     * Searches across all reportids and returns the first non-empty value found.
     *
     * @param int    $courseid  The course ID.
     * @param int    $userid    The user ID.
     * @param string $itopstat  The iTOP stat name.
     * @return string|null The cached value, or null if not found.
     */
    private static function get_cached_value(int $courseid, int $userid, string $itopstat): ?string {
        global $DB;

        // Check preloaded data first (avoids N+1 queries when iterating users).
        $preloaded = self::get_preloaded_value($courseid, $userid, $itopstat);
        if ($preloaded !== false) {
            return $preloaded; // null (no value) or string (found).
        }

        // Fallback: query the DB directly.
        $records = $DB->get_records_select(
            self::CACHE_TABLE,
            'courseid = :courseid AND userid = :userid AND stat = :stat AND value IS NOT NULL AND value != :empty',
            [
                'courseid' => $courseid,
                'userid'   => $userid,
                'stat'     => $itopstat,
                'empty'    => '',
            ],
            'reportid ASC',  // Prefer lower reportids (more general reports).
            'value',
            0,
            1
        );

        if (empty($records)) {
            return null;
        }

        $record = reset($records);
        return $record->value;
    }

    /**
     * Preload all cached values for a course to minimize DB queries.
     *
     * Call this once before iterating over users to avoid N+1 queries.
     * After preloading, get_cached_value() will use the preloaded data.
     *
     * @param int   $courseid  The course ID.
     * @param array $userids   Array of user IDs to preload (empty = all).
     */
    public static function preload(int $courseid, array $userids = []): void {
        global $DB;

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists(self::CACHE_TABLE)) {
            return;
        }

        $params = ['courseid' => $courseid];
        $userwhere = '';

        if (!empty($userids)) {
            list($insql, $inparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
            $userwhere = " AND userid {$insql}";
            $params = array_merge($params, $inparams);
        }

        $sql = "SELECT id, userid, reportid, stat, value
                  FROM {" . self::CACHE_TABLE . "}
                 WHERE courseid = :courseid{$userwhere}
              ORDER BY reportid ASC";

        $records = $DB->get_records_sql($sql, $params);

        // Index by userid → stat → value (first reportid wins).
        $indexed = [];
        foreach ($records as $r) {
            if (!isset($indexed[$r->userid][$r->stat]) && $r->value !== null && $r->value !== '') {
                $indexed[$r->userid][$r->stat] = $r->value;
            }
        }

        $key = 'course_' . $courseid;
        self::$preloaded[$key] = $indexed;

        // Mark course as having data if we got results.
        self::$coursehasdata[$courseid] = !empty($records);
    }

    /**
     * Check if preloaded data exists and return the value.
     *
     * @param int    $courseid The course ID.
     * @param int    $userid   The user ID.
     * @param string $itopstat The iTOP stat name.
     * @return string|null|false False if not preloaded, null if preloaded but no value, string if found.
     */
    private static function get_preloaded_value(int $courseid, int $userid, string $itopstat) {
        $key = 'course_' . $courseid;
        if (!isset(self::$preloaded[$key])) {
            return false; // Not preloaded.
        }
        return self::$preloaded[$key][$userid][$itopstat] ?? null;
    }

    /**
     * Check if a stat_type can potentially be resolved from legacy data.
     *
     * @param string $stat_type The stat_type to check.
     * @return bool True if a mapping exists and it's not excluded.
     */
    public static function is_bridgeable(string $stat_type): bool {
        return isset(self::STAT_MAP[$stat_type]) && !in_array($stat_type, self::EXCLUDED_STATS, true);
    }

    /**
     * Get all available stat mappings for documentation/debugging.
     *
     * @return array<string, string[]> The full stat_type → iTOP stat mapping.
     */
    public static function get_stat_map(): array {
        return self::STAT_MAP;
    }

    /**
     * Reset internal caches.
     *
     * Useful for unit tests.
     */
    public static function reset_cache(): void {
        self::$coursehasdata = [];
        self::$preloaded = [];
    }
}
