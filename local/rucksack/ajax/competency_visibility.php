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
 * AJAX endpoint: toggle competency visibility for a template set.
 *
 * @package    local_rucksack
 * @copyright  Ueli Leutwyler
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

require_login();
require_capability('local/rucksack:manageconfigs', context_system::instance());

header('Content-Type: application/json; charset=utf-8');

$setid        = required_param('setid', PARAM_INT);
$competencyid = required_param('competencyid', PARAM_INT);
$visible      = required_param('visible', PARAM_INT);

if (!confirm_sesskey()) {
    echo json_encode(['success' => false, 'error' => 'Invalid session key']);
    die();
}

$now = time();

$record = $DB->get_record('local_rucksack_templateset_comp', ['setid' => $setid, 'competencyid' => $competencyid]);
if ($record) {
    $record->visible = $visible;
    $record->timemodified = $now;
    $DB->update_record('local_rucksack_templateset_comp', $record);
} else {
    $record = new stdClass();
    $record->setid = $setid;
    $record->competencyid = $competencyid;
    $record->visible = $visible;
    $record->sortorder = 0;
    $record->timemodified = $now;
    $DB->insert_record('local_rucksack_templateset_comp', $record);
}

echo json_encode(['success' => true]);
die();
