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
        $onchangeload = 'var fields={t:document.querySelector(\'textarea[name="config_template"]\'),p:document.querySelector(\'textarea[name="config_template_badge_row"]\'),c:document.querySelector(\'textarea[name="config_customcss"]\')};var sb=document.getElementById("rucksack-save-set");var db=document.getElementById("rucksack-delete-set");fetch(' . $ajaxurl . '?action=load&id=\'+this.value+\'&sesskey=' . urlencode(sesskey()) . ').then(function(r){return r.json()}).then(function(d){if(d.success){if(fields.t)fields.t.value=d.template||"";if(fields.p)fields.p.value=d.partial||"";if(fields.c)fields.c.value=d.css||"";}if(sb){if(!!d.isstandard){sb.setAttribute("disabled","disabled");}else{sb.removeAttribute("disabled");}}if(db){if(!!d.isstandard){db.setAttribute("disabled","disabled");}else{db.removeAttribute("disabled");}}});';

        $select = $mform->createElement('select', 'config_templateset', get_string('templateset', 'block_rucksack'), $options, ['onchange' => $onchangeload]);
        $mform->addElement($select);
        $mform->setDefault('config_templateset', $selectedsetid);
        $mform->addHelpButton('config_templateset', 'templateset', 'block_rucksack');

        // Set management buttons.
        $ajaxurl = json_encode((new moodle_url('/blocks/rucksack/templateset.php'))->out());
        $sesskey = json_encode(sesskey());
        $saved = json_encode(get_string('setsaved', 'block_rucksack'));
        $created = json_encode(get_string('setcreated', 'block_rucksack'));
        $deleted = json_encode(get_string('setdeleted', 'block_rucksack'));
        $error = json_encode(get_string('seterror', 'block_rucksack'));
        $confirmdelete = json_encode(get_string('confirmdeleteset', 'block_rucksack'));
        $promptname = json_encode(get_string('saveasname', 'block_rucksack'));
        $standardname = json_encode(get_string('standardset', 'block_rucksack'));

        $onclicksave = 'var dd=document.querySelector(\'select[name="config_templateset"]\');var id=dd?dd.value:0;if(id==0)return;var f={t:document.querySelector(\'textarea[name="config_template"]\').value,p:document.querySelector(\'textarea[name="config_template_badge_row"]\').value,c:document.querySelector(\'textarea[name="config_customcss"]\').value};var b=new URLSearchParams();b.append("action","save");b.append("id",id);b.append("sesskey",' . $sesskey . ');b.append("template",f.t);b.append("partial",f.p);b.append("css",f.c);fetch(' . $ajaxurl . ',{method:"POST",body:b}).then(function(r){return r.json()}).then(function(d){var s=document.getElementById("rucksack-set-status");if(s){s.textContent=d.success?' . $saved . ':' . $error . ';setTimeout(function(){s.textContent="";},3000);}});';

        $onclicksaveas = 'var name=prompt(' . $promptname . ');if(!name||!name.trim())return;var f={t:document.querySelector(\'textarea[name="config_template"]\').value,p:document.querySelector(\'textarea[name="config_template_badge_row"]\').value,c:document.querySelector(\'textarea[name="config_customcss"]\').value};var b=new URLSearchParams();b.append("action","saveas");b.append("sesskey",' . $sesskey . ');b.append("name",name.trim());b.append("template",f.t);b.append("partial",f.p);b.append("css",f.c);fetch(' . $ajaxurl . ',{method:"POST",body:b}).then(function(r){return r.json()}).then(function(d){if(d.success&&d.id){var dd=document.querySelector(\'select[name="config_templateset"]\');if(dd){var o=document.createElement("option");o.value=d.id;o.textContent=name.trim();dd.appendChild(o);dd.value=d.id;}var sb=document.getElementById("rucksack-save-set");var db=document.getElementById("rucksack-delete-set");if(sb)sb.removeAttribute("disabled");if(db)db.removeAttribute("disabled");var s=document.getElementById("rucksack-set-status");if(s){s.textContent=' . $created . ';setTimeout(function(){s.textContent="";},3000);}}else{var s=document.getElementById("rucksack-set-status");if(s){s.textContent=' . $error . ';setTimeout(function(){s.textContent="";},3000);}}});';

        $onclickdelete = 'var dd=document.querySelector(\'select[name="config_templateset"]\');var id=dd?dd.value:0;if(id==0||!confirm(' . $confirmdelete . '))return;var b=new URLSearchParams();b.append("action","delete");b.append("id",id);b.append("sesskey",' . $sesskey . ');fetch(' . $ajaxurl . ',{method:"POST",body:b}).then(function(r){return r.json()}).then(function(d){if(d.success){var dd=document.querySelector(\'select[name="config_templateset"]\');if(dd){var o=dd.querySelector(\'option[value="\'+id+\'"]\');if(o)o.remove();dd.value=0;}var f={t:document.querySelector(\'textarea[name="config_template"]\'),p:document.querySelector(\'textarea[name="config_template_badge_row"]\'),c:document.querySelector(\'textarea[name="config_customcss"]\')};fetch(' . $ajaxurl . '?action=load&id=0&sesskey=' . urlencode(sesskey()) . ').then(function(r){return r.json()}).then(function(d){if(d.success){if(f.t)f.t.value=d.template||"";if(f.p)f.p.value=d.partial||"";if(f.c)f.c.value=d.css||"";}var sb=document.getElementById("rucksack-save-set");var db=document.getElementById("rucksack-delete-set");if(sb)sb.setAttribute("disabled","disabled");if(db)db.setAttribute("disabled","disabled");var s=document.getElementById("rucksack-set-status");if(s){s.textContent=' . $deleted . ';setTimeout(function(){s.textContent="";},3000);}});}});';

        $onclickreset = 'var f={t:document.querySelector(\'textarea[name="config_template"]\'),p:document.querySelector(\'textarea[name="config_template_badge_row"]\'),c:document.querySelector(\'textarea[name="config_customcss"]\')};fetch(' . $ajaxurl . '?action=load&id=0&sesskey=' . urlencode(sesskey()) . ').then(function(r){return r.json()}).then(function(d){if(d.success){if(f.t)f.t.value=d.template||"";if(f.p)f.p.value=d.partial||"";if(f.c)f.c.value=d.css||"";}var s=document.getElementById("rucksack-set-status");if(s){s.textContent=' . $standardname . ';setTimeout(function(){s.textContent="";},3000);}});';

        $buttons = html_writer::tag('button', get_string('saveset', 'block_rucksack'), array_merge([
                'type' => 'button',
                'id' => 'rucksack-save-set',
                'class' => 'btn btn-primary',
                'onclick' => $onclicksave,
            ], $readonlyattrs))
            . ' '
            . html_writer::tag('button', get_string('saveasset', 'block_rucksack'), [
                'type' => 'button',
                'id' => 'rucksack-saveas-set',
                'class' => 'btn btn-secondary',
                'onclick' => $onclicksaveas,
            ])
            . ' '
            . html_writer::tag('button', get_string('deleteset', 'block_rucksack'), array_merge([
                'type' => 'button',
                'id' => 'rucksack-delete-set',
                'class' => 'btn btn-danger',
                'onclick' => $onclickdelete,
            ], $readonlyattrs))
            . ' '
            . html_writer::tag('button', get_string('resetdefault', 'block_rucksack'), [
                'type' => 'button',
                'id' => 'rucksack-reset-set',
                'class' => 'btn btn-secondary',
                'onclick' => $onclickreset,
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

    }
}
