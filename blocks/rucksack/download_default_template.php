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
 * Download the default rucksack template, its badge_row partial or the default CSS.
 *
 * @package    block_rucksack
 * @copyright  Ueli Leutwyler
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

require_login();

$type = optional_param('type', 'main', PARAM_ALPHANUMEXT);

$files = [
    'main' => $CFG->dirroot . '/local/rucksack/templates/earned_badges.mustache',
    'badge_row' => $CFG->dirroot . '/local/rucksack/templates/badge_row.mustache',
    'css' => $CFG->dirroot . '/local/rucksack/styles.css',
];

$path = $files[$type] ?? $files['main'];
$filename = basename($path);

if (!file_exists($path)) {
    throw new moodle_exception('filenotfound');
}

header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
