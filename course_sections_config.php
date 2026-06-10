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
require_once($CFG->dirroot . "/blocks/configurable_reports/course_sections_config_form.php");

$courseid = required_param('courseid', PARAM_INT);

if (!$course = $DB->get_record('course', ['id' => $courseid])) {
    throw new moodle_exception('invalidcourseid');
}

require_login($course);
$context = context_course::instance($course->id);
require_capability('block/configurable_reports:managereports', $context);

$PAGE->set_url('/blocks/configurable_reports/course_sections_config.php', ['courseid' => $courseid]);
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');

// Fetch named sections (section 0 is the intro, skip it).
$sections = $DB->get_records_select(
    'course_sections',
    "course = :courseid AND section > 0 AND name IS NOT NULL AND name <> ''",
    ['courseid' => $courseid],
    'section ASC',
    'id, section, name'
);

$disabledkey = 'course_sections_disabled_' . $courseid;
$sectionskey = 'course_sections_config_' . $courseid;

$mform = new course_sections_config_form(null, [
    'sections' => $sections,
    'courseid' => $courseid,
]);

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/course/view.php', ['id' => $courseid]));

} else if ($data = $mform->get_data()) {
    set_config($disabledkey, !empty($data->disable_sections) ? 1 : 0, 'block_configurable_reports');
    set_config($sectionskey, json_encode($data->selected_sections ?? []), 'block_configurable_reports');

    redirect(
        new moodle_url('/blocks/configurable_reports/course_sections_config.php', ['courseid' => $courseid]),
        get_string('changessaved')
    );

} else {
    // Pre-populate form with saved values.
    $defaults                   = new stdClass();
    $defaults->courseid         = $courseid;
    $defaults->disable_sections = (int) get_config('block_configurable_reports', $disabledkey);
    $raw                        = get_config('block_configurable_reports', $sectionskey);
    $defaults->selected_sections = ($raw !== false && $raw !== '') ? json_decode($raw, true) : [];
    $mform->set_data($defaults);
}

$title = get_string('coursesectionsconfig', 'block_configurable_reports');
$PAGE->navbar->add(get_string('managereports', 'block_configurable_reports'),
    new moodle_url('/blocks/configurable_reports/managereport.php', ['courseid' => $courseid]));
$PAGE->navbar->add($title);
$PAGE->set_title($title);
$PAGE->set_heading($title);

echo $OUTPUT->header();
$mform->display();
echo $OUTPUT->footer();
