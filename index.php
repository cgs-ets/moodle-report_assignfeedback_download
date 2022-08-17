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
 * A report to display  assignments files and allow to  download
 *
 * @package    report
 * @subpackage assignfeedback_download
 * @copyright  2021 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once('lib.php');
require_once('assignfeedback_download_select_form.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');


$id                      = optional_param('id', 0, PARAM_INT); // Course ID.
$cmid                    = optional_param('cmid', 0, PARAM_INT); // Course module ID.
$option                  = optional_param('operation', '', PARAM_TEXT);
$itemids                 = optional_param('itemids', '', PARAM_TEXT);
$cmids                   = optional_param('cmids', '', PARAM_TEXT);
$instaceids              = optional_param('instanceids', '', PARAM_TEXT);
$selectedusers           = optional_param('selectedusers', '', PARAM_TEXT);
$selectedaction          = optional_param('operation', '', PARAM_TEXT);
$frubricselection        = optional_param('frubricdetails', '', PARAM_TEXT);
$manager = new report_assignfeedback_download\reportmanager();

// download
if ($selectedusers != '') {
    $selectedusers = explode(',', $selectedusers);

    switch ($selectedaction) {
        case 'dldsubmission':
            $manager->download_submission_files($instaceids, $id, $selectedusers);
            break;
        case 'dldanotated':
            $nofilestozip = $manager->download_anotatepdf_files($itemids, $id);
            break;
        case 'dldfeedbackf':
            $manager->download_feedback_files($itemids, $id);
            break;
        case 'dldrubric':
            $manager->download_rubric($itemids, $cmids, $instaceids, $id, $frubricselection);
            break;
        case 'dldall':
            $manager->download_all_files($itemids, $id);
            break;
    }
}

$url = new moodle_url('/report/assignfeedback_download/index.php', array('id' => $id, 'cmid' => $cmid));
$PAGE->set_url($url);
$PAGE->set_pagelayout('admin');
$PAGE->add_body_class('report_assignfeedback_download');

if (!$course = $DB->get_record('course', array('id' => $id))) {
    print_error('invalidcourse');
}

require_login($course);
$context = context_course::instance($course->id);
require_capability('report/assignfeedback_download:grade', $context);
// Display the backup report
$PAGE->set_title(format_string($course->shortname, true, array('context' => $context)));
$PAGE->set_heading(format_string($course->fullname, true, array('context' => $context)));
echo $OUTPUT->header();

$aids = $manager->get_submitted_assessments($id);
$mform = new assignfeedback_download_select_form(null, ['id' => $id, 'cmid' => $cmid, 'aids' => $aids]);
$assessmentids = '';
$filter = false;
$noasses = 0;
//Form processing and displaying is done here
if ($mform->is_cancelled()) {
    //Handle form cancel operation, if cancel button is present on form
} else if ($data = $mform->get_data()) {

    //In this case you process validated data. $mform->get_data() returns data posted in form.
    $assessmentids = $data->assessments; // Get the selected assessments.
    $filter = true;
} else {

    if (count($aids) == 0) {
        $noasses = 1;
    }
}

if ($id == 0 || $id == 1) {  // $id = 1 is the main page.
    \core\notification::add(get_string('cantdisplayerror', 'report_assignfeedback_download'), core\output\notification::NOTIFY_ERROR);
} else {

    echo $OUTPUT->box_start();
    $renderer = $PAGE->get_renderer('report_assignfeedback_download');

    if ($noasses) {
        echo $renderer->render_no_assessment_in_course();
    } else {
        $mform->display();
    }

    // Only if the user clicked filter display this.
    if ($filter) {
        $url =  $PAGE->url;
        $coursename = $DB->get_field('course', 'fullname', ['id' => $id], $strictness = IGNORE_MISSING);
        echo $renderer->render_assignfeedback_download($id, $assessmentids, $url, $cmid, $filter, $coursename);
    }

    echo $OUTPUT->box_end();
}



echo $OUTPUT->footer();
