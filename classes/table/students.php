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
 * Contains the class used for displaying the assignment reports students table.
 *
 * @package    report_assignfeedback_download
 * @copyright  2026 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace report_assignfeedback_download\table;

use context;
use core_table\dynamic as dynamic_table;
use core_table\local\filter\filterset;
use moodle_url;

defined('MOODLE_INTERNAL') || die;

global $CFG;
require_once($CFG->libdir . '/tablelib.php');

class students extends \table_sql implements dynamic_table {

    /** @var int $courseid The course id. */
    protected $courseid;

    /** @var filterset $filterset Filterset describing which students to include. */
    protected $filterset;

    /** @var moodle_url $baseurl The base URL for the report. */
    public $baseurl;

    /** @var \stdClass[] $groups The list of groups with membership info for the course. */
    protected $groups;

    /** @var \stdClass $course The course details. */
    protected $course;

    /** @var context $context The course context. */
    protected $context;

    /** @var array $submissiondetail Per-user submission detail keyed by userid. */
    protected $submissiondetail = [];

    /** @var array $gradingdetail Per-user grading detail keyed by userid. */
    protected $gradingdetail = [];

    /**
     * Render the students table.
     *
     * @param int $pagesize Size of page for paginated displayed table.
     * @param bool $useinitialsbar Whether to use the initials bar.
     * @param string $downloadhelpbutton
     */
    public function out($pagesize, $useinitialsbar, $downloadhelpbutton = '') {
        global $CFG, $OUTPUT;

        $headers = [];
        $columns = [];

        // Select checkbox column.
        $mastercheckbox = new \core\output\checkbox_toggleall('students-table', true, [
            'id' => 'select-all-students',
            'name' => 'select-all-students',
            'label' => get_string('selectall'),
            'labelclasses' => 'visually-hidden',
            'classes' => 'm-1',
            'checked' => false,
        ]);
        $headers[] = $OUTPUT->render($mastercheckbox);
        $columns[] = 'select';

        $headers[] = get_string('fullname');
        $columns[] = 'fullname';

        $headers[] = get_string('email');
        $columns[] = 'email';

        // Get the list of fields we have to hide.
        $hiddenfields = [];
        if (!has_capability('moodle/course:viewhiddenuserfields', $this->context)) {
            $hiddenfields = array_flip(explode(',', $CFG->hiddenuserfields));
        }

        // Add column for groups if the user can view them.
        $canseegroups = !isset($hiddenfields['groups']);
        if ($canseegroups) {
            $headers[] = get_string('groups');
            $columns[] = 'groups';
        }

        // Submissions column.
        $headers[] = get_string('submissionstatus', 'report_assignfeedback_download');
        $columns[] = 'submissiondetail';

        // Graded column.
        $headers[] = get_string('gradingstatus', 'report_assignfeedback_download');
        $columns[] = 'gradingdetail';

        // Last access column.
        if (!isset($hiddenfields['lastaccess'])) {
            $headers[] = get_string('lastcourseaccess');
            $columns[] = 'lastaccess';
        }

        $this->define_columns($columns);
        $this->define_headers($headers);

        $this->define_header_column('fullname');
        $this->sortable(true, 'lastname');

        $this->no_sorting('select');
        $this->no_sorting('submissiondetail');
        $this->no_sorting('gradingdetail');
        if ($canseegroups) {
            $this->no_sorting('groups');
        }

        $this->set_default_per_page(20);
        $this->set_attribute('id', 'assignfeedback-students');

        if ($canseegroups) {
            $this->groups = groups_get_all_groups($this->courseid, 0, 0, 'g.*', true);
        }

        parent::out($pagesize, $useinitialsbar, $downloadhelpbutton);

        // Add JS to toggle the "show more/less" link text on collapse events.
        global $PAGE;
        $PAGE->requires->js_amd_inline("
            require([], function() {
                document.getElementById('assignfeedback-students').addEventListener('shown.bs.collapse', function(e) {
                    var toggle = e.target.nextElementSibling;
                    if (toggle && toggle.classList.contains('detail-toggle')) {
                        var icon = toggle.querySelector('i');
                        toggle.childNodes[toggle.childNodes.length - 1].textContent = toggle.dataset.showless;
                        if (icon) { icon.className = 'fa fa-chevron-up mr-1'; }
                    }
                });
                document.getElementById('assignfeedback-students').addEventListener('hidden.bs.collapse', function(e) {
                    var toggle = e.target.nextElementSibling;
                    if (toggle && toggle.classList.contains('detail-toggle')) {
                        var icon = toggle.querySelector('i');
                        toggle.childNodes[toggle.childNodes.length - 1].textContent = toggle.dataset.showmore;
                        if (icon) { icon.className = 'fa fa-chevron-down mr-1'; }
                    }
                });
            });
        ");
    }

    /**
     * Generate the select column.
     *
     * @param \stdClass $data
     * @return string
     */
    public function col_select($data) {
        global $OUTPUT;

        $checkbox = new \core\output\checkbox_toggleall('students-table', false, [
            'classes' => 'usercheckbox m-1',
            'id' => 'user' . $data->id,
            'name' => 'user' . $data->id,
            'checked' => false,
            'label' => get_string('selectitem', 'moodle', fullname($data)),
            'labelclasses' => 'accesshide',
        ]);

        return $OUTPUT->render($checkbox);
    }

    /**
     * Generate the fullname column.
     *
     * @param \stdClass $data
     * @return string
     */
    public function col_fullname($data) {
        global $OUTPUT;

        $userpic = $OUTPUT->user_picture($data, ['courseid' => $this->course->id, 'size' => 35, 'link' => true]);
        $fullname = fullname($data);
        $profileurl = new moodle_url('/user/view.php', ['id' => $data->id, 'course' => $this->course->id]);
        $namelink = \html_writer::link($profileurl, $fullname);

        return $userpic . ' ' . $namelink;
    }

    /**
     * Generate the email column.
     *
     * @param \stdClass $data
     * @return string
     */
    public function col_email($data) {
        if ($this->is_downloading()) {
            return $data->email;
        }
        return \html_writer::link('mailto:' . $data->email, $data->email);
    }

    /**
     * Generate the groups column.
     *
     * @param \stdClass $data
     * @return string
     */
    public function col_groups($data) {
        $usergroups = [];
        foreach ($this->groups as $coursegroup) {
            if (isset($coursegroup->members[$data->id])) {
                $usergroups[] = format_string($coursegroup->name, true, ['context' => $this->context]);
            }
        }
        return implode(', ', $usergroups);
    }

    /** @var int $collapsecounter Counter for unique collapse IDs. */
    protected static $collapsecounter = 0;

    /** @var int Maximum items to show before collapsing. */
    protected const VISIBLE_ITEMS = 3;

    /**
     * Generate the submission detail column.
     *
     * Shows per-assignment submission status: AssignmentName (Submitted) or (Not submitted).
     *
     * @param \stdClass $data
     * @return string
     */
    public function col_submissiondetail($data) {
        if (empty($this->submissiondetail[$data->id])) {
            return \html_writer::span(
                get_string('notsubmitted', 'report_assignfeedback_download'),
                'badge badge-warning bg-warning'
            );
        }

        $items = [];
        foreach ($this->submissiondetail[$data->id] as $detail) {
            $assignname = format_string($detail->assignname, true, ['context' => $this->context]);
            $url = new moodle_url('/mod/assign/view.php', ['id' => $detail->cmid]);
            $link = \html_writer::link($url, $assignname);
            if ($detail->submitted) {
                $badge = \html_writer::span(
                    get_string('submitted', 'report_assignfeedback_download'),
                    'badge badge-success bg-success'
                );
            } else {
                $badge = \html_writer::span(
                    get_string('notsubmitted', 'report_assignfeedback_download'),
                    'badge badge-warning bg-warning'
                );
            }
            $items[] = \html_writer::div($link . ' ' . $badge, 'mb-1');
        }

        return $this->render_collapsible_list($items);
    }

    /**
     * Generate the grading detail column.
     *
     * Shows per-assignment grading status: AssignmentName (Graded) or (Not graded).
     *
     * @param \stdClass $data
     * @return string
     */
    public function col_gradingdetail($data) {
        if (empty($this->gradingdetail[$data->id])) {
            return \html_writer::span(
                get_string('notgraded', 'report_assignfeedback_download'),
                'badge badge-warning bg-warning'
            );
        }

        $items = [];
        foreach ($this->gradingdetail[$data->id] as $detail) {
            $assignname = format_string($detail->assignname, true, ['context' => $this->context]);
            $url = new moodle_url('/mod/assign/view.php', ['id' => $detail->cmid]);
            $link = \html_writer::link($url, $assignname);
            if ($detail->graded) {
                $badge = \html_writer::span(
                    get_string('graded', 'report_assignfeedback_download'),
                    'badge badge-success bg-success'
                );
            } else {
                $badge = \html_writer::span(
                    get_string('notgraded', 'report_assignfeedback_download'),
                    'badge badge-warning bg-warning'
                );
            }
            $items[] = \html_writer::div($link . ' ' . $badge, 'mb-1');
        }

        return $this->render_collapsible_list($items);
    }

    /**
     * Render a list of items, collapsing beyond VISIBLE_ITEMS with a toggle link.
     *
     * @param string[] $items The HTML items to render.
     * @return string The rendered HTML.
     */
    protected function render_collapsible_list(array $items): string {
        $count = count($items);
        if ($count <= self::VISIBLE_ITEMS) {
            return implode('', $items);
        }

        self::$collapsecounter++;
        $collapseid = 'detail-collapse-' . self::$collapsecounter;

        // Show first VISIBLE_ITEMS items directly.
        $visible = implode('', array_slice($items, 0, self::VISIBLE_ITEMS));

        // Remaining items go in a collapsible div.
        $hidden = implode('', array_slice($items, self::VISIBLE_ITEMS));
        $hiddencount = $count - self::VISIBLE_ITEMS;

        $showmoretext = get_string('showmore', 'report_assignfeedback_download', $hiddencount);
        $showlesstext = get_string('showless', 'report_assignfeedback_download');

        $collapsediv = \html_writer::div($hidden, 'collapse', ['id' => $collapseid]);

        $togglelink = \html_writer::link(
            '#' . $collapseid,
            \html_writer::tag('i', '', ['class' => 'fa fa-chevron-down mr-1']) . $showmoretext,
            [
                'class' => 'btn btn-link btn-sm p-0 mt-1 detail-toggle',
                'data-toggle' => 'collapse',
                'data-bs-toggle' => 'collapse',
                'aria-expanded' => 'false',
                'aria-controls' => $collapseid,
                'data-showmore' => $showmoretext,
                'data-showless' => $showlesstext,
            ]
        );

        return $visible . $collapsediv . $togglelink;
    }

    /**
     * Generate the last access column.
     *
     * @param \stdClass $data
     * @return string
     */
    public function col_lastaccess($data) {
        if ($data->lastaccess) {
            return format_time(time() - $data->lastaccess);
        }
        return get_string('never');
    }

    /**
     * Query the database for results to display in the table.
     *
     * @param int $pagesize size of page for paginated displayed table.
     * @param bool $useinitialsbar do you want to use the initials bar.
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        global $DB;

        list($twhere, $tparams) = $this->get_sql_where();
        $psearch = new students_search($this->course, $this->context, $this->filterset);

        $sort = $this->get_sql_sort();

        $this->use_pages = true;
        $rawdata = $psearch->get_participants($twhere, $tparams, $sort, $this->get_page_start(), $this->get_page_size());
        $total = $rawdata->current()->fullcount ?? 0;
        $this->pagesize($pagesize, $total);

        $this->rawdata = [];
        foreach ($rawdata as $user) {
            $this->rawdata[$user->id] = $user;
        }
        $rawdata->close();

        // Now fetch per-assignment detail for the users on this page.
        if (!empty($this->rawdata)) {
            $this->load_assignment_detail(array_keys($this->rawdata));
        }

        if ($useinitialsbar) {
            $this->initialbars(true);
        }
    }

    /**
     * Load per-assignment submission and grading detail for the given user IDs.
     *
     * @param int[] $userids The user IDs to load detail for.
     */
    protected function load_assignment_detail(array $userids): void {
        global $DB;

        if (empty($userids)) {
            return;
        }

        // Determine which assignments to show detail for.
        $selectedassignids = [];
        if ($this->filterset->has_filter('assignment')) {
            $assignfilter = $this->filterset->get_filter('assignment');
            foreach ($assignfilter as $aid) {
                $selectedassignids[] = $aid;
            }
        }

        // If no assignment filter, get all assignments in the course. Include cmid for links.
        if (empty($selectedassignids)) {
            $sql = "SELECT a.id, a.name, cm.id AS cmid
                      FROM {assign} a
                      JOIN {course_modules} cm ON cm.instance = a.id
                      JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
                     WHERE a.course = :courseid
                  ORDER BY a.name ASC";
            $assigns = $DB->get_records_sql($sql, ['courseid' => $this->courseid]);
        } else {
            [$ainsql, $ainparams] = $DB->get_in_or_equal($selectedassignids, SQL_PARAMS_NAMED, 'detailassign');
            $sql = "SELECT a.id, a.name, cm.id AS cmid
                      FROM {assign} a
                      JOIN {course_modules} cm ON cm.instance = a.id
                      JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
                     WHERE a.id {$ainsql}
                  ORDER BY a.name ASC";
            $assigns = $DB->get_records_sql($sql, $ainparams);
        }

        if (empty($assigns)) {
            return;
        }

        $assignids = array_keys($assigns);
        [$uinsql, $uinparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'detailuser');
        [$asinsql, $asinparams] = $DB->get_in_or_equal($assignids, SQL_PARAMS_NAMED, 'detailassid');

        // Fetch submissions: which users submitted which assignments.
        $sql = "SELECT s.userid, s.assignment
                  FROM {assign_submission} s
                 WHERE s.userid {$uinsql}
                   AND s.assignment {$asinsql}
                   AND s.status = 'submitted'";
        $submissions = $DB->get_records_sql($sql, array_merge($uinparams, $asinparams));

        // Build a lookup: submitted[userid][assignmentid] = true.
        $submittedlookup = [];
        foreach ($submissions as $sub) {
            $submittedlookup[$sub->userid][$sub->assignment] = true;
        }

        // Fetch grades: which users have been graded for which assignments.
        [$uinsql2, $uinparams2] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'gradeuser');
        [$asinsql2, $asinparams2] = $DB->get_in_or_equal($assignids, SQL_PARAMS_NAMED, 'gradeassid');
        $sql = "SELECT g.userid, g.assignment
                  FROM {assign_grades} g
                 WHERE g.userid {$uinsql2}
                   AND g.assignment {$asinsql2}
                   AND g.grade >= 0";
        $grades = $DB->get_records_sql($sql, array_merge($uinparams2, $asinparams2));

        // Build a lookup: graded[userid][assignmentid] = true.
        $gradedlookup = [];
        foreach ($grades as $grade) {
            $gradedlookup[$grade->userid][$grade->assignment] = true;
        }

        // Determine active filter values to restrict detail display.
        $statusfiltervalues = $this->get_active_filter_values('status');
        $gradedfiltervalues = $this->get_active_filter_values('graded');

        // Build per-user detail arrays, respecting active filters.
        foreach ($userids as $userid) {
            $this->submissiondetail[$userid] = [];
            $this->gradingdetail[$userid] = [];

            foreach ($assigns as $assign) {
                $issubmitted = !empty($submittedlookup[$userid][$assign->id]);
                $isgraded = !empty($gradedlookup[$userid][$assign->id]);

                // Submission detail: skip if status filter is active and this assignment doesn't match.
                if (empty($statusfiltervalues) || in_array((int)$issubmitted, $statusfiltervalues, true)) {
                    $subdetail = new \stdClass();
                    $subdetail->assignname = $assign->name;
                    $subdetail->cmid = $assign->cmid;
                    $subdetail->submitted = $issubmitted;
                    $this->submissiondetail[$userid][] = $subdetail;
                }

                // Grading detail: skip if graded filter is active and this assignment doesn't match.
                if (empty($gradedfiltervalues) || in_array((int)$isgraded, $gradedfiltervalues, true)) {
                    $gradedetail = new \stdClass();
                    $gradedetail->assignname = $assign->name;
                    $gradedetail->cmid = $assign->cmid;
                    $gradedetail->graded = $isgraded;
                    $this->gradingdetail[$userid][] = $gradedetail;
                }
            }
        }
    }

    /**
     * Get the active values for a given filter.
     *
     * @param string $filtername The filter name.
     * @return int[] The selected values, or empty array if no values selected.
     */
    protected function get_active_filter_values(string $filtername): array {
        if (!$this->filterset->has_filter($filtername)) {
            return [];
        }

        $filter = $this->filterset->get_filter($filtername);
        $values = [];
        foreach ($filter as $value) {
            $values[] = (int)$value;
        }
        return $values;
    }

    /**
     * Set the filterset, extracting course/context info.
     *
     * @param filterset $filterset The filterset object to get the filters from.
     */
    public function set_filterset(filterset $filterset): void {
        $this->courseid = $filterset->get_filter('courseid')->current();
        $this->course = get_course($this->courseid);
        $this->context = \context_course::instance($this->courseid, MUST_EXIST);

        parent::set_filterset($filterset);
    }

    /**
     * Guess the base url for the table.
     */
    public function guess_base_url(): void {
        $this->baseurl = new moodle_url('/report/assignfeedback_download/assignmentgeneralreports.php', [
            'id' => $this->courseid ?? 0,
        ]);
    }

    /**
     * Get the context of the current table.
     *
     * @return context
     */
    public function get_context(): context {
        return $this->context;
    }

    /**
     * Check if the user has the capability to access this table.
     *
     * @return bool
     */
    public function has_capability(): bool {
        return has_capability('report/assignfeedback_download:grade', $this->context);
    }
}
