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
 * AJAX endpoint for generating an API token for a block instance.
 *
 * @package    block_rucksack
 * @copyright  Ueli Leutwyler
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

require_login();
require_capability('local/rucksack:manageconfigs', context_system::instance());

$action = optional_param('action', '', PARAM_ALPHANUMEXT);
header('Content-Type: application/json; charset=utf-8');

if ($action !== 'generate') {
    echo json_encode(['success' => false, 'error' => get_string('invalidaction', 'block_rucksack')]);
    die();
}

$instanceid = required_param('instanceid', PARAM_INT);

if (!confirm_sesskey()) {
    echo json_encode(['success' => false, 'error' => 'Invalid session key']);
    die();
}

// Load block instance.
$blockinstance = $DB->get_record('block_instances', ['id' => $instanceid, 'blockname' => 'rucksack']);
if (!$blockinstance) {
    echo json_encode(['success' => false, 'error' => 'Block instance not found']);
    die();
}

// Generate a new random hash.
$hash = random_string(32);
$token = $instanceid . '_' . $hash;

// Update block config.
$config = unserialize(base64_decode($blockinstance->configdata));
if (!is_object($config)) {
    $config = new stdClass();
}
$config->apitoken = $hash;
$blockinstance->configdata = base64_encode(serialize($config));
$blockinstance->timemodified = time();
$DB->update_record('block_instances', $blockinstance);

echo json_encode(['success' => true, 'token' => $token]);
die();
