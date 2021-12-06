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

    public function get_assessments_by_course($assessmentids) {
        global $DB;

        $sql = "SELECT grades.id as gradeid, u.id as userid, u.firstname, u.lastname, grades.assignment as assignmentid, assign.name as 'assignmentname'
                FROM {assign_grades} AS grades
                JOIN {assign} as assign ON grades.assignment = assign.id
                JOIN {user} as u ON grades.userid = u.id
                WHERE grades.assignment  IN ($assessmentids)
                AND grades.grade != -1.00000
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
                    $pathfilename = $user->firstname . $user->lastname . '/' . $fr->name . $file->get_filepath() . $file->get_filename();
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

                        // Naming convention would be LAST Name, FirstName, Year, Subject, Level Component.
                        $date = new \DateTime();
                        $date->setTimestamp(intval($file->get_timecreated()));
                        $year =  userdate($date->getTimestamp(), '%Y');
                        $extension = '.' . pathinfo($file->get_filename(), PATHINFO_EXTENSION);
                        $notname  = $this->clean_custom($user->lastname . ' ' . $user->firstname . ' ' . $year . ' ' . $assessname . $extension);
                        $pathfilename = $user->firstname . $user->lastname . '/' . $assessname . $file->get_filepath() . $notname;

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
            //print_object($itemid); exit;
            foreach ($uitemid->uitemids as $itemid) {
                $assessname = $assesmentsdetails[$itemid]->assignmentname;
                $filerecords =  $this->get_assessment_feedback_files($itemid);
                foreach ($filerecords as $fr) {
                    $files = $fs->get_area_files($fr->contextid, $fr->component, $fr->filearea,  $fr->itemid);
                    foreach ($files as $file) {
                        $pathfilename = $user->firstname . $user->lastname . '/' . $assessname . $file->get_filepath() . $file->get_filename();
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
     * Helper function to replace "special" characters with regular ones in filenames.
     *
     * @param String $filename filename which to clean up
     * @return String the clean filename, without special characters
     */
    protected function clean_custom($filename) {

        $cleanfilenameuserpref = get_user_preferences('clean_filerenaming', '');

        if ((isset($cleanfilenameuserpref) && $cleanfilenameuserpref)) {
            $replace = array(
                'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 's', ' ' => '_'
            );

            $o = preg_replace('/[^A-Za-z0-9\_\-\.]/', '', strtr($filename, $replace));
            return clean_filename($o);
        } else {
            return clean_filename($filename);
        }
    }
}
