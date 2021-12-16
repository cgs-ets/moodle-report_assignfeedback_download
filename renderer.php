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
 * @package    report_assignfeedback_download
 * @copyright  2021 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use report_assignfeedback_download\reportmanager;


class report_assignfeedback_download_renderer extends plugin_renderer_base {

    const SUBMISSION     = 'submission';
    const FEEDBACK       = 'feedback';
    const ANNOTATEPDF    = 'annotatedpdf';
    const EMBEDDEDFILEINCOMMENT = 'embeddedfileincomment';



    public function __construct(moodle_page $page, $target) {

        $this->manager = new reportmanager();

        parent::__construct($page, $target);
    }

    public function render_no_assessment_in_course() {
        $templatename = 'report_assignfeedback_download/no_result';
        echo $this->render_from_template($templatename, '');
    }

    public function render_assignfeedback_download($courseid, $assessmentids, $url, $moduleid, $filter = false) {

        $templatename = 'report_assignfeedback_download/main';

        $context = $this->get_table_context($courseid, $assessmentids, $url, $moduleid, $filter);

        echo $this->render_from_template($templatename, $context);
    }

    public function render_assessement_files_tree(assessement_files_tree $tree, $htmlid, $filetype) {

        if (empty($tree->dir['subdirs']) && empty($tree->dir['files'])) {
            $html = $this->output->box(get_string('nofilesavailable', 'repository'));
        } else {
            $this->page->requires->js_init_call('M.report_assignfeedback_download.init_tree', array(false, $htmlid), true); //$module
            $html = '<div id="' . $htmlid . '">';
            $html .= $this->htmllize_tree($tree, $tree->dir, $filetype);
            $html .= '</div>';
        }
        return $html;
    }

    /**
     * Internal function - creates htmls structure suitable for YUI tree.
     */
    protected function htmllize_tree($tree, $dir, $filetype) {

        global $CFG;
        $yuiconfig = array();
        $yuiconfig['type'] = 'html';

        if (empty($dir['subdirs']) and empty($dir['files'])) {
            return '';
        }
        $result = '<ul>';
        foreach ($dir['subdirs'] as $subdir) {
            $image = $this->output->pix_icon(file_folder_icon(), $subdir['dirname'], 'moodle', array('class' => 'icon'));
            $result .= '<li yuiConfig=\'' . json_encode($yuiconfig) . '\'><div>' . $image . s($subdir['dirname']) . '</div> ' . $this->htmllize_tree($tree, $subdir, $filetype) . '</li>';
        }
        foreach ($dir['files'] as $file) {

            switch ($filetype) {
                case self::SUBMISSION:
                    $url = moodle_url::make_file_url("{$CFG->wwwroot}/pluginfile.php", "/{$tree->contextid}/assignsubmission_file/{$tree->filearea}/{$tree->itemid}" . $file->get_filepath() . $file->get_filename(), true);
                    break;
                case self::FEEDBACK:
                    $url = moodle_url::make_file_url("{$CFG->wwwroot}/pluginfile.php", "/{$tree->contextid}/assignfeedback_file/{$tree->filearea}/{$tree->itemid}" . $file->get_filepath() . $file->get_filename(), true);
                    break;
                case self::ANNOTATEPDF:
                    $url = moodle_url::make_file_url("{$CFG->wwwroot}/pluginfile.php", "/{$tree->contextid}/assignfeedback_editpdf/{$tree->filearea}/{$tree->itemid}" . $file->get_filepath() . $file->get_filename(), true);
                    break;
                case self::EMBEDDEDFILEINCOMMENT:
                    $url = moodle_url::make_file_url("{$CFG->wwwroot}/pluginfile.php", "/{$tree->contextid}/assignfeedback_comments/{$tree->filearea}/{$tree->itemid}" . $file->get_filepath() . $file->get_filename(), true);
                    break;
            }

            $filename = $file->get_filename();
            $image = $this->output->pix_icon(file_file_icon($file), $filename, 'moodle', array('class' => 'icon'));
            $result .= '<li yuiConfig=\'' . json_encode($yuiconfig) . '\'><div>' . html_writer::link($url, $image . $filename) . '</div></li>';
        }
        $result .= '</ul>';

        return $result;
    }

    protected function htmlize_rubric($tree, $rubricname, $img) {
        $yuiconfig = array();
        $yuiconfig['type'] = 'html';
        $result = '<ul>'; 
        $image = $this->output->pix_icon('icon','frubric', 'report_assignfeedback_download');
        $result .= '<li yuiConfig=\'' . json_encode($yuiconfig) . '\'><div>' . html_writer::span($image . $rubricname, 'frubric-container') . '</div></li>';

        return $result;
    }

    private function get_active_users($context) {
        return  get_enrolled_users(
            $context,
            "mod/assign:submit",
            null,
            'u.*',
            'firstname',
            null,
            null,
            show_only_active_users($context)
        );
    }

    protected function get_table_context($courseid, $assessmentids, $url, $moduleid, $filter = false) {
        $context = context_course::instance($courseid);

        $users = $this->get_active_users($context);

        $userscontext = $this->get_users_context($courseid, $assessmentids);

        $context = [
            'users' => $userscontext['users'],
            'sessionkey' => sesskey(),
            'actionurl' => $url,
            'id' => $courseid,
            'cmid' => $moduleid,
            'formid' => 'downloadactionform',
            'noresult' => count($users) == 0,
            'assignids' => $userscontext['assignids'],
            'filter' => $filter
        ];

        return $context;
    }

    public function get_users_context($courseid, $assessmentids) {
        global  $CFG;
        $context = context_course::instance($courseid);

        $activeusers = $this->get_active_users($context);
        $assessids = $this->manager->get_assessment_ids($courseid, $assessmentids);
        $assessments = $this->manager->get_assessments_by_course($assessids);

        $cmids = $this->manager->get_course_module($courseid, $assessids);

        $users = ['users' => []];

        foreach ($assessments as $assess) {
          
            $user = $activeusers[$assess->userid];
            $user->namelastname = $this->output->user_picture($user, array(
                'course' => $courseid,
                'includefullname' => true, 'class' => 'userpicture'
            ));


            $userassessment = new \stdClass();
            $userassessment->assignmentid =  $assess->assignmentid;
            $userassessment->assignmentname = $assess->assignmentname;
            $cmid = $cmids[$assess->assignmentid]->cmid;
            $userassessment->assignmentnameurl = new moodle_url("$CFG->wwwroot/mod/assign/view.php", ['id' => $cmid]);
            $userassessment->submtree = $this->get_assessment_submission_files_tree($user->id, $courseid, $assess->assignmentid);
            $userassessment->annottedpdftree = $this->get_assessment_anotatepdf_files_tree($assess->gradeid);
            $userassessment->feedbackfiletree = $this->get_assessment_feedback_files_tree($assess->gradeid);
            $feedbackcomment = $this->get_assessment_feedback_comments($assess->gradeid, $assess->userid);

            if (gettype($feedbackcomment) == "array") {
                $userassessment->feedbackcomment = $this->get_assessment_feedback_comments($assess->gradeid, $assess->userid);
            } else {
                $userassessment->feedbackcommentxt = $this->get_assessment_feedback_comments($assess->gradeid, $assess->userid);
            }

            $userassessment->finalgrade = $this->manager->get_final_grade($assess->gradeid, $assess->userid);
            $userassessment->frubric = 0;
            //$rubric = $this->manager->get_rubric($cmid, $assess->gradeid, $courseid, $assess->userid, $assess->assignmentid);
             $this->get_assessment_frubric_tree($cmid, $courseid, $assess, $userassessment, $user);
            // if ($rubric != '') {
            //     $userassessment->frubric = 1;
            //     $userassessment->frubricicon = $this->output->image_url('icon', 'report_assignfeedback_download');
            //     $userassessment->rubric = $rubric;
            //     $date = new \DateTime();
            //     $date->setTimestamp(intval($assess->duedate));
            //     $year =  userdate($date->getTimestamp(), '%Y');
            //     $userassessment->rubricfilename = $user->firstname . $user->lastname . $year . 'FinalCriteria';
            // }
            if (!isset($user->itemids)) {
                $user->itemids = '';
            }

            $user->itemids .= $assess->gradeid . ','; // Call it itemid because it is called like that in other tables.

            // If there are no files at all, dont show the student. TODO
            if (empty($userassessment->submtree['tree']) && empty($userassessment->annottedpdftree['tree'])  && empty($userassessment->feedbackfiletree['tree'])) {
                // if (isset($users['users'][$assess->userid])) {
                //     unset($users['users'][$assess->userid]);
                // }
            } else {

                if (!isset($users['users'][$assess->userid])) {
                    $user->assessments[] = $userassessment;
                    $users['users'][$assess->userid] = $user;
                } else {
                    ($users['users'][$assess->userid]->assessments)[] = $userassessment;
                }
            }
        }

        $users = array_values($users['users']);
        usort($users, "sort_by_firstname");

        if (isset($users[0])) {
            ($users[0])->firstuser = 1; // Only display the inner table's header on the first user.
        }

        $context = [
            'users' =>  $users,
            'assignids' => $assessids
        ];

        return $context;
    }



    protected function get_assessment_submission_files_tree($userid, $courseid, $assignmentid) {

        $results = $this->manager->get_assessment_submission_records($userid, $courseid, $assignmentid);

        $trees = ['tree' => []];

        foreach ($results as $result) {
            $t = new assessement_files_tree($result->contextid, $result->component, $result->filearea,  $result->itemid);
            $tree = new \stdClass();
            $htmlid = 'assessement_files_tree_' . uniqid();
            $tree->submissionfiletree =  $this->render_assessement_files_tree($t, $htmlid, self::SUBMISSION);
            $trees['tree'] = $tree;
        }

        return $trees;
    }

    protected function get_assessment_anotatepdf_files_tree($itemid) {


        $results = $this->manager->get_assessment_anotatepdf_files($itemid);
        $trees = ['tree' => []];

        foreach ($results as $result) {
            $t = new assessement_files_tree($result->contextid, $result->component, $result->filearea,  $result->itemid);
            $tree = new \stdClass();
            $htmlid = 'assessement_feedback_files_tree_' . uniqid();
            $tree->feedbackfiletree =  $this->render_assessement_files_tree($t, $htmlid, self::ANNOTATEPDF);
            $trees['tree'] = $tree;
        }


        return $trees;
    }

    protected function get_assessment_feedback_files_tree($itemid) {

        $results =  $this->manager->get_assessment_feedback_files($itemid);
        $trees = ['tree' => []];

        foreach ($results as $result) {
            $t = new assessement_files_tree($result->contextid, $result->component, $result->filearea,  $result->itemid);
            $tree = new \stdClass();
            $htmlid = 'assessement_feedback_files_tree_' . uniqid();
            $tree->feedbackfiletree =  $this->render_assessement_files_tree($t, $htmlid, self::FEEDBACK);
            $trees['tree'] = $tree;
        }

        return $trees;
    }

    protected function get_assessment_feedback_comments($itemid, $userid) {

        list($commentonlytext, $commentwithfile) = $this->manager->get_assessment_feedback_comments($itemid, $userid);
        $comments = '';
        if (count($commentwithfile) > 0) {
            $trees = ['tree' => []];
            foreach ($commentwithfile as $result) {
                $t = new assessement_files_tree($result->contextid, $result->component, $result->filearea,  $itemid);
                $tree = new \stdClass();
                $htmlid = 'assessement_feedback_files_tree_' . uniqid();
                $tree->feedbackfiletree =  $this->render_assessement_files_tree($t, $htmlid, self::EMBEDDEDFILEINCOMMENT);
                $trees['tree'] = $tree;
            }

            return $trees;
        } else if (count($commentonlytext) > 0) {
            foreach ($commentonlytext as $comment) {
                $comments .= $comment->commenttext;
            }
            return $comments;
        }
    }

    protected function get_assessment_frubric_tree($cmid,  $courseid, $assess, &$userassessment, $user) {
        $rubric = $this->manager->get_rubric($cmid, $assess->gradeid, $courseid, $assess->userid, $assess->assignmentid);

        if ($rubric != '') {

            $trees = ['tree' => []]; 
            $userassessment->frubric = 1;
            $userassessment->frubricicon = $this->output->image_url('icon', 'report_assignfeedback_download');
            $userassessment->rubric = $rubric;
            $date = new \DateTime();
            $date->setTimestamp(intval($assess->duedate));
            $year =  userdate($date->getTimestamp(), '%Y');
            $userassessment->rubricfilename = $user->firstname . $user->lastname . $year . 'FinalCriteria';
            $tree = new \stdClass();
            $htmlid = 'assessement_frubric_tree_' . uniqid();
            $this->page->requires->js_init_call('M.report_assignfeedback_download.init_tree', array(false, $htmlid), true);
            $html = '<div id="' . $htmlid . '">';
            $html .= $this->htmlize_rubric($tree, $userassessment->rubricfilename, $this->output->image_url('icon', 'report_assignfeedback_download'));
            $html .= '</div>';
            $userassessment->frubrictree = $html;
            //print_object($html);
        }  
    }
}

class assessement_files_tree implements renderable {
    public $dir;
    public $contextid;
    public $files;
    public $itemid;
    public $filearea;

    public function __construct($contextid, $component, $filearea, $itemid) {
        $fs = get_file_storage();
        $this->dir = $fs->get_area_tree($contextid, $component, $filearea, $itemid);
        $this->contextid = $contextid;
        $this->filearea = $filearea;
        $this->component = $component;
        $this->itemid = $itemid;
    }
}
