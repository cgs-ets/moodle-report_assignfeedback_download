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
 * Assignment reports students filter management.
 *
 * @module     report_assignfeedback_download/students_filter
 * @copyright  2026 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import CoreFilter from 'core/datafilter';
import CourseFilter from 'core/datafilter/filtertypes/courseid';
import * as DynamicTable from 'core_table/dynamic';
import Selectors from 'core/datafilter/selectors';
import Notification from 'core/notification';
import Pending from 'core/pending';

// Hidden filter names that should be skipped when restoring from config.
const HIDDEN_FILTERS = ['courseid'];

/**
 * Initialise the students filter on the element with the given id.
 *
 * @param {String} filterRegionId The id for the filter element.
 */
export const init = (filterRegionId) => {

    const filterSet = document.getElementById(filterRegionId);

    // Create and initialize filter.
    const coreFilter = new CoreFilter(filterSet, function(filters, pendingPromise) {
        DynamicTable.setFilters(
            DynamicTable.getTableFromId(filterSet.dataset.tableRegion),
            {
                jointype: parseInt(filterSet.querySelector(Selectors.filterset.fields.join).value, 10),
                filters,
            }
        )
            .then(result => {
                pendingPromise.resolve();
                return result;
            })
            .catch(Notification.exception);
    });

    // Add required hidden filters that are always sent with every AJAX request.
    coreFilter.activeFilters.courseid = new CourseFilter('courseid', filterSet);
    coreFilter.init();

    /**
     * Set the current filter options based on a provided configuration.
     *
     * @param {Object} config
     * @param {Number} config.jointype
     * @param {Object} config.filters
     * @returns {Promise}
     */
    const setFilterFromConfig = config => {
        const filterConfig = Object.entries(config.filters);

        if (!filterConfig.length) {
            return Promise.resolve();
        }

        // Set the main join type.
        filterSet.querySelector(Selectors.filterset.fields.join).value = config.jointype;

        const filterPromises = filterConfig.map(([filterType, filterData]) => {
            if (HIDDEN_FILTERS.includes(filterType)) {
                return false;
            }

            const filterValues = filterData.values;

            if (!filterValues.length) {
                return false;
            }
            return coreFilter.addFilterRow()
                .then(([filterRow]) => {
                    coreFilter.addFilter(filterRow, filterType, filterValues);
                    return;
                });
        }).filter(promise => promise);

        if (!filterPromises.length) {
            return Promise.resolve();
        }

        return Promise.all(filterPromises)
            .then(() => {
                return coreFilter.removeEmptyFilters();
            })
            .then(() => {
                coreFilter.updateFiltersOptions();
                return;
            })
            .then(() => {
                coreFilter.updateTableFromFilter();
                return;
            });
    };

    // Initialize DynamicTable for showing result.
    const tableRoot = DynamicTable.getTableFromId(filterSet.dataset.tableRegion);
    const initialFilters = DynamicTable.getFilters(tableRoot);
    if (initialFilters) {
        const initialFilterPromise = new Pending('report_assignfeedback_download/filter:setFilterFromConfig');
        setFilterFromConfig(initialFilters)
            .then(() => initialFilterPromise.resolve())
            .catch();
    }
};
