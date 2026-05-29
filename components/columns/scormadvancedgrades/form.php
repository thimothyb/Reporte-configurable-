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
 * Configurable Reports a Moodle block for creating customizable reports.
 *
 * @copyright  2026
 * @package    block_configurable_reports
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for SCORM advanced grades / activity-resource statistics column plugin.
 *
 * @package   block_configurable_reports
 */
class scormadvancedgrades_form extends moodleform {

    /**
     * Form definition.
     *
     * @return void
     */
    public function definition(): void {
        $mform =& $this->_form;

        $mform->addElement('header', 'crformheader', get_string('scormadvancedgrades', 'block_configurable_reports'), '');

        // Main statistics selector + format selector.
        $this->_customdata['pluginclass']->add_stat_selector_to_form($mform);

        // Common column configuration (name, align, size, wrap).
        $this->_customdata['compclass']->add_form_elements($mform, $this);

        // Buttons.
        $this->add_action_buttons(true, get_string('add'));
    }

    /**
     * Server side rules.
     *
     * @param array $data  array of submitted data.
     * @param array $files array of uploaded files.
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $errors = $this->_customdata['compclass']->validate_form_elements($data, $errors);

        if (empty($data['stat']) || (string)$data['stat'] === '0') {
            $errors['stat'] = get_string('required');
        }

        return $errors;
    }

}
