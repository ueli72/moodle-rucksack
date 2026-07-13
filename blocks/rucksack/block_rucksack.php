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
 * Rucksack block.
 *
 * @package    block_rucksack
 * @copyright  Ueli Leutwyler
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->libdir . '/badgeslib.php');
require_once($CFG->dirroot . '/local/rucksack/lib.php');

use chillerlan\QRCode\{QRCode, QROptions};
use chillerlan\QRCode\Output\QROutputInterface;
include($CFG->libdir . '/../blocks/rucksack/vendor/autoload.php');

defined('MOODLE_INTERNAL') || die();

class block_rucksack extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_rucksack');
    }

    public function get_content() {
        global $CFG, $OUTPUT, $USER, $PAGE;

        if ($this->content !== null) {
            return $this->content;
        }

        if (empty($this->instance)) {
            $this->content = '';
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->items = [];
        $this->content->icons = [];
        $this->content->footer = '';

        $userhash = local_rucksack_encrypt($USER->id);
        $viewurl = $CFG->wwwroot . '/local/rucksack/view.php?user=' . urlencode($userhash);
        $pdfurl = $CFG->wwwroot . '/local/rucksack/pdf.php?user=' . urlencode($userhash);

        $this->content->text = html_writer::tag('p', get_string('publicaddress', 'block_rucksack'));

        $this->content->text .= html_writer::start_tag('div', ['class' => 'local-rucksack-block']);

        // Copy / open buttons.
        $this->content->text .= html_writer::start_tag('div', ['style' => 'float:left; margin-right:1rem;']);
        $this->content->text .= html_writer::empty_tag('input', [
            'type' => 'text',
            'value' => $viewurl,
            'id' => 'qrcodeurl',
            'class' => 'form-control',
            'style' => 'margin-bottom:0.5rem;',
        ]);
        $this->content->text .= html_writer::tag('button', get_string('open', 'block_rucksack'), [
            'onclick' => "window.open('" . $viewurl . "','_blank')",
            'class' => 'btn btn-secondary',
            'type' => 'button',
        ]);
        $this->content->text .= ' ';
        $this->content->text .= html_writer::tag('button', get_string('copytoclipboard', 'block_rucksack'), [
            'onclick' => 'copyQRCode()',
            'class' => 'btn btn-secondary',
            'type' => 'button',
        ]);
        $this->content->text .= ' ';
        $this->content->text .= html_writer::tag('a', get_string('downloadpdf', 'block_rucksack'), [
            'href' => $pdfurl,
            'target' => '_blank',
            'class' => 'btn btn-secondary',
        ]);
        $this->content->text .= html_writer::end_tag('div');

        // QR code.
        $options = new QROptions;
        $options->outputType = QROutputInterface::GDIMAGE_PNG;
        $this->content->text .= html_writer::empty_tag('img', [
            'src' => (new QRCode($options))->render($viewurl),
            'width' => '150',
            'height' => '150',
            'alt' => get_string('qrcode', 'block_rucksack'),
        ]);
        $this->content->text .= html_writer::end_tag('div');
        $this->content->text .= html_writer::tag('div', '', ['style' => 'clear:both;']);

        // Copy script.
        $this->content->text .= html_writer::script('function copyQRCode() {
            var copyText = document.getElementById("qrcodeurl");
            copyText.select();
            copyText.setSelectionRange(0, 99999);
            document.execCommand("copy");
            alert("' . get_string('copied', 'block_rucksack') . '");
        }');

        return $this->content;
    }

    public function applicable_formats() {
        return [
            'all' => true,
            'site' => true,
            'site-index' => true,
            'course-view' => true,
            'course-view-social' => false,
            'mod' => true,
            'mod-quiz' => false,
            'my' => true,
        ];
    }

    public function instance_allow_multiple() {
        return true;
    }

    public function has_config() {
        return true;
    }

    public function instance_config_save($data, $nolongerused = false) {
        if (!empty($data->logo)) {
            file_save_draft_area_files(
                $data->logo,
                context_system::instance()->id,
                'block_rucksack',
                'logo',
                0
            );
        }
        if (!empty($data->template)) {
            file_save_draft_area_files(
                $data->template,
                context_system::instance()->id,
                'block_rucksack',
                'template',
                0
            );
        }
        if (!empty($data->template_badge_row)) {
            file_save_draft_area_files(
                $data->template_badge_row,
                context_system::instance()->id,
                'block_rucksack',
                'template_partial',
                0
            );
        }
        return parent::instance_config_save($data, $nolongerused);
    }

    public function cron() {
        mtrace('Hey, my cron script is running');
        return true;
    }
}
