/**
 * JavaScript for form editing proctor availability conditions.
 *
 * Layout and field-dependency logic mirror the admin preset form
 * (classes/preset_form.php). Storage format remains the legacy flat
 * structure so existing exams continue to work unchanged.
 *
 * @module moodle-availability_proctor-form
 */
/** @suppress checkVars */
M.availability_proctor = M.availability_proctor || {};

M.availability_proctor.form = Y.Object(M.core_availability.plugin);

M.availability_proctor.form.rules = null;

M.availability_proctor.form.initInner = function(rules, warnings, scoring, streamsPresetOptions, defaults, groups, context) {
    this.rules = rules;
    this.warnings = warnings;
    this.scoring = scoring;
    this.defaults = defaults;
    this.groups = groups;
    this.streamsPresetOptions = streamsPresetOptions;
    this.context = context || {ajaxurl: '', sesskey: '', courseid: 0, global_presets: [], user_presets: []};
    this.hiddenFields = (context && context.hidden_fields) ? context.hidden_fields : [];
};

M.availability_proctor.form.instId = 0;

M.availability_proctor.form.getNode = function(json) {
    M.availability_proctor.form.instId += 1;
    var node, value;

    // Inject style overrides once: suppress the focus outline/box-shadow on
    // the help-icon spans so clicking the icon doesn't draw a focus square.
    if (!M.availability_proctor.form._stylesInjected) {
        M.availability_proctor.form._stylesInjected = true;
        var head = Y.one('head');
        if (head) {
            head.append(Y.Node.create(
                '<style>' +
                '.proctor-help, .proctor-help:focus, .proctor-help:active, .proctor-help:focus-visible,' +
                '.availability_proctor-tab-btns a, .availability_proctor-tab-btns a:focus,' +
                '.availability_proctor-tab-btns a:active, .availability_proctor-tab-btns a:focus-visible,' +
                '.proctor-load-preset, .proctor-load-preset:focus, .proctor-load-preset:active,' +
                '.proctor-load-preset:focus-visible,' +
                '.proctor-moreless, .proctor-moreless:focus, .proctor-moreless:active,' +
                '.proctor-moreless:focus-visible {' +
                '  outline: none !important; box-shadow: none !important; border: none !important;' +
                '}' +
                '</style>'
            ));
        }
    }

    var id = 'proctor' + M.availability_proctor.form.instId;
    var modeId = id + '_mode';
    var sendManualWarningsToLearnerId = id + '_sendManualWarningsToLearner';
    var identificationId = id + '_identification';
    var checkidphotoqualityId = id + '_checkidphotoquality';
    var preliminaryCheckId = id + '_preliminaryCheck';
    var webCameraMainViewId = id + '_webCameraMainView';
    var auxiliaryCameraId = id + '_auxCamera';
    var allowRoomScanAuxCameraId = id + '_allowRoomScanAuxCamera';
    var allowmultipledisplaysId = id + '_allowmultipledisplays';
    var streamsPresetId = id + '_streamsPreset';
    var enableSecureBrowserId = id + '_secureBrowser';
    var secureBrowserLevelId = id + '_secureBrowserLevel';
    var allowToUseAdditionalResourcesId = id + '_allowToUseAdditionalResources';
    var allowedProcessesId = id + '_allowedProcesses';
    var forbiddenProcessesId = id + '_forbiddenProcesses';
    var allowvirtualenvironmentId = id + '_allowvirtualenvironment';
    var calculatorId = id + '_calculator';
    var userAgreementId = id + '_userAgreement';
    var isTrialId = id + '_isTrial';
    var customRulesId = id + '_customRules';

    var tabButtonOne, tabButtonTwo, tabOne, tabTwo;

    // Brand-driven visibility: PHP-side brand::HIDDEN_FORM_FIELDS is shipped
    // through context. formGroup/sectionHeader return '' for any name in this
    // list, so the field/section is not rendered into the DOM. The backend
    // (condition::__construct) replaces values for the same set of fields
    // with default preset values, so even if a hidden field is force-rendered
    // via DevTools, its submitted value is dropped server-side.
    var hiddenFields = this.hiddenFields || [];
    function isHidden(name) {
        return !!name && hiddenFields.indexOf(name) !== -1;
    }

    function getString(identifier, module) {
        module = module || 'availability_proctor';
        return M.util.get_string(identifier, module);
    }

    function moreLess(content) {
        var showmore = getString('showmore', 'core_form');
        var showless = getString('showless', 'core_form');
        return '<a href="#" class="proctor-moreless" data-more="' + showmore + '" data-less="' + showless + '">' +
            showmore +
            '</a><div class="hidden col-md-12">' + content + '</div>';
    }

    function switchMoreLessState(target) {
        var next = target.next();
        var hidden = next.hasClass('hidden');
        if (hidden) {
            next.removeClass('hidden');
            target.setContent(target.getAttribute('data-less'));
        } else {
            next.addClass('hidden');
            target.setContent(target.getAttribute('data-more'));
        }
    }

    function nextTick(callback) {
        setTimeout(callback, 0);
    }

    function switchTab(tab) {
        if (tab == 1) {
            tabButtonOne.addClass('btn-primary');
            tabButtonOne.removeClass('btn-secondary');
            tabButtonTwo.addClass('btn-secondary');
            tabButtonTwo.removeClass('btn-primary');
            tabOne.removeClass('hidden');
            tabTwo.addClass('hidden');
        } else {
            tabButtonTwo.addClass('btn-primary');
            tabButtonTwo.removeClass('btn-secondary');
            tabButtonOne.addClass('btn-secondary');
            tabButtonOne.removeClass('btn-primary');
            tabOne.addClass('hidden');
            tabTwo.removeClass('hidden');
        }
    }

    var optionStyle = 'white-space: break-spaces; display:flex; align-items: baseline; justify-content: flex-start;';

    /**
     * Wrap one form field with a label column, marking it via data-field
     * so visibility toggles can find and hide it.
     */
    function formGroup(fieldId, label, content, fullwidth, fieldName, labelHintKey) {
        if (isHidden(fieldName)) {
            return '';
        }
        var labelcols = fullwidth ? 12 : 5;
        var fieldcols = fullwidth ? 12 : 7;
        var flexdir = fullwidth ? 'flex-column' : 'flex-row';
        var dataAttr = fieldName ? ' data-field="' + fieldName + '"' : '';
        var labelHelp = '';
        if (labelHintKey) {
            // Wrap icon in an inline-flex anchor that vertically centres its
            // child icon — that way the icon's centre line aligns with the
            // checkbox/input's centre line in the field column.
            labelHelp = ' <span class="proctor-help ml-auto d-inline-flex align-items-center"' +
                ' data-hint-key="' + labelHintKey + '"' +
                ' role="button" tabindex="0" style="cursor:pointer; outline:none; min-height:1.5rem;">' +
                '<i class="icon fa fa-question-circle text-info"></i></span>';
        }
        // Use start (top) alignment in both columns so the help icon and the
        // input/checkbox stay on the first line even when the label wraps.
        return '<span class="availability-group form-group mb-2 d-flex ' + flexdir + '"' + dataAttr + '>' +
            '<div class="col-md-' + labelcols + ' col-form-label d-flex pb-0 pl-md-0 align-items-start">' +
            '  <label for="' + fieldId + '" class="mb-0" style="line-height:1.5rem;">' + label + '</label>' + labelHelp +
            '</div>' +
            '<div class="col-md-' + fieldcols + ' form-inline align-items-start felement pl-md-0"' +
            ' style="min-height:1.5rem;">' +
            content +
            '</div>' +
            '</span>';
    }

    function sectionHeader(label, sectionName) {
        if (isHidden(sectionName)) {
            return '';
        }
        return '<h5 class="proctor-section-header mt-3 mb-2 pb-1 border-bottom">' + label + '</h5>';
    }

    /**
     * Append a clickable help icon next to the input. Hover and click both
     * show the same width-capped popover; the native title tooltip is
     * intentionally omitted so it cannot exceed our width cap.
     * @param {string} content Existing control HTML
     * @param {string} hintKey Lang key for the hint text
     */
    function withHint(content, hintKey) {
        return content +
            ' <span class="proctor-help ml-2" data-hint-key="' + hintKey + '"' +
            ' role="button" tabindex="0" style="cursor:pointer; outline:none;">' +
            '<i class="icon fa fa-question-circle text-info"></i>' +
            '</span>';
    }

    var html = '';

    // ---- Section: Proctoring mode ----
    html += sectionHeader(getString('preset_section_mode'), 'preset_section_mode');
    html += formGroup(modeId, getString('proctoring_mode'),
        '<select name="mode" id="' + modeId + '" class="custom-select">' +
        '  <option value="online">' + getString('online_mode') + '</option>' +
        '  <option value="offline">' + getString('offline_mode') + '</option>' +
        '  <option value="auto">' + getString('auto_mode') + '</option>' +
        '</select>',
        false, 'mode', 'proctoring_mode_help'
    );
    html += formGroup(sendManualWarningsToLearnerId, getString('sendmanualwarningstolearner'),
        '<input type="checkbox" name="sendmanualwarningstolearner" id="' + sendManualWarningsToLearnerId + '" value="1">',
        false, 'sendmanualwarningstolearner', 'sendmanualwarningstolearner_help'
    );

    // ---- Section: Identity verification ----
    html += sectionHeader(getString('preset_section_identity'), 'preset_section_identity');
    html += formGroup(identificationId, getString('identification'),
        '<select name="identification" id="' + identificationId + '" class="custom-select">' +
        '  <option value="face_and_passport">' + getString('face_passport_identification') + '</option>' +
        '  <option value="passport">' + getString('passport_identification') + '</option>' +
        '  <option value="face">' + getString('face_identification') + '</option>' +
        '  <option value="skip">' + getString('skip_identification') + '</option>' +
        '</select>',
        false, 'identification', 'identification_help'
    );
    html += formGroup(checkidphotoqualityId, getString('checkidphotoquality'),
        '<input type="checkbox" name="checkidphotoquality" id="' + checkidphotoqualityId + '" value="1">',
        false, 'checkidphotoquality', 'checkidphotoquality_help'
    );
    html += formGroup(preliminaryCheckId, getString('preliminary_check'),
        '<input type="checkbox" name="preliminarycheck" id="' + preliminaryCheckId + '" value="1">',
        false, 'preliminarycheck', 'preliminary_check_help'
    );

    // ---- Section: Camera and monitoring ----
    html += sectionHeader(getString('preset_section_camera'), 'preset_section_camera');
    html += formGroup(webCameraMainViewId, getString('web_camera_main_view'),
        '<select name="webcameramainview" id="' + webCameraMainViewId + '" class="custom-select">' +
        '  <option value="front">' + getString('web_camera_main_view_front') + '</option>' +
        '  <option value="side">' + getString('web_camera_main_view_side') + '</option>' +
        '</select>',
        false, 'webcameramainview', 'web_camera_main_view_help'
    );
    html += formGroup(auxiliaryCameraId, getString('auxiliary_camera'),
        '<select name="auxiliarycamera" id="' + auxiliaryCameraId + '" class="custom-select">' +
        '  <option value="0">' + getString('auxiliary_camera_off') + '</option>' +
        '  <option value="1">' + getString('auxiliary_camera_on') + '</option>' +
        '</select>',
        false, 'auxiliarycamera', 'auxiliary_camera_help'
    );
    html += formGroup(allowRoomScanAuxCameraId, getString('allowroomscanauxcamera'),
        '<input type="checkbox" name="allowroomscanauxcamera" id="' + allowRoomScanAuxCameraId + '" value="1">',
        false, 'allowroomscanauxcamera', 'allowroomscanauxcamera_help'
    );
    html += formGroup(allowmultipledisplaysId, getString('allowmultipledisplays'),
        '<input type="checkbox" name="allowmultipledisplays" id="' + allowmultipledisplaysId + '" value="1">',
        false, 'allowmultipledisplays', 'allowmultipledisplays_help'
    );

    var streamsPresetOptions = '';
    for (var spi in this.streamsPresetOptions) {
        var spkey = this.streamsPresetOptions[spi];
        streamsPresetOptions += '<option value="' + spkey + '">' + getString('streamspreset_' + spkey) + '</option>';
    }
    html += formGroup(streamsPresetId, getString('streamspreset'),
        '<select name="streamspreset" id="' + streamsPresetId + '" class="custom-select">' + streamsPresetOptions + '</select>',
        false, 'streamspreset', 'streamspreset_help'
    );

    // ---- Section: Secure Browser ----
    html += sectionHeader(getString('preset_section_securebrowser'), 'preset_section_securebrowser');
    html += formGroup(enableSecureBrowserId, getString('enable_secure_browser'),
        '<input type="checkbox" name="securebrowser" id="' + enableSecureBrowserId + '" value="1">',
        false, 'securebrowser', 'enable_secure_browser_help'
    );
    html += formGroup(secureBrowserLevelId, getString('secure_browser_level'),
        '<select name="securebrowserlevel" id="' + secureBrowserLevelId + '" class="custom-select">' +
        '  <option value="basic">' + getString('secure_browser_level_basic') + '</option>' +
        '  <option value="medium">' + getString('secure_browser_level_medium') + '</option>' +
        '  <option value="high">' + getString('secure_browser_level_high') + '</option>' +
        '</select>',
        false, 'securebrowserlevel', 'secure_browser_level_help'
    );
    html += formGroup(allowToUseAdditionalResourcesId, getString('allowtouseadditionalresources'),
        '<input type="checkbox" name="allowtouseadditionalresources" id="' + allowToUseAdditionalResourcesId + '" value="1">',
        false, 'allowtouseadditionalresources', 'allowtouseadditionalresources_help'
    );
    html += formGroup(allowedProcessesId, getString('allowed_processes'),
        '<textarea name="allowedprocesses" id="' + allowedProcessesId + '" style="width: 100%" class="form-control"></textarea>',
        false, 'allowedprocesses', 'allowed_processes_help'
    );
    html += formGroup(forbiddenProcessesId, getString('forbidden_processes'),
        '<textarea name="forbiddenprocesses" id="' + forbiddenProcessesId + '" style="width: 100%" class="form-control"></textarea>',
        false, 'forbiddenprocesses', 'forbidden_processes_help'
    );
    html += formGroup(allowvirtualenvironmentId, getString('allowvirtualenvironment'),
        '<input type="checkbox" name="allowvirtualenvironment" id="' + allowvirtualenvironmentId + '" value="1">',
        false, 'allowvirtualenvironment', 'allowvirtualenvironment_help'
    );

    // ---- Section: Allow during exam ----
    html += sectionHeader(getString('preset_section_rules'), 'preset_section_rules');
    if (!isHidden('rules')) {
        for (var key in this.rules) {
            // Calculator allowance is derived from the Calculator dropdown below.
            if (key === 'allow_to_use_calculator') {
                continue;
            }
            var keyId = id + '_' + key;
            html += formGroup(keyId, getString(key),
                '<input type="checkbox" class="proctor-rule" name="' + key + '" id="' + keyId + '" value="' + key + '">',
                false, key, key + '_help'
            );
        }
    }

    html += formGroup(calculatorId, getString('calculator'),
        '<select name="calculator" id="' + calculatorId + '" class="custom-select">' +
        '  <option value="off">' + getString('calculator_off') + '</option>' +
        '  <option value="simple">' + getString('calculator_simple') + '</option>' +
        '  <option value="scientific">' + getString('calculator_scientific') + '</option>' +
        '</select>',
        false, undefined, 'calculator_help'
    );

    html += formGroup(userAgreementId, getString('user_agreement_url'),
        '<input name="useragreementurl" id="' + userAgreementId + '" class="form-control" value="" />',
        false, undefined, 'user_agreement_url_help'
    );

    // ---- Exam-only fields (not part of preset) ----
    html += sectionHeader(getString('preset_section_exam'), 'preset_section_exam');
    html += formGroup(isTrialId, getString('is_trial'),
        '<input type="checkbox" name="istrial" id="' + isTrialId + '" value="1">',
        false, undefined, 'is_trial_help'
    );
    html += formGroup(customRulesId, getString('custom_rules'),
        '<textarea name="customrules" id="' + customRulesId + '" style="width: 100%" class="form-control"></textarea>',
        false, undefined, 'custom_rules_help'
    );
    var groupOptions = '';
    for (var i in this.groups) {
        var group = this.groups[i];
        groupOptions += '<label style="' + optionStyle + '">' +
            '<input value="' + group.id + '" type="checkbox" name="proctoring-groups[' + group.id + ']">' +
            '&nbsp;' + group.name +
            '</label>';
    }
    if (groupOptions && groupOptions.length) {
        html += formGroup(null, getString('select_groups'), '<div class="groups">' + groupOptions + '</div>');
    }

    // ---- Tab 2: Alerts shown to student + Scoring parameters ----
    // Warnings that get suppressed when the corresponding allow-rule is on.
    var ruleWarningMap = {
        'allow_to_use_websites': 'warning_change_active_window_on_computer',
        'allow_voices': 'warning_voice_detected',
        'allow_wrong_gaze_direction': 'warning_avert_eyes',
        'allow_absence_in_frame': 'warning_no_user_in_frame'
    };
    var suppressibleWarnings = {};
    for (var rk in ruleWarningMap) {
        suppressibleWarnings[ruleWarningMap[rk]] = rk;
    }

    var warningOptions = '';
    for (var wkey in this.warnings) {
        var wkeyId = id + '_' + wkey;
        var hint = '';
        if (suppressibleWarnings[wkey]) {
            hint = ' <span class="proctor-help" data-hint-key="' + wkey + '_help"' +
                ' role="button" tabindex="0" style="cursor:pointer; outline:none;">' +
                '<i class="icon fa fa-question-circle text-info"></i></span>';
        }
        warningOptions += '<label for="' + wkeyId + '" style="' + optionStyle + '">' +
            '<input type="checkbox" name="' + wkey + '" id="' + wkeyId + '" value="' + wkey + '">&nbsp;' +
            getString(wkey) + hint +
            '</label>';
    }
    var scoringOptions = '';
    for (var skey in this.scoring) {
        var skeyId = id + '_' + skey;
        var smin = this.scoring[skey].min;
        var smax = this.scoring[skey].max;
        var scoringInputHTML = '<input type="number" class="proctor-scoring-input" value=""' +
            ' step="0.01" name="' + skey + '" id="scoring_' + skeyId + '"' +
            ' min="' + smin + '" max="' + smax + '" style="width: 6em;">';
        var scoringHintKey = skey === 'cheater_level' ? 'scoring_cheater_level_help' : 'scoring_help';
        scoringOptions += formGroup(skeyId, getString('scoring_' + skey),
            scoringInputHTML, false, undefined, scoringHintKey);
    }

    var htmlTwo = '';
    if (!isHidden('warnings')) {
        htmlTwo += sectionHeader(getString('preset_section_warnings'), 'warnings');
        htmlTwo += '<div class="text-muted small mb-2">' + getString('warnings_help') + '</div>';
        htmlTwo += '<div class="warnings" style="white-space: nowrap">' + warningOptions + '</div>';
    }
    if (!isHidden('scoring')) {
        htmlTwo += sectionHeader(getString('preset_section_scoring'), 'scoring');
        htmlTwo += '<div class="text-muted small mb-2">' + getString('scoring_section_hint') + '</div>';
        htmlTwo += scoringOptions;
    }

    // ---- Build node ----
    node = Y.Node.create('<span class="availability_proctor-tabs" style="position:relative; display:block;"></span>');
    node.setHTML('<label><strong>' + getString('title') + '</strong></label><br>');

    // Load-preset toolbar (above the form).
    var loadBar = Y.Node.create(
        '<div class="proctor-loadbar mb-2 d-flex align-items-baseline flex-wrap">' +
        '  <span class="proctor-loaded-preset text-muted mr-2"></span>' +
        '  <a href="#" class="proctor-load-preset">' + getString('load_preset') + '</a>' +
        '  <div class="proctor-preset-picker w-100 mt-2" style="display:none; flex-basis:100%;"></div>' +
        '</div>'
    ).appendTo(node);

    // Tab 2 holds the warnings + scoring sections. If both are hidden by
    // brand visibility, htmlTwo is empty — render only tab 1 inline and skip
    // the tab nav buttons altogether.
    var hasTabTwo = htmlTwo.length > 0;

    var tabButtons = Y.Node.create(
        '<div style="position:absolute; top: 0; right: 0;" class="availability_proctor-tab-btns"></div>'
    ).appendTo(node);
    tabButtonOne = Y.Node.create('<a href="#" class="btn btn-primary">1</a>').appendTo(tabButtons);
    tabButtonTwo = Y.Node.create('<a href="#" class="btn btn-secondary">2</a>').appendTo(tabButtons);
    if (!hasTabTwo) {
        tabButtons.setStyle('display', 'none');
    }

    tabOne = Y.Node.create('<div class="tab_content">' + html + '</div>').appendTo(node);
    tabTwo = Y.Node.create('<div class="tab_content hidden">' + htmlTwo + '</div>').appendTo(node);

    // Save-as-personal-preset toolbar (below the form, in tab one).
    var saveBar = Y.Node.create(
        '<div class="proctor-savebar mt-2">' +
        '  <a href="#" class="proctor-save-preset btn btn-secondary">' + getString('save_personal_preset') + '</a>' +
        '  <div class="proctor-save-panel mt-2 p-3 border rounded bg-light" style="display:none; max-width:600px;">' +
        '    <div class="text-muted small mb-2">' + getString('save_personal_preset_hint') + '</div>' +
        '    <label class="d-block mb-1">' + getString('save_personal_preset_prompt') + '</label>' +
        '    <input type="text" class="proctor-save-name form-control mb-2" maxlength="255">' +
        '    <div class="proctor-save-status text-danger small mb-2" style="display:none;"></div>' +
        '    <button type="button" class="proctor-save-confirm btn btn-primary btn-sm">' + getString('savechanges', 'core') + '</button>' +
        '    <button type="button" class="proctor-save-cancel btn btn-secondary btn-sm ml-1">' + getString('cancel', 'core') + '</button>' +
        '  </div>' +
        '</div>'
    ).appendTo(tabOne);

    // ---- Default values ----
    if (json.rules === undefined) {
        json.rules = {};
        for (var dr in this.rules) { json.rules[dr] = this.rules[dr]; }
    }
    if (json.warnings === undefined) {
        json.warnings = {};
        for (var dw in this.warnings) { json.warnings[dw] = this.warnings[dw]; }
    }
    json.scoring = json.scoring || {};
    if (json.sendmanualwarningstolearner === undefined) {
        json.sendmanualwarningstolearner = true;
    }

    if (json.creating) {
        for (var dkey in this.defaults) {
            var dvalue = this.defaults[dkey];
            if (dkey == 'scoring') {
                for (var dskey in dvalue) {
                    json.scoring[dskey] = dvalue[dskey] ? parseFloat(dvalue[dskey]) : null;
                }
            } else if (dkey == 'rules') {
                for (var drkey in dvalue) {
                    json.rules[drkey] = dvalue[drkey];
                }
            } else if (dkey == 'warnings') {
                for (var dwkey in dvalue) {
                    json.warnings[dwkey] = dvalue[dwkey] ? true : false;
                }
            } else if (dkey == 'groups') {
                json.groups = dvalue;
            } else {
                json[dkey] = dvalue;
            }
        }
        if (!json.mode) {
            json.mode = 'offline';
        }
    }

    // ---- Hydrate inputs from json ----
    if (json.mode !== undefined) {
        var modeOpt = node.one('select[name=mode] option[value=' + json.mode + ']');
        if (modeOpt) { modeOpt.set('selected', 'selected'); }
    }
    if (json.identification) {
        var idOpt = node.one('select[name=identification] option[value=' + json.identification + ']');
        if (idOpt) { idOpt.set('selected', 'selected'); }
    }
    if (json.webcameramainview) {
        var wcOpt = node.one('select[name=webcameramainview] option[value=' + json.webcameramainview + ']');
        if (wcOpt) { wcOpt.set('selected', 'selected'); }
    }
    // Brand visibility may have skipped rendering for some fields, so every
    // initial-value setter must guard against a null element.
    function setOn(selector, key, val) {
        var el = node.one(selector);
        if (el) { el.set(key, val); }
    }
    if (json.istrial !== undefined) {
        setOn('#' + isTrialId, 'checked', json.istrial ? 'checked' : null);
    }
    if (json.auxiliarycamera !== undefined) {
        var auxVal = json.auxiliarycamera ? '1' : '0';
        var auxOpt = node.one('select[name=auxiliarycamera] option[value=' + auxVal + ']');
        if (auxOpt) { auxOpt.set('selected', 'selected'); }
    }
    if (json.securebrowser !== undefined) {
        setOn('#' + enableSecureBrowserId, 'checked', json.securebrowser ? 'checked' : null);
    }
    if (json.allowmultipledisplays !== undefined) {
        setOn('#' + allowmultipledisplaysId, 'checked', json.allowmultipledisplays ? 'checked' : null);
    }
    if (json.allowvirtualenvironment !== undefined) {
        setOn('#' + allowvirtualenvironmentId, 'checked', json.allowvirtualenvironment ? 'checked' : null);
    }
    if (json.allowtouseadditionalresources !== undefined) {
        setOn('#' + allowToUseAdditionalResourcesId, 'checked',
            json.allowtouseadditionalresources ? 'checked' : null);
    }
    if (json.checkidphotoquality !== undefined) {
        setOn('#' + checkidphotoqualityId, 'checked', json.checkidphotoquality ? 'checked' : null);
    }
    if (json.sendmanualwarningstolearner !== undefined) {
        setOn('#' + sendManualWarningsToLearnerId, 'checked', json.sendmanualwarningstolearner ? 'checked' : null);
    }
    if (json.preliminarycheck !== undefined) {
        setOn('#' + preliminaryCheckId, 'checked', json.preliminarycheck ? 'checked' : null);
    }
    if (json.calculator !== undefined) {
        var calcOpt = node.one('select[name=calculator] option[value=' + json.calculator + ']');
        if (calcOpt) { calcOpt.set('selected', 'selected'); }
    }
    if (json.streamspreset !== undefined) {
        var spOpt = node.one('select[name=streamspreset] option[value=' + json.streamspreset + ']');
        if (spOpt) { spOpt.set('selected', 'selected'); }
    }
    if (json.securebrowserlevel) {
        var sblOpt = node.one('select[name=securebrowserlevel] option[value=' + json.securebrowserlevel + ']');
        if (sblOpt) { sblOpt.set('selected', 'selected'); }
    }
    if (json.allowroomscanauxcamera !== undefined) {
        setOn('#' + allowRoomScanAuxCameraId, 'checked', json.allowroomscanauxcamera ? 'checked' : null);
    }

    // Warnings: apply hardcoded defaults when missing.
    for (var wrkey in this.warnings) {
        if (json.warnings[wrkey] === undefined) {
            json.warnings[wrkey] = this.warnings[wrkey];
        }
    }
    for (var warningKey in json.warnings) {
        if (json.warnings[warningKey]) {
            var winput = node.one('.warnings input[name=' + warningKey + ']');
            if (winput) { winput.set('checked', 'checked'); }
        }
    }
    for (var ruleKey in json.rules) {
        if (json.rules[ruleKey]) {
            var rinput = node.one('input.proctor-rule[name=' + ruleKey + ']');
            if (rinput) { rinput.set('checked', 'checked'); }
        }
    }
    var selectedGroups = (json.groups instanceof Array) ? json.groups : [];
    selectedGroups = selectedGroups.map(function(gid) { return parseInt(gid); });
    for (var gi in this.groups) {
        var selectedGroup = this.groups[gi];
        var checked = selectedGroups.indexOf(parseInt(selectedGroup.id)) > -1;
        var groupKey = 'proctoring-groups[' + selectedGroup.id + ']';
        var ginput = node.one('.groups input[name="' + groupKey + '"]');
        if (ginput && checked) { ginput.set('checked', 'checked'); }
    }
    for (var scoringKey in json.scoring) {
        if (!isNaN(json.scoring[scoringKey])) {
            var sinput = node.one('.proctor-scoring-input[name=' + scoringKey + ']');
            if (sinput) { sinput.set('value', json.scoring[scoringKey]); }
        }
    }
    if (json.customrules !== undefined) {
        setOn('#' + customRulesId, 'value', json.customrules);
    }
    if (json.useragreementurl !== undefined) {
        setOn('#' + userAgreementId, 'value', json.useragreementurl);
    }
    if (json.forbiddenprocesses !== undefined) {
        setOn('#' + forbiddenProcessesId, 'value', json.forbiddenprocesses);
    }
    if (json.allowedprocesses !== undefined) {
        setOn('#' + allowedProcessesId, 'value', json.allowedprocesses);
    }

    // ---- Field-dependency visibility ----
    function setVisible(fieldName, visible) {
        var group = node.one('[data-field="' + fieldName + '"]');
        if (group) {
            // Bootstrap's `d-flex` sets display:flex !important, so inline
            // display:none cannot override it. Toggle `d-none` (also !important)
            // alongside `d-flex` to control visibility reliably.
            if (visible) {
                group.removeClass('d-none');
                group.addClass('d-flex');
            } else {
                group.removeClass('d-flex');
                group.addClass('d-none');
            }
        }
    }
    function applyDependencies() {
        // Each lookup is guarded against null — when a field is hidden by
        // brand visibility rules, its DOM element doesn't exist, and
        // dependent fields stay hidden too (the controller they depend on
        // is gone, so the dependent has no meaning).
        var modeEl = node.one('select[name=mode]');
        var liveMode = modeEl ? (modeEl.get('value') === 'online') : false;
        setVisible('sendmanualwarningstolearner', liveMode);

        var identEl = node.one('select[name=identification]');
        var ident = identEl ? identEl.get('value') : '';
        var idCaptured = (ident === 'face_and_passport' || ident === 'passport');
        setVisible('checkidphotoquality', idCaptured);
        var faceCaptured = (ident === 'face_and_passport' || ident === 'face');
        setVisible('preliminarycheck', faceCaptured);

        var auxEl = node.one('select[name=auxiliarycamera]');
        var auxOn = auxEl ? (auxEl.get('value') === '1') : false;
        setVisible('allowroomscanauxcamera', auxOn);

        var sbEl = node.one('input[name=securebrowser]');
        var sbOn = sbEl ? sbEl.get('checked') : false;
        setVisible('securebrowserlevel', sbOn);
        setVisible('allowtouseadditionalresources', sbOn);
        setVisible('allowedprocesses', sbOn);
        setVisible('forbiddenprocesses', sbOn);
        setVisible('allowvirtualenvironment', sbOn);

        // Rules that suppress a corresponding warning: when the rule is on,
        // force the warning off and disable its checkbox.
        for (var ruleKey in ruleWarningMap) {
            var ruleInp = node.one('input.proctor-rule[name=' + ruleKey + ']');
            if (!ruleInp) { continue; }
            var warnInp = node.one('.warnings input[name=' + ruleWarningMap[ruleKey] + ']');
            if (!warnInp) { continue; }
            if (ruleInp.get('checked')) {
                warnInp.set('checked', false);
                warnInp.set('disabled', true);
            } else {
                warnInp.set('disabled', false);
            }
        }
    }
    applyDependencies();

    // ---- Event wiring ----
    node.delegate('valuechange', function() {
        nextTick(function() { M.core_availability.form.update(); });
        applyDependencies();
        clearLoadedNameOnEdit();
    }, 'input,textarea,select');

    // Native `change` event covers <select> reliably (valuechange is text-input only).
    node.delegate('change', function() {
        nextTick(function() { M.core_availability.form.update(); });
        applyDependencies();
        clearLoadedNameOnEdit();
    }, 'select');

    node.delegate('click', function() {
        nextTick(function() { M.core_availability.form.update(); });
        applyDependencies();
        clearLoadedNameOnEdit();
    }, 'input[type=checkbox]');

    tabButtonOne.on('click', function(e) {
        e.preventDefault();
        switchTab(1);
    });
    tabButtonTwo.on('click', function(e) {
        e.preventDefault();
        switchTab(2);
    });
    node.delegate('click', function(e) {
        e.preventDefault();
        switchMoreLessState(e.target);
    }, '.proctor-moreless');

    // Help icons — show same width-capped info-card popover on hover and click.
    function helpAnchor(icon) {
        return icon.ancestor('.availability-group') || icon.ancestor('label');
    }
    function showHelp(icon) {
        var group = helpAnchor(icon);
        if (!group) { return; }
        // Close any other open popovers in this form.
        node.all('.proctor-help-pop').remove();
        var hintKey = icon.getAttribute('data-hint-key');
        // Lang strings are authored content (trusted), but we still escape
        // unexpected angle brackets defensively. Authored <br> tags are
        // restored after the escape pass so help text can break lines.
        var text = getString(hintKey)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/&lt;br\s*\/?&gt;/gi, '<br>')
            .replace(/\n/g, '<br>');
        // Cap popover width to ~120% of the form's rendered width.
        var formWidth = node.get('offsetWidth') || 600;
        var maxw = Math.round(formWidth * 1.2);
        // Absolute positioning so the popover overlays the next row instead
        // of pushing all subsequent content down (which would resize the
        // form box noticeably, especially in Safari on macOS).
        group.setStyle('position', 'relative');
        var pop = Y.Node.create(
            '<div class="proctor-help-pop"' +
            ' style="position: absolute; top: 100%; left: 0; z-index: 1050;' +
            ' margin-top: 2px; padding: 10px 14px;' +
            ' background:#fff; color:#212529;' +
            ' border:1px solid #ced4da; border-radius:4px;' +
            ' box-shadow:0 2px 6px rgba(0,0,0,0.15);' +
            ' font-size:0.9rem; line-height:1.45;' +
            ' max-width:' + maxw + 'px; box-sizing:border-box;' +
            ' min-width: 240px;">' +
            text + '</div>'
        );
        group.append(pop);
    }
    function hideHelp(icon) {
        var group = helpAnchor(icon);
        if (!group) { return; }
        var p = group.one('.proctor-help-pop');
        if (p) { p.remove(); }
    }
    node.delegate('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var icon = e.currentTarget;
        var group = helpAnchor(icon);
        if (group && group.next('.proctor-help-pop')) {
            hideHelp(icon);
        } else {
            showHelp(icon);
        }
    }, '.proctor-help');
    node.delegate('mouseenter', function(e) {
        showHelp(e.currentTarget);
    }, '.proctor-help');
    node.delegate('mouseleave', function(e) {
        hideHelp(e.currentTarget);
    }, '.proctor-help');

    // Click outside any open popover closes it.
    Y.one('body').on('click', function() {
        node.all('.proctor-help-pop').remove();
    });

    // ---- Preset picker + save-as-personal handlers ----
    var ctx = M.availability_proctor.form.context;

    // Normalise list shapes — PHP associative arrays can round-trip as objects.
    function toArray(v) {
        if (v instanceof Array) { return v; }
        if (v && typeof v === 'object') {
            var out = [];
            for (var k in v) {
                if (Object.prototype.hasOwnProperty.call(v, k)) { out.push(v[k]); }
            }
            return out;
        }
        return [];
    }
    ctx.global_presets = toArray(ctx.global_presets);
    ctx.user_presets = toArray(ctx.user_presets);

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    // Bool fields arrive from PHP/DB as string "0"/"1"; JS treats "0" as
    // truthy so we must coerce explicitly.
    function asBool(v) {
        return parseInt(v, 10) === 1;
    }

    function presetSummary(p) {
        var parts = [];
        if (p.mode) {
            var modeLabel = '';
            if (p.mode === 'online') { modeLabel = getString('online_mode'); }
            else if (p.mode === 'offline') { modeLabel = getString('offline_mode'); }
            else if (p.mode === 'auto') { modeLabel = getString('auto_mode'); }
            if (modeLabel) {
                parts.push(escapeHtml(getString('proctoring_mode')) + ': ' + escapeHtml(modeLabel));
            }
        }
        if (p.identification) {
            var idMap = {
                'face_and_passport': 'face_passport_identification',
                'passport': 'passport_identification',
                'face': 'face_identification',
                'skip': 'skip_identification'
            };
            if (idMap[p.identification]) {
                parts.push(escapeHtml(getString('identification')) + ': ' +
                    escapeHtml(getString(idMap[p.identification])));
            }
        }
        if (p.webcameramainview) {
            parts.push(escapeHtml(getString('web_camera_main_view')) + ': ' +
                escapeHtml(getString('web_camera_main_view_' + p.webcameramainview)));
        }
        if (asBool(p.auxiliarycamera)) {
            parts.push(escapeHtml(getString('auxiliary_camera')));
        }
        if (asBool(p.securebrowser)) {
            var lvl = p.securebrowserlevel ?
                ' (' + escapeHtml(getString('secure_browser_level_' + p.securebrowserlevel)) + ')' : '';
            parts.push(escapeHtml(getString('enable_secure_browser')) + lvl);
        }
        return parts.map(function(p) { return '<div>' + p + '</div>'; }).join('');
    }

    function renderPicker() {
        var picker = node.one('.proctor-preset-picker');
        var html = '<div class="card" style="width:100%;">';

        function renderGroup(label, presets, isUser) {
            html += '<div class="card-body p-2">';
            html += '<div class="font-weight-bold small text-uppercase text-muted mb-1">' + escapeHtml(label) + '</div>';
            if (!presets.length) {
                html += '<div class="text-muted small px-2 py-1">' + escapeHtml(getString('no_presets')) + '</div>';
            } else {
                html += '<ul class="list-group list-group-flush">';
                for (var i = 0; i < presets.length; i++) {
                    var p = presets[i];
                    var del = isUser ?
                        '<a href="#" class="proctor-delete-preset text-danger small ml-2" data-id="' + p.id + '">' +
                        escapeHtml(getString('delete')) + '</a>' : '';
                    html += '<li class="list-group-item p-2 d-flex justify-content-between align-items-start">' +
                        '<div style="flex:1; min-width:0;">' +
                        '  <a href="#" class="proctor-pick-preset font-weight-bold" data-id="' + p.id +
                        '" data-scope="' + (isUser ? 'user' : 'global') + '">' + escapeHtml(p.name) + '</a>' +
                        '  <div class="small text-muted">' + presetSummary(p) + '</div>' +
                        '</div>' + del +
                        '</li>';
                }
                html += '</ul>';
            }
            html += '</div>';
        }

        renderGroup(getString('global_presets'), ctx.global_presets, false);
        renderGroup(getString('personal_presets'), ctx.user_presets, true);

        html += '</div>';
        picker.setHTML(html);
    }

    var loadedPresetName = null;
    var suppressDirty = false;

    function setLoadedName(name) {
        loadedPresetName = name || null;
        var label = node.one('.proctor-loaded-preset');
        if (!label) { return; }
        var display = name ? escapeHtml(name) : escapeHtml(getString('loaded_preset_none'));
        label.setHTML(M.util.get_string('loaded_preset', 'availability_proctor', display));
    }

    function clearLoadedNameOnEdit() {
        if (suppressDirty) { return; }
        if (loadedPresetName !== null) { setLoadedName(null); }
    }

    function applyPresetToForm(preset) {
        // Scalars / selects.
        var setSelect = function(name, val) {
            if (val === null || val === undefined) { return; }
            var sel = node.one('select[name=' + name + ']');
            if (!sel) { return; }
            sel.all('option').each(function(o) { o.set('selected', false); });
            var opt = sel.one('option[value="' + val + '"]');
            if (opt) { opt.set('selected', true); }
        };
        var setCheckbox = function(name, val) {
            var inp = node.one('input[name=' + name + ']');
            // val arrives as "0"/"1" string from PHP — must coerce, since "0" is truthy.
            if (inp) { inp.set('checked', asBool(val) ? 'checked' : null); }
        };
        var setText = function(name, val) {
            if (val === null || val === undefined) { return; }
            var inp = node.one('[name=' + name + ']');
            if (inp) { inp.set('value', val); }
        };

        setSelect('mode', preset.mode);
        setSelect('identification', preset.identification);
        setSelect('webcameramainview', preset.webcameramainview);
        setSelect('streamspreset', preset.streamspreset);
        setSelect('securebrowserlevel', preset.securebrowserlevel);
        setSelect('calculator', preset.calculator);
        setSelect('auxiliarycamera', asBool(preset.auxiliarycamera) ? '1' : '0');

        setCheckbox('scheduling_required', preset.schedulingrequired);
        setCheckbox('auto_rescheduling', preset.autorescheduling);
        setCheckbox('sendmanualwarningstolearner', preset.sendmanualwarningstolearner);
        setCheckbox('checkidphotoquality', preset.checkidphotoquality);
        setCheckbox('preliminarycheck', preset.preliminarycheck);
        setCheckbox('allowroomscanauxcamera', preset.allowroomscanauxcamera);
        setCheckbox('allowmultipledisplays', preset.allowmultipledisplays);
        setCheckbox('securebrowser', preset.securebrowser);
        setCheckbox('allowtouseadditionalresources', preset.allowtouseadditionalresources);
        setCheckbox('allowvirtualenvironment', preset.allowvirtualenvironment);

        setText('useragreementurl', preset.useragreementurl);
        setText('allowedprocesses', preset.allowedprocesses);
        setText('forbiddenprocesses', preset.forbiddenprocesses);

        // Rules / warnings (objects).
        if (preset.rules) {
            node.all('input.proctor-rule').each(function(inp) {
                var key = inp.get('value');
                inp.set('checked', preset.rules[key] ? 'checked' : null);
            });
        }
        if (preset.warnings) {
            node.all('.warnings input').each(function(inp) {
                var key = inp.get('value');
                inp.set('checked', preset.warnings[key] ? 'checked' : null);
            });
        }
        // Scoring (object).
        if (preset.scoring) {
            node.all('.proctor-scoring-input').each(function(inp) {
                var key = inp.get('name');
                var val = preset.scoring[key];
                inp.set('value', (val === null || val === undefined) ? '' : val);
            });
        }

        applyDependencies();
        nextTick(function() { M.core_availability.form.update(); });
        setLoadedName(preset.name || null);
    }

    function findPreset(scope, id) {
        var list = scope === 'user' ? ctx.user_presets : ctx.global_presets;
        for (var i = 0; i < list.length; i++) {
            if (parseInt(list[i].id) === parseInt(id)) { return list[i]; }
        }
        return null;
    }

    // Toggle picker visibility.
    node.one('.proctor-load-preset').on('click', function(e) {
        e.preventDefault();
        var picker = node.one('.proctor-preset-picker');
        var hidden = picker.getStyle('display') === 'none';
        if (hidden) {
            renderPicker();
            picker.setStyle('display', 'block');
        } else {
            picker.setStyle('display', 'none');
        }
    });

    // Pick a preset from the picker.
    node.delegate('click', function(e) {
        e.preventDefault();
        var t = e.currentTarget;
        var p = findPreset(t.getAttribute('data-scope'), t.getAttribute('data-id'));
        if (p) {
            suppressDirty = true;
            applyPresetToForm(p);
            suppressDirty = false;
            node.one('.proctor-preset-picker').setStyle('display', 'none');
        }
    }, '.proctor-pick-preset');

    // Always render the "Current preset: None" label initially.
    setLoadedName(null);

    // Detect a brand-new restriction. Different Moodle versions signal this
    // differently:
    //   - `json.creating` is true (newer versions), OR
    //   - `json` has no proctor-specific keys (empty state), OR
    //   - `json` values match the legacy `default_proctoring_settings` blob
    //     exactly (some versions prefill with that blob on add).
    var jsonMatchesDefaults = function() {
        if (!M.availability_proctor.form.defaults) { return false; }
        var defs = M.availability_proctor.form.defaults;
        var checkedKeys = ['mode', 'identification', 'webcameramainview',
            'securebrowser', 'securebrowserlevel', 'auxiliarycamera', 'calculator',
            'streamspreset', 'allowtouseadditionalresources', 'allowmultipledisplays'];
        var seenAny = false;
        for (var ci = 0; ci < checkedKeys.length; ci++) {
            var k = checkedKeys[ci];
            if (defs[k] !== undefined) {
                seenAny = true;
                // Normalise booleans/strings loosely since types may differ.
                if (String(defs[k]) !== String(json[k])) {
                    return false;
                }
            }
        }
        return seenAny;
    };

    var isFresh = !!json.creating ||
        (json.mode === undefined && json.identification === undefined &&
         json.securebrowser === undefined && json.webcameramainview === undefined) ||
        jsonMatchesDefaults();

    if (isFresh) {
        for (var dpi = 0; dpi < ctx.global_presets.length; dpi++) {
            if (parseInt(ctx.global_presets[dpi].is_default, 10) === 1) {
                suppressDirty = true;
                applyPresetToForm(ctx.global_presets[dpi]);
                suppressDirty = false;
                break;
            }
        }
    }

    // Delete a personal preset — show inline confirmation next to the row.
    node.delegate('click', function(e) {
        e.preventDefault();
        var t = e.currentTarget;
        var id = t.getAttribute('data-id');
        // If a confirmation row is already open for this id, skip.
        if (t.next('.proctor-delete-confirm')) { return; }
        // Restore any other delete links and remove any other open confirms.
        node.all('.proctor-delete-preset').setStyle('display', '');
        node.all('.proctor-delete-confirm').remove();
        // Hide the originating Delete link so the row isn't redundant.
        t.setStyle('display', 'none');
        var confirmRow = Y.Node.create(
            '<span class="proctor-delete-confirm small">' +
            getString('preset_delete_confirm') + ' ' +
            '<a href="#" class="proctor-delete-yes text-danger ml-1">' + getString('delete') + '</a>' +
            ' / <a href="#" class="proctor-delete-no ml-1">' + getString('cancel', 'core') + '</a>' +
            '</span>'
        );
        t.insert(confirmRow, 'after');

        confirmRow.one('.proctor-delete-no').on('click', function(ev) {
            ev.preventDefault();
            confirmRow.remove();
            t.setStyle('display', '');
        });
        confirmRow.one('.proctor-delete-yes').on('click', function(ev) {
            ev.preventDefault();
            Y.io(ctx.ajaxurl, {
                method: 'POST',
                data: 'action=delete&sesskey=' + encodeURIComponent(ctx.sesskey) +
                    '&courseid=' + encodeURIComponent(ctx.courseid) +
                    '&id=' + encodeURIComponent(id),
                on: {
                    success: function(_, resp) {
                        var data;
                        try { data = JSON.parse(resp.responseText); } catch (err) { data = null; }
                        if (data && data.ok) {
                            ctx.user_presets = ctx.user_presets.filter(function(p) {
                                return parseInt(p.id) !== parseInt(id);
                            });
                            renderPicker();
                        } else {
                            confirmRow.setHTML('<span class="text-danger">' + ((data && data.error) || 'Error') + '</span>');
                        }
                    },
                    failure: function() {
                        confirmRow.setHTML('<span class="text-danger">Network error</span>');
                    }
                }
            });
        });
    }, '.proctor-delete-preset');

    // Save current form values as a personal preset (inline panel — no browser dialogs).
    function showSaveStatus(text, isError) {
        var st = node.one('.proctor-save-status');
        if (!st) { return; }
        if (text) {
            st.setHTML(text);
            st.setStyle('display', '');
            st.removeClass('text-danger').removeClass('text-success');
            st.addClass(isError ? 'text-danger' : 'text-success');
        } else {
            st.setStyle('display', 'none');
        }
    }
    function openSavePanel() {
        node.one('.proctor-save-panel').setStyle('display', 'block');
        var input = node.one('.proctor-save-name');
        if (input) {
            input.set('value', '');
            input.focus();
        }
        showSaveStatus(null);
    }
    function closeSavePanel() {
        node.one('.proctor-save-panel').setStyle('display', 'none');
        showSaveStatus(null);
    }

    node.one('.proctor-save-preset').on('click', function(e) {
        e.preventDefault();
        openSavePanel();
    });
    node.one('.proctor-save-cancel').on('click', function() {
        closeSavePanel();
    });
    node.one('.proctor-save-confirm').on('click', function() {
        var nameInp = node.one('.proctor-save-name');
        var name = String(nameInp ? nameInp.get('value') : '').trim();
        if (name === '') {
            showSaveStatus(getString('error_preset_name_required'), true);
            return;
        }

        var current = {};
        M.availability_proctor.form.fillValue(current, node);
        var payload = {
            mode: current.mode,
            schedulingrequired: current.scheduling_required,
            autorescheduling: current.auto_rescheduling,
            sendmanualwarningstolearner: current.sendmanualwarningstolearner,
            identification: current.identification,
            checkidphotoquality: current.checkidphotoquality,
            preliminarycheck: current.preliminarycheck,
            useragreementurl: current.useragreementurl,
            webcameramainview: current.webcameramainview,
            auxiliarycamera: current.auxiliarycamera,
            allowroomscanauxcamera: current.allowroomscanauxcamera,
            allowmultipledisplays: current.allowmultipledisplays,
            streamspreset: current.streamspreset,
            securebrowser: current.securebrowser,
            securebrowserlevel: current.securebrowserlevel,
            allowtouseadditionalresources: current.allowtouseadditionalresources,
            allowedprocesses: current.allowedprocesses,
            forbiddenprocesses: current.forbiddenprocesses,
            allowvirtualenvironment: current.allowvirtualenvironment,
            calculator: current.calculator,
            rules: current.rules,
            warnings: current.warnings,
            scoring: current.scoring
        };

        showSaveStatus('…', false);
        Y.io(ctx.ajaxurl, {
            method: 'POST',
            data: 'action=save' +
                '&sesskey=' + encodeURIComponent(ctx.sesskey) +
                '&courseid=' + encodeURIComponent(ctx.courseid) +
                '&name=' + encodeURIComponent(name) +
                '&payload=' + encodeURIComponent(JSON.stringify(payload)),
            on: {
                success: function(_, resp) {
                    var data;
                    try { data = JSON.parse(resp.responseText); } catch (err) { data = null; }
                    if (data && data.ok && data.preset) {
                        ctx.user_presets.push(data.preset);
                        if (node.one('.proctor-preset-picker').getStyle('display') !== 'none') {
                            renderPicker();
                        }
                        showSaveStatus(getString('preset_saved'), false);
                        setTimeout(closeSavePanel, 1500);
                    } else {
                        showSaveStatus((data && data.error) || 'Error', true);
                    }
                },
                failure: function() { showSaveStatus('Network error', true); }
            }
        });
    });

    return node;
};

M.availability_proctor.form.fillValue = function(value, node) {
    var rulesInputs, warningsInputs, scoringInputs, groupsInputs, key;
    // Defensive accessors: when a field is hidden by brand visibility rules,
    // its DOM element is not rendered, so node.one(...) returns null. Return
    // a neutral default so the read never throws. The PHP backend
    // (condition::__construct) replaces these neutral defaults with the
    // current default-preset values for hidden fields.
    function s(sel) { var e = node.one(sel); return e ? e.get('value').trim() : ''; }
    function b(sel) { var e = node.one(sel); return e ? e.get('checked') : false; }
    function selBool(sel) { var e = node.one(sel); return e ? (e.get('value') === '1') : false; }

    value.mode = s('select[name=mode]');
    value.identification = s('select[name=identification]');
    value.webcameramainview = s('select[name=webcameramainview]');
    value.auto_rescheduling = false;
    value.scheduling_required = false;
    value.istrial = b('input[name=istrial]');
    value.customrules = s('textarea[name=customrules]');
    value.useragreementurl = s('input[name=useragreementurl]');
    value.auxiliarycamera = selBool('select[name=auxiliarycamera]');
    value.securebrowser = b('input[name=securebrowser]');
    value.securebrowserlevel = s('select[name=securebrowserlevel]');
    value.allowtouseadditionalresources = b('input[name=allowtouseadditionalresources]');
    value.allowmultipledisplays = b('input[name=allowmultipledisplays]');
    value.allowvirtualenvironment = b('input[name=allowvirtualenvironment]');
    value.sendmanualwarningstolearner = b('input[name=sendmanualwarningstolearner]');
    value.checkidphotoquality = b('input[name=checkidphotoquality]');
    value.calculator = s('select[name=calculator]');
    value.streamspreset = s('select[name=streamspreset]');
    value.allowedprocesses = s('textarea[name=allowedprocesses]');
    value.forbiddenprocesses = s('textarea[name=forbiddenprocesses]');
    value.allowroomscanauxcamera = b('input[name=allowroomscanauxcamera]');
    value.preliminarycheck = b('input[name=preliminarycheck]');

    value.rules = {};
    rulesInputs = node.all('input.proctor-rule');
    Y.each(rulesInputs, function(ruleInput) {
        key = ruleInput.get('value');
        value.rules[key] = ruleInput.get('checked') === true;
    });
    // Calculator-allowance is mirrored from the dropdown.
    value.rules.allow_to_use_calculator = (value.calculator && value.calculator !== 'off');

    value.warnings = {};
    warningsInputs = node.all('.warnings input');
    Y.each(warningsInputs, function(warningInput) {
        key = warningInput.get('value');
        value.warnings[key] = warningInput.get('checked') === true;
    });

    value.scoring = {};
    scoringInputs = node.all('.proctor-scoring-input');
    Y.each(scoringInputs, function(scoringInput) {
        key = scoringInput.get('name');
        var scoringValue = scoringInput.get('value').trim();
        value.scoring[key] = scoringValue.length > 0 ? parseFloat(scoringValue) : null;
    });

    value.groups = [];
    groupsInputs = node.all('.groups input');
    Y.each(groupsInputs, function(groupInput) {
        var id = groupInput.get('value');
        if (groupInput.get('checked') === true) {
            value.groups.push(id);
        }
    });
};

M.availability_proctor.form.fillErrors = function(errors, node) {
    var value = {};
    this.fillValue(value, node);
    var hidden = this.hiddenFields || [];
    function isVisible(name) { return hidden.indexOf(name) === -1; }
    if (isVisible('mode') && !value.mode) {
        errors.push('availability_proctor:proctoring_mode');
    }
    if (isVisible('identification') && !value.identification) {
        errors.push('availability_proctor:identification');
    }
    if (isVisible('webcameramainview') && !value.webcameramainview) {
        errors.push('availability_proctor:web_camera_main_view');
    }
    if (isVisible('streamspreset') && !value.streamspreset) {
        errors.push('availability_proctor:streamspreset');
    }
    if (value.useragreementurl) {
        var url = String(value.useragreementurl).trim();
        if (!/^https?:\/\//i.test(url)) {
            errors.push('availability_proctor:error_useragreementurl');
        }
    }
};
