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
 *  Library file to help with PDF generation
 *
 * @package    report
 * @subpackage assignfeedback_download
 * @copyright  2022 Veronica Bermegui
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace report_assignfeedback_download\pdflib;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/pdflib.php');
require_once($CFG->libdir.'/tcpdf/tcpdf.php');

use moodle_url;
use Mpdf\Mpdf;
use stdClass;
use TCPDF;



require_once($CFG->dirroot . '/report/assignfeedback_download/vendor/autoload.php');

/**
 * Generate the PDF with the flexible rubric for a student. It contains the checked descriptors
 * Mpdf was used in this case because TCPDF did not render checkboxes from html properply.
 */
function report_assignfeedback_download_create_frubric_pdf($rubric, $assessname, $frubric) {
    global $CFG, $OUTPUT;

    $rubric = json_decode($rubric);
    $jsonparts = explode('</div>', $rubric);
    $table = $jsonparts[0];

    $table = str_replace('<table class="criteria-table table-light ">', '<table "style=\'font-family:helvetica\'"> ', $table);
    $table = str_replace(
        '<input disabled type="checkbox" id ="" name = ""  value = "1" checked = "checked"  >',
        '<span style=\'font-family:helvetica\'>&#9745;</span>',
        $table
    );

    $totalgrade = "<strong>TOTAL:  $frubric->grade </strong>";
    $rubric = $table .
              '<br><br><strong> FEEDBACK COMMENT </strong><br><br>' .
              $frubric->feedbackcomment .
              '<br><br>' .
               $totalgrade;

    $mpdf = new Mpdf(['tempDir' => $CFG->tempdir . '/', 'assignment_', 'mode' => 's', 'debug' => false]);
    $mpdf->SetFont('DejaVuSans', '', 9);
    $mpdf->backupSubsFont = ['dejavusans'];
    $mpdf->allow_charset_conversion = true;

    $data = new stdClass();
    $data->rubric = $rubric;

    $mpdf->WriteHTML($rubric);
    $mpdf->showImageErrors = true;
    $pathfilename = $assessname;
    $fd = new \stdClass();
    $fd->filename = $frubric->rubricfilename;
    $fd->pathfilename = $pathfilename;
    $fd->pdf = $mpdf;

    return $fd;
}

function report_assignfeedback_download_generatefeedbackpdf($content, $assessmentname, $student, $course) {

    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    // Set default header data.
    $pdfheaderstring = "ASSESSMENT: $assessmentname \n STUDENT: $student->firstname $student->lastname";

    $pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, 'COURSE: ' . $course->fullname, $pdfheaderstring);

    // Set header and footer fonts.
    $pdf->setHeaderFont(array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

    // Set margins.
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

    // Set auto page breaks.
    $pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);

    // Set image scale factor.
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

    // Add a page.
    $pdf->AddPage();
    $html = pdfassessmentdownloader_fix_image_links($content);

    // Output the HTML content.
    $pdf->writeHTML($html);
    $pathfilename = $assessmentname;

    $fd = new \stdClass();
    $fd->filename = "$student->lastname  $student->firstname $course->fullname .pdf";
    $fd->pathfilename = $pathfilename;
    $fd->pdf = $pdf;

    return $fd;
}


 /**
  * Adapted from giportfoliotool_print
  *
  * @copyright 2014 Davo Smith, Synergy Learning
  * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
  */
function pdfassessmentdownloader_fix_image_links($html) {
        global $CFG;

        $html = pdfassessmentdownloader_fix_svg_images($html);

        $baseurl = new moodle_url('/pluginfile.php');
        $baseurl = preg_quote($baseurl->out());
        $regex = "|<img[^>]*src=\"({$baseurl}([^\"]*))|";

    if (preg_match_all($regex, $html, $matches)) {
        $fs = get_file_storage();
        foreach ($matches[2] as $params) {
            if (substr($params, 0, 1) == '?') {
                $pos = strpos($params, 'file=');
                $params = substr($params, $pos + 5);
            } else {
                if (($pos = strpos($params, '?')) !== false) {
                    $params = substr($params, 0, $pos - 1);
                }
            }
            $params = urldecode($params);
            $params = explode('/', $params);
            array_shift($params); // Remove empty first param.
            $contextid = (int)array_shift($params);
            $component = clean_param(array_shift($params), PARAM_COMPONENT);
            $filearea  = clean_param(array_shift($params), PARAM_AREA);
            $itemid = array_shift($params);

            if (empty($params)) {
                $filename = $itemid;
                $itemid = 0;
            } else {
                $filename = array_pop($params);
            }

            if (empty($params)) {
                $filepath = '/';
            } else {
                $filepath = '/'.implode('/', $params).'/';
            }

            if (!$file = $fs->get_file($contextid, $component, $filearea, $itemid, $filepath, $filename)) {
                if ($itemid) {
                    $filepath = '/'.$itemid.$filepath; // See if there was no itemid in the original URL.
                    $itemid = 0;
                    $file = $fs->get_file($contextid, $component, $filename, $itemid, $filepath, $filename);
                }
            }

            if (!$file) {
                $content = file_get_contents($CFG->dirroot.'/pix/spacer.gif');
            } else {
                $content = $file->get_content();
            }
            $content = '@'.base64_encode($content);
            $html = str_replace($matches[1], $content, $html);
        }
    }

        return $html;
}

function pdfassessmentdownloader_fix_svg_images($html) {
    $baseurl = new moodle_url('/theme/image.php');
    $baseurl = preg_quote($baseurl->out());
    $html = preg_replace_callback("|({$baseurl})([^\"']*)|", function ($matches) {
        global $CFG;
        if (substr($matches[2], 0, 1) == '?') {
            // Not using slash arguments.
            $sep = '&';
            if (strpos($matches[2], '&amp;') !== false) {
                $sep = '&amp;';
            }

            // See if the file can be rewritten as a direct link to the file.
            $parts = explode($sep, $matches[2]);
            $params = array();
            foreach ($parts as $part) {
                $keyvalue = explode('=', $part, 2);
                if (count($keyvalue) < 2) {
                    continue;
                }
                $params[$keyvalue[0]] = $keyvalue[1];
            }
            if (isset($params['component']) && $params['component'] == 'core') {
                if (isset($params['image'])) {
                    $filepath = urldecode($params['image']);
                    $filepath = $CFG->dirroot.'/pix/'.$filepath;
                    foreach (array('.gif', '.png') as $ext) {
                        if (file_exists($filepath.$ext)) {
                            return '@'.base64_encode(file_get_contents($filepath.$ext));
                        }
                    }
                }
            }

            // Rewrite the non-slash arguments URL.
            if (strpos($matches[2], 'svg=0') !== false) {
                return $matches[0]; // svg=0 already set => nothing to change.
            }
            return $matches[1].$matches[2].$sep.'svg=0'; // Add 'svg=0' to parameters
        }

        // Slash arguments.

        // See if the file can be rewritten as a direct link to the file.
        $parts = explode('/', $matches[2]);
        if ($parts[2] == 'core') {
            $parts = array_slice($parts, 4); // Remove 'theme', 'core' and 'iteration' params
            $filepath = implode('/', $parts);
            $filepath = $CFG->dirroot.'/pix/'.$filepath;
            foreach (array('.gif', '.png') as $ext) {
                if (file_exists($filepath.$ext)) {
                    return '@'.base64_encode(file_get_contents($filepath.$ext));
                }
            }
        }

        if (substr($matches[1], 4) == '/_s/') {
            return $matches[0]; // /_s/ prefix already set => nothing to change.
        }
        return $matches[1].'/_s'.$matches[2]; // Add /_s/ prefix to the start of the path.
    }, $html);

    return $html;
}
