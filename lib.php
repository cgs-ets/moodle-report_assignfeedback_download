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
 * Defines the APIs used by assignfeedback_download reports
 *
 * @package    report
 * @subpackage assignfeedback_download
 * @copyright  2021 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define("SUPPORTED_ADVANCED_GRADING_METHODS", ['frubric', 'rubric', 'guide']);

const HEADINGSROW = 4;
const HEADINGTITLES = array('size' => 12, 'bold' => 1, 'text_wrap' => true, 'align' => 'centre');
const HEADINGSUBTITLES = array('bold' => 1, 'text_wrap' => true, 'align' => 'fill');

function report_assignfeedback_download_extend_navigation_course($navigation, $course, $context) {

    if (has_capability('moodle/site:viewuseridentity', $context)) {
        $url = new moodle_url('/report/assignfeedback_download/index.php', array('id' => $course->id, 'cmid' => $context->id));
        $navigation->add(get_string('pluginname', 'report_assignfeedback_download'), $url, navigation_node::COURSE_INDEX_PAGE, null, null, new pix_icon('i/report', ''));
    }
}

function report_assignfeedback_download_sort_by_firstname($param1, $param2) {
    return strcmp($param1->firstname, $param2->firstname);
}

function  report_assignfeedback_download_get_anonymous_submission_id($cm, $userid) {
    return \assign::get_uniqueid_for_user_static($cm->instance, $userid);
}

/**
 * Common method for all advanced grading methods
 * Create the first 3 columns of the Excel
 * CourseName
 * AssessmentName
 * Frubric name
 * Student
 *      First name, last name and username
 */
function report_assignfeedback_download_add_header(MoodleExcelWorkbook $workbook, MoodleExcelWorksheet $sheet, $coursename, $modname, $methodname) {
    // Course, assingment and Rubric definition section.
    $format = $workbook->add_format(array('size' => 18, 'bold' => 1));
    $sheet->write_string(0, 0, $coursename, $format);
    $sheet->set_row(0, 24, $format);
    $format = $workbook->add_format(array('size' => 16, 'bold' => 1));
    $sheet->write_string(1, 0, $modname, $format);
    $sheet->set_row(1, 21, $format);
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

    return $col;
}

/**
 * This method is common for rubric and marking guide
 * Create the rubrics columns
 *  * Criterion name
 *  * Grading info
 *
 */
function report_assignfeedback_download_add_advanced_method_and_grading_info_header(MoodleExcelWorkbook $workbook, MoodleExcelWorksheet $sheet, $data, $pos) {
    $format = $workbook->add_format(HEADINGTITLES);
    $format2 = $workbook->add_format(HEADINGSUBTITLES);
    // Set the Rubric headers.
    $firstel = reset($data);
    foreach ($data as $line) {
        if ($line->userid !== $firstel->userid) { // We have all the rubrics titles needed.
            break;
        }
        if (isset($line->description)) {
            $sheet->write_string(HEADINGSROW, $pos, $line->description);
        } else if (isset($line->shortname)) {
            $sheet->write_string(HEADINGSROW, $pos, $line->shortname);
        }
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
 * This method is common for rubric and marking guide
 * Get  details of the students selected.
 */
function report_assignfeedback_download_get_advanced_method_students_data($modcontext, $cm, $selectedusers) {
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
    }

    return $students;
}

/**
 * Get data for each student
 *
 * @param array $students
 * @param array $data array of objects
 * @return array
 */
function report_assignfeedback_process_data_rubric(array $students, array $data) {

    foreach ($students as $i => $student) {
        $student->data = array();
        foreach ($data as $key => $line) {
            if ($line->userid == $student->userid) {
                $student->data[$key] = $line;
                unset($data[$key]);
            }
        }
        if (count($student->data) == 0) {
            unset($students[$i]);
        }
    }

    return $students;
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
            if (isset($line->definition)) {
                $sheet->write_string($row, $col++, $line->definition);
            }
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




