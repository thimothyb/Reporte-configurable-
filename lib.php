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
 * Library with callbacks.
 *
 * @package    block_configurable_reports
 * @category   check
 * @copyright  2020 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add security check.
 *
 * @return array check
 */
function block_configurable_reports_security_checks(): array {
    return [new block_configurable_reports\check\sql_execution()];
}

/**
 * Adds visible configurable reports to the course navigation (Reports section).
 *
 * @param navigation_node $parentnode
 * @param stdClass        $course
 * @param context_course  $context
 * @return void
 */
function block_configurable_reports_extend_navigation_course(
    navigation_node $parentnode,
    stdClass $course,
    context_course $context
): void {
    global $DB, $USER, $CFG;

    if (!isloggedin()) {
        return;
    }

    require_once($CFG->dirroot . '/blocks/configurable_reports/locallib.php');
    require_once($CFG->dirroot . '/blocks/configurable_reports/report.class.php');

    // Informes específicos del curso.
    $coursereports = $DB->get_records(
        'block_configurable_reports',
        ['courseid' => $course->id, 'global' => 0],
        'name ASC'
    ) ?: [];

    // Informes globales (compartidos en todo el sitio).
    $globalreports = $DB->get_records(
        'block_configurable_reports',
        ['global' => 1],
        'name ASC'
    ) ?: [];

    $allreports = array_merge($coursereports, $globalreports);

    // Si el nodo recibido es el nodo del curso (no el de reports),
    // buscar el nodo 'coursereports' hijo para que aparezcan en la página Informes.
    $targetnode = $parentnode->find('coursereports', navigation_node::TYPE_CONTAINER);
    if (!$targetnode) {
        $targetnode = $parentnode;
    }

    foreach ($allreports as $report) {
        if (!$report->visible) {
            continue;
        }

        // Para informes globales, inyectar el courseid actual en el objeto.
        if ($report->global) {
            $report->courseid = $course->id;
        }

        if (!cr_check_report_permissions($report, $USER->id, $context)) {
            continue;
        }

        $url = new moodle_url('/blocks/configurable_reports/viewreport.php', [
            'id'       => $report->id,
            'courseid' => $course->id,
        ]);
        $targetnode->add(
            format_string($report->name),
            $url,
            navigation_node::TYPE_SETTING,
            null,
            'cr_report_' . $report->id,
            new pix_icon('i/report', '')
        );
    }
}

