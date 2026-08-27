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
 * Legacy data viewer — displays itop (advanced_reports) data as read-only
 * within the Configurable Reports plugin.
 *
 * Supports three scenarios:
 *   1. Legacy frozen — course ended, data from itop tables (read-only).
 *   2. itop live — course active, live data from itop tables.
 *   3. Own plugin — redirects to standard Configurable Reports view.
 *
 * @package    block_configurable_reports
 * @subpackage legacy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/blocks/configurable_reports/locallib.php');

use block_configurable_reports\legacy\detector;
use block_configurable_reports\legacy\reader;
use block_configurable_reports\legacy\exporter;

$courseid = required_param('courseid', PARAM_INT);
$section  = optional_param('section', 'summary', PARAM_ALPHA);
$userid   = optional_param('userid', 0, PARAM_INT);
$download = optional_param('download', '', PARAM_ALPHA);
$embed    = optional_param('embed', false, PARAM_BOOL);

// Validate course.
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

// Authentication.
if ((int) $course->id === SITEID) {
    require_login();
    $context = context_system::instance();
} else {
    require_login($course);
    $context = context_course::instance($course->id);
}

// Capability check — same as viewing reports.
require_capability('block/configurable_reports:viewreports', $context);

// Detect scenario.
$scenario = detector::detect($courseid);

// Scenario 3 → redirect to standard reports.
if ($scenario === detector::SCENARIO_OWN_PLUGIN) {
    $url = new moodle_url('/blocks/configurable_reports/managereport.php', [
        'courseid' => $courseid,
    ]);
    redirect($url, get_string('legacy_no_itop_data', 'block_configurable_reports'));
}

$scenarioinfo = detector::get_scenario_info($courseid);
$legacyreader = new reader($courseid);

// Handle download requests.
if (!empty($download)) {
    $exportr = new exporter($courseid);
    switch ($download) {
        case 'dedication':
            $exportr->export_dedication_excel();
            break;
        case 'scorm':
            $exportr->export_scorm_excel();
            break;
        case 'summary':
            $exportr->export_summary_excel();
            break;
    }
    // export methods call die() internally.
}

// Page setup.
$PAGE->set_context($context);
$PAGE->set_url('/blocks/configurable_reports/legacy_view.php', [
    'courseid' => $courseid,
    'section'  => $section,
]);
$PAGE->set_pagelayout($embed ? 'embedded' : 'incourse');

$pagetitle = get_string('legacy_view_title', 'block_configurable_reports');
$PAGE->set_title($pagetitle);
$PAGE->set_heading($pagetitle);
$PAGE->navbar->add(get_string('legacy_breadcrumb', 'block_configurable_reports'));

$PAGE->requires->css(new moodle_url('/blocks/configurable_reports/legacy_styles.css'));

echo $OUTPUT->header();

// ---- Scenario badge ----
$badgeclass = ($scenario === detector::SCENARIO_LEGACY_FROZEN) ? 'badge-frozen' : 'badge-live';
$badgelabel = $scenarioinfo->label;
echo html_writer::start_div('cr-legacy-header');
echo html_writer::tag('span', $badgelabel, ['class' => 'cr-legacy-badge ' . $badgeclass]);
if ($scenarioinfo->readonly) {
    echo html_writer::tag('span',
        get_string('legacy_readonly_notice', 'block_configurable_reports'),
        ['class' => 'cr-legacy-readonly']
    );
}
echo html_writer::end_div();

// ---- Section tabs ----
$sections = [
    'summary'    => get_string('legacy_tab_summary', 'block_configurable_reports'),
    'dedication' => get_string('legacy_tab_dedication', 'block_configurable_reports'),
    'scorm'      => get_string('legacy_tab_scorm', 'block_configurable_reports'),
    'videoconf'  => get_string('legacy_tab_videoconf', 'block_configurable_reports'),
    'daily'      => get_string('legacy_tab_daily', 'block_configurable_reports'),
    'values'     => get_string('legacy_tab_values', 'block_configurable_reports'),
];

$tabs = [];
$toprow = [];
foreach ($sections as $key => $label) {
    $taburl = new moodle_url('/blocks/configurable_reports/legacy_view.php', [
        'courseid' => $courseid,
        'section'  => $key,
    ]);
    $toprow[] = new tabobject($key, $taburl, $label);
}
$tabs[] = $toprow;
print_tabs($tabs, $section);

// ---- Section content ----
echo html_writer::start_div('cr-legacy-content');

switch ($section) {
    case 'summary':
        render_summary($legacyreader, $courseid, $scenarioinfo);
        break;
    case 'dedication':
        render_dedication($legacyreader, $courseid, $userid);
        break;
    case 'scorm':
        render_scorm($legacyreader, $courseid, $userid);
        break;
    case 'videoconf':
        render_videoconf($legacyreader, $courseid, $userid);
        break;
    case 'daily':
        render_daily($legacyreader, $courseid, $userid);
        break;
    case 'values':
        render_values($legacyreader, $courseid, $userid);
        break;
    default:
        render_summary($legacyreader, $courseid, $scenarioinfo);
}

echo html_writer::end_div(); // .cr-legacy-content

// Export buttons.
echo html_writer::start_div('cr-legacy-export');
echo html_writer::tag('h4', get_string('legacy_export_heading', 'block_configurable_reports'));

$exporturl = new moodle_url('/blocks/configurable_reports/legacy_view.php', [
    'courseid' => $courseid,
]);

$exportbuttons = [
    'dedication' => get_string('legacy_export_dedication', 'block_configurable_reports'),
    'scorm'      => get_string('legacy_export_scorm', 'block_configurable_reports'),
    'summary'    => get_string('legacy_export_summary', 'block_configurable_reports'),
];

foreach ($exportbuttons as $type => $label) {
    $dlurl = new moodle_url($exporturl, ['download' => $type]);
    echo html_writer::link($dlurl, $label, ['class' => 'btn btn-outline-secondary mr-2 mb-2']);
}
echo html_writer::end_div();

echo $OUTPUT->footer();

// =============================================================================
// Render functions.
// =============================================================================

/**
 * Render the executive summary section.
 */
function render_summary(reader $rdr, int $courseid, \stdClass $info): void {
    $summary = $rdr->get_executive_summary();

    if (!$summary->has_data) {
        echo html_writer::div(
            get_string('legacy_no_data', 'block_configurable_reports'),
            'alert alert-info'
        );
        return;
    }

    echo html_writer::start_div('cr-legacy-summary-grid');

    // Cards.
    $cards = [];
    $cards[] = [
        'label' => get_string('legacy_summary_students', 'block_configurable_reports'),
        'value' => $summary->total_students,
    ];
    $cards[] = [
        'label' => get_string('legacy_summary_avgdedication', 'block_configurable_reports'),
        'value' => reader::format_dedication($summary->avg_dedication),
    ];
    if (!empty($summary->course_hours)) {
        $cards[] = [
            'label' => get_string('legacy_summary_coursehours', 'block_configurable_reports'),
            'value' => $summary->course_hours . 'h',
        ];
    }
    if (!empty($summary->scorm_count)) {
        $cards[] = [
            'label' => get_string('legacy_summary_scorms', 'block_configurable_reports'),
            'value' => $summary->scorm_count,
        ];
    }
    if (!empty($summary->videoconf_records)) {
        $cards[] = [
            'label' => get_string('legacy_summary_videoconf', 'block_configurable_reports'),
            'value' => $summary->videoconf_records,
        ];
    }
    if (!empty($summary->first_record)) {
        $cards[] = [
            'label' => get_string('legacy_summary_datarange', 'block_configurable_reports'),
            'value' => userdate($summary->first_record, '%d/%m/%Y') . ' - ' .
                       userdate($summary->last_record, '%d/%m/%Y'),
        ];
    }

    foreach ($cards as $card) {
        echo html_writer::start_div('cr-legacy-card');
        echo html_writer::tag('div', $card['value'], ['class' => 'cr-legacy-card-value']);
        echo html_writer::tag('div', $card['label'], ['class' => 'cr-legacy-card-label']);
        echo html_writer::end_div();
    }

    echo html_writer::end_div(); // .cr-legacy-summary-grid
}

/**
 * Render the dedication times table.
 */
function render_dedication(reader $rdr, int $courseid, int $userid): void {
    $data = $rdr->get_dedication_times($userid);

    if (empty($data)) {
        echo html_writer::div(
            get_string('legacy_no_data', 'block_configurable_reports'),
            'alert alert-info'
        );
        return;
    }

    $table = new html_table();
    $table->head = [
        get_string('legacy_col_firstname', 'block_configurable_reports'),
        get_string('legacy_col_lastname', 'block_configurable_reports'),
        get_string('legacy_col_email', 'block_configurable_reports'),
        get_string('legacy_col_dedication_formatted', 'block_configurable_reports'),
        get_string('legacy_col_dedication_seconds', 'block_configurable_reports'),
        get_string('legacy_col_graceperiods', 'block_configurable_reports'),
        get_string('legacy_col_timemodified', 'block_configurable_reports'),
    ];
    $table->attributes['class'] = 'generaltable cr-legacy-table';

    foreach ($data as $record) {
        $table->data[] = [
            format_string($record->firstname),
            format_string($record->lastname),
            $record->email,
            reader::format_dedication((int)$record->dedicationtime),
            (int)$record->dedicationtime,
            (int)($record->graceperiods ?? 0),
            !empty($record->timemodified) ? userdate((int)$record->timemodified) : '-',
        ];
    }

    echo html_writer::table($table);
}

/**
 * Render the SCORM times table.
 */
function render_scorm(reader $rdr, int $courseid, int $userid): void {
    $data = $rdr->get_scorm_times($userid);

    if (empty($data)) {
        echo html_writer::div(
            get_string('legacy_no_data', 'block_configurable_reports'),
            'alert alert-info'
        );
        return;
    }

    $table = new html_table();
    $table->head = [
        get_string('legacy_col_firstname', 'block_configurable_reports'),
        get_string('legacy_col_lastname', 'block_configurable_reports'),
        get_string('legacy_col_scormname', 'block_configurable_reports'),
        get_string('legacy_col_attempt', 'block_configurable_reports'),
        get_string('legacy_col_dedication_formatted', 'block_configurable_reports'),
        get_string('legacy_col_timemodified', 'block_configurable_reports'),
    ];
    $table->attributes['class'] = 'generaltable cr-legacy-table';

    foreach ($data as $record) {
        $table->data[] = [
            format_string($record->firstname),
            format_string($record->lastname),
            format_string($record->scormname ?? '-'),
            (int)($record->attempt ?? 0),
            reader::format_dedication((int)($record->dedicationtime ?? 0)),
            !empty($record->timemodified) ? userdate((int)$record->timemodified) : '-',
        ];
    }

    echo html_writer::table($table);
}

/**
 * Render the videoconference attendance table.
 */
function render_videoconf(reader $rdr, int $courseid, int $userid): void {
    $data = $rdr->get_videoconference_data($userid);

    if (empty($data)) {
        echo html_writer::div(
            get_string('legacy_no_data', 'block_configurable_reports'),
            'alert alert-info'
        );
        return;
    }

    $table = new html_table();
    $table->head = [
        get_string('legacy_col_firstname', 'block_configurable_reports'),
        get_string('legacy_col_lastname', 'block_configurable_reports'),
        get_string('legacy_col_jointime', 'block_configurable_reports'),
        get_string('legacy_col_leavetime', 'block_configurable_reports'),
        get_string('legacy_col_duration', 'block_configurable_reports'),
    ];
    $table->attributes['class'] = 'generaltable cr-legacy-table';

    foreach ($data as $record) {
        $table->data[] = [
            format_string($record->firstname),
            format_string($record->lastname),
            !empty($record->timestart) ? $record->timestart : '-',
            !empty($record->timeend) ? $record->timeend : '-',
            reader::format_dedication((int)($record->duration ?? 0)),
        ];
    }

    echo html_writer::table($table);
}

/**
 * Render the daily stats table.
 */
function render_daily(reader $rdr, int $courseid, int $userid): void {
    $data = $rdr->get_daily_stats($userid);

    if (empty($data)) {
        echo html_writer::div(
            get_string('legacy_no_data', 'block_configurable_reports'),
            'alert alert-info'
        );
        return;
    }

    $table = new html_table();
    $table->head = [
        get_string('legacy_col_firstname', 'block_configurable_reports'),
        get_string('legacy_col_lastname', 'block_configurable_reports'),
        get_string('legacy_col_stat', 'block_configurable_reports'),
        get_string('legacy_col_value', 'block_configurable_reports'),
        get_string('legacy_col_date', 'block_configurable_reports'),
    ];
    $table->attributes['class'] = 'generaltable cr-legacy-table';

    foreach ($data as $record) {
        $table->data[] = [
            format_string($record->firstname),
            format_string($record->lastname),
            $record->stat,
            $record->value,
            !empty($record->timeday) ? userdate((int)$record->timeday, '%d/%m/%Y') : '-',
        ];
    }

    echo html_writer::table($table);
}

/**
 * Render the predefined report values table.
 */
function render_values(reader $rdr, int $courseid, int $userid): void {
    $data = $rdr->get_report_values($userid);

    if (empty($data)) {
        echo html_writer::div(
            get_string('legacy_no_data', 'block_configurable_reports'),
            'alert alert-info'
        );
        return;
    }

    $table = new html_table();
    $table->head = [
        get_string('legacy_col_firstname', 'block_configurable_reports'),
        get_string('legacy_col_lastname', 'block_configurable_reports'),
        get_string('legacy_col_reportname', 'block_configurable_reports'),
        get_string('legacy_col_stat', 'block_configurable_reports'),
        get_string('legacy_col_value', 'block_configurable_reports'),
    ];
    $table->attributes['class'] = 'generaltable cr-legacy-table';

    foreach ($data as $record) {
        $table->data[] = [
            format_string($record->firstname),
            format_string($record->lastname),
            format_string($record->reportname ?? '-'),
            $record->stat,
            $record->value,
        ];
    }

    echo html_writer::table($table);
}
