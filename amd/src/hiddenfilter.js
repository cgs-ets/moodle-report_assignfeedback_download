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
 * A hidden filter that reads its value from a data attribute on the filter root node.
 *
 * @module     report_assignfeedback_download/hiddenfilter
 * @copyright  2026 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import Filter from 'core/datafilter/filtertype';

export default class extends Filter {

    /**
     * @param {String} filterType The filter name.
     * @param {HTMLElement} filterSet The root filter set element.
     * @param {String} dataAttribute The data attribute name (camelCase) to read the value from.
     */
    constructor(filterType, filterSet, dataAttribute) {
        super(filterType, filterSet);
        this.dataAttribute = dataAttribute;
    }

    // No UI needed for hidden filters.
    async addValueSelector() {
        // eslint-disable-line no-empty-function
    }

    /**
     * Get the composed value for this filter.
     *
     * @returns {Object}
     */
    get filterValue() {
        return {
            name: this.name,
            jointype: 1,
            values: [parseInt(this.rootNode.dataset[this.dataAttribute], 10)],
        };
    }
}
