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
defined('MOODLE_INTERNAL') || die;

function report_assignfeedback_download_extend_navigation_course($navigation, $course, $context) {

    if (has_capability('moodle/site:viewuseridentity', $context)) {
        $url = new moodle_url('/report/assignfeedback_download/index.php', array('id' => $course->id, 'cmid' => $context->id));
        $navigation->add(get_string('pluginname', 'report_assignfeedback_download'), $url, navigation_node::COURSE_INDEX_PAGE, null, null, new pix_icon('i/report', ''));
    }
}


// function report_assignfeedback_download_extends_settings_navigation($settingsnav, $context) {
//     global $CFG, $PAGE, $USER;
//     // Setup navigation for Admin metadata
//     if (is_null($PAGE->course)) {
//         //return;
//     } else {
//         if ($categorynode = $settingsnav->find('categorysettings', null)) {
//             $url = new moodle_url('/local/metadata/admview_knowledge.php', array('categoryid' => $PAGE->category->id));
//             $foonode = navigation_node::create(get_string('manage_pluginname', 'local_metadata'), $url, navigation_node::NODETYPE_LEAF, 'metadata', 'metadata', new pix_icon('i/report', ''));
//             if ($PAGE->url->compare($url, URL_MATCH_BASE)) {
//                 $foonode->make_active();
//             }
//             $categorynode->add_node($foonode);
//             //$categorynode->add(get_string('manage_pluginname', 'local_metadata'), $url, self::TYPE_SETTING, null, 'permissions', new pix_icon('i/permissions', ''));
//         }
//     }
//     // Only add this settings item on non-site course pages.
//     if (!$PAGE->course or $PAGE->course->id == 1) {
//         return;
//     }
//     // TODO: Only let users with the appropriate capability see this settings item.
//     //if (!has_capability('local/metadata:ins_view', context_course::instance($PAGE->course->id))) {
//     //    return;
//     //}
//     if ($settingnode = $settingsnav->find('courseadmin', navigation_node::TYPE_COURSE)) {
//         $url = new moodle_url('/report/assignfeedback_download/index.php', array('id' => $PAGE->course->id));
//         // TODO: Should change the name to something more descriptive
//         $foonode = navigation_node::create(get_string('pluginname', 'report_assignfeedback_download'), $url, navigation_node::NODETYPE_LEAF, 'metadata', 'metadata', new pix_icon('i/report', ''));
//         if ($PAGE->url->compare($url, URL_MATCH_BASE)) {
//             $foonode->make_active();
//         }
//         $settingnode->add_node($foonode);
//     }
// }


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


function sort_by_firstname($param1, $param2) {
    return strcmp($param1->firstname, $param2->firstname);
}
