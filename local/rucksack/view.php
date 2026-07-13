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
 * Public view page for a user's earned badges.
 *
 * @package    local_rucksack
 * @copyright  Ueli Leutwyler
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

require_once($CFG->libdir . '/badgeslib.php');
require_once($CFG->dirroot . '/local/rucksack/lib.php');

global $CFG, $PAGE, $OUTPUT, $USER, $DB;

$context = context_system::instance();

// Public access via encrypted user hash.
$userhash = optional_param('user', '', PARAM_TEXT);

// Trainer-only access via direct user id.
$directid = optional_param('id', 0, PARAM_INT);

$userid = 0;

if (!empty($userhash)) {
    $userid = (int)local_rucksack_decrypt($userhash);
} elseif ($directid > 0 && local_rucksack_can_manage()) {
    $userid = $directid;
} else {
    $userid = (int)$USER->id;
}

if ($userid <= 0) {
    throw new moodle_exception('invaliduser', 'local_rucksack');
}

$targetuser = $DB->get_record('user', ['id' => $userid]);
if (!$targetuser) {
    throw new moodle_exception('invaliduser', 'local_rucksack');
}

$canmanage = local_rucksack_can_manage();

$PAGE->set_pagelayout('standard');
$PAGE->set_context($context);
$PAGE->set_url('/local/rucksack/view.php', ['user' => $userhash]);
$PAGE->set_heading(get_string('pluginname', 'local_rucksack'));
$PAGE->set_title(get_string('badgesfor', 'local_rucksack', fullname($targetuser)));
$PAGE->requires->css('/local/rucksack/styles.css');
$customcssurl = local_rucksack_get_custom_css_url();
if ($customcssurl) {
    $PAGE->requires->css($customcssurl);
}

$renderer = $PAGE->get_renderer('local_rucksack');
$renderable = new \local_rucksack\output\earned_badges($userid);
$data = $renderable->export_for_template($renderer);

// Trainer user selector.
if ($canmanage) {
    $data->canmanage = true;
    $data->currentuser = $userid;
        $data->users = [];
        $sql = "SELECT id, firstname, lastname
                  FROM {user}
                 WHERE deleted = 0 AND suspended = 0 AND id > 1
                   AND TRIM(firstname) <> ''
              ORDER BY TRIM(firstname), TRIM(lastname)";
        $users = $DB->get_records_sql($sql);
        foreach ($users as $u) {
            $data->users[] = [
                'id' => $u->id,
                'name' => fullname($u),
                'selected' => $u->id == $userid,
            ];
        }
}

$content = $renderer->render_earned_badges_data($data);

// Build action buttons (PDF download + browser print) for the screen view.
$actionshtml = '<div class="local-rucksack-actions">';
$actionshtml .= '<a href="' . $data->pdfurl . '" class="btn btn-secondary" target="_blank">' . get_string('downloadpdf', 'local_rucksack') . '</a>';
$actionshtml .= '<button type="button" class="btn btn-secondary" onclick="window.print()">' . get_string('print', 'local_rucksack') . '</button>';
$actionshtml .= '</div>';

// Top bar: user selector (trainer only) and action buttons on one row above the title.
$topbarhtml = '<div class="local-rucksack-top-bar">';
if ($canmanage) {
    $selectorhtml = '<form method="get" action="' . $CFG->wwwroot . '/local/rucksack/view.php" class="local-rucksack-user-selector form-inline">';
    $selectorhtml .= '<div class="form-group">';
    $selectorhtml .= '<label for="rucksack-user-select">' . get_string('selectuser', 'local_rucksack') . '</label>';
    $selectorhtml .= '<select id="rucksack-user-select" name="id" class="form-control">';
    foreach ($data->users as $u) {
        $selected = $u['selected'] ? ' selected' : '';
        $selectorhtml .= '<option value="' . $u['id'] . '"' . $selected . '>' . s($u['name']) . '</option>';
    }
    $selectorhtml .= '</select>';
    $selectorhtml .= '</div>';
    $selectorhtml .= '<button type="submit" class="btn btn-primary">' . get_string('show') . '</button>';
    $selectorhtml .= '</form>';
    $topbarhtml .= $selectorhtml;
}
$topbarhtml .= $actionshtml;
$topbarhtml .= '</div>';

$content = $topbarhtml . $content;

echo $OUTPUT->header();
echo $content;
echo $OUTPUT->footer();
