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

use ibassessment\Feedback_files_tree;

defined('MOODLE_INTERNAL') || die;


function report_ibassessment_extend_navigation_course($navigation, $course, $context) {
    //has_capability('moodle/site:viewuseridentity', $context)
    if (has_capability('moodle/site:viewuseridentity', $context)) {
        $url = new moodle_url('/report/ibassessment/index.php', array('id' => $course->id));
        $navigation->add(get_string('pluginname', 'report_ibassessment'), $url, navigation_node::TYPE_SETTING, null, null, new pix_icon('i/report', ''));
    }
}

function get_table_context($courseid) {
    $context = context_course::instance($courseid);

    $users = get_enrolled_users($context, 'mod/assignment:submit');
    $userfiles = [];
    foreach ($users as $user) {
        $tree = get_assessment_files_tree($user->id, $courseid);
        $user->files = $tree;
    }

    $context = ['users' => array_values(($users))];
    print_object($context);
    exit;

    return $context;
}

// Collect the assessment that are already graded
function get_assessment_files_tree($userid, $courseid) {
    global $DB;

    $sql = 'SELECT * FROM {files} 
            WHERE filearea = ? AND component = ? AND itemid = ? AND userid = ? AND filename <> ?
            ORDER BY filename';
    $params_array = ['filearea' => 'submission_files', 'component' => 'assignsubmission_file', 'itemid' => $courseid, 'userid' => $userid, 'filename' => '.'];
    $result = array_values($DB->get_records_sql($sql, $params_array));
    foreach ($result as $result) {

        $tree = new Feedback_files_tree($result->contextid, $result->itemid, $result->filearea, $result->component);
    }
    return $tree;
}
