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
 * Popup to add multiple columns for activity/resource statistics.
 *
 * @package    block_configurable_reports
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../../../../config.php");
require_once($CFG->dirroot . "/blocks/configurable_reports/locallib.php");
require_once($CFG->dirroot . '/blocks/configurable_reports/components/columns/scormadvancedgrades/plugin.class.php');

$id = required_param('id', PARAM_INT);
$submitted = optional_param('submitadd', '', PARAM_RAW_TRIMMED) !== '';

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
$title = scormadvancedgrades_addmultiple_label('scormadvancedgrades_addmultiple_title', 'Anadir varias columnas');
$PAGE->set_title($title);
$PAGE->set_heading($title);

$quizmetricoptions = scormadvancedgrades_addmultiple_get_quiz_metric_options();
$assignmetricoptions = scormadvancedgrades_addmultiple_get_assign_metric_options();
$forummetricoptions = scormadvancedgrades_addmultiple_get_forum_metric_options();
$chatmetricoptions = scormadvancedgrades_addmultiple_get_chat_metric_options();
$scormmetricoptions = scormadvancedgrades_addmultiple_get_scorm_metric_options();
$zoommetricoptions = scormadvancedgrades_addmultiple_get_zoom_metric_options();

$selectedquizmetrics = scormadvancedgrades_addmultiple_collect_selected_metrics($quizmetricoptions, $submitted);
$selectedassignmetrics = scormadvancedgrades_addmultiple_collect_selected_metrics($assignmetricoptions, $submitted);
$selectedforummetrics = scormadvancedgrades_addmultiple_collect_selected_metrics($forummetricoptions, $submitted);
$selectedchatmetrics = scormadvancedgrades_addmultiple_collect_selected_metrics($chatmetricoptions, $submitted);
$selectedscormmetrics = scormadvancedgrades_addmultiple_collect_selected_metrics($scormmetricoptions, $submitted);
$selectedzoommetrics = scormadvancedgrades_addmultiple_collect_selected_metrics($zoommetricoptions, $submitted);

$quizactivities = scormadvancedgrades_addmultiple_get_module_activities((int)$course->id, 'quiz');
$assignactivities = scormadvancedgrades_addmultiple_get_module_activities((int)$course->id, 'assign');
$forumactivities = scormadvancedgrades_addmultiple_get_module_activities((int)$course->id, 'forum');
$chatactivities = scormadvancedgrades_addmultiple_get_module_activities((int)$course->id, 'chat');
$scormactivities = scormadvancedgrades_addmultiple_get_module_activities((int)$course->id, 'scorm');
$zoomactivities = scormadvancedgrades_addmultiple_get_module_activities((int)$course->id, 'zoom');

$selectedquizinstanceids = optional_param_array('quizinstance', [], PARAM_INT);
$selectedassigninstanceids = optional_param_array('assigninstance', [], PARAM_INT);
$selectedforuminstanceids = optional_param_array('foruminstance', [], PARAM_INT);
$selectedchatinstanceids = optional_param_array('chatinstance', [], PARAM_INT);
$selectedscorminstanceids = optional_param_array('scorminstance', [], PARAM_INT);
$selectedzoominstanceids = optional_param_array('zoominstance', [], PARAM_INT);
$selectedquizinstanceids = scormadvancedgrades_addmultiple_normalize_int_list($selectedquizinstanceids);
$selectedassigninstanceids = scormadvancedgrades_addmultiple_normalize_int_list($selectedassigninstanceids);
$selectedforuminstanceids = scormadvancedgrades_addmultiple_normalize_int_list($selectedforuminstanceids);
$selectedchatinstanceids = scormadvancedgrades_addmultiple_normalize_int_list($selectedchatinstanceids);
$selectedscorminstanceids = scormadvancedgrades_addmultiple_normalize_int_list($selectedscorminstanceids);
$selectedzoominstanceids = scormadvancedgrades_addmultiple_normalize_int_list($selectedzoominstanceids);

if (!$submitted && empty($selectedquizinstanceids)) {
    $selectedquizinstanceids = array_map(static function($activity): int {
        return (int)$activity->instanceid;
    }, $quizactivities);
}
if (!$submitted && empty($selectedassigninstanceids)) {
    $selectedassigninstanceids = array_map(static function($activity): int {
        return (int)$activity->instanceid;
    }, $assignactivities);
}
if (!$submitted && empty($selectedforuminstanceids)) {
    $selectedforuminstanceids = array_map(static function($activity): int {
        return (int)$activity->instanceid;
    }, $forumactivities);
}
if (!$submitted && empty($selectedchatinstanceids)) {
    $selectedchatinstanceids = array_map(static function($activity): int {
        return (int)$activity->instanceid;
    }, $chatactivities);
}
if (!$submitted && empty($selectedscorminstanceids)) {
    $selectedscorminstanceids = array_map(static function($activity): int {
        return (int)$activity->instanceid;
    }, $scormactivities);
}
if (!$submitted && empty($selectedzoominstanceids)) {
    $selectedzoominstanceids = array_map(static function($activity): int {
        return (int)$activity->instanceid;
    }, $zoomactivities);
}

$saved = false;
$error = '';
if ($submitted) {
    require_sesskey();

    $hasquizselection = !empty($selectedquizmetrics);
    $hasassignselection = !empty($selectedassignmetrics);
    $hasforumselection = !empty($selectedforummetrics);
    $haschatselection = !empty($selectedchatmetrics);
    $hasscormselection = !empty($selectedscormmetrics);
    $haszoomselection = !empty($selectedzoommetrics);
    if (!$hasquizselection && !$hasassignselection && !$hasforumselection && !$haschatselection && !$hasscormselection && !$haszoomselection) {
        $error = scormadvancedgrades_addmultiple_label(
            'scormadvancedgrades_addmultiple_nometrics',
            'Debes seleccionar al menos una opcion de columnas.'
        );
    } else if ($hasquizselection && empty($selectedquizinstanceids)) {
        $error = scormadvancedgrades_addmultiple_label(
            'scormadvancedgrades_addmultiple_noselection_quiz',
            'Debes seleccionar al menos un cuestionario.'
        );
    } else if ($hasassignselection && empty($selectedassigninstanceids)) {
        $error = scormadvancedgrades_addmultiple_label(
            'scormadvancedgrades_addmultiple_noselection_assign',
            'Debes seleccionar al menos una tarea.'
        );
    } else if ($hasforumselection && empty($selectedforuminstanceids)) {
        $error = scormadvancedgrades_addmultiple_label(
            'scormadvancedgrades_addmultiple_noselection_forum',
            'Debes seleccionar al menos un foro.'
        );
    } else if ($haschatselection && empty($selectedchatinstanceids)) {
        $error = scormadvancedgrades_addmultiple_label(
            'scormadvancedgrades_addmultiple_noselection_chat',
            'Debes seleccionar al menos un chat.'
        );
    } else if ($hasscormselection && empty($selectedscorminstanceids)) {
        $error = scormadvancedgrades_addmultiple_label(
            'scormadvancedgrades_addmultiple_noselection_scorm',
            'Debes seleccionar al menos un SCORM.'
        );
    } else if ($haszoomselection && empty($selectedzoominstanceids)) {
        $error = scormadvancedgrades_addmultiple_label(
            'scormadvancedgrades_addmultiple_noselection_zoom',
            'Debes seleccionar al menos una sesión Zoom.'
        );
    } else {
        scormadvancedgrades_addmultiple_save_columns(
            $report,
            [
                'quiz' => [
                    'instanceids' => $selectedquizinstanceids,
                    'metrics' => $selectedquizmetrics,
                    'metricoptions' => $quizmetricoptions,
                ],
                'assign' => [
                    'instanceids' => $selectedassigninstanceids,
                    'metrics' => $selectedassignmetrics,
                    'metricoptions' => $assignmetricoptions,
                ],
                'forum' => [
                    'instanceids' => $selectedforuminstanceids,
                    'metrics' => $selectedforummetrics,
                    'metricoptions' => $forummetricoptions,
                ],
                'chat' => [
                    'instanceids' => $selectedchatinstanceids,
                    'metrics' => $selectedchatmetrics,
                    'metricoptions' => $chatmetricoptions,
                ],
                'scorm' => [
                    'instanceids' => $selectedscorminstanceids,
                    'metrics' => $selectedscormmetrics,
                    'metricoptions' => $scormmetricoptions,
                ],
                'zoom' => [
                    'instanceids' => $selectedzoominstanceids,
                    'metrics' => $selectedzoommetrics,
                    'metricoptions' => $zoommetricoptions,
                ],
            ]
        );
        $saved = true;
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);

if ($saved) {
    echo $OUTPUT->notification(
        scormadvancedgrades_addmultiple_label('scormadvancedgrades_addmultiple_saved', 'Columnas anadidas correctamente.'),
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

if (empty($quizactivities) && empty($assignactivities) && empty($forumactivities) && empty($chatactivities) && empty($scormactivities) && empty($zoomactivities)) {
    echo $OUTPUT->notification(get_string('norecordsfound', 'block_configurable_reports'), 'notifyinfo');
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::tag(
    'p',
    scormadvancedgrades_addmultiple_label(
        'scormadvancedgrades_addmultiple_intro',
        'Selecciona columnas globales y actividades para anadir automaticamente estadisticas.'
    )
);

echo html_writer::start_tag('form', ['method' => 'post', 'action' => $url->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

scormadvancedgrades_addmultiple_render_module_block(
    'quiz',
    scormadvancedgrades_addmultiple_label('scormadvancedgrades_addmultiple_quizsection', 'Cuestionarios'),
    $quizmetricoptions,
    $selectedquizmetrics,
    $quizactivities,
    $selectedquizinstanceids
);

scormadvancedgrades_addmultiple_render_module_block(
    'assign',
    scormadvancedgrades_addmultiple_label('scormadvancedgrades_addmultiple_assignsection', 'Tareas'),
    $assignmetricoptions,
    $selectedassignmetrics,
    $assignactivities,
    $selectedassigninstanceids
);
scormadvancedgrades_addmultiple_render_module_block(
    'forum',
    scormadvancedgrades_addmultiple_label('scormadvancedgrades_addmultiple_forumsection', 'Foros'),
    $forummetricoptions,
    $selectedforummetrics,
    $forumactivities,
    $selectedforuminstanceids
);
scormadvancedgrades_addmultiple_render_module_block(
    'chat',
    scormadvancedgrades_addmultiple_label('scormadvancedgrades_addmultiple_chatsection', 'Chats'),
    $chatmetricoptions,
    $selectedchatmetrics,
    $chatactivities,
    $selectedchatinstanceids
);
scormadvancedgrades_addmultiple_render_module_block(
    'scorm',
    scormadvancedgrades_addmultiple_label('scormadvancedgrades_addmultiple_scormsection', 'SCORMs'),
    $scormmetricoptions,
    $selectedscormmetrics,
    $scormactivities,
    $selectedscorminstanceids
);
scormadvancedgrades_addmultiple_render_module_block(
    'zoom',
    scormadvancedgrades_addmultiple_label('scormadvancedgrades_addmultiple_zoomsection', 'Zoom'),
    $zoommetricoptions,
    $selectedzoommetrics,
    $zoomactivities,
    $selectedzoominstanceids
);

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
    // --- Accordion toggle (pure vanilla JS, no Bootstrap dependency) ---
    "  document.querySelectorAll('.cr-sag-toggle').forEach(function(btn){\n" .
    "    var bodyId = btn.getAttribute('data-target');\n" .
    "    var body = document.getElementById(bodyId);\n" .
    "    if (!body) return;\n" .
    "    var arrow = btn.querySelector('.cr-sag-arrow');\n" .
    "    btn.addEventListener('click', function(){\n" .
    "      var open = body.style.display !== 'none';\n" .
    "      body.style.display = open ? 'none' : 'block';\n" .
    "      btn.setAttribute('aria-expanded', open ? 'false' : 'true');\n" .
    "      if (arrow) arrow.style.transform = open ? '' : 'rotate(90deg)';\n" .
    "    });\n" .
    "  });\n" .
    // --- Select-all toggle (alternates between all-checked / all-unchecked) ---
    "  document.querySelectorAll('.cr-sag-select-all').forEach(function(link){\n" .
    "    var mod = link.getAttribute('data-mod');\n" .
    "    var allChecked = true;\n" .
    "    link.addEventListener('click', function(e){\n" .
    "      e.preventDefault();\n" .
    "      document.querySelectorAll('.cr-sag-activity-' + mod).forEach(function(box){\n" .
    "        box.checked = allChecked;\n" .
    "      });\n" .
    "      allChecked = !allChecked;\n" .
    "    });\n" .
    "  });\n" .
    "})();"
);

echo $OUTPUT->footer();

/**
 * Returns metric options for quiz module.
 *
 * @return array<string,array<string,mixed>>
 */
function scormadvancedgrades_addmultiple_get_quiz_metric_options(): array {
    return [
        'completiondate' => [
            'param' => 'addquizcompletiondate',
            'labelkey' => 'scormadvancedgrades_addmultiple_addquizcompletiondate',
            'fallback' => 'Anadir la fecha de realizacion de todos los cuestionarios',
            'default' => 0,
            'columnprefix' => 'FECHA DE REALIZACION ACTIVIDAD',
            'format' => 'datetime',
        ],
        'score' => [
            'param' => 'addquizscore',
            'labelkey' => 'scormadvancedgrades_addmultiple_addquizscore',
            'fallback' => 'Anadir la puntuacion de todos los cuestionarios',
            'default' => 0,
            'columnprefix' => 'PUNTUACION ACTIVIDAD',
            'format' => 'percent',
        ],
        'dedicationtime' => [
            'param' => 'addquizdedicationtime',
            'labelkey' => 'scormadvancedgrades_addmultiple_addquizdedicationtime',
            'fallback' => 'Anadir el tiempo de dedicacion de todos los cuestionarios',
            'default' => 0,
            'columnprefix' => 'TIEMPO DE DEDICACION ACTIVIDAD',
            'format' => 'text',
        ],
        'opendate' => [
            'param' => 'addquizopendate',
            'labelkey' => 'scormadvancedgrades_addmultiple_addquizopendate',
            'fallback' => 'Anadir la fecha de apertura de todos los cuestionarios',
            'default' => 0,
            'columnprefix' => 'FECHA DE APERTURA ACTIVIDAD',
            'format' => 'datetime',
        ],
        'firstpassattempt' => [
            'param' => 'addquizfirstpassattempt',
            'labelkey' => 'scormadvancedgrades_addmultiple_addquizfirstpassattempt',
            'fallback' => 'Anadir el primer intento aprobado de todos los cuestionarios',
            'default' => 0,
            'columnprefix' => 'PRIMER INTENTO APROBADO ACTIVIDAD',
            'format' => 'number',
        ],
    ];
}

/**
 * Returns metric options for assignment module.
 *
 * @return array<string,array<string,mixed>>
 */
function scormadvancedgrades_addmultiple_get_assign_metric_options(): array {
    return [
        'score' => [
            'param' => 'addassignscore',
            'labelkey' => 'scormadvancedgrades_addmultiple_addassignscore',
            'fallback' => 'Anadir la puntuacion de todas las tareas',
            'default' => 0,
            'columnprefix' => 'NOTA ACTIVIDAD',
            'format' => 'percent',
        ],
        'submissiondate' => [
            'param' => 'addassignsubmissiondate',
            'labelkey' => 'scormadvancedgrades_addmultiple_addassignsubmissiondate',
            'fallback' => 'Anadir fechas de entrega de tareas',
            'default' => 0,
            'columnprefix' => 'FECHA DE ENTREGA ACTIVIDAD',
            'format' => 'datetime',
        ],
        'gradedate' => [
            'param' => 'addassigngradedate',
            'labelkey' => 'scormadvancedgrades_addmultiple_addassigngradedate',
            'fallback' => 'Anadir fechas de correccion de tareas',
            'default' => 0,
            'columnprefix' => 'FECHA DE CORRECCION ACTIVIDAD',
            'format' => 'datetime',
        ],
        'grader' => [
            'param' => 'addassigngrader',
            'labelkey' => 'scormadvancedgrades_addmultiple_addassigngrader',
            'fallback' => 'Anadir usuario que ha corregido la tarea',
            'default' => 0,
            'columnprefix' => 'USUARIO QUE HA CORREGIDO ACTIVIDAD',
            'format' => 'text',
        ],
        'feedback' => [
            'param' => 'addassignfeedback',
            'labelkey' => 'scormadvancedgrades_addmultiple_addassignfeedback',
            'fallback' => 'Feedback en la entrega',
            'default' => 0,
            'columnprefix' => 'FEEDBACK EN LA ENTREGA ACTIVIDAD',
            'format' => 'text',
        ],
    ];
}

/**
 * Returns metric options for SCORM module.
 *
 * @return array<string,array<string,mixed>>
 */
function scormadvancedgrades_addmultiple_get_scorm_metric_options(): array {
    return [
        'completiondate' => [
            'param' => 'addscormcompletiondate',
            'labelkey' => 'scormadvancedgrades_addmultiple_addscormcompletiondate',
            'fallback' => 'Anadir fecha de finalizacion del SCORM',
            'default' => 0,
            'columnprefix' => 'FECHA FINALIZACION SCORM ACTIVIDAD',
            'format' => 'datetime',
        ],
        'score' => [
            'param' => 'addsCormscore',
            'labelkey' => 'scormadvancedgrades_addmultiple_addsCormscore',
            'fallback' => 'Anadir puntuacion del SCORM',
            'default' => 0,
            'columnprefix' => 'PUNTUACION SCORM ACTIVIDAD',
            'format' => 'percent',
        ],
        'scocompleted' => [
            'param' => 'addscormscocompleted',
            'labelkey' => 'scormadvancedgrades_addmultiple_addscormscocompleted',
            'fallback' => 'Anadir objetos SCO completados',
            'default' => 0,
            'columnprefix' => 'SCO COMPLETADOS SCORM ACTIVIDAD',
            'format' => 'number',
        ],
        'dedicationtime' => [
            'param' => 'addsCormdedicationtime',
            'labelkey' => 'scormadvancedgrades_addmultiple_addsCormdedicationtime',
            'fallback' => 'Anadir tiempo de dedicacion en el SCORM',
            'default' => 0,
            'columnprefix' => 'TIEMPO DEDICACION SCORM ACTIVIDAD',
            'format' => 'text',
        ],
        'lastaccess' => [
            'param' => 'addsCormlastaccess',
            'labelkey' => 'scormadvancedgrades_addmultiple_addsCormlastaccess',
            'fallback' => 'Anadir ultimo acceso al SCORM',
            'default' => 0,
            'columnprefix' => 'ULTIMO ACCESO SCORM ACTIVIDAD',
            'format' => 'datetime',
        ],
    ];
}

/**
 * Returns metric options for chat module.
 *
 * @return array<string,array<string,mixed>>
 */
function scormadvancedgrades_addmultiple_get_chat_metric_options(): array {
    return [
        'messages' => [
            'param' => 'addchatmessages',
            'labelkey' => 'scormadvancedgrades_addmultiple_addchatmessages',
            'fallback' => 'Anadir numero de mensajes en chats',
            'default' => 0,
            'columnprefix' => 'NUMERO MENSAJES CHAT ACTIVIDAD',
            'format' => 'number',
        ],
        'firstmessage' => [
            'param' => 'addchatfirstmessage',
            'labelkey' => 'scormadvancedgrades_addmultiple_addchatfirstmessage',
            'fallback' => 'Anadir primera fecha y hora en chats',
            'default' => 0,
            'columnprefix' => 'PRIMERA FECHA HORA CHAT ACTIVIDAD',
            'format' => 'datetime',
        ],
        'lastmessage' => [
            'param' => 'addchatlastmessage',
            'labelkey' => 'scormadvancedgrades_addmultiple_addchatlastmessage',
            'fallback' => 'Anadir ultima fecha y hora en chats',
            'default' => 0,
            'columnprefix' => 'ULTIMA FECHA HORA CHAT ACTIVIDAD',
            'format' => 'datetime',
        ],
    ];
}

/**
 * Returns metric options for forum module.
 *
 * @return array<string,array<string,mixed>>
 */
function scormadvancedgrades_addmultiple_get_forum_metric_options(): array {
    return [
        'posts' => [
            'param' => 'addforumposts',
            'labelkey' => 'scormadvancedgrades_addmultiple_addforumposts',
            'fallback' => 'Anadir numero posts de los usuarios en el foro',
            'default' => 0,
            'columnprefix' => 'NUMERO POSTS FORO ACTIVIDAD',
            'format' => 'number',
        ],
        'firstpost' => [
            'param' => 'addforumfirstpost',
            'labelkey' => 'scormadvancedgrades_addmultiple_addforumfirstpost',
            'fallback' => 'Anadir fecha del primer post',
            'default' => 0,
            'columnprefix' => 'FECHA PRIMER POST FORO ACTIVIDAD',
            'format' => 'datetime',
        ],
        'lastpost' => [
            'param' => 'addforumlastpost',
            'labelkey' => 'scormadvancedgrades_addmultiple_addforumlastpost',
            'fallback' => 'Anadir fecha del ultimo post',
            'default' => 0,
            'columnprefix' => 'FECHA ULTIMO POST FORO ACTIVIDAD',
            'format' => 'datetime',
        ],
    ];
}

/**
 * Returns metric options for Zoom module.
 *
 * @return array<string,array<string,mixed>>
 */
function scormadvancedgrades_addmultiple_get_zoom_metric_options(): array {
    return [
        'duration' => [
            'param' => 'addzoomduration',
            'labelkey' => 'scormadvancedgrades_addmultiple_addzoomduration',
            'fallback' => 'Añadir duración de las sesiones Zoom',
            'default' => 0,
            'columnprefix' => 'DURACION ZOOM ACTIVIDAD',
            'format' => 'text',
        ],
        'jointime' => [
            'param' => 'addzoomjointime',
            'labelkey' => 'scormadvancedgrades_addmultiple_addzoomjointime',
            'fallback' => 'Añadir hora de entrada a las sesiones Zoom',
            'default' => 0,
            'columnprefix' => 'HORA ENTRADA ZOOM ACTIVIDAD',
            'format' => 'datetime',
        ],
        'leavetime' => [
            'param' => 'addzoomleavetime',
            'labelkey' => 'scormadvancedgrades_addmultiple_addzoomleavetime',
            'fallback' => 'Añadir hora de salida a las sesiones Zoom',
            'default' => 0,
            'columnprefix' => 'HORA SALIDA ZOOM ACTIVIDAD',
            'format' => 'datetime',
        ],
        'ip' => [
            'param' => 'addzoomip',
            'labelkey' => 'scormadvancedgrades_addmultiple_addzoomip',
            'fallback' => 'Añadir IP al conectar a las sesiones Zoom',
            'default' => 0,
            'columnprefix' => 'IP ZOOM ACTIVIDAD',
            'format' => 'text',
        ],
        'durationinschedule' => [
            'param' => 'addzoomdurationinschedule',
            'labelkey' => 'scormadvancedgrades_addmultiple_addzoomdurationinschedule',
            'fallback' => 'Agregar duración de las sesiones de Zoom dentro de los tiempos de programación de sesiones',
            'default' => 0,
            'columnprefix' => 'DURACION HORARIO ZOOM ACTIVIDAD',
            'format' => 'text',
        ],
    ];
}

/**
 * Collects selected metrics from request.
 *
 * @param array<string,array<string,mixed>> $metricoptions
 * @param bool $submitted
 * @return array<int,string>
 */
function scormadvancedgrades_addmultiple_collect_selected_metrics(array $metricoptions, bool $submitted): array {
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

    return $selectedmetrics;
}

/**
 * Renders one module block as a collapsible accordion in the popup.
 *
 * @param string $modkey
 * @param string $sectiontitle
 * @param array<string,array<string,mixed>> $metricoptions
 * @param array<int,string> $selectedmetrics
 * @param array<int,object> $activities
 * @param array<int> $selectedinstanceids
 * @return void
 */
function scormadvancedgrades_addmultiple_render_module_block(
    string $modkey,
    string $sectiontitle,
    array $metricoptions,
    array $selectedmetrics,
    array $activities,
    array $selectedinstanceids
): void {
    if (empty($metricoptions) || empty($activities)) {
        return;
    }

    $collapseid = 'cr-sag-body-' . $modkey;
    // Start collapsed; JS will open on click.
    $bodystyle = 'display:none;';

    echo html_writer::start_tag('div', ['class' => 'cr-sag-accordion mt-2']);

    // Header button.
    echo html_writer::start_tag('button', [
        'type' => 'button',
        'class' => 'cr-sag-toggle w-100 d-flex align-items-center justify-content-between p-2 border rounded',
        'data-target' => $collapseid,
        'style' => 'background:#f8f9fa;cursor:pointer;text-align:left;',
        'aria-expanded' => 'false',
    ]);
    echo html_writer::tag('strong', s($sectiontitle));
    echo html_writer::tag('span', '&#9654;', [
        'class' => 'cr-sag-arrow',
        'aria-hidden' => 'true',
        'style' => 'font-size:0.8em;transition:transform 0.2s;display:inline-block;',
    ]);
    echo html_writer::end_tag('button');

    // Body (hidden by default).
    echo html_writer::start_tag('div', [
        'id' => $collapseid,
        'style' => $bodystyle,
        'class' => 'cr-sag-body border border-top-0 rounded-bottom p-3',
    ]);

    // Metric checkboxes.
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

    // Activity sub-heading + select-all link.
    $activityheading = scormadvancedgrades_addmultiple_label(
        'scormadvancedgrades_addmultiple_activitysectionheading',
        'Selecciona de las actividades de tipo "{$a}"',
        s($sectiontitle)
    );
    echo html_writer::tag('p', $activityheading, ['class' => 'font-weight-bold mb-1']);
    echo html_writer::div(
        html_writer::link(
            '#',
            scormadvancedgrades_addmultiple_label('scormadvancedgrades_addmultiple_selectall', 'Seleccionar todos/ninguno'),
            ['class' => 'cr-sag-select-all', 'data-mod' => $modkey]
        ),
        'mb-2'
    );

    // Activity table.
    $table = new html_table();
    $table->attributes['class'] = 'generaltable';
    $table->head = [
        scormadvancedgrades_addmultiple_label('scormadvancedgrades_addmultiple_activitycol', 'Actividad'),
    ];
    $table->data = [];

    foreach ($activities as $index => $activity) {
        $instanceid = (int)$activity->instanceid;
        $checked = in_array($instanceid, $selectedinstanceids, true);
        $inputattrs = [
            'type' => 'checkbox',
            'class' => 'cr-sag-activity-' . $modkey,
            'name' => $modkey . 'instance[]',
            'value' => $instanceid,
        ];
        if ($checked) {
            $inputattrs['checked'] = 'checked';
        }

        $name = trim((string)$activity->name);
        if ($name === '') {
            $name = scormadvancedgrades_addmultiple_label(
                'scormadvancedgrades_addmultiple_fallbackname',
                'Actividad {$a}',
                $index + 1
            );
        }

        $table->data[] = [
            html_writer::start_tag('label', ['class' => 'd-flex align-items-center mb-0']) .
            html_writer::empty_tag('input', $inputattrs) .
            html_writer::tag('span', format_string($name), ['class' => 'ml-2']) .
            html_writer::end_tag('label'),
        ];
    }

    echo html_writer::table($table);

    echo html_writer::end_tag('div'); // body
    echo html_writer::end_tag('div'); // accordion wrapper
}

/**
 * Gets visible module activities in course order.
 *
 * @param int $courseid
 * @param string $modname
 * @return array<int,object>
 */
function scormadvancedgrades_addmultiple_get_module_activities(int $courseid, string $modname): array {
    global $DB;

    $modtotable = [
        'quiz' => 'quiz',
        'assign' => 'assign',
        'forum' => 'forum',
        'chat' => 'chat',
        'scorm' => 'scorm',
        'zoom' => 'zoom',
    ];
    if ($courseid <= 0 || !isset($modtotable[$modname])) {
        return [];
    }

    $nametable = $modtotable[$modname];
    $sql = "SELECT cm.id AS cmid,
                   cm.instance AS instanceid,
                   a.name
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module
              JOIN {" . $nametable . "} a ON a.id = cm.instance
             WHERE cm.course = :courseid
               AND cm.visible = 1
               AND m.name = :modname
          ORDER BY cm.section ASC, cm.added ASC, cm.id ASC";
    $records = $DB->get_records_sql($sql, ['courseid' => $courseid, 'modname' => $modname]);

    return array_values($records);
}

/**
 * Saves generated columns into report definition.
 *
 * @param object $report
 * @param array<string,array<string,mixed>> $modulepayloads
 * @return void
 */
function scormadvancedgrades_addmultiple_save_columns(object $report, array $modulepayloads): void {
    global $DB;

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

        $stat = isset($formdata->stat) ? trim((string)$formdata->stat) : '';
        if ($stat !== '' && preg_match(
            '/^(quiz:\d+:(completiondate|score|dedicationtime|opendate|firstpassattempt)|assign:\d+:(score|submissiondate|gradedate|grader|feedback)|forum:\d+:(posts|firstpost|lastpost)|chat:\d+:(messages|firstmessage|lastmessage)|scorm:\d+:(completiondate|score|scocompleted|dedicationtime|lastaccess)|zoom:\d+:(duration|jointime|leavetime|ip|durationinschedule))$/',
            $stat
        )) {
            return false;
        }

        $name = isset($formdata->columname) ? trim((string)$formdata->columname) : '';
        if ($name !== '' && preg_match(
            '/^(NOTA|PUNTUACION|FECHA DE ENTREGA|FECHA DE CORRECCION|USUARIO QUE HA CORREGIDO|FEEDBACK EN LA ENTREGA|FECHA DE REALIZACION|TIEMPO DE DEDICACION|FECHA DE APERTURA|PRIMER INTENTO APROBADO|NUMERO POSTS FORO|FECHA PRIMER POST FORO|FECHA ULTIMO POST FORO|NUMERO MENSAJES CHAT|PRIMERA FECHA HORA CHAT|ULTIMA FECHA HORA CHAT|FECHA FINALIZACION SCORM|PUNTUACION SCORM|SCO COMPLETADOS SCORM|TIEMPO DEDICACION SCORM|ULTIMO ACCESO SCORM|DURACION ZOOM|HORA ENTRADA ZOOM|HORA SALIDA ZOOM|IP ZOOM|DURACION HORARIO ZOOM)\s+ACTIVIDAD\s+\d+$/iu',
            $name
        )) {
            return false;
        }

        return true;
    }));

    $pluginclass = new plugin_scormadvancedgrades($report);
    $usedids = [];
    foreach ($elements as $element) {
        if (is_array($element) && !empty($element['id'])) {
            $usedids[(string)$element['id']] = true;
        }
    }

    foreach ($modulepayloads as $modname => $payload) {
        $instanceids = $payload['instanceids'] ?? [];
        $metrics = $payload['metrics'] ?? [];
        $metricoptions = $payload['metricoptions'] ?? [];
        if (
            !in_array($modname, ['quiz', 'assign', 'forum', 'chat', 'scorm', 'zoom'], true) ||
            !is_array($instanceids) ||
            !is_array($metrics) ||
            !is_array($metricoptions) ||
            empty($instanceids) ||
            empty($metrics)
        ) {
            continue;
        }

        $activityindex = 1;
        foreach ($instanceids as $instanceid) {
            $instanceid = (int)$instanceid;
            if ($instanceid <= 0) {
                continue;
            }

            foreach ($metrics as $metrickey) {
                if (!isset($metricoptions[$metrickey])) {
                    continue;
                }
                $metricdata = $metricoptions[$metrickey];
                $formdata = (object)[
                    'columname' => (string)$metricdata['columnprefix'] . ' ' . $activityindex,
                    'stat' => $modname . ':' . $instanceid . ':' . $metrickey,
                    'format' => (string)$metricdata['format'],
                    'align' => 'center',
                    'size' => '',
                    'wrap' => '',
                ];
                $elements[] = scormadvancedgrades_addmultiple_build_element($pluginclass, $formdata, $usedids);
            }

            $activityindex++;
        }
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
