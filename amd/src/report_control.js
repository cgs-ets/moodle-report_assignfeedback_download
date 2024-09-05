/* eslint-disable no-unused-vars */
/* eslint-disable require-jsdoc */
/* eslint-disable jsdoc/require-jsdoc */
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
 *
 * @package    report
 * @subpackage ibassessmentreport
 * @copyright  2021 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(["jquery", "core/ajax", "core/log"/*, "report_assignfeedback_download/html2pdf"*/], function ($, Ajax, Log, /*html2pdf*/) {
    "use strict";

    function init() {
        let userids = [];
        let useritemids = [];
        var control = new Controls(userids, useritemids);
        control.main();
    }

    function Controls(userids, useritemids) {
        let self = this;
        self.userids = userids;
        self.useritemids = useritemids;
        self.frcollection = new Map();
        self.indexFrubric = (Array.from(document.querySelector('td.rubric-cell').parentElement.cells)).indexOf(document.querySelector('td.rubric-cell'));
        self.indexGrade = (Array.from(document.querySelector('td.grade-cell').parentElement.cells)).indexOf(document.querySelector('td.grade-cell'));
        console.log(self.indexGrade);

        $('.report-assignfeedback-downloading').toggle();

    }

    /**
     * Run the controller.
     *
     */
    Controls.prototype.main = function () {
        let self = this;

        self.selectbyone();
        self.selectallaction();
        //  self.rubricaction();  TODO: The  framework isnt downloading with the same style as the one in PHP. First version wont download

        const instanceids = document
            .querySelector("table.assignfeedback_download")
            .getAttribute("data-selected-assign");
        document
            .getElementById("downloadactionform")
            .querySelector('input[name="instanceids"]').value = instanceids;
        const cmids = document
            .querySelector("table.assignfeedback_download")
            .getAttribute("data-selected-cmid");
        document
            .getElementById("downloadactionform")
            .querySelector('input[name="cmids"]').value = cmids;


        document.querySelector('.cant-download-btn').addEventListener('click', self.cantdownloadbuttonhandler);
        //  self.downloadrubric();

        //  document.getElementById("rubrictestdownload").addEventListener("click", self.downloadrubric);

    };

    Controls.prototype.cantdownloadbuttonhandler = function (e) {
        if (document.querySelector('.alert-cant-download').style.display == 'none') {
            document.querySelector('.alert-cant-download').style.display = 'block';
        } else {
            document.querySelector('.alert-cant-download').style.display = 'none';
        }

    }
    Controls.prototype.selectbyone = function () {
        let self = this;
        let t = document.getElementById("iassignfeedbacktb");
        if (t) {
            //  Collect all the users ids
            Array.from(t.rows).forEach((tr) => {
                const checkbox = tr.cells[0].firstChild;
                checkbox.addEventListener(
                    "click",
                    self.selectbyonehandler.bind(self, this)
                );
            });
        }
    };

    Controls.prototype.selectallaction = function () {
        let self = this;
        let selectall = document.getElementById("selectall");
        selectall.addEventListener("click", self.selectallhandler.bind(self, this));
    };

    Controls.prototype.selectallhandler = function (s, e) {
        let t = document.getElementById("iassignfeedbacktb");
        let countusers = 0;
        const selectallcheckstatus = document.getElementById("selectall").checked;

        let form = document.getElementById("downloadactionform");
        let selectedusers = form.querySelector('input[name="selectedusers"]');
        let useritemids = form.querySelector('input[name="itemids"]');
        let frubricdetails = form.querySelector('input[name="frubricdetails"]');


        if (t) {
            Array.from(t.rows).forEach((tr) => {
                const checkbox = tr.cells[0].firstChild;
                checkbox.checked = selectallcheckstatus;
                const users = checkbox.getAttribute("id").split("_");
                const userid = users[users.length - 1];
                const innertable = document.getElementById(`innertable_${userid}`);
                const uitemids = innertable
                    .getAttribute("data-user-grade-id")
                    .split(",");
                uitemids.pop(); // Remove the last empty item.
                const usersummary = {
                    userid: userid,
                    uitemids: uitemids,

                };

                s.useritemids.push(usersummary);
                countusers++;

                if (!checkbox.checked && s.userids.includes(userid)) {
                    const index = s.userids.indexOf(userid);
                    if (index > -1) {
                        s.userids.splice(index, 1);
                        const aux = s.useritemids;

                        s.useritemids = aux.filter(function (el) {
                            if (el.userid != userid) {
                                return el;
                            }
                        }, userid);
                        // Remove from Frubric
                        s.frcollection.delete(userid);
                        frubricdetails.value = JSON.stringify(Object.fromEntries(s.frcollection));
                    }

                } else if (checkbox.checked && !s.userids.includes(userid)) {
                    s.userids.push(users[users.length - 1]);
                    // Check FRubric
                    const innertabletr = document.getElementById(`innertable_${userid}`).querySelector('tbody');
                    for (let i = 0; i < innertabletr.rows.length; i++) {
                        if (innertabletr.rows[i].cells[s.indexFrubric].children.length > 0) { // It has  a rubric
                            if (!s.frcollection.has(userid)) {
                                s.frcollection.set(userid, []);
                            }
                            const frcontainer = innertabletr.rows[i].cells[s.indexFrubric].firstElementChild;
                            s.frcollection.get(userid).push(frcontainer.getAttribute('data-frubric-params'));

                            // Check if the rubric will be able to download
                            //alert-cant-download
                            const gradecontainer = innertabletr.rows[i].cells[s.indexGrade];

                            if (gradecontainer.getAttribute('data-candownload') == 0) {
                                document.querySelector('.alert-cant-download').style.display = 'block'
                            }
                        }
                    }

                    frubricdetails.value = JSON.stringify(Object.fromEntries(s.frcollection));
                }


            });

            if (!selectallcheckstatus) {
                s.userids = []; // Clear the array.
            }

            if (countusers == s.userids.length) {
                (document.getElementById("id_operation").children[0]).disabled = false;
            } else {
                (document.getElementById("id_operation").children[0]).disabled = true;
            }

        }

        selectedusers.value = s.userids;
        useritemids.value = JSON.stringify(s.useritemids);

        // Enable the download select only if there are selected users.
        if (selectedusers.value) {
            document.getElementById("id_operation").disabled = false;
            document.getElementById("id_submit").disabled = false;
        } else {
            document.getElementById("id_operation").disabled = true;
            document.getElementById("id_submit").disabled = true;
        }
    };

    Controls.prototype.selectbyonehandler = function (s, e) {
        console.log("selectbyonehandler");
        let userid = e.target.id;
        userid = userid.split("_");
        userid = userid[userid.length - 1];
        const innertable = document.getElementById(`innertable_${userid}`);
        const uitemids = innertable.getAttribute("data-user-grade-id").split(",");
        uitemids.pop(); // Remove the last empty item.
        const usersummary = {
            userid: userid,
            uitemids: uitemids
        };

        s.useritemids.push(usersummary);
        const selectedall = document.getElementById("selectall");

        let form = document.getElementById("downloadactionform");
        let frubricdetails = form.querySelector('input[name="frubricdetails"]');

        if (selectedall.checked) {
            document.getElementById("selectall").checked = false;
        }

        if (!e.target.checked && s.userids.includes(userid)) {
            const index = s.userids.indexOf(userid);
            if (index > -1) {
                s.userids.splice(index, 1);
                const aux = s.useritemids;
                s.useritemids = aux.filter(function (el) {
                    if (el.userid != userid) {
                        return el;
                    }
                }, userid);
                console.log(s.frcollection);
                // Remove from Frubric
                s.frcollection.delete(userid);
                frubricdetails.value = JSON.stringify(Object.fromEntries(s.frcollection));
            }
        } else {
            s.userids.push(userid);
            // Check FRubric
            for (let i = 0; i < innertable.rows.length; i++) {
                if (innertable.rows[i].cells[s.indexFrubric].children.length > 0) { // It has  a rubric
                    if (!s.frcollection.has(userid)) {
                        s.frcollection.set(userid, []);
                    }
                    const frcontainer = innertable.rows[i].cells[s.indexFrubric].firstElementChild;
                    s.frcollection.get(userid).push(frcontainer.getAttribute('data-frubric-params'));

                    // Check if the rubric will be able to download
                    //alert-cant-download
                    const gradecontainer = innertable.rows[i].cells[s.indexGrade];
                    if (gradecontainer.getAttribute('data-candownload') == 0) {
                        document.querySelector('.alert-cant-download').style.display = 'block'
                    }
                }
            }


            frubricdetails.value = JSON.stringify(Object.fromEntries(s.frcollection));
        }


        let selectedusers = form.querySelector('input[name="selectedusers"]');
        let useritemids = form.querySelector('input[name="itemids"]');
        selectedusers.value = s.userids;
        useritemids.value = JSON.stringify(s.useritemids);


        if (selectedusers.value) {
            document.getElementById("id_operation").disabled = false;
            document.getElementById("id_submit").disabled = false;
        } else {
            document.getElementById("id_operation").disabled = true;
            document.getElementById("id_submit").disabled = true;
        }
    };

    // Controls.prototype.rubricaction = function () {
    //     Y.log('rubricaction');
    //     const self = this;
    //     let t = document.getElementById("iassignfeedbacktb");

    //     if (t) {

    //         Array.from(t.rows).forEach((tr) => {
    //             const checkbox = tr.cells[0].firstChild;
    //             const user = checkbox.getAttribute("id").split("_");
    //             const userid = user[user.length - 1];
    //             const innertabletr = document.getElementById(`innertable_${userid}`).querySelector('tbody');

    //             for (let i = 0; i < innertabletr.rows.length; i++) {
    //                 if (innertabletr.rows[i].cells[5].children.length > 0) { // It has  a rubric
    //                     const frcontainer = innertabletr.rows[i].cells[5].firstElementChild;
    //                     frcontainer.addEventListener('click', self.downloadrubric);

    //                 }
    //             }
    //         }, self);
    //     }
    // }

    return {
        init: init
    };
});