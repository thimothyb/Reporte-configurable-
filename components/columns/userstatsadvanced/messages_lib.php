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
 * Shared message-query library for userstatsadvanced.
 * Included by both show_messages.php (web view) and export_global_pdf.php (PDF export).
 *
 * @package    block_configurable_reports
 */

defined('MOODLE_INTERNAL') || die();

// ─── Stat-type helpers ────────────────────────────────────────────────────────

function userstatsadvanced_show_messages_is_forum_stat(string $stattype): bool {
    return in_array($stattype, ['interacciones_foros', 'mensajes_foro', 'foros_publicados'], true);
}

function userstatsadvanced_show_messages_get_target_archetypes(string $stattype): array {
    if ($stattype === 'mensajes_alumnos') {
        return ['student'];
    }
    return ['editingteacher', 'teacher'];
}

// ─── Main record fetchers ─────────────────────────────────────────────────────

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
                    $source['table'], $userid, $targetids, $context
                );
            } else {
                $records = userstatsadvanced_show_messages_get_legacy_records(
                    $source['table'], $userid, $targetids, $context
                );
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

function userstatsadvanced_show_messages_get_target_ids_for_correos(int $courseid, int $excludeuserid = 0): array {
    $targetids = userstatsadvanced_show_messages_get_course_user_ids_by_archetypes(
        $courseid, ['editingteacher', 'teacher', 'manager'], $excludeuserid
    );
    $targetids = array_merge(
        $targetids,
        userstatsadvanced_show_messages_get_course_nonstudent_user_ids($courseid, $excludeuserid)
    );
    $targetids = array_merge($targetids, userstatsadvanced_show_messages_get_course_staff_user_ids_by_capability(
        $courseid, $excludeuserid
    ));

    if (empty($targetids)) {
        return [];
    }

    $targetids = array_map('intval', $targetids);
    $targetids = array_filter($targetids, static function(int $id): bool { return $id > 0; });
    return array_values(array_unique($targetids));
}

// ─── Conversation-based message tables ───────────────────────────────────────

function userstatsadvanced_show_messages_get_conversation_records(
    string $tablename, int $userid, array $targetids, context $context
): array {
    global $DB;

    $columns = userstatsadvanced_show_messages_get_table_columns($tablename);
    if (
        empty($columns['id']) || empty($columns['useridfrom']) ||
        empty($columns['conversationid']) || empty($columns['timecreated'])
    ) {
        return [];
    }

    [$targetexistsinsql, $targetexistsparams] = $DB->get_in_or_equal($targetids, SQL_PARAMS_NAMED, 'tgexists');
    [$targetfrominsql,  $targetfromparams]  = $DB->get_in_or_equal($targetids, SQL_PARAMS_NAMED, 'tgfrom');
    $params = array_merge(
        ['userid' => $userid, 'useridfrom' => $userid],
        $targetexistsparams,
        $targetfromparams
    );

    $selectfields = [
        'm.id', 'm.useridfrom', 'm.conversationid', 'm.timecreated',
        userstatsadvanced_show_messages_optional_column_sql($columns, 'subject',           "''"),
        userstatsadvanced_show_messages_optional_column_sql($columns, 'fullmessage',       "''"),
        userstatsadvanced_show_messages_optional_column_sql($columns, 'fullmessageformat', '0'),
        userstatsadvanced_show_messages_optional_column_sql($columns, 'fullmessagehtml',   "''"),
        userstatsadvanced_show_messages_optional_column_sql($columns, 'smallmessage',      "''"),
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
        $cid = (int)$row->conversationid;
        if ($cid > 0)   $conversationids[$cid] = $cid;
        $fid = (int)$row->useridfrom;
        if ($fid > 0)   $userids[$fid] = $fid;
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
            'fromname'   => userstatsadvanced_show_messages_get_user_name($users, (int)$row->useridfrom),
            'toname'     => userstatsadvanced_show_messages_join_user_names($users, array_values($recipientids)),
            'subject'    => userstatsadvanced_show_messages_resolve_subject(
                                (string)$row->subject, (string)$row->smallmessage, (string)$row->fullmessage),
            'messagehtml'  => userstatsadvanced_show_messages_render_direct_message_html($row, $context),
            'createdhtml'  => userstatsadvanced_show_messages_render_created_html((int)$row->timecreated),
            'createdts'    => (int)$row->timecreated,
        ];
    }
    return $records;
}

// ─── Legacy message table ─────────────────────────────────────────────────────

function userstatsadvanced_show_messages_get_legacy_records(
    string $tablename, int $userid, array $targetids, context $context
): array {
    global $DB;

    $columns = userstatsadvanced_show_messages_get_table_columns($tablename);
    if (
        empty($columns['id']) || empty($columns['useridfrom']) ||
        empty($columns['useridto']) || empty($columns['timecreated'])
    ) {
        return [];
    }

    [$targettoinsql,   $targettoparams]   = $DB->get_in_or_equal($targetids, SQL_PARAMS_NAMED, 'tgto');
    [$targetfrominsql, $targetfromparams] = $DB->get_in_or_equal($targetids, SQL_PARAMS_NAMED, 'tgfrom');
    $params = array_merge(
        ['useridfrom' => $userid, 'useridto' => $userid],
        $targettoparams,
        $targetfromparams
    );

    $selectfields = [
        'm.id', 'm.useridfrom', 'm.useridto', 'm.timecreated',
        userstatsadvanced_show_messages_optional_column_sql($columns, 'subject',           "''"),
        userstatsadvanced_show_messages_optional_column_sql($columns, 'fullmessage',       "''"),
        userstatsadvanced_show_messages_optional_column_sql($columns, 'fullmessageformat', '0'),
        userstatsadvanced_show_messages_optional_column_sql($columns, 'fullmessagehtml',   "''"),
        userstatsadvanced_show_messages_optional_column_sql($columns, 'smallmessage',      "''"),
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
        $fid = (int)$row->useridfrom;
        $tid = (int)$row->useridto;
        if ($fid > 0) $userids[$fid] = $fid;
        if ($tid > 0) $userids[$tid] = $tid;
    }
    $users = userstatsadvanced_show_messages_get_users_map($userids);

    $records = [];
    foreach ($rows as $row) {
        $records[] = [
            'fromname'    => userstatsadvanced_show_messages_get_user_name($users, (int)$row->useridfrom),
            'toname'      => userstatsadvanced_show_messages_get_user_name($users, (int)$row->useridto),
            'subject'     => userstatsadvanced_show_messages_resolve_subject(
                                 (string)$row->subject, (string)$row->smallmessage, (string)$row->fullmessage),
            'messagehtml'  => userstatsadvanced_show_messages_render_direct_message_html($row, $context),
            'createdhtml'  => userstatsadvanced_show_messages_render_created_html((int)$row->timecreated),
            'createdts'    => (int)$row->timecreated,
        ];
    }
    return $records;
}

// ─── Forum posts ──────────────────────────────────────────────────────────────

function userstatsadvanced_show_messages_get_forum_records(int $userid, int $courseid, context $context): array {
    global $DB;

    $columns    = userstatsadvanced_show_messages_get_table_columns('forum_posts');
    $selectfields = [
        'fp.id', 'fp.userid', 'fp.created AS timecreated',
        'f.name AS forumname', 'fd.name AS discussionname',
        userstatsadvanced_show_messages_optional_column_sql($columns, 'subject',       "''",  'fp'),
        userstatsadvanced_show_messages_optional_column_sql($columns, 'message',       "''",  'fp'),
        userstatsadvanced_show_messages_optional_column_sql($columns, 'messageformat', '0',   'fp'),
        userstatsadvanced_show_messages_optional_column_sql($columns, 'deleted',       '0',   'fp'),
    ];

    $sql = "SELECT " . implode(",\n                       ", $selectfields) . "
              FROM {forum_posts} fp
              JOIN {forum_discussions} fd ON fd.id = fp.discussion
              JOIN {forum} f ON f.id = fd.forum
             WHERE fp.userid = :userid AND f.course = :courseid
          ORDER BY fp.created DESC, fp.id DESC";
    $rows = $DB->get_records_sql($sql, ['userid' => $userid, 'courseid' => $courseid]);
    if (empty($rows)) {
        return [];
    }

    $users   = userstatsadvanced_show_messages_get_users_map([$userid]);
    $records = [];
    foreach ($rows as $row) {
        $subject = trim((string)$row->subject);
        if ($subject === '') $subject = trim((string)$row->discussionname);
        if ($subject === '') $subject = get_string('userstatsadvanced_no_subject', 'block_configurable_reports');

        $records[] = [
            'fromname'    => userstatsadvanced_show_messages_get_user_name($users, (int)$row->userid),
            'forumname'   => format_string((string)$row->forumname),
            'subject'     => $subject,
            'messagehtml' => userstatsadvanced_show_messages_render_forum_message_html($row, $context),
            'createdhtml' => userstatsadvanced_show_messages_render_created_html((int)$row->timecreated),
            'createdts'   => (int)$row->timecreated,
        ];
    }
    return $records;
}

function userstatsadvanced_show_messages_get_staff_forum_mail_records(
    int $userid, int $courseid, context $context
): array {
    global $DB;

    $targetids = userstatsadvanced_show_messages_get_target_ids_for_correos($courseid, $userid);
    if (empty($targetids)) return [];

    [$insql, $inparams] = $DB->get_in_or_equal($targetids, SQL_PARAMS_NAMED, 'mailstaff');
    $columns    = userstatsadvanced_show_messages_get_table_columns('forum_posts');
    $selectfields = [
        'fp.id', 'fp.userid AS useridfrom', 'fp.created AS timecreated',
        userstatsadvanced_show_messages_optional_column_sql($columns, 'subject',       "''", 'fp'),
        userstatsadvanced_show_messages_optional_column_sql($columns, 'message',       "''", 'fp'),
        userstatsadvanced_show_messages_optional_column_sql($columns, 'messageformat', '0',  'fp'),
        userstatsadvanced_show_messages_optional_column_sql($columns, 'deleted',       '0',  'fp'),
    ];
    $params = array_merge(['courseid' => $courseid, 'userid' => $userid], $inparams);

    $sql = "SELECT " . implode(",\n                       ", $selectfields) . "
              FROM {forum_posts} fp
              JOIN {forum_discussions} fd ON fd.id = fp.discussion
              JOIN {forum} f ON f.id = fd.forum
             WHERE f.course = :courseid
               AND fp.userid $insql
               AND fp.userid <> :userid
          ORDER BY fp.created DESC, fp.id DESC";
    $rows = $DB->get_records_sql($sql, $params);
    if (empty($rows)) return [];

    $userids = [$userid => $userid];
    foreach ($rows as $row) {
        $fid = (int)$row->useridfrom;
        if ($fid > 0) $userids[$fid] = $fid;
    }
    $users   = userstatsadvanced_show_messages_get_users_map($userids);
    $records = [];
    foreach ($rows as $row) {
        $records[] = [
            'fromname'    => userstatsadvanced_show_messages_get_user_name($users, (int)$row->useridfrom),
            'toname'      => userstatsadvanced_show_messages_get_user_name($users, $userid),
            'subject'     => userstatsadvanced_show_messages_resolve_subject((string)$row->subject, '', (string)$row->message),
            'messagehtml' => userstatsadvanced_show_messages_render_forum_message_html($row, $context),
            'createdhtml' => userstatsadvanced_show_messages_render_created_html((int)$row->timecreated),
            'createdts'   => (int)$row->timecreated,
        ];
    }
    return $records;
}

function userstatsadvanced_show_messages_get_forum_records_with_students(
    int $userid, int $courseid, context $context
): array {
    global $DB;

    if ($courseid <= 0 || $userid <= 0) return [];

    $studentids = userstatsadvanced_show_messages_get_course_user_ids_by_archetypes($courseid, ['student'], $userid);
    if (empty($studentids)) {
        return userstatsadvanced_show_messages_get_forum_records($userid, $courseid, $context);
    }

    [$insql, $inparams] = $DB->get_in_or_equal($studentids, SQL_PARAMS_NAMED, 'studetail');
    $columns    = userstatsadvanced_show_messages_get_table_columns('forum_posts');
    $selectfields = [
        'fp.id', 'fp.userid AS useridfrom', 'fp.created AS timecreated',
        userstatsadvanced_show_messages_optional_column_sql($columns, 'subject',       "''", 'fp'),
        userstatsadvanced_show_messages_optional_column_sql($columns, 'message',       "''", 'fp'),
        userstatsadvanced_show_messages_optional_column_sql($columns, 'messageformat', '0',  'fp'),
        userstatsadvanced_show_messages_optional_column_sql($columns, 'deleted',       '0',  'fp'),
    ];
    $params = array_merge(['courseid' => $courseid, 'userid' => $userid], $inparams);

    $sql = "SELECT " . implode(",\n                       ", $selectfields) . "
              FROM {forum_posts} fp
              JOIN {forum_discussions} fd ON fd.id = fp.discussion
              JOIN {forum} f ON f.id = fd.forum
             WHERE f.course = :courseid AND fp.userid = :userid
               AND EXISTS (
                    SELECT 1 FROM {forum_posts} fps
                     WHERE fps.discussion = fp.discussion AND fps.userid $insql
               )
          ORDER BY fp.created DESC, fp.id DESC";
    $rows = $DB->get_records_sql($sql, $params);
    if (empty($rows)) return [];

    $users   = userstatsadvanced_show_messages_get_users_map([$userid]);
    $records = [];
    foreach ($rows as $row) {
        $records[] = [
            'fromname'    => userstatsadvanced_show_messages_get_user_name($users, (int)$row->useridfrom),
            'toname'      => get_string('userstatsadvanced_students_group', 'block_configurable_reports'),
            'subject'     => userstatsadvanced_show_messages_resolve_subject((string)$row->subject, '', (string)$row->message),
            'messagehtml' => userstatsadvanced_show_messages_render_forum_message_html($row, $context),
            'createdhtml' => userstatsadvanced_show_messages_render_created_html((int)$row->timecreated),
            'createdts'   => (int)$row->timecreated,
        ];
    }
    return $records;
}

// ─── Chat messages (Moodle Chat module) ──────────────────────────────────────

/**
 * Returns chat message records for a user in a course.
 * Each record: ['fromname', 'chatname', 'message', 'createdts']
 *
 * @param int $userid
 * @param int $courseid
 * @return array
 */
function userstatsadvanced_show_messages_get_chat_records(int $userid, int $courseid): array {
    global $DB;

    if (!$DB->get_manager()->table_exists('chat_messages')) {
        return [];
    }

    $sql = "SELECT cm.id, cm.userid, cm.message, cm.timestamp AS timecreated,
                   c.name AS chatname
              FROM {chat_messages} cm
              JOIN {chat} c ON c.id = cm.chatid
             WHERE cm.userid = :userid
               AND c.course = :courseid
               AND cm.issystem = 0
          ORDER BY cm.timestamp DESC, cm.id DESC";
    $rows = $DB->get_records_sql($sql, ['userid' => $userid, 'courseid' => $courseid]);
    if (empty($rows)) {
        return [];
    }

    $users   = userstatsadvanced_show_messages_get_users_map([$userid]);
    $records = [];
    foreach ($rows as $row) {
        $records[] = [
            'fromname'  => userstatsadvanced_show_messages_get_user_name($users, (int)$row->userid),
            'chatname'  => format_string((string)$row->chatname),
            'message'   => trim(strip_tags((string)$row->message)),
            'createdts' => (int)$row->timecreated,
        ];
    }
    return $records;
}

// ─── User / course helpers ────────────────────────────────────────────────────

function userstatsadvanced_show_messages_get_course_user_ids_by_archetypes(
    int $courseid, array $archetypes, int $excludeuserid = 0
): array {
    global $DB;
    if (empty($archetypes)) return [];

    $contextids = userstatsadvanced_show_messages_get_course_related_context_ids($courseid);
    if (empty($contextids)) return [];

    [$insql,    $inparams]    = $DB->get_in_or_equal($archetypes,  SQL_PARAMS_NAMED, 'arc');
    [$ctxinsql, $ctxparams]   = $DB->get_in_or_equal($contextids,  SQL_PARAMS_NAMED, 'ctxid');
    $params = array_merge($inparams, $ctxparams);

    $sql = "SELECT DISTINCT ra.userid
              FROM {role_assignments} ra
              JOIN {context} ctx ON ctx.id = ra.contextid
              JOIN {role} r ON r.id = ra.roleid
             WHERE ctx.id $ctxinsql AND r.archetype $insql";
    $rows = $DB->get_records_sql($sql, $params);

    $userids = [];
    foreach ($rows as $row) {
        $id = (int)$row->userid;
        if ($id > 0 && $id !== $excludeuserid) $userids[$id] = $id;
    }
    return array_values($userids);
}

function userstatsadvanced_show_messages_get_course_related_context_ids(int $courseid): array {
    global $DB;
    if ($courseid <= 0) return [];

    $coursecontext = $DB->get_record('context', [
        'contextlevel' => CONTEXT_COURSE,
        'instanceid'   => $courseid,
    ], 'id,path', IGNORE_MISSING);
    if (!$coursecontext) return [];

    $contextids = [];
    if (!empty($coursecontext->path)) {
        foreach (explode('/', trim((string)$coursecontext->path, '/')) as $chunk) {
            $id = (int)$chunk;
            if ($id > 0) $contextids[$id] = $id;
        }
    }
    $id = (int)$coursecontext->id;
    if ($id > 0) $contextids[$id] = $id;

    return array_values($contextids);
}

function userstatsadvanced_show_messages_get_course_nonstudent_user_ids(int $courseid, int $excludeuserid = 0): array {
    global $DB;
    $contextids = userstatsadvanced_show_messages_get_course_related_context_ids($courseid);
    if (empty($contextids)) return [];

    [$ctxinsql, $ctxparams] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED, 'ctxnonstd');
    $sql = "SELECT DISTINCT ra.userid
              FROM {role_assignments} ra
              JOIN {context} ctx ON ctx.id = ra.contextid
              JOIN {role} r ON r.id = ra.roleid
             WHERE ctx.id $ctxinsql
               AND (r.archetype IS NULL OR r.archetype = '' OR r.archetype <> :studentarchetype)";
    $rows = $DB->get_records_sql($sql, array_merge($ctxparams, ['studentarchetype' => 'student']));
    if (empty($rows)) return [];

    $userids = [];
    foreach ($rows as $row) {
        $id = (int)$row->userid;
        if ($id > 0 && $id !== $excludeuserid) $userids[$id] = $id;
    }
    return array_values($userids);
}

function userstatsadvanced_show_messages_get_course_staff_user_ids_by_capability(
    int $courseid, int $excludeuserid = 0
): array {
    if ($courseid <= 0) return [];
    try {
        $context = context_course::instance($courseid);
    } catch (Throwable $t) {
        return [];
    }
    if (!$context) return [];

    $users   = get_enrolled_users($context, 'moodle/course:update', 0, 'u.id');
    $userids = [];
    foreach ($users as $user) {
        $id = (int)($user->id ?? 0);
        if ($id > 0 && $id !== $excludeuserid) $userids[$id] = $id;
    }
    return array_values($userids);
}

// ─── Conversation members ─────────────────────────────────────────────────────

function userstatsadvanced_show_messages_get_conversation_members(array $conversationids, array &$userids): array {
    global $DB;
    if (empty($conversationids)) return [];

    [$insql, $params] = $DB->get_in_or_equal(array_values($conversationids), SQL_PARAMS_NAMED, 'conv');
    $sql = "SELECT mcm.id, mcm.conversationid, mcm.userid
              FROM {message_conversation_members} mcm
             WHERE mcm.conversationid $insql
          ORDER BY mcm.conversationid ASC, mcm.id ASC";
    $rows = $DB->get_records_sql($sql, $params);

    $byconversation = [];
    foreach ($rows as $row) {
        $cid = (int)$row->conversationid;
        $mid = (int)$row->userid;
        if ($cid <= 0 || $mid <= 0) continue;
        $byconversation[$cid][] = $mid;
        $userids[$mid] = $mid;
    }
    return $byconversation;
}

// ─── User name helpers ────────────────────────────────────────────────────────

function userstatsadvanced_show_messages_get_users_map(array $userids): array {
    global $DB;
    $normalizeduserids = [];
    foreach ($userids as $uid) {
        $uid = (int)$uid;
        if ($uid > 0) $normalizeduserids[$uid] = $uid;
    }
    if (empty($normalizeduserids)) return [];

    [$insql, $params] = $DB->get_in_or_equal(array_values($normalizeduserids), SQL_PARAMS_NAMED, 'usr');
    return $DB->get_records_sql(
        "SELECT id, firstname, lastname, middlename, firstnamephonetic, lastnamephonetic, alternatename, deleted
           FROM {user} WHERE id $insql",
        $params
    );
}

function userstatsadvanced_show_messages_get_user_name(array $users, int $userid): string {
    if ($userid > 0 && !empty($users[$userid])) return fullname($users[$userid]);
    if ($userid > 0) return get_string('user') . ' #' . $userid;
    return '-';
}

function userstatsadvanced_show_messages_join_user_names(array $users, array $userids): string {
    $names = [];
    foreach ($userids as $uid) {
        $uid = (int)$uid;
        if ($uid > 0) $names[$uid] = userstatsadvanced_show_messages_get_user_name($users, $uid);
    }
    return empty($names) ? '-' : implode(', ', array_values($names));
}

// ─── Text / rendering helpers ─────────────────────────────────────────────────

function userstatsadvanced_show_messages_resolve_subject(
    string $subject, string $smallmessage, string $fullmessage
): string {
    foreach ([$subject, $smallmessage, $fullmessage] as $candidate) {
        $candidate = trim(preg_replace('/\s+/', ' ', strip_tags($candidate)));
        if ($candidate === '') continue;
        if (core_text::strlen($candidate) > 120) return core_text::substr($candidate, 0, 117) . '...';
        return $candidate;
    }
    return get_string('userstatsadvanced_no_subject', 'block_configurable_reports');
}

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
    return html_writer::div($html, '', ['style' => 'min-width:280px;max-width:620px;white-space:normal;word-break:break-word;']);
}

function userstatsadvanced_show_messages_render_forum_message_html(object $row, context $context): string {
    if (!empty($row->deleted)) {
        $html = s(get_string('userstatsadvanced_deleted_message', 'block_configurable_reports'));
    } else if (trim((string)$row->message) !== '') {
        $format = isset($row->messageformat) ? (int)$row->messageformat : FORMAT_HTML;
        $html = format_text((string)$row->message, $format, ['context' => $context, 'para' => false]);
    } else {
        $html = s(get_string('userstatsadvanced_no_subject', 'block_configurable_reports'));
    }
    return html_writer::div($html, '', ['style' => 'min-width:280px;max-width:620px;white-space:normal;word-break:break-word;']);
}

function userstatsadvanced_show_messages_render_created_html(int $timestamp): string {
    if ($timestamp <= 0) return '-';
    return s(userdate($timestamp, '%d/%m/%Y')) .
        html_writer::empty_tag('br') .
        s(userdate($timestamp, '%H:%M'));
}

function userstatsadvanced_show_messages_merge_records_by_date(array $records, array $extrarecords): array {
    $allrecords = array_merge($records, $extrarecords);
    if (empty($allrecords)) return [];

    usort($allrecords, static function(array $a, array $b): int {
        $ats = (int)($a['createdts'] ?? 0);
        $bts = (int)($b['createdts'] ?? 0);
        if ($ats === $bts) return 0;
        return ($ats > $bts) ? -1 : 1;
    });
    return $allrecords;
}

// ─── SQL helpers ──────────────────────────────────────────────────────────────

function userstatsadvanced_show_messages_get_table_columns(string $tablename): array {
    global $DB;
    try {
        $columns = $DB->get_columns($tablename);
        return array_change_key_case($columns, CASE_LOWER);
    } catch (Throwable $t) {
        return [];
    }
}

function userstatsadvanced_show_messages_optional_column_sql(
    array $columns, string $columnname, string $defaultsql, string $tablealias = 'm'
): string {
    if (!empty($columns[$columnname])) {
        return $tablealias . '.' . $columnname . ' AS ' . $columnname;
    }
    return $defaultsql . ' AS ' . $columnname;
}
