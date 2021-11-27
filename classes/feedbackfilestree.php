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
 * @package    report_ibassessment
 * @copyright  2021 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_ibassessment;

use renderable;

class Feedback_files_tree implements renderable, \templatable {

    public $contextid;
    public $dir;
    public $files;
    public $itemid;
    public $filearea;

    public function __construct($contextid, $itemid, $filearea, $component) {
        $this->contextid = $contextid;
        $this->component = $component;
        $this->itemid = $itemid;
        $this->filearea = $filearea;
        $fs = get_file_storage();
        $this->dir = $fs->get_area_tree($this->contextid, $component, $filearea, $itemid);

        $this->files =   $fs->get_area_files(
            $this->context->id,
            $component,
            $filearea,
            $itemid,
            'timemodified',
            false
        );
    }

    public function export_for_template(renderer_base $output) {
        $data = new \stdClass();
        $data->url = $this->htmllize_tree($this, $this->dir);

        return $data;
    }

    public  function htmllize_tree($tree, $dir) {
        global  $OUTPUT, $CFG;
        $yuiconfig = array();
        $yuiconfig['type'] = 'html';

        if (empty($dir['subdirs']) and empty($dir['files'])) {
            return '';
        }

        $result = '<ul>';
        foreach ($dir['subdirs'] as $subdir) {
            $image = $OUTPUT->pix_icon(file_folder_icon(), $subdir['dirname'], 'moodle', array('class' => 'icon'));
            $result .= '<li yuiConfig=\'' . json_encode($yuiconfig) . '\'><div>' . $image . s($subdir['dirname']) . '</div> ' . $this->htmllize_tree($tree, $subdir) . '</li>';
        }
        foreach ($dir['files'] as $file) {
            $url = file_encode_url("$CFG->wwwroot/pluginfile.php", '/' . $tree->contextid . '/assignfeedback_file/' . $tree->filearea . '/' . $tree->itemid . $file->get_filepath() . $file->get_filename(), true);
            $filename = $file->get_filename();
            $image = $OUTPUT->pix_icon(file_file_icon($file), $filename, 'moodle', array('class' => 'icon'));
            $result .= '<li yuiConfig=\'' . json_encode($yuiconfig) . '\'><div class="fileuploadsubmission">' . html_writer::link($url, $image . $filename) . '</div></li>';
        }


        $result .= '</ul>';
        return $result;
    }

    public function get_tree() {
        $htmlid = \html_writer::random_id('feedback_files_tree');
        $html = '<div id="' . $htmlid . '">';
        $html .=  $this->htmllize_tree($this, $this->dir);
        $html .= '</div>';

        return $html;
    }
}
