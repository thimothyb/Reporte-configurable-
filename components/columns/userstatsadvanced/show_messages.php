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
 * Message detail view for userstatsadvanced.
 * Query logic lives in messages_lib.php.
 *
 * @package    block_configurable_reports
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../../../../config.php");
require_once(__DIR__ . '/messages_lib.php');

$courseid = required_param('courseid', PARAM_INT);
$userid   = required_param('userid',   PARAM_INT);
$stattype = optional_param('stat_type', '', PARAM_ALPHANUMEXT);
$reportid = optional_param('reportid',   0, PARAM_INT);

$course     = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$targetuser = $DB->get_record('user',   ['id' => $userid],   '*', MUST_EXIST);
if (!empty($targetuser->deleted)) {
    throw new moodle_exception('invaliduser');
}

if ((int)$course->id === SITEID) {
    require_login();
    $context = context_system::instance();
} else {
    require_login($course);
    $context = context_course::instance($course->id);
}

$url = new moodle_url(
    '/blocks/configurable_reports/components/columns/userstatsadvanced/show_messages.php',
    ['courseid' => $courseid, 'userid' => $userid, 'stat_type' => $stattype]
);
if ($reportid > 0) {
    $url->param('reportid', $reportid);
}

$isforumstat = userstatsadvanced_show_messages_is_forum_stat($stattype);
$title = $isforumstat
    ? get_string('userstatsadvanced_forum_messages_detail', 'block_configurable_reports')
    : get_string('userstatsadvanced_messages_detail',       'block_configurable_reports');

$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_url($url);
$PAGE->set_title($title);
$PAGE->set_heading(format_string($course->fullname));

$records = $isforumstat
    ? userstatsadvanced_show_messages_get_forum_records($userid, $courseid, $context)
    : userstatsadvanced_show_messages_get_direct_records($userid, $courseid, $stattype, $context);

$table = new html_table();
$table->attributes['class'] = 'generaltable table table-striped';
$table->data = [];

if ($isforumstat) {
    $table->head = [
        get_string('userstatsadvanced_from',    'block_configurable_reports'),
        get_string('userstatsadvanced_forum',   'block_configurable_reports'),
        get_string('userstatsadvanced_subject', 'block_configurable_reports'),
        get_string('userstatsadvanced_message', 'block_configurable_reports'),
        get_string('userstatsadvanced_created', 'block_configurable_reports'),
    ];
    foreach ($records as $record) {
        $table->data[] = [
            s($record['fromname']),
            s($record['forumname']),
            s($record['subject']),
            $record['messagehtml'],
            $record['createdhtml'],
        ];
    }
} else {
    $table->head = [
        get_string('userstatsadvanced_from',    'block_configurable_reports'),
        get_string('userstatsadvanced_to',      'block_configurable_reports'),
        get_string('userstatsadvanced_subject', 'block_configurable_reports'),
        get_string('userstatsadvanced_message', 'block_configurable_reports'),
        get_string('userstatsadvanced_created', 'block_configurable_reports'),
    ];
    foreach ($records as $record) {
        $table->data[] = [
            s($record['fromname']),
            s($record['toname']),
            s($record['subject']),
            $record['messagehtml'],
            $record['createdhtml'],
        ];
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
echo html_writer::div(
    get_string('userstatsadvanced_user_label', 'block_configurable_reports') . ': ' . fullname($targetuser),
    'mb-3'
);
if (empty($table->data)) {
    echo $OUTPUT->notification(get_string('norecordsfound', 'block_configurable_reports'));
}
echo html_writer::start_div('table-responsive');
echo html_writer::table($table);
echo html_writer::end_div();
echo $OUTPUT->footer();
