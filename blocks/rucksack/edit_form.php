<?php

class block_rucksack_edit_form extends block_edit_form {

    protected function specific_definition($mform) {
        global $CFG;

        // Section header title according to language file.
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        // Logo file upload.
        $mform->addElement('filemanager', 'config_logo', get_string('logo', 'block_rucksack'), null, [
            'subdirs' => 0,
            'maxbytes' => 2 * 1024 * 1024,
            'areamaxbytes' => 2 * 1024 * 1024,
            'maxfiles' => 1,
            'accepted_types' => ['.png', '.jpg', '.jpeg', '.gif', '.svg'],
        ]);
        $mform->addHelpButton('config_logo', 'logo', 'block_rucksack');

        // Template file upload.
        $mform->addElement('filemanager', 'config_template', get_string('template', 'block_rucksack'), null, [
            'subdirs' => 0,
            'maxbytes' => 1 * 1024 * 1024,
            'areamaxbytes' => 1 * 1024 * 1024,
            'maxfiles' => 1,
            'accepted_types' => ['.mustache', '.html', '.txt'],
        ]);
        $mform->addHelpButton('config_template', 'template', 'block_rucksack');

        // Badge row partial upload.
        $mform->addElement('filemanager', 'config_template_badge_row', get_string('template_badge_row', 'block_rucksack'), null, [
            'subdirs' => 0,
            'maxbytes' => 1 * 1024 * 1024,
            'areamaxbytes' => 1 * 1024 * 1024,
            'maxfiles' => 1,
            'accepted_types' => ['.mustache', '.html', '.txt'],
        ]);
        $mform->addHelpButton('config_template_badge_row', 'template_badge_row', 'block_rucksack');

        $mainurl = $CFG->wwwroot . '/blocks/rucksack/download_default_template.php?type=main';
        $partialurl = $CFG->wwwroot . '/blocks/rucksack/download_default_template.php?type=badge_row';
        $downloadlinks = html_writer::tag('strong', get_string('downloaddefaulttemplates', 'block_rucksack')) . ' ' .
            html_writer::link($mainurl, 'earned_badges.mustache') . ' | ' .
            html_writer::link($partialurl, 'badge_row.mustache');
        $mform->addElement('static', 'template_downloads', '', $downloadlinks);

        // Prepare draft area with existing logo and template files.
        $context = context_system::instance();
        $draftitemid = file_get_unused_draft_itemid();
        file_prepare_draft_area($draftitemid, $context->id, 'block_rucksack', 'logo', 0);
        $mform->setDefault('config_logo', $draftitemid);

        $draftitemidtemplate = file_get_unused_draft_itemid();
        file_prepare_draft_area($draftitemidtemplate, $context->id, 'block_rucksack', 'template', 0);
        $mform->setDefault('config_template', $draftitemidtemplate);

        $draftitemidpartial = file_get_unused_draft_itemid();
        file_prepare_draft_area($draftitemidpartial, $context->id, 'block_rucksack', 'template_partial', 0);
        $mform->setDefault('config_template_badge_row', $draftitemidpartial);
    }
}
