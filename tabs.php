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

// Only show the component tabs the user needs.
// ordering, calcs and plot are hidden intentionally.
$allowedcomponents = ['columns', 'conditions', 'filters', 'template', 'permissions'];

$top = [];

// Informe — report settings (editreport.php). Shown first.
$url = new moodle_url('/blocks/configurable_reports/editreport.php', ['id' => $report->id, 'courseid' => $COURSE->id]);
$top[] = new tabobject('report', $url, get_string('report', 'block_configurable_reports'));

// Component tabs (Columnas, Condiciones, Filtros, Plantilla, Permisos).
foreach ($reportclass->components as $comptab) {
    if (!in_array($comptab, $allowedcomponents, true)) {
        continue;
    }
    $urlattrs = ['id' => $report->id, 'comp' => $comptab, 'courseid' => $COURSE->id];
    $url = new moodle_url('/blocks/configurable_reports/editcomp.php', $urlattrs);
    $top[] = new tabobject($comptab, $url, get_string($comptab, 'block_configurable_reports'));
}

// Exportar plantilla — export the report configuration template.
// sesskey is required by export.php (confirm_sesskey()).
$url = new moodle_url('/blocks/configurable_reports/export.php', [
    'id'       => $report->id,
    'courseid' => $COURSE->id,
    'sesskey'  => sesskey(),
]);
$top[] = new tabobject('exporttemplate', $url, get_string('exporttemplate', 'block_configurable_reports'));

// Ver informe — view the report output.
$url = new moodle_url('/blocks/configurable_reports/viewreport.php', ['id' => $report->id, 'courseid' => $COURSE->id]);
$top[] = new tabobject('viewreport', $url, get_string('viewreport', 'block_configurable_reports'));

print_tabs([$top], $currenttab);
