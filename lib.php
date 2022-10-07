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
 * Defines the APIs used by assignfeedback_download reports
 *
 * @package    report
 * @subpackage assignfeedback_download
 * @copyright  2021 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define("SUPPORTED_ADVANCED_GRADING_METHODS", ['frubric', 'rubric']);

function report_assignfeedback_download_extend_navigation_course($navigation, $course, $context) {

    if (has_capability('moodle/site:viewuseridentity', $context)) {
        $url = new moodle_url('/report/assignfeedback_download/index.php', array('id' => $course->id, 'cmid' => $context->id));
        $navigation->add(get_string('pluginname', 'report_assignfeedback_download'), $url, navigation_node::COURSE_INDEX_PAGE, null, null, new pix_icon('i/report', ''));
    }
}

function report_assignfeedback_download_sort_by_firstname($param1, $param2) {
    return strcmp($param1->firstname, $param2->firstname);
}

function  report_assignfeedback_download_get_anonymous_submission_id($cm, $userid) {
    return \assign::get_uniqueid_for_user_static($cm->instance, $userid);
}
