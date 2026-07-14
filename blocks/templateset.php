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
 * AJAX endpoints for managing rucksack template sets.
 *
 * @package    block_rucksack
 * @copyright  Ueli Leutwyler
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/local/rucksack/lib.php');

require_login();
require_capability('local/rucksack:manageconfigs', context_system::instance());

$action = optional_param('action', '', PARAM_ALPHANUMEXT);
header('Content-Type: application/json; charset=utf-8');

switch ($action) {
    case 'load':
        $id = required_param('id', PARAM_INT);
        $set = local_rucksack_get_templateset($id);
        if (!$set) {
            echo json_encode(['success' => false, 'error' => get_string('setnotfound', 'block_rucksack')]);
            break;
        }
        echo json_encode([
            'success' => true,
            'template' => $set->templatetext,
            'partial' => $set->partialtext,
            'css' => $set->csstext,
            'isstandard' => (int)$set->isstandard,
        ]);
        break;

    case 'save':
        require_sesskey();
        $id = required_param('id', PARAM_INT);
        $template = optional_param('template', '', PARAM_RAW);
        $partial = optional_param('partial', '', PARAM_RAW);
        $css = optional_param('css', '', PARAM_RAW);

        $set = local_rucksack_get_templateset($id);
        if (!$set) {
            echo json_encode(['success' => false, 'error' => get_string('setnotfound', 'block_rucksack')]);
            break;
        }
        if ($set->isstandard) {
            echo json_encode(['success' => false, 'error' => get_string('standardreadonly', 'block_rucksack')]);
            break;
        }
        $success = local_rucksack_save_templateset($id, $set->name, $template, $partial, $css);
        echo json_encode(['success' => $success]);
        break;

    case 'saveas':
        require_sesskey();
        $name = required_param('name', PARAM_TEXT);
        $template = optional_param('template', '', PARAM_RAW);
        $partial = optional_param('partial', '', PARAM_RAW);
        $css = optional_param('css', '', PARAM_RAW);

        if (trim($name) === '') {
            echo json_encode(['success' => false, 'error' => get_string('setnameempty', 'block_rucksack')]);
            break;
        }
        $id = local_rucksack_create_templateset($name, $template, $partial, $css);
        if ($id === false) {
            echo json_encode(['success' => false, 'error' => get_string('seterror', 'block_rucksack')]);
            break;
        }
        echo json_encode(['success' => true, 'id' => $id]);
        break;

    case 'delete':
        require_sesskey();
        $id = required_param('id', PARAM_INT);
        $success = local_rucksack_delete_templateset($id);
        if (!$success) {
            echo json_encode(['success' => false, 'error' => get_string('setdeletefailed', 'block_rucksack')]);
            break;
        }
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => get_string('invalidaction', 'block_rucksack')]);
        break;
}

die();
