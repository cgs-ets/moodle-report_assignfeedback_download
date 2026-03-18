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
 * Students table filterset.
 *
 * @package    report_assignfeedback_download
 * @copyright  2026 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace report_assignfeedback_download\table;

use core_table\local\filter\filterset;
use core_table\local\filter\integer_filter;

class students_filterset extends filterset {

    /**
     * Get the required filters.
     *
     * The courseid is required to scope the query.
     *
     * @return array
     */
    public function get_required_filters(): array {
        return [
            'courseid' => integer_filter::class,
        ];
    }

    /**
     * Get the optional filters.
     *
     * - assignment: filter by assignment.
     * - groups: filter by course groups.
     * - status: filter by submission status (submitted or not).
     * - graded: filter by grading status (graded or not).
     *
     * @return array
     */
    public function get_optional_filters(): array {
        return [
            'assignment' => integer_filter::class,
            'groups' => integer_filter::class,
            'status' => integer_filter::class,
            'graded' => integer_filter::class,
        ];
    }
}
