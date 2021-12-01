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
 * A report to display the outcome of scheduled assignfeedback_download
 *
 * @package    report
 * @subpackage assignfeedback_download
 * @copyright  2021 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class assignfeedback_download_form extends moodleform {

    function definition() {
        global $CFG, $DB;

        $mform = $this->_form;
        $mform->addElement('hidden', 'id', $this->_customdata['id']);
        $mform->settype('id', PARAM_INT); // To be able to pre-fill the form

        // $mform->addElement('header', 'parameters', get_string('parameters', 'report_assignfeedback_download'));

        $assessarray = array();

        $assessarray[] = get_string('all', 'report_assignfeedback_download');

        $result = $DB->get_records_select('assign', 'course = ?', array($this->_customdata['id']), 'name');
      
        foreach ($result as $row) {
            $assessarray[$row->id] = $row->name;
        }

        $mform->addElement('select', 'assessments', get_string('allassessment', 'report_assignfeedback_download'), $assessarray);
        $mform->getElement('assessments')->setMultiple(true);
        $mform->setDefault('assessments', 0);

        $buttonarray = array();

        $buttonarray[] = &$mform->createElement('submit', 'submitbutton', get_string('filterassess', 'report_assignfeedback_download'));
        $buttonarray[] = &$mform->createElement('cancel', 'canceltbutton', get_string('cancel', 'report_assignfeedback_download'));

        $mform->addGroup($buttonarray, 'buttonar', '', [' '], false);
        $mform->closeHeaderBefore('buttonar');
    }

}
