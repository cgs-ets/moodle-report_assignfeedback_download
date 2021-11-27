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

$id          = optional_param('id', 0, PARAM_INT); // Course ID.
$option      = optional_param('operation', '', PARAM_TEXT);
$selectedusers = optional_param('selectedusers', '', PARAM_TEXT);

require_login();
admin_externalpage_setup('report_ibassessment', '', null, '', array('pagelayout' => 'report'));

$PAGE->add_body_class('report_ibassessment');
// Display the backup report
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('heading', 'report_ibassessment'));

if ($id == 0) {

    \core\notification::add(get_string('cantdisplayerror', 'report_ibassessment'), core\output\notification::NOTIFY_ERROR);

} else {

    echo $OUTPUT->box_start();

    $renderer = $PAGE->get_renderer('report_ibassessment');

    echo $renderer->render_ibassessmentreport($id);

    echo $OUTPUT->box_end();
}
echo $OUTPUT->footer();
