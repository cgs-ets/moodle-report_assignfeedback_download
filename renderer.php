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
 * Print private files tree
 *
 * @package    report_ibassessment
 * @copyright  2010 Dongsheng Cai <dongsheng@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class report_ibassessment_renderer extends plugin_renderer_base {

    /**
     * Prints private files tree view
     * @return string
     */
    public function ibassessement_table() {
        // return $this->render(new assessement_files_tree);
    }

    public function render_ibassessmentreport($courseid) {

        $templatename = 'report_ibassessment/main';

        $context = $this->get_table_context($courseid);
        echo $this->render_from_template($templatename, $context);
    }

    public function render_assessement_files_tree(assessement_files_tree $tree) {
        $module = array('name' => 'report_ibassessment', 'fullpath' => '/report/ibassessment/module.js', 'requires' => array('yui2-treeview'));
        if (empty($tree->dir['subdirs']) && empty($tree->dir['files'])) {
            $html = $this->output->box(get_string('nofilesavailable', 'repository'));
        } else {
            $htmlid = 'assessement_files_tree_' . uniqid();
            $this->page->requires->js_init_call('M.report_ibassessment.init_tree', array(false, $htmlid), true, $module);
            $html = '<div id="' . $htmlid . '">';
            $html .= $this->htmllize_tree($tree, $tree->dir);
            $html .= '</div>';
        }
        return $html;
    }

    /**
     * Internal function - creates htmls structure suitable for YUI tree.
     */
    protected function htmllize_tree($tree, $dir) {

        global $CFG;
        $yuiconfig = array();
        $yuiconfig['type'] = 'html';

        if (empty($dir['subdirs']) and empty($dir['files'])) {
            return '';
        }
        $result = '<ul>';
        foreach ($dir['subdirs'] as $subdir) {
            $image = $this->output->pix_icon(file_folder_icon(), $subdir['dirname'], 'moodle', array('class' => 'icon'));
            $result .= '<li yuiConfig=\'' . json_encode($yuiconfig) . '\'><div>' . $image . s($subdir['dirname']) . '</div> ' . $this->htmllize_tree($tree, $subdir) . '</li>';
        }
        foreach ($dir['files'] as $file) {

            $url = file_encode_url("$CFG->wwwroot/pluginfile.php", '/' . $tree->contextid . '/assignsubmission_file/' . $tree->filearea . '/' . $tree->itemid . $file->get_filepath() . $file->get_filename(), true);
            $filename = $file->get_filename();
            $image = $this->output->pix_icon(file_file_icon($file), $filename, 'moodle', array('class' => 'icon'));
            $result .= '<li yuiConfig=\'' . json_encode($yuiconfig) . '\'><div>' . html_writer::link($url, $image . $filename) . '</div></li>';
        }
        $result .= '</ul>';

        return $result;
    }

    protected function get_table_context($courseid) {
        $context = context_course::instance($courseid);

        $users = get_enrolled_users($context, 'mod/assignment:submit');

        foreach ($users as $i => $user) {
            $tree = $this->get_assessment_files_tree($user->id, $courseid);
            if ($tree != null) {
                $user->files = $this->render_assessement_files_tree($tree);
            } else {
                unset($users[$i]);  // Only render students that have files

            }
        }

        $context = [
            'users' => array_values(($users)),
            'sessionkey' => sesskey(),
            'actionurl' => '',
            'id' => $courseid,
            'formid' => 'ibassessmentform',
        ];


        return $context;
    }

    // Collect the assessment that are already graded
    protected function get_assessment_files_tree($userid, $courseid) {
        global $DB;

        $sql = 'SELECT * FROM {assign} AS assign
                JOIN {assign_submission} AS asub
                ON asub.assignment = assign.id
                JOIN {files} as f  ON f.itemid = asub.id
                WHERE f.userid = ? AND f.filearea = ?' ;
        $params_array = ['userid' => $userid, 'filearea' => 'submission_files'];
        $result = array_values($DB->get_records_sql($sql, $params_array));
        $tree = null;
        foreach ($result as $result) {
            $tree = new assessement_files_tree($result->contextid, $result->component, $result->filearea,  $result->itemid);
        }
        return $tree;
    }
}

class assessement_files_tree implements renderable {
    public $dir;
    public $contextid;
    public $files;
    public $itemid;
    public $filearea;

    public function __construct($contextid, $component, $filearea, $itemid) {
        global $USER;
        // $this->context = context_user::instance($USER->id);
        $fs = get_file_storage();
        $this->dir = $fs->get_area_tree($contextid, $component, $filearea, $itemid);
        $this->contextid = $contextid;
        $this->filearea = $filearea;
        $this->component = $component;
        $this->itemid = $itemid;
    }
}
