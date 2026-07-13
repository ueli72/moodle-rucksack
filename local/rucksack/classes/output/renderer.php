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

defined('MOODLE_INTERNAL') || die;

require_once($GLOBALS['CFG']->dirroot . '/local/rucksack/lib.php');

use plugin_renderer_base;

/**
 * Renderer for local_rucksack.
 *
 * @package    local_rucksack
 * @copyright  Ueli Leutwyler
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends plugin_renderer_base {

    /**
     * Render the earned badges page.
     *
     * @param earned_badges $badges
     * @return string
     */
    public function render_earned_badges(earned_badges $badges) {
        return $this->render_earned_badges_data($badges->export_for_template($this));
    }

    /**
     * Render the earned badges page from a pre-exported data object.
     *
     * Used by both the screen view and the PDF export so that a custom template
     * configured in the block is honoured everywhere.
     *
     * @param stdClass $data
     * @return string
     */
    public function render_earned_badges_data($data) {
        $template = local_rucksack_get_custom_template();
        if ($template) {
            return $this->render_from_string($template, $data);
        }
        return $this->render_from_template('local_rucksack/earned_badges', $data);
    }

    /**
     * Render output from a Mustache template string while keeping the standard
     * helpers (str, pix, ...) and partials loader.
     *
     * @param string $templatestring
     * @param stdClass $data
     * @return string
     */
    protected function render_from_string($templatestring, $data) {
        $mustache = $this->get_mustache();
        $defaultloader = $mustache->getLoader();
        $mustache->setLoader(new \Mustache\Loader\ArrayLoader(['__custom_template__' => $templatestring]));
        $mustache->setPartialsLoader(new mustache_partial_loader($defaultloader));
        return $mustache->render('__custom_template__', $data);
    }
}
