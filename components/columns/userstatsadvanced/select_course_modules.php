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
 * Course module selector popup for userstatsadvanced.
 *
 * @package    block_configurable_reports
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../../../../config.php");
require_once($CFG->dirroot . "/blocks/configurable_reports/locallib.php");

$id = required_param('id', PARAM_INT);
$cid = optional_param('cid', '', PARAM_ALPHANUM);
$modname = optional_param('modname', '', PARAM_ALPHANUMEXT);
$selectedcmidsraw = optional_param('selectedcmids', '', PARAM_RAW_TRIMMED);
$submitselection = optional_param('submitselection', 0, PARAM_BOOL);

$report = $DB->get_record('block_configurable_reports', ['id' => $id], '*', MUST_EXIST);
$course = $DB->get_record('course', ['id' => $report->courseid], '*', MUST_EXIST);
if ($selectedcmidsraw === '' && $cid !== '') {
    $selectedcmidsraw = userstatsadvanced_popup_get_selectedcmids_from_report($report, $cid);
}

if ((int)$course->id === SITEID) {
    require_login();
    $context = context_system::instance();
} else {
    require_login($course->id);
    $context = context_course::instance($course->id);
}

$hasmanagereportcap = has_capability('block/configurable_reports:managereports', $context);
if (!$hasmanagereportcap && !has_capability('block/configurable_reports:manageownreports', $context)) {
    throw new moodle_exception('badpermissions');
}
if (!$hasmanagereportcap && (int)$report->ownerid !== (int)$USER->id) {
    throw new moodle_exception('badpermissions');
}

$urlparams = ['id' => $id];
if ($cid !== '') {
    $urlparams['cid'] = $cid;
}
if ($modname !== '') {
    $urlparams['modname'] = $modname;
}
if ($selectedcmidsraw !== '') {
    $urlparams['selectedcmids'] = $selectedcmidsraw;
}
$pageurl = new moodle_url('/blocks/configurable_reports/components/columns/userstatsadvanced/select_course_modules.php', $urlparams);
$isquizmode = ($modname === 'quiz');
$isassignmode = ($modname === 'assign');
$allowedmodnames = [];
if ($modname !== '') {
    if ($isquizmode) {
        $allowedmodnames = ['quiz', 'feedback'];
    } else {
        $allowedmodnames = [$modname];
    }
}

$title = userstatsadvanced_popup_label(
    $isquizmode
        ? 'userstatsadvanced_select_quizzes'
        : ($isassignmode ? 'userstatsadvanced_select_tasks' : 'userstatsadvanced_select_course_modules'),
    $isquizmode
        ? 'Seleccionar cuestionarios'
        : ($isassignmode ? 'Seleccionar tareas' : 'Seleccionar actividades/recursos')
);

$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_url($pageurl);
$PAGE->set_title($title);
$PAGE->set_heading(format_string($course->fullname));

$stringmanager = get_string_manager();
$showcoursecolumn = ((int)$course->id === SITEID);
$availablecms = [];
if ($showcoursecolumn) {
    $courserecords = $DB->get_records_sql(
        "SELECT c.id, c.fullname
           FROM {course} c
          WHERE c.id <> :siteid
       ORDER BY c.fullname ASC, c.id ASC",
        ['siteid' => SITEID]
    );
} else {
    $courserecords = [
        (int)$course->id => (object)[
            'id' => (int)$course->id,
            'fullname' => (string)$course->fullname,
        ],
    ];
}

foreach ($courserecords as $courserecord) {
    $coursename = format_string((string)$courserecord->fullname);
    try {
        $modinfo = get_fast_modinfo((int)$courserecord->id);
    } catch (Exception $e) {
        continue;
    }

    foreach ($modinfo->get_cms() as $cm) {
        if (!empty($cm->deletioninprogress) || $cm->modname === 'label') {
            continue;
        }
        if (!empty($allowedmodnames) && !in_array($cm->modname, $allowedmodnames, true)) {
            continue;
        }

        $modcomponent = 'mod_' . $cm->modname;
        $modtypename = $cm->modname;
        if ($stringmanager->string_exists('pluginname', $modcomponent)) {
            $modtypename = get_string('pluginname', $modcomponent);
        }

        $activityname = trim((string)$cm->name);
        if ($activityname === '') {
            if ($stringmanager->string_exists('modulename', $modcomponent)) {
                $activityname = get_string('modulename', $modcomponent) . ' #' . $cm->id;
            } else {
                $activityname = 'Actividad #' . $cm->id;
            }
        }

        $availablecms[(int)$cm->id] = [
            'name' => format_string($activityname),
            'type' => format_string($modtypename),
            'course' => $coursename,
        ];
    }
}

ksort($availablecms, SORT_NUMERIC);

$selectedcmids = userstatsadvanced_popup_parse_selected_cmids($selectedcmidsraw);
if (!empty($selectedcmids)) {
    $selectedcmids = array_values(array_intersect($selectedcmids, array_keys($availablecms)));
}
sort($selectedcmids, SORT_NUMERIC);

if ($submitselection && confirm_sesskey()) {
    $postedcmids = optional_param_array('cmids', [], PARAM_INT);
    $postedcmids = array_map('intval', $postedcmids);
    $postedcmids = array_values(array_unique(array_filter($postedcmids, static function($idvalue) {
        return $idvalue > 0;
    })));

    if (!empty($postedcmids)) {
        $postedcmids = array_values(array_intersect($postedcmids, array_keys($availablecms)));
    }
    sort($postedcmids, SORT_NUMERIC);

    $selectedcsv = implode(',', $postedcmids);
    if ($cid !== '') {
        userstatsadvanced_popup_save_selectedcmids_to_report($report, $cid, $selectedcsv);
    }
    $selectedcount = count($postedcmids);
    if ($selectedcount > 0) {
        $counttemplate = userstatsadvanced_popup_label(
            $isquizmode
                ? 'userstatsadvanced_selected_quizzes_count'
                : ($isassignmode ? 'userstatsadvanced_selected_tasks_count' : 'userstatsadvanced_selected_items_count'),
            $isquizmode
                ? 'Cuestionarios seleccionados: {$a}'
                : ($isassignmode ? 'Tareas seleccionadas: {$a}' : 'Elementos seleccionados: {$a}')
        );
        $selectedlabel = str_replace('{$a}', (string)$selectedcount, $counttemplate);
    } else {
        if ($showcoursecolumn) {
            if ($isquizmode) {
                $selectedlabel = 'Acceso a cursos y cuestionarios';
            } else if ($isassignmode) {
                $selectedlabel = 'Acceso a cursos y tareas';
            } else {
                $selectedlabel = 'Se usarán todos los recursos visibles de los cursos.';
            }
        } else {
            $selectedlabel = userstatsadvanced_popup_label(
                $isquizmode
                    ? 'userstatsadvanced_elements_filter_default_quizzes'
                    : ($isassignmode ? 'userstatsadvanced_elements_filter_default_tasks' : 'userstatsadvanced_selected_items_all'),
                $isquizmode
                    ? 'Acceso al curso y cuestionarios'
                    : ($isassignmode ? 'Acceso al curso y tareas' : 'Se usarán todos los recursos visibles del curso.')
            );
        }
    }

    $syncscript = '(function() {' .
        'var csv = ' . json_encode($selectedcsv) . ';' .
        'var label = ' . json_encode($selectedlabel) . ';' .
        'if (window.opener) {' .
            'var syncFallback = function() {' .
                'var hidden = window.opener.document.getElementById(\"id_selectedcmids\");' .
                'if (!hidden) { hidden = window.opener.document.querySelector(\"input[name=selectedcmids]\"); }' .
                'if (hidden) {' .
                    'hidden.value = csv;' .
                    'if (typeof hidden.dispatchEvent === \"function\") {' .
                        'hidden.dispatchEvent(new Event(\"change\"));' .
                    '}' .
                '}' .
                'var display = window.opener.document.getElementById(\"userstatsadvanced-selectedcmids-display\");' .
                'if (display) { display.textContent = label; }' .
            '};' .
            'if (typeof window.opener.crUserstatsAdvancedSetSelectedCourseModules === "function") {' .
                'try {' .
                    'window.opener.crUserstatsAdvancedSetSelectedCourseModules(csv, label);' .
                '} catch (err) {' .
                    'syncFallback();' .
                '}' .
            '} else {' .
                'syncFallback();' .
            '}' .
        '}' .
        'window.close();' .
    '})();';

    echo $OUTPUT->header();
    echo html_writer::script($syncscript);
    echo html_writer::div(
        userstatsadvanced_popup_label('userstatsadvanced_selection_saved', 'Selección guardada. Puedes cerrar esta ventana.'),
        'alert alert-info'
    );
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
if ($showcoursecolumn) {
    if ($isquizmode) {
        $helptext = 'Si no seleccionas cuestionarios, se usarán todos los cuestionarios visibles de los cursos.';
    } else if ($isassignmode) {
        $helptext = 'Si no seleccionas tareas, se usarán todas las tareas visibles de los cursos.';
    } else {
        $helptext = 'Si no seleccionas elementos, se usarán todos los recursos visibles de los cursos.';
    }
} else {
    $helptext = userstatsadvanced_popup_label(
        $isquizmode
            ? 'userstatsadvanced_select_quizzes_help'
            : ($isassignmode ? 'userstatsadvanced_select_tasks_help' : 'userstatsadvanced_select_course_modules_help'),
        $isquizmode
            ? 'Si no seleccionas cuestionarios, se usarán todos los cuestionarios visibles del curso.'
            : ($isassignmode
                ? 'Si no seleccionas tareas, se usarán todas las tareas visibles del curso.'
                : 'Si no seleccionas elementos, se usarán todos los recursos visibles del curso.')
    );
}
echo html_writer::div(
    $helptext,
    'mb-3'
);

if (empty($availablecms)) {
    echo $OUTPUT->notification(get_string('norecordsfound', 'block_configurable_reports'));
    echo $OUTPUT->footer();
    exit;
}

$formurlparams = ['id' => $id];
if ($cid !== '') {
    $formurlparams['cid'] = $cid;
}
if ($modname !== '') {
    $formurlparams['modname'] = $modname;
}
$formurl = new moodle_url('/blocks/configurable_reports/components/columns/userstatsadvanced/select_course_modules.php', $formurlparams);

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $formurl->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'submitselection', 'value' => '1']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

$table = new html_table();
$table->attributes['class'] = 'generaltable table table-striped';
$table->head = [
    userstatsadvanced_popup_label('userstatsadvanced_select_column', 'Seleccionar'),
    userstatsadvanced_popup_label('userstatsadvanced_content_column', 'Actividad/Recurso'),
    userstatsadvanced_popup_label('userstatsadvanced_type_column', 'Tipo'),
];
if ($showcoursecolumn) {
    $table->head[] = get_string('course');
}
$table->data = [];

foreach ($availablecms as $cmid => $cmdata) {
    $checkboxattrs = [
        'type' => 'checkbox',
        'name' => 'cmids[]',
        'value' => (string)$cmid,
        'class' => 'userstatsadvanced-cmid-checkbox',
    ];
    if (in_array((int)$cmid, $selectedcmids, true)) {
        $checkboxattrs['checked'] = 'checked';
    }

    $row = [
        html_writer::empty_tag('input', $checkboxattrs),
        s($cmdata['name']),
        s($cmdata['type']),
    ];
    if ($showcoursecolumn) {
        $row[] = s($cmdata['course'] ?? '');
    }
    $table->data[] = $row;
}

echo html_writer::start_div('table-responsive');
echo html_writer::table($table);
echo html_writer::end_div();

echo html_writer::start_div('mb-3 mt-2');
echo html_writer::tag(
    'button',
    userstatsadvanced_popup_label('userstatsadvanced_select_all_modules', 'Seleccionar todo'),
    ['type' => 'button', 'id' => 'userstatsadvanced-select-all', 'class' => 'btn btn-secondary btn-sm mr-2']
);
echo html_writer::tag(
    'button',
    userstatsadvanced_popup_label('userstatsadvanced_clear_all_modules', 'Quitar selección'),
    ['type' => 'button', 'id' => 'userstatsadvanced-clear-all', 'class' => 'btn btn-secondary btn-sm']
);
echo html_writer::end_div();

echo html_writer::start_div('mt-3');
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'class' => 'btn btn-primary mr-2',
    'value' => userstatsadvanced_popup_label('userstatsadvanced_save_selection', 'Guardar selección'),
]);
echo html_writer::tag(
    'button',
    get_string('cancel'),
    ['type' => 'button', 'class' => 'btn btn-secondary', 'onclick' => 'window.close();']
);
echo html_writer::end_div();
echo html_writer::end_tag('form');

echo html_writer::script(
    '(function() {' .
        'function setCheckboxState(checked) {' .
            'var boxes = document.querySelectorAll(".userstatsadvanced-cmid-checkbox");' .
            'boxes.forEach(function(box) { box.checked = checked; });' .
        '}' .
        'var selectAllBtn = document.getElementById("userstatsadvanced-select-all");' .
        'if (selectAllBtn) {' .
            'selectAllBtn.addEventListener("click", function() { setCheckboxState(true); });' .
        '}' .
        'var clearAllBtn = document.getElementById("userstatsadvanced-clear-all");' .
        'if (clearAllBtn) {' .
            'clearAllBtn.addEventListener("click", function() { setCheckboxState(false); });' .
        '}' .
    '})();'
);

echo $OUTPUT->footer();

/**
 * Returns localized popup label if available.
 *
 * @param string $identifier
 * @param string $fallback
 * @return string
 */
function userstatsadvanced_popup_label(string $identifier, string $fallback): string {
    $stringmanager = get_string_manager();
    if ($stringmanager->string_exists($identifier, 'block_configurable_reports')) {
        return get_string($identifier, 'block_configurable_reports');
    }
    return $fallback;
}

/**
 * Parses selected course module IDs from CSV text.
 *
 * @param string $selectedcmidsraw
 * @return array<int>
 */
function userstatsadvanced_popup_parse_selected_cmids(string $selectedcmidsraw): array {
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
 * Returns saved selected course modules for a specific column component.
 *
 * @param object $report
 * @param string $cid
 * @return string
 */
function userstatsadvanced_popup_get_selectedcmids_from_report(object $report, string $cid): string {
    if ($cid === '' || empty($report->components)) {
        return '';
    }

    $components = cr_unserialize($report->components);
    $elements = $components['columns']['elements'] ?? [];
    if (empty($elements) || !is_array($elements)) {
        return '';
    }

    foreach ($elements as $element) {
        if (!is_array($element) || !array_key_exists('id', $element)) {
            continue;
        }
        if ((string)$element['id'] !== $cid) {
            continue;
        }

        $formdata = $element['formdata'] ?? null;
        if (is_object($formdata) && isset($formdata->selectedcmids)) {
            return (string)$formdata->selectedcmids;
        }
        if (is_array($formdata) && isset($formdata['selectedcmids'])) {
            return (string)$formdata['selectedcmids'];
        }
        return '';
    }

    return '';
}

/**
 * Persists selected course modules for a specific column component.
 *
 * @param object $report
 * @param string $cid
 * @param string $selectedcsv
 * @return void
 */
function userstatsadvanced_popup_save_selectedcmids_to_report(object $report, string $cid, string $selectedcsv): void {
    global $DB;

    if ($cid === '' || empty($report->components)) {
        return;
    }

    $components = cr_unserialize($report->components);
    $elements = $components['columns']['elements'] ?? [];
    if (empty($elements) || !is_array($elements)) {
        return;
    }

    $updated = false;
    foreach ($elements as $index => $element) {
        if (!is_array($element) || !array_key_exists('id', $element)) {
            continue;
        }
        if ((string)$element['id'] !== $cid) {
            continue;
        }

        $formdata = $element['formdata'] ?? null;
        if (is_array($formdata)) {
            $formdata['selectedcmids'] = $selectedcsv;
        } else if (is_object($formdata)) {
            $formdata->selectedcmids = $selectedcsv;
        } else {
            $formdata = new stdClass();
            $formdata->selectedcmids = $selectedcsv;
        }

        $elements[$index]['formdata'] = $formdata;
        $updated = true;
        break;
    }

    if (!$updated) {
        return;
    }

    $components['columns']['elements'] = $elements;
    $updatedreport = clone $report;
    $updatedreport->components = cr_serialize($components);
    $DB->update_record('block_configurable_reports', $updatedreport);
}
