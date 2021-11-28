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
 * A report to display the outcome of scheduled ibassessment
 *
 * @package    report
 * @subpackage ibassessment
 * @copyright  2021 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once('lib.php');
require_once('ibassessment_form.php');

$id          = optional_param('id', 0, PARAM_INT); // Course ID.
$option      = optional_param('operation', '', PARAM_TEXT);
$selectedusers = optional_param('selectedusers', '', PARAM_TEXT);

require_login();
admin_externalpage_setup('report_ibassessment', '', null, '', array('pagelayout' => 'report'));

// download
if($selectedusers != '') {
    $selectedusers = explode(',', $selectedusers);
    // var_dump($selectedusers); exit;
    // download_submissions($selectedusers, $id);
}

$PAGE->add_body_class('report_ibassessment');
// Display the backup report
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('heading', 'report_ibassessment'));

$mform = new ibassessment_form(null, ['id'=>$id]);

//Form processing and displaying is done here
if ($mform->is_cancelled()) {
    //Handle form cancel operation, if cancel button is present on form
} else if ($fromform = $mform->get_data()) {
  //In this case you process validated data. $mform->get_data() returns data posted in form.
} else {
  // this branch is executed if the form is submitted but the data doesn't validate and the form should be redisplayed
  // or on the first display of the form.

  //Set default data (if any)
 // $mform->set_data($toform);
  //displays the form
  $mform->display();
}

if ($id == 0) {

    \core\notification::add(get_string('cantdisplayerror', 'report_ibassessment'), core\output\notification::NOTIFY_ERROR);

} else {

    echo $OUTPUT->box_start();

    $renderer = $PAGE->get_renderer('report_ibassessment');

    echo $renderer->render_ibassessmentreport($id);

    echo $OUTPUT->box_end();
}
echo $OUTPUT->footer();
