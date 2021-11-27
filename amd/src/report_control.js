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
 * Defines the APIs used by log reports
 *
 * @package    report
 * @subpackage ibassessmentreport
 * @copyright  2021 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(["jquery", "core/ajax", "core/log"], function ($, Ajax, Log) {
  "use strict";

  function init() {
    Y.log("ibassessmentreport control...");
    var control = new Controls();
    control.main();
  }

  function Controls() {
    let self = this;
  }

  /**
   * Run the controller.
   *
   */
  Controls.prototype.main = function () {
    let self = this;
    self.selectaction();
  };

  Controls.prototype.selectaction = function () {
    var self = this;
    var selectall = document.getElementById("selectall");
    selectall.addEventListener("click", self.checkedhandler);
  };

  Controls.prototype.checkedhandler = function (e) {
    Y.log(e);

    //  Collect all the users ids
    var t = document.getElementById("ibasesstableb");
    if (t) {
      var userids = [];
      Array.from(t.rows).forEach((tr) => {
        const checkbox = tr.cells[0].firstChild;
        checkbox.setAttribute("checked", true);
        const users = checkbox.getAttribute("id").split("_");
        userids.push(users[users.length - 1]);
      });
      Y.log(userids);
    }
    var form = document.getElementById("ibassessmentform");
    var selectedusers = form.querySelector('input[name="selectedusers"]');
    selectedusers.value = userids;
  };

  return { init: init };
});
