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

require_once(__DIR__ . '/../../../config.php');

require_login();
require_capability('local/rucksack:manageconfigs', context_system::instance());

header('Content-Type: application/json; charset=utf-8');

$setid    = required_param('setid', PARAM_INT);
$parentid = optional_param('parentid', 0, PARAM_INT);

if (!confirm_sesskey()) {
    echo json_encode(['success' => false, 'error' => 'Invalid session key']);
    die();
}

// Load visibility + sort overrides for this set.
$overrides = local_rucksack_get_templateset_comp_overrides($setid);

$nodes = [];

if ($parentid == 0) {
    // Load frameworks (root level).
    $frameworks = $DB->get_records('competency_framework', [], 'shortname', 'id, shortname');
    foreach ($frameworks as $fw) {
        $comp = $DB->get_records('competency', ['competencyframeworkid' => $fw->id, 'parentid' => 0], 'sortorder, shortname', 'id, shortname, parentid');
        foreach ($comp as $c) {
            $ov = $overrides[$c->id] ?? null;
            $nodes[] = [
                'id'          => $c->id,
                'name'        => $c->shortname,
                'haschildren' => (int)$DB->record_exists('competency', ['parentid' => $c->id]),
                'visible'     => $ov ? (int)$ov->visible : 1,
                'sortorder'   => $ov ? (int)$ov->sortorder : 0,
            ];
        }
    }
} else {
    // Load children of a specific competency.
    $children = $DB->get_records('competency', ['parentid' => $parentid], 'sortorder, shortname', 'id, shortname, parentid');
    foreach ($children as $c) {
        $ov = $overrides[$c->id] ?? null;
        $nodes[] = [
            'id'          => $c->id,
            'name'        => $c->shortname,
            'haschildren' => (int)$DB->record_exists('competency', ['parentid' => $c->id]),
            'visible'     => $ov ? (int)$ov->visible : 1,
            'sortorder'   => $ov ? (int)$ov->sortorder : 0,
        ];
    }
}

echo json_encode(['success' => true, 'nodes' => $nodes]);
die();
