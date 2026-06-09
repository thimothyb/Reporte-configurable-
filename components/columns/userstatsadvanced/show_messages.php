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
 * Message detail view for userstatsadvanced.
 *
 * @package    block_configurable_reports
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../../../../config.php");

$courseid = required_param('courseid', PARAM_INT);
$userid = required_param('userid', PARAM_INT);
$stattype = optional_param('stat_type', '', PARAM_ALPHANUMEXT);
$reportid = optional_param('reportid', 0, PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$targetuser = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
if (!empty($targetuser->deleted)) {
    throw new moodle_exception('invaliduser');
}

if ((int)$course->id === SITEID) {
    require_login();
    $context = context_system::instance();
} else {
    require_login($course);
    $context = context_course::instance($course->id);
}

$url = new moodle_url('/blocks/configurable_reports/components/columns/userstatsadvanced/show_messages.php', [
    'courseid' => $courseid,
    'userid' => $userid,
    'stat_type' => $stattype,
]);
if ($reportid > 0) {
    $url->param('reportid', $reportid);
}

$isforumstat = userstatsadvanced_show_messages_is_forum_stat($stattype);
$title = $isforumstat
    ? get_string('userstatsadvanced_forum_messages_detail', 'block_configurable_reports')
    : get_string('userstatsadvanced_messages_detail', 'block_configurable_reports');

$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_url($url);
$PAGE->set_title($title);
$PAGE->set_heading(format_string($course->fullname));

$records = $isforumstat
    ? userstatsadvanced_show_messages_get_forum_records($userid, $courseid, $context)
    : userstatsadvanced_show_messages_get_direct_records($userid, $courseid, $stattype, $context);

$table = new html_table();
$table->attributes['class'] = 'generaltable table table-striped';
$table->data = [];

if ($isforumstat) {
    $table->head = [
        get_string('userstatsadvanced_from', 'block_configurable_reports'),
        get_string('userstatsadvanced_forum', 'block_configurable_reports'),
        get_string('userstatsadvanced_subject', 'block_configurable_reports'),
        get_string('userstatsadvanced_message', 'block_configurable_reports'),
        get_string('userstatsadvanced_created', 'block_configurable_reports'),
    ];
    foreach ($records as $record) {
        $table->data[] = [
            s($record['fromname']),
            s($record['forumname']),
            s($record['subject']),
            $record['messagehtml'],
            $record['createdhtml'],
        ];
    }
} else {
    $table->head = [
        get_string('userstatsadvanced_from', 'block_configurable_reports'),
        get_string('userstatsadvanced_to', 'block_configurable_reports'),
        get_string('userstatsadvanced_subject', 'block_configurable_reports'),
        get_string('userstatsadvanced_message', 'block_configurable_reports'),
        get_string('userstatsadvanced_created', 'block_configurable_reports'),
    ];
    foreach ($records as $record) {
        $table->data[] = [
            s($record['fromname']),
            s($record['toname']),
            s($record['subject']),
            $record['messagehtml'],
            $record['createdhtml'],
        ];
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading($title);
echo html_writer::div(
    get_string('userstatsadvanced_user_label', 'block_configurable_reports') . ': ' . fullname($targetuser),
    'mb-3'
);

if (empty($table->data)) {
    echo $OUTPUT->notification(get_string('norecordsfound', 'block_configurable_reports'));
}

echo html_writer::start_div('table-responsive');
echo html_writer::table($table);
echo html_writer::end_div();
echo $OUTPUT->footer();

/**
 * Returns whether the selected stat type belongs to forum message metrics.
 *
 * @param string $stattype
 * @return bool
 */
function userstatsadvanced_show_messages_is_forum_stat(string $stattype): bool {
    return in_array($stattype, ['interacciones_foros', 'mensajes_foro', 'foros_publicados'], true);
}

/**
 * Returns target course archetypes for direct message stats.
 *
 * @param string $stattype
 * @return array
 */
function userstatsadvanced_show_messages_get_target_archetypes(string $stattype): array {
    if ($stattype === 'mensajes_alumnos') {
        return ['student'];
    }

    return ['editingteacher', 'teacher'];
}

/**
 * Returns direct message rows normalized for rendering.
 *
 * @param int $userid
 * @param int $courseid
 * @param string $stattype
 * @param context $context
 * @return array
 */
function userstatsadvanced_show_messages_get_direct_records(
    int $userid,
    int $courseid,
    string $stattype,
    context $context
): array {
    global $DB;

    if ($stattype === 'correos') {
        $targetids = userstatsadvanced_show_messages_get_target_ids_for_correos($courseid, $userid);
    } else {
        $targetarchetypes = userstatsadvanced_show_messages_get_target_archetypes($stattype);
        $targetids = userstatsadvanced_show_messages_get_course_user_ids_by_archetypes($courseid, $targetarchetypes, $userid);
    }

    $directrecords = [];
    $dbman = $DB->get_manager();
    $sources = [];
    if ($dbman->table_exists('message_messages') && $dbman->table_exists('message_conversation_members')) {
        $sources[] = ['type' => 'conversation', 'table' => 'message_messages'];
    }
    if ($dbman->table_exists('message')) {
        $sources[] = ['type' => 'legacy', 'table' => 'message'];
    }
    if ($dbman->table_exists('messages') && $dbman->table_exists('message_conversation_members')) {
        $sources[] = ['type' => 'conversation', 'table' => 'messages'];
    }

    if (!empty($targetids)) {
        foreach ($sources as $source) {
            if ($source['type'] === 'conversation') {
                $records = userstatsadvanced_show_messages_get_conversation_records(
                    $source['table'],
                    $userid,
                    $targetids,
                    $context
                );
            } else {
                $records = userstatsadvanced_show_messages_get_legacy_records($source['table'], $userid, $targetids, $context);
            }
            if (!empty($records)) {
                $directrecords = $records;
                break;
            }
        }
    }

    if ($stattype === 'correos') {
        $forumrecords = userstatsadvanced_show_messages_get_staff_forum_mail_records($userid, $courseid, $context);
        return userstatsadvanced_show_messages_merge_records_by_date($directrecords, $forumrecords);
    }

    if ($stattype === 'mensajes_alumnos') {
        $forumrecords = userstatsadvanced_show_messages_get_forum_records_with_students($userid, $courseid, $context);
        return userstatsadvanced_show_messages_merge_records_by_date($directrecords, $forumrecords);
    }

    return $directrecords;
}

/**
 * Returns target user IDs for "correos" metric detail.
 *
 * @param int $courseid
 * @param int $excludeuserid
 * @return array
 */
function userstatsadvanced_show_messages_get_target_ids_for_correos(int $courseid, int $excludeuserid = 0): array {
    $targetids = userstatsadvanced_show_messages_get_course_user_ids_by_archetypes(
        $courseid,
        ['editingteacher', 'teacher', 'manager'],
        $excludeuserid
    );
    $targetids = array_merge(
        $targetids,
        userstatsadvanced_show_messages_get_course_nonstudent_user_ids($courseid, $excludeuserid)
    );
    $targetids = array_merge($targetids, userstatsadvanced_show_messages_get_course_staff_user_ids_by_capability(
        $courseid,
        $excludeuserid
    ));

    if (empty($targetids)) {
        return [];
    }

    $targetids = array_map('intval', $targetids);
    $targetids = array_filter($targetids, static function(int $id): bool {
        return $id > 0;
    });
    $targetids = array_values(array_unique($targetids));

    return $targetids;
}

/**
 * Returns direct message rows from conversation-based tables.
 *
 * @param string $tablename
 * @param int $userid
 * @param array $targetids
 * @param context $context
 * @return array
 */
function userstatsadvanced_show_messages_get_conversation_records(
    string $tablename,
    int $userid,
    array $targetids,
    context $context
): array {
    global $DB;

    $columns = userstatsadvanced_show_messages_get_table_columns($tablename);
    if (
        empty($columns['id']) ||
        empty($columns['useridfrom']) ||
        empty($columns['conversationid']) ||
        empty($columns['timecreated'])
    ) {
        return [];
    }

    [$targetexistsinsql, $targetexistsparams] = $DB->get_in_or_equal($targetids, SQL_PARAMS_NAMED, 'tgexists');
    [$targetfrominsql, $targetfromparams] = $DB->get_in_or_equal($targetids, SQL_PARAMS_NAMED, 'tgfrom');
    $params = array_merge(
        ['userid' => $userid, 'useridfrom' => $userid],
        $targetexistsparams,
        $targetfromparams
    );

    $selectfields = [
        'm.id',
        'm.useridfrom',
        'm.conversationid',
        'm.timecreated',
        userstatsadvanced_show_messages_optional_column_sql($columns, 'subject', "''"),
        userstatsadvanced_show_messages_optional_column_sql($columns, 'fullmessage', "''"),
        userstatsadvanced_show_messages_optional_column_sql($columns, 'fullmessageformat', '0'),
        userstatsadvanced_show_messages_optional_column_sql($columns, 'fullmessagehtml', "''"),
        userstatsadvanced_show_messages_optional_column_sql($columns, 'smallmessage', "''"),
    ];

    $sql = "SELECT " . implode(",\n                       ", $selectfields) . "
              FROM {" . $tablename . "} m
              JOIN {message_conversation_members} me
                ON me.conversationid = m.conversationid
               AND me.userid = :userid
             WHERE (m.useridfrom = :useridfrom OR m.useridfrom $targetfrominsql)
               AND EXISTS (
                    SELECT 1
                      FROM {message_conversation_members} mt
                     WHERE mt.conversationid = m.conversationid
                       AND mt.userid $targetexistsinsql
                )
          ORDER BY m.timecreated DESC, m.id DESC";
    $rows = $DB->get_records_sql($sql, $params);

    if (empty($rows)) {
        return [];
    }

    $conversationids = [];
    $userids = [];
    foreach ($rows as $row) {
        $conversationid = (int)$row->conversationid;
        if ($conversationid > 0) {
            $conversationids[$conversationid] = $conversationid;
        }
        $fromuserid = (int)$row->useridfrom;
        if ($fromuserid > 0) {
            $userids[$fromuserid] = $fromuserid;
        }
    }

    $membersbyconversation = userstatsadvanced_show_messages_get_conversation_members($conversationids, $userids);
    $users = userstatsadvanced_show_messages_get_users_map($userids);

    $records = [];
    foreach ($rows as $row) {
        $recipientids = [];
        foreach ($membersbyconversation[(int)$row->conversationid] ?? [] as $memberid) {
            if ($memberid !== (int)$row->useridfrom) {
                $recipientids[$memberid] = $memberid;
            }
        }

        $records[] = [
            'fromname' => userstatsadvanced_show_messages_get_user_name($users, (int)$row->useridfrom),
            'toname' => userstatsadvanced_show_messages_join_user_names($users, array_values($recipientids)),
            'subject' => userstatsadvanced_show_messages_resolve_subject(
                (string)$row->subject,
                (string)$row->smallmessage,
                (string)$row->fullmessage
            ),
            'messagehtml' => userstatsadvanced_show_messages_render_direct_message_html($row, $context),
            'createdhtml' => userstatsadvanced_show_messages_render_created_html((int)$row->timecreated),
            'createdts' => (int)$row->timecreated,
        ];
    }

    return $records;
}

/**
 * Returns direct message rows from the legacy message table.
 *
 * @param string $tablename
 * @param int $userid
 * @param array $targetids
 * @param context $context
 * @return array
 */
function userstatsadvanced_show_messages_get_legacy_records(
    string $tablename,
    int $userid,
    array $targetids,
    context $context
): array {
    global $DB;

    $columns = userstatsadvanced_show_messages_get_table_columns($tablename);
    if (
        empty($columns['id']) ||
        empty($columns['useridfrom']) ||
        empty($columns['useridto']) ||
        empty($columns['timecreated'])
    ) {
        return [];
    }

    [$targettoinsql, $targettoparams] = $DB->get_in_or_equal($targetids, SQL_PARAMS_NAMED, 'tgto');
    [$targetfrominsql, $targetfromparams] = $DB->get_in_or_equal($targetids, SQL_PARAMS_NAMED, 'tgfrom');
    $params = array_merge(
        ['useridfrom' => $userid, 'useridto' => $userid],
        $targettoparams,
        $targetfromparams
    );

    $selectfields = [
        'm.id',
        'm.useridfrom',
        'm.useridto',
        'm.timecreated',
        userstatsadvanced_show_messages_optional_column_sql($columns, 'subject', "''"),
        userstatsadvanced_show_messages_optional_column_sql($columns, 'fullmessage', "''"),
        userstatsadvanced_show_messages_optional_column_sql($columns, 'fullmessageformat', '0'),
        userstatsadvanced_show_messages_optional_column_sql($columns, 'fullmessagehtml', "''"),
        userstatsadvanced_show_messages_optional_column_sql($columns, 'smallmessage', "''"),
    ];

    $sql = "SELECT " . implode(",\n                       ", $selectfields) . "
              FROM {" . $tablename . "} m
             WHERE ((m.useridfrom = :useridfrom AND m.useridto $targettoinsql)
                OR  (m.useridto = :useridto AND m.useridfrom $targetfrominsql))
          ORDER BY m.timecreated DESC, m.id DESC";
    $rows = $DB->get_records_sql($sql, $params);

    if (empty($rows)) {
        return [];
    }

    $userids = [];
    foreach ($rows as $row) {
        $fromuserid = (int)$row->useridfrom;
        $touserid = (int)$row->useridto;
        if ($fromuserid > 0) {
            $userids[$fromuserid] = $fromuserid;
        }
        if ($touserid > 0) {
            $userids[$touserid] = $touserid;
        }
    }

    $users = userstatsadvanced_show_messages_get_users_map($userids);

    $records = [];
    foreach ($rows as $row) {
        $records[] = [
            'fromname' => userstatsadvanced_show_messages_get_user_name($users, (int)$row->useridfrom),
            'toname' => userstatsadvanced_show_messages_get_user_name($users, (int)$row->useridto),
            'subject' => userstatsadvanced_show_messages_resolve_subject(
                (string)$row->subject,
                (string)$row->smallmessage,
                (string)$row->fullmessage
            ),
            'messagehtml' => userstatsadvanced_show_messages_render_direct_message_html($row, $context),
            'createdhtml' => userstatsadvanced_show_messages_render_created_html((int)$row->timecreated),
            'createdts' => (int)$row->timecreated,
        ];
    }

    return $records;
}

/**
 * Returns forum message rows normalized for rendering.
 *
 * @param int $userid
 * @param int $courseid
 * @param context $context
 * @return array
 */
function userstatsadvanced_show_messages_get_forum_records(int $userid, int $courseid, context $context): array {
    global $DB;

    $columns = userstatsadvanced_show_messages_get_table_columns('forum_posts');
    $selectfields = [
        'fp.id',
        'fp.userid',
        'fp.created AS timecreated',
        'f.name AS forumname',
        'fd.name AS discussionname',
        userstatsadvanced_show_messages_optional_column_sql($columns, 'subject', "''", 'fp'),
        userstatsadvanced_show_messages_optional_column_sql($columns, 'message', "''", 'fp'),
        userstatsadvanced_show_messages_optional_column_sql($columns, 'messageformat', '0', 'fp'),
        userstatsadvanced_show_messages_optional_column_sql($columns, 'deleted', '0', 'fp'),
    ];

    $sql = "SELECT " . implode(",\n                       ", $selectfields) . "
              FROM {forum_posts} fp
              JOIN {forum_discussions} fd ON fd.id = fp.discussion
              JOIN {forum} f ON f.id = fd.forum
             WHERE fp.userid = :userid
               AND f.course = :courseid
          ORDER BY fp.created DESC, fp.id DESC";
    $rows = $DB->get_records_sql($sql, ['userid' => $userid, 'courseid' => $courseid]);

    if (empty($rows)) {
        return [];
    }

    $users = userstatsadvanced_show_messages_get_users_map([$userid]);
    $records = [];
    foreach ($rows as $row) {
        $subject = trim((string)$row->subject);
        if ($subject === '') {
            $subject = trim((string)$row->discussionname);
        }
        if ($subject === '') {
            $subject = get_string('userstatsadvanced_no_subject', 'block_configurable_reports');
        }

        $records[] = [
            'fromname' => userstatsadvanced_show_messages_get_user_name($users, (int)$row->userid),
            'forumname' => format_string((string)$row->forumname),
            'subject' => $subject,
            'messagehtml' => userstatsadvanced_show_messages_render_forum_message_html($row, $context),
            'createdhtml' => userstatsadvanced_show_messages_render_created_html((int)$row->timecreated),
            'createdts' => (int)$row->timecreated,
        ];
    }

    return $records;
}

/**
 * Returns staff/admin forum posts as fallback detail rows for "correos".
 *
 * @param int $userid
 * @param int $courseid
 * @param context $context
 * @return array
 */
function userstatsadvanced_show_messages_get_staff_forum_mail_records(
    int $userid,
    int $courseid,
    context $context
): array {
    global $DB;

    $targetids = userstatsadvanced_show_messages_get_target_ids_for_correos($courseid, $userid);
    if (empty($targetids)) {
        return [];
    }

    [$insql, $inparams] = $DB->get_in_or_equal($targetids, SQL_PARAMS_NAMED, 'mailstaff');
    $columns = userstatsadvanced_show_messages_get_table_columns('forum_posts');
    $selectfields = [
        'fp.id',
        'fp.userid AS useridfrom',
        'fp.created AS timecreated',
        userstatsadvanced_show_messages_optional_column_sql($columns, 'subject', "''", 'fp'),
        userstatsadvanced_show_messages_optional_column_sql($columns, 'message', "''", 'fp'),
        userstatsadvanced_show_messages_optional_column_sql($columns, 'messageformat', '0', 'fp'),
        userstatsadvanced_show_messages_optional_column_sql($columns, 'deleted', '0', 'fp'),
    ];

    $params = array_merge([
        'courseid' => $courseid,
        'userid' => $userid,
    ], $inparams);

    $sql = "SELECT " . implode(",\n                       ", $selectfields) . "
              FROM {forum_posts} fp
              JOIN {forum_discussions} fd ON fd.id = fp.discussion
              JOIN {forum} f ON f.id = fd.forum
             WHERE f.course = :courseid
               AND fp.userid $insql
               AND fp.userid <> :userid
          ORDER BY fp.created DESC, fp.id DESC";
    $rows = $DB->get_records_sql($sql, $params);
    if (empty($rows)) {
        return [];
    }

    $userids = [$userid => $userid];
    foreach ($rows as $row) {
        $fromuserid = (int)$row->useridfrom;
        if ($fromuserid > 0) {
            $userids[$fromuserid] = $fromuserid;
        }
    }
    $users = userstatsadvanced_show_messages_get_users_map($userids);

    $records = [];
    foreach ($rows as $row) {
        $records[] = [
            'fromname' => userstatsadvanced_show_messages_get_user_name($users, (int)$row->useridfrom),
            'toname' => userstatsadvanced_show_messages_get_user_name($users, $userid),
            'subject' => userstatsadvanced_show_messages_resolve_subject(
                (string)$row->subject,
                '',
                (string)$row->message
            ),
            'messagehtml' => userstatsadvanced_show_messages_render_forum_message_html($row, $context),
            'createdhtml' => userstatsadvanced_show_messages_render_created_html((int)$row->timecreated),
            'createdts' => (int)$row->timecreated,
        ];
    }

    return $records;
}

/**
 * Returns forum records authored by the selected user in discussions with students.
 * Falls back to all authored forum records when student mapping is unavailable.
 *
 * @param int $userid
 * @param int $courseid
 * @param context $context
 * @return array
 */
function userstatsadvanced_show_messages_get_forum_records_with_students(
    int $userid,
    int $courseid,
    context $context
): array {
    global $DB;

    if ($courseid <= 0 || $userid <= 0) {
        return [];
    }

    $studentids = userstatsadvanced_show_messages_get_course_user_ids_by_archetypes($courseid, ['student'], $userid);
    if (empty($studentids)) {
        return userstatsadvanced_show_messages_get_forum_records($userid, $courseid, $context);
    }

    [$insql, $inparams] = $DB->get_in_or_equal($studentids, SQL_PARAMS_NAMED, 'studetail');
    $columns = userstatsadvanced_show_messages_get_table_columns('forum_posts');
    $selectfields = [
        'fp.id',
        'fp.userid AS useridfrom',
        'fp.created AS timecreated',
        userstatsadvanced_show_messages_optional_column_sql($columns, 'subject', "''", 'fp'),
        userstatsadvanced_show_messages_optional_column_sql($columns, 'message', "''", 'fp'),
        userstatsadvanced_show_messages_optional_column_sql($columns, 'messageformat', '0', 'fp'),
        userstatsadvanced_show_messages_optional_column_sql($columns, 'deleted', '0', 'fp'),
    ];
    $params = array_merge([
        'courseid' => $courseid,
        'userid' => $userid,
    ], $inparams);

    $sql = "SELECT " . implode(",\n                       ", $selectfields) . "
              FROM {forum_posts} fp
              JOIN {forum_discussions} fd ON fd.id = fp.discussion
              JOIN {forum} f ON f.id = fd.forum
             WHERE f.course = :courseid
               AND fp.userid = :userid
               AND EXISTS (
                    SELECT 1
                      FROM {forum_posts} fps
                     WHERE fps.discussion = fp.discussion
                       AND fps.userid $insql
             )
          ORDER BY fp.created DESC, fp.id DESC";
    $rows = $DB->get_records_sql($sql, $params);
    if (empty($rows)) {
        return [];
    }

    $users = userstatsadvanced_show_messages_get_users_map([$userid]);
    $records = [];
    foreach ($rows as $row) {
        $records[] = [
            'fromname' => userstatsadvanced_show_messages_get_user_name($users, (int)$row->useridfrom),
            'toname' => get_string('userstatsadvanced_students_group', 'block_configurable_reports'),
            'subject' => userstatsadvanced_show_messages_resolve_subject(
                (string)$row->subject,
                '',
                (string)$row->message
            ),
            'messagehtml' => userstatsadvanced_show_messages_render_forum_message_html($row, $context),
            'createdhtml' => userstatsadvanced_show_messages_render_created_html((int)$row->timecreated),
            'createdts' => (int)$row->timecreated,
        ];
    }

    return $records;
}

/**
 * Returns users assigned to selected role archetypes in a course.
 *
 * @param int $courseid
 * @param array $archetypes
 * @param int $excludeuserid
 * @return array
 */
function userstatsadvanced_show_messages_get_course_user_ids_by_archetypes(
    int $courseid,
    array $archetypes,
    int $excludeuserid = 0
): array {
    global $DB;

    if (empty($archetypes)) {
        return [];
    }

    $contextids = userstatsadvanced_show_messages_get_course_related_context_ids($courseid);
    if (empty($contextids)) {
        return [];
    }

    [$insql, $inparams] = $DB->get_in_or_equal($archetypes, SQL_PARAMS_NAMED, 'arc');
    [$ctxinsql, $ctxparams] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'ctxid');
    $params = array_merge($inparams, $ctxparams);

    $sql = "SELECT DISTINCT ra.userid
              FROM {role_assignments} ra
              JOIN {context} ctx ON ctx.id = ra.contextid
              JOIN {role} r ON r.id = ra.roleid
             WHERE ctx.id $ctxinsql
               AND r.archetype $insql";
    $rows = $DB->get_records_sql($sql, $params);

    $userids = [];
    foreach ($rows as $row) {
        $candidateid = (int)$row->userid;
        if ($candidateid > 0 && $candidateid !== $excludeuserid) {
            $userids[$candidateid] = $candidateid;
        }
    }

    return array_values($userids);
}

/**
 * Returns context IDs relevant for a course role lookup (course + parent contexts).
 *
 * @param int $courseid
 * @return array
 */
function userstatsadvanced_show_messages_get_course_related_context_ids(int $courseid): array {
    global $DB;

    if ($courseid <= 0) {
        return [];
    }

    $coursecontext = $DB->get_record('context', [
        'contextlevel' => CONTEXT_COURSE,
        'instanceid' => $courseid,
    ], 'id,path', IGNORE_MISSING);
    if (!$coursecontext) {
        return [];
    }

    $contextids = [];
    if (!empty($coursecontext->path)) {
        foreach (explode('/', trim((string)$coursecontext->path, '/')) as $chunk) {
            $id = (int)$chunk;
            if ($id > 0) {
                $contextids[$id] = $id;
            }
        }
    }

    $coursecontextid = (int)$coursecontext->id;
    if ($coursecontextid > 0) {
        $contextids[$coursecontextid] = $coursecontextid;
    }

    return array_values($contextids);
}

/**
 * Returns non-student role user IDs assigned directly in the course context.
 *
 * @param int $courseid
 * @param int $excludeuserid
 * @return array
 */
function userstatsadvanced_show_messages_get_course_nonstudent_user_ids(
    int $courseid,
    int $excludeuserid = 0
): array {
    global $DB;

    $contextids = userstatsadvanced_show_messages_get_course_related_context_ids($courseid);
    if (empty($contextids)) {
        return [];
    }

    [$ctxinsql, $ctxparams] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'ctxnonstd');
    $sql = "SELECT DISTINCT ra.userid
              FROM {role_assignments} ra
              JOIN {context} ctx ON ctx.id = ra.contextid
              JOIN {role} r ON r.id = ra.roleid
             WHERE ctx.id $ctxinsql
               AND (r.archetype IS NULL OR r.archetype = '' OR r.archetype <> :studentarchetype)";
    $rows = $DB->get_records_sql($sql, array_merge($ctxparams, [
        'studentarchetype' => 'student',
    ]));
    if (empty($rows)) {
        return [];
    }

    $userids = [];
    foreach ($rows as $row) {
        $candidateid = (int)$row->userid;
        if ($candidateid > 0 && $candidateid !== $excludeuserid) {
            $userids[$candidateid] = $candidateid;
        }
    }

    return array_values($userids);
}

/**
 * Returns staff users enrolled in the course with editing capability.
 *
 * @param int $courseid
 * @param int $excludeuserid
 * @return array
 */
function userstatsadvanced_show_messages_get_course_staff_user_ids_by_capability(
    int $courseid,
    int $excludeuserid = 0
): array {
    if ($courseid <= 0) {
        return [];
    }

    try {
        $context = context_course::instance($courseid);
    } catch (Throwable $t) {
        return [];
    }
    if (!$context) {
        return [];
    }

    $users = get_enrolled_users($context, 'moodle/course:update', 0, 'u.id');
    if (empty($users)) {
        return [];
    }

    $userids = [];
    foreach ($users as $user) {
        $id = (int)($user->id ?? 0);
        if ($id > 0 && $id !== $excludeuserid) {
            $userids[$id] = $id;
        }
    }

    return array_values($userids);
}

/**
 * Returns site admin user IDs.
 *
 * @param int $excludeuserid
 * @return array
 */
function userstatsadvanced_show_messages_get_site_admin_user_ids(int $excludeuserid = 0): array {
    $userids = [];
    if (!function_exists('get_admins')) {
        return [];
    }

    $admins = get_admins();
    if (empty($admins)) {
        return [];
    }

    foreach ($admins as $admin) {
        $id = (int)($admin->id ?? 0);
        if ($id > 0 && $id !== $excludeuserid) {
            $userids[$id] = $id;
        }
    }

    return array_values($userids);
}

/**
 * Returns a normalized map of table columns.
 *
 * @param string $tablename
 * @return array
 */
function userstatsadvanced_show_messages_get_table_columns(string $tablename): array {
    global $DB;

    try {
        $columns = $DB->get_columns($tablename);
        return array_change_key_case($columns, CASE_LOWER);
    } catch (Throwable $t) {
        return [];
    }
}

/**
 * Returns an aliased SQL fragment for an optional column.
 *
 * @param array $columns
 * @param string $columnname
 * @param string $defaultsql
 * @param string $tablealias
 * @return string
 */
function userstatsadvanced_show_messages_optional_column_sql(
    array $columns,
    string $columnname,
    string $defaultsql,
    string $tablealias = 'm'
): string {
    if (!empty($columns[$columnname])) {
        return $tablealias . '.' . $columnname . ' AS ' . $columnname;
    }

    return $defaultsql . ' AS ' . $columnname;
}

/**
 * Returns conversation members indexed by conversation and accumulates user IDs.
 *
 * @param array $conversationids
 * @param array $userids
 * @return array
 */
function userstatsadvanced_show_messages_get_conversation_members(array $conversationids, array &$userids): array {
    global $DB;

    if (empty($conversationids)) {
        return [];
    }

    [$insql, $params] = $DB->get_in_or_equal(array_values($conversationids), SQL_PARAMS_NAMED, 'conv');
    $sql = "SELECT mcm.id, mcm.conversationid, mcm.userid
              FROM {message_conversation_members} mcm
             WHERE mcm.conversationid $insql
          ORDER BY mcm.conversationid ASC, mcm.id ASC";
    $rows = $DB->get_records_sql($sql, $params);

    $membersbyconversation = [];
    foreach ($rows as $row) {
        $conversationid = (int)$row->conversationid;
        $memberid = (int)$row->userid;
        if ($conversationid <= 0 || $memberid <= 0) {
            continue;
        }
        $membersbyconversation[$conversationid][] = $memberid;
        $userids[$memberid] = $memberid;
    }

    return $membersbyconversation;
}

/**
 * Returns users keyed by ID.
 *
 * @param array $userids
 * @return array
 */
function userstatsadvanced_show_messages_get_users_map(array $userids): array {
    global $DB;

    $normalizeduserids = [];
    foreach ($userids as $userid) {
        $userid = (int)$userid;
        if ($userid > 0) {
            $normalizeduserids[$userid] = $userid;
        }
    }

    if (empty($normalizeduserids)) {
        return [];
    }

    [$insql, $params] = $DB->get_in_or_equal(array_values($normalizeduserids), SQL_PARAMS_NAMED, 'usr');
    $sql = "SELECT id,
                   firstname,
                   lastname,
                   middlename,
                   firstnamephonetic,
                   lastnamephonetic,
                   alternatename,
                   deleted
              FROM {user}
             WHERE id $insql";

    return $DB->get_records_sql($sql, $params);
}

/**
 * Returns a display name for a user.
 *
 * @param array $users
 * @param int $userid
 * @return string
 */
function userstatsadvanced_show_messages_get_user_name(array $users, int $userid): string {
    if ($userid > 0 && !empty($users[$userid])) {
        return fullname($users[$userid]);
    }

    if ($userid > 0) {
        return get_string('user') . ' #' . $userid;
    }

    return '-';
}

/**
 * Returns a comma-separated list of user names.
 *
 * @param array $users
 * @param array $userids
 * @return string
 */
function userstatsadvanced_show_messages_join_user_names(array $users, array $userids): string {
    $names = [];
    foreach ($userids as $userid) {
        $userid = (int)$userid;
        if ($userid <= 0) {
            continue;
        }
        $names[$userid] = userstatsadvanced_show_messages_get_user_name($users, $userid);
    }

    if (empty($names)) {
        return '-';
    }

    return implode(', ', array_values($names));
}

/**
 * Returns a subject-like preview for a message.
 *
 * @param string $subject
 * @param string $smallmessage
 * @param string $fullmessage
 * @return string
 */
function userstatsadvanced_show_messages_resolve_subject(
    string $subject,
    string $smallmessage,
    string $fullmessage
): string {
    foreach ([$subject, $smallmessage, $fullmessage] as $candidate) {
        $candidate = trim(preg_replace('/\s+/', ' ', strip_tags($candidate)));
        if ($candidate === '') {
            continue;
        }
        if (core_text::strlen($candidate) > 120) {
            return core_text::substr($candidate, 0, 117) . '...';
        }
        return $candidate;
    }

    return get_string('userstatsadvanced_no_subject', 'block_configurable_reports');
}

/**
 * Renders direct message body HTML.
 *
 * @param object $row
 * @param context $context
 * @return string
 */
function userstatsadvanced_show_messages_render_direct_message_html(object $row, context $context): string {
    $content = trim((string)$row->fullmessagehtml);
    if ($content !== '') {
        $html = format_text($content, FORMAT_HTML, ['context' => $context, 'para' => false]);
    } else if (trim((string)$row->fullmessage) !== '') {
        $format = isset($row->fullmessageformat) ? (int)$row->fullmessageformat : FORMAT_PLAIN;
        $html = format_text((string)$row->fullmessage, $format, ['context' => $context, 'para' => false]);
    } else if (trim((string)$row->smallmessage) !== '') {
        $html = format_text((string)$row->smallmessage, FORMAT_PLAIN, ['context' => $context, 'para' => false]);
    } else {
        $html = s(get_string('userstatsadvanced_no_subject', 'block_configurable_reports'));
    }

    return html_writer::div($html, '', [
        'style' => 'min-width:280px;max-width:620px;white-space:normal;word-break:break-word;',
    ]);
}

/**
 * Renders forum message body HTML.
 *
 * @param object $row
 * @param context $context
 * @return string
 */
function userstatsadvanced_show_messages_render_forum_message_html(object $row, context $context): string {
    if (!empty($row->deleted)) {
        $html = s(get_string('userstatsadvanced_deleted_message', 'block_configurable_reports'));
    } else if (trim((string)$row->message) !== '') {
        $format = isset($row->messageformat) ? (int)$row->messageformat : FORMAT_HTML;
        $html = format_text((string)$row->message, $format, ['context' => $context, 'para' => false]);
    } else {
        $html = s(get_string('userstatsadvanced_no_subject', 'block_configurable_reports'));
    }

    return html_writer::div($html, '', [
        'style' => 'min-width:280px;max-width:620px;white-space:normal;word-break:break-word;',
    ]);
}

/**
 * Renders a two-line created date/time cell.
 *
 * @param int $timestamp
 * @return string
 */
function userstatsadvanced_show_messages_render_created_html(int $timestamp): string {
    if ($timestamp <= 0) {
        return '-';
    }

    return s(userdate($timestamp, '%d/%m/%Y')) .
        html_writer::empty_tag('br') .
        s(userdate($timestamp, '%H:%M'));
}

/**
 * Merges message arrays and sorts records by timestamp (newest first).
 *
 * @param array $records
 * @param array $extrarecords
 * @return array
 */
function userstatsadvanced_show_messages_merge_records_by_date(array $records, array $extrarecords): array {
    $allrecords = array_merge($records, $extrarecords);
    if (empty($allrecords)) {
        return [];
    }

    usort($allrecords, static function(array $a, array $b): int {
        $ats = (int)($a['createdts'] ?? 0);
        $bts = (int)($b['createdts'] ?? 0);
        if ($ats === $bts) {
            return 0;
        }

        return ($ats > $bts) ? -1 : 1;
    });

    return $allrecords;
}
