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
 * Newblock block caps.
 *
 * @package    block_rucksack
 * @copyright  Ueli Leutwyler <ueli@modularity.ch>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$settings->add(new admin_setting_heading('sampleheader',
                                         get_string('headerconfig', 'block_rucksack'),
                                         get_string('descconfig', 'block_rucksack')));

$settings->add(new admin_setting_configcheckbox('rucksack/foo',
                                                 get_string('labelfoo', 'block_rucksack'),
                                                 get_string('descfoo', 'block_rucksack'),
                                                 '0'));

$settings->add(new admin_setting_configstoredfile('block_rucksack/logo',
                                                  get_string('logo', 'block_rucksack'),
                                                  get_string('logo_desc', 'block_rucksack'),
                                                  'logo',
                                                  0,
                                                  ['accepted_types' => ['.png', '.jpg', '.jpeg', '.gif', '.svg']]));
