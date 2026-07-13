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

$html = $renderer->render_from_template('local_rucksack/earned_badges_pdf', $data);

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

/**
 * Recursively remove a directory.
 *
 * @param string $dir
 */
function local_rucksack_rrmdir($dir) {
    if (!file_exists($dir)) {
        return;
    }
    $objects = scandir($dir);
    foreach ($objects as $object) {
        if ($object == '.' || $object == '..') {
            continue;
        }
        $path = $dir . '/' . $object;
        if (is_dir($path)) {
            local_rucksack_rrmdir($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

/**
 * Build a standalone HTML document with embedded CSS and images.
 *
 * @param string $bodyhtml
 * @param string $username
 * @return string
 */
function local_rucksack_make_pdf_html($bodyhtml, $username) {
    global $CFG;

    $css = '';
    $cssfile = $CFG->dirroot . '/local/rucksack/styles.css';
    if (file_exists($cssfile)) {
        $css = file_get_contents($cssfile);
    }

    // Embed pluginfile images as base64.
    $bodyhtml = preg_replace_callback(
        '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i',
        function ($matches) {
            $src = $matches[1];
            $datauri = local_rucksack_image_to_datauri($src);
            if ($datauri) {
                return str_replace($src, $datauri, $matches[0]);
            }
            return $matches[0];
        },
        $bodyhtml
    );

    return '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>' . s($username) . '</title>
    <style>
        ' . $css . '
        @page { size: A4; margin: 0.3cm; }
        body { font-family: Arial, sans-serif; font-size: 10pt; line-height: 1.35; }
        .local-rucksack-actions { display: none !important; }
        .local-rucksack-header { margin-bottom: 0.75rem; padding-bottom: 0.5rem; }
        .local-rucksack-header h1 { font-size: 1.4rem; margin-bottom: 0.25rem; }
        .local-rucksack-meta { font-size: 0.9rem; margin-bottom: 0.25rem; }
        .local-rucksack-framework { margin-bottom: 0.75rem; }
        .local-rucksack-framework-title { font-size: 1.15rem; margin-bottom: 0.35rem; }
        .local-rucksack-competency { margin-bottom: 0.5rem; }
        .local-rucksack-competency-title { margin-bottom: 0.25rem; }
        .local-rucksack-competency.level-1 > .local-rucksack-competency-title { font-size: 1.05rem; }
        .local-rucksack-competency.level-2 > .local-rucksack-competency-title { font-size: 0.95rem; }
        .local-rucksack-competency.level-3 > .local-rucksack-competency-title { font-size: 0.9rem; }
        .local-rucksack-competency.level-4 > .local-rucksack-competency-title { font-size: 0.85rem; }
        .local-rucksack-competency.level-5 > .local-rucksack-competency-title { font-size: 0.8rem; }
        .local-rucksack-badge-table { width: 100%; border-collapse: separate; border-spacing: 0.5rem; }
        .local-rucksack-badge-table-cell { width: 50%; vertical-align: top; padding: 0.35rem; border: 1px solid #e0e0e0; border-radius: 0.25rem; }
        .local-rucksack-badge-inner { width: 100%; border-collapse: collapse; }
        .local-rucksack-badge-inner td { vertical-align: top; }
        .local-rucksack-badge-image { width: 100px !important; padding-right: 20px !important; flex: none !important; max-width: none !important; }
        .local-rucksack-badge-img { width: 100px !important; height: auto !important; display: block !important; }
        .local-rucksack-badge-content { width: auto !important; flex: none !important; min-width: auto !important; margin-left: 0 !important; }
        .local-rucksack-badge-name { font-size: 0.75rem; margin-bottom: 0.05rem; }
        .local-rucksack-badge-description { font-size: 0.65rem; line-height: 1.1; }
        .local-rucksack-badge-description p { margin-bottom: 0.05rem; }
        .local-rucksack-badge-date { font-size: 0.6rem; margin-top: 0.05rem; }
    </style>
</head>
<body>
    ' . $bodyhtml . '
</body>
</html>';
}

/**
 * Convert an image URL to a data URI.
 *
 * Handles Moodle pluginfile badge images by reading them directly from the file storage.
 *
 * @param string $url
 * @return string|false
 */
function local_rucksack_image_to_datauri($url) {
    global $CFG;

    if (strpos($url, 'data:') === 0) {
        return $url;
    }

    // Try to read Moodle badge image directly from file storage.
    if (preg_match('/pluginfile\.php\/[^\/]+\/badges\/badgeimage\/(\d+)\/f1/', $url, $matches)) {
        $badgeid = (int)$matches[1];
        $datauri = local_rucksack_badge_image_datauri($badgeid);
        if ($datauri) {
            return $datauri;
        }
    }

    // Fallback: try to fetch via HTTP.
    $fullurl = $url;
    if (strpos($url, 'http') !== 0) {
        $fullurl = $CFG->wwwroot . $url;
    }

    $content = @file_get_contents($fullurl);
    if (!$content) {
        return false;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimetype = $finfo->buffer($content);
    return 'data:' . $mimetype . ';base64,' . base64_encode($content);
}

/**
 * Get a badge image as a data URI from the Moodle file storage.
 *
 * @param int $badgeid
 * @return string|false
 */
function local_rucksack_badge_image_datauri($badgeid) {
    try {
        $badge = new \core_badges\badge($badgeid);
        $context = $badge->get_context();
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'badges', 'badgeimage', $badgeid, 'sortorder', false);
        foreach ($files as $file) {
            if ($file->is_valid_image()) {
                return 'data:' . $file->get_mimetype() . ';base64,' . base64_encode($file->get_content());
            }
        }
    } catch (Exception $e) {
        return false;
    }
    return false;
}

/**
 * Generate a PDF file from HTML using LibreOffice.
 *
 * @param string $html
 * @param stdClass $user
 * @return string|false Path to generated PDF.
 */
function local_rucksack_generate_pdf($html, $user) {
    global $CFG;

    $tempdir = make_temp_directory('local_rucksack');
    $base = 'rucksack_' . $user->id . '_' . time();
    $htmlfile = $tempdir . '/' . $base . '.html';
    $pdffile = $tempdir . '/' . $base . '.pdf';

    file_put_contents($htmlfile, $html);

    $logfile = $tempdir . '/' . $base . '.log';
    $homedir = $tempdir . '/' . $base . '_home';
    if (!file_exists($homedir)) {
        mkdir($homedir, 0770, true);
    }
    $cmd = 'HOME=' . escapeshellarg($homedir) . ' ' . escapeshellcmd('libreoffice') . ' --headless --convert-to pdf --outdir ' . escapeshellarg($tempdir) . ' ' . escapeshellarg($htmlfile) . ' > ' . escapeshellarg($logfile) . ' 2>&1';
    $output = [];
    $return = 0;
    exec($cmd, $output, $return);

    $log = file_exists($logfile) ? file_get_contents($logfile) : '';

    unlink($htmlfile);
    if (file_exists($logfile)) {
        unlink($logfile);
    }
    if (file_exists($homedir)) {
        local_rucksack_rrmdir($homedir);
    }

    if ($return !== 0 || !file_exists($pdffile)) {
        $message = 'Rucksack PDF generation failed (exit ' . $return . '): ' . $log;
        debugging($message, DEBUG_DEVELOPER);
        error_log($message);
        return false;
    }

    return $pdffile;
}
