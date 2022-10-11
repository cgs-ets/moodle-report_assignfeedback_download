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
 * Exports an Excel spreadsheet of the component grades in a frubric-graded assignment.
 *
 * @package    report
 * @subpackage assignfeedback_download
 * @copyright  2022 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_assignfeedback_download\frubric;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/lib/excellib.class.php');
require($CFG->dirroot . '/report/assignfeedback_download/classes/excelmanager.php');

use context_module;
use MoodleExcelWorkbook;
use MoodleExcelWorksheet;
use stdClass;
use AssignfedbackDownloaderExcelWorkbook;

function report_assignfeedback_download_setup_frubric_workbook($id, $modid, $areaid, $selectedusers, $maxscore, $tempdir) {
    global $DB;

    $course         = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);
    $modinfo        = get_fast_modinfo($course->id);
    $cm             = $modinfo->get_cm($modid);
    $modcontext     = context_module::instance($cm->id);

    require_capability('mod/assign:grade', $modcontext);

    $filename       = $course->shortname . ' - ' . $cm->name . '.xls';
    $workbook       = new AssignfedbackDownloaderExcelWorkbook("-");

    $workbook->send($filename);

    $sheet          = $workbook->add_worksheet($cm->name);
    $frubric        = report_assignfeedback_download_decode_level_filling($areaid);
    $methodname     = "Frubric : $frubric->name";
    $pos            = report_assignfeedback_download_add_header($workbook, $sheet, $course->fullname, $cm->name, $methodname);
    $pos            = report_assignfeedback_download_add_frubric_and_grading_info_header($workbook, $sheet, $frubric, $pos);
    $data           = report_assignfeedback_download_students_frubric_data($modid, $selectedusers, $cm, $areaid);

    report_assignfeedback_set_students_rows($sheet, $data, $maxscore);

    $workbook->savetotempdir($tempdir);

}

/**
 * Get the frubric definition.
 * Frubic is made of: Criterion, levels and descritors.
 * It defers from rubric because a level can have 1..* descriptors.
 */
function report_assignfeedback_download_decode_level_filling($areaid) {
    global $DB;

    $sql = "SELECT gd.*, rc.id AS rcid, rc.sortorder AS rcsortorder,
            rc.description AS rcdescription,
            rc.descriptionformat AS rcdescriptionformat,
            rc.criteriajson AS criteriajson, rl.id AS rlid,
            rl.score AS rlscore,
            rl.definition AS rldefinition,
            rl.definitionformat AS rldefinitionformat
            FROM {grading_definitions} gd
            LEFT JOIN {gradingform_frubric_criteria} rc ON (rc.definitionid = gd.id)
            LEFT JOIN {gradingform_frubric_levels} rl ON (rl.criterionid = rc.id)
            WHERE gd.areaid = :areaid AND gd.method = :method
            ORDER BY rl.id";
            $params = array('areaid' => $areaid, 'method' => 'frubric');

    $rs         = $DB->get_recordset_sql($sql, $params);
    $definition = new stdClass();
    $definition->frubric_criteria = array();

    foreach ($rs as $record) {
        // Common definition.
        foreach (array('id', 'name') as $fieldname) {
            $definition->$fieldname = $record->$fieldname;
        }

        foreach (array('id', 'sortorder', 'description', 'descriptionformat') as $fieldname) {
            $definition->frubric_criteria[$record->rcid][$fieldname] = $record->{'rc' . $fieldname};
        }

        if (empty($definition->frubric_criteria[$record->rcid])) {
            $definition->frubric_criteria[$record->rcid]['levels'] = array();
        }
        // Criterion data.
        foreach (array('id', 'score', 'definition', 'definitionformat') as $fieldname) {
            $value = $record->{'rl' . $fieldname};
            $definition->frubric_criteria[$record->rcid]['levels'][$record->rlid][$fieldname] = $value;

            // Level data.
            if ($fieldname == 'definition') { // Get the descriptors for the level.
                $descrip = json_decode($value);
                if (isset($descrip->descriptors)) {
                    $definition->frubric_criteria[$record->rcid]['levels'][$record->rlid]['descriptors'] = $descrip->descriptors;
                }
            }
        }

    }

    return $definition;

}

/**
 * Creates:
 * Frubric columns
 *  Criterion descriptor
 *      Levels this descriptor has
 * Grading info column
 *  Grader
 *
 */
function report_assignfeedback_download_add_frubric_and_grading_info_header(MoodleExcelWorkbook $workbook, MoodleExcelWorksheet $sheet, $frubric, $pos) {
    $format     = $workbook->add_format(HEADINGTITLES);
    $format2    = $workbook->add_format(HEADINGSUBTITLES);

    // Set the Frubric headers.
    foreach ($frubric->frubric_criteria as $cid => $criteria) {

        foreach ($criteria as $title => $criterion) {
            if ($title == 'description') {
                $sheet->write_string(HEADINGSROW, $pos, $criterion, $format);
                $thiscriterionlevel = $frubric->frubric_criteria[$cid]['levels'];
                $descriptortext = report_assignfeedback_download_get_descriptors_and_titles($thiscriterionlevel);

                $lastcol = (count($descriptortext) + $pos) - 1;
                $sheet->merge_cells(HEADINGSROW, $pos, HEADINGSROW, $lastcol, $format);
                $sheet->set_column($pos, $lastcol, 20); // Set column width to 20.
                // Set the descriptors for each criterion.
                foreach ($descriptortext as $desctext) {
                    $sheet->write_string(5, $pos++, $desctext, $format2);
                }
                $sheet->set_column($lastcol - 1, $lastcol, 20); // Set column widths to 20.
            }

        }
    }
    $sheet->set_row(4, 30, $format);
    // Add the grading info header.
    $sheet->write_string(HEADINGSROW, $pos, get_string('gradinginfo', 'report_assignfeedback_download'), $format);
    $sheet->write_string(5, $pos++, get_string('criteriatotal', 'report_assignfeedback_download'), $format2);
    $sheet->write_string(5, $pos++, get_string('finalgrade', 'report_assignfeedback_download'), $format2);
    $sheet->write_string(5, $pos++, get_string('gradedby', 'report_assignfeedback_download'), $format2);
    $sheet->write_string(5, $pos, get_string('timegraded', 'report_assignfeedback_download'), $format2);
    $sheet->set_column($pos - 1, $pos, 20); // Set column widths to 10.
    $sheet->merge_cells(HEADINGSROW, $pos - 3 , HEADINGSROW, $pos - 1, $format);

    return $pos;
}

/**
 * Helper function to get the levels descritors
 */
function report_assignfeedback_download_get_descriptors_and_titles($levels) {
    $descriptortext = [];

    foreach ($levels as $level) {
        foreach ($level['descriptors'] as $descriptor) {

            $descriptortext[] = $descriptor->descText;

        }
    }

    // Add the Feedback  and score text.
    $descriptortext[] = get_string('feedback', 'report_assignfeedback_download');
    $descriptortext[] = get_string('score', 'report_assignfeedback_download');

    return $descriptortext;
}

/**
 * Get the students grading details
 *    descritors checked, level sscore, criteria total score, final grade
 */
function report_assignfeedback_download_students_frubric_data($cmid, $selectedusers, $cm, $areaid) {
    global $DB, $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $sql = "SELECT grf.id AS grfid, grl.id AS levelid, grf.leveljson  AS selections,
            grf.remark, grf.levelscore AS score, grf.criterionid,
            rubm.username AS grader, stu.id AS userid,
            stu.idnumber AS idnumber, stu.firstname,stu.lastname,
            stu.username, gin.timemodified AS modified,
            crs.id AS courseid, asg.id AS itemid, asg.blindmarking
            FROM {course} crs
            JOIN {course_modules} cm ON crs.id = cm.course
            JOIN {assign} asg ON asg.id = cm.instance
            JOIN {context} c ON cm.id = c.instanceid
            JOIN {grading_areas}  ga ON c.id=ga.contextid
            JOIN {grading_definitions} gd ON ga.id = gd.areaid
            JOIN {gradingform_frubric_criteria} grc ON (grc.definitionid = gd.id)
            JOIN {gradingform_frubric_levels} grl ON (grl.criterionid = grc.id)
            JOIN {grading_instances} gin ON gin.definitionid = gd.id
            JOIN {assign_grades} ag ON ag.id = gin.itemid
            JOIN {user} stu ON stu.id = ag.userid
            JOIN {user} rubm ON rubm.id = gin.raterid
            JOIN {gradingform_frubric_fillings} grf ON (grf.instanceid = gin.id)
            AND (grf.criterionid = grc.id)  -- AND (grf.levelid = grl.id)
            WHERE cm.id = :cmid AND gin.status = 1 AND stu.id in ($selectedusers)
            ORDER BY lastname ASC, firstname ASC, userid ASC, grc.sortorder ASC";

    $params     = ['cmid' => $cmid, 'cmid2' => $cmid];
    $results    = $DB->get_recordset_sql($sql, $params);
    $data       = [];
    // When no descriptor is selected, the levelid is missing in the gradingform_frubric_fillings table.
    // This is not a bug in frubric plugin.
    // To avoid PHP notice unique id, I used get_recordset_sql this brigs all records.
    // Filter here the duplicate.
    $trackfill  = [];
    foreach ($results as $result) {

        if (in_array($result->grfid, $trackfill)) {
            continue;
        } else {
            $trackfill[] = $result->grfid;
        }
        if (!isset($data[$result->userid])) {
            $filling = new stdClass();
            $filling->firstname     = '';
            $filling->lastname      = '';
            if ($result->blindmarking == 1) {
                $filling->username = report_assignfeedback_download_get_anonymous_submission_id($cm, $result->userid);
            } else {

                $filling->firstname     = $result->firstname;
                $filling->lastname      = $result->lastname;
                $filling->username      = $result->username;
            }

            $filling->grader        = $result->grader;
            $filling->modified      = userdate($result->modified);

            $gradinginfo = grade_get_grades(
                $result->courseid,
                'mod',
                'assign',
                $result->itemid,
                $result->userid
            );

            if (isset($gradinginfo->items[0])) {
                $gradingitem = $gradinginfo->items[0];
                $gradebookgrade = $gradingitem->grades[$result->userid];
                $gradebookgrade->str_long_grade;
                $filling->finalgrade = $gradebookgrade->str_long_grade;
                // $filling->modified      = $result->modified;
            }

        } else {
            $filling = $data[$result->userid];
        }

        $level                       = new stdClass();
        $level->id                   = $result->levelid;
        $level->feedback             = $result->remark;
        $level->score                = $result->score;
        $level->descriptors          = report_assignfeedback_download_decode_level_descriptors($result->selections);
        $filling->levels[$level->id] = $level;
        $data[$result->userid]       = $filling;
    }

    $results->close(); // Remember to close the set.
    return $data;
}

/**
 * Helper function to set a ✔ to the checked levels
 */
function report_assignfeedback_download_decode_level_descriptors($selections) {
    $selections = json_decode($selections);
    $decoded    = [];
    foreach ($selections as $levelid => $filling) {
        $definition = json_decode($filling->definition);

        foreach ($definition->descriptors as $descriptor) {

            if ($descriptor->checked == 1) {
                $descriptor->checked = '✔';
            } else {
                $descriptor->checked = '';
            }

            $decoded[] = $descriptor->checked;
        }

    }
    return $decoded;

}

/**
 * Fill the rows with the student info
 */
function report_assignfeedback_set_students_rows (MoodleExcelWorksheet $sheet, $students, $maxscore) {
    $row = 5;
    $format = array('text_wrap' => true);
    foreach ($students as $student) {
        $col = 0;
        $row++;
        $total = 0;
        $sheet->write_string($row, $col++, $student->firstname, $format);
        $sheet->write_string($row, $col++, $student->lastname, $format);
        $sheet->write_string($row, $col++, $student->username, $format);

        foreach ($student->levels as $level) {
            foreach ($level->descriptors as $descriptor) {
                $sheet->write_string($row, $col++, $descriptor, array('align' => 'centre'));
            }
            $sheet->write_string($row, $col++, $level->feedback, $format);
            $sheet->write_string($row, $col++, $level->score);
            $total += $level->score;

        }
        $total = "$total / $maxscore";
        $sheet->set_column($col, $col, 15, $format);
        $sheet->write_string($row, $col++, $total, $format);
        $sheet->set_column($col, $col, 25, $format);
        $sheet->write_string($row, $col++, $student->finalgrade, $format);
        $sheet->write_string($row, $col++, $student->grader, $format);
        $sheet->write_string($row, $col++, $student->modified, $format);

    }

}

