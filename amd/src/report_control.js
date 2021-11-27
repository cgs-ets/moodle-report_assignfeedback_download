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
    self.init_tree("feedback_files_tree");
  };

//   Controls.prototype.render_feedback_file_tree = function () {
//     var self = this;

//     $("#connectassignrep")
//       .children()
//       .each(function (e) {
//         $(this)
//           .find("td:last")
//           .children()
//           .each(function (i) {
//             let treecol = $(this).first()[i];
//             let htmlid = $(treecol).attr("id");

//             self.init_tree(htmlid);
//           });
//       });
//   };

  Controls.prototype.treeInit = function (htmlid) {
    var treeElement = Y.one("#assign_files_tree");

    if (treeElement) {
      Y.use("yui2-treeview", "node-event-simulate", function (Y) {
        var tree = new Y.YUI2.widget.TreeView(htmlid);
        tree.subscribe("clickEvent", function (node, event) {
          // We want normal clicking which redirects to url.
          return false;
        });

        tree.subscribe("enterKeyPressed", function (node) {
          // We want keyboard activation to trigger a click on the first link.
          Y.one(node.getContentEl()).one("a").simulate("click");
          return false;
        });

        tree.setNodesProperty("className", "feedbackfilestv", false);
        tree.render();
      });
    }
  };

  return {init: init};
});
