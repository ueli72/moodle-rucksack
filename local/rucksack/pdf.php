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
 * PDF export endpoint for a user's earned badges.
 *
 * @package    local_rucksack
 * @copyright  Ueli Leutwyler
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

require_once($CFG->libdir . '/badgeslib.php');
require_once($CFG->dirroot . '/local/rucksack/lib.php');

global $CFG, $PAGE, $OUTPUT, $DB;

$context = context_system::instance();

$userhash = required_param('user', PARAM_TEXT);
$userid = (int)local_rucksack_decrypt($userhash);

if ($userid <= 0) {
    throw new moodle_exception('invaliduser', 'local_rucksack');
}

$targetuser = $DB->get_record('user', ['id' => $userid]);
if (!$targetuser) {
    throw new moodle_exception('invaliduser', 'local_rucksack');
}

$PAGE->set_context($context);
$PAGE->set_pagelayout('standard');
$PAGE->set_url('/local/rucksack/pdf.php', ['user' => $userhash]);

$renderer = $PAGE->get_renderer('local_rucksack');
$renderable = new \local_rucksack\output\earned_badges($userid);
$data = $renderable->export_for_pdf($renderer);

// PDF uses the same template as the screen, but hides the action buttons.
$data->showpdfbutton = false;

$html = $renderer->render_from_template('local_rucksack/earned_badges', $data);

// Convert to standalone HTML with embedded CSS and images.
$html = local_rucksack_make_pdf_html($html, fullname($targetuser));

// Generate PDF.
$pdfpath = local_rucksack_generate_pdf($html, $targetuser);

if (!$pdfpath || !file_exists($pdfpath)) {
    throw new moodle_exception('pdfgenerationfailed', 'local_rucksack');
}

// Send PDF.
$filename = clean_filename(get_string('badgesfor', 'local_rucksack', fullname($targetuser)) . '_' . date('Y-m-d') . '.pdf');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($pdfpath));
readfile($pdfpath);

// Clean up.
unlink($pdfpath);
die();
