<?php

require_once($GLOBALS['CFG']->dirroot . '/local/rucksack/lib.php');

class block_rucksack_edit_form extends block_edit_form {

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
        global $CFG, $PAGE;

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

        // Template set selection.
        $mform->addElement('header', 'templatesetheader', get_string('templatesets', 'block_rucksack'));

        // Determine the currently selected set early so button states can use it.
        $selectedsetid = !empty($this->block->config->templateset) ? (int)$this->block->config->templateset : 0;
        $selectedset = local_rucksack_get_templateset($selectedsetid);
        $standardset = local_rucksack_get_templateset(0);
        $readonlyattrs = $selectedsetid === 0 ? ['disabled' => 'disabled'] : [];

        $sets = local_rucksack_get_templatesets();
        $options = [];
        foreach ($sets as $set) {
            $options[$set->id] = format_string($set->name);
        }
        $mform->addElement('select', 'config_templateset', get_string('templateset', 'block_rucksack'), $options);
        $mform->setDefault('config_templateset', $selectedsetid);
        $mform->addHelpButton('config_templateset', 'templateset', 'block_rucksack');

        // Set management buttons.
        $buttons = html_writer::tag('button', get_string('saveset', 'block_rucksack'), array_merge([
                'type' => 'button',
                'id' => 'rucksack-save-set',
                'class' => 'btn btn-primary',
            ], $readonlyattrs))
            . ' '
            . html_writer::tag('button', get_string('saveasset', 'block_rucksack'), [
                'type' => 'button',
                'id' => 'rucksack-saveas-set',
                'class' => 'btn btn-secondary',
            ])
            . ' '
            . html_writer::tag('button', get_string('deleteset', 'block_rucksack'), array_merge([
                'type' => 'button',
                'id' => 'rucksack-delete-set',
                'class' => 'btn btn-danger',
            ], $readonlyattrs))
            . ' '
            . html_writer::tag('button', get_string('resetdefault', 'block_rucksack'), [
                'type' => 'button',
                'id' => 'rucksack-reset-set',
                'class' => 'btn btn-secondary',
            ])
            . ' '
            . html_writer::tag('span', '', ['id' => 'rucksack-set-status', 'class' => 'local-rucksack-set-status']);
        $mform->addElement('static', 'templateset_buttons', '', $buttons);

        // Main template textarea.
        $this->add_template_textarea(
            $mform,
            'config_template',
            get_string('template', 'block_rucksack'),
            'template',
            $selectedset->templatetext,
            $standardset->templatetext,
            25
        );

        // Badge row partial textarea.
        $this->add_template_textarea(
            $mform,
            'config_template_badge_row',
            get_string('template_badge_row', 'block_rucksack'),
            'template_badge_row',
            $selectedset->partialtext,
            $standardset->partialtext,
            15
        );

        // Custom CSS textarea.
        $this->add_template_textarea(
            $mform,
            'config_customcss',
            get_string('customcss', 'block_rucksack'),
            'customcss',
            $selectedset->csstext,
            $standardset->csstext,
            20
        );

        // Prepare draft area for the logo file.
        $context = context_system::instance();
        $draftitemid = file_get_unused_draft_itemid();
        file_prepare_draft_area($draftitemid, $context->id, 'block_rucksack', 'logo', 0);
        $mform->setDefault('config_logo', $draftitemid);

        // Inject the form handling script.
        $this->add_templateset_js($PAGE);
    }

    /**
     * Add JavaScript for template set management (load/save/save-as/delete/reset).
     *
     * @param \moodle_page $page
     */
    protected function add_templateset_js($page) {
        $ajaxurl = (new moodle_url('/blocks/rucksack/templateset.php'))->out();
        $sesskey = sesskey();
        $standardname = json_encode(get_string('standardset', 'block_rucksack'));
        $confirmdelete = json_encode(get_string('confirmdeleteset', 'block_rucksack'));
        $promptname = json_encode(get_string('saveasname', 'block_rucksack'));
        $savedmsg = json_encode(get_string('setsaved', 'block_rucksack'));
        $createdmsg = json_encode(get_string('setcreated', 'block_rucksack'));
        $deletedmsg = json_encode(get_string('setdeleted', 'block_rucksack'));
        $errormsg = json_encode(get_string('seterror', 'block_rucksack'));

        $js = <<<JS
(function() {
    if (document.body.dataset.rucksackTemplatesetInitialized) {
        return;
    }
    document.body.dataset.rucksackTemplatesetInitialized = '1';

    var ajaxUrl = {$ajaxurl};
    var sesskey = {$sesskey};
    var standardName = {$standardname};
    var confirmDelete = {$confirmdelete};
    var promptName = {$promptname};
    var savedMsg = {$savedmsg};
    var createdMsg = {$createdmsg};
    var deletedMsg = {$deletedmsg};
    var errorMsg = {$errormsg};

    function find(name) {
        return document.querySelector('textarea[name="' + name + '"],select[name="' + name + '"],button#' + name + ',span#' + name);
    }

    function getDropdown() {
        return document.querySelector('select[name="config_templateset"]');
    }

    function getFields() {
        return {
            template: document.querySelector('textarea[name="config_template"]'),
            partial: document.querySelector('textarea[name="config_template_badge_row"]'),
            css: document.querySelector('textarea[name="config_customcss"]')
        };
    }

    function showStatus(msg) {
        var s = document.getElementById('rucksack-set-status');
        if (s) {
            s.textContent = msg;
            setTimeout(function() { s.textContent = ''; }, 3000);
        }
    }

    function updateButtons(isstandard) {
        var saveBtn = document.getElementById('rucksack-save-set');
        var deleteBtn = document.getElementById('rucksack-delete-set');
        if (saveBtn) saveBtn.disabled = !!isstandard;
        if (deleteBtn) deleteBtn.disabled = !!isstandard;
    }

    function loadSet(id) {
        var fields = getFields();
        fetch(ajaxUrl + '?action=load&id=' + encodeURIComponent(id) + '&sesskey=' + encodeURIComponent(sesskey))
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    if (fields.template) fields.template.value = data.template || '';
                    if (fields.partial) fields.partial.value = data.partial || '';
                    if (fields.css) fields.css.value = data.css || '';
                    updateButtons(data.isstandard);
                } else {
                    showStatus(errorMsg);
                }
            })
            .catch(function() { showStatus(errorMsg); });
    }

    function saveSet() {
        var dropdown = getDropdown();
        var id = dropdown ? dropdown.value : 0;
        if (id == 0) return;
        var fields = getFields();
        var body = new URLSearchParams();
        body.append('action', 'save');
        body.append('id', id);
        body.append('sesskey', sesskey);
        body.append('template', fields.template ? fields.template.value : '');
        body.append('partial', fields.partial ? fields.partial.value : '');
        body.append('css', fields.css ? fields.css.value : '');
        fetch(ajaxUrl, {method: 'POST', body: body})
            .then(function(r) { return r.json(); })
            .then(function(data) { showStatus(data.success ? savedMsg : (data.error || errorMsg)); })
            .catch(function() { showStatus(errorMsg); });
    }

    function saveAsSet() {
        var name = prompt(promptName);
        if (!name || !name.trim()) return;
        var fields = getFields();
        var body = new URLSearchParams();
        body.append('action', 'saveas');
        body.append('sesskey', sesskey);
        body.append('name', name.trim());
        body.append('template', fields.template ? fields.template.value : '');
        body.append('partial', fields.partial ? fields.partial.value : '');
        body.append('css', fields.css ? fields.css.value : '');
        fetch(ajaxUrl, {method: 'POST', body: body})
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success && data.id) {
                    var dropdown = getDropdown();
                    if (dropdown) {
                        var opt = document.createElement('option');
                        opt.value = data.id;
                        opt.textContent = name.trim();
                        dropdown.appendChild(opt);
                        dropdown.value = data.id;
                    }
                    updateButtons(false);
                    showStatus(createdMsg);
                } else {
                    showStatus(data.error || errorMsg);
                }
            })
            .catch(function() { showStatus(errorMsg); });
    }

    function deleteSet() {
        var dropdown = getDropdown();
        var id = dropdown ? dropdown.value : 0;
        if (id == 0) return;
        if (!confirm(confirmDelete)) return;
        var body = new URLSearchParams();
        body.append('action', 'delete');
        body.append('id', id);
        body.append('sesskey', sesskey);
        fetch(ajaxUrl, {method: 'POST', body: body})
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    var dropdown = getDropdown();
                    if (dropdown) {
                        var opt = dropdown.querySelector('option[value="' + id + '"]');
                        if (opt) opt.remove();
                        dropdown.value = 0;
                    }
                    loadSet(0);
                    showStatus(deletedMsg);
                } else {
                    showStatus(data.error || errorMsg);
                }
            })
            .catch(function() { showStatus(errorMsg); });
    }

    function resetSet() {
        loadSet(0);
        showStatus(standardName);
    }

    document.addEventListener('click', function(e) {
        if (e.target.id === 'rucksack-save-set') {
            e.preventDefault();
            saveSet();
        } else if (e.target.id === 'rucksack-saveas-set') {
            e.preventDefault();
            saveAsSet();
        } else if (e.target.id === 'rucksack-delete-set') {
            e.preventDefault();
            deleteSet();
        } else if (e.target.id === 'rucksack-reset-set') {
            e.preventDefault();
            resetSet();
        }
    });

    document.addEventListener('change', function(e) {
        if (e.target.name === 'config_templateset') {
            loadSet(e.target.value);
        }
    });

    // Initialize button state for the currently selected set.
    var dropdown = getDropdown();
    if (dropdown) {
        updateButtons(dropdown.value == 0);
    }
})();
JS;

        $page->requires->js_init_code($js);
    }
}
