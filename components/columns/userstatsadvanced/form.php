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
 * @package    block_configurable_reports
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/formslib.php');

/**
 * Class userstatsadvanced_form
 *
 * @package   block_configurable_reports
 */
class userstatsadvanced_form extends moodleform {

    /**
     * Returns translated text if available, otherwise fallback.
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
     * Returns selected course module IDs from current component data (edit mode).
     *
     * @return string
     */
    protected function get_current_selected_cmids_from_customdata(): string {
        if (empty($this->_customdata['cid']) || empty($this->_customdata['comp']) || empty($this->_customdata['report']->components)) {
            return '';
        }

        $components = cr_unserialize($this->_customdata['report']->components);
        $componentname = (string)$this->_customdata['comp'];
        $componentid = (string)$this->_customdata['cid'];
        $elements = $components[$componentname]['elements'] ?? [];

        if (empty($elements) || !is_array($elements)) {
            return '';
        }

        foreach ($elements as $element) {
            if (!is_array($element) || !array_key_exists('id', $element)) {
                continue;
            }
            if ((string)$element['id'] !== $componentid) {
                continue;
            }

            $formdata = $element['formdata'] ?? null;
            if (is_object($formdata) && !empty($formdata->selectedcmids)) {
                return (string)$formdata->selectedcmids;
            }
            if (is_array($formdata) && !empty($formdata['selectedcmids'])) {
                return (string)$formdata['selectedcmids'];
            }
            break;
        }

        return '';
    }

    /**
     * Parses selected course module IDs from CSV text.
     *
     * @param string $selectedcmidsraw
     * @return array<int>
     */
    protected function parse_selected_cmids(string $selectedcmidsraw): array {
        $selectedcmidsraw = trim($selectedcmidsraw);
        if ($selectedcmidsraw === '') {
            return [];
        }

        $parts = preg_split('/[\s,;]+/', $selectedcmidsraw, -1, PREG_SPLIT_NO_EMPTY);
        if (empty($parts)) {
            return [];
        }

        $ids = [];
        foreach ($parts as $part) {
            $id = (int)$part;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    /**
     * Returns current selected modules label text.
     *
     * @param int $courseid
     * @param string $selectedcmidsraw
     * @return string
     */
    protected function get_selected_cmids_label_text(int $courseid, string $selectedcmidsraw): string {
        $selectedcmids = $this->parse_selected_cmids($selectedcmidsraw);
        if (empty($selectedcmids)) {
            return $this->get_localized_label(
                'userstatsadvanced_selected_items_all',
                'Se usarán todos los recursos visibles del curso.'
            );
        }

        $counttemplate = $this->get_localized_label(
            'userstatsadvanced_selected_items_count',
            'Elementos seleccionados: {$a}'
        );
        $counttext = str_replace('{$a}', (string)count($selectedcmids), $counttemplate);

        if ($courseid <= SITEID) {
            return $counttext;
        }

        $modinfo = get_fast_modinfo($courseid);
        $selectednames = [];
        foreach ($selectedcmids as $cmid) {
            if (empty($modinfo->cms[$cmid])) {
                continue;
            }
            $cm = $modinfo->cms[$cmid];
            if ((int)$cm->visible !== 1 || $cm->modname === 'label') {
                continue;
            }
            $name = trim((string)$cm->name);
            if ($name === '') {
                $name = 'ID ' . $cmid;
            }
            $selectednames[] = format_string($name);
        }

        if (empty($selectednames)) {
            return $counttext;
        }

        $preview = implode(', ', array_slice($selectednames, 0, 3));
        if (count($selectednames) > 3) {
            $preview .= ' (+' . (count($selectednames) - 3) . ')';
        }

        return $counttext . ' — ' . $preview;
    }

    /**
     * Returns popup URL for course module selector.
     *
     * @param string $modname
     * @return string
     */
    protected function get_selector_popup_url(string $modname = ''): string {
        $params = [
            'id' => !empty($this->_customdata['id']) ? (int)$this->_customdata['id'] : 0,
        ];
        if (!empty($this->_customdata['cid'])) {
            $params['cid'] = (string)$this->_customdata['cid'];
        }
        if ($modname !== '') {
            $params['modname'] = $modname;
        }

        $url = new moodle_url('/blocks/configurable_reports/components/columns/userstatsadvanced/select_course_modules.php', $params);
        return $url->out(false);
    }

    /**
     * Returns available reports for linked-modal selector.
     *
     * @return array
     */
    protected function get_modal_report_options(): array {
        global $DB;

        $options = [
            0 => $this->get_localized_label('userstatsadvanced_modalreport_no', 'No'),
        ];

        $courseid = !empty($this->_customdata['report']->courseid) ? (int)$this->_customdata['report']->courseid : 0;
        $currentreportid = !empty($this->_customdata['id']) ? (int)$this->_customdata['id'] : 0;
        if ($courseid <= 0) {
            return $options;
        }

        $params = [
            'courseid' => $courseid,
            'currentid' => $currentreportid,
        ];
        $sql = "SELECT id, name
                  FROM {block_configurable_reports}
                 WHERE (courseid = :courseid OR global = 1)
                   AND id <> :currentid
              ORDER BY name ASC, id ASC";
        $reports = $DB->get_records_sql($sql, $params);

        foreach ($reports as $report) {
            $options[(int)$report->id] = format_string((string)$report->name);
        }

        return $options;
    }

    /**
     * Form definition.
     *
     * @return void
     */
    public function definition(): void {
        $mform =& $this->_form;

        $mform->addElement('header', 'crformheader', $this->get_localized_label('userstatsadvanced', 'user_stats_advanced'), '');

        $statoptions = [
            'actividades_aprendizaje' => $this->get_localized_label(
                'userstatsadvanced_actividades_aprendizaje_moodle_completion',
                'Tareas completadas / Total (criterio de finalización Moodle)'
            ),
            'tareas_entregadas' => $this->get_localized_label('userstatsadvanced_tareas_entregadas', 'Tareas entregadas (completadas / total)'),
            'evaluaciones' => $this->get_localized_label(
                'userstatsadvanced_evaluaciones_moodle_completion',
                'Cuestionarios completados / Total (criterio de finalización Moodle)'
            ),
            'intentos_cuestionario' => $this->get_localized_label('userstatsadvanced_intentos_cuestionario', 'Intentos de cuestionario (completados / total)'),
            'contenidos_visualizados' => $this->get_localized_label('userstatsadvanced_contenidos_visualizados', 'Contenidos visualizados (progreso global)'),
            'recursos_completados' => $this->get_localized_label('userstatsadvanced_recursos_completados', 'Recursos completados / Total'),
            'finalizacion_cruzada' => $this->get_localized_label('userstatsadvanced_finalizacion_cruzada', 'Finalización cruzada (progreso global)'),
            'correos' => $this->get_localized_label('userstatsadvanced_correos', 'Correos (interacción con docentes)'),
            'mensajes_tutor' => $this->get_localized_label('userstatsadvanced_mensajes_tutor', 'Mensajes al tutor'),
            'mensajes_alumnos' => $this->get_localized_label('userstatsadvanced_mensajes_alumnos', 'Mensajes con alumnos'),
            'registros' => $this->get_localized_label('userstatsadvanced_registros', 'Registros (eventos en logs)'),
            'logs_integracion' => $this->get_localized_label('userstatsadvanced_logs_integracion', 'Eventos de integración'),
            'dias_conexion' => $this->get_localized_label('userstatsadvanced_dias_conexion', 'Días de conexión'),
            'tiempos_conexion_diarios_html' => $this->get_localized_label('userstatsadvanced_tiempos_conexion_diarios_html', 'Tabla HTML de tiempos de conexión diarios'),
            'interacciones_foros' => $this->get_localized_label('userstatsadvanced_interacciones_foros', 'Interacciones en foros'),
            'mensajes_foro' => $this->get_localized_label('userstatsadvanced_mensajes_foro', 'Mensajes en foro'),
            'foros_publicados' => $this->get_localized_label('userstatsadvanced_foros_publicados', 'Mensajes publicados en foros'),
            'ips_utilizadas' => $this->get_localized_label('userstatsadvanced_ips_utilizadas', 'IPs utilizadas'),
            'ultima_ip' => $this->get_localized_label('userstatsadvanced_ultima_ip', 'Última IP'),
            'nota_final' => $this->get_localized_label('userstatsadvanced_nota_final', 'Nota final del curso'),
            'primer_acceso' => $this->get_localized_label('userstatsadvanced_primer_acceso', 'Primer acceso'),
            'ultimo_acceso' => $this->get_localized_label('userstatsadvanced_ultimo_acceso', 'Último acceso'),
            'primer_acceso_scorm' => $this->get_localized_label('userstatsadvanced_primer_acceso_scorm', 'Primer acceso SCORM'),
            'matricula_activa' => $this->get_localized_label('userstatsadvanced_matricula_activa', 'Matrícula activa'),
            'scorm_completados' => $this->get_localized_label('userstatsadvanced_scorm_completados', 'SCORM completados/aprobados'),
            'tiempo_total' => $this->get_localized_label('userstatsadvanced_tiempo_total', 'Tiempo total de dedicación (formato horas)'),
        ];

        $mform->addElement(
            'select',
            'stat_type',
            $this->get_localized_label('userstatsadvanced_stattype', 'Tipo de estadística'),
            $statoptions
        );
        $mform->setType('stat_type', PARAM_ALPHANUMEXT);
        $mform->addRule('stat_type', get_string('required'), 'required', null, 'server');

        $mform->addElement(
            'text',
            'maxdisplayvalue',
            $this->get_localized_label('userstatsadvanced_maxdisplayvalue', 'Valor máximo que se mostrará (0-100)')
        );
        $mform->setType('maxdisplayvalue', PARAM_INT);
        $mform->setDefault('maxdisplayvalue', 100);

        $displayformatoptions = [
            'numdenum_percent' => $this->get_localized_label(
                'userstatsadvanced_displayformat_numdenum_percent',
                'Num / Denum (%)'
            ),
            'percent' => $this->get_localized_label('userstatsadvanced_displayformat_percent', '%'),
            'progressbar' => $this->get_localized_label('userstatsadvanced_displayformat_progress', 'Barra de progreso'),
        ];
        $mform->addElement(
            'select',
            'displayformat',
            $this->get_localized_label('userstatsadvanced_displayformat', 'Formato de visualización'),
            $displayformatoptions
        );
        $mform->setType('displayformat', PARAM_ALPHANUMEXT);
        $mform->setDefault('displayformat', 'numdenum_percent');

        $mform->addElement(
            'select',
            'modalreportid',
            $this->get_localized_label('userstatsadvanced_modalreport', 'Mostrar informe enlazado en ventana modal'),
            $this->get_modal_report_options()
        );
        $mform->setType('modalreportid', PARAM_INT);
        $mform->setDefault('modalreportid', 0);

        $currentselectedcmids = $this->get_current_selected_cmids_from_customdata();
        $courseid = !empty($this->_customdata['report']->courseid) ? (int)$this->_customdata['report']->courseid : 0;

        $mform->addElement('hidden', 'selectedcmids', $currentselectedcmids);
        $mform->setType('selectedcmids', PARAM_RAW_TRIMMED);

        $selectedcmidslabel = $this->get_selected_cmids_label_text($courseid, $currentselectedcmids);
        $mform->addElement(
            'static',
            'selectedcmidslabel',
            $this->get_localized_label('userstatsadvanced_elements_filter', 'Elementos a tener en cuenta'),
            html_writer::div(
                s($selectedcmidslabel),
                '',
                ['id' => 'userstatsadvanced-selectedcmids-display']
            )
        );

        $popupurlalljson = json_encode($this->get_selector_popup_url());
        $popupurlquizjson = json_encode($this->get_selector_popup_url('quiz'));
        $popupurlassignjson = json_encode($this->get_selector_popup_url('assign'));
        $openpopuponclick = "(function() {\n" .
            "  var hiddenfield = document.getElementById('id_selectedcmids');\n" .
            "  var statfield = document.getElementById('id_stat_type');\n" .
            "  var stattype = (statfield && statfield.value) ? statfield.value : '';\n" .
            "  var selected = (hiddenfield && hiddenfield.value) ? hiddenfield.value : '';\n" .
            "  var popupbase = " . $popupurlalljson . ";\n" .
            "  if (stattype === 'evaluaciones') {\n" .
            "    popupbase = " . $popupurlquizjson . ";\n" .
            "  } else if (stattype === 'actividades_aprendizaje') {\n" .
            "    popupbase = " . $popupurlassignjson . ";\n" .
            "  }\n" .
            "  var popupurl = popupbase + '&selectedcmids=' + encodeURIComponent(selected);\n" .
            "  window.open(popupurl, 'userstatsadvanced_selectmodules', 'width=1150,height=750,scrollbars=yes,resizable=yes');\n" .
            "})(); return false;";
        $mform->addElement(
            'button',
            'selectcoursemodulesbutton',
            $this->get_localized_label('userstatsadvanced_select_course_modules', 'Seleccionar actividades/recursos'),
            ['type' => 'button', 'onclick' => $openpopuponclick]
        );

        $mform->addElement(
            'static',
            'selectedcmidshelp',
            '',
            html_writer::tag(
                'small',
                s(
                    $this->get_localized_label(
                        'userstatsadvanced_select_course_modules_help',
                        'Si no seleccionas elementos, se usarán todos los recursos visibles del curso.'
                    )
                ),
                ['class' => 'text-muted', 'id' => 'userstatsadvanced-selectedcmids-help']
            )
        );

        $defaultselectedtext = $this->get_localized_label(
            'userstatsadvanced_selected_items_all',
            'Se usarán todos los recursos visibles del curso.'
        );
        $defaultselectedquiztext = $this->get_localized_label(
            'userstatsadvanced_elements_filter_default_quizzes',
            'Acceso al curso y cuestionarios'
        );
        $defaultselectedtaskstext = $this->get_localized_label(
            'userstatsadvanced_elements_filter_default_tasks',
            'Acceso al curso y tareas'
        );
        $defaultselectedcompletedresourcestext = $this->get_localized_label(
            'userstatsadvanced_elements_filter_default_completed_resources',
            'Se usarán todos los recursos completados del curso.'
        );
        $selectedcounttemplate = $this->get_localized_label(
            'userstatsadvanced_selected_items_count',
            'Elementos seleccionados: {$a}'
        );
        $selectedcounttemplate = str_replace('{$a}', '__COUNT__', $selectedcounttemplate);
        $selectedquizcounttemplate = $this->get_localized_label(
            'userstatsadvanced_selected_quizzes_count',
            'Cuestionarios seleccionados: {$a}'
        );
        $selectedquizcounttemplate = str_replace('{$a}', '__COUNT__', $selectedquizcounttemplate);
        $selectedtaskscounttemplate = $this->get_localized_label(
            'userstatsadvanced_selected_tasks_count',
            'Tareas seleccionadas: {$a}'
        );
        $selectedtaskscounttemplate = str_replace('{$a}', '__COUNT__', $selectedtaskscounttemplate);
        $defaulthelptext = $this->get_localized_label(
            'userstatsadvanced_select_course_modules_help',
            'Si no seleccionas elementos, se usarán todos los recursos visibles del curso.'
        );
        $defaultquizhelptext = $this->get_localized_label(
            'userstatsadvanced_select_quizzes_help',
            'Si no seleccionas cuestionarios, se usarán todos los cuestionarios visibles del curso.'
        );
        $defaulttaskshelptext = $this->get_localized_label(
            'userstatsadvanced_select_tasks_help',
            'Si no seleccionas tareas, se usarán todas las tareas visibles del curso.'
        );
        $defaultcompletedresourceshelptext = $this->get_localized_label(
            'userstatsadvanced_select_completed_resources_help',
            'Si no seleccionas elementos, se usarán todos los recursos completados del curso.'
        );
        $selectresourceslabel = $this->get_localized_label(
            'userstatsadvanced_select_course_modules',
            'Seleccionar actividades/recursos'
        );
        $selectquizzeslabel = $this->get_localized_label(
            'userstatsadvanced_select_quizzes',
            'Seleccionar cuestionarios'
        );
        $selecttaskslabel = $this->get_localized_label(
            'userstatsadvanced_select_tasks',
            'Seleccionar tareas'
        );

        $defaultselectedtextjson = json_encode($defaultselectedtext);
        $defaultselectedquiztextjson = json_encode($defaultselectedquiztext);
        $defaultselectedtaskstextjson = json_encode($defaultselectedtaskstext);
        $defaultselectedcompletedresourcestextjson = json_encode($defaultselectedcompletedresourcestext);
        $selectedcounttemplatejson = json_encode($selectedcounttemplate);
        $selectedquizcounttemplatejson = json_encode($selectedquizcounttemplate);
        $selectedtaskscounttemplatejson = json_encode($selectedtaskscounttemplate);
        $defaulthelptextjson = json_encode($defaulthelptext);
        $defaultquizhelptextjson = json_encode($defaultquizhelptext);
        $defaulttaskshelptextjson = json_encode($defaulttaskshelptext);
        $defaultcompletedresourceshelptextjson = json_encode($defaultcompletedresourceshelptext);
        $selectresourceslabeljson = json_encode($selectresourceslabel);
        $selectquizzeslabeljson = json_encode($selectquizzeslabel);
        $selecttaskslabeljson = json_encode($selecttaskslabel);

        $script = <<<JS
(function() {
    var defaultText = $defaultselectedtextjson;
    var defaultQuizText = $defaultselectedquiztextjson;
    var defaultTasksText = $defaultselectedtaskstextjson;
    var defaultCompletedResourcesText = $defaultselectedcompletedresourcestextjson;
    var selectedTemplate = $selectedcounttemplatejson;
    var selectedQuizTemplate = $selectedquizcounttemplatejson;
    var selectedTasksTemplate = $selectedtaskscounttemplatejson;
    var defaultHelpText = $defaulthelptextjson;
    var defaultQuizHelpText = $defaultquizhelptextjson;
    var defaultTasksHelpText = $defaulttaskshelptextjson;
    var defaultCompletedResourcesHelpText = $defaultcompletedresourceshelptextjson;
    var selectResourcesLabel = $selectresourceslabeljson;
    var selectQuizzesLabel = $selectquizzeslabeljson;
    var selectTasksLabel = $selecttaskslabeljson;

    function getCurrentStatType() {
        var statField = document.getElementById("id_stat_type");
        if (!statField) {
            return "";
        }
        return statField.value || "";
    }

    function countSelected(raw) {
        if (!raw) {
            return 0;
        }
        var ids = raw.split(/[\\s,;]+/).filter(function(item) {
            return item !== "";
        });
        var unique = {};
        ids.forEach(function(id) {
            var n = parseInt(id, 10);
            if (!isNaN(n) && n > 0) {
                unique[n] = true;
            }
        });
        return Object.keys(unique).length;
    }

    function toggleFormRows() {
        var statType = getCurrentStatType();
        var showAdvancedFields = (statType === "evaluaciones" || statType === "actividades_aprendizaje");
        var showSelectionFields = (
            statType === "evaluaciones" ||
            statType === "actividades_aprendizaje" ||
            statType === "contenidos_visualizados" ||
            statType === "recursos_completados"
        );

        var maxRow = document.getElementById("fitem_id_maxdisplayvalue");
        var formatRow = document.getElementById("fitem_id_displayformat");
        var modalRow = document.getElementById("fitem_id_modalreportid");
        var selectedLabelRow = document.getElementById("fitem_id_selectedcmidslabel");
        var selectedHelpRow = document.getElementById("fitem_id_selectedcmidshelp");
        var selectButtonRow = document.getElementById("fitem_id_selectcoursemodulesbutton");

        if (maxRow) {
            maxRow.style.display = showAdvancedFields ? "" : "none";
        }
        if (formatRow) {
            formatRow.style.display = showAdvancedFields ? "" : "none";
        }
        if (modalRow) {
            modalRow.style.display = showAdvancedFields ? "" : "none";
        }
        if (selectedLabelRow) {
            selectedLabelRow.style.display = showSelectionFields ? "" : "none";
        }
        if (selectedHelpRow) {
            selectedHelpRow.style.display = showSelectionFields ? "" : "none";
        }
        if (selectButtonRow) {
            selectButtonRow.style.display = showSelectionFields ? "" : "none";
        }

        var maxInput = document.getElementById("id_maxdisplayvalue");
        var formatInput = document.getElementById("id_displayformat");
        var modalInput = document.getElementById("id_modalreportid");
        if (maxInput) {
            maxInput.disabled = !showAdvancedFields;
        }
        if (formatInput) {
            formatInput.disabled = !showAdvancedFields;
        }
        if (modalInput) {
            modalInput.disabled = !showAdvancedFields;
        }

        var hidden = document.getElementById("id_selectedcmids");
        if (hidden) {
            hidden.disabled = !showSelectionFields;
        }

        var selectButton = document.getElementById("id_selectcoursemodulesbutton");
        if (selectButton) {
            selectButton.disabled = !showSelectionFields;
            if (statType === "evaluaciones") {
                selectButton.textContent = selectQuizzesLabel;
            } else if (statType === "actividades_aprendizaje") {
                selectButton.textContent = selectTasksLabel;
            } else {
                selectButton.textContent = selectResourcesLabel;
            }
        }

        var helpLabel = document.getElementById("userstatsadvanced-selectedcmids-help");
        if (helpLabel) {
            if (statType === "evaluaciones") {
                helpLabel.textContent = defaultQuizHelpText;
            } else if (statType === "actividades_aprendizaje") {
                helpLabel.textContent = defaultTasksHelpText;
            } else if (statType === "recursos_completados") {
                helpLabel.textContent = defaultCompletedResourcesHelpText;
            } else {
                helpLabel.textContent = defaultHelpText;
            }
        }
    }

    function refreshLabel(customLabel) {
        var label = document.getElementById("userstatsadvanced-selectedcmids-display");
        var hidden = document.getElementById("id_selectedcmids");
        if (!label || !hidden) {
            return;
        }
        if (customLabel && customLabel !== "") {
            label.textContent = customLabel;
            return;
        }

        var statType = getCurrentStatType();
        var total = countSelected(hidden.value);
        if (statType === "evaluaciones") {
            if (total <= 0) {
                label.textContent = defaultQuizText;
            } else {
                label.textContent = selectedQuizTemplate.replace("__COUNT__", String(total));
            }
        } else if (statType === "actividades_aprendizaje") {
            if (total <= 0) {
                label.textContent = defaultTasksText;
            } else {
                label.textContent = selectedTasksTemplate.replace("__COUNT__", String(total));
            }
        } else if (statType === "recursos_completados") {
            if (total <= 0) {
                label.textContent = defaultCompletedResourcesText;
            } else {
                label.textContent = selectedTemplate.replace("__COUNT__", String(total));
            }
        } else {
            if (total <= 0) {
                label.textContent = defaultText;
            } else {
                label.textContent = selectedTemplate.replace("__COUNT__", String(total));
            }
        }
    }

    window.crUserstatsAdvancedSetSelectedCourseModules = function(csv, labelText) {
        var hidden = document.getElementById("id_selectedcmids");
        if (hidden) {
            hidden.value = csv || "";
        }
        var normalizedCsv = (csv || "").trim();
        if (normalizedCsv === "") {
            refreshLabel("");
            return;
        }
        refreshLabel(labelText || "");
    };

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", function() {
            var statField = document.getElementById("id_stat_type");
            if (statField) {
                statField.addEventListener("change", function() {
                    toggleFormRows();
                    refreshLabel("");
                });
            }
            toggleFormRows();
            refreshLabel("");
        });
    } else {
        var statField = document.getElementById("id_stat_type");
        if (statField) {
            statField.addEventListener("change", function() {
                toggleFormRows();
                refreshLabel("");
            });
        }
        toggleFormRows();
        refreshLabel("");
    }
})();
JS;
        $mform->addElement('html', html_writer::script($script));

        $limitoptions = [];
        $limitminutes = array_merge([1, 2, 3, 4], range(5, 180, 5), range(210, 480, 30), [600, 720]);
        foreach ($limitminutes as $minutes) {
            $limitoptions[$minutes * 60] = $minutes;
        }
        $mform->addElement(
            'select',
            'sessionlimittime',
            get_string('sessionlimittime', 'block_configurable_reports'),
            $limitoptions
        );
        $mform->addHelpButton('sessionlimittime', 'sessionlimittime', 'block_configurable_reports');
        $mform->setType('sessionlimittime', PARAM_INT);
        $mform->setDefault('sessionlimittime', 4 * 60 * 60);
        $mform->disabledIf('sessionlimittime', 'stat_type', 'neq', 'tiempo_total');

        $this->_customdata['compclass']->add_form_elements($mform, $this);

        $this->add_action_buttons(true, get_string('add'));
    }

    /**
     * Server side validation.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $errors = $this->_customdata['compclass']->validate_form_elements($data, $errors);

        if (empty($data['stat_type'])) {
            $errors['stat_type'] = get_string('required');
        }
        if (!empty($data['stat_type']) && in_array($data['stat_type'], ['evaluaciones', 'actividades_aprendizaje'], true)) {
            if (!isset($data['maxdisplayvalue']) || $data['maxdisplayvalue'] === '') {
                $errors['maxdisplayvalue'] = get_string('required');
            } else if (!is_numeric($data['maxdisplayvalue'])) {
                $errors['maxdisplayvalue'] = get_string('error_value_expected_integer', 'block_configurable_reports');
            } else {
                $maxdisplayvalue = (int)$data['maxdisplayvalue'];
                if ($maxdisplayvalue < 0 || $maxdisplayvalue > 100) {
                    $errors['maxdisplayvalue'] = $this->get_localized_label(
                        'userstatsadvanced_maxdisplayvalue_error',
                        'El valor máximo debe estar entre 0 y 100.'
                    );
                }
            }
        }

        return $errors;
    }

}
