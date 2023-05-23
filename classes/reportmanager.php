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
 *
 * @package    report_assignfeedback_download
 * @copyright  2021 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_assignfeedback_download;

use moodle_url;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use zip_packer;

use function report_assignfeedback_download\frubric\report_assignfeedback_download_setup_frubric_workbook;
use function report_assignfeedback_download\markguide\report_assignfeedback_download_setup_marking_guide_workbook;
use function report_assignfeedback_download\onlinetext\report_assignfeedback_download_setup_onlinetext_workbook;
use function report_assignfeedback_download\pdflib\report_assignfeedback_download_create_frubric_pdf;
use function report_assignfeedback_download\pdflib\report_assignfeedback_download_generatefeedbackpdf;
use function report_assignfeedback_download\reflection\report_assignfeedback_download_setup_reflection_workbook;
use function report_assignfeedback_download\rubric\report_assignfeedback_download_setup_rubric_workbook;

defined('MOODLE_INTERNAL') || die();

require($CFG->dirroot . '/report/assignfeedback_download/classes/pdflib.php');
require($CFG->dirroot . '/report/assignfeedback_download/classes/frubric.php');
require($CFG->dirroot . '/report/assignfeedback_download/classes/rubric.php');
require($CFG->dirroot . '/report/assignfeedback_download/classes/markguide.php');
require($CFG->dirroot . '/report/assignfeedback_download/classes/reflection.php');
require($CFG->dirroot . '/report/assignfeedback_download/classes/onlinetext.php');

require_once($CFG->libdir . '/filestorage/zip_archive.php');
require_once($CFG->dirroot . '/report/assignfeedback_download/vendor/autoload.php');

/**
 * @package       report
 * @subpackage    assignfeedback_download
 * @copyright     2021 Veronica Bermegui
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reportmanager {

    /**
     * @courseid course id
     * returns assessments that have at least one graded student.
     */
    public function get_assesments_with_grades($courseid) {
        global $DB;

        $sql = "SELECT grades.id AS gradeid,
                       grades.assignment AS assignmentid,
                       assign.name AS 'assignmentname'
                FROM {assign_grades} grades
                JOIN {assign} assign ON grades.assignment = assign.id
                WHERE assign.course = ?
                ORDER BY assign.name";

        $paramsarray = ['course' => $courseid];

        $results = $DB->get_records_sql($sql, $paramsarray);

        return $results;
    }

    /**
     *
     * @courseid course id
     * return  array assessments that have submissions. it doesnt matter if they are graded.
     */
    public function get_submitted_assessments($courseid) {
        global $DB;

        $sql = "SELECT distinct assign.id as assignmentid,
                assign.name AS 'assignmentname'
                FROM {assign} assign
                JOIN {assign_submission} asub
                ON assign.id = asub.assignment
                WHERE assign.course = ? ";

        $params = ['course' => $courseid];

        $results = $DB->get_records_sql($sql, $params);

        return $results;
    }

    /**
     * Get Assessments that are not graded.
     * @assessmentids assesments ids
     * @return array
     */
    private function get_assessments_by_course_2($assessmentids) {
        global $DB;

        $sql = "SELECT asub.id, assign.id as assignmentid,
                assign.name AS 'assignmentname',
                u.id as userid, u.firstname, u.lastname
                FROM {assign} assign
                JOIN {assign_submission} asub
                ON assign.id = asub.assignment
                JOIN {user} u ON u.id = asub.userid
                WHERE assign.id in ($assessmentids)";

        $results = $DB->get_records_sql($sql);
        return $results;
    }

    /**
     *
     * @assessmentids assesments ids
     * @return array
     */
    public function get_assessments($assessmentids) {
        // Assessments not graded.
        $notgraded       = $this->get_assessments_by_course_2($assessmentids);
        // Assessments that could be fully graded or in the process off.
        $gradedorstarted = $this->get_assessments_by_course($assessmentids);
        $assessments = $gradedorstarted + $notgraded; // Combine the results.  The order matters here!!

        return $assessments;

    }

    /**
     * @userid user id
     * @courseid course id
     * @assignmentid assignment id
     * return the submissions related to the assignment passed as parametre.
     */
    public function get_assessment_submission_records($userid, $courseid, $assignmentid) {
        global $DB;

        $sql = "SELECT *
                FROM {assign}  assign
                JOIN {assign_submission} asub
                ON asub.assignment = assign.id
                JOIN {files}  f  ON f.itemid = asub.id
                WHERE f.userid = ? AND f.filearea = ? AND assign.id IN ($assignmentid) AND assign.course = ?
                ORDER BY asub.attemptnumber DESC, f.filename ASC";

        $paramsarray = ['userid' => $userid, 'filearea' => 'submission_files', 'course' => $courseid];

        $results = array_values($DB->get_records_sql($sql, $paramsarray));

        return $results;
    }

    /**
     * @itemid assignment id
     * @userid user id
     * @createpdf
     * return list of online text subimssion
     */
    public function get_assessment_submission_onlinetext($itemid, $userid, $createpdf = false) {
        global $DB;

        $sql = "SELECT *
                FROM {assignsubmission_onlinetext} onlinetxt
                WHERE onlinetxt.assignment = :assignment
                AND onlinetxt.submission = :submission";

        $submissionid = $this->get_assesment_submission_id($itemid, $userid);
        $params = ['assignment' => $itemid, 'submission' => $submissionid];
        $results = array_values($DB->get_records_sql($sql, $params));
        $texts = [];
        $contextid = $this->get_context_id_from_files($submissionid, $userid, 'submissions_onlinetext', 'assignsubmission_onlinetext');

        foreach ($results as $i => $r) {
            $text = file_rewrite_pluginfile_urls($r->onlinetext, 'pluginfile.php', $contextid, 'assignsubmission_onlinetext', 'submissions_onlinetext', $submissionid);
            if (!$createpdf) {
                $texts[] = shorten_text($text, 10, true);
            } else {
                $texts[] = $text;
            }
        }

        return $texts;

    }

    /**
     * @itemid assignment id
     * @userid user id
     * return list of reflection
     */
    public function get_assessment_submission_reflection($itemid, $userid) {
        global $DB;

        $sql = "SELECT *
                FROM {assignsubmission_reflection} reflection
                WHERE reflection.assignment = :assignment
                AND reflection.submission = :submission";

        $submissionid = $this->get_assesment_submission_id($itemid, $userid);
        $params = ['assignment' => $itemid, 'submission' => $submissionid];
        $result = $DB->get_record_sql($sql, $params);
        $texts = [];

        if ($result = $DB->get_record_sql($sql, $params)) {
            $contextid = $this->get_context_id_from_files($result->id, $userid, 'submission_reflection', 'assignsubmission_reflection');
            $text = file_rewrite_pluginfile_urls($result->reflectiontxt, 'pluginfile.php', $contextid, 'assignsubmission_reflection', 'submission_reflection', $result->id);
            $texts[] = shorten_text($text, 30, true);
        }

        return $texts;

    }

    /**
     * Get the context id from the mdl_files table
     * @itemid is the $itemid from mdl_files table.
     * @userid userid
     */
    public function get_context_id_from_files($itemid, $userid, $filearea, $component) {
        global $DB;

        $sql = "SELECT distinct contextid
        FROM {files} f
        WHERE f.filearea = ?
        AND f.component = ?
        AND f.itemid = ?
        AND f.userid = ?";

        $params = [
                'filearea'  => $filearea,
                'component' => $component,
                'itemid'    => $itemid,
                'userid'   => $userid
                ];

        $contextid = array_column($DB->get_records_sql($sql, $params), 'contextid');
        $contextid = ( count($contextid) > 0 ) ? ($contextid[count($contextid) - 1]) : '0';

        return $contextid;

    }

    /**
     * Get the submission ids for the assignment given.
     * @itemid assignment id
     * @userid user id
     * @return int
     */
    public function get_assesment_submission_id($itemid, $userid) {
        global $DB;
        $sql = "SELECT id
                FROM {assign_submission} asub
                WHERE asub.assignment = :assignment AND userid = :userid";
        $params = ['assignment' => $itemid, 'userid' => $userid ];

        $result = array_values($DB->get_records_sql($sql, $params));
        if (isset($result[ 0 ])) {
            $result = $result[ 0 ];
            return $result->id;
        }

        return 0;
    }

    /**
     * Get the submission ids for the assignment given
     * @itemid assignment id
     * @userid user id
     * @return list.
     */
    public function get_assesment_submission_ids($itemid, $userids) {
        global $DB;

        $sql = "SELECT asub.id AS 'assignsubmissionid', u.id AS userid, u.username, u.firstname, u.lastname
                FROM {assign_submission} asub
                JOIN {user} u ON asub.userid = u.id
                WHERE asub.assignment = :assignment AND userid IN ($userids)";

        $params = ['assignment' => $itemid];

        $results = $DB->get_records_sql($sql, $params);
        $assignsubmissionids = implode(',', array_keys($results));

        return [$results, $assignsubmissionids];
    }

    /**
     * @itemid assignment id
     * @userid user id
     * returns the id of the reflection saved.
     */
    public function get_assesment_submission_reflection_id($itemid, $userid) {
        global $DB;
        $sql = "SELECT id
                FROM {assign_submission} assignsubmission_reflection
                WHERE asub.assignment = :assignment AND userid = :userid";
        $params = ['assignment' => $itemid, 'userid' => $userid ];

        $result = array_values($DB->get_records_sql($sql, $params));
        if (isset($result[0])) {
            $result = $result[ 0 ];
            return $result->id;
        }

        return 0;
    }

    /**
     * @itemid assignmentid
     * @return array
     */
    public function get_assessment_anotatepdf_files($itemid) {
        global $DB;

        $sql = "SELECT * FROM {files}  f
                WHERE  f.filearea = ? AND f.itemid = ? AND f.component = ?
                ORDER BY f.filename ASC";

        $paramsarray = ['filearea' => 'download', 'itemid' => $itemid, 'component' => 'assignfeedback_editpdf'];
        $results = array_values($DB->get_records_sql($sql, $paramsarray));

        return $results;
    }

    /**
     * @itemid assignment id
     * @return array
     */
    public function get_assessment_feedback_files($itemid, $createpdf = false) {
        global $DB, $USER;
        $sql = "SELECT *
                FROM {files}  f
                WHERE f.userid = ? AND f.filearea = ? AND f.itemid = ?
                ORDER BY f.filename ASC";

        $paramsarray = ['userid' => $USER->id, 'filearea' => 'feedback_files', 'itemid' => $itemid];
        $results = array_values($DB->get_records_sql($sql, $paramsarray));
        return $results;
    }
    /**
     * @return array
     */
    public function get_assessment_feedback_comments($itemid, $userid, $createpdf = false) {

        $txtwithfile = $this->get_assessment_feedback_comments_with_file($itemid, $userid);
        $onlytxt     = $this->get_assessment_feedback_comments_text($itemid, $userid);
        $results     = $txtwithfile + $onlytxt;
        $comments    = [];

        foreach ($results as $i => $r) {

            if (!$createpdf && isset($r->contextid)) {
                $comments[] = shorten_text(file_rewrite_pluginfile_urls($r->commenttext, 'pluginfile.php', $r->contextid, 'assignfeedback_comments', 'feedback', $itemid));
            } else if ($createpdf && isset($r->contextid)) {
                $comments[] = file_rewrite_pluginfile_urls($r->commenttext, 'pluginfile.php', $r->contextid, 'assignfeedback_comments', 'feedback', $itemid);
            } else if ($createpdf) { // We need the entire text for the PDF.
                $comments[] = $r->commenttext;
            } else {
                $comments[] = shorten_text($r->commenttext);
            }
        }

        return $comments;
    }

    /**
     *  Get the assessment feedback comments that have both files and text.
     */
    private function get_assessment_feedback_comments_with_file($itemid, $userid) {
        global $DB;
        // Get the comments that have both files and text.
        $sql = "SELECT distinct ac.id, ac.commenttext, f.contextid
        FROM {assignfeedback_comments} ac
        JOIN {files} f on f.itemid = ac.grade
        JOIN {assign_grades} ag ON f.itemid = ag.id
        WHERE f.itemid = ? AND f.component = ? AND f.filearea = ? AND  ac.grade = ? AND ag.userid = ?
        AND f.filename <> '.'";

        $paramsarray = ['itemid' => $itemid, 'component' => 'assignfeedback_comments', 'filearea' => 'feedback', 'grade' => $itemid, 'userid' => $userid];
        $results     = $DB->get_records_sql($sql, $paramsarray);

        return $results;
    }

    /**
     *  Get the assessment feedback comments that have text.
     */
    private function get_assessment_feedback_comments_text($itemid, $userid) {
        global $DB;

        $sql = "SELECT distinct ac.id, ac.commenttext
                FROM {assignfeedback_comments} ac
                WHERE ac.grade = ?";
        $paramsarray = ['grade' => $itemid];

        $results = $DB->get_records_sql($sql, $paramsarray);
        return $results;
    }

    /**
     * get the assessments ids as a string separated by comma.
     */
    public function get_assessment_ids($courseid, $assessmentids) {

        $assessids = $assessmentids;

        if ($assessmentids == '' || in_array(0, $assessmentids)) {
            $r = $this->get_submitted_assessments($courseid);
            $r = array_unique(array_column($r, 'assignmentid'));
            $assessids = implode(',', $r);
        } else {
            $assessids = implode(',', $assessmentids);
        }

        return $assessids;
    }


    /**
     *  Get the assessments that are graded or that are not but the teacher already started working on.
     * For example, editing the annotated PDF or adding comments.
     */
    private function get_assessments_by_course($assessmentids) {
        global $DB;

        $sql = "SELECT  grades.id AS gradeid,
                        grades.assignment AS assignmentid,
                        u.id AS userid, u.firstname,
                        u.lastname,  assign.name AS 'assignmentname',
                        assign.duedate, assign.markingworkflow, grades.grade,
                        assign.grade AS gradeoutof
                FROM {assign_grades}  grades
                JOIN {assign}  assign ON grades.assignment = assign.id
                JOIN {user}  u ON grades.userid = u.id
                WHERE grades.assignment  IN ($assessmentids)
                ORDER BY assign.name DESC, grades.grade DESC, grades.id DESC";

        $result = $DB->get_records_sql($sql);

        return $result;
    }

    /**
     * Get course module
     */
    public function get_course_module($courseid, $assessids) {
        global $DB;

        $sql = "SELECT cm.instance, cm.id as cmid
                FROM mdl_course_modules  cm
                JOIN  mdl_modules  m ON cm.module = m.id
                WHERE cm.course = ? AND cm.instance IN ($assessids) AND m.name = 'assign'; ";

        $paramsarray = ['course' => $courseid];

        $results = $DB->get_records_sql($sql, $paramsarray);

        return $results;
    }

    /**
     * Downloads files from submission type files.
     * @assignmentids ID of the assignment
     * @id Course id
     * @selected users list of users selected
     */
    public function download_submission_files($assignmentids, $id, $selectedusers) {
        global $DB;
        // Increase the server timeout to handle the creation and sending of large zip files.
        \core_php_time_limit::raise();

        $fs = get_file_storage();
        // Build a list of files to zip.
        $filesforzipping = array();
        // Construct the zip file name.
        $course = $DB->get_record('course', array('id' => $id));
        $filename = clean_filename($course->fullname . '.zip'); // Main folder.

        foreach ($selectedusers as $userid) {

            $filerecords = $this->get_assessment_submission_records($userid, $id, $assignmentids);
            $user = $DB->get_record('user', array('id' => $userid));

            foreach ($filerecords as $fr) {

                $files = $fs->get_area_files($fr->contextid, $fr->component, $fr->filearea,  $fr->itemid);

                foreach ($files as $file) {

                    if ($file->get_filename() == '.') {
                        continue;
                    }
                    // Get extension from mimetype.
                    $extension = '.' . $this->get_extension($file);
                    // In case there are files really long names.
                    $n = shorten_text($file->get_filename(), 30, false, '') . $extension;

                    $fname  = $fr->name . '/' . $user->lastname . ' ' . $user->firstname . ' ' . $course->fullname . ' ' . $n;
                    $pathfilename = $fname;
                    $filesforzipping[$pathfilename] = $file;
                }
            }
        }

        if (count($filesforzipping) > 0) {
            $zipfile = $this->pack_files($filesforzipping);
            send_temp_file($zipfile, $filename);
        }
    }

    /**
     * Downloads the anotated PDF feedback
     * @itemids submission ids
     * @id course id
     */
    public function download_anotatepdf_files($itemids, $id) {
        global $DB;
        // Increase the server timeout to handle the creation and sending of large zip files.
        \core_php_time_limit::raise();

        $uitemids = json_decode($itemids);

        $fs = get_file_storage();
        // Build a list of files to zip.
        $filesforzipping = array();
        // Construct the zip file name.
        $course = $DB->get_record('course', array('id' => $id));
        $filename = clean_filename($course->fullname . '.zip'); // Main folder.
        $assesmentsdetails = $this->get_assesments_with_grades($id);

        foreach ($uitemids as $uitemid) {
            $user = $DB->get_record('user', ['id' => $uitemid->userid], 'firstname, lastname');

            foreach ($uitemid->uitemids as $itemid) {
                $assessname = $assesmentsdetails[$itemid]->assignmentname;
                $filerecords = $this->get_assessment_anotatepdf_files($itemid);
                foreach ($filerecords as $fr) {
                    $files = $fs->get_area_files($fr->contextid, $fr->component, $fr->filearea,  $fr->itemid);
                    foreach ($files as $file) {

                        // Naming convention is LAST Name, FirstName, Year, Subject, Level, Component.
                        $extension = '.' . $this->get_extension($file);
                        $n = shorten_text($file->get_filename(), 30, false, '') . $extension;
                        $notname  = $user->lastname . ' ' . $user->firstname . ' ' . $course->fullname . ' ' . $n;
                        $pathfilename = $assessname . '/' . $notname;

                        $filesforzipping[$pathfilename] = $file;
                    }
                }
            }
        }

        // Remove folder.
        foreach ($filesforzipping as $path => $files) {
            if ($path[-1] == '.') {
                unset($filesforzipping[$path]);
            }
        }

        if (count($filesforzipping) > 0) {
            $zipfile = $this->pack_files($filesforzipping);
            send_temp_file($zipfile, $filename);
        } else {
            return 1;
        }
    }

    /**
     * Downloads the feedback type files
     * @itemids submission ids
     * @id course id
     */
    public function download_feedback_files($itemids, $id) {
        global $DB;

        // Increase the server timeout to handle the creation and sending of large zip files.
        \core_php_time_limit::raise();

        $uitemids = json_decode($itemids);

        $fs = get_file_storage();
        // Build a list of files to zip.
        $filesforzipping = array();
        // Construct the zip file name.
        $course = $DB->get_record('course', array('id' => $id));
        $filename = clean_filename($course->fullname . '.zip'); // Main folder.
        $assesmentsdetails = $this->get_assesments_with_grades($id);

        foreach ($uitemids as $uitemid) {
            $user = $DB->get_record('user', ['id' => $uitemid->userid], 'firstname, lastname');

            foreach ($uitemid->uitemids as $itemid) {
                $assessname = $assesmentsdetails[$itemid]->assignmentname;
                $filerecords = $this->get_assessment_feedback_files($itemid);
                foreach ($filerecords as $fr) {
                    $files = $fs->get_area_files($fr->contextid, $fr->component, $fr->filearea,  $fr->itemid);
                    foreach ($files as $file) {

                        if ($file->get_filename() == '.') {
                            continue;
                        }
                        $extension = '.' . $this->get_extension($file);
                        $n = shorten_text($file->get_filename(), 30, false, '') . $extension;
                        $fname  = $assessname . '/' . $user->lastname . ' ' . $user->firstname . ' ' . $course->fullname . ' ' . $n;
                        $pathfilename = $fname;
                        $filesforzipping[$pathfilename] = $file;
                    }
                }
            }
        }

        if (count($filesforzipping) > 0) {
            $zipfile = $this->pack_files($filesforzipping);
            send_temp_file($zipfile, $filename);
        } else {
            return 1;
        }
    }

    /**
     * Downloads the feedback type comments as PDFS
     * @itemids submission ids
     * @id course id
     */
    public function download_feedback_comments($itemids, $id) {
        global $DB;
        // Increase the server timeout to handle the creation and sending of large zip files.
        \core_php_time_limit::raise();

        $uitemids = json_decode($itemids);
        $course = $DB->get_record('course', array('id' => $id));
        $dirname = clean_filename($course->fullname . '.zip'); // Main folder.
        $assesmentsdetails = $this->get_assesments_with_grades($id);
        $userspdfs = [];
        foreach ($uitemids as $uitemid) {
            $user = $DB->get_record('user', ['id' => $uitemid->userid], 'id, firstname, lastname');

            foreach ($uitemid->uitemids as $itemid) {
                $assessname = $assesmentsdetails[$itemid]->assignmentname;
                $filerecords = $this->get_assessment_feedback_comments($itemid, $user->id, true);

                foreach ($filerecords as $fr) {
                    $userspdfs [$user->id][] = report_assignfeedback_download_generatefeedbackpdf($fr, $assessname, $user, $course);
                }
            }
        }

        $this->save_generated_pdf_files($userspdfs , $dirname);

    }

    /**
     * Download the submission type onlinetext as PDF
     * @assignmentids assignments id
     * @id course id
     * @selectedusers list of users selected.
     */
    public function download_submission_onlinetext($assignmentids, $id, $selectedusers) {
        global $DB;
        // Increase the server timeout to handle the creation and sending of large zip files.
        \core_php_time_limit::raise();

        $course = $DB->get_record('course', array('id' => $id));
        $dirname = clean_filename($course->fullname . '.zip'); // Main folder.

        $assignmentids = explode(',', $assignmentids);

        foreach ($assignmentids as $assignmentid) {
            $select = "id = {$assignmentid}";
            $asessn = $DB->get_field_select('assign', 'name', $select);

            foreach ($selectedusers as $user) {
                $filerecords = $this->get_assessment_submission_onlinetext($assignmentid, $user, true);
                $user = $DB->get_record('user', ['id' => $user], 'id, firstname, lastname');

                foreach ($filerecords as $fr) {
                    $userpdfs[$user->id][] = report_assignfeedback_download_generatefeedbackpdf($fr, $asessn, $user, $course);
                }
            }

        }

        $this->save_generated_pdf_files($userpdfs, $dirname);

    }

    /**
     * Download the submission type onlinetext as a spreadsheet
     * @assignmentids assignments id
     * @id course id
     * @selectedusers list of users selected.
     */
    public function download_submission_onlinetextexcel($courseid, $selectedusers, $cmids, $cmid) {
        $cmids = (array) json_decode($cmids);
        $selectedusers = implode(',', $selectedusers);
        $tempdir = make_temp_directory('report_assing_fdownloader/excel');

        foreach ($cmids as $cmid) {
            report_assignfeedback_download_setup_onlinetext_workbook($courseid, $cmid, $selectedusers, $tempdir);
        }

        // Now make a zip file of the temp dir and then delete it.
        $this->zip_excelworkbook($courseid, $cmid);

    }

    /**
     * Generate an excel file with the grades.
     * @cmids Course module ids
     * @instaceids assignments id
     * @courseid course id
     * @selectedusers list of users selected.
     */
    public function download_assessment_grades($cmids, $courseid, $instaceids, $selecteduser, $cmid) {
        \core_php_time_limit::raise();

        $cmids = (array) json_decode($cmids);
        $instaceids = explode(',', $instaceids);
        $selectedusers = implode(',', $selecteduser);
        $tempdir = make_temp_directory('report_assing_fdownloader/excel');

        foreach ($cmids as $cmid) {
            $this->download_grades_helper($cmid, $courseid, $selectedusers, $tempdir);
        }

        // Now make a zip file of the temp dir and then delete it.
        $this->zip_excelworkbook($courseid, $cmid);

    }
    /**
     * Helper function for download_assessment_grades
     * @cmid Course module id
     * @courseid course id
     * @selectedusers list of users selected.
     * @tempdir temporary directory where the files are saved
     */
    private function download_grades_helper($cmid, $courseid, $selectedusers, $tempdir) {
        $context = \context_module::instance($cmid);
        $gradingmanager = get_grading_manager($context, 'mod_assign', 'submissions');

        switch ($gradingmanager->get_active_method()) {
            case 'frubric':
                if ($gradingmanager->get_active_controller() != null) {
                    $areaid = $gradingmanager->get_active_controller()->get_areaid();
                    $maxscore = $gradingmanager->get_active_controller()->get_min_max_score()['maxscore'];
                    report_assignfeedback_download_setup_frubric_workbook($courseid, $cmid, $areaid, $selectedusers, $maxscore, $tempdir);
                }
                break;
            case 'rubric':
                report_assignfeedback_download_setup_rubric_workbook($courseid, $cmid, $selectedusers, $tempdir);
                break;
            case 'guide':
                report_assignfeedback_download_setup_marking_guide_workbook($courseid, $cmid, $selectedusers, $tempdir);
                break;
        }
    }

    /**
     * Generate an excel file with the reflections.
     * @instanceids assessment id
     * @id course id
     * @selectedusers list of users selected
     */
    public function download_submission_reflection($id, $selectedusers, $cmids, $cmid) {
        \core_php_time_limit::raise();
        $cmids = (array) json_decode($cmids);
        $selectedusers = implode(',', $selectedusers);
        $tempdir = make_temp_directory('report_assing_fdownloader/excel');

        foreach ($cmids as $cmid) {
            report_assignfeedback_download_setup_reflection_workbook($id, $cmid, $selectedusers, $tempdir);
        }

        // Now make a zip file of the temp dir and then delete it.
        $this->zip_excelworkbook($id, $cmid);

    }


    /**
     * This function is called to collect all the online text submission
     * to export them to an spreadsheet.
     * @instanceid assignment id
     * @selectedusers selected users
     * @return list of objects that contains the user id and the user reflection.
     */
    public function get_submission_onlinetext($instanceid, $selectedusers) {
        global $DB;

        if (strlen($instanceid) > 0 && strlen($selectedusers)) {

            list($submissionsanduser, $submissionsids) = $this->get_assesment_submission_ids($instanceid, $selectedusers);

            $sql = "SELECT *
                    FROM {assignsubmission_onlinetext} onlinetxt
                    WHERE onlinetxt.assignment = :assignment
                    AND onlinetxt.submission IN ($submissionsids)";
            $params = ['assignment' => $instanceid];

            $results = $DB->get_records_sql($sql, $params);

            foreach ($results as $result) {

                $user = $submissionsanduser[$result->submission];
                $contextid = $this->get_context_id_from_files($result->id,
                                                              $user->userid,
                                                            'submissions_onlinetext',
                                                            'assignsubmission_onlinetext');

                $txt = file_rewrite_pluginfile_urls($result->onlinetext,
                                                            'pluginfile.php',
                                                            $contextid,
                                                            'assignsubmission_onlinetext',
                                                            'submissions_onlinetext',
                                                            $result->id);
                $data                = new \stdClass();
                $data->userid        = $user->userid;
                $data->firstname     = $user->firstname;
                $data->lastname      = $user->lastname;
                $data->username      = $user->username;
                $data->submission    = $result->submission;
                $data->assignment    = $result->assignment;
                // Replace non break space entity to new line character.
                $data->txt           = str_replace("&nbsp;", "\r\n", strip_tags($txt));

                $texts[]       = $data;
            }

        }
        return $texts;

    }
    /**
     * @instanceid assignment id
     * @selectedusers selected users
     * @return list of objects that contains the user id and the user reflection.
     */
    public function get_submission_reflections($instanceid, $selectedusers) {
        global $DB;

        $reflections = [];

        if (strlen($instanceid) > 0 && strlen($selectedusers)) {

            $sql = "SELECT ref.*, u.firstname, u.lastname, u.username
                    FROM {assignsubmission_reflection} ref
                    JOIN {user} u ON ref.userid = u.id
                    WHERE assignment = $instanceid AND userid IN ($selectedusers)";

            $results = $DB->get_records_sql($sql);

            foreach ($results as $result) {

                $contextid = $this->get_context_id_from_files($result->id,
                                                            $result->userid,
                                                            'submission_reflection',
                                                            'assignsubmission_reflection');

                $reflectiontxt = file_rewrite_pluginfile_urls($result->reflectiontxt,
                                                            'pluginfile.php',
                                                            $contextid,
                                                            'assignsubmission_reflection',
                                                            'submission_reflection',
                                                            $result->id);
                $data                = new \stdClass();
                $data->userid        = $result->userid;
                $data->firstname     = $result->firstname;
                $data->lastname      = $result->lastname;
                $data->username      = $result->username;
                $data->submission    = $result->submission;
                $data->assignment    = $result->assignment;
                $data->reflectiontxt = strip_tags($reflectiontxt);

                $reflections[]       = $data;
            }

        }
        return $reflections;

    }

    // TODO
    public function download_all_files($itemids, $id) {
        \core_php_time_limit::raise();
    }

    /**
     * Downloads flexible rubric grading results as PDF
     * @cmids course module ids
     * @instanceids assignment ids
     * @courseid course id
     * @frubricselection the content of the flexible rubrics selected
     *
     */
    public function download_frubric($cmids, $instaceids, $courseid, $frubricselection) {
        global $DB;

        \core_php_time_limit::raise();

        $cmids = (array) json_decode($cmids);
        $instaceids = explode(',', $instaceids);
        $userpdfs = [];
        $frubricselection = (array) json_decode($frubricselection);
        // Construct the zip file name.
        $course = $DB->get_record('course', array('id' => $courseid));
        $dirname = clean_filename($course->fullname . '.zip'); // Main folder.

        $coursedetails = new \stdClass();
        $coursedetails->courseid = $courseid;
        $coursedetails->name  = $course->fullname;
        $assesmentsdetails = $this->get_assesments_with_grades($courseid);

        foreach ($frubricselection as $userid => $frubrics) {
            foreach ($frubrics as $frubric) {
                if ($frubric == null) {
                    continue;
                }
                $frubric = json_decode($frubric);

                $assessname = $assesmentsdetails[$frubric->gradeid]->assignmentname;
                $rubric = $this->get_advanced_method($frubric->cmid, $courseid, $frubric->userid, $frubric->assignmentid);

                if (isset($rubric['frubric'])) {
                    $userpdfs[$frubric->userid][] = report_assignfeedback_download_create_frubric_pdf($rubric['frubric'], $assessname, $frubric);
                }
            }
        }

        $this->save_generated_pdf_files($userpdfs, $dirname);
    }

    /**
     * Based on the active method, return what is needed.
     * @cmid course module id
     * @courseid course id
     * @userid user id
     * @instanceid assignment id
     */
    public function get_advanced_method($cmid, $courseid, $userid, $instanceid) {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        $context = \context_module::instance($cmid);
        $gradingmanager = get_grading_manager($context, 'mod_assign', 'submissions');

        switch($gradingmanager->get_active_method()) {
            case 'frubric' :
                return ['frubric' => $this->get_frubric_json($courseid, $userid, $instanceid, $gradingmanager->get_active_controller())];
                break;
            case 'rubric' :
                return ['rubric' => 'rubric'];
                break;
            case 'guide' :
                return ['guide' => 'guide'];
                break;

            default:
                return '';
        }
    }

    /**
     * Returns the current active method for the assessment.
     * @cmid course module id
     */
    public function get_active_grading_method($cmid) {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        $context = \context_module::instance($cmid);
        $gradingmanager = get_grading_manager($context, 'mod_assign', 'submissions');
        return $gradingmanager->get_active_method();
    }

    /**
     * @cmid course module id
     * @courseid course id
     * @instanceid assignment id
     * @return Get the frubric that is rendered to a student. With the checked descriptors.
     */
    public function get_rubric($cmid, $courseid, $userid, $instanceid) {
        global $DB, $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        $context = \context_module::instance($cmid);
        $gradingmanager = get_grading_manager($context, 'mod_assign', 'submissions');

        if ($controller = $gradingmanager->get_active_controller()) {
            $methodname = $DB->get_record('grading_areas', ['id' => $controller->get_areaid()], 'activemethod', IGNORE_MISSING);

            if ($methodname->activemethod == 'frubric') {

                return  $this->get_frubric_json($courseid, $userid, $instanceid, $controller);
            }
        }

        return '';
    }

    /**
     * Only Frubric can be downloaded as PDF.
     * This function generats the JSON needed to download from the front page (Using JS) --> NOTE: is not active yet. The JS code is commented out
     *
     *
     */
    private function get_frubric_json($courseid, $userid, $instanceid, $controller) {
        global $DB, $PAGE;

        $params = array('assignment' => $instanceid, 'userid' => $userid);
        $grade = array_values($DB->get_records('assign_grades', $params, 'attemptnumber DESC', '*', 0, 1));

        if (count($grade) == 0) {
            return '';
        }

        $gradinginfo = grade_get_grades(
            $courseid,
            'mod',
            'assign',
            $instanceid,
            $userid
        );

        $gradingitem = null;

        if (isset($gradinginfo->items[0])) {
            $gradingitem = $gradinginfo->items[0];
            $gradebookgrade = $gradingitem->grades[$userid];
        }

        $fr = json_encode($controller->render_grade_to_download(
            $PAGE,
            $grade[0]->id,
            $gradinginfo,
            $gradebookgrade->str_long_grade,
            true
        ));

        return $fr;
    }


    /**
     * Generate zip file from array of given files - copied from mod_assign 3.10
     *
     * @param array $filesforzipping - array of files to pass into archive_to_pathname.
     *                                 This array is indexed by the final file name and each
     *                                 element in the array is an instance of a stored_file object.
     * @return path of temp file - note this returned file does
     *         not have a .zip extension - it is a temp file.
     */
    private function pack_files($filesforzipping) {

        global $CFG;
        // Create path for new zip file.
        $tempzip = tempnam($CFG->tempdir . '/', 'assignment_');
        // Zip files.
        $zipper = new zip_packer();

        if ($zipper->archive_to_pathname($filesforzipping, $tempzip)) {
            return $tempzip;
        }
        return false;
    }

    /**
     * This function allows to generate PDF files with
     * the content of frubric, feedback comments.
     */
    private function save_generated_pdf_files($pdfs, $dirname) {

        $workdir = make_temp_directory('report_assing_fdownloader/zipfrubric');

        // Create the zip.
        $zipfile = new \zip_archive();
        @unlink($workdir . '/' . $dirname);
        $zipfile->open($workdir . '/' . $dirname);

        foreach ($pdfs as $pdfarray) {
            foreach ($pdfarray as $pdf) {
                $zipfile->add_file_from_string($pdf->pathfilename . "\\" . $pdf->filename, $pdf->pdf->Output($pdf->filename, 'S'));
            }
        }

        $zipfile->close();

        header("Content-Type: application/zip");
        header("Content-Disposition: attachment; filename=$dirname");
        header("Content-Length: " . filesize("$workdir/$dirname"));
        readfile("$workdir/$dirname");
        unlink("$workdir/$dirname");
        die(); // If not set, a invalid zip file error is thrown.
    }

    /**
     * Creates a zip file with excel files in it
     */
    private function zip_excelworkbook($courseid, $cmid) {
        global $CFG;
        $foldertozip = $CFG->tempdir.'/report_assing_fdownloader/excel';
        // Get real path for our folder.
        $rootpath = realpath($foldertozip);

        // Initialize archive object.
        $zip = new \ZipArchive();
        $filename = $CFG->tempdir.'/report_assing_fdownloader/grades.zip';

        $zip->open( $filename, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        // Create recursive directory iterator.
        /** @var SplFileInfo[] $files */
        $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($rootpath),
        RecursiveIteratorIterator::LEAVES_ONLY
        );
        $filestodelete = [];
        foreach ($files as $name => $file) {
            // Skip directories (they would be added automatically).
            if (!$file->isDir()) {
                // Get real and relative path for current file.
                $filepath = $file->getRealPath();
                $relativepath = substr($filepath, strlen($rootpath) + 1);
                // Add current file to archive.
                $zip->addFile($filepath, $relativepath);
                $filestodelete[] = $filepath;
            }
        }

        if ($zip->numFiles > 0) {
            // Zip archive will be created only after closing object.
            $zip->close();

            foreach ($filestodelete as $file) {
                unlink($file);
            }

            header("Content-Type: application/zip");
            header("Content-Disposition: attachment; filename=grades.zip");
            header("Content-Length: " . filesize("$filename"));
            readfile("$filename");
            unlink("$filename");
        } else {
            $url = new moodle_url('/report/assignfeedback_download/index.php', array('id' => $courseid, 'cmid' => $cmid));
            redirect($url, get_string('nofilestocompress', 'report_assignfeedback_download'), null, \core\output\notification::NOTIFY_INFO);
        }

        die(); // If not set, a invalid zip file error is thrown.

    }

    /**
     * @cmid course module id
     * @itemid assignment id
     */
    public function get_grading_instance_status($cmid, $itemid) {
        global $USER;
        $context        = \context_module::instance($cmid);
        $gradingmanager = get_grading_manager($context, 'mod_assign', 'submissions');

        if ($activemethod   = $gradingmanager->get_active_method()) {
            $controller = $gradingmanager->get_controller( $activemethod);
            $currentinstance = $controller->get_current_instance($USER->id, $itemid);

            if (!is_null($currentinstance)) {
                return  $currentinstance->get_status();
            }

        }
        return -1;
    }
    // Get the file extension.  https://docs.w3cub.com/http/basics_of_http/mime_types/complete_list_of_mime_types.html
    // Mac users sometimes dont have the extension in the file. To avoid issues, pick up the mimetype of the file
    // and get the extension from it/.
    private function get_extension_helper($mime) {
        $mimemap = [
            'video/3gpp2'                                                               => '3g2',
            'video/3gp'                                                                 => '3gp',
            'video/3gpp'                                                                => '3gp',
            'application/x-compressed'                                                  => '7zip',
            'audio/x-acc'                                                               => 'aac',
            'audio/ac3'                                                                 => 'ac3',
            'application/postscript'                                                    => 'ai',
            'audio/x-aiff'                                                              => 'aif',
            'audio/aiff'                                                                => 'aif',
            'audio/x-au'                                                                => 'au',
            'video/x-msvideo'                                                           => 'avi',
            'video/msvideo'                                                             => 'avi',
            'video/avi'                                                                 => 'avi',
            'application/x-troff-msvideo'                                               => 'avi',
            'application/macbinary'                                                     => 'bin',
            'application/mac-binary'                                                    => 'bin',
            'application/x-binary'                                                      => 'bin',
            'application/x-macbinary'                                                   => 'bin',
            'image/bmp'                                                                 => 'bmp',
            'image/x-bmp'                                                               => 'bmp',
            'image/x-bitmap'                                                            => 'bmp',
            'image/x-xbitmap'                                                           => 'bmp',
            'image/x-win-bitmap'                                                        => 'bmp',
            'image/x-windows-bmp'                                                       => 'bmp',
            'image/ms-bmp'                                                              => 'bmp',
            'image/x-ms-bmp'                                                            => 'bmp',
            'application/bmp'                                                           => 'bmp',
            'application/x-bmp'                                                         => 'bmp',
            'application/x-win-bitmap'                                                  => 'bmp',
            'application/cdr'                                                           => 'cdr',
            'application/coreldraw'                                                     => 'cdr',
            'application/x-cdr'                                                         => 'cdr',
            'application/x-coreldraw'                                                   => 'cdr',
            'image/cdr'                                                                 => 'cdr',
            'image/x-cdr'                                                               => 'cdr',
            'zz-application/zz-winassoc-cdr'                                            => 'cdr',
            'application/mac-compactpro'                                                => 'cpt',
            'application/pkix-crl'                                                      => 'crl',
            'application/pkcs-crl'                                                      => 'crl',
            'application/x-x509-ca-cert'                                                => 'crt',
            'application/pkix-cert'                                                     => 'crt',
            'text/css'                                                                  => 'css',
            'text/x-comma-separated-values'                                             => 'csv',
            'text/comma-separated-values'                                               => 'csv',
            'application/vnd.msexcel'                                                   => 'csv',
            'application/x-director'                                                    => 'dcr',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'   => 'docx',
            'application/x-dvi'                                                         => 'dvi',
            'message/rfc822'                                                            => 'eml',
            'application/x-msdownload'                                                  => 'exe',
            'video/x-f4v'                                                               => 'f4v',
            'audio/x-flac'                                                              => 'flac',
            'video/x-flv'                                                               => 'flv',
            'image/gif'                                                                 => 'gif',
            'application/gpg-keys'                                                      => 'gpg',
            'application/x-gtar'                                                        => 'gtar',
            'application/x-gzip'                                                        => 'gzip',
            'application/mac-binhex40'                                                  => 'hqx',
            'application/mac-binhex'                                                    => 'hqx',
            'application/x-binhex40'                                                    => 'hqx',
            'application/x-mac-binhex40'                                                => 'hqx',
            'text/html'                                                                 => 'html',
            'image/x-icon'                                                              => 'ico',
            'image/x-ico'                                                               => 'ico',
            'image/vnd.microsoft.icon'                                                  => 'ico',
            'text/calendar'                                                             => 'ics',
            'application/java-archive'                                                  => 'jar',
            'application/x-java-application'                                            => 'jar',
            'application/x-jar'                                                         => 'jar',
            'image/jp2'                                                                 => 'jp2',
            'video/mj2'                                                                 => 'jp2',
            'image/jpx'                                                                 => 'jp2',
            'image/jpm'                                                                 => 'jp2',
            'image/jpeg'                                                                => 'jpeg',
            'image/pjpeg'                                                               => 'jpeg',
            'application/x-javascript'                                                  => 'js',
            'application/json'                                                          => 'json',
            'text/json'                                                                 => 'json',
            'application/vnd.google-earth.kml+xml'                                      => 'kml',
            'application/vnd.google-earth.kmz'                                          => 'kmz',
            'text/x-log'                                                                => 'log',
            'audio/x-m4a'                                                               => 'm4a',
            'application/vnd.mpegurl'                                                   => 'm4u',
            'audio/midi'                                                                => 'mid',
            'application/vnd.mif'                                                       => 'mif',
            'video/quicktime'                                                           => 'mov',
            'video/x-sgi-movie'                                                         => 'movie',
            'audio/mpeg'                                                                => 'mp3',
            'audio/mpg'                                                                 => 'mp3',
            'audio/mpeg3'                                                               => 'mp3',
            'audio/mp3'                                                                 => 'mp3',
            'video/mp4'                                                                 => 'mp4',
            'video/mpeg'                                                                => 'mpeg',
            'application/oda'                                                           => 'oda',
            'audio/ogg'                                                                 => 'ogg',
            'video/ogg'                                                                 => 'ogg',
            'application/ogg'                                                           => 'ogg',
            'application/x-pkcs10'                                                      => 'p10',
            'application/pkcs10'                                                        => 'p10',
            'application/x-pkcs12'                                                      => 'p12',
            'application/x-pkcs7-signature'                                             => 'p7a',
            'application/pkcs7-mime'                                                    => 'p7c',
            'application/x-pkcs7-mime'                                                  => 'p7c',
            'application/x-pkcs7-certreqresp'                                           => 'p7r',
            'application/pkcs7-signature'                                               => 'p7s',
            'application/pdf'                                                           => 'pdf',
            'application/octet-stream'                                                  => 'pdf',
            'application/x-x509-user-cert'                                              => 'pem',
            'application/x-pem-file'                                                    => 'pem',
            'application/pgp'                                                           => 'pgp',
            'application/x-httpd-php'                                                   => 'php',
            'application/php'                                                           => 'php',
            'application/x-php'                                                         => 'php',
            'text/php'                                                                  => 'php',
            'text/x-php'                                                                => 'php',
            'application/x-httpd-php-source'                                            => 'php',
            'image/png'                                                                 => 'png',
            'image/x-png'                                                               => 'png',
            'application/powerpoint'                                                    => 'ppt',
            'application/vnd.ms-powerpoint'                                             => 'ppt',
            'application/vnd.ms-office'                                                 => 'ppt',
            'application/msword'                                                        => 'doc',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'application/x-photoshop'                                                   => 'psd',
            'image/vnd.adobe.photoshop'                                                 => 'psd',
            'audio/x-realaudio'                                                         => 'ra',
            'audio/x-pn-realaudio'                                                      => 'ram',
            'application/x-rar'                                                         => 'rar',
            'application/rar'                                                           => 'rar',
            'application/x-rar-compressed'                                              => 'rar',
            'audio/x-pn-realaudio-plugin'                                               => 'rpm',
            'application/x-pkcs7'                                                       => 'rsa',
            'text/rtf'                                                                  => 'rtf',
            'text/richtext'                                                             => 'rtx',
            'video/vnd.rn-realvideo'                                                    => 'rv',
            'application/x-stuffit'                                                     => 'sit',
            'application/smil'                                                          => 'smil',
            'text/srt'                                                                  => 'srt',
            'image/svg+xml'                                                             => 'svg',
            'application/x-shockwave-flash'                                             => 'swf',
            'application/x-tar'                                                         => 'tar',
            'application/x-gzip-compressed'                                             => 'tgz',
            'image/tiff'                                                                => 'tiff',
            'text/plain'                                                                => 'txt',
            'text/x-vcard'                                                              => 'vcf',
            'application/videolan'                                                      => 'vlc',
            'text/vtt'                                                                  => 'vtt',
            'audio/x-wav'                                                               => 'wav',
            'audio/wave'                                                                => 'wav',
            'audio/wav'                                                                 => 'wav',
            'application/wbxml'                                                         => 'wbxml',
            'video/webm'                                                                => 'webm',
            'audio/x-ms-wma'                                                            => 'wma',
            'application/wmlc'                                                          => 'wmlc',
            'video/x-ms-wmv'                                                            => 'wmv',
            'video/x-ms-asf'                                                            => 'wmv',
            'application/xhtml+xml'                                                     => 'xhtml',
            'application/excel'                                                         => 'xl',
            'application/msexcel'                                                       => 'xls',
            'application/x-msexcel'                                                     => 'xls',
            'application/x-ms-excel'                                                    => 'xls',
            'application/x-excel'                                                       => 'xls',
            'application/x-dos_ms_excel'                                                => 'xls',
            'application/xls'                                                           => 'xls',
            'application/x-xls'                                                         => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'         => 'xlsx',
            'application/vnd.ms-excel'                                                  => 'xlsx',
            'application/xml'                                                           => 'xml',
            'text/xml'                                                                  => 'xml',
            'text/xsl'                                                                  => 'xsl',
            'application/xspf+xml'                                                      => 'xspf',
            'application/x-compress'                                                    => 'z',
            'application/x-zip'                                                         => 'zip',
            'application/zip'                                                           => 'zip',
            'application/x-zip-compressed'                                              => 'zip',
            'application/s-compressed'                                                  => 'zip',
            'multipart/x-zip'                                                           => 'zip',
            'text/x-scriptzsh'                                                          => 'zsh',
        ];

        return isset($mimemap[$mime]) === true ? $mimemap[$mime] : false;
    }

    private function get_extension($file) {
        if (pathinfo($file->get_filename(), PATHINFO_EXTENSION) == '') {
            return $this->get_extension_helper($file->get_mimetype());
        } else {
            return  pathinfo($file->get_filename(), PATHINFO_EXTENSION);
        }
    }
}
