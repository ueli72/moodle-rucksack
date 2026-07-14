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

// Get parentid from one of the competencies (they are siblings).
$comp1 = $DB->get_record('competency', ['id' => $compid1], 'id, parentid');
if (!$comp1) {
    echo json_encode(['success' => false, 'error' => 'Competency not found']);
    die();
}
$parentid = $comp1->parentid;

// Load all siblings with their current sortorder (core or override).
$sql = "SELECT c.id,
               COALESCE(tsc.sortorder, c.sortorder) AS sortorder,
               COALESCE(tsc.visible, 1) AS visible,
               tsc.id AS overrideid
          FROM {competency} c
          LEFT JOIN {local_rucksack_templateset_comp} tsc
            ON tsc.competencyid = c.id AND tsc.setid = :setid
         WHERE c.parentid = :parentid
      ORDER BY sortorder, c.shortname";
$siblings = $DB->get_records_sql($sql, ['setid' => $setid, 'parentid' => $parentid]);

// Ensure both target competencies exist in the list.
if (!isset($siblings[$compid1]) || !isset($siblings[$compid2])) {
    echo json_encode(['success' => false, 'error' => 'Competencies are not siblings']);
    die();
}

// If all sortorders are identical (e.g. all 0), renumber all siblings uniquely.
$sortorders = array_column($siblings, 'sortorder');
$unique = array_unique($sortorders);
if (count($unique) === 1) {
    $idx = 0;
    foreach ($siblings as $sid => $s) {
        $siblings[$sid]->sortorder = $idx++;
    }
}

// Swap sortorders of the two target competencies.
$tmp = $siblings[$compid1]->sortorder;
$siblings[$compid1]->sortorder = $siblings[$compid2]->sortorder;
$siblings[$compid2]->sortorder = $tmp;

// Save all siblings that now have a different sortorder.
foreach ($siblings as $sid => $s) {
    if ($s->overrideid) {
        $DB->set_field('local_rucksack_templateset_comp', 'sortorder', $s->sortorder, ['id' => $s->overrideid]);
        $DB->set_field('local_rucksack_templateset_comp', 'timemodified', $now, ['id' => $s->overrideid]);
    } else {
        $r = new stdClass();
        $r->setid = $setid;
        $r->competencyid = $sid;
        $r->visible = $s->visible;
        $r->sortorder = $s->sortorder;
        $r->timemodified = $now;
        $DB->insert_record('local_rucksack_templateset_comp', $r);
    }
}

echo json_encode(['success' => true]);
die();
