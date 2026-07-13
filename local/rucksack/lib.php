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
 * Library functions for local_rucksack.
 *
 * @package    local_rucksack
 * @copyright  Ueli Leutwyler
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Return the configured logo URL for the public rucksack page.
 *
 * @return moodle_url|false
 */
function local_rucksack_get_logo_url() {
    $fs = get_file_storage();
    $context = context_system::instance();
    $files = $fs->get_area_files($context->id, 'block_rucksack', 'logo', 0, 'sortorder', false);

    foreach ($files as $file) {
        if ($file->is_valid_image()) {
            return moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename()
            );
        }
    }

    return false;
}

/**
 * Return the configured logo as a data URI for PDF embedding.
 *
 * @return string|false
 */
function local_rucksack_get_logo_datauri() {
    $url = local_rucksack_get_logo_url();
    if (!$url) {
        return false;
    }
    return local_rucksack_image_to_datauri($url->out());
}

/**
 * Return the custom template content configured in the block.
 *
 * @return string|false
 */
function local_rucksack_get_custom_template() {
    $fs = get_file_storage();
    $context = context_system::instance();
    $files = $fs->get_area_files($context->id, 'block_rucksack', 'template', 0, 'sortorder', false);

    foreach ($files as $file) {
        if (!$file->is_directory()) {
            return $file->get_content();
        }
    }

    return false;
}

/**
 * Return the custom badge_row partial content configured in the block.
 *
 * @return string|false
 */
function local_rucksack_get_custom_badge_row_partial() {
    $fs = get_file_storage();
    $context = context_system::instance();
    $files = $fs->get_area_files($context->id, 'block_rucksack', 'template_partial', 0, 'sortorder', false);

    foreach ($files as $file) {
        if (!$file->is_directory()) {
            return $file->get_content();
        }
    }

    return false;
}

/**
 * Return the custom CSS content configured in the block.
 *
 * @return string|false
 */
function local_rucksack_get_custom_css() {
    $fs = get_file_storage();
    $context = context_system::instance();
    $files = $fs->get_area_files($context->id, 'block_rucksack', 'customcss', 0, 'sortorder', false);

    foreach ($files as $file) {
        if (!$file->is_directory()) {
            return $file->get_content();
        }
    }

    return false;
}

/**
 * Return the custom CSS URL for the public rucksack page.
 *
 * @return moodle_url|false
 */
function local_rucksack_get_custom_css_url() {
    $fs = get_file_storage();
    $context = context_system::instance();
    $files = $fs->get_area_files($context->id, 'block_rucksack', 'customcss', 0, 'sortorder', false);

    foreach ($files as $file) {
        if (!$file->is_directory()) {
            return moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename()
            );
        }
    }

    return false;
}

/**
 * Return the custom page title configured in the rucksack block.
 *
 * Falls back to the default language string 'badgesfor' if no title is set.
 *
 * @return string
 */
function local_rucksack_get_title() {
    global $DB;
    $records = $DB->get_records('block_instances', ['blockname' => 'rucksack'], 'id');
    foreach ($records as $record) {
        $config = unserialize(base64_decode($record->configdata));
        if (!empty($config->title)) {
            return $config->title;
        }
    }
    return get_string('badgesfor', 'local_rucksack');
}

/**
 * Return the configured encryption key.
 *
 * @return string
 */
function local_rucksack_get_key() {
    return get_config('local_rucksack', 'encryptionkey') ?: 'z8YBNf3T8zWGYXpVHW4Npwaz3rECZCR4';
}

/**
 * Encrypt a user id for use in public URLs.
 *
 * @param string|int $plaintext
 * @return string
 */
function local_rucksack_encrypt($plaintext) {
    $key = local_rucksack_get_key();
    $method = 'aes-256-cbc';
    $bkey = hex2bin($key);
    $iv = hex2bin(md5(microtime() . rand()));
    $data = openssl_encrypt($plaintext, $method, $bkey, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $data);
}

/**
 * Decrypt a user id from a public URL.
 *
 * @param string $encryptedtext
 * @return string|false
 */
function local_rucksack_decrypt($encryptedtext) {
    $key = local_rucksack_get_key();
    $method = 'aes-256-cbc';
    $bkey = hex2bin($key);
    $decoded = base64_decode(str_replace(' ', '+', $encryptedtext));
    $iv = substr($decoded, 0, 16);
    $data = substr($decoded, 16);
    return openssl_decrypt($data, $method, $bkey, OPENSSL_RAW_DATA, $iv);
}

/**
 * Check whether the current user is allowed to manage rucksack configs / view other users.
 *
 * @return bool
 */
function local_rucksack_can_manage() {
    return has_capability('local/rucksack:manageconfigs', context_system::instance());
}

/**
 * Get the configuration set assigned to a user.
 *
 * @param int $userid
 * @return stdClass|null
 */
function local_rucksack_get_user_config($userid) {
    global $DB;
    $sql = "SELECT c.*
              FROM {local_rucksack_config} c
              JOIN {local_rucksack_user_config} uc ON uc.configid = c.id
             WHERE uc.userid = ?
          ORDER BY uc.timemodified DESC
             LIMIT 1";
    return $DB->get_record_sql($sql, [$userid]);
}

/**
 * Get badge configuration for a set.
 *
 * @param int $configid
 * @return array keyed by badgeid
 */
function local_rucksack_get_config_badges($configid) {
    global $DB;
    $records = $DB->get_records('local_rucksack_config_badge', ['configid' => $configid]);
    $result = [];
    foreach ($records as $record) {
        $result[$record->badgeid] = $record;
    }
    return $result;
}

/**
 * Get competency configuration for a set.
 *
 * @param int $configid
 * @return array keyed by competencyid
 */
function local_rucksack_get_config_competencies($configid) {
    global $DB;
    $records = $DB->get_records('local_rucksack_config_comp', ['configid' => $configid]);
    $result = [];
    foreach ($records as $record) {
        $result[$record->competencyid] = $record;
    }
    return $result;
}

/**
 * Format a plain-text badge description for output.
 *
 * - Leading "-" on a line is turned into a list item.
 * - Line breaks are rendered as <br>.
 * - HTML entities are escaped.
 *
 * @param string $text
 * @return string
 */
function local_rucksack_format_description($text) {
    if (empty($text)) {
        return '';
    }

    // Normalize line endings.
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $lines = explode("\n", $text);

    $output = [];
    $inlist = false;

    foreach ($lines as $line) {
        $trimmed = ltrim($line);
        if (strpos($trimmed, '-') === 0) {
            $item = trim(substr($trimmed, 1));
            if (!$inlist) {
                $output[] = '<ul>';
                $inlist = true;
            }
            $output[] = '<li>' . s($item) . '</li>';
        } else {
            if ($inlist) {
                $output[] = '</ul>';
                $inlist = false;
            }
            if ($trimmed !== '') {
                $output[] = s($trimmed) . '<br>';
            } else {
                $output[] = '<br>';
            }
        }
    }

    if ($inlist) {
        $output[] = '</ul>';
    }

    return implode("\n", $output);
}

/**
 * Build a standalone HTML document with embedded CSS and images for PDF printing.
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

    $customcss = local_rucksack_get_custom_css();
    if ($customcss !== false) {
        $css .= "\n\n/* Custom CSS */\n" . $customcss;
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

    // Match the theme's default sans-serif font stack (Bootstrap 5) in the PDF.
    $fontfamily = 'system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", "Noto Sans", "Liberation Sans", Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji"';

    return '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>' . s($username) . '</title>
    <style>
        body { font-family: ' . $fontfamily . '; }
        ' . $css . '
        @page { size: A4; margin: 0.5cm; }
        .local-rucksack-actions { display: none !important; }
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
 * Generate a PDF file from HTML using headless Chrome.
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

    $chrome = local_rucksack_find_chrome();
    if (!$chrome) {
        debugging('Rucksack PDF generation failed: no Chrome/Chromium binary found', DEBUG_DEVELOPER);
        error_log('Rucksack PDF generation failed: no Chrome/Chromium binary found');
        unlink($htmlfile);
        local_rucksack_rrmdir($homedir);
        return false;
    }

    $cmd = 'HOME=' . escapeshellarg($homedir)
        . ' ' . escapeshellcmd($chrome)
        . ' --headless --disable-gpu --no-sandbox --disable-dev-shm-usage'
        . ' --disable-crashpad --disable-crash-reporter'
        . ' --run-all-compositor-stages-before-draw --print-to-pdf-no-header'
        . ' --print-to-pdf=' . escapeshellarg($pdffile)
        . ' ' . escapeshellarg($htmlfile)
        . ' > ' . escapeshellarg($logfile) . ' 2>&1';
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

/**
 * Find a Chrome/Chromium binary on the system.
 *
 * @return string|false
 */
function local_rucksack_find_chrome() {
    $candidates = [
        'google-chrome-stable',
        'google-chrome',
        'chromium-browser',
        'chromium',
    ];
    foreach ($candidates as $candidate) {
        $path = trim(shell_exec('which ' . escapeshellarg($candidate) . ' 2>/dev/null'));
        if (!empty($path)) {
            return $path;
        }
    }
    return false;
}

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
