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
 *  Exports an Excel spreadsheet of the component grades in a Marking Guide-graded assignment.
 *
 * Adapted from
 * @package    report_componentgrades
 * @copyright  2014 Paul Nicholls
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @package    report
 * @subpackage assignfeedback_download
 * @copyright  2022 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_assignfeedback_download\markguide;

use AssignfedbackDownloaderExcelWorkbook;
use context_module;
use MoodleExcelWorkbook;
use MoodleExcelWorksheet;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/lib/excellib.class.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

function report_assignfeedback_download_setup_marking_guide_workbook($id, $modid, $selectedusers, $tempdir) {
    global $DB;

    $course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);
    $modinfo = get_fast_modinfo($course->id);
    $cm = $modinfo->get_cm($modid);
    $modcontext = context_module::instance($cm->id);
    require_capability('mod/assign:grade', $modcontext);

    $filename = $course->shortname . ' - ' . $cm->name . '.xls';
    $workbook = new AssignfedbackDownloaderExcelWorkbook("-");

    $workbook->send($filename);

    $sheet      = $workbook->add_worksheet($cm->name);
    $mguide      = report_assignfeedback_get_marking_guide_data($cm);
    $first      = reset($mguide);
    $methodname = "Marking guide: $first->guide";
    $pos        = report_assignfeedback_download_add_header($workbook, $sheet, $course->fullname, $cm->name, $methodname);
    $pos        = report_assignfeedback_download_add_advanced_method_and_grading_info_header($workbook, $sheet, $mguide, $pos);
    $students   = report_assignfeedback_download_get_advanced_method_students_data($modcontext, $cm, $selectedusers);
    // Get data for each student.
    $students   = report_assignfeedback_process_data($students, $mguide);

    report_assignfeedback_set_students_rows($sheet, $students, $pos);

    $workbook->savetotempdir($tempdir);

}

function report_assignfeedback_get_marking_guide_data($cm) {
    global $DB;

    $sql = "SELECT ggf.id AS ggfid, crs.shortname AS course, asg.name AS assignment,
                  gd.name AS guide, ggc.shortname, ggf.score, ggf.remark, ggf.criterionid,
                  rubm.username AS grader, stu.id AS userid, stu.idnumber AS idnumber,
                  ggc.description AS definition, stu.firstname, stu.lastname, stu.username AS student,
                  gin.timemodified AS modified, asg.markingworkflow
            FROM {course} crs
            JOIN {course_modules} cm ON crs.id = cm.course
            JOIN {assign} asg ON asg.id = cm.instance
            JOIN {context} c ON cm.id = c.instanceid
            JOIN {grading_areas} ga ON c.id=ga.contextid
            JOIN {grading_definitions} gd ON ga.id = gd.areaid
            JOIN {gradingform_guide_criteria} ggc ON (ggc.definitionid = gd.id)
            JOIN {grading_instances} gin ON gin.definitionid = gd.id
            JOIN {assign_grades} ag ON ag.id = gin.itemid
            JOIN {user} stu ON stu.id = ag.userid
            JOIN {user} rubm ON rubm.id = gin.raterid
            JOIN {gradingform_guide_fillings} ggf ON (ggf.instanceid = gin.id)
            AND (ggf.criterionid = ggc.id)
            WHERE cm.id = ? AND gin.status = 1
            ORDER BY lastname ASC, firstname ASC, userid ASC, ggc.sortorder ASC,
            ggc.shortname ASC";

    $params = array($cm->id);
    $data   = $DB->get_records_sql($sql, $params);

    return $data;
}



