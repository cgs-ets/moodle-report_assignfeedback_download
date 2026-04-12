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
 * Bulk actions for the assignment reports students table.
 *
 * @module     report_assignfeedback_download/bulk_actions
 * @copyright  2026 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import CheckboxToggleAll from 'core/checkbox-toggleall';

/**
 * Initialise bulk actions.
 *
 * @param {String} formId The form element id.
 */
export const init = (formId) => {
    // Ensure CheckboxToggleAll listeners are registered.
    CheckboxToggleAll.init();

    const form = document.getElementById(formId);
    if (!form) {
        return;
    }

    const actionSelect = form.querySelector('#formactionid');
    if (!actionSelect) {
        return;
    }

    // Handle the bulk action select change.
    actionSelect.addEventListener('change', (e) => {
        const action = e.target.value;
        if (!action) {
            return;
        }

        // For download actions (URLs), redirect with selected user ids.
        if (action.indexOf('#') === -1 && action !== '') {
            e.preventDefault();

            // Collect selected user ids.
            const checkboxes = form.querySelectorAll(
                'input[data-togglegroup="students-table"][data-toggle="slave"]:checked'
            );

            if (!checkboxes.length) {
                actionSelect.value = '';
                return;
            }

            // Build URL with user ids.
            const url = new URL(action, window.location.origin);
            checkboxes.forEach(checkbox => {
                const userid = checkbox.getAttribute('name').replace('user', '');
                url.searchParams.append('userid[]', userid);
            });

            // Include active assignment filter IDs so download.php respects the filter.
            const tableEl = document.querySelector('[data-region="core_table/dynamic"]');
            if (tableEl) {
                try {
                    const filtersData = JSON.parse(tableEl.dataset.tableFilters);
                    const assignmentFilter = filtersData.filters && filtersData.filters.assignment;
                    if (assignmentFilter && assignmentFilter.values && assignmentFilter.values.length) {
                        assignmentFilter.values.forEach(assignid => {
                            url.searchParams.append('assignid[]', assignid);
                        });
                    }
                } catch (err) {
                    // If parsing fails, proceed without assignment filter.
                }
            }

            // Navigate to download URL.
            window.location.href = url.toString();
            actionSelect.value = '';
        }
    });
};
