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
 * AJAX endpoint: swap sortorder of two competencies within the same parent.
 *
 * @package    local_rucksack
 * @copyright  Ueli Leutwyler
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/rucksack/lib.php');

require_login();
require_capability('local/rucksack:manageconfigs', context_system::instance());

header('Content-Type: application/json; charset=utf-8');

$setid   = required_param('setid', PARAM_INT);
$compid1 = required_param('compid1', PARAM_INT);
$compid2 = required_param('compid2', PARAM_INT);

if (!confirm_sesskey()) {
    echo json_encode(['success' => false, 'error' => 'Invalid session key']);
    die();
}

$now = time();

// Ensure both records exist.
foreach ([$compid1, $compid2] as $cid) {
    if (!$DB->record_exists('local_rucksack_templateset_comp', ['setid' => $setid, 'competencyid' => $cid])) {
        // Insert default row if missing.
        $r = new stdClass();
        $r->setid = $setid;
        $r->competencyid = $cid;
        $r->visible = 1;
        $r->sortorder = 0;
        $r->timemodified = $now;
        $DB->insert_record('local_rucksack_templateset_comp', $r);
    }
}

// Swap sortorder.
$rec1 = $DB->get_record('local_rucksack_templateset_comp', ['setid' => $setid, 'competencyid' => $compid1]);
$rec2 = $DB->get_record('local_rucksack_templateset_comp', ['setid' => $setid, 'competencyid' => $compid2]);

$tmp = $rec1->sortorder;
$rec1->sortorder = $rec2->sortorder;
$rec1->timemodified = $now;
$rec2->sortorder = $tmp;
$rec2->timemodified = $now;

$DB->update_record('local_rucksack_templateset_comp', $rec1);
$DB->update_record('local_rucksack_templateset_comp', $rec2);

echo json_encode(['success' => true]);
die();
