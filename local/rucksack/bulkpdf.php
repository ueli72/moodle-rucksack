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
 * Bulk PDF generation API endpoint.
 *
 * Accepts a token and a list of Moodle usernames. Returns a single PDF or a
 * ZIP archive depending on the number of users.
 *
 * @package    local_rucksack
 * @copyright  Ueli Leutwyler
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/badgeslib.php');
require_once($CFG->dirroot . '/local/rucksack/lib.php');

// No require_login() – this endpoint is token-authenticated.

// ------------------------------------------------------------------
// Read token
// ------------------------------------------------------------------
$token = '';

// 1. Authorization header.
$authheader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (preg_match('/Bearer\s+(\S+)/i', $authheader, $matches)) {
    $token = $matches[1];
}

// 2. Fallback to query / POST parameter.
if (empty($token)) {
    $token = optional_param('token', '', PARAM_ALPHANUMEXT);
}

// 3. Validate token format: instanceid_hash.
if (empty($token) || !preg_match('/^(\d+)_([a-zA-Z0-9]+)$/', $token, $matches)) {
    header('HTTP/1.1 401 Unauthorized');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => get_string('invalidtoken', 'local_rucksack')]);
    die();
}

$instanceid = (int)$matches[1];
$hash       = $matches[2];

// Load block instance.
$blockinstance = $DB->get_record('block_instances', ['id' => $instanceid, 'blockname' => 'rucksack']);
if (!$blockinstance) {
    header('HTTP/1.1 401 Unauthorized');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => get_string('invalidtoken', 'local_rucksack')]);
    die();
}

// Verify stored hash.
$config = unserialize(base64_decode($blockinstance->configdata));
if (!is_object($config) || empty($config->apitoken) || $config->apitoken !== $hash) {
    header('HTTP/1.1 401 Unauthorized');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => get_string('invalidtoken', 'local_rucksack')]);
    die();
}

// ------------------------------------------------------------------
// Read request parameters
// ------------------------------------------------------------------
$users    = optional_param_array('users', [], PARAM_TEXT);
$filename = optional_param('filename', '', PARAM_FILE);

if (empty($users)) {
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => get_string('nobulkusers', 'local_rucksack')]);
    die();
}

// ------------------------------------------------------------------
// Determine template set from block config
// ------------------------------------------------------------------
$setid = !empty($config->templateset) ? (int)$config->templateset : 0;

// ------------------------------------------------------------------
// Generate PDFs
// ------------------------------------------------------------------
$tempdir = make_temp_directory('local_rucksack_bulkpdf');
$files   = [];
$errors  = [];

foreach ($users as $username) {
    $username = trim($username);
    if (empty($username)) {
        continue;
    }

    $user = $DB->get_record('user', ['username' => $username, 'deleted' => 0, 'suspended' => 0]);
    if (!$user) {
        $errors[] = "User '$username' not found";
        continue;
    }

    // We need a page renderer for Mustache; system context is fine.
    $PAGE->set_context(context_system::instance());
    $renderer   = $PAGE->get_renderer('local_rucksack');
    $renderable = new \local_rucksack\output\earned_badges($user->id, $setid);
    $data       = $renderable->export_for_pdf($renderer);
    $data->showpdfbutton = false;

    $html = $renderer->render_earned_badges_data($data, $setid);
    $html = local_rucksack_make_pdf_html($html, fullname($user), $setid);

    $pdfpath = local_rucksack_generate_pdf($html, $user);
    if (!$pdfpath || !file_exists($pdfpath)) {
        $errors[] = "PDF generation failed for '$username'";
        continue;
    }

    $pdfname = 'Kompetenznachweise_' . $user->firstname . '_' . $user->lastname . '.pdf';
    $destpath = $tempdir . '/' . clean_filename($pdfname);
    rename($pdfpath, $destpath);
    $files[] = $destpath;
}

// ------------------------------------------------------------------
// Nothing generated?
// ------------------------------------------------------------------
if (empty($files)) {
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error'   => get_string('bulkpdferror', 'local_rucksack'),
        'details' => $errors,
    ]);
    die();
}

// ------------------------------------------------------------------
// Single PDF → direct download
// ------------------------------------------------------------------
if (count($files) === 1) {
    $filepath = $files[0];
    $downloadname = basename($filepath);

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $downloadname . '"');
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);
    unlink($filepath);
    die();
}

// ------------------------------------------------------------------
// Multiple PDFs → ZIP download
// ------------------------------------------------------------------
$zipname = !empty($filename)
    ? (substr($filename, -4) === '.zip' ? $filename : $filename . '.zip')
    : 'rucksack_pdfs_' . date('Y-m-d') . '.zip';

$zippath = $tempdir . '/' . clean_filename($zipname);

$zip = new ZipArchive();
if ($zip->open($zippath, ZipArchive::CREATE) !== true) {
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Could not create ZIP archive']);
    die();
}

foreach ($files as $file) {
    $zip->addFile($file, basename($file));
}
$zip->close();

// Clean up individual PDFs before sending ZIP.
foreach ($files as $f) {
    if (file_exists($f)) {
        unlink($f);
    }
}

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipname . '"');
header('Content-Length: ' . filesize($zippath));
readfile($zippath);
unlink($zippath);
die();
