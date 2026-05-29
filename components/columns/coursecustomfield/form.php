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
 * Configuration form for coursecustomfield column plugin.
 *
 * @package   block_configurable_reports
 */
class coursecustomfield_form extends moodleform {

    /**
     * Form definition.
     *
     * @return void
     */
    public function definition(): void {
        $mform =& $this->_form;

        $pluginlabel = $this->_customdata['pluginclass']->get_plugin_title_label();
        $fieldlabel = $this->_customdata['pluginclass']->get_plugin_field_label();

        $mform->addElement('header', 'crformheader', $pluginlabel, '');

        // Dynamic selector with all available course fields (standard + custom).
        $fieldoptions = $this->_customdata['pluginclass']->get_fields_for_form();
        $mform->addElement('select', 'field', $fieldlabel, $fieldoptions);
        $mform->setType('field', PARAM_RAW_TRIMMED);
        $mform->addRule('field', get_string('required'), 'required', null, 'server');

        // Shared column properties: header name, align, size, wrap...
        $this->_customdata['compclass']->add_form_elements($mform, $this);

        // Buttons.
        $this->add_action_buttons(true, get_string('add'));
    }

    /**
     * Server-side validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $errors = $this->_customdata['compclass']->validate_form_elements($data, $errors);

        $selectedfield = isset($data['field']) ? (string)$data['field'] : '';
        if (!$this->_customdata['pluginclass']->is_valid_field_selection($selectedfield)) {
            $errors['field'] = get_string('required');
        }

        return $errors;
    }

}
