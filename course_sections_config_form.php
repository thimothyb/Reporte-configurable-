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

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for configuring which course sections the reports draw data from.
 *
 * @package block_configurable_reports
 */
class course_sections_config_form extends moodleform {

    /**
     * Form definition.
     */
    public function definition() {
        $mform      = $this->_form;
        $sections   = $this->_customdata['sections'];
        $courseid   = $this->_customdata['courseid'];

        $mform->addElement(
            'checkbox',
            'disable_sections',
            get_string('coursesections_disable', 'block_configurable_reports')
        );

        $options = [];
        foreach ($sections as $section) {
            $options[$section->id] = format_string($section->name);
        }

        $select = $mform->addElement(
            'select',
            'selected_sections',
            get_string('coursesectionsconfig', 'block_configurable_reports'),
            $options,
            ['size' => max(4, min(count($sections), 10))]
        );
        $select->setMultiple(true);
        $mform->addRule('selected_sections', null, 'required', null, 'client');

        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
