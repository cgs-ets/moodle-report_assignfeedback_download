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

    const SUBMISSION                      = 'submission';
    const FEEDBACK                        = 'feedback';
    const ANNOTATEPDF                     = 'annotatedpdf';
    const GRADING_METHOD_FRUBRIC          = 'frubric';
    const EMBEDDEDFILEINCOMMENT           = 'embeddedfileincomment';

    public function __construct(moodle_page $page, $target) {

        $this->manager = new reportmanager();

        parent::__construct($page, $target);
    }

    public function render_no_assessment_in_course() {
        $templatename = 'report_assignfeedback_download/no_result';
        echo $this->render_from_template($templatename, '');
    }

    public function render_assignfeedback_download($courseid, $assessmentids, $url, $moduleid, $filter = false, $coursename) {

        $templatename = 'report_assignfeedback_download/main';
        $context = $this->get_table_context($courseid, $assessmentids, $url, $moduleid, $filter, $coursename);
        echo $this->render_from_template($templatename, $context);
    }

    public function render_assessement_files_tree(assessement_files_tree $tree, $htmlid, $filetype) {

        if (empty($tree->dir['subdirs']) && empty($tree->dir['files'])) {
            $html = $this->output->box(get_string('nofilesavailable', 'repository'));
        } else {
            $this->page->requires->js_init_call('M.report_assignfeedback_download.init_tree', array(false, $htmlid), true);
            $html  = '<div id="' . $htmlid . '">';
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

        if (empty($dir['subdirs']) && empty($dir['files'])) {
            return '';
        }
        $result = '<ul>';
        foreach ($dir['subdirs'] as $subdir) {
            $image = $this->output->pix_icon(file_folder_icon(), $subdir['dirname'], 'moodle', array('class' => 'icon'));
            $result .= '<li yuiConfig=\'' . json_encode($yuiconfig) . '\'><div>' . $image . s($subdir['dirname']) . '</div> ' . $this->htmllize_tree($tree, $subdir, $filetype) . '</li>';
        }
        $urlbase = "{$CFG->wwwroot}/pluginfile.php";
        foreach ($dir['files'] as $file) {

            switch ($filetype) {
                case self::SUBMISSION:
                    $url = moodle_url::make_file_url($urlbase,
                    "/{$tree->contextid}/assignsubmission_file/{$tree->filearea}/{$tree->itemid}"
                    . $file->get_filepath()
                    . $file->get_filename(),
                    true);
                    break;
                case self::FEEDBACK:
                    $url = moodle_url::make_file_url($urlbase,
                    "/{$tree->contextid}/assignfeedback_file/{$tree->filearea}/{$tree->itemid}"
                    . $file->get_filepath()
                    . $file->get_filename(),
                    true);
                    break;
                case self::ANNOTATEPDF:
                    $url = moodle_url::make_file_url($urlbase,
                    "/{$tree->contextid}/assignfeedback_editpdf/{$tree->filearea}/{$tree->itemid}"
                    . $file->get_filepath()
                    . $file->get_filename(),
                    true);
                    break;
                case self::EMBEDDEDFILEINCOMMENT:
                    $url = moodle_url::make_file_url($urlbase,
                    "/{$tree->contextid}/assignfeedback_comments/{$tree->filearea}/{$tree->itemid}"
                    . $file->get_filepath()
                    . $file->get_filename(),
                    true);
                    break;
            }

            $filename = shorten_text($file->get_filename(), 10);
            $image = $this->output->pix_icon(file_file_icon($file), $filename, 'moodle', array('class' => 'icon'));
            $result .= '<li yuiConfig=\'' . json_encode($yuiconfig) . '\'><div>' . html_writer::link($url, $image . $filename) . '</div></li>';
        }
        $result .= '</ul>';

        return $result;
    }

    protected function htmlize_rubric($rubricname, $image, $type) {
        $yuiconfig = array();
        $yuiconfig['type'] = 'html';
        $result = '<ul>';
        $title = '';
        if ($type == self::GRADING_METHOD_FRUBRIC) {
            $title = get_string('frubric_desc', 'report_assignfeedback_download');
        }

        $result .= '<li yuiConfig=\'' . json_encode($yuiconfig) . '\'><div>'
        . html_writer::span($image . $rubricname, 'frubric-container',
        ['title' => $title])
        . '</div></li>';
        return $result;
    }

    private function get_active_users($context) {

        $extrafields = \core_user\fields::for_identity($context)->get_required_fields();
        $ufields = \core_user\fields::for_userpic()->including(...$extrafields);
        $ufields = $ufields->get_sql('u', false, '', '', false)->selects;
        return  get_enrolled_users(
            $context,
            "mod/assign:submit",
            null,
            $ufields,
            'firstname',
            0,
            0,
            true
        );
    }

    protected function get_table_context($courseid, $assessmentids, $url, $moduleid, $filter = false, $coursename) {

        $context = context_course::instance($courseid);
        $userscontext = $this->get_users_context($courseid, $assessmentids, $coursename);

        $context = [
            'users'         => $userscontext['users'],
            'sessionkey'    => sesskey(),
            'actionurl'     => $url,
            'id'            => $courseid,
            'cmid'          => $moduleid,
            'formid'        => 'downloadactionform',
            'noresult'      => count($userscontext['users']) == 0, // Get the students that have assigments graded.
            'assignids'     => $userscontext['assignids'],
            'cmids'         => $userscontext['cmids'],
            'filter'        => $filter,
            // Enable download option only if there is at least one file to download.
            'noexistsubmissions'            => $userscontext['noexistsubmissions'],
            'noexistfeedbackfiles'          => $userscontext['noexistfeedbackfiles'],
            'noexistannotatedpdffiles'      => $userscontext['noexistannotatedpdffiles'],
            'noexistfrubrics'               => $userscontext['noexistfrubrics'],
            'noexistsubmissiononlinetext'   => $userscontext['noexistsubmissiononlinetext'],
            'noexistfeedbackcomments'       => $userscontext['noexistfeedbackcommentfiles'],
            'noexistgrades'                 => $userscontext['noexistgrades']

        ];

        return $context;
    }

    public function get_users_context($courseid, $assessmentids, $coursename) {
        global  $CFG;

        $context = context_course::instance($courseid);

        $activeusers    = $this->get_active_users($context);
        $assessids      = $this->manager->get_assessment_ids($courseid, $assessmentids);
        $assessments    = $this->manager->get_assessments($assessids);
        $cmids          = $this->manager->get_course_module($courseid, $assessids);
        $cmidsaux       = [];
        $rubricparams   = [];
        $users          = ['users' => []];
        // Count the number of files to enable the download type.
        $countsubmissionfiles       = 0;
        $countsubmissiononlinetxt   = 0;
        $countfeedbackfiles         = 0;
        $countfeedbackcommentfiles  = 0;
        $countannotatedpdffiles     = 0;
        $countfrubricfiles          = 0;
        $countgradedsubmissions     = 0;

        foreach ($assessments as $assess) {

            if (!isset($activeusers[$assess->userid])) {
                continue;
            }
            $user = $activeusers[$assess->userid];

            if (!isset($user)) {
                continue;
            }

            $user->namelastname = $this->output->user_picture($user, array(
                'course' => $courseid,
                'includefullname' => true, 'class' => 'userpicture'
            ));

            $userassessment                         = new \stdClass();
            $userassessment->assignmentid           = $assess->assignmentid;
            $userassessment->assignmentname         = $assess->assignmentname;
            $cmid                                   = $cmids[$assess->assignmentid]->cmid;
            $userassessment->assignmentnameurl      = new moodle_url("$CFG->wwwroot/mod/assign/view.php", ['id' => $cmid]);
            $userassessment->submtree               = $this->get_assessment_submission_files_tree($user->id, $courseid, $assess->assignmentid);
            $userassessment->onlinetextsubmission   = $this->get_assessment_submission_onlinetext($assess->assignmentid, $user->id);

            // If student didnt submit anything, then dont display.
            if (!isset(($userassessment->submtree['tree'])->submissionfiletree)
                && $userassessment->onlinetextsubmission == ''
                && !isset($assess->grade)) {
                continue;
            }

            if ($userassessment->onlinetextsubmission != '') {
                $countsubmissiononlinetxt++;
                $userassessment->onlinetxtsubmissionview = true;
                $sid = $this->manager->get_assesment_submission_id($assess->assignmentid, $user->id);
                $urlparams  = array(
                            'id'        => $cmid,
                            'sid'       => $sid,
                            'gid'       => $sid,
                            'plugin'    => 'onlinetext',
                            'action'    => 'viewpluginassignsubmission');
                $url                          = new moodle_url('/mod/assign/view.php', $urlparams);
                $userassessment->onlinetxturl = $url;
            }

            if (isset(($userassessment->submtree['tree'])->submissionfiletree)) {

                $countsubmissionfiles++;
            }

            if (isset($assess->gradeid)) {

                $userassessment->annottedpdftree    = $this->get_assessment_anotatepdf_files_tree($assess->gradeid);
                $userassessment->feedbackfiletree   = $this->get_assessment_feedback_files_tree($assess->gradeid);
                $userassessment->feedbackcommentxt  = $this->get_assessment_feedback_comments($assess->gradeid, $assess->userid);

                // Check if the trees have files.
                if (isset(($userassessment->annottedpdftree['tree'])->feedbackfiletree)) {
                    $countannotatedpdffiles++;
                }

                if (isset(($userassessment->feedbackfiletree['tree'])->feedbackfiletree)) {
                    $countfeedbackfiles++;
                }

                if ($userassessment->feedbackcommentxt != '') {
                    $countfeedbackcommentfiles++;
                    $userassessment->feedbackview = true;
                    $urlparams = array(
                        'id' => $cmid,
                        'sid' => $assess->gradeid,
                        'gid' => $assess->gradeid,
                        'plugin' => 'comments',
                        'action' => 'viewpluginassignfeedback',
                    );
                    $url = new moodle_url('/mod/assign/view.php', $urlparams);
                    $userassessment->url = $url;
                }

                $grade = $assess->grade > 0 ? number_format($assess->grade, 2, '.', '') : "0.00";
                $maxgrade = number_format($assess->gradeoutof, 2, '.', '');
                $userassessment->finalgrade = "$grade/$maxgrade";
                $userassessment->frubric = 0;
                $showfrubricicon         = false;

                if ($assess->grade > 0) {
                    $showfrubricicon = true;
                }

                if ($showfrubricicon) {

                    $fr = $this->get_assessment_advanced_grading_tree($cmid,
                    $courseid,
                    $assess,
                    $userassessment,
                    $user, $cmidsaux,
                    $coursename);
                    $supported = in_array($this->manager->get_active_grading_method($cmid), SUPPORTED_ADVANCED_GRADING_METHODS);

                    if ($fr != ''  && $supported) {
                        $rubricparams[] = $fr;
                        $countfrubricfiles++;
                        $countgradedsubmissions++; // Only enable download grades if there are frubrics or rubrics saved.
                    }
                }

                if (!isset($user->itemids)) {
                    $user->itemids = '';
                }

                $user->itemids .= $assess->gradeid . ','; // Call it itemid because it is called like that in other tables.
            }

            if (!isset($users['users'][$assess->userid])) {
                $user->assessments[]             = $userassessment;
                $users['users'][$assess->userid] = $user;
            } else {
                $asseaux = $users['users'][$assess->userid]->assessments;
                $checkexistance = $this->remove_duplicate_assessment($asseaux, $userassessment);
                if (!$checkexistance) {
                    ($users['users'][$assess->userid]->assessments)[] = $userassessment;
                }
            }

        }

        $users = array_values($users['users']);
        usort($users, "report_assignfeedback_download_sort_by_firstname");

        $cmidsaux        = json_encode($cmidsaux);
        $rubricparams    = json_encode($rubricparams);

        if (isset($users[0])) {
            ($users[0])->firstuser = 1; // Only display the inner table's header on the first user.
        }

        $context = [
            'users'                         => $users,
            'assignids'                     => $assessids,
            'cmids'                         => $cmidsaux,
            'noexistsubmissions'            => $countsubmissionfiles == 0,
            'noexistfeedbackcommentfiles'   => $countfeedbackcommentfiles == 0,
            'noexistfeedbackfiles'          => $countfeedbackfiles == 0,
            'noexistannotatedpdffiles'      => $countannotatedpdffiles == 0,
            'noexistfrubrics'               => $countfrubricfiles == 0,
            'noexistsubmissiononlinetext'   => $countsubmissiononlinetxt == 0,
            'noexistgrades'                 => $countgradedsubmissions == 0
        ];

        return $context;
    }

    // By allowing graded and ungraded assessments, they can duplicate.
    private function remove_duplicate_assessment($assessments, $assessmentoadd) {

        foreach ($assessments as $assessment) {
            if ($assessment->assignmentid == $assessmentoadd->assignmentid) {
                return true;
            }
        }

        return false;
    }

    protected function get_assessment_submission_files_tree($userid, $courseid, $assignmentid) {

        $results = $this->manager->get_assessment_submission_records($userid, $courseid, $assignmentid);

        $trees = ['tree' => []];

        foreach ($results as $result) {
            $t                          = new assessement_files_tree($result->contextid, $result->component, $result->filearea,  $result->itemid);
            $tree                       = new \stdClass();
            $htmlid                     = 'assessement_files_tree_' . uniqid();
            $tree->submissionfiletree   = $this->render_assessement_files_tree($t, $htmlid, self::SUBMISSION);
            $trees['tree']              = $tree;
        }

        return $trees;
    }

    protected function get_assessment_anotatepdf_files_tree($itemid) {

        $results = $this->manager->get_assessment_anotatepdf_files($itemid);
        $trees = ['tree' => []];

        foreach ($results as $result) {
            $t                      = new assessement_files_tree($result->contextid, $result->component, $result->filearea,  $result->itemid);
            $tree                   = new \stdClass();
            $htmlid                 = 'assessement_feedback_files_tree_' . uniqid();
            $tree->feedbackfiletree = $this->render_assessement_files_tree($t, $htmlid, self::ANNOTATEPDF);
            $trees['tree']          = $tree;
        }

        return $trees;
    }

    protected function get_assessment_feedback_files_tree($itemid) {

        $results = $this->manager->get_assessment_feedback_files($itemid);
        $trees = ['tree' => []];

        foreach ($results as $result) {
            $t                      = new assessement_files_tree($result->contextid, $result->component, $result->filearea,  $result->itemid);
            $tree                   = new \stdClass();
            $htmlid                 = 'assessement_feedback_files_tree_' . uniqid();
            $tree->feedbackfiletree = $this->render_assessement_files_tree($t, $htmlid, self::FEEDBACK);
            $trees['tree']          = $tree;
        }

        return $trees;
    }

    protected function get_assessment_feedback_comments($itemid, $userid) {

        $arraycomments   = $this->manager->get_assessment_feedback_comments($itemid, $userid);
        $comments        = '';

        foreach ($arraycomments as $ac) {

            $comments .= $ac;
        }
        return $comments;
    }

    protected function get_assessment_submission_onlinetext($itemid, $userid) {
        $arrayonlinetext = $this->manager->get_assessment_submission_onlinetext($itemid,  $userid, false);
        $onlinetext      = '';

        foreach ($arrayonlinetext as $aolt) {
            $onlinetext .= $aolt;
        }
        return $onlinetext;

    }

    private function get_assessment_frubric_tree($cmid, $courseid, $assess, &$userassessment, $user, &$cmidcollection, $coursename, &$rubricparams) {

        $rubric = $this->manager->get_advanced_method($cmid, $courseid, $assess->userid, $assess->assignmentid);

        if (isset($rubric['frubric'])) {

            $userassessment->frubric = 1;
            $date                    = new \DateTime();

            $date->setTimestamp(intval($assess->duedate));
            $year = userdate($date->getTimestamp(), '%Y');
            $year = $year == 1970 ? date("Y") : $year; // When the assessment doesnt have a due date.
            // Naming convention: Student name_CourseName_AssessmentName.
            $rubricfilename = $user->firstname . ' ' . $user->lastname . ' ' . $coursename . ' ' . $assess->assignmentname . '.pdf';
            $userassessment->rubricfilename      = $rubricfilename;
            $rubricparams->rubricfilename        = $userassessment->rubricfilename;
            $userassessment->rubricparams        = json_encode($rubricparams);

            $htmlid  = 'assessement_frubric_tree_' . uniqid();
            $this->page->requires->js_init_call('M.report_assignfeedback_download.init_tree', array(false, $htmlid), true);
            $html    = '<div id="' . $htmlid . '">';
            $rubricname = shorten_text($userassessment->rubricfilename, 20);
            $icon    = $this->output->pix_icon('frubric-icon', '', 'report_assignfeedback_download');
            $html   .= $this->htmlize_rubric($rubricname, $icon, 'frubric');
            $html   .= '</div>';

            $userassessment->frubrictree           = $html;
            $cmidcollection[$assess->assignmentid] = $cmid;

            return $rubricparams;
        }
    }

    private function get_assessment_rubric_tree($cmid, $assess, &$userassessment, &$cmidcollection, $rubricname) {
            $userassessment->rubric = 1;

            $htmlid     = 'assessement_rubric_tree_' . uniqid();
            $this->page->requires->js_init_call('M.report_assignfeedback_download.init_tree', array(false, $htmlid), true);
            $html       = '<div id="' . $htmlid . '">';
            // $rubricname = get_string('rubric', 'report_assignfeedback_download');
            $icon       = $this->output->pix_icon('rubric-icon', '', 'report_assignfeedback_download');
            $html      .= $this->htmlize_rubric($rubricname, $icon, 'rubric');
            $html      .= '</div>';

            $userassessment->rubrictree              = $html;
            $cmidcollection[$assess->assignmentid]   = $cmid;

    }

    private function get_assessment_advanced_grading_tree($cmid, $courseid, $assess, &$userassessment, $user, &$cmidcollection, $coursename) {
        $rubric = $this->manager->get_advanced_method($cmid, $courseid, $assess->userid, $assess->assignmentid);
        $rubricparams               = new \stdClass();
        $rubricparams->cmid         = $cmid;
        $rubricparams->courseid     = $courseid;
        $rubricparams->userid       = $assess->userid;
        $rubricparams->assignmentid = $assess->assignmentid;
        $rubricparams->gradeid      = $assess->gradeid;

        if (isset($rubric['frubric'])) {
            $this->get_assessment_frubric_tree($cmid, $courseid, $assess, $userassessment, $user, $cmidcollection, $coursename, $rubricparams);
        } else if (isset($rubric['rubric']) || isset($rubric['guide'])) {
            $mn = isset($rubric['rubric']) ? get_string('rubric', 'report_assignfeedback_download') : get_string('mguide', 'report_assignfeedback_download');
            $this->get_assessment_rubric_tree($cmid, $assess, $userassessment, $cmidcollection, $mn);
            $userassessment->rubricparams = json_encode($rubricparams);
        }

        return $rubricparams;
    }


}

class assessement_files_tree implements renderable {
    public $dir;
    public $contextid;
    public $files;
    public $itemid;
    public $filearea;

    public function __construct($contextid, $component, $filearea, $itemid) {
        $fs                 = get_file_storage();
        $this->dir          = $fs->get_area_tree($contextid, $component, $filearea, $itemid);
        $this->contextid    = $contextid;
        $this->filearea     = $filearea;
        $this->component    = $component;
        $this->itemid       = $itemid;
    }
}
