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
 * Download students assignment report data.
 *
 * @package    report_assignfeedback_download
 * @copyright  2026 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$id = required_param('id', PARAM_INT); // Course ID.
$dataformat = required_param('dataformat', PARAM_ALPHA);
$userids = required_param_array('userid', PARAM_INT);

if (!$course = $DB->get_record('course', ['id' => $id])) {
    throw new moodle_exception('invalidcourse', 'report_assignfeedback_download');
}

$context = context_course::instance($course->id);

require_login($course);
require_capability('report/assignfeedback_download:grade', $context);

if (empty($userids)) {
    $returnurl = new moodle_url('/report/assignfeedback_download/assignmentgeneralreports.php', ['id' => $id]);
    redirect($returnurl, get_string('nousersselected', 'report_assignfeedback_download'));
}

// Validate dataformat plugin.
$plugins = core_plugin_manager::instance()->get_plugins_of_type('dataformat');
if (!isset($plugins[$dataformat]) || !$plugins[$dataformat]->is_enabled()) {
    throw new moodle_exception('invalidparam', 'error', '', 'dataformat');
}

// Get all assignments in the course with cmid for reference.
$sql = "SELECT a.id, a.name, cm.id AS cmid
          FROM {assign} a
          JOIN {course_modules} cm ON cm.instance = a.id
          JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
         WHERE a.course = :courseid
      ORDER BY a.name ASC";
$assigns = $DB->get_records_sql($sql, ['courseid' => $course->id]);

// Build column names: firstname, lastname, email, groups, then per-assignment submission and grading status.
$columnnames = [
    'firstname' => get_string('firstname'),
    'lastname' => get_string('lastname'),
    'email' => get_string('email'),
    'groups' => get_string('groups'),
];

foreach ($assigns as $assign) {
    $safename = clean_param($assign->name, PARAM_ALPHANUMEXT);
    $columnnames['sub_' . $assign->id] = format_string($assign->name) . ' - ' .
        get_string('submissionstatus', 'report_assignfeedback_download');
    $columnnames['grade_' . $assign->id] = format_string($assign->name) . ' - ' .
        get_string('gradingstatus', 'report_assignfeedback_download');
}

$columnnames['lastaccess'] = get_string('lastcourseaccess');

// Get the selected users.
[$useridsql, $useridparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
[$enrolledsql, $enrolledparams] = get_enrolled_sql($context, 'mod/assign:submit');
[$groupconcatnamesql, $groupconcatnameparams] = groups_get_names_concat_sql($course->id);

$sql = "SELECT u.id, u.firstname, u.lastname, u.email,
               COALESCE(gcn.groupnames, '') AS groups,
               COALESCE(ul.timeaccess, 0) AS lastaccess
          FROM {user} u
          JOIN ({$enrolledsql}) je ON je.id = u.id
     LEFT JOIN ({$groupconcatnamesql}) gcn ON gcn.userid = u.id
     LEFT JOIN {user_lastaccess} ul ON ul.userid = u.id AND ul.courseid = :courseid
         WHERE u.id {$useridsql}
      ORDER BY u.lastname, u.firstname";

$params = array_merge(
    $enrolledparams,
    $groupconcatnameparams,
    ['courseid' => $course->id],
    $useridparams
);

// Pre-fetch submission and grading data for all selected users.
$assignids = array_keys($assigns);
$submittedlookup = [];
$gradedlookup = [];

if (!empty($assignids)) {
    [$uinsql, $uinparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'subuser');
    [$ainsql, $ainparams] = $DB->get_in_or_equal($assignids, SQL_PARAMS_NAMED, 'subassign');

    $submissions = $DB->get_records_sql(
        "SELECT s.userid, s.assignment
           FROM {assign_submission} s
          WHERE s.userid {$uinsql}
            AND s.assignment {$ainsql}
            AND s.status = 'submitted'",
        array_merge($uinparams, $ainparams)
    );
    foreach ($submissions as $sub) {
        $submittedlookup[$sub->userid][$sub->assignment] = true;
    }

    [$uinsql2, $uinparams2] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'gradeuser');
    [$ainsql2, $ainparams2] = $DB->get_in_or_equal($assignids, SQL_PARAMS_NAMED, 'gradeassign');

    $grades = $DB->get_records_sql(
        "SELECT g.userid, g.assignment
           FROM {assign_grades} g
          WHERE g.userid {$uinsql2}
            AND g.assignment {$ainsql2}
            AND g.grade >= 0",
        array_merge($uinparams2, $ainparams2)
    );
    foreach ($grades as $grade) {
        $gradedlookup[$grade->userid][$grade->assignment] = true;
    }
}

$rs = $DB->get_recordset_sql($sql, $params);

\core\dataformat::download_data(
    'assignment_report_' . clean_filename($course->shortname),
    $dataformat,
    $columnnames,
    $rs,
    function (stdClass $record) use ($assigns, $submittedlookup, $gradedlookup, $columnnames): stdClass {
        $out = new stdClass();
        $out->firstname = $record->firstname;
        $out->lastname = $record->lastname;
        $out->email = $record->email;
        $out->groups = $record->groups;

        foreach ($assigns as $assign) {
            $issubmitted = !empty($submittedlookup[$record->id][$assign->id]);
            $isgraded = !empty($gradedlookup[$record->id][$assign->id]);

            $out->{'sub_' . $assign->id} = $issubmitted
                ? get_string('submitted', 'report_assignfeedback_download')
                : get_string('notsubmitted', 'report_assignfeedback_download');

            $out->{'grade_' . $assign->id} = $isgraded
                ? get_string('graded', 'report_assignfeedback_download')
                : get_string('notgraded', 'report_assignfeedback_download');
        }

        $out->lastaccess = $record->lastaccess
            ? userdate($record->lastaccess)
            : get_string('never');

        return $out;
    }
);

$rs->close();
