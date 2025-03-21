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
 * Exports an Excel spreadsheet of the component grades in a rubric-graded assignment.
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

namespace report_assignfeedback_download\rubric;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/lib/excellib.class.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

use context_module;
use AssignfedbackDownloaderExcelWorkbook;

function report_assignfeedback_download_setup_rubric_workbook($id, $modid, $selectedusers, $tempdir) {
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
    $rubric     = report_assignfeedback_get_rubric_data($cm);
    $first      = reset($rubric);
    $methodname = "Rubric : $first->rubric";
    $pos        = report_assignfeedback_download_add_header($workbook, $sheet, $course->fullname, $cm->name, $methodname);
    $pos        = report_assignfeedback_download_add_advanced_method_and_grading_info_header($workbook, $sheet, $rubric, $pos);
    $students   = report_assignfeedback_download_get_advanced_method_students_data($modcontext, $cm, $selectedusers);
    // Get data for each student.
    $students   = report_assignfeedback_process_data_rubric($students, $rubric);

    if (count($students) > 0 ) { // Only generate the files if there is at least a student fully graded.
        report_assignfeedback_set_students_rows($sheet, $students, $selectedusers, $pos);
        $workbook->savetotempdir($tempdir);
    }

}

function report_assignfeedback_get_rubric_data($cm) {

    global $DB;
    $sql = "SELECT grf.id AS grfid, crs.shortname AS course, asg.name AS assignment, gd.name AS rubric,
            grc.description, grl.definition, grl.score, grf.remark, grf.criterionid,
            rubm.username AS grader, stu.id AS userid, stu.idnumber AS idnumber, stu.firstname,
            stu.lastname, stu.username AS student, gin.timemodified AS modified
            FROM {course} crs
            JOIN {course_modules} cm ON crs.id = cm.course
            JOIN {assign} asg ON asg.id = cm.instance
            JOIN {context} c ON cm.id = c.instanceid
            JOIN {grading_areas}  ga ON c.id=ga.contextid
            JOIN {grading_definitions} gd ON ga.id = gd.areaid
            JOIN {gradingform_rubric_criteria} grc ON (grc.definitionid = gd.id)
            JOIN {gradingform_rubric_levels} grl ON (grl.criterionid = grc.id)
            JOIN {grading_instances} gin ON gin.definitionid = gd.id
            JOIN {assign_grades} ag ON ag.id = gin.itemid
            JOIN {user} stu ON stu.id = ag.userid
            JOIN {user} rubm ON rubm.id = gin.raterid
            JOIN {gradingform_rubric_fillings} grf ON (grf.instanceid = gin.id)
            AND (grf.criterionid = grc.id) AND (grf.levelid = grl.id)
            WHERE cm.id = ? AND gin.status = 1
            ORDER BY lastname ASC, firstname ASC, userid ASC, grc.sortorder ASC,
            grc.description ASC";

    $params = array($cm->id);
    $data   = $DB->get_records_sql($sql, $params);

    return $data;

}
