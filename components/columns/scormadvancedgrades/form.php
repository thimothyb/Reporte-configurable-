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
     * Returns localized string if available; otherwise fallback text.
     *
     * @param string $identifier
     * @param string $fallback
     * @return string
     */
    protected function get_localized_label(string $identifier, string $fallback): string {
        $stringmanager = get_string_manager();
        if ($stringmanager->string_exists($identifier, 'block_configurable_reports')) {
            return get_string($identifier, 'block_configurable_reports');
        }
        return $fallback;
    }

    /**
     * Returns popup URL for adding multiple progress columns.
     *
     * @return string
     */
    protected function get_add_multiple_popup_url(): string {
        $params = [
            'id' => !empty($this->_customdata['id']) ? (int)$this->_customdata['id'] : 0,
        ];

        $url = new moodle_url('/blocks/configurable_reports/components/columns/scormadvancedgrades/addmultiple.php', $params);
        return $url->out(false);
    }

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

        // Requested UX: keep format visible but locked to percentage by default.
        $mform->setDefault('format', 'percent');
        if ($mform->elementExists('format')) {
            $mform->hardFreeze('format');
        }

        $popupurljson = json_encode($this->get_add_multiple_popup_url());
        $openpopupjs = "(function(){\n" .
            "  var popupurl = " . $popupurljson . ";\n" .
            "  window.open(popupurl, 'scormadvancedgrades_addmultiple', 'width=1200,height=780,scrollbars=yes,resizable=yes');\n" .
            "})(); return false;";
        $mform->addElement(
            'button',
            'addmultiplecolumnsbutton',
            $this->get_localized_label('scormadvancedgrades_addmultiplecolumns', 'Añadir varias columnas'),
            ['type' => 'button', 'onclick' => $openpopupjs]
        );

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
