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
 * Defines the APIs used by ibassessment reports
 *
 * @package    report
 * @subpackage ibassessment
 * @copyright  2021 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die;

function report_ibassessment_extend_navigation_course($navigation, $course, $context) {
    //has_capability('moodle/site:viewuseridentity', $context)
    if (has_capability('moodle/site:viewuseridentity', $context)) {
        $url = new moodle_url('/report/ibassessment/index.php', array('id' => $course->id));
        $navigation->add(get_string('pluginname', 'report_ibassessment'), $url, navigation_node::TYPE_SETTING, null, null, new pix_icon('i/report', ''));
    }
}

function show_only_active_users($context) {
    global $CFG;
    $showonlyactiveenrol = true;
    if ($showonlyactiveenrol) {
        $defaultgradeshowactiveenrol = !empty($CFG->grade_report_showonlyactiveenrol);
        $showonlyactiveenrol = get_user_preferences('grade_report_showonlyactiveenrol', $defaultgradeshowactiveenrol);

        if (!is_null($context)) {
            $showonlyactiveenrol = $showonlyactiveenrol ||
                !has_capability('moodle/course:viewsuspendedusers', $context);
        }
    }
    return $showonlyactiveenrol;
}

function get_user_submission($userid, $assignmentid) {
    global $DB;
    $params = array('assignment' => $assignmentid, 'userid' => $userid);
    $submission = null;
    $submissions = $DB->get_records('assign_submission', $params, 'attemptnumber DESC', '*', 0, 1);
    print_object($assignmentid); exit;
    if ($submissions) {
        $submission = reset($submissions);
    }

    if ($submission) {
        return $submission;
    }
    return false;
}

function get_assessment_files($userid) {
    global $DB;

    $sql = 'SELECT * FROM {assign} AS assign
            JOIN {assign_submission} AS asub
            ON asub.assignment = assign.id
            JOIN {files} as f  ON f.itemid = asub.id
            WHERE f.userid = ? AND f.filearea = ? AND f.filename <> ?
            ORDER BY asub.attemptnumber DESC, f.filename ASC';
    $params_array = ['userid' => $userid, 'filearea' => 'submission_files', 'filename' => '.'];
    $result = array_values($DB->get_records_sql($sql, $params_array));
    return $result;
   
}
/**
 * Download a zip file of all assignment submissions.
 *
 * @param array $userids Array of user ids to download assignment submissions in a zip file
 * @return string - If an error occurs, this will contain the error page.
 */
// function download_submissions($userids = false, $courseid) {
//     global $CFG, $DB;
//     $context = context_course::instance($courseid);
//     // More efficient to load this here.
//     require_once($CFG->libdir . '/filelib.php');
//     // Increase the server timeout to handle the creation and sending of large zip files.
//     core_php_time_limit::raise();

//     // Load all users with submit.
//     $students = get_enrolled_users(
//         $context,
//         "mod/assign:submit",
//         null,
//         'u.*',
//         null,
//         null,
//         null,
//         show_only_active_users($context)
//     );

//     // print_object(($students)); exit;
//     // Build a list of files to zip.
//     $filesforzipping = array();
//     $fs = get_file_storage();

//     // Construct the zip file name.
//     $filename = clean_filename('TESTDOWNLOAD.zip');

//     // Get all the files for each student.
//     foreach ($students as $student) {
//         $userid = $student->id;
//         // Download all assigments submission or only selected users.
//         if ($userids and !in_array($userid, $userids)) {
//             continue;
//         }

//         // $submission = get_user_submission($userid, false);

//         // if ($submission) {
//         //     $downloadasfolders = get_user_preferences('assign_downloadasfolders', 1);
//         // }
//         // $submission->exportfullpath = true;
//         $pluginfiles =  array_values(get_assessment_files($userid));

//         foreach ($pluginfiles as $zipfilepath => $file) {
//         //   print_object($file ); exit;
//             //$subtype = $plugin->get_subtype();
//             $type = $file->mimetype;
//             $zipfilename = basename($zipfilepath);
//             $prefixedfilename = clean_filename($student->firstname . ' ' .$student->lastname .
//                 ' ' .               
//                 $file->name .
//                 ' ');
//             //if ($type == 'file') {
//                 $pathfilename = $prefixedfilename . $file->filepath . $zipfilename;
//             // } //else if ($type == 'onlinetext') {
//             //     $pathfilename = $prefixedfilename . '/' . $zipfilename;
//             // } else {
//             //     $pathfilename = $prefixedfilename . '/' . $zipfilename;
//             // }
//             $pathfilename = clean_param($pathfilename, PARAM_PATH);
//             $filesforzipping[$pathfilename] =  $file;
//         }

//         // Close the session.
//         \core\session\manager::write_close();

//         $zipwriter = \core_files\archive_writer::get_stream_writer($filename, \core_files\archive_writer::ZIP_WRITER);

//         // Stream the files into the zip.
//         foreach ($filesforzipping as $pathinzip => $storedfile) {
//             $zipwriter->add_file_from_stored_file($pathinzip, $storedfile);
//         }

//         // Finish the archive.
//         $zipwriter->finish();
//         exit();
//     }
// }
