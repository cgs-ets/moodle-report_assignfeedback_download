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
 * Excel writer abstraction layer extension.
 * Allow to save excel files in a zip file.
 * @copyright  2022 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package    report_assignfeedback_download
 */

use PhpOffice\PhpSpreadsheet\IOFactory;

class AssignfedbackDownloaderExcelWorkbook extends MoodleExcelWorkbook {

    /**
     *  Save the excel file to a temp dir to be able to download more than one excel
     *  per request. savetotempdir
     */
    public function savetotempdir($tempdir) {

        foreach ($this->objspreadsheet->getAllSheets() as $sheet) {
            $sheet->setSelectedCells('A1');
        }
        $this->objspreadsheet->setActiveSheetIndex(0);

        $filename = preg_replace('/\.xlsx?$/i', '', $this->filename);

        $filename = $filename.'.xlsx';

        if (is_https()) { // HTTPS sites - watch out for IE! KB812935 and KB316431.
            header('Cache-Control: max-age=10');
            header('Expires: '. gmdate('D, d M Y H:i:s', 0) .' GMT');
            header('Pragma: ');
        } else { // Normal http - prevent caching at all cost.
            header('Cache-Control: private, must-revalidate, pre-check=0, post-check=0, max-age=0');
            header('Expires: '. gmdate('D, d M Y H:i:s', 0) .' GMT');
            header('Pragma: no-cache');
        }

        if (core_useragent::is_ie() || core_useragent::is_edge()) {
            $filename = rawurlencode($filename);
        } else {
            $filename = s($filename);
        }

        $tempdir = "$tempdir/$filename";
        $objwriter = IOFactory::createWriter($this->objspreadsheet, $this->type);

        $objwriter->save($tempdir);
    }
}
