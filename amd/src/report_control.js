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
    let userids = [];
    var control = new Controls(userids);
    control.main();
  }

  function Controls(userids) {
    let self = this;
    self.userids = userids;
  }

  /**
   * Run the controller.
   *
   */
  Controls.prototype.main = function () {
    let self = this;

    self.initeventhablers();
    //self.selectbyone();
    self.selectaction();
  };

  Controls.prototype.initeventhablers = function () {
    var self = this;
    var t = document.getElementById("ibasesstableb");
    if (t) {
      //  Collect all the users ids
      Array.from(t.rows).forEach((tr) => {
        const checkbox = tr.cells[0].firstChild;
        checkbox.addEventListener("click", self.selectbyone.bind(self, this));
      });
    }
  };

  Controls.prototype.selectaction = function () {
    var self = this;
    var selectall = document.getElementById("selectall");
    selectall.addEventListener("click", self.checkedhandler.bind(self, this));
  };

  Controls.prototype.checkedhandler = function (s, e) {
    var t = document.getElementById("ibasesstableb");
    Y.log(s.userids);
    if (t) {
      //  Collect all the users ids
      Array.from(t.rows).forEach((tr) => {
        const checkbox = tr.cells[0].firstChild;
        checkbox.checked = !checkbox.checked;
        const users = checkbox.getAttribute("id").split("_");
        const userid = users[users.length - 1];

        if (!checkbox.checked && s.userids.includes(userid)) {
          const index = s.userids.indexOf(userid);
          if (index > -1) {
            s.userids.splice(index, 1);
          }
        } else {
          s.userids.push(users[users.length - 1]);
        }
      });
    }
    var form = document.getElementById("ibassessmentform");
    var selectedusers = form.querySelector('input[name="selectedusers"]');
    selectedusers.value = s.userids;
  };

  Controls.prototype.selectbyone = function (s, e) {
    Y.log(s);
    Y.log(e);
    let userid = e.target.id;
    userid = userid.split("_");
    userid = userid[userid.length - 1];

    if (!e.target.checked && s.userids.includes(userid)) {
      const index = s.userids.indexOf(userid);
      if (index > -1) {
        s.userids.splice(index, 1);
      }
    } else {
      s.userids.push(userid);
    }

    var form = document.getElementById("ibassessmentform");
    var selectedusers = form.querySelector('input[name="selectedusers"]');
    selectedusers.value = s.userids;
  };

  return { init: init };
});
