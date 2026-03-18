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
 * Class for rendering student filters on the assignfeedback_download reports page.
 *
 * @package    report_assignfeedback_download
 * @copyright  2026 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_assignfeedback_download\output;

use renderer_base;
use stdClass;

class students_filter extends \core\output\datafilter {

    /** @var int $courseid The course id. */
    protected $courseid;

    /**
     * Constructor.
     *
     * @param \context $context The context where the filters are being rendered.
     * @param string|null $tableregionid Container of the table to be updated by this filter.
     * @param int $courseid The course id.
     */
    public function __construct(\context $context, ?string $tableregionid, int $courseid) {
        parent::__construct($context, $tableregionid);
        $this->courseid = $courseid;
    }

    /**
     * Get data for all filter types.
     *
     * @return array
     */
    protected function get_filtertypes(): array {
        $filtertypes = [];

        if ($filtertype = $this->get_assignment_filter()) {
            $filtertypes[] = $filtertype;
        }

        if ($filtertype = $this->get_groups_filter()) {
            $filtertypes[] = $filtertype;
        }

        if ($filtertype = $this->get_status_filter()) {
            $filtertypes[] = $filtertype;
        }

        if ($filtertype = $this->get_graded_filter()) {
            $filtertypes[] = $filtertype;
        }

        return $filtertypes;
    }

    /**
     * Get data for the assignment filter.
     *
     * @return stdClass|null
     */
    protected function get_assignment_filter(): ?stdClass {
        global $DB;

        $assignments = $DB->get_records('assign', ['course' => $this->courseid], 'name ASC', 'id, name');

        if (empty($assignments)) {
            return null;
        }

        return $this->get_filter_object(
            'assignment',
            get_string('assignments', 'report_assignfeedback_download'),
            false,
            true,
            null,
            array_map(function($assign) {
                return (object) [
                    'value' => $assign->id,
                    'title' => format_string($assign->name, true, ['context' => $this->context]),
                ];
            }, array_values($assignments))
        );
    }

    /**
     * Get data for the groups filter.
     *
     * @return stdClass|null
     */
    protected function get_groups_filter(): ?stdClass {
        global $USER;

        $coursecontext = $this->context;
        if ($this->context->contextlevel == CONTEXT_MODULE) {
            $coursecontext = $this->context->get_parent_context();
        }

        $course = get_course($coursecontext->instanceid);
        $seeallgroups = has_capability('moodle/site:accessallgroups', $coursecontext);
        $seeallgroups = $seeallgroups || ($course->groupmode != SEPARATEGROUPS);

        if ($seeallgroups) {
            $groups = groups_get_all_groups($course->id);
        } else {
            $groups = groups_get_all_groups($course->id, $USER->id);
        }

        if (empty($groups)) {
            return null;
        }

        return $this->get_filter_object(
            'groups',
            get_string('groups'),
            false,
            true,
            null,
            array_map(function($group) {
                return (object) [
                    'value' => $group->id,
                    'title' => format_string($group->name, true, ['context' => $this->context]),
                ];
            }, array_values($groups))
        );
    }

    /**
     * Get data for the submission status filter.
     *
     * @return stdClass|null
     */
    protected function get_status_filter(): ?stdClass {
        return $this->get_filter_object(
            'status',
            get_string('submissionstatus', 'report_assignfeedback_download'),
            false,
            false,
            null,
            [
                (object) [
                    'value' => 1,
                    'title' => get_string('submitted', 'report_assignfeedback_download'),
                ],
                (object) [
                    'value' => 0,
                    'title' => get_string('notsubmitted', 'report_assignfeedback_download'),
                ],
            ]
        );
    }

    /**
     * Get data for the graded status filter.
     *
     * @return stdClass|null
     */
    protected function get_graded_filter(): ?stdClass {
        return $this->get_filter_object(
            'graded',
            get_string('gradingstatus', 'report_assignfeedback_download'),
            false,
            false,
            null,
            [
                (object) [
                    'value' => 1,
                    'title' => get_string('graded', 'report_assignfeedback_download'),
                ],
                (object) [
                    'value' => 0,
                    'title' => get_string('notgraded', 'report_assignfeedback_download'),
                ],
            ]
        );
    }

    /**
     * Export the renderer data in a mustache template friendly format.
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output): stdClass {
        return (object) [
            'tableregionid' => $this->tableregionid,
            'courseid' => $this->courseid,
            'filtertypes' => $this->get_filtertypes(),
            'rownumber' => 1,
        ];
    }
}
