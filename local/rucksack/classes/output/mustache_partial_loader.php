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

namespace local_rucksack\output;

/**
 * Mustache partial loader that allows overriding the badge_row partial
 * with a custom version uploaded in the block configuration.
 *
 * @package    local_rucksack
 * @copyright  Ueli Leutwyler
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mustache_partial_loader implements \Mustache\Loader {

    /** @var \Mustache_Loader */
    protected $defaultloader;

    /** @var int */
    protected $setid;

    /**
     * Constructor.
     *
     * @param \Mustache\Loader $defaultloader
     * @param int $setid
     */
    public function __construct($defaultloader, $setid = 0) {
        $this->defaultloader = $defaultloader;
        $this->setid = (int)$setid;
    }

    /**
     * Load a partial template.
     *
     * @param string $name
     * @return string
     */
    public function load($name) {
        if ($name === 'local_rucksack/badge_row') {
            $partial = local_rucksack_get_partial($this->setid);
            if ($partial !== false && trim($partial) !== '') {
                return $partial;
            }
        }
        return $this->defaultloader->load($name);
    }
}
