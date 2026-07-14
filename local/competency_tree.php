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
 * AJAX endpoint: load competency tree (level-wise, lazy).
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

$setid    = required_param('setid', PARAM_INT);
$parentid = optional_param('parentid', 0, PARAM_INT);

if (!confirm_sesskey()) {
    echo json_encode(['success' => false, 'error' => 'Invalid session key']);
    die();
}

$nodes = [];

if ($parentid == 0) {
    // Load frameworks (root level).
    $frameworks = $DB->get_records('competency_framework', [], 'shortname', 'id, shortname');
    foreach ($frameworks as $fw) {
        $sql = "SELECT c.id, c.shortname, c.parentid,
                       COALESCE(tsc.sortorder, c.sortorder) AS sortorder,
                       COALESCE(tsc.visible, 1) AS visible
                  FROM {competency} c
                  LEFT JOIN {local_rucksack_templateset_comp} tsc
                    ON tsc.competencyid = c.id AND tsc.setid = :setid
                 WHERE c.competencyframeworkid = :fwid AND c.parentid = 0
              ORDER BY sortorder, c.shortname";
        $comp = $DB->get_records_sql($sql, ['setid' => $setid, 'fwid' => $fw->id]);
        foreach ($comp as $c) {
            $nodes[] = [
                'id'          => $c->id,
                'name'        => $c->shortname,
                'haschildren' => (int)$DB->record_exists('competency', ['parentid' => $c->id]),
                'visible'     => (int)$c->visible,
                'sortorder'   => (int)$c->sortorder,
            ];
        }
    }
} else {
    // Load children of a specific competency.
    $sql = "SELECT c.id, c.shortname, c.parentid,
                   COALESCE(tsc.sortorder, c.sortorder) AS sortorder,
                   COALESCE(tsc.visible, 1) AS visible
              FROM {competency} c
              LEFT JOIN {local_rucksack_templateset_comp} tsc
                ON tsc.competencyid = c.id AND tsc.setid = :setid
             WHERE c.parentid = :parentid
          ORDER BY sortorder, c.shortname";
    $children = $DB->get_records_sql($sql, ['setid' => $setid, 'parentid' => $parentid]);
    foreach ($children as $c) {
        $nodes[] = [
            'id'          => $c->id,
            'name'        => $c->shortname,
            'haschildren' => (int)$DB->record_exists('competency', ['parentid' => $c->id]),
            'visible'     => (int)$c->visible,
            'sortorder'   => (int)$c->sortorder,
        ];
    }
}

echo json_encode(['success' => true, 'nodes' => $nodes]);
die();
