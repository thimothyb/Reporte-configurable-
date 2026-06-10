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
 * Exports a configurable_reports global report as PDF for a single user.
 *
 * URL params:
 *   id       – report ID (the global/main report)
 *   courseid – course ID
 *   userid   – target user ID
 *   sesskey  – Moodle session key (CSRF protection)
 *
 * @package    block_configurable_reports
 */

define('NO_OUTPUT_BUFFERING', true);

require_once('../../config.php');
require_once($CFG->dirroot . '/blocks/configurable_reports/plugin.class.php');
require_once($CFG->dirroot . '/blocks/configurable_reports/locallib.php');
require_once($CFG->dirroot . '/blocks/configurable_reports/components/columns/userstatsadvanced/messages_lib.php');

$pdflibpath = $CFG->libdir . '/pdflib.php';
if (!file_exists($pdflibpath)) {
    throw new moodle_exception('error', '', '', null, 'PDF library (lib/pdflib.php) not found.');
}
require_once($pdflibpath);

// ── Parameters & security ─────────────────────────────────────────────────────

$reportid = required_param('id',       PARAM_INT);
$courseid = required_param('courseid', PARAM_INT);
$userid   = required_param('userid',   PARAM_INT);

require_sesskey();
require_login();

$course     = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context    = context_course::instance($courseid);

if ((int)$USER->id !== $userid) {
    require_capability('block/configurable_reports:manageownreports', $context);
}

$targetuser = $DB->get_record('user', ['id' => $userid],   '*', MUST_EXIST);
$reportrec  = $DB->get_record('block_configurable_reports', ['id' => $reportid], '*', MUST_EXIST);

// ── Helper: execute all column plugins for a report/user ──────────────────────

/**
 * Iterates the column elements of a report, executes each plugin for the given
 * user, and returns key-value rows.
 *
 * Also populates:
 *   $subreportids  – modal-linked sub-report IDs found
 *   $foundstats    – stat_type values found (for message detail rendering)
 *
 * @param  object   $reportrec
 * @param  object   $userrow        Full user record (used as $row and $user).
 * @param  int      $courseid
 * @param  int[]    $subreportids   Accumulated modal report IDs (passed by ref).
 * @param  string[] $foundstats     Accumulated stat types  (passed by ref).
 * @return array<int,array{label:string,value:string}>
 */
function cr_pdf_execute_columns(
    object $reportrec,
    object $userrow,
    int $courseid,
    array &$subreportids,
    array &$foundstats
): array {
    global $CFG;

    $components  = cr_unserialize((string)$reportrec->components);
    $columnelems = $components['columns']['elements'] ?? [];
    $rows        = [];

    foreach ($columnelems as $elem) {
        $pname = is_array($elem['pluginname'])
            ? (string)reset($elem['pluginname'])
            : (string)($elem['pluginname'] ?? '');
        $pname = trim($pname);
        if ($pname === '') {
            continue;
        }

        $formdata = $elem['formdata'] ?? null;
        if (is_array($formdata)) {
            $formdata = (object)$formdata;
        }
        if (!is_object($formdata)) {
            continue;
        }

        // Track stat types for message-detail rendering.
        $stattype = !empty($formdata->stat_type) ? (string)$formdata->stat_type : '';
        if ($stattype !== '' && !in_array($stattype, $foundstats, true)) {
            $foundstats[] = $stattype;
        }

        // Collect modal-linked sub-report IDs.
        $modalid = isset($formdata->modalreportid) ? (int)$formdata->modalreportid : 0;
        if ($modalid > 0 && !in_array($modalid, $subreportids, true)) {
            $subreportids[] = $modalid;
        }

        $pluginfile = $CFG->dirroot . '/blocks/configurable_reports/components/columns/' . $pname . '/plugin.class.php';
        if (!file_exists($pluginfile)) {
            continue;
        }
        require_once($pluginfile);
        $pclass = 'plugin_' . $pname;
        if (!class_exists($pclass)) {
            continue;
        }

        /** @var plugin_base $plugin */
        $plugin = new $pclass($reportrec);
        $label  = trim(strip_tags(format_string($plugin->summary($formdata))));

        try {
            $rawval = (string)$plugin->execute($formdata, $userrow, $userrow, $courseid);
        } catch (Throwable $e) {
            $rawval = '';
        }

        // Strip button-style anchors, then all remaining HTML.
        $rawval   = preg_replace('/<a\b[^>]*class="[^"]*btn[^"]*"[^>]*>.*?<\/a>/si', '', $rawval);
        $plainval = html_entity_decode(strip_tags($rawval), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plainval = trim(preg_replace('/\s+/', ' ', $plainval));

        $rows[] = ['label' => $label, 'value' => $plainval];
    }

    return $rows;
}

// ── Helper: 2-column key/value table HTML ─────────────────────────────────────

function cr_pdf_two_col_table(array $rows): string {
    if (empty($rows)) {
        return '';
    }
    $html  = '<table border="1" cellspacing="0" cellpadding="4" style="width:100%;border-color:#aaaaaa;">';
    foreach ($rows as $r) {
        $lbl   = htmlspecialchars((string)$r['label'], ENT_QUOTES, 'UTF-8');
        $val   = htmlspecialchars((string)$r['value'], ENT_QUOTES, 'UTF-8');
        $html .= '<tr>'
            . '<td style="width:50%;font-size:9pt;">' . $lbl . '</td>'
            . '<td style="width:50%;text-align:center;font-size:9pt;">' . $val . '</td>'
            . '</tr>';
    }
    $html .= '</table>';
    return $html;
}

// ── Message detail helpers ────────────────────────────────────────────────────

/** Stat types that produce a direct-message detail table. */
const CR_PDF_DIRECT_MSG_TYPES = ['correos', 'mensajes_tutor', 'mensajes_alumnos'];
/** Stat types that produce a forum-post detail table. */
const CR_PDF_FORUM_MSG_TYPES  = ['interacciones_foros', 'mensajes_foro', 'foros_publicados'];

/**
 * Strips HTML and truncates text to $maxlen characters.
 */
function cr_pdf_plain(string $html, int $maxlen = 400): string {
    $plain = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $plain = trim(preg_replace('/\s+/', ' ', $plain));
    if (core_text::strlen($plain) > $maxlen) {
        $plain = core_text::substr($plain, 0, $maxlen - 3) . '...';
    }
    return $plain;
}

/**
 * Builds a 5-column message detail table (De | Para | Asunto | Mensaje | Fecha).
 *
 * @param  string $title   Section heading.
 * @param  array  $records Rows from userstatsadvanced_show_messages_get_*().
 * @param  bool   $isforum True when records have 'forumname' instead of 'toname'.
 * @return string HTML
 */
function cr_pdf_message_table(string $title, array $records, bool $isforum = false): string {
    if (empty($records)) {
        return '';
    }
    $col3 = $isforum ? 'Foro' : 'Para';
    $html  = '<br/>';
    $html .= '<p style="font-size:10pt;font-weight:bold;">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</p>';
    $html .= '<table border="1" cellspacing="0" cellpadding="3" style="width:100%;border-color:#aaaaaa;font-size:8pt;">';
    $html .= '<tr style="background-color:#dddddd;font-weight:bold;">'
        . '<td>De</td><td>' . $col3 . '</td><td>Asunto</td><td>Mensaje</td><td>Fecha</td>'
        . '</tr>';
    foreach ($records as $r) {
        $from    = htmlspecialchars(cr_pdf_plain((string)($r['fromname']  ?? '-'), 60), ENT_QUOTES, 'UTF-8');
        $to      = htmlspecialchars(cr_pdf_plain((string)($isforum ? ($r['forumname'] ?? '-') : ($r['toname'] ?? '-')), 60), ENT_QUOTES, 'UTF-8');
        $subj    = htmlspecialchars(cr_pdf_plain((string)($r['subject']   ?? ''), 120), ENT_QUOTES, 'UTF-8');
        $msg     = htmlspecialchars(cr_pdf_plain((string)($r['messagehtml'] ?? ''), 400), ENT_QUOTES, 'UTF-8');
        $created = htmlspecialchars(strip_tags(str_replace('<br>', ' ', (string)($r['createdhtml'] ?? ''))), ENT_QUOTES, 'UTF-8');
        $html .= '<tr>'
            . '<td style="width:12%;">' . $from . '</td>'
            . '<td style="width:12%;">' . $to   . '</td>'
            . '<td style="width:20%;">' . $subj . '</td>'
            . '<td style="width:42%;">' . $msg  . '</td>'
            . '<td style="width:14%;">' . $created . '</td>'
            . '</tr>';
    }
    $html .= '</table>';
    return $html;
}

/**
 * Builds a 4-column chat detail table (De | Chat | Mensaje | Fecha).
 *
 * @param  int $userid
 * @param  int $courseid
 * @return string HTML
 */
function cr_pdf_chat_table(int $userid, int $courseid): string {
    $records = userstatsadvanced_show_messages_get_chat_records($userid, $courseid);
    if (empty($records)) {
        return '';
    }
    $html  = '<br/>';
    $html .= '<p style="font-size:10pt;font-weight:bold;">Mensajes de Chat</p>';
    $html .= '<table border="1" cellspacing="0" cellpadding="3" style="width:100%;border-color:#aaaaaa;font-size:8pt;">';
    $html .= '<tr style="background-color:#dddddd;font-weight:bold;">'
        . '<td>De</td><td>Chat</td><td>Mensaje</td><td>Fecha</td>'
        . '</tr>';
    foreach ($records as $r) {
        $from    = htmlspecialchars(cr_pdf_plain((string)($r['fromname'] ?? '-'), 60), ENT_QUOTES, 'UTF-8');
        $chat    = htmlspecialchars(cr_pdf_plain((string)($r['chatname'] ?? '-'), 60), ENT_QUOTES, 'UTF-8');
        $msg     = htmlspecialchars(cr_pdf_plain((string)($r['message']  ?? ''), 400), ENT_QUOTES, 'UTF-8');
        $created = htmlspecialchars(userdate((int)($r['createdts'] ?? 0), '%d/%m/%Y %H:%M'), ENT_QUOTES, 'UTF-8');
        $html .= '<tr>'
            . '<td style="width:14%;">' . $from    . '</td>'
            . '<td style="width:14%;">' . $chat    . '</td>'
            . '<td style="width:54%;">' . $msg     . '</td>'
            . '<td style="width:18%;">' . $created . '</td>'
            . '</tr>';
    }
    $html .= '</table>';
    return $html;
}

/**
 * Builds all message-detail HTML sections for a given set of stat types.
 * Avoids duplicating a section type across multiple columns of the same type.
 *
 * @param  string[]  $stattypes   Stat types found in the report's columns.
 * @param  int       $userid
 * @param  int       $courseid
 * @param  context   $context
 * @return string HTML
 */
function cr_pdf_message_detail_sections(
    array $stattypes,
    int $userid,
    int $courseid,
    context $context
): string {
    $html = '';
    $directdone = false;
    $forumdone  = false;
    $chatdone   = false;

    foreach ($stattypes as $st) {
        if (!$directdone && in_array($st, CR_PDF_DIRECT_MSG_TYPES, true)) {
            $records = userstatsadvanced_show_messages_get_direct_records($userid, $courseid, $st, $context);
            $html .= cr_pdf_message_table('Correos y Mensajes Directos', $records, false);
            $directdone = true;
        }
        if (!$forumdone && in_array($st, CR_PDF_FORUM_MSG_TYPES, true)) {
            $records = userstatsadvanced_show_messages_get_forum_records($userid, $courseid, $context);
            $html .= cr_pdf_message_table('Mensajes en Foro', $records, true);
            $forumdone = true;
        }
    }

    // Chat messages: include whenever any message stat is present.
    if (!$chatdone && ($directdone || $forumdone)) {
        $html    .= cr_pdf_chat_table($userid, $courseid);
        $chatdone = true;
    }

    return $html;
}

// ── Enrollment dates ──────────────────────────────────────────────────────────

$enrolrec = $DB->get_record_sql(
    "SELECT MIN(ue.timestart) AS tstart, MAX(ue.timeend) AS tend
       FROM {user_enrolments} ue
       JOIN {enrol} e ON e.id = ue.enrolid
      WHERE ue.userid = :uid AND e.courseid = :cid",
    ['uid' => $userid, 'cid' => $courseid]
);
$startstr = ($enrolrec && (int)$enrolrec->tstart > 0)
    ? userdate((int)$enrolrec->tstart, get_string('strftimedate', 'langconfig'))
    : userdate(time(), get_string('strftimedate', 'langconfig'));
$endstr = ($enrolrec && (int)$enrolrec->tend > 0)
    ? userdate((int)$enrolrec->tend, get_string('strftimedate', 'langconfig'))
    : 'No definida';

// ── Execute main report columns ───────────────────────────────────────────────

$subreportids  = [];
$mainstattypes = [];
$mainrows      = cr_pdf_execute_columns($reportrec, $targetuser, $courseid, $subreportids, $mainstattypes);

// ── Execute each sub-report ───────────────────────────────────────────────────

$subreportsdata = [];
foreach ($subreportids as $srid) {
    $srec = $DB->get_record('block_configurable_reports', ['id' => $srid]);
    if (!$srec) {
        continue;
    }
    if ((int)$srec->courseid !== $courseid && empty($srec->global)) {
        continue;
    }
    $dummy     = [];
    $srstats   = [];
    $srrows    = cr_pdf_execute_columns($srec, $targetuser, $courseid, $dummy, $srstats);
    $subreportsdata[] = [
        'name'      => format_string((string)$srec->name),
        'rows'      => $srrows,
        'stattypes' => $srstats,
    ];
}

// ── Build PDF HTML ────────────────────────────────────────────────────────────

$coursename  = format_string($course->fullname);
$participant = fullname($targetuser);

$html  = '<p style="font-size:12pt;font-weight:bold;">'
    . 'Curso: ' . htmlspecialchars($coursename, ENT_QUOTES, 'UTF-8') . '</p>';
$html .= '<p style="font-size:11pt;font-weight:bold;">'
    . 'Participante: ' . htmlspecialchars($participant, ENT_QUOTES, 'UTF-8') . '</p>';
$html .= '<p style="font-size:11pt;font-weight:bold;">'
    . 'Fecha de inicio: ' . $startstr . ' - Fecha de fin: ' . $endstr . '</p>';
$html .= '<hr style="border-color:#cccccc;"/>';
$html .= '<p style="font-size:10pt;font-weight:bold;">Estadisticas del curso</p>';
$html .= cr_pdf_two_col_table($mainrows);

// Message detail sections for the main report (if applicable).
$html .= cr_pdf_message_detail_sections($mainstattypes, $userid, $courseid, $context);

foreach ($subreportsdata as $sr) {
    $html .= '<br/>';
    $html .= '<p style="font-size:10pt;font-weight:bold;">'
        . htmlspecialchars($sr['name'], ENT_QUOTES, 'UTF-8') . '</p>';
    $html .= cr_pdf_two_col_table($sr['rows']);

    // Message detail sections for this sub-report (correos, foros, chat).
    $html .= cr_pdf_message_detail_sections($sr['stattypes'], $userid, $courseid, $context);
}

// ── Render PDF ────────────────────────────────────────────────────────────────

// helvetica is a core PDF font — always available without external font files.
// ISO-8859-1 covers all Spanish characters (á é í ó ú ñ ü ¿ ¡).
$pdf = new pdf('P', 'mm', 'A4', false, 'ISO-8859-1', false);
$pdf->SetCreator(format_string(get_site()->fullname));
$pdf->SetTitle($coursename . ' - ' . $participant);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();
$pdf->SetFont('helvetica', '', 9);

// Convert from UTF-8 to ISO-8859-1 so helvetica renders accented chars correctly.
$html = mb_convert_encoding($html, 'ISO-8859-1', 'UTF-8');
$pdf->writeHTML($html, true, false, true, false, '');

// Safe filename: SHORTCOURSE-REPORTNAME-FULLNAME.pdf
$safecourse = preg_replace('/[^A-Za-z0-9\-]/', '_', strtoupper((string)($course->shortname ?? 'CURSO')));
$safename   = preg_replace('/[^A-Za-z0-9\-]/', '_', strtoupper($participant));
$saferep    = preg_replace('/[^A-Za-z0-9\-]/', '_', strtoupper(format_string((string)$reportrec->name)));
$filename   = $safecourse . '-' . $saferep . '-' . $safename . '.pdf';

$pdf->Output($filename, 'D');
exit;
