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
 * Returns the configured encryption key.
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
