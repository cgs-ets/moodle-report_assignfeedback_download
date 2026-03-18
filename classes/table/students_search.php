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
 * Class used to fetch students based on a filterset for assignment reports.
 *
 * @package    report_assignfeedback_download
 * @copyright  2026 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_assignfeedback_download\table;

use core_table\local\filter\filterset;
use context;
use stdClass;

class students_search {

    /** @var filterset $filterset The filterset describing which students to include. */
    protected $filterset;

    /** @var stdClass $course The course being searched. */
    protected $course;

    /** @var context $context The context of the search. */
    protected $context;

    /**
     * Class constructor.
     *
     * @param stdClass $course The course being searched.
     * @param context $context The context of the search.
     * @param filterset $filterset The filterset used to filter the students.
     */
    public function __construct(stdClass $course, context $context, filterset $filterset) {
        $this->course = $course;
        $this->context = $context;
        $this->filterset = $filterset;
    }

    /**
     * Fetch students matching the filterset.
     *
     * @param string $additionalwhere Any additional SQL to add to where.
     * @param array $additionalparams The additional params used by $additionalwhere.
     * @param string $sort Optional SQL sort.
     * @param int $limitfrom Return a subset of records, starting at this point.
     * @param int $limitnum Return a subset comprising this many records.
     * @return \moodle_recordset
     */
    public function get_participants(string $additionalwhere = '', array $additionalparams = [], string $sort = '',
            int $limitfrom = 0, int $limitnum = 0): \moodle_recordset {
        global $DB;

        [
            'subqueryalias' => $subqueryalias,
            'outerselect' => $outerselect,
            'innerselect' => $innerselect,
            'outerjoins' => $outerjoins,
            'innerjoins' => $innerjoins,
            'outerwhere' => $outerwhere,
            'innerwhere' => $innerwhere,
            'params' => $params,
        ] = $this->get_participants_sql($additionalwhere, $additionalparams);

        $select = "{$outerselect}
                        FROM ({$innerselect}
                                FROM {$innerjoins}
                              {$innerwhere}
                        ) {$subqueryalias}
                   {$outerjoins}
                   {$outerwhere}";

        return $DB->get_counted_recordset_sql(
            sql: $select,
            fullcountcolumn: 'fullcount',
            sort: $sort,
            params: $params,
            limitfrom: $limitfrom,
            limitnum: $limitnum,
        );
    }

    /**
     * Generate the SQL used to fetch filtered data for the students table.
     *
     * @param string $additionalwhere Any additional SQL to add to where.
     * @param array $additionalparams The additional params.
     * @return array
     */
    protected function get_participants_sql(string $additionalwhere, array $additionalparams): array {
        global $CFG;

        $usersubqueryalias = 'targetusers';
        $inneruseralias = 'udistinct';

        // Inner query: distinct enrolled users who are not deleted and not guest.
        $innerselect = "SELECT DISTINCT {$inneruseralias}.id";
        $innerjoins = ["{user} {$inneruseralias}"];
        $innerwhere = "WHERE {$inneruseralias}.deleted = 0 AND {$inneruseralias}.id <> :siteguest";
        $params = ['siteguest' => $CFG->siteguest];

        $outerjoins = ["JOIN {user} u ON u.id = {$usersubqueryalias}.id"];
        $wheres = [];

        // Only enrolled users with submit capability (students).
        [$enrolledsql, $enrolledparams] = get_enrolled_sql($this->context, 'mod/assign:submit');
        $innerjoins[] = "JOIN ({$enrolledsql}) je ON je.id = {$inneruseralias}.id";
        $params = array_merge($params, $enrolledparams);

        // User fields for display.
        $userfields = \core_user\fields::for_identity(null)->with_userpic();
        ['selects' => $userfieldssql, 'joins' => $userfieldsjoin, 'params' => $userfieldsparams] =
                (array)$userfields->get_sql('u', true);
        if ($userfieldsjoin) {
            $outerjoins[] = $userfieldsjoin;
            $params = array_merge($params, $userfieldsparams);
        }

        // Last access to course.
        $outerselect = "SELECT COALESCE(ul.timeaccess, 0) AS lastaccess {$userfieldssql}";
        $outerjoins[] = 'LEFT JOIN {user_lastaccess} ul ON (ul.userid = u.id AND ul.courseid = :courseid2)';
        $params['courseid2'] = $this->course->id;

        // Context preload.
        $ccselect = ', ' . \context_helper::get_preload_record_columns_sql('ctx');
        $ccjoin = 'LEFT JOIN {context} ctx ON (ctx.instanceid = u.id AND ctx.contextlevel = :contextlevel)';
        $params['contextlevel'] = CONTEXT_USER;
        $outerselect .= $ccselect;
        $outerjoins[] = $ccjoin;

        // Note: the assignment filter does NOT restrict the student list.
        // It only scopes the counts and the status/graded filters.

        // Apply groups filter.
        if ($this->filterset->has_filter('groups')) {
            [
                'where' => $groupswhere,
                'params' => $groupsparams,
            ] = $this->get_groups_sql();

            if (!empty($groupswhere)) {
                $wheres[] = "({$groupswhere})";
            }
            if (!empty($groupsparams)) {
                $params = array_merge($params, $groupsparams);
            }
        }

        // Apply submission status filter.
        if ($this->filterset->has_filter('status')) {
            [
                'where' => $statuswhere,
                'params' => $statusparams,
            ] = $this->get_status_sql();

            if (!empty($statuswhere)) {
                $wheres[] = "({$statuswhere})";
            }
            if (!empty($statusparams)) {
                $params = array_merge($params, $statusparams);
            }
        }

        // Apply graded status filter.
        if ($this->filterset->has_filter('graded')) {
            [
                'where' => $gradedwhere,
                'params' => $gradedparams,
            ] = $this->get_graded_sql();

            if (!empty($gradedwhere)) {
                $wheres[] = "({$gradedwhere})";
            }
            if (!empty($gradedparams)) {
                $params = array_merge($params, $gradedparams);
            }
        }

        // Add any supplied additional WHERE clauses.
        if (!empty($additionalwhere)) {
            $innerwhere .= " AND ({$additionalwhere})";
            $params = array_merge($params, $additionalparams);
        }

        // Prepare final values.
        $outerjoinsstring = implode("\n", $outerjoins);
        $innerjoinsstring = implode("\n", $innerjoins);
        if ($wheres) {
            switch ($this->filterset->get_join_type()) {
                case $this->filterset::JOINTYPE_ALL:
                    $wherenot = '';
                    $wheresjoin = ' AND ';
                    break;
                case $this->filterset::JOINTYPE_NONE:
                    $wherenot = ' NOT ';
                    $wheresjoin = ' AND NOT ';
                    $wheres = array_map(function($where) {
                        return "({$where})";
                    }, $wheres);
                    break;
                default:
                    $wherenot = '';
                    $wheresjoin = ' OR ';
                    break;
            }
            $outerwhere = 'WHERE ' . $wherenot . implode($wheresjoin, $wheres);
        } else {
            $outerwhere = '';
        }

        return [
            'subqueryalias' => $usersubqueryalias,
            'outerselect' => $outerselect,
            'innerselect' => $innerselect,
            'outerjoins' => $outerjoinsstring,
            'innerjoins' => $innerjoinsstring,
            'outerwhere' => $outerwhere,
            'innerwhere' => $innerwhere,
            'params' => $params,
        ];
    }

    /**
     * Get the selected assignment IDs from the assignment filter.
     *
     * @return int[] The selected assignment IDs, or empty array if no filter is active.
     */
    protected function get_selected_assignment_ids(): array {
        if (!$this->filterset->has_filter('assignment')) {
            return [];
        }

        $assignfilter = $this->filterset->get_filter('assignment');
        $assignids = [];
        foreach ($assignfilter as $assignid) {
            $assignids[] = $assignid;
        }
        return $assignids;
    }

    /**
     * Get the SQL for filtering by groups.
     *
     * @return array With 'where' and 'params' keys.
     */
    protected function get_groups_sql(): array {
        $groupsfilter = $this->filterset->get_filter('groups');
        $groupids = [];
        foreach ($groupsfilter as $groupid) {
            $groupids[] = $groupid;
        }

        if (empty($groupids)) {
            return ['where' => '', 'params' => []];
        }

        global $DB;
        [$insql, $inparams] = $DB->get_in_or_equal($groupids, SQL_PARAMS_NAMED, 'grp');

        $where = "u.id IN (
            SELECT gm.userid
              FROM {groups_members} gm
             WHERE gm.groupid {$insql}
        )";

        return ['where' => $where, 'params' => $inparams];
    }

    /**
     * Get the SQL for filtering by submission status.
     *
     * Status values:
     * - 1 = Submitted
     * - 0 = Not submitted
     *
     * When assignment filter is active, scopes the check to those assignments.
     *
     * @return array With 'where' and 'params' keys.
     */
    protected function get_status_sql(): array {
        $statusfilter = $this->filterset->get_filter('status');
        $statusvalues = [];
        foreach ($statusfilter as $status) {
            $statusvalues[] = $status;
        }

        if (empty($statusvalues)) {
            return ['where' => '', 'params' => []];
        }

        // Scope the status check to selected assignments or all course assignments.
        $assignrestriction = '';
        $assignparams = [];
        $selectedids = $this->get_selected_assignment_ids();
        if (!empty($selectedids)) {
            global $DB;
            [$ainsql, $assignparams] = $DB->get_in_or_equal($selectedids, SQL_PARAMS_NAMED, 'stassign');
            $assignrestriction = " AND s.assignment {$ainsql}";
        } else {
            $assignrestriction = " AND s.assignment IN (SELECT id FROM {assign} WHERE course = :stcourseid)";
            $assignparams = ['stcourseid' => $this->course->id];
        }

        $conditions = [];
        $params = [];

        foreach ($statusvalues as $status) {
            if ($status == 1) {
                // Has submissions.
                $conditions[] = "u.id IN (
                    SELECT s.userid
                      FROM {assign_submission} s
                     WHERE s.status = 'submitted'{$assignrestriction}
                )";
            } else {
                // No submissions.
                $conditions[] = "u.id NOT IN (
                    SELECT s.userid
                      FROM {assign_submission} s
                     WHERE s.status = 'submitted'{$assignrestriction}
                )";
            }
            $params = array_merge($params, $assignparams);
        }

        $jointype = $statusfilter->get_join_type();
        if ($jointype === $statusfilter::JOINTYPE_ALL) {
            $where = implode(' AND ', $conditions);
        } else {
            $where = implode(' OR ', $conditions);
        }

        return ['where' => $where, 'params' => $params];
    }

    /**
     * Get the SQL for filtering by grading status.
     *
     * Graded values:
     * - 1 = Graded (has at least one grade >= 0)
     * - 0 = Not graded
     *
     * When assignment filter is active, scopes the check to those assignments.
     *
     * @return array With 'where' and 'params' keys.
     */
    protected function get_graded_sql(): array {
        $gradedfilter = $this->filterset->get_filter('graded');
        $gradedvalues = [];
        foreach ($gradedfilter as $graded) {
            $gradedvalues[] = $graded;
        }

        if (empty($gradedvalues)) {
            return ['where' => '', 'params' => []];
        }

        // Scope the graded check to selected assignments or all course assignments.
        $assignrestriction = '';
        $assignparams = [];
        $selectedids = $this->get_selected_assignment_ids();
        if (!empty($selectedids)) {
            global $DB;
            [$ainsql, $assignparams] = $DB->get_in_or_equal($selectedids, SQL_PARAMS_NAMED, 'grassign');
            $assignrestriction = " AND g.assignment {$ainsql}";
        } else {
            $assignrestriction = " AND g.assignment IN (SELECT id FROM {assign} WHERE course = :grcourseid)";
            $assignparams = ['grcourseid' => $this->course->id];
        }

        $conditions = [];
        $params = [];

        foreach ($gradedvalues as $graded) {
            if ($graded == 1) {
                // Has been graded.
                $conditions[] = "u.id IN (
                    SELECT g.userid
                      FROM {assign_grades} g
                     WHERE g.grade >= 0{$assignrestriction}
                )";
            } else {
                // Not graded.
                $conditions[] = "u.id NOT IN (
                    SELECT g.userid
                      FROM {assign_grades} g
                     WHERE g.grade >= 0{$assignrestriction}
                )";
            }
            $params = array_merge($params, $assignparams);
        }

        $jointype = $gradedfilter->get_join_type();
        if ($jointype === $gradedfilter::JOINTYPE_ALL) {
            $where = implode(' AND ', $conditions);
        } else {
            $where = implode(' OR ', $conditions);
        }

        return ['where' => $where, 'params' => $params];
    }
}
