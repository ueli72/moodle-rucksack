<?php

require_once($GLOBALS['CFG']->dirroot . '/local/rucksack/lib.php');

class block_rucksack_edit_form extends block_edit_form {

    /**
     * Return the content of a default file or an empty string if it does not exist.
     *
     * @param string $path
     * @return string
     */
    protected function get_default_file_content($path) {
        if (file_exists($path)) {
            return file_get_contents($path);
        }
        return '';
    }

    /**
     * Build a textarea + reset button pair for editing a template/CSS file.
     *
     * The reset button is placed directly below the title (header). The default
     * content is embedded base64-encoded in the button so the reset works even
     * inside the AJAX-loaded dynamic form.
     *
     * @param \MoodleQuickForm $mform
     * @param string $fieldname
     * @param string $label
     * @param string $helpstring
     * @param string $currentcontent
     * @param string $defaultcontent
     * @param int $rows
     */
    protected function add_template_textarea($mform, $fieldname, $label, $helpstring, $currentcontent, $defaultcontent, $rows = 20) {
        // Title as header, followed by the reset button below it.
        $mform->addElement('header', $fieldname . '_header', $label);

        $defaultb64 = base64_encode($defaultcontent);
        $onclick = 'var t=document.querySelector(' . json_encode('textarea[name="' . $fieldname . '"]:not([type="hidden"])') . ');'
            . 'if(t){t.value=decodeURIComponent(escape(atob(this.getAttribute(\'data-default\'))));}'
            . 'else{console.error(\'Rucksack reset: textarea not found\');}';
        $resetbutton = html_writer::tag(
            'button',
            get_string('resetdefault', 'block_rucksack'),
            [
                'type' => 'button',
                'class' => 'btn btn-secondary',
                'onclick' => $onclick,
                'data-default' => $defaultb64,
            ]
        );
        $mform->addElement('static', $fieldname . '_reset', '', $resetbutton);

        // Textarea without a label (the header serves as the title).
        $mform->addElement('textarea', $fieldname, '', [
            'rows' => $rows,
            'cols' => 80,
            'style' => 'width: 100%; font-family: monospace;',
        ]);
        $mform->setType($fieldname, PARAM_RAW);
        $mform->setDefault($fieldname, $currentcontent);
        $mform->addHelpButton($fieldname, $helpstring, 'block_rucksack');
    }

    /**
     * Prepare block defaults for the form.
     *
     * Override to prevent legacy file-upload draft IDs (stored under the old
     * config keys template, template_badge_row and customcss) from overriding
     * the textarea defaults with meaningless numbers.
     *
     * @param stdClass $defaults
     * @return stdClass
     */
    protected function prepare_defaults(stdClass $defaults): stdClass {
        foreach (['template', 'template_badge_row', 'customcss'] as $field) {
            if (isset($this->block->config->$field)) {
                unset($this->block->config->$field);
            }
        }
        return parent::prepare_defaults($defaults);
    }

    protected function specific_definition($mform) {
        global $CFG;

        // Section header title according to language file.
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        // Page title text.
        $mform->addElement('text', 'config_title', get_string('title', 'block_rucksack'), ['size' => 40]);
        $mform->setType('config_title', PARAM_TEXT);
        $mform->setDefault('config_title', get_string('badgesfor', 'local_rucksack'));
        $mform->addHelpButton('config_title', 'title', 'block_rucksack');

        // Logo file upload.
        $mform->addElement('filemanager', 'config_logo', get_string('logo', 'block_rucksack'), null, [
            'subdirs' => 0,
            'maxbytes' => 2 * 1024 * 1024,
            'areamaxbytes' => 2 * 1024 * 1024,
            'maxfiles' => 1,
            'accepted_types' => ['.png', '.jpg', '.jpeg', '.gif', '.svg'],
        ]);
        $mform->addHelpButton('config_logo', 'logo', 'block_rucksack');

        // Main template textarea.
        $defaultmain = $this->get_default_file_content($CFG->dirroot . '/local/rucksack/templates/earned_badges.mustache');
        $currentmain = local_rucksack_get_custom_template();
        $this->add_template_textarea(
            $mform,
            'config_template',
            get_string('template', 'block_rucksack'),
            'template',
            $currentmain !== false ? $currentmain : $defaultmain,
            $defaultmain,
            25
        );

        // Badge row partial textarea.
        $defaultpartial = $this->get_default_file_content($CFG->dirroot . '/local/rucksack/templates/badge_row.mustache');
        $currentpartial = local_rucksack_get_custom_badge_row_partial();
        $this->add_template_textarea(
            $mform,
            'config_template_badge_row',
            get_string('template_badge_row', 'block_rucksack'),
            'template_badge_row',
            $currentpartial !== false ? $currentpartial : $defaultpartial,
            $defaultpartial,
            15
        );

        // Custom CSS textarea.
        $defaultcss = $this->get_default_file_content($CFG->dirroot . '/local/rucksack/styles.css');
        $currentcss = local_rucksack_get_custom_css();
        $this->add_template_textarea(
            $mform,
            'config_customcss',
            get_string('customcss', 'block_rucksack'),
            'customcss',
            $currentcss !== false ? $currentcss : $defaultcss,
            $defaultcss,
            20
        );

        // Prepare draft area for the logo file.
        $context = context_system::instance();
        $draftitemid = file_get_unused_draft_itemid();
        file_prepare_draft_area($draftitemid, $context->id, 'block_rucksack', 'logo', 0);
        $mform->setDefault('config_logo', $draftitemid);
    }
}
