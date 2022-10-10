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

use assign;
use context_module;
use MoodleExcelWorkbook;
use MoodleExcelWorksheet;
use AssignfedbackDownloaderExcelWorkbook;

const HEADINGSROW = 4;
const HEADINGTITLES = array('size' => 12, 'bold' => 1, 'text_wrap' => true, 'align' => 'centre');
const HEADINGSUBTITLES = array('bold' => 1, 'text_wrap' => true, 'align' => 'fill');

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

    $sheet    = $workbook->add_worksheet($cm->name);
    $rubric   = report_assignfeedback_get_rubric_data($cm);
    $first    = reset($rubric);
    $pos      = report_assignfeedback_download_add_header($workbook, $sheet, $course->fullname, $cm->name, $first->rubric);
    $pos      = report_assignfeedback_download_add_rubric_and_grading_info_header($workbook, $sheet, $rubric, $pos);
    $students = report_assignfeedback_download_get_students_data($modcontext, $cm, $selectedusers);
    // Get data for each student.
    $students = report_assignfeedback_process_data($students, $rubric);

    report_assignfeedback_set_students_rows($sheet, $students, $pos);

    $workbook->savetotempdir($tempdir);

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

    $data = $DB->get_records_sql($sql, $params);

    return $data;

}

/**
 * Get data for each student
 *
 * @param array $students
 * @param array $data array of objects
 * @return void
 */
function report_assignfeedback_process_data(array $students, array $data) {

    foreach ($students as $i => $student) {
        $student->data = array();
        foreach ($data as $key => $line) {
            if ($line->userid == $student->userid) {
                $student->data[$key] = $line;
                unset($data[$key]);
            }
        }
        // It might be the case where we  want the grades for different grading methods and this student was not graded with rubric.
        if (count($student->data) == 0) {
            unset($students[$i]);
        }
    }

    return $students;
}

/**
 * Create the first 3 columns of the Excel
 * CourseName
 * AssessmentName
 * Rubric name
 * Student
 *      First name, last name and username
 */
function report_assignfeedback_download_add_header(MoodleExcelWorkbook $workbook, MoodleExcelWorksheet $sheet, $coursename, $modname, $rubricname) {

    // Course, assingment and Rubric definition section.
    $format = $workbook->add_format(array('size' => 18, 'bold' => 1));
    $sheet->write_string(0, 0, $coursename, $format);
    $sheet->set_row(0, 24, $format);
    $format = $workbook->add_format(array('size' => 16, 'bold' => 1));
    $sheet->write_string(1, 0, $modname, $format);
    $sheet->set_row(1, 21, $format);
    $methodname = "Rubric : $rubricname";
    $sheet->write_string(2, 0, $methodname, $format);
    $sheet->set_row(2, 21, $format);

    // Column headers - two rows for grouping.
    $format = $workbook->add_format(HEADINGTITLES);
    $format2 = $workbook->add_format(HEADINGSUBTITLES);
    $sheet->write_string(HEADINGSROW, 0, get_string('student', 'report_assignfeedback_download'), $format);
    $sheet->merge_cells(HEADINGSROW, 0, HEADINGSROW, 2, $format); // Student section.
    $col = 0;
    $sheet->write_string(5, $col++, get_string('firstname', 'report_assignfeedback_download'), $format2);
    $sheet->write_string(5, $col++, get_string('lastname', 'report_assignfeedback_download'), $format2);
    $sheet->write_string(5, $col++, get_string('username', 'report_assignfeedback_download'), $format2);
    $sheet->set_column(0, $col, 10); // Set column widths to 10.
    return $col; // Add an empty column to make it better.
}

/**
 * Create the rubrics columns
 *  * Criterion name
 *  * Grading info
 */
function report_assignfeedback_download_add_rubric_and_grading_info_header(MoodleExcelWorkbook $workbook, MoodleExcelWorksheet $sheet, $data, $pos) {
    $format = $workbook->add_format(HEADINGTITLES);
    $format2 = $workbook->add_format(HEADINGSUBTITLES);
    // Set the Rubric headers.
    $firstel = reset($data);
    foreach ($data as $line) {
        if ($line->userid !== $firstel->userid) { // We have all the rubrics titles needed.
            break;
        }
        $sheet->write_string(HEADINGSROW, $pos, $line->description);
        $sheet->merge_cells(HEADINGSROW, $pos, HEADINGSROW, $pos + 2);
        $sheet->write_string(5, $pos, get_string('score_rubric', 'report_assignfeedback_download'), $format2);
        $sheet->set_column($pos, $pos++, 6); // Set column width to 6.
        $sheet->write_string(5, $pos++, get_string('definition', 'report_assignfeedback_download'), $format2);
        $sheet->write_string(5, $pos, get_string('feedback', 'report_assignfeedback_download'), $format2);
        $sheet->set_column($pos - 1, $pos++, 10); // Set column widths to 10.
        // Get the longes description to set the row height later.
    }

    // Grading info columns.
    $sheet->write_string(4, $pos, get_string('gradinginfo', 'report_assignfeedback_download'), $format);
    $sheet->write_string(5, $pos, get_string('gradedby', 'report_assignfeedback_download'), $format2);
    $sheet->set_column($pos, $pos++, 10); // Set column width to 10.
    $sheet->write_string(5, $pos, get_string('timegraded', 'report_assignfeedback_download'), $format2);
    $sheet->set_column($pos, $pos, 17.5); // Set column width to 17.5.
    $sheet->merge_cells(4, $pos - 1, 4, $pos);

    $sheet->set_row(4, 30, $format);
    $sheet->set_row(5, null, $format2);

    // Merge header cells.
    $sheet->merge_cells(0, 0, 0, $pos);
    $sheet->merge_cells(1, 0, 1, $pos);
    $sheet->merge_cells(2, 0, 2, $pos);

    return $pos;
}

/**
 *  Fill the workbook with the graded rubric for each student:
 *   Student column
 *      Firstname
 *      Lastname
 *      username
 *   Criterion column
 *      Score
 *      Level selected in the criterion criterion
 *      Feedback
 *  Grade info column
 *       Grader
 *       Time graded
 */
function report_assignfeedback_set_students_rows (MoodleExcelWorksheet $sheet, $students, $pos) {

    $row           = 5;
    $pos          -= 1;
    $format        = array('text_wrap' => true);

    foreach ($students as $student) {
        $col = 0;
        $row++;
        $sheet->write_string($row, $col++, $student->firstname, $format);
        $sheet->write_string($row, $col++, $student->lastname, $format);
        $sheet->write_string($row, $col++, $student->username, $format);
        foreach ($student->data as $line) {

            if (is_numeric($line->score)) {
                $sheet->write_number($row, $col++, $line->score);
            }

            $sheet->set_column($col, $col, 35);
            $sheet->write_string($row, $col++, $line->definition);
            $sheet->write_string($row, $col++, $line->remark);
            $sheet->set_row($row, 25, $format);

            if ($col === $pos) {
                $sheet->set_column($col, $col, 15);
                $sheet->write_string($row, $col++, $line->grader);
                $sheet->set_column($col, $col, 35);
                $sheet->write_string($row, $col, userdate($line->modified));
            }
        }

    }

}

/**
 * Get  details of the students selected.
 */
function report_assignfeedback_download_get_students_data($modcontext, $cm, $selectedusers) {
    global $DB;

    $sql = "SELECT stu.id AS userid, stu.idnumber AS idnumber,
            stu.firstname, stu.lastname, stu.username
            FROM {user} stu
            WHERE stu.id IN ($selectedusers)
            ORDER BY lastname ASC, firstname ASC, userid ASC";

    $result = $DB->get_records_sql($sql);
    $assign = new assign($modcontext, $cm, $cm->course);

    if ($assign->is_blind_marking()) {
        foreach ($result as &$r) {
            $r->firstname = '';
            $r->lastname = '';
            $r->student = get_string('participant', 'assign') .
             ' ' . \report_assignfeedback_download_get_anonymous_submission_id($cm, $r->userid);
        }
    }
    return $result;
}

