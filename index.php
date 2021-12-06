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
$instaceids              = optional_param('instanceids', '', PARAM_TEXT);
$selectedusers           = optional_param('selectedusers', '', PARAM_TEXT);
$selectedaction          = optional_param('operation', '', PARAM_TEXT);

require_login();
admin_externalpage_setup('report_assignfeedback_download', '', null, '', array('pagelayout' => 'report'));

$PAGE->add_body_class('report_assignfeedback_download');


if ($id != 0) {
    $context = context_course::instance($id);
    require_capability('report/assignfeedback_download:grade', $context);
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
        }
    }

    // Display the backup report
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('heading', 'report_assignfeedback_download'));
        
 
    
    $aids = $manager->get_assesments_with_grades($id);
    $mform = new assignfeedback_download_select_form(null, ['id' => $id, 'cmid' => $cmid, 'aids' => $aids]);
    
    $assessmentids = '';
    $filter = false;
    $noasses = 0;
    
    //Form processing and displaying is done here
    if ($mform->is_cancelled()) {
        //Handle form cancel operation, if cancel button is present on form
    } else if ($data = $mform->get_data()) {
    
        //In this case you process validated data. $mform->get_data() returns data posted in form.
        $assessmentids = $data->assessments; // get the selected assessments.
        $filter = true;
    } else {
    
        if (count($aids) == 0) {
            $noasses = 1;
        }
    }
    echo $OUTPUT->box_start();
    $renderer = $PAGE->get_renderer('report_assignfeedback_download');
    
    if ($noasses) {
        echo $renderer->render_no_assessment_in_course();
    } else {
        $mform->display();
    
    
        $url =  $PAGE->url;
    
        echo $renderer->render_assignfeedback_download($id, $assessmentids, $url, $cmid, $filter);
    }
    
    echo $OUTPUT->box_end();
} else {
    \core\notification::add(get_string('cantdisplayerror', 'report_assignfeedback_download'), core\output\notification::NOTIFY_ERROR);

}


echo $OUTPUT->footer();
