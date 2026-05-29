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

require_once("../../config.php");
require_once($CFG->dirroot . "/blocks/configurable_reports/locallib.php");

$id = required_param('id', PARAM_INT);
$download = optional_param('download', false, PARAM_BOOL);
$format = optional_param('format', '', PARAM_ALPHA);
$courseid = optional_param('courseid', null, PARAM_INT);
$embed = optional_param('embed', false, PARAM_BOOL);
$refresh = optional_param('refresh', false, PARAM_BOOL);
$refreshed = optional_param('refreshed', false, PARAM_BOOL);

if (!$report = $DB->get_record('block_configurable_reports', ['id' => $id])) {
    throw new moodle_exception('reportdoesnotexists', 'block_configurable_reports');
}

if ($courseid && $report->global) {
    $report->courseid = $courseid;
} else {
    $courseid = $report->courseid;
}

if (!$course = $DB->get_record('course', ['id' => $courseid])) {
    throw new moodle_exception('No such course id');
}

// Force user login in course (SITE or Course).
if ((int) $course->id === SITEID) {
    require_login();
    $context = context_system::instance();
} else {
    require_login($course);
    $context = context_course::instance($course->id);
}

require_once($CFG->dirroot . '/blocks/configurable_reports/report.class.php');
require_once($CFG->dirroot . '/blocks/configurable_reports/reports/' . $report->type . '/report.class.php');

$reportclassname = 'report_' . $report->type;
$reportclass = new $reportclassname($report);

if (!$reportclass->check_permissions($USER->id, $context)) {
    throw new moodle_exception('badpermissions', 'block_configurable_reports');
}

$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_url('/blocks/configurable_reports/viewreport.php', ['id' => $id]);
$PAGE->requires->jquery();

$download = $download && $format && strpos($report->export, $format . ',') !== false;
$usingcachedreportdata = false;

if ($download && $report->type === "sql") {
    $reportclass->set_forexport(true);
}

if (!$download) {
    $reportcache = cr_get_report_result_cache();
    $cachekey = cr_get_report_cache_key($report, (int) $USER->id, (int) $courseid);

    if ($refresh) {
        require_sesskey();
        $reportcache->delete($cachekey);
        $reportclass->create_report();
        $reportcache->set($cachekey, cr_get_report_cache_payload($reportclass));

        $redirectparams = array_merge(
            ['id' => $id, 'courseid' => $courseid],
            cr_get_viewreport_request_params(['refresh', 'refreshed', 'download', 'format', 'sesskey'])
        );
        $redirectparams['refreshed'] = 1;
        redirect(new moodle_url('/blocks/configurable_reports/viewreport.php', $redirectparams));
    }

    $cachedpayload = $reportcache->get($cachekey);
    if ($cachedpayload && cr_apply_report_cache_payload($reportclass, $cachedpayload)) {
        $usingcachedreportdata = true;
    } else {
        $reportclass->create_report();
        $reportcache->set($cachekey, cr_get_report_cache_payload($reportclass));
    }
} else {
    $reportclass->create_report();
}

$action = (!empty($download)) ? 'download' : 'view';

// No download, build navigation header etc..
if (!$download) {
    $reportclass->check_filters_request();
    $reportname = format_string($report->name);
    $navlinks = [];

    $hasmanageallcap = has_capability('block/configurable_reports:managereports', $context);
    $hasmanageowncap = has_capability('block/configurable_reports:manageownreports', $context);

    if ($hasmanageallcap || ($hasmanageowncap && $report->ownerid == $USER->id)) {
        $managereporturl = new moodle_url('/blocks/configurable_reports/managereport.php', ['courseid' => $report->courseid]);
        $PAGE->navbar->add(get_string('managereports', 'block_configurable_reports'), $managereporturl);
        $PAGE->navbar->add($reportname);
    } else {
        // These users don't have the capability to manage reports but we still want them to see some breadcrumbs.
        $PAGE->navbar->add(get_string('viewreport', 'block_configurable_reports'));
        $PAGE->navbar->add($reportname);
    }

    $PAGE->set_title($reportname);
    $PAGE->set_heading($reportname);
    $PAGE->set_cacheable(true);
    if ($embed) {
        $PAGE->set_pagelayout('embedded');
    }
    echo $OUTPUT->header();

    $canmanage = ($hasmanageallcap || ($hasmanageowncap && $report->ownerid == $USER->id));
    if (!$embed && $canmanage) {
        $currenttab = 'viewreport';
        include('tabs.php');
    }
    // Quick navigation tabs for numbered audit reports (01..08).
    if (!$embed) {
        $tabs = [];
        $toprow = [];
        $auditreports = $DB->get_records_select(
            'block_configurable_reports',
            'name LIKE :namepattern AND courseid = :courseid',
            ['namepattern' => '0% %', 'courseid' => $course->id],
            'name ASC',
            'id, name',
            0,
            8
        );

        foreach ($auditreports as $auditreport) {
            $taburl = new moodle_url('/blocks/configurable_reports/viewreport.php', [
                'id' => $auditreport->id,
                'courseid' => $course->id,
            ]);
            $toprow[] = new tabobject('report' . $auditreport->id, $taburl, format_string($auditreport->name));
        }

        if (!empty($toprow)) {
            echo '<style>
                .cr-audit-tabs {
                    margin: 12px 0 16px;
                    padding: 8px 12px;
                    background-color: #17a2b8;
                    border-radius: 4px;
                }

                /* Bootstrap/Moodle nav tabs (newer themes). */
                .cr-audit-tabs .nav-tabs {
                    margin: 0 !important;
                    border-bottom: 0 !important;
                }
                .cr-audit-tabs .nav-tabs .nav-item {
                    margin-bottom: 0 !important;
                }
                .cr-audit-tabs .nav-tabs .nav-link,
                .cr-audit-tabs .nav-tabs .nav-link:link,
                .cr-audit-tabs .nav-tabs .nav-link:visited,
                .cr-audit-tabs .nav-tabs .nav-link:hover,
                .cr-audit-tabs .nav-tabs .nav-link:focus,
                .cr-audit-tabs .nav-tabs .nav-link span {
                    color: #ffffff !important;
                    background: transparent !important;
                    border: 0 !important;
                    text-decoration: none;
                }
                .cr-audit-tabs .nav-tabs .nav-link:hover,
                .cr-audit-tabs .nav-tabs .nav-link:focus {
                    background-color: rgba(255, 255, 255, 0.15) !important;
                }
                .cr-audit-tabs .nav-tabs .nav-link.active,
                .cr-audit-tabs .nav-tabs .nav-item.show .nav-link {
                    color: #0f4c5a !important;
                    background-color: #ffffff !important;
                    border: 0 !important;
                }

                /* Legacy tab markup (older themes). */
                .cr-audit-tabs .tabtree,
                .cr-audit-tabs .tabtree .tabrow0,
                .cr-audit-tabs .tabtree .tabrow0:before,
                .cr-audit-tabs .tabtree .tabrow0:after {
                    margin: 0;
                    padding: 0;
                    border: 0 !important;
                    box-shadow: none !important;
                    background: transparent !important;
                }
                .cr-audit-tabs .tabrow0 li {
                    margin: 0 2px 0 0;
                    color: #ffffff !important;
                }
                .cr-audit-tabs .tabrow0 li a,
                .cr-audit-tabs .tabrow0 li a:link,
                .cr-audit-tabs .tabrow0 li a:visited,
                .cr-audit-tabs .tabrow0 li a:hover,
                .cr-audit-tabs .tabrow0 li a:focus,
                .cr-audit-tabs .tabrow0 li a span {
                    color: #ffffff !important;
                    background: transparent !important;
                    text-decoration: none;
                    border: 0 !important;
                }
                .cr-audit-tabs .tabrow0 li a:hover,
                .cr-audit-tabs .tabrow0 li a:focus {
                    background-color: rgba(255, 255, 255, 0.15) !important;
                }
                .cr-audit-tabs .tabrow0 li.here a,
                .cr-audit-tabs .tabrow0 li.selected a,
                .cr-audit-tabs .tabrow0 li.active a {
                    color: #0f4c5a !important;
                    background-color: #ffffff !important;
                    border: 0 !important;
                }
            </style>';
            echo html_writer::start_tag('div', ['class' => 'cr-audit-tabs']);
            $tabs[] = $toprow;
            print_tabs($tabs, 'report' . $id);
            echo html_writer::end_tag('div');
        }
    }

    if (!$embed) {
        if ($refreshed) {
            echo html_writer::div(
                get_string('reportcacherefreshed', 'block_configurable_reports'),
                'cr-report-cache-success'
            );
        }

        if ($usingcachedreportdata) {
            $refreshparams = array_merge(
                ['id' => $id, 'courseid' => $courseid],
                cr_get_viewreport_request_params(['refresh', 'refreshed', 'download', 'format', 'sesskey'])
            );
            $refreshparams['refresh'] = 1;
            $refreshparams['sesskey'] = sesskey();
            $refreshurl = new moodle_url('/blocks/configurable_reports/viewreport.php', $refreshparams);
            $refreshlink = html_writer::link(
                $refreshurl,
                get_string('refreshreportdata', 'block_configurable_reports')
            );

            echo html_writer::div(
                get_string('reportcachewarning', 'block_configurable_reports', $refreshlink),
                'cr-report-cache-notice'
            );
        }
    }

    // Print the report HTML.
    $reportclass->print_report_page($PAGE);

} else {
    // Large exports are likely to take their time and memory.
    core_php_time_limit::raise();
    raise_memory_limit(MEMORY_EXTRA);
    $exportplugin = $CFG->dirroot . '/blocks/configurable_reports/export/' . $format . '/export.php';
    if (file_exists($exportplugin)) {
        require_once($exportplugin);
        export_report($reportclass->finalreport);
    }
    die;
}

// Never reached if download = true.
echo $OUTPUT->footer();
