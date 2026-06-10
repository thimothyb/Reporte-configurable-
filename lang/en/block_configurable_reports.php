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
 * Configurable Reports a Moodle block for creating customizable reports
 *
 * @copyright  2020 Juan Leyva <juan@moodle.com>
 * @package    block_configurable_reports
 * @author     Juan leyva <http://www.twitter.com/jleyvadelgado>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = "Configurable Reports";
$string['blockname'] = "Configurable Reports";
$string['report_courses'] = "Courses report";
$string['report_users'] = "Users report";
$string['report_sql'] = "SQL Report";
$string['managereports'] = "Manage reports";

$string['report'] = "Report";
$string['reports'] = "Reports";

$string['columns'] = "Columns";
$string['conditions'] = "Conditions";
$string['permissions'] = "Permissions";
$string['plot'] = "Plot - Graphs";
$string['filters'] = "Filters	";
$string['calcs'] = "Calculations";
$string['ordering'] = "Ordering";
$string['customsql'] = "Custom SQL";
$string['addreport'] = "Add report";
$string['type'] = "Type of report";
$string['columncalculations'] = "Column Calculations";
$string['newreport'] = "New report";
$string['column'] = "Column";
$string['confirmdeletereport'] = "Are you sure you want to delete this report?";
$string['noreportsavailable'] = "No reports available";
$string['downloadreport'] = "Download report";
$string['reportlimit'] = "Report row limit";
$string['reportlimitinfo'] = "Limit the number of rows that are displayed in the report table
    (Default is 5000 rows. Better to have some limit, so users will not over load the DB engine)";

$string['configurable_reports:addinstance'] = 'Add a new configurable reports block';
$string['configurable_reports:myaddinstance'] = 'Add a new configurable reports block to MY HOME page';
$string['configurable_reports:manageownreports'] = "Manage own reports";
$string['configurable_reports:managereports'] = "Manage reports";
$string['configurable_reports:managesqlreports'] = "Manage SQL reports";
$string['configurable_reports:viewreports'] = "View reports";

$string['exportoptions'] = "Export options";
$string['embedoptions'] = "Embed options";
$string['field'] = "Field";

// Report form.
$string['typeofreport'] = "Type of report";
$string['enablejsordering'] = "Enable JavaScript ordering";
$string['enablejspagination'] = "Enable JavaScript Pagination";
$string['export_csv'] = "Export in CSV format";
$string['export_ods'] = "Export in ODS format";
$string['export_slk'] = "Export in SYLK format";
$string['export_xls'] = "Export in XLS format";
$string['export_json'] = "Export in JSON format";
$string['viewreport'] = "View report";
$string['norecordsfound'] = "No records found";
$string['reportcachewarning'] = 'Cached data is being shown for this report. If you want to force a recalculation, click {$a}.';
$string['refreshreporttab'] = 'Update';
$string['refreshreportdata'] = 'Update records';
$string['reportcacherefreshed'] = 'The report data was recalculated successfully.';
$string['jsordering'] = 'JavaScript Ordering';
$string['cron'] = 'Auto run daily';
$string['crondescription'] = 'Schedule this query to run each day (At night)';
$string['displaytotalrecords'] = 'Total Records';
$string['displaytotalrecordsdescription'] = 'Displays the total number of results in the report';
$string['displayprintbutton'] = 'Print Button';
$string['displayprintbuttondescription'] = 'Displays the print button at the bottom of the report';
$string['embedlink'] = 'Embed Link';
$string['embedlinkdescription'] = 'You can copy this link to embed the report in an HTML block';
$string['cron_help'] = 'Schedule this query to run each day (At night)';
$string['remote'] = 'Run on remote db';
$string['remotedescription'] = 'Do you want to run this query on the remote db';
$string['remote_help'] = 'Do you want to run this query on the remote db';
$string['setcourseid'] = 'Set courseid';

// Columns.
$string['column'] = "Column";
$string['nocolumnsyet'] = "No columns yet";
$string['tablealign'] = "Table align";
$string['tablecellspacing'] = "Table cellspacing";
$string['tablecellpadding'] = "Table cellpadding";
$string['tableclass'] = "Table class";
$string['tablewidth'] = "Table width";
$string['cellalign'] = "Cell align";
$string['cellwrap'] = "Cell wrap";
$string['cellsize'] = "Cell size";

// Conditions.
$string['conditionexpr'] = "Condition";
$string['conditionexprhelp'] = "Enter a valid condition i.e: (c1 and c2) or (c4 and c3)";
$string['noconditionsyet'] = "No conditions yet";
$string['operator'] = "Operator";
$string['value'] = "Value";

// Filter.
$string['filter'] = "Filter";
$string['nofilteryet'] = "No filters yet";
$string['courses'] = "Courses";
$string['nofiltersyet'] = "No filters yet";
$string['filter_all'] = 'All';
$string['filter_apply'] = 'Apply';
$string['filter_searchtext'] = 'Search text';
$string['searchtext'] = 'Search text';
$string['filter_searchtext_summary'] = 'Free text filter';
$string['years'] = 'Year (Numeric)';
$string['filteryears'] = 'Year (Numeric)';
$string['filteryears_summary'] = 'Filter by years (numeric representation, 2012...)';
$string['filteryears_list'] = '2010,2011,2012,2013,2014,2015';
$string['semester'] = 'Semester (Hebrew)';
$string['filtersemester'] = 'Semester (Hebrew)';
$string['filtersemester_summary'] = 'מאפשר סינון לפני סמסטרים (בעברית, למשל: סמסטר א,סמסטר ב)';
$string['filtersemester_list'] = 'סמסטר א,סמסטר ב,סמסטר ג,סמינריון';
$string['subcategories'] = 'Category (Include sub categories)';
$string['filtersubcategories'] = 'Category (Include sub categories)';
$string['filtersubcategories_summary'] = 'Use: %%FILTER_SUBCATEGORIES:mdl_course_category.path%%';
$string['yearnumeric'] = 'Year (Numeric)';
$string['filteryearnumeric'] = 'Year (Numeric)';
$string['filteryearnumeric_summary'] = 'Filter is using numeric years (2013,...)';
$string['yearhebrew'] = 'Year (Hebrew)';
$string['filteryearhebrew'] = 'Year (Hebrew)';
$string['filteryearhebrew_list'] = 'תשע,תשעא,תשעב,תשעג,תשעד,תשעה';
$string['filteryearhebrew_summary'] = 'Filter is using Hebrew years (תשעג,...)';
$string['role'] = 'Role';
$string['filterrole'] = 'role';
$string['filterrole_summary'] = 'Filter system Roles (Teacher, Student, ...)';
$string['coursemodules'] = 'Course module';
$string['filtercoursemodules'] = 'Course module';
$string['filtercoursemodules_summary'] = 'Filter course modules';
$string['user'] = 'Course user (id)';
$string['filteruser'] = 'Current course user';
$string['filteruser_summary'] = 'Filter a user (id) from current course users';
$string['users'] = 'System user (id)';
$string['filterusers'] = 'System user';
$string['enrolledstudents'] = 'Enrolled students';
$string['filterusers_summary'] = 'Filter a user (by id) from system user list';
$string['filterenrolledstudents'] = 'Enrolled course students';
$string['filterenrolledstudents_summary'] = 'Filter a user (by id) from enrolled course students';
$string['competencyframeworks'] = 'Competency Frameworks';
$string['filtercompetencyframeworks'] = 'Competency Frameworks';
$string['filtercompetencyframeworks_summary'] = 'Use: %%FILTER_COMPETENCYFRAMEWORKS:prefix_competency_framework.id%%';
$string['competencytemplates'] = 'Competency Templates';
$string['filtercompetencytemplates'] = 'Competency templates';
$string['filtercompetencytemplates_summary'] = 'Use: %%FILTER_COMPETENCYTEMPLATES:prefix_competency_template.id%%';
$string['cohorts'] = 'Cohorts';
$string['filtercohorts'] = 'Cohorts';
$string['filtercohorts_summary'] = 'Use: %%FILTER_COHORTS:prefix_cohort.id%%';
$string['student'] = 'Student';

// Calcs.
$string['nocalcsyet'] = "No calculations yet";

// Plot.
$string['noplotyet'] = "No plots yet";

// Permissions.

$string['nopermissionsyet'] = "No permissions yet";

// Ordering.

$string['noorderingyet'] = "No ordering yet";
$string['userfieldorder'] = "User field order";

// Plugins.
$string['coursefield'] = "Course field";
$string['coursecustomfield'] = 'Course custom field';
$string['coursecustomfield_field'] = 'Field';
$string['coursecustomfield_group_standard'] = 'Standard fields';
$string['coursecustomfield_group_custom'] = 'Custom fields';
$string['coursecustomfield_source_course'] = 'Course table';
$string['coursecustomfield_source_custom'] = 'Course custom field';
$string['course_custom_fields'] = 'Course custom field';
$string['course_custom_fields_field'] = 'Field';
$string['course_custom_fields_group_standard'] = 'Standard fields';
$string['course_custom_fields_group_custom'] = 'Custom fields';
$string['course_custom_fields_source_course'] = 'Course table';
$string['course_custom_fields_source_custom'] = 'Course custom field';
$string['ccoursefield'] = "Course field condition";
$string['roleusersn'] = "Number of users with role...";
$string['coursecategory'] = "Course in category";
$string['filtercourses'] = "Courses";
$string['filtercourses_summary'] = "This filter shows a list of courses. Only one course can be selected at the same time";
$string['roleincourse'] = "User with the selected role/s in the current report course";
$string['reportscapabilities'] = "Report Capabilities";
$string['reportscapabilities_summary'] = "Users with the capability moodle/site:viewreports enabled";
$string['sum'] = "Sum";
$string['max'] = "Maximum";
$string['min'] = "Minimum";
$string['percent'] = "Percentage";
$string['average'] = "Average";
$string['pie'] = "Pie";
$string['piesummary'] = "A pie graph";
$string['pieareaname'] = "Name";
$string['pieareavalue'] = "Value";
$string['piesummary'] = "A pie graph";

$string['bar'] = "Bar";
$string['barsummary'] = "A bar graph";
$string['label_field'] = "Label field";
$string['label_field_help'] = "The field that provides names for the things represented in the graph";
$string['value_fields'] = "Value fields";
$string['value_fields_help'] = "Fields that should be represented in the graph. Ctrl+click (Cmd+click on Mac) to select multiple.
If you select the Label field or a field with non-numeric values it will be ignored";

$string['width'] = "Width";
$string['height'] = "Height";
$string['head_data'] = "Graph data";
$string['head_size'] = "Graph size";
$string['head_color'] = "Graph background color";

$string['anyone'] = "Anyone";
$string['anyone_summary'] = "Any user in the Campus will be able to view this report";

$string['currentuserfinalgrade'] = "Current user final grade in course";

$string['currentuserfinalgrade_summary'] = "This column shows the final grade of the current user in the row-course";
$string['userfield'] = "User profile field";
$string['userfield_column_id'] = 'ID';
$string['userfield_column_auth'] = 'Authentication method';
$string['userfield_column_confirmed'] = 'Confirmed account';
$string['userfield_column_policyagreed'] = 'Policy agreed';
$string['userfield_column_deleted'] = 'Deleted';
$string['userfield_column_suspended'] = 'Suspended';
$string['userfield_column_mnethostid'] = 'MNet host';
$string['userfield_column_emailstop'] = 'Email disabled';

$string['cuserfield'] = "User field condition";
$string['direction'] = "Direction";

$string['courseparent'] = "Courses whose parent is";
$string['coursechild'] = "Courses that are children of";

$string['currentusercourses'] = "Current user enrolled courses";
$string['currentusercourses_summary'] = "A list of the current users courses (only visible courses)";
$string['currentreportcourse'] = "Current report course";
$string['currentreportcourse_summary'] = "The course where the report has been created";

$string['coursefieldorder'] = "Course field order";

$string['fcoursefield'] = "Course field filter";
$string['usersincoursereport'] = "Any user in the current report course";

$string['groupvalues'] = "Group same values (sum)";
$string['fuserfield'] = "User field filter";
$string['fsearchuserfield'] = "User field search box";

$string['module'] = "Module";

$string['usersincurrentcourse'] = "Users in current report course";
$string['usersincurrentcourse_summary'] = "Users with the role/s selected in the report course";

$string['usermodoutline'] = "User module outline stats";
$string['donotshowtime'] = "Do not show date information";
$string['usermodactions'] = "User module actions";
$string['scormadvancedgrades'] = 'Estadísticas de actividades y recursos';
$string['scorm_advanced_grades'] = 'Estadísticas de actividades y recursos';
$string['scormadvancedgrades_stat'] = 'Statistic';
$string['scormadvancedgrades_format'] = 'Format';
$string['scormadvancedgrades_format_auto'] = 'Automatic';
$string['scormadvancedgrades_format_text'] = 'Text';
$string['scormadvancedgrades_format_number'] = 'Number';
$string['scormadvancedgrades_format_percent'] = 'Percentage';
$string['scormadvancedgrades_format_datetime'] = 'Date';
$string['scormadvancedgrades_addmultiplecolumns'] = 'Add multiple columns';
$string['scormadvancedgrades_addmultiple_title'] = 'Add multiple columns';
$string['scormadvancedgrades_addmultiple_intro'] = 'Select global columns and activities to automatically generate statistics.';
$string['scormadvancedgrades_addmultiple_quizsection'] = 'Quizzes';

$string['scormadvancedgrades_addmultiple_assignsection'] = 'Assignments';
$string['scormadvancedgrades_addmultiple_forumsection'] = 'Forums';
$string['scormadvancedgrades_addmultiple_addassignscore'] = 'Add score for all assignments';
$string['scormadvancedgrades_addmultiple_addassignsubmissiondate'] = 'Add submission dates for all assignments';
$string['scormadvancedgrades_addmultiple_addassigngradedate'] = 'Add grading dates for all assignments';
$string['scormadvancedgrades_addmultiple_addassigngrader'] = 'Add user who graded the assignment';
$string['scormadvancedgrades_addmultiple_addassignfeedback'] = 'Add assignment feedback';
$string['scormadvancedgrades_addmultiple_addforumposts'] = 'Add number of user posts in forum';
$string['scormadvancedgrades_addmultiple_addforumfirstpost'] = 'Add first post date';
$string['scormadvancedgrades_addmultiple_addforumlastpost'] = 'Add last post date';
$string['scormadvancedgrades_addmultiple_chatsection'] = 'Chats';
$string['scormadvancedgrades_addmultiple_addchatmessages'] = 'Add number of messages in chats';
$string['scormadvancedgrades_addmultiple_addchatfirstmessage'] = 'Add first date and time in chats';
$string['scormadvancedgrades_addmultiple_addchatlastmessage'] = 'Add last date and time in chats';
$string['scormadvancedgrades_addmultiple_noselection_chat'] = 'You must select at least one chat.';
$string['scormadvancedgrades_addmultiple_scormsection'] = 'SCORMs';
$string['scormadvancedgrades_addmultiple_addscormcompletiondate'] = 'Add completion date of the SCORM';
$string['scormadvancedgrades_addmultiple_addsCormscore'] = 'Add score of the SCORM';
$string['scormadvancedgrades_addmultiple_addscormscocompleted'] = 'Add completed SCO objects';
$string['scormadvancedgrades_addmultiple_addsCormdedicationtime'] = 'Add dedication time in the SCORM';
$string['scormadvancedgrades_addmultiple_addsCormlastaccess'] = 'Add last access to the SCORM';
$string['scormadvancedgrades_addmultiple_noselection_scorm'] = 'You must select at least one SCORM.';
$string['scormadvancedgrades_addmultiple_activitysectionheading'] = 'Select activities of type "{$a}"';
$string['scormadvancedgrades_addmultiple_addquizcompletiondate'] = 'Add completion date for all quizzes';
$string['scormadvancedgrades_addmultiple_addquizscore'] = 'Add score for all quizzes';
$string['scormadvancedgrades_addmultiple_addquizdedicationtime'] = 'Add dedication time for all quizzes';
$string['scormadvancedgrades_addmultiple_addquizopendate'] = 'Add opening date for all quizzes';
$string['scormadvancedgrades_addmultiple_addquizfirstpassattempt'] = 'Add first passed attempt for all quizzes';
$string['scormadvancedgrades_addmultiple_selectcol'] = 'Select';
$string['scormadvancedgrades_addmultiple_activitycol'] = 'Activity';
$string['scormadvancedgrades_addmultiple_selectall'] = 'Select all';
$string['scormadvancedgrades_addmultiple_clearall'] = 'Clear selection';
$string['scormadvancedgrades_addmultiple_saved'] = 'Columns were added successfully.';
$string['scormadvancedgrades_addmultiple_noselection'] = 'You must select at least one activity.';

$string['scormadvancedgrades_addmultiple_noselection_quiz'] = 'You must select at least one quiz.';
$string['scormadvancedgrades_addmultiple_noselection_assign'] = 'You must select at least one assignment.';
$string['scormadvancedgrades_addmultiple_noselection_forum'] = 'You must select at least one forum.';
$string['scormadvancedgrades_addmultiple_nometrics'] = 'You must select at least one column option.';
$string['scormadvancedgrades_addmultiple_fallbackname'] = 'Activity {$a}';

$string['currentuser'] = "Current user";
$string['currentuser_summary'] = "The user that is viewing the report";

$string['puserfield'] = "User field value";
$string['puserfield_summary'] = "User with the selected value in the selected field";

$string['startendtime'] = "Start / End date filter";
$string['starttime'] = "Start date";
$string['endtime'] = "End date";
$string['filteraccessscope'] = 'Access scope';
$string['filteraccessscope_summary'] = 'Switch between only course events and course + platform events';
$string['filteraccessscope_courseonly'] = 'Course only';
$string['filteraccessscope_courseplatform'] = 'Course + platform (courseid = 0)';

$string['template'] = "Template";
$string['availablemarks'] = "Available marks";
$string['header'] = "Header";
$string['footer'] = "Footer";
$string['templaterecord'] = "Record template";
$string['querysql'] = "SQL Query";
$string['filterstartendtime_summary'] = "Start / End date filter";

$string['pagination'] = "Pagination";
$string['disabled'] = "Disabled";
$string['enabled'] = "Enabled";

$string['reportcolumn'] = "Other report column";

$string['reporttable'] = "Report table";
$string['columnandcellproperties'] = "Column and cell properties";
$string['componenthelp'] = "Component help";

$string['badsize'] = 'Incorrect size, it must be in &#37; or px';
$string['badtablewidth'] = 'Incorrect width, it must be in &#37; or absolute value';
$string['missingcolumn'] = "A column is required";
$string['error_operator'] = "Operator not allowed";

$string['error_field'] = "Field not allowed";
$string['error_value_expected_integer'] = "Expected integer value";
$string['badconditionexpr'] = "Incorrect condition expression";

$string['notallowedwords'] = "Not allowed words";
$string['nosemicolon'] = "No semicolon";
$string['noexplicitprefix'] = "No explicit prefix";
$string['queryfailed'] = 'Query failed <code><pre>{$a}</pre></code>';
$string['norowsreturned'] = "No rows returned";

$string['listofsqlreports'] = 'Press F11 when cursor is in the editor to toggle full screen editing. Esc can also be used to exit
full screen editing.<br/><br/>SQL helper placeholders for access analytics:<br/>
<code>%%FILTER_ACCESSSCOPE_COURSEID:log.courseid%%</code> (use with Access scope filter)<br/>
<code>%%ACCESS_SOURCE_EXPR:log.courseid%%</code> (returns course/platform)<br/>
<code>%%ACCESS_EVENTTYPE_EXPR:log.eventname%%</code> (returns login/logout/activity)<br/>
<code>%%ACCESS_SCOPE%%</code> and <code>%%ACCESS_SCOPE_LABEL%%</code> (SQL string literals)<br/>
<code>%%STARTTIME%%</code> and <code>%%ENDTIME%%</code> (filled automatically when Start/End filter is present)<br/><br/>
<code>%%ACADEMIC_STARTTIME%%</code> and <code>%%ACADEMIC_ENDTIME%%</code> (course period from course settings)<br/>
<code>%%FILTER_ACADEMICPERIOD:log.timecreated%%</code> (injects an AND clause using course period)<br/><br/>
<a href="http://docs.moodle.org/en/ad-hoc_contributed_reports" target="_blank">List of SQL Contributed reports</a>';

$string['exportmetadata_generatedat'] = 'Generated at';
$string['exportmetadata_scope'] = 'Access scope';
$string['exportmetadata_startdate'] = 'Start date';
$string['exportmetadata_enddate'] = 'End date';

$string['usersincoursereport_summary'] = "Any user in the current report course";

$string['printreport'] = 'Print report';

$string['importreport'] = "Import report";
$string['exportreport'] = "Export report";

$string['download'] = "Download";

$string['report_timeline'] = 'Timeline report';
$string['timeline'] = 'Timeline';
$string['timemode'] = 'Time mode';
$string['previousdays'] = 'Previous days';
$string['fixeddate'] = 'Fixed date';
$string['previousstart'] = 'Previous start';
$string['previousend'] = 'Previous end';
$string['forcemidnight'] = 'Force midnight';
$string['timeinterval'] = 'Time interval';
$string['date'] = 'Date';
$string['dateformat'] = 'Date format';
$string['customdateformat'] = 'Custom date format';
$string['custom'] = 'Custom';

$string['line'] = 'Line graph';
$string['userstats'] = 'User statistics';
$string['userstatsadvanced'] = 'Advanced user statistics';
$string['user_stats_advanced'] = 'Advanced user statistics';
$string['userstatsadvanced_stattype'] = 'Statistic type';
$string['userstatsadvanced_actividades_aprendizaje'] = 'Learning activities (completed / total)';
$string['userstatsadvanced_actividades_aprendizaje_moodle_completion'] = 'Completed assignments / Total (Moodle completion criterion)';
$string['userstatsadvanced_evaluaciones'] = 'Assessments (completed / total)';
$string['userstatsadvanced_contenidos_visualizados'] = 'Viewed content (overall progress)';
$string['userstatsadvanced_recursos_completados'] = 'Completed resources / Total';
$string['userstatsadvanced_correos'] = 'Messages with teachers';
$string['userstatsadvanced_mensajes_alumnos'] = 'Messages with students';
$string['userstatsadvanced_registros'] = 'Log records';
$string['userstatsadvanced_dias_conexion'] = 'Connection days';
$string['userstatsadvanced_interacciones_foros'] = 'Forum interactions';
$string['userstatsadvanced_mensajes_foro'] = 'Forum posts';
$string['userstatsadvanced_ips_utilizadas'] = 'IPs used';
$string['userstatsadvanced_ultima_ip'] = 'Last IP';
$string['userstatsadvanced_nota_final'] = 'Final course grade';
$string['userstatsadvanced_primer_acceso'] = 'First access';
$string['userstatsadvanced_ultimo_acceso'] = 'Last access';
$string['userstatsadvanced_primer_acceso_scorm'] = 'First SCORM access';
$string['userstatsadvanced_matricula_activa'] = 'Active enrolment';
$string['userstatsadvanced_scorm_completados'] = 'Completed/passed SCORMs';
$string['userstatsadvanced_mensajes_tutor'] = 'Messages to tutor';
$string['userstatsadvanced_foros_publicados'] = 'Forum posts';
$string['userstatsadvanced_tareas_entregadas'] = 'Assignments submitted';
$string['userstatsadvanced_intentos_cuestionario'] = 'Quiz attempts';
$string['userstatsadvanced_evaluaciones_moodle_completion'] = 'Completed quizzes / Total (Moodle completion criterion)';
$string['userstatsadvanced_finalizacion_cruzada'] = 'Cross completion';
$string['userstatsadvanced_logs_integracion'] = 'Integration logs';
$string['userstatsadvanced_tiempo_total'] = 'Total dedication time (hours format)';
$string['userstatsadvanced_tiempos_conexion_diarios_html'] = 'Daily connection times HTML table';
$string['userstatsadvanced_maxdisplayvalue'] = 'Maximum value to display (0-100)';
$string['userstatsadvanced_maxdisplayvalue_error'] = 'Maximum value must be between 0 and 100.';
$string['userstatsadvanced_displayformat'] = 'Display format';
$string['userstatsadvanced_displayformat_numdenum_percent'] = 'Num / Denum (%)';
$string['userstatsadvanced_displayformat_percent'] = '%';
$string['userstatsadvanced_displayformat_progress'] = 'Progress bar';
$string['userstatsadvanced_modalreport'] = 'Show linked report in modal window';
$string['userstatsadvanced_modalreport_no'] = 'No';
$string['userstatsadvanced_modalreport_open'] = 'View detail';
$string['userstatsadvanced_download_pdf'] = 'Download PDF report';
$string['userstatsadvanced_informe_pdf'] = 'PDF report download button';
$string['exporttemplate'] = 'Export template';
$string['actualizarregistros'] = 'Actualizar registros';
$string['coursesectionsconfig'] = 'Configure course sections';
$string['coursesections_disable'] = 'Disable course sections functionality';
$string['userstatsadvanced_view_message'] = 'View message';
$string['userstatsadvanced_messages_detail'] = 'Message details';
$string['userstatsadvanced_forum_messages_detail'] = 'Forum message details';
$string['userstatsadvanced_from'] = 'From';
$string['userstatsadvanced_to'] = 'To';
$string['userstatsadvanced_forum'] = 'Forum';
$string['userstatsadvanced_subject'] = 'Subject';
$string['userstatsadvanced_message'] = 'Message';
$string['userstatsadvanced_created'] = 'Created';
$string['userstatsadvanced_students_group'] = 'Students';
$string['userstatsadvanced_user_label'] = 'User';
$string['userstatsadvanced_no_subject'] = '-';
$string['userstatsadvanced_deleted_message'] = 'Deleted message';
$string['userstatsadvanced_select_quizzes'] = 'Select quizzes';
$string['userstatsadvanced_select_quizzes_help'] = 'If you do not select quizzes, all visible quizzes in the course will be used.';
$string['userstatsadvanced_selected_quizzes_count'] = 'Selected quizzes: {$a}';
$string['userstatsadvanced_elements_filter_default_quizzes'] = 'Course access and quizzes';
$string['userstatsadvanced_select_tasks'] = 'Select assignments';
$string['userstatsadvanced_select_tasks_help'] = 'If you do not select assignments, all visible assignments in the course will be used.';
$string['userstatsadvanced_select_completed_resources_help'] = 'If you do not select items, all completed resources in the course will be used.';
$string['userstatsadvanced_selected_tasks_count'] = 'Selected assignments: {$a}';
$string['userstatsadvanced_elements_filter_default_tasks'] = 'Course access and assignments';
$string['userstatsadvanced_elements_filter_default_completed_resources'] = 'All completed resources in the course will be used.';
$string['stat'] = 'Statistic';
$string['statslogins'] = 'Logins in the platform';
$string['activityview'] = 'Activity views';
$string['activitypost'] = 'Activity posts';
$string[''] = '';
$string['globalstatsshouldbeenabled'] = 'Site statistics must be enabled. Go to Admin -> Server -> Statistics';

$string['xaxis'] = 'X Axis';
$string['yaxis'] = 'Y Axis';
$string['serieid'] = 'Serie column';
$string['groupseries'] = 'Group series';
$string['linesummary'] = 'A line graph with multiple series of data';
$string['xandynotequal'] = 'X and Y axis need to be different';

$string['coursestats'] = 'Course stats';
$string['statstotalenrolments'] = 'Total enrolments';
$string['statsactiveenrolments'] = 'Active (last week) enrolments';
$string['youmustselectarole'] = 'At least a role is required';

$string['report_categories'] = 'Categories report';
$string['categoryfield'] = 'Category field';
$string['categoryfieldorder'] = 'Category field order';
$string['categories'] = 'Categories';
$string['parentcategory'] = 'Parent category';
$string['filtercategories'] = 'Filter categories';
$string['filtercategories_summary'] = 'To filter by category';

$string['includesubcats'] = 'Include subcategories';

$string['coursededicationtime'] = 'Course dedication time';

$string['jsordering_help'] = 'JavaScript Ordering allow you to order the report table without reloading the page';
$string['pagination_help'] = 'Number of records to show in each page. Zero means no pagination';
$string['typeofreport_help'] = 'Choose the type of report you want to create.
For security, SQL Report requires an additional capability';
$string['template_marks'] = 'Template marks';
$string['template_marks_help'] = '<p>You can use any of this replacement marks:</p>

<ul>
<li>##reportname## - For including the report name</li>
<li>##reportsummary## - For including the reports summary</li>
<li>##graphs## - For including the graphs</li>
<li>##exportoptions## - For including the export options</li>
<li>##calculationstable## - For including the calculations table</li>
<li>##pagination## - For including the pagination </li>

</ul>';

$string['conditionexpr_conditions'] = 'Condition';
$string['conditionexpr_conditions_help'] = '<p>You can combine conditions using a logic expression</p>

<p>Enter a valid logic expression with these operators: and, or.</p>';

$string['conditionexpr_permissions'] = 'Condition';
$string['conditionexpr_permissions_help'] = '<p>You can combine conditions using a logic expression</p>

<p>Enter a valid logic expression with these operators: and, or.</p>';

$string['reporttable_help'] = '<p>This is the width of the table that will display the report records.</p>

<p>If you use a Template this option has no effect</p>';

$string['comp_calcs'] = 'Calcs';
$string['comp_calcs_help'] = '<p>Here you can add calculations for columns, i.e: average of number of users enrolled in courses</p>

<p>More help: <a href="http://docs.moodle.org/en/blocks/configurable_reports/" target="_blank">Plugin documentation</a></p>';

$string['comp_calculations'] = 'Calcs';
$string['comp_calculations_help'] =
    '<p>Here you can add calculations for columns, i.e: average of number of users enrolled in courses</p>';
$string['comp_conditions'] = 'Conditions';
$string['comp_conditions_help'] = '<p>Here you can define the conditions (i.e, only courses from this category, only users from Spain, etc.. </p>

<p>You can add a logical expression if you are using more than one condition.</p>

<p>More help: <a href="http://docs.moodle.org/en/blocks/configurable_reports/" target="_blank">Plugin documentation</a></p>';
$string['comp_customsql'] = 'Custom SQL';
$string['comp_customsql_help'] = '<p>Add a working SQL query. Do no use the moodle database prefix $CFG->prefix instead use "prefix_" without quotes</p>
<p>Example: SELECT * FROM prefix_course</p>

<p>You can find a lot of SQL Reports here: <a href="http://docs.moodle.org/en/ad-hoc_contributed_reports" target="_blank">ad-hoc contributed reports</a></p>

<p>An updated layout of Moodle\'s tables and their interconnected relations: <a href="https://docs.moodle.org/dev/Database_Schema" target="_blank">Database schema</a></p>

<p>Since this block supports Tim Hunt\'s CustomSQL Queries Reports, you can use any query.</p>

<p>Remember to add a "Time filter" if you are going to use reports with time tokens. </p>

<p>For using filters see: <a href="http://docs.moodle.org/en/blocks/configurable_reports/#Creating_a_SQL_Report" target="_blank">Creating a SQL Report Tutorial</a></p>';

$string['comp_ordering'] = 'Ordering';
$string['comp_ordering_help'] = '<p>Here you can choose how to order the report using fields and directions</p>

<p>More help: <a href="http://docs.moodle.org/en/blocks/configurable_reports/" target="_blank">Plugin documentation</a></p>';
$string['comp_permissions'] = 'Permissions';
$string['comp_permissions_help'] = '<p>Here you can choose who can view a report.</p>

<p>You can add a logical expression to calculate the final permission if you are using more than one condition.</p>

<p>More help: <a href="http://docs.moodle.org/en/blocks/configurable_reports/" target="_blank">Plugin documentation</a></p>';
$string['comp_plot'] = 'Plot';
$string['comp_plot_help'] = '<p>Here you can add graphs to your report based on the report columns and values</p>

<p>More help: <a href="http://docs.moodle.org/en/blocks/configurable_reports/" target="_blank">Plugin documentation</a></p>';
$string['comp_template'] = 'Template';
$string['comp_template_help'] = '<p>You can modify the report\'s layout by creating a template</p>

<p>For creating a template see the replacemnet marks you can use in header, footer and for each report record using the help buttons or the information displayed in the same page.</p>

<p>More help: <a href="http://docs.moodle.org/en/blocks/configurable_reports/" target="_blank">Plugin documentation</a></p>';
$string['comp_filters'] = 'Filters';
$string['comp_filters_help'] = '<p>Here you can choose which filters will be displayed</p>

<p>A filter lets an user to choose columns from the report to filter the report results</p>

<p>For using filters if your report type is SQL see: <a href="http://docs.moodle.org/en/blocks/configurable_reports/#Creating_a_SQL_Report" target="_blank">Creating a SQL Report Tutorial</a></p>

<p>More help: <a href="http://docs.moodle.org/en/blocks/configurable_reports/" target="_blank">Plugin documentation</a></p>';
$string['comp_columns'] = 'Columns';
$string['comp_columns_help'] = '<p>Here you can choose the different columns of your report depending on the type of report</p>

<p>More help: <a href="http://docs.moodle.org/en/blocks/configurable_reports/" target="_blank">Plugin documentation</a></p>';

$string['coursecategories'] = 'Category course filter';
$string['filtercoursecategories'] = 'Category course filter';
$string['filtercoursecategories_summary'] = 'Filter courses by their any parent category';

$string['dbhost'] = "DB Host";
$string['dbhostinfo'] = "Remote Database host name (on which, we will be executing our SQL queries)";
$string['dbname'] = "DB Name";
$string['dbnameinfo'] = "Remote Database name (on which, we will be executing our SQL queries)";
$string['dbuser'] = "DB Username";
$string['dbuserinfo'] = "Remote Database username (should have SELECT privileges on above DB)";
$string['dbpass'] = "DB Password";
$string['dbpassinfo'] = "Remote Database password (for above username)";

$string['totalrecords'] = 'Total record count = {$a->totalrecords}';
$string['lastexecutiontime'] = 'Execution time = {$a} (Sec)';

$string['reportcategories'] = '1) Choose a remote report categories';
$string['reportsincategory'] = '2) Choose a report form the list';
$string['remotequerysql'] = 'SQL query';
$string['executeat'] = 'Execute at';
$string['executeatinfo'] = 'Moodle CRON will run scheduled SQL queries after selected time. Once in 24h';
$string['sharedsqlrepository'] = 'Shared sql repository';
$string['sharedsqlrepositoryinfo'] = 'Name of GitHub account owner + slash + repository name';
$string['sqlsyntaxhighlight'] = 'Highlight SQL syntax';
$string['sqlsyntaxhighlightinfo'] = 'Highlight SQL syntax in code editor (CodeMirror JS library)';
$string['datatables'] = 'Enable DataTables JS library';
$string['datatablesinfo'] = 'DataTables JS library (Column sort, fixed header, search, paging...)';
$string['reporttableui'] = 'Report table UI';
$string['reporttableuiinfo'] = 'Display the report table as: Simple scrollable HTML table, jQuery with column sorting Or
DataTables JS library (Column sort, fixed header, search, paging...)';

$string['email_subject'] = 'Subject';
$string['email_message'] = 'Message';
$string['email_send'] = 'Send';

$string['sqlsecurity'] = 'SQL Security';
$string['sqlsecurityinfo'] = 'Disable for executing SQL queries with statements for inserting data';
$string['allowedsqlusers'] = 'SQL report users';
$string['allowedsqlusersinfo'] =
    'If you wish to only allow certain admin users to manage sql reports, add a list of usernames separated by commas. They must also have the block/configurable_reports:managesqlreports capability.';
$string['global'] = 'Global report';
$string['enableglobal'] = 'This is a global report (accesible from any course)';
$string['global_help'] =
    'Global report can be accessed from any course in the platform just appending &courseid=MY_COURSE_ID in the report URL';

$string['crrepository'] = 'Reports repository';
$string['crrepositoryinfo'] =
    'Remote shared repository with sample reports fully functional (Name of GitHub account owner + slash + repository name)';
$string['importfromrepository'] = 'Import report from repository';
$string['repository'] = 'Reports repository';
$string['repository_help'] = 'You can import sample reports from a public shared repository.

Please, notice that there is a daily limit of calls to the repository.

If the connection to the repository is not working, you can download manually here <a href="https://github.com/jleyva/moodle-configurable_reports_repository" target="_blank">https://github.com/jleyva/moodle-configurable_reports_repository</a> a report and then import it using the "Import report" feature displayed bellow
';
$string['reportcreated'] = 'Report successfully created';
$string['usersincohorts'] = 'User who are member of a/several cohorts';
$string['usersincohorts_summary'] = 'Only the users who are members of the selected cohorts';
$string['displayglobalreports'] = 'Display global reports';
$string['displayreportslist'] = 'Display the reports list in the block body';

$string['usercompletion'] = 'User course completion status';
$string['usercompletionsummary'] = 'Course completion status';

$string['finalgradeincurrentcourse'] = 'Final grade in current course';
$string['legacylognotenabled'] = 'Legacy logs must be enabled.
 Go to Site administration / Plugins / Logging Enable the Legacy log and inside the log settings check Log legacy data';

$string['datatables_sortascending'] = ': activate to sort column ascending';
$string['datatables_sortdescending'] = ': activate to sort column descending';
$string['datatables_first'] = 'First';
$string['datatables_last'] = 'Last';
$string['datatables_next'] = 'Next';
$string['datatables_previous'] = 'Previous';
$string['datatables_emptytable'] = 'No data available in table';
$string['datatables_info'] = 'Showing _START_ to _END_ of _TOTAL_ entries';
$string['datatables_infoempty'] = 'Showing 0 to 0 of 0 entries';
$string['datatables_infofiltered'] = '(filtered from _MAX_ total entries)';
$string['datatables_lengthmenu'] = 'Show _MENU_ entries';
$string['datatables_loadingrecords'] = 'Loading...';
$string['datatables_processing'] = 'Processing...';
$string['datatables_search'] = 'Search:';
$string['datatables_zerorecords'] = 'No matching records found';
// New features: Graph new column.

$string['others'] = 'Others';
$string['limitcategories'] = 'Limit categories in a graph';
$string['decimals'] = 'Number of decimals';
$string['sessionlimittime'] = 'Limit between clicks (in minutes)';
$string['sessionlimittime_help'] = 'The limit between clicks defines if two clicks are part of the same session or not';

$string['excludedeletedusers'] = 'Exclude deleted users (only for SQL reports)';

// Privacy provider.
$string['privacy:metadata:block_configurable_reports'] = 'The configurable reports block contains customizable course reports.';
$string['privacy:metadata:block_configurable_reports:courseid'] = 'Course ID';
$string['privacy:metadata:block_configurable_reports:ownerid'] = 'The ID of the user who created the report';
$string['privacy:metadata:block_configurable_reports:visible'] = 'Whether the report is visible or not';
$string['privacy:metadata:block_configurable_reports:global'] = 'Whether the report is accessible from all the courses or not';
$string['privacy:metadata:block_configurable_reports:name'] = 'The name of the report';
$string['privacy:metadata:block_configurable_reports:summary'] = 'The description of the report';
$string['privacy:metadata:block_configurable_reports:type'] = 'The type of the report';
$string['privacy:metadata:block_configurable_reports:components'] = 'The configuration of the report. It contains the query,
 the filters...';
$string['privacy:metadata:block_configurable_reports:lastexecutiontime'] = 'Time this report took to run last time it was executed,
 in milliseconds.';
// Filter forms.
$string['add'] = 'Add';
$string['description'] = 'Description';
$string['description_help'] = 'Text used to describe the filter that will be displayed in the summary on the filters page.';
$string['label'] = 'Label';
$string['label_help'] = 'Text describing the filter to be displayed on the report page.';
$string['idnumber'] = 'ID Number';
$string['idnumber_help'] = 'Used to differentiate between filters of the same type. Case-sensitive.
Example usage: %%FILTER_SEARCHTEXT_username:u.username:~%%';

// Pie Chart Strings.
$string['description'] = 'Description';
$string['legendheader'] = 'Mapped Palette';
$string['legendheaderdesc'] = 'Map color codes to specific keys in the pie chart legend.';
$string['piechart_label'] = 'Key - {$a}';
$string['piechart_label_color'] = 'Color - {$a}';
$string['piechart_add_colors'] = 'Add color';
$string['invalidcolorcode'] = 'Invalid color code';
$string['generalcolorpaletteheader'] = 'General color palette';
$string['generalcolorpalette'] = 'Unmapped Palette';
$string['generalcolorpalette_help'] = 'Hexadecimal color codes for general use in the pie chart. Codes should be separated
by new lines in the order you wish them to be used in the pie chart.';

$string['checksql_execution'] = 'Block Configurable Reports SQL execution';
$string['checksql_execution_ok'] = 'SQL execution is disabled.';

$string['checksql_execution_warning'] = 'It is recommended to disable SQL execution to avoid execution of arbitrary SQL code in
your server.';
$string['checksql_execution_details'] = 'By allowing SQL code execution there is a potential security issue with users adding
arbitrary code. SQL code execution should be disable to only allow SQL queries for reading/retreaving data. SQL execution can
be disabled in your config.php file by setting $CFG->block_configurable_reports_enable_sql_execution to 0';
$string['csvdelimiter'] = 'CSV delimiter';
$string['csvdelimiterinfo'] = 'CSV delimiter: "colon" for ":", "comma" for ",", semicolon for ";",  "tab" for "\t" and "cfg" for character configured in "CFG->CSV_DELIMITER" of the config.php file.';

