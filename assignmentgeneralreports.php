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
 * Assignment general reports page with filterable students table.
 *
 * @package    report_assignfeedback_download
 * @copyright  2026 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

use core_table\local\filter\filter;
use core_table\local\filter\integer_filter;

$id = required_param('id', PARAM_INT); // Course ID.

if (!$course = $DB->get_record('course', array('id' => $id))) {
    $message = get_string('invalidcourse', 'report_assignfeedback_download');
    $level = core\output\notification::NOTIFY_ERROR;
    \core\notification::add($message, $level);
}

$url = new moodle_url('/report/assignfeedback_download/assignmentgeneralreports.php', array('id' => $id));
$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->add_body_class('report_assignfeedback_download');

require_login($course);
$context = context_course::instance($course->id);

// Check capabilities - handle context locking for finished courses.
if ($CFG->contextlocking) {
    $isallowed = false;

    if (is_siteadmin($USER)) {
        $isallowed = true;
    } else {
        $roles = get_user_roles($context, $USER->id, false);
        foreach ($roles as $role) {
            if ($role->shortname === 'editingteacher' || $role->shortname === 'teacher') {
                $isallowed = true;
                break;
            }
        }
    }

    if (!$isallowed) {
        throw new required_capability_exception($context, 'report/assignfeedback_download:grade', 'nopermissions', '');
    }
} else {
    require_capability('report/assignfeedback_download:grade', $context);
}

// Display the report page.
$PAGE->set_title(format_string($course->shortname, true, array('context' => $context)));
$PAGE->set_heading(format_string($course->fullname, true, array('context' => $context)));
echo $OUTPUT->header();

// Link back to the plugin index page.
$indexurl = new moodle_url('/report/assignfeedback_download/index.php', ['id' => $course->id]);
echo html_writer::div(
    html_writer::link($indexurl, get_string('returntocourse', 'report_assignfeedback_download'), ['class' => 'btn btn-primary mb-3']),
);

echo $OUTPUT->box_start();

// Create the dynamic table.
$studentstable = new \report_assignfeedback_download\table\students("assignfeedback-students-{$course->id}");

$filterset = new \report_assignfeedback_download\table\students_filterset();
$filterset->add_filter(new integer_filter('courseid', filter::JOINTYPE_DEFAULT, [(int)$course->id]));

// Render the filter UI.
$filterrenderable = new \report_assignfeedback_download\output\students_filter(
    $context,
    $studentstable->uniqueid,
    $course->id
);
$templatecontext = $filterrenderable->export_for_template($OUTPUT);
echo $OUTPUT->render_from_template('report_assignfeedback_download/studentsfilter', $templatecontext);

// Start the form wrapping the table and bulk actions.
echo '<form id="studentsform" method="post">';
echo '<div class="userlist">';
$studentstable->set_filterset($filterset);
$studentstable->out(20, true);
echo '</div>';

// Bulk actions below the table.
echo '<div class="mt-3">';
echo '<label for="formactionid">' . get_string('withselectedusers') . '</label> ';

// Build download options.
$downloadbaseurl = new moodle_url('/report/assignfeedback_download/download.php', ['id' => $course->id]);
$plugins = core_plugin_manager::instance()->get_plugins_of_type('dataformat');
$options = ['' => get_string('choosedots')];
foreach ($plugins as $plugin) {
    if ($plugin->is_enabled()) {
        $dlurl = new moodle_url($downloadbaseurl, ['dataformat' => $plugin->name]);
        $options[$dlurl->out(false)] = $plugin->displayname;
    }
}

echo html_writer::select($options, 'formaction', '', null, ['id' => 'formactionid']);
echo '</div>';
echo '</form>';

// Init bulk actions JS.
$PAGE->requires->js_call_amd('report_assignfeedback_download/bulk_actions', 'init', ['studentsform']);

echo $OUTPUT->box_end();

echo $OUTPUT->footer();
