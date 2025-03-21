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
 * Exports an Excel spreadsheet  with reflection submissions
 *
 * @package    report
 * @subpackage assignfeedback_download
 * @copyright  2023 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_assignfeedback_download\onlinetext;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/lib/excellib.class.php');
require_once($CFG->dirroot . '/report/assignfeedback_download/classes/excelmanager.php');

use context_module;
use MoodleExcelWorkbook;
use MoodleExcelWorksheet;
use AssignfedbackDownloaderExcelWorkbook;
use report_assignfeedback_download\reportmanager;

/**
 * $id: Course id.
 * $modid: Module Id.
 */
function report_assignfeedback_download_setup_onlinetext_workbook($id, $modid, $selectedusers, $tempdir) {
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
    $methodname     = get_string('onlinesubmission', 'report_assignfeedback_download');
    $pos            = report_assignfeedback_download_add_header($workbook, $sheet, $course->fullname, $cm->name, $methodname);
    $pos            = report_assignfeedback_download_add_onlinetext_info_header($workbook, $sheet, $pos);

    $manager = new reportmanager();
    $texts = $manager->get_submission_onlinetext($cm->instance, $selectedusers);
    $texts = $texts == null ? [] : $texts;

    if (count($texts) > 0 ) {
        report_assignfeedback_set_students_rows($sheet, $texts);
        $workbook->savetotempdir($tempdir);
    }

}

/**
 * Creates the header and subheader
 *  Submission
 *      Online text
 */
function report_assignfeedback_download_add_onlinetext_info_header(MoodleExcelWorkbook $workbook,
                                                                   MoodleExcelWorksheet $sheet,
                                                                   $pos) {
    $format     = $workbook->add_format(HEADINGTITLES);
    $format2    = $workbook->add_format(HEADINGSUBTITLES);

    $sheet->set_row(4, 30, $format);
    // Add the grading info header.
    $sheet->write_string(HEADINGSROW, $pos, get_string('submission', 'report_assignfeedback_download'), $format);
    $sheet->write_string(5, $pos++, get_string('onlinetext', 'report_assignfeedback_download'), $format2);
    $sheet->set_column($pos - 1, $pos, 20); // Set column widths to 10.

    return $pos;
}

/**
 * Fill the rows with the student info
 */
function report_assignfeedback_set_students_rows (MoodleExcelWorksheet $sheet, $onlinetext) {
    $row = 5;
    $format = array('text_wrap' => true);
    foreach ($onlinetext as $text) {
        $col = 0;
        $row++;
        $sheet->write_string($row, $col++, $text->firstname, $format);
        $sheet->write_string($row, $col++, $text->lastname, $format);
        $sheet->write_string($row, $col++, $text->username, $format);
        $sheet->write_string($row, $col++, $text->studiescode, $format);
        $sheet->write_string($row, $col++, $text->txt, $format);

    }

}


