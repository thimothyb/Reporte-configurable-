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
 * Popup to add multiple quiz columns for activity/resource statistics.
 *
 * @package    block_configurable_reports
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../../../../config.php");
require_once($CFG->dirroot . "/blocks/configurable_reports/locallib.php");
require_once($CFG->dirroot . '/blocks/configurable_reports/components/columns/scormadvancedgrades/plugin.class.php');

$id = required_param('id', PARAM_INT);
$submitted = optional_param('submitadd', '', PARAM_RAW_TRIMMED) !== '';
$selectedinstanceids = optional_param_array('quizinstance', [], PARAM_INT);

$metricoptions = scormadvancedgrades_addmultiple_get_metric_options();
$selectedmetrics = [];
foreach ($metricoptions as $metrickey => $metricdata) {
    $paramname = (string)$metricdata['param'];
    $defaultselected = !empty($metricdata['default']) ? 1 : 0;
    $enabled = $submitted
        ? optional_param($paramname, 0, PARAM_BOOL)
        : optional_param($paramname, $defaultselected, PARAM_BOOL);
    if ($enabled) {
        $selectedmetrics[] = $metrickey;
    }
}

if (!$report = $DB->get_record('block_configurable_reports', ['id' => $id])) {
    throw new moodle_exception('reportdoesnotexists', 'block_configurable_reports');
}
if (!$course = $DB->get_record('course', ['id' => $report->courseid])) {
    throw new moodle_exception('invalidcourseid');
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

$url = new moodle_url('/blocks/configurable_reports/components/columns/scormadvancedgrades/addmultiple.php', ['id' => $id]);
$PAGE->set_context($context);
$PAGE->set_pagelayout('popup');
$PAGE->set_url($url);
$title = scormadvancedgrades_addmultiple_label('scormadvancedgrades_addmultiple_title', 'Añadir varias columnas');
$PAGE->set_title($title);
$PAGE->set_heading($title);

$activities = scormadvancedgrades_addmultiple_get_quiz_activities((int)$course->id);
$selectedinstanceids = scormadvancedgrades_addmultiple_normalize_int_list($selectedinstanceids);

if (!$submitted && empty($selectedinstanceids)) {
    foreach ($activities as $activity) {
        $selectedinstanceids[] = (int)$activity->instanceid;
    }
    $selectedinstanceids = array_values(array_unique($selectedinstanceids));
}

$saved = false;
$error = '';
if ($submitted) {
    require_sesskey();

    if (empty($selectedmetrics)) {
        $error = scormadvancedgrades_addmultiple_label(
            'scormadvancedgrades_addmultiple_nometrics',
            'Debes seleccionar al menos una opción de columnas.'
        );
    } else if (empty($selectedinstanceids)) {
        $error = scormadvancedgrades_addmultiple_label(
            'scormadvancedgrades_addmultiple_noselection',
            'Debes seleccionar al menos una actividad.'
        );
    } else {
        scormadvancedgrades_addmultiple_save_columns(
            $report,
            $selectedinstanceids,
            $selectedmetrics
        );
        $saved = true;
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);

if ($saved) {
    echo $OUTPUT->notification(
        scormadvancedgrades_addmultiple_label('scormadvancedgrades_addmultiple_saved', 'Columnas añadidas correctamente.'),
        'notifysuccess'
    );

    $editcolumnsurl = new moodle_url('/blocks/configurable_reports/editcomp.php', ['id' => $id, 'comp' => 'columns']);
    $escapedurl = json_encode($editcolumnsurl->out(false));
    echo html_writer::script(
        "(function(){\n" .
        "  try {\n" .
        "    if (window.opener && !window.opener.closed) {\n" .
        "      window.opener.location = {$escapedurl};\n" .
        "    }\n" .
        "  } catch (e) {}\n" .
        "  setTimeout(function(){ window.close(); }, 500);\n" .
        "})();"
    );

    echo html_writer::div(
        html_writer::link($editcolumnsurl, get_string('continue')),
        'mt-2'
    );
    echo $OUTPUT->footer();
    exit;
}

if ($error !== '') {
    echo $OUTPUT->notification($error, 'notifyproblem');
}

if (empty($activities)) {
    echo $OUTPUT->notification(get_string('norecordsfound', 'block_configurable_reports'), 'notifyinfo');
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::tag(
    'p',
    scormadvancedgrades_addmultiple_label(
        'scormadvancedgrades_addmultiple_intro',
        'Selecciona columnas globales y cuestionarios para añadir automáticamente estadísticas.'
    )
);

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

echo html_writer::tag(
    'h3',
    s(scormadvancedgrades_addmultiple_label('scormadvancedgrades_addmultiple_quizsection', 'Cuestionarios')),
    ['class' => 'h5 mb-2']
);

echo html_writer::start_div('mb-3');
foreach ($metricoptions as $metrickey => $metricdata) {
    $ischecked = in_array($metrickey, $selectedmetrics, true);
    $metricattrs = [
        'type' => 'checkbox',
        'name' => (string)$metricdata['param'],
        'value' => 1,
    ];
    if ($ischecked) {
        $metricattrs['checked'] = 'checked';
    }

    echo html_writer::start_tag('label', ['class' => 'd-block mb-2']);
    echo html_writer::empty_tag('input', $metricattrs) . ' ' .
        s(scormadvancedgrades_addmultiple_label(
            (string)$metricdata['labelkey'],
            (string)$metricdata['fallback']
        ));
    echo html_writer::end_tag('label');
}
echo html_writer::end_div();

echo html_writer::div(
    html_writer::link(
        '#',
        scormadvancedgrades_addmultiple_label('scormadvancedgrades_addmultiple_selectall', 'Seleccionar todo'),
        ['id' => 'cr-sag-select-all']
    ) .
    ' / ' .
    html_writer::link(
        '#',
        scormadvancedgrades_addmultiple_label('scormadvancedgrades_addmultiple_clearall', 'Quitar seleccion'),
        ['id' => 'cr-sag-clear-all']
    ),
    'mb-2'
);

$table = new html_table();
$table->attributes['class'] = 'generaltable';
$table->head = [
    scormadvancedgrades_addmultiple_label('scormadvancedgrades_addmultiple_selectcol', 'Seleccionar'),
    scormadvancedgrades_addmultiple_label('scormadvancedgrades_addmultiple_activitycol', 'Actividad'),
];
$table->data = [];

foreach ($activities as $index => $activity) {
    $instanceid = (int)$activity->instanceid;
    $checked = in_array($instanceid, $selectedinstanceids, true);
    $inputattrs = [
        'type' => 'checkbox',
        'class' => 'cr-sag-activity',
        'name' => 'quizinstance[]',
        'value' => $instanceid,
    ];
    if ($checked) {
        $inputattrs['checked'] = 'checked';
    }

    $checkbox = html_writer::empty_tag('input', $inputattrs);
    $name = trim((string)$activity->name);
    if ($name === '') {
        $name = scormadvancedgrades_addmultiple_label(
            'scormadvancedgrades_addmultiple_fallbackname',
            'Actividad {$a}',
            $index + 1
        );
    }

    $table->data[] = [
        $checkbox,
        format_string($name),
    ];
}

echo html_writer::table($table);

echo html_writer::start_div('mt-3');
echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'name' => 'submitadd',
    'value' => get_string('add'),
    'class' => 'btn btn-primary mr-2',
]);
echo html_writer::tag('button', get_string('cancel'), [
    'type' => 'button',
    'class' => 'btn btn-secondary',
    'onclick' => 'window.close();',
]);
echo html_writer::end_div();
echo html_writer::end_tag('form');

echo html_writer::script(
    "(function(){\n" .
    "  var all = document.getElementById('cr-sag-select-all');\n" .
    "  var clear = document.getElementById('cr-sag-clear-all');\n" .
    "  function setChecked(value) {\n" .
    "    var boxes = document.querySelectorAll('.cr-sag-activity');\n" .
    "    boxes.forEach(function(box){ box.checked = value; });\n" .
    "  }\n" .
    "  if (all) {\n" .
    "    all.addEventListener('click', function(e){ e.preventDefault(); setChecked(true); });\n" .
    "  }\n" .
    "  if (clear) {\n" .
    "    clear.addEventListener('click', function(e){ e.preventDefault(); setChecked(false); });\n" .
    "  }\n" .
    "})();"
);

echo $OUTPUT->footer();

/**
 * Returns the available quiz metric options for add-multiple popup.
 *
 * @return array<string,array<string,mixed>>
 */
function scormadvancedgrades_addmultiple_get_metric_options(): array {
    return [
        'completiondate' => [
            'param' => 'addquizcompletiondate',
            'labelkey' => 'scormadvancedgrades_addmultiple_addquizcompletiondate',
            'fallback' => 'Añadir la fecha de realización de todos los cuestionarios',
            'default' => 1,
            'columnprefix' => 'FECHA DE REALIZACION ACTIVIDAD',
            'format' => 'datetime',
        ],
        'score' => [
            'param' => 'addquizscore',
            'labelkey' => 'scormadvancedgrades_addmultiple_addquizscore',
            'fallback' => 'Añadir la puntuación de todos los cuestionarios',
            'default' => 1,
            'columnprefix' => 'NOTA ACTIVIDAD',
            'format' => 'percent',
        ],
        'dedicationtime' => [
            'param' => 'addquizdedicationtime',
            'labelkey' => 'scormadvancedgrades_addmultiple_addquizdedicationtime',
            'fallback' => 'Añadir el tiempo de dedicación de todos los cuestionarios',
            'default' => 0,
            'columnprefix' => 'TIEMPO DE DEDICACION ACTIVIDAD',
            'format' => 'text',
        ],
        'opendate' => [
            'param' => 'addquizopendate',
            'labelkey' => 'scormadvancedgrades_addmultiple_addquizopendate',
            'fallback' => 'Añadir la fecha de apertura de todos los cuestionarios',
            'default' => 0,
            'columnprefix' => 'FECHA DE APERTURA ACTIVIDAD',
            'format' => 'datetime',
        ],
        'firstpassattempt' => [
            'param' => 'addquizfirstpassattempt',
            'labelkey' => 'scormadvancedgrades_addmultiple_addquizfirstpassattempt',
            'fallback' => 'Añadir el primer intento aprobado de todos los cuestionarios',
            'default' => 0,
            'columnprefix' => 'PRIMER INTENTO APROBADO ACTIVIDAD',
            'format' => 'number',
        ],
    ];
}

/**
 * Gets visible quiz activities in course order.
 *
 * @param int $courseid
 * @return array<int,object>
 */
function scormadvancedgrades_addmultiple_get_quiz_activities(int $courseid): array {
    global $DB;

    if ($courseid <= 0) {
        return [];
    }

    $sql = "SELECT cm.id AS cmid,
                   cm.instance AS instanceid,
                   q.name
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module
              JOIN {quiz} q ON q.id = cm.instance
             WHERE cm.course = :courseid
               AND cm.visible = 1
               AND m.name = :modname
          ORDER BY cm.section ASC, cm.added ASC, cm.id ASC";
    $records = $DB->get_records_sql($sql, ['courseid' => $courseid, 'modname' => 'quiz']);

    return array_values($records);
}

/**
 * Saves generated columns into report definition.
 *
 * @param object $report
 * @param array<int> $instanceids
 * @param array<int,string> $metrics
 * @return void
 */
function scormadvancedgrades_addmultiple_save_columns(
    object $report,
    array $instanceids,
    array $metrics
): void {
    global $DB;

    if (empty($instanceids) || empty($metrics)) {
        return;
    }

    $metricoptions = scormadvancedgrades_addmultiple_get_metric_options();
    $metrics = array_values(array_filter($metrics, static function(string $metrickey) use ($metricoptions): bool {
        return array_key_exists($metrickey, $metricoptions);
    }));
    if (empty($metrics)) {
        return;
    }

    $components = cr_unserialize((string)$report->components);
    $elements = $components['columns']['elements'] ?? [];
    if (!is_array($elements)) {
        $elements = [];
    }

    $elements = array_values(array_filter($elements, static function($element): bool {
        if (!is_array($element)) {
            return true;
        }
        if (!isset($element['pluginname']) || (string)$element['pluginname'] !== 'scormadvancedgrades') {
            return true;
        }

        $formdata = $element['formdata'] ?? null;
        if (is_array($formdata)) {
            $formdata = (object)$formdata;
        }
        if (!is_object($formdata)) {
            return true;
        }

        if (empty($formdata->stat) || !is_string($formdata->stat)) {
            $columnname = isset($formdata->columname) ? trim((string)$formdata->columname) : '';
            if ($columnname === '') {
                return true;
            }
            return !preg_match(
                '/^(NOTA|FECHA DE ENTREGA|FECHA DE REALIZACION|TIEMPO DE DEDICACION|FECHA DE APERTURA|PRIMER INTENTO APROBADO)\s+ACTIVIDAD\s+\d+$/iu',
                $columnname
            );
        }

        $isautogeneratedstat = preg_match(
            '/^(quiz:\d+:(completiondate|score|dedicationtime|opendate|firstpassattempt)|assign:\d+:(score|submissiondate))$/',
            $formdata->stat
        );
        if ($isautogeneratedstat) {
            return false;
        }

        $columnname = isset($formdata->columname) ? trim((string)$formdata->columname) : '';
        if ($columnname === '') {
            return true;
        }

        return !preg_match(
            '/^(NOTA|FECHA DE ENTREGA|FECHA DE REALIZACION|TIEMPO DE DEDICACION|FECHA DE APERTURA|PRIMER INTENTO APROBADO)\s+ACTIVIDAD\s+\d+$/iu',
            $columnname
        );
    }));

    $pluginclass = new plugin_scormadvancedgrades($report);
    $usedids = [];
    foreach ($elements as $element) {
        if (is_array($element) && !empty($element['id'])) {
            $usedids[(string)$element['id']] = true;
        }
    }

    $activityindex = 1;
    foreach ($instanceids as $instanceid) {
        $instanceid = (int)$instanceid;
        if ($instanceid <= 0) {
            continue;
        }

        foreach ($metrics as $metrickey) {
            $metricdata = $metricoptions[$metrickey];
            $formdata = (object)[
                'columname' => (string)$metricdata['columnprefix'] . ' ' . $activityindex,
                'stat' => 'quiz:' . $instanceid . ':' . $metrickey,
                'format' => (string)$metricdata['format'],
                'align' => 'center',
                'size' => '',
                'wrap' => '',
            ];
            $elements[] = scormadvancedgrades_addmultiple_build_element($pluginclass, $formdata, $usedids);
        }

        $activityindex++;
    }

    $components['columns']['elements'] = $elements;
    $updatedreport = clone $report;
    $updatedreport->components = cr_serialize($components);
    $DB->update_record('block_configurable_reports', $updatedreport);
}

/**
 * Builds a report column element payload for scormadvancedgrades.
 *
 * @param plugin_scormadvancedgrades $pluginclass
 * @param stdClass $formdata
 * @param array<string,bool> $usedids
 * @return array
 */
function scormadvancedgrades_addmultiple_build_element(
    plugin_scormadvancedgrades $pluginclass,
    stdClass $formdata,
    array &$usedids
): array {
    $uniqueid = random_string(15);
    while (isset($usedids[$uniqueid])) {
        $uniqueid = random_string(15);
    }
    $usedids[$uniqueid] = true;

    return [
        'id' => $uniqueid,
        'formdata' => $formdata,
        'pluginname' => 'scormadvancedgrades',
        'pluginfullname' => $pluginclass->fullname,
        'summary' => $pluginclass->summary($formdata),
    ];
}

/**
 * Normalizes an int list into unique positive integers.
 *
 * @param array<mixed> $values
 * @return array<int>
 */
function scormadvancedgrades_addmultiple_normalize_int_list(array $values): array {
    $ids = [];
    foreach ($values as $value) {
        $id = (int)$value;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    return array_values($ids);
}

/**
 * Returns localized string if available; otherwise fallback text.
 *
 * @param string $identifier
 * @param string $fallback
 * @param mixed|null $a
 * @return string
 */
function scormadvancedgrades_addmultiple_label(string $identifier, string $fallback, $a = null): string {
    $stringmanager = get_string_manager();
    if ($stringmanager->string_exists($identifier, 'block_configurable_reports')) {
        if ($a !== null) {
            return get_string($identifier, 'block_configurable_reports', $a);
        }
        return get_string($identifier, 'block_configurable_reports');
    }

    if ($a !== null) {
        return str_replace('{$a}', (string)$a, $fallback);
    }
    return $fallback;
}
