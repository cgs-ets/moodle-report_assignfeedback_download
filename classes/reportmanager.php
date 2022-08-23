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

use zip_packer;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filestorage/zip_archive.php');
require_once($CFG->dirroot . '/report/assignfeedback_download/vendor/autoload.php');
/**
 *
 * @package       report
 * @subpackage    assignfeedback_download
 * @copyright     2021 Veronica Bermegui
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reportmanager {

    // Only display assessments that have at least one graded student.
    public function get_assesments_with_grades($courseid) {
        global $DB;

        $sql = "SELECT grades.id AS gradeid, grades.assignment AS assignmentid, assign.name AS 'assignmentname'
                FROM {assign_grades} AS grades
                JOIN {assign} AS assign ON grades.assignment = assign.id 
                WHERE assign.course = ? 
                ORDER BY assign.name";

        $params_array = ['course' => $courseid];

        $results = ($DB->get_records_sql($sql, $params_array));

        return $results;
    }

    //Bring assessments that have submissions. it doesnt matter if they are graded.
    public function get_submitted_assessments($courseid) {
        global $DB;

        $sql = "SELECT distinct assign.id as assignmentid, assign.name AS 'assignmentname' FROM {assign} as assign 
                JOIN {assign_submission} as asub 
                ON assign.id = asub.assignment
                WHERE assign.course = ? AND asub.status = ?";

        $params = ['course' => $courseid, 'status' => 'submitted'];

        $results = $DB->get_records_sql($sql, $params);
       
        return $results;
    }

    // Get the assessments that are submitted 
    private function get_assessments_by_course_2($assessmentids) {
        global $DB;

        $sql = "SELECT asub.id, assign.id as assignmentid, assign.name AS 'assignmentname', u.id as userid, u.firstname, u.lastname
                FROM {assign} as assign 
                JOIN {assign_submission} as asub 
                ON assign.id = asub.assignment
                JOIN {user}  as u ON u.id = asub.userid
                WHERE assign.id in ($assessmentids)";
       
        $results = $DB->get_records_sql($sql);

       

        return $results;
    }

    // Combine graded and not graded (but submitted) assignments
    public function get_assessments($assessmentids) {
        //Assessments not graded
        $notgraded = $this->get_assessments_by_course_2($assessmentids);
        // Assessments that could be fully graded or in the process off.
        $gradedorstarted = $this->get_assessments_by_course($assessmentids);
        $assessments = $gradedorstarted + $notgraded; // combine the results.  THe order matters here!! 
        return $assessments;
        
    }


    public function get_assessment_submission_records($userid, $courseid, $assignmentid) {
        global $DB;

        $sql = "SELECT * FROM {assign} AS assign
                JOIN {assign_submission} AS asub
                ON asub.assignment = assign.id
                JOIN {files} as f  ON f.itemid = asub.id
                WHERE f.userid = ? AND f.filearea = ? AND assign.id IN ($assignmentid) AND assign.course = ?
                ORDER BY asub.attemptnumber DESC, f.filename ASC";

        $params_array = ['userid' => $userid, 'filearea' => 'submission_files', 'course' => $courseid];

        $results = array_values($DB->get_records_sql($sql, $params_array));

        return $results;
    }

    public function get_assessment_anotatepdf_files($itemid) {
        global $DB;

        $sql = "SELECT * FROM {files} AS f
                WHERE  f.filearea = ? AND f.itemid = ? AND f.component = ?
                ORDER BY f.filename ASC";

        $params_array = ['filearea' => 'download', 'itemid' => $itemid, 'component' => 'assignfeedback_editpdf'];
        $results = array_values($DB->get_records_sql($sql, $params_array));

        return $results;
    }

    public function get_assessment_feedback_files($itemid) {
        global $DB, $USER;
        $sql = "SELECT * FROM {files} AS f
        WHERE f.userid = ? AND f.filearea = ? AND f.itemid = ?
        ORDER BY f.filename ASC";

        $params_array = ['userid' => $USER->id, 'filearea' => 'feedback_files', 'itemid' => $itemid];
        $results = array_values($DB->get_records_sql($sql, $params_array));
        return $results;
    }

    public function get_assessment_feedback_comments($itemid, $userid) {
        global $DB;

        $sql = "SELECT  distinct ac.id, ac.commenttext, f.contextid FROM {assignfeedback_comments} AS ac
                JOIN {files} as f on f.itemid = ac.grade
                JOIN {assign_grades} as ag ON f.itemid = ag.id
                WHERE f.itemid = ? AND f.component = ? AND f.filearea = ? AND  ac.grade = ? AND ag.userid = ?
                AND f.filename <> '.'";

        $params_array = ['itemid' => $itemid, 'component' => 'assignfeedback_comments', 'filearea' => 'feedback', 'grade' => $itemid, 'userid' => $userid];

        $results = array_values($DB->get_records_sql($sql, $params_array));
        $comments = [];

        foreach ($results as $i => $r) {
            $comments[] = shorten_text(file_rewrite_pluginfile_urls($r->commenttext, 'pluginfile.php', $r->contextid, 'assignfeedback_comments', 'feedback', $itemid));
        }

        return $comments;
    }

    public function get_final_grade($assignmentinstance, $userid) {
        global $DB;

        // get the itemid
        $sql = "SELECT id FROM {grade_items} WHERE iteminstance = ?";
        $params_array = ['iteminstance' => $assignmentinstance];
        $itemid = $DB->get_records_sql($sql, $params_array);
        $itemid = array_column($itemid, 'id');
        $itemid = end($itemid);

        $sql = "SELECT finalgrade, rawgrademax from {grade_grades} WHERE itemid = ? AND userid = ?";
        $params_array = ['itemid' => $itemid, 'userid' => $userid];
        $finalgrade = array_values($DB->get_records_sql($sql, $params_array));
        $fg = '';

        if ($finalgrade) {

            $final = $finalgrade[0]->finalgrade;
            $final = number_format($final, 2);
            $maxgrade = $finalgrade[0]->rawgrademax;
            $maxgrade = number_format($maxgrade, 2);

            $fg =  $final . ' / ' . $maxgrade;
        }

        return $fg;
    }

    public function get_assessment_ids($courseid, $assessmentids) {

        $assessids = $assessmentids;

        if ($assessmentids == '' || in_array(0, $assessmentids)) {

            $r = $this->get_assesments_with_grades($courseid);
            $r = array_unique(array_column($r, 'assignmentid'));
            $assessids = implode(',', $r);
        } else {
            $assessids = implode(',', $assessmentids);
        }

        return $assessids;
    }

    // Get the assessments that are graded or that are not but the teacher already started working on. For example, editing the annotated PDF or adding comments.
    private function get_assessments_by_course($assessmentids) {
        global $DB;

        $sql = "SELECT  grades.id as gradeid, grades.assignment as assignmentid, u.id as userid, u.firstname, u.lastname,  assign.name as 'assignmentname', assign.duedate
                FROM {assign_grades} AS grades
                JOIN {assign} as assign ON grades.assignment = assign.id
                JOIN {user} as u ON grades.userid = u.id
                WHERE grades.assignment  IN ($assessmentids)
              --  AND grades.grade != -1.00000
                ORDER BY assign.name";

        $result = $DB->get_records_sql($sql);

        return $result;
    }

    public function get_course_module($courseid, $assessids) {
        global $DB;

        $sql = "SELECT cm.instance, cm.id as cmid FROM mdl_course_modules AS cm
        JOIN  mdl_modules AS m ON cm.module = m.id
        WHERE cm.course = ? AND cm.instance IN ($assessids) AND m.name = 'assign'; ";
        $params_array = ['course' => $courseid];

        $results = ($DB->get_records_sql($sql, $params_array));

        return $results;
    }

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
                    
                    if ($file->get_filename() == '.') continue;
                    // In case there are files really long names/
                    $extension = '.' . $this->getextension($file->get_mimetype()); //pathinfo($file->get_filename(), PATHINFO_EXTENSION);
                    $n = shorten_text($file->get_filename(), 30, false, $extension);
                    $fname  =  $fr->name . '/' . $user->lastname . ' ' . $user->firstname . ' ' . $course->fullname . ' ' . $n;
                    $pathfilename =    $fname;
                    $filesforzipping[$pathfilename] = $file;
                }
            }
        }

        if (count($filesforzipping) > 0) {
            $zipfile = $this->pack_files($filesforzipping);
            send_temp_file($zipfile, $filename);
        }
    }

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
                $filerecords =  $this->get_assessment_anotatepdf_files($itemid);
                foreach ($filerecords as $fr) {
                    $files = $fs->get_area_files($fr->contextid, $fr->component, $fr->filearea,  $fr->itemid);
                    foreach ($files as $file) {

                        // Naming convention would be LAST Name, FirstName, Year, Subject, Level, Component.
                        $extension = '.' . pathinfo($file->get_filename(), PATHINFO_EXTENSION);
                        $n = shorten_text($file->get_filename(), 30, false, $extension);
                        $notname  = $user->lastname . ' ' . $user->firstname . ' ' . $course->fullname . ' ' . $n;
                        $pathfilename =   $assessname . '/' . $notname;

                        $filesforzipping[$pathfilename] = $file;
                    }
                }
            }
        }

        // Remove folder
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
                $filerecords =  $this->get_assessment_feedback_files($itemid);
                foreach ($filerecords as $fr) {
                    $files = $fs->get_area_files($fr->contextid, $fr->component, $fr->filearea,  $fr->itemid);
                    foreach ($files as $file) {

                        if ($file->get_filename() == '.') continue;
                        $extension = '.' . pathinfo($file->get_filename(), PATHINFO_EXTENSION);
                        $n = shorten_text($file->get_filename(), 30, false, $extension);
                        $fname  =  $assessname . '/' . $user->lastname . ' ' . $user->firstname . ' ' . $course->fullname . ' ' . $n;
                        $pathfilename =    $fname;
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

    public function download_all_files($itemids, $id) {
        \core_php_time_limit::raise();
    }

    public function download_rubric($itemids, $cmids, $instaceids, $courseid, $frubricselection) {
        global $DB, $CFG;

        \core_php_time_limit::raise();

        $cmids = (array) json_decode($cmids);
        $instaceids = explode(',', $instaceids);
        $userpdfs = [];
        $frubricselection = (array) json_decode($frubricselection);

        // Construct the zip file name.
        $course = $DB->get_record('course', array('id' => $courseid));
        $dirname = clean_filename($course->fullname . '.zip'); // Main folder.

        $uitemids = json_decode($itemids);
        $userids = implode(',', array_column($uitemids, 'userid'));
        $sql = "SELECT id, firstname, lastname FROM {user} WHERE id in ($userids)";
        $users = $DB->get_records_sql($sql);
        $coursedetails = new \stdClass();
        $coursedetails->courseid = $courseid;
        $coursedetails->name  = $course->fullname;
        $assesmentsdetails = $this->get_assesments_with_grades($courseid);

        foreach ($frubricselection as $userid => $frubrics) {
            foreach ($frubrics as $frubric) {
                if ($frubric == null) continue;
                $frubric = json_decode($frubric);

                $assessname = $assesmentsdetails[$frubric->gradeid]->assignmentname;
                $rubric = $this->get_rubric($frubric->cmid, $courseid, $frubric->userid, $frubric->assignmentid);

                if ($rubric != '') {
                    $rubric = json_decode($rubric);

                    $jsonparts = explode('</div>', $rubric);
                    $table = $jsonparts[0];

                    $table = str_replace('<table class="criteria-table table-light ">', '<table "style=\'font-family:helvetica\'"> ', $table);
                    $table = str_replace(
                        '<input disabled type="checkbox" id ="" name = ""  value = "1" checked = "checked"  >',
                        '<span style=\'font-family:helvetica\'>&#9745;</span>',
                        $table
                    );

                    $totalgrade = $jsonparts[count($jsonparts) - 1];
                    $totalgrade = "<strong>TOTAL:  $totalgrade </strong>";
                    $rubric = $table . '<br> ' . $totalgrade;

                    $mpdf = new \Mpdf\Mpdf(['tempDir' => $CFG->tempdir . '/', 'assignment_', 'mode' => 's']);
                    $mpdf->SetFont('DejaVuSans', '', 9);
                    //   $mpdf->backupSubsFont = ['dejavusans'];
                    $mpdf->allow_charset_conversion = true;
                    $mpdf->WriteHTML($rubric);

                    $u = $users[$frubric->userid];
                    $pathfilename =  $assessname; //$u->firstname . $u->lastname . '/' .
                    $fd = new \stdClass();
                    $fd->filename = $frubric->rubricfilename;
                    $fd->pathfilename = $pathfilename;
                    $fd->pdf = $mpdf;
                    $userpdfs[$frubric->userid][] = $fd;
                }
            }
        }

        $this->save_rubricfiles($userpdfs, $dirname);
    }

    public function get_rubric($cmid, $courseid, $userid, $instanceid) {

        global $DB, $PAGE, $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        $context = \context_module::instance($cmid);
        $gradingmanager = get_grading_manager($context, 'mod_assign', 'submissions');

        if ($controller = $gradingmanager->get_active_controller()) {
            $methodname =  $DB->get_record('grading_areas', ['id' => $controller->get_areaid()], 'activemethod', IGNORE_MISSING);

            if ($methodname->activemethod == 'frubric') {  // Only works for flexible rubric.

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


                $fr = json_encode($controller->render_grade(
                    $PAGE,
                    $grade[0]->id,
                    $gradinginfo,
                    $gradebookgrade->str_long_grade,
                    true
                ));

                return $fr;
            }
        }


        return '';
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

    private function save_rubricfiles($pdfs, $dirname) {
        $workdir = make_temp_directory('report_assing_fdownloader/zipfrubric');

        // Create the zip
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
        die(); // if not set, a invalid zip file error is thrown.
    }


    // Get the file extension.  https://docs.w3cub.com/http/basics_of_http/mime_types/complete_list_of_mime_types.html
    // Mac users sometimes dont have the extension in the file. To avoid issues, pick up the mimetype of the file
    // and get the extension from it/.
    private function getextension($mime) {
        $mime_map = [
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

        return isset($mime_map[$mime]) === true ? $mime_map[$mime] : false;
    }
}
