/**
 * Provider Stepper Form Controller
 *
 * Manages step navigation, per-step validation, sidebar state,
 * progress bar, and review step rendering for add_partner / edit_partner forms.
 *
 * Set window.stepperMode = 'edit' before loading to enable edit mode (9 steps).
 */
(function () {
    'use strict';

    var isEditMode = (window.stepperMode === 'edit');
    var hasScheduling = !!document.querySelector('.step-panel[data-panel="scheduling"]');
    var hasLeaves = !!document.querySelector('.step-panel[data-panel="leaves"]');
    // edit_partner inserts a Subscription step (just before Review); add/duplicate do not.
    var hasSubscription = !!document.querySelector('.step-panel[data-panel="subscription"]');
    // When the host page renders SEO as a separate module (e.g. partner profile),
    // it sets window.stepperSkipSeo = true so the stepper drops the SEO step entirely.
    var skipSeo = !!window.stepperSkipSeo;
    // When the host page renders Working Hours as a separate module (e.g. partner profile),
    // it sets window.stepperSkipWorkingHours = true so the stepper drops that step entirely.
    var skipWorkingHours = !!window.stepperSkipWorkingHours;
    var WORKING_HOURS_STEP_INDEX = hasScheduling ? 4 : 4; // same in both layouts
    var TOTAL_STEPS = (hasScheduling ? 9 : 8) + (hasLeaves ? 1 : 0) - (skipSeo ? 1 : 0) - (skipWorkingHours ? 1 : 0) + (hasSubscription ? 1 : 0);
    var currentStep = 1;
    var highestReached = isEditMode ? TOTAL_STEPS : 1;

    // Step indices shift when scheduling/leaves steps are present (add_partner)
    var LEAVES_OFFSET = hasLeaves ? 1 : 0;
    var STEP_MEDIA = (hasScheduling ? 6 : 5) + LEAVES_OFFSET - (skipWorkingHours ? 1 : 0);
    var STEP_BANK = (hasScheduling ? 7 : 6) + LEAVES_OFFSET - (skipWorkingHours ? 1 : 0);
    var STEP_SEO = skipSeo ? -1 : ((hasScheduling ? 8 : 7) + LEAVES_OFFSET - (skipWorkingHours ? 1 : 0));

    var STEP_META = hasScheduling ? [
        null,
        { icon: 'fa-user' },
        { icon: 'fa-briefcase' },
        { icon: 'fa-map-marker-alt' },
        { icon: 'fa-clock' },
        { icon: 'fa-sliders-h' },
        { icon: 'fa-images' },
        { icon: 'fa-university' },
        { icon: 'fa-magnifying-glass' },
        { icon: 'fa-check-circle' }
    ] : [
        null,
        { icon: 'fa-user' },
        { icon: 'fa-briefcase' },
        { icon: 'fa-map-marker-alt' },
        { icon: 'fa-clock' },
        { icon: 'fa-images' },
        { icon: 'fa-university' },
        { icon: 'fa-magnifying-glass' },
        { icon: 'fa-check-circle' }
    ];
    if (hasLeaves) {
        // Insert Leaves entry right after Scheduling Configuration (or after Working Hours when scheduling absent).
        STEP_META.splice(hasScheduling ? 6 : 5, 0, { icon: 'fa-calendar-times' });
    }
    if (skipSeo) {
        // Drop the SEO entry; review step shifts up by one.
        STEP_META.splice((hasScheduling ? 8 : 7) + LEAVES_OFFSET, 1);
    }
    if (skipWorkingHours) {
        // Drop the Working Hours entry; subsequent steps shift up by one.
        STEP_META.splice(WORKING_HOURS_STEP_INDEX, 1);
    }
    if (hasSubscription) {
        // Insert the Subscription entry just before the final Review step.
        STEP_META.splice(STEP_META.length - 1, 0, { icon: 'fa-credit-card' });
    }

    var sidebarItems, horizontalItems, stepPanels, progressFill, progressBar,
        btnBack, btnNext, btnSubmit, stepTitle, stepSubtitle;

    // ============================================================
    //  Initialisation
    // ============================================================
    function init() {
        sidebarItems    = Array.from(document.querySelectorAll('.stepper-sidebar .step-item'));
        horizontalItems = Array.from(document.querySelectorAll('.stepper-horizontal .step-h-item'));
        stepPanels      = Array.from(document.querySelectorAll('.step-panel'));
        progressFill = document.querySelector('.stepper-progress-bar .progress-fill');
        progressBar  = document.querySelector('.stepper-progress-bar');
        btnBack      = document.querySelector('.btn-step-back');
        btnNext      = document.querySelector('.btn-step-next');
        btnSubmit    = document.querySelector('.btn-step-submit');
        stepTitle    = document.getElementById('stepper-step-title');
        stepSubtitle = document.getElementById('stepper-step-subtitle');

        if (!sidebarItems.length || !stepPanels.length) return;

        btnBack.addEventListener('click', function (e) { e.preventDefault(); goToStep(currentStep - 1); });
        btnNext.addEventListener('click', function (e) { e.preventDefault(); attemptNext(); });

        sidebarItems.forEach(function (item, idx) {
            item.addEventListener('click', function () {
                var stepNum = idx + 1;
                if (stepNum <= highestReached && stepNum !== currentStep) {
                    goToStep(stepNum);
                }
            });
        });

        horizontalItems.forEach(function (item, idx) {
            item.addEventListener('click', function () {
                var stepNum = idx + 1;
                if (stepNum <= highestReached && stepNum !== currentStep) {
                    goToStep(stepNum);
                }
            });
        });

        goToStep(1);

        // --- Toggle Card Logic ---
        // Synchronise card 'active' class when the checkbox changes
        $(document).on('change', '.stepper-toggle-card input[type="checkbox"].custom-control-input', function () {
            $(this).closest('.stepper-toggle-card').toggleClass('active', this.checked);
        });

        // Make entire card clickable
        $(document).on('click', '.stepper-toggle-card', function (e) {
            // If click was on the switch or its label, let it propagate naturally
            if ($(e.target).closest('.custom-control').length) return;
            var $cb = $(this).find('input[type="checkbox"].custom-control-input');
            if ($cb.length) {
                $cb.prop('checked', !$cb.prop('checked')).trigger('change');
            }
        });
    }

    // ============================================================
    //  Navigation
    // ============================================================
    function goToStep(step) {
        if (step < 1 || step > TOTAL_STEPS) return;

        currentStep = step;
        if (step > highestReached) highestReached = step;

        // Toggle panels
        stepPanels.forEach(function (panel, idx) {
            panel.classList.toggle('active', idx + 1 === step);
        });

        // Update sidebar
        sidebarItems.forEach(function (item, idx) {
            var s = idx + 1;
            var iconEl = item.querySelector('.step-icon i');
            item.classList.remove('active', 'completed', 'disabled');

            if (s === step) {
                item.classList.add('active');
                if (iconEl) iconEl.className = 'fas ' + STEP_META[s].icon;
            } else if (s <= highestReached || s < step) {
                item.classList.add('completed');
                if (iconEl) iconEl.className = 'fas fa-check';
            } else {
                item.classList.add('disabled');
                if (iconEl) iconEl.className = 'fas ' + STEP_META[s].icon;
            }
        });

        // Update horizontal stepper (mobile)
        horizontalItems.forEach(function (item, idx) {
            var s = idx + 1;
            var iconEl = item.querySelector('.step-h-icon i');
            item.classList.remove('active', 'completed', 'disabled');

            if (s === step) {
                item.classList.add('active');
                if (iconEl) iconEl.className = 'fas ' + STEP_META[s].icon;
            } else if (s <= highestReached || s < step) {
                item.classList.add('completed');
                if (iconEl) iconEl.className = 'fas fa-check';
            } else {
                item.classList.add('disabled');
                if (iconEl) iconEl.className = 'fas ' + STEP_META[s].icon;
            }
        });

        // Scroll active horizontal step into view on mobile
        var activeHItem = document.querySelector('.stepper-horizontal .step-h-item.active');
        if (activeHItem) {
            activeHItem.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
        }

        // Update progress bar
        var pct = Math.round((step / TOTAL_STEPS) * 100);
        progressFill.style.width = pct + '%';
        progressBar.classList.toggle('complete', step === TOTAL_STEPS);

        // Update footer buttons
        btnBack.disabled = (step === 1);
        btnBack.style.visibility = (step === 1) ? 'hidden' : 'visible';
        btnNext.style.display = (step === TOTAL_STEPS) ? 'none' : 'inline-flex';
        btnSubmit.style.display = (step === TOTAL_STEPS) ? 'inline-flex' : 'none';

        // Update step title/subtitle
        var subtitles = hasScheduling ? [
            null,
            getLabel('provider_identity_contact_account', 'Provider identity, contact details, and account setup'),
            getLabel('configure_business_charges', 'Configure business type, charges, and operational preferences'),
            getLabel('set_provider_location', 'Set the provider service location on the map'),
            getLabel('working_schedule_subtitle', 'Configure when your services are available to customers.'),
            getLabel('configure_scheduling_subtitle', 'Configure slot intervals, booking windows, and buffer rules.'),
            getLabel('configure_leaves_subtitle', 'Mark the shifts on which the provider will be on leave.'),
            getLabel('upload_images_documents', 'Upload profile images and required documents'),
            getLabel('enter_bank_account_details', 'Enter bank account and payment details'),
            getLabel('configure_seo_meta_tags', 'Configure SEO meta tags for better search visibility'),
            getLabel('verify_details_before_submitting', 'Verify all provider details before submitting')
        ] : [
            null,
            getLabel('provider_identity_contact_account', 'Provider identity, contact details, and account setup'),
            getLabel('configure_business_charges', 'Configure business type, charges, and operational preferences'),
            getLabel('set_provider_location', 'Set the provider service location on the map'),
            getLabel('working_schedule_subtitle', 'Configure when your services are available to customers.'),
            getLabel('upload_images_documents', 'Upload profile images and required documents'),
            getLabel('enter_bank_account_details', 'Enter bank account and payment details'),
            getLabel('configure_seo_meta_tags', 'Configure SEO meta tags for better search visibility'),
            getLabel('verify_details_before_submitting', 'Verify all provider details before submitting')
        ];
        var titles = hasScheduling ? [
            null,
            getLabel('basic_info', 'Basic Info'),
            getLabel('business_settings', 'Business Settings'),
            getLabel('location', 'Location'),
            getLabel('working_schedule', 'Working Schedule'),
            getLabel('scheduling_configuration', 'Scheduling Configuration'),
            getLabel('leaves', 'Leaves'),
            getLabel('media_and_docs', 'Media & Docs'),
            getLabel('bank_details', 'Bank Details'),
            getLabel('seo_settings', 'SEO Settings'),
            getLabel('review_and_submit', 'Review & Submit')
        ] : [
            null,
            getLabel('basic_info', 'Basic Info'),
            getLabel('business_settings', 'Business Settings'),
            getLabel('location', 'Location'),
            getLabel('working_schedule', 'Working Schedule'),
            getLabel('media_and_docs', 'Media & Docs'),
            getLabel('bank_details', 'Bank Details'),
            getLabel('seo_settings', 'SEO Settings'),
            getLabel('review_and_submit', 'Review & Submit')
        ];
        if (hasScheduling && !hasLeaves) {
            // Leaves entry only exists in the scheduling-layout arrays — drop it
            // so subsequent steps shift up. The non-scheduling arrays never had
            // a Leaves entry to begin with.
            subtitles.splice(6, 1);
            titles.splice(6, 1);
        }
        if (skipSeo) {
            // Drop the SEO subtitle/title so the review step shifts up by one.
            subtitles.splice((hasScheduling ? 8 : 7) + LEAVES_OFFSET, 1);
            titles.splice((hasScheduling ? 8 : 7) + LEAVES_OFFSET, 1);
        }
        if (skipWorkingHours) {
            // Drop the Working Hours subtitle/title; subsequent steps shift up.
            subtitles.splice(WORKING_HOURS_STEP_INDEX, 1);
            titles.splice(WORKING_HOURS_STEP_INDEX, 1);
        }
        if (hasSubscription) {
            // Insert the Subscription title/subtitle just before the Review step.
            titles.splice(titles.length - 1, 0, getLabel('subscription', 'Subscription'));
            subtitles.splice(subtitles.length - 1, 0, getLabel('manage_subscription_plan', 'Manage the provider subscription plan'));
        }

        if (stepTitle && titles[step]) stepTitle.textContent = titles[step];
        if (stepSubtitle && subtitles[step]) stepSubtitle.textContent = subtitles[step];

        // Show "Apply to all" button only on Working Hours step
        var applyBtn = document.getElementById('apply_to_all_days');
        if (applyBtn) {
            var showApply = !skipWorkingHours && step === WORKING_HOURS_STEP_INDEX;
            applyBtn.classList.toggle('d-none', !showApply);
            applyBtn.classList.toggle('d-flex', showApply);
        }

        // Show "Clear All Leaves" button only on Leaves step AND when a date range
        // has been selected. The inline leaves script owns the date-driven part of
        // this toggle; here we strictly hide on non-leaves steps so the button
        // does not leak across the stepper.
        var clearLeavesBtn = document.getElementById('clear_all_leaves_btn');
        if (clearLeavesBtn && hasLeaves) {
            var leavesStep = STEP_MEDIA - 1;
            if (step !== leavesStep) {
                clearLeavesBtn.classList.add('d-none');
                clearLeavesBtn.classList.remove('d-flex');
            } else {
                var fromInput = document.getElementById('leave_from_date');
                var toInput = document.getElementById('leave_to_date');
                var datesFilled = !!(fromInput && fromInput.value && toInput && toInput.value);
                clearLeavesBtn.classList.toggle('d-none', !datesFilled);
                clearLeavesBtn.classList.toggle('d-flex', datesFilled);
            }
        }

        // Build review content when entering the review step
        if (step === TOTAL_STEPS) {
            buildReview();
        }

        // Initialize or resize Google Maps when Location step becomes visible
        if (step === 3) {
            setTimeout(function () {
                // If map init was deferred because the container was hidden, do it now
                if (window._partnerMapPending && typeof initPartnerMap === 'function') {
                    window._partnerMapPending = false;
                    initPartnerMap();
                    if (typeof initPartnerAutocomplete === 'function') {
                        initPartnerAutocomplete();
                    }
                } else {
                    var mapObj = partnerMap || map;
                    if (mapObj && typeof MapProvider !== 'undefined') {
                        // Re-layout (and re-center on the current marker) after resize
                        MapProvider.resize(mapObj, marker);
                    }
                }
            }, 300);
        }

        // Scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function attemptNext() {
        if (validateCurrentStep()) {
            goToStep(currentStep + 1);
        }
    }

    // ============================================================
    //  Validation
    // ============================================================
    // ---- Inline error helpers (red text under the field, no toast) ----
    function fieldGroup(field) {
        return field.closest('.form-group') || field.parentElement;
    }

    function fieldLabelText(field) {
        var g = fieldGroup(field);
        var l = g && g.querySelector('label');
        var txt = l && l.textContent ? l.textContent.trim() : '';
        return txt || field.placeholder || field.name || getLabel('field', 'Field');
    }

    function clearStepErrors(panel) {
        panel.querySelectorAll('.stepper-field-error').forEach(function (e) { e.remove(); });
        panel.querySelectorAll('.form-group.has-error').forEach(function (g) { g.classList.remove('has-error'); });
    }

    // Render the message just below the offending control and auto-clear it
    // the moment the user edits the field again.
    function reportError(field, message, anchorEl) {
        var g = fieldGroup(field);
        if (!g) return;
        g.classList.add('has-error');
        if (!g.querySelector('.stepper-field-error')) {
            var div = document.createElement('div');
            div.className = 'stepper-field-error';
            div.textContent = message;
            var anchor = anchorEl
                || field.closest('.position-relative')
                || field.closest('.input-group')
                || field;
            if (anchor && anchor.parentNode) {
                anchor.insertAdjacentElement('afterend', div);
            } else {
                g.appendChild(div);
            }
        }
        var clear = function () {
            g.classList.remove('has-error');
            var e = g.querySelector('.stepper-field-error');
            if (e) e.remove();
        };
        // Namespaced so a re-validation rebinds cleanly instead of stacking handlers.
        $(field).off('.stepperVal').on('input.stepperVal change.stepperVal', clear);
    }

    // select2 hides the native <select>; resolve visibility/anchor via its container.
    function isSelect2(el) {
        return el.tagName === 'SELECT'
            && (el.classList.contains('select2') || el.classList.contains('select2-hidden-accessible'));
    }

    function select2Container(sel) {
        var g = sel.closest('.form-group');
        return g ? g.querySelector('.select2-container') : null;
    }

    function controlVisible(field) {
        if (isSelect2(field)) {
            var c = select2Container(field);
            return c ? isVisible(c) : isVisible(field);
        }
        return isVisible(field);
    }

    function findByLabelFor(group, htmlFor) {
        if (!htmlFor) return null;
        try {
            return group.querySelector('#' + (window.CSS && CSS.escape ? CSS.escape(htmlFor) : htmlFor));
        } catch (e) {
            return document.getElementById(htmlFor);
        }
    }

    function validateCurrentStep() {
        var panel = stepPanels[currentStep - 1];
        if (!panel) return true;

        clearStepErrors(panel);

        var firstInvalid = null;
        var processed = [];

        function fail(field, msg, anchor) {
            reportError(field, msg, anchor);
            if (!firstInvalid) firstInvalid = field;
        }

        // 1. Build the candidate list: every control explicitly marked `required`
        //    PLUS every control whose label carries the `.required` asterisk even
        //    if the input itself is missing the HTML `required` attribute. The red
        //    asterisk is the contract — if the user sees it, we enforce it.
        var controls = [];
        panel.querySelectorAll('input[required], select[required], textarea[required]').forEach(function (f) {
            controls.push(f);
        });
        panel.querySelectorAll('.form-group label.required').forEach(function (lbl) {
            var g = lbl.closest('.form-group');
            if (!g) return;
            var ctrl = findByLabelFor(g, lbl.htmlFor) || g.querySelector('input, select, textarea');
            if (ctrl && controls.indexOf(ctrl) === -1) controls.push(ctrl);
        });

        controls.forEach(function (field) {
            if (processed.indexOf(field) !== -1) return;
            processed.push(field);

            // Handled by their own dedicated passes below.
            if (field.classList.contains('filepond') || field.classList.contains('filepond-custom-field')) return;
            if (field.classList.contains('summernotes')) return;
            if (isSelect2(field)) return;
            if (field.type === 'checkbox' || field.type === 'radio' || field.type === 'hidden') return;
            if (!isVisible(field)) return;

            var label = fieldLabelText(field);
            var empty = field.value == null || String(field.value).trim() === '';
            if (empty) {
                fail(field, getLabel('validation_required', '{field} is required.').replace('{field}', label));
                return;
            }
            if (!field.checkValidity()) {
                fail(field, getValidationMessage(field, label));
                return;
            }
            // Password must satisfy the strength rules from Authentication Settings
            // before the user is allowed off this step (not just at final submit).
            if (field.id === 'password'
                && typeof window.passwordStrengthValid === 'function'
                && !window.passwordStrengthValid()) {
                var pwAnchor = document.getElementById('password-strength-indicator')
                    || field.closest('.position-relative')
                    || field;
                fail(field,
                    getLabel('password_requirements_not_met', 'Password does not meet the requirements. Please check the rules above.'),
                    pwAnchor);
            }
        });

        // 2. Summernote rich-text editors (original textarea is hidden by summernote)
        panel.querySelectorAll('textarea.summernotes[required]').forEach(function (sn) {
            var noteEditor = sn.closest('.form-group')?.querySelector('.note-editor');
            if (!noteEditor || !isVisible(noteEditor)) return;
            var $sn = $(sn);
            if ($sn.summernote && $sn.summernote('isEmpty')) {
                fail(sn,
                    getLabel('validation_required', '{field} is required.').replace('{field}', fieldLabelText(sn)),
                    noteEditor);
            }
        });

        // 3. FilePond required file inputs
        panel.querySelectorAll('input.filepond[required], input.filepond-custom-field[required]').forEach(function (fp) {
            if (!isVisible(fp)) return;
            var pondInstance = FilePond.find(fp);
            if (pondInstance && pondInstance.getFiles().length === 0) {
                var fg = fp.closest('.form-group');
                fail(fp,
                    getLabel('validation_required', '{field} is required.').replace('{field}', fieldLabelText(fp)),
                    (fg && fg.querySelector('.filepond--root')) || fp);
            }
        });

        // 4. Select2 required selects (validate via the visible container)
        panel.querySelectorAll('select[required]').forEach(function (sel) {
            if (!isSelect2(sel)) return;
            if (!controlVisible(sel)) return;
            var opt = sel.selectedOptions[0];
            if (!sel.value || sel.value === '' || (opt && opt.disabled)) {
                fail(sel,
                    getLabel('validation_required', '{field} is required.').replace('{field}', fieldLabelText(sel)),
                    select2Container(sel) || sel);
            }
        });

        if (firstInvalid) {
            var grp = fieldGroup(firstInvalid);
            (grp || firstInvalid).scrollIntoView({ behavior: 'smooth', block: 'center' });
            try {
                if (isVisible(firstInvalid)) firstInvalid.focus({ preventScroll: true });
            } catch (e) { /* hidden native select behind select2 — ignore */ }
            return false;
        }
        return true;
    }

    function isVisible(el) {
        while (el && el !== document.body) {
            if (el.style && el.style.display === 'none') return false;
            var cs = window.getComputedStyle(el);
            if (cs.display === 'none') return false;
            el = el.parentElement;
        }
        return true;
    }

    function getValidationMessage(field, fieldLabel) {
        var v = field.validity;
        if (v.valueMissing) {
            return getLabel('validation_required', '{field} is required.').replace('{field}', fieldLabel);
        }
        if (v.typeMismatch) {
            if (field.type === 'email') {
                return getLabel('validation_invalid_email', 'Please enter a valid email address.');
            }
            return getLabel('validation_invalid_value', 'Please enter a valid value for {field}.').replace('{field}', fieldLabel);
        }
        if (v.patternMismatch) {
            return field.title || getLabel('validation_invalid_format', 'Please enter a valid format for {field}.').replace('{field}', fieldLabel);
        }
        if (v.tooShort) {
            return getLabel('validation_too_short', '{field} must be at least {min} characters.')
                .replace('{field}', fieldLabel)
                .replace('{min}', field.minLength);
        }
        if (v.rangeUnderflow) {
            return getLabel('validation_min_value', '{field} must be at least {min}.')
                .replace('{field}', fieldLabel)
                .replace('{min}', field.min);
        }
        if (v.rangeOverflow) {
            return getLabel('validation_max_value', '{field} must be at most {max}.')
                .replace('{field}', fieldLabel)
                .replace('{max}', field.max);
        }
        if (v.stepMismatch) {
            return getLabel('validation_step_value', '{field} must be in steps of {step}.')
                .replace('{field}', fieldLabel)
                .replace('{step}', field.step);
        }
        return getLabel('validation_invalid_value', 'Please enter a valid value for {field}.').replace('{field}', fieldLabel);
    }

    // ============================================================
    //  Review Builder
    // ============================================================
    function buildReview() {
        var container = document.getElementById('stepper-review-content');
        if (!container) return;
        container.innerHTML = '';

        var sections = [
            { step: 1, title: getLabel('basic_info', 'Basic Info'), builder: buildBasicInfoReview },
            { step: 2, title: getLabel('business_settings', 'Business Settings'), builder: buildBusinessSettingsReview },
            { step: 3, title: getLabel('location', 'Location'), builder: buildLocationReview }
        ];
        if (!skipWorkingHours) {
            sections.push({ step: 4, title: getLabel('working_hours', 'Working Hours'), builder: buildWorkingHoursReview });
        }
        if (hasScheduling) {
            sections.push({ step: 5, title: getLabel('scheduling_configuration', 'Scheduling Configuration'), builder: buildSchedulingReview });
        }
        if (hasLeaves) {
            sections.push({ step: (hasScheduling ? 6 : 5), title: getLabel('leaves', 'Leaves'), builder: buildLeavesReview });
        }
        sections.push(
            { step: STEP_MEDIA, title: getLabel('media_and_docs', 'Media & Docs'), builder: buildMediaDocsReview },
            { step: STEP_BANK, title: getLabel('bank_details', 'Bank Details'), builder: buildBankDetailsReview }
        );
        if (!skipSeo) {
            sections.push({ step: STEP_SEO, title: getLabel('seo_settings', 'SEO Settings'), builder: buildSeoReview });
        }

        // Subscription step is excluded from review — it is managed separately via assign/cancel actions

        sections.forEach(function (sec) {
            var fields = sec.builder();
            if (!fields || fields.length === 0) return;
            var accordion = createAccordion(sec.title, sec.step, fields);
            container.appendChild(accordion);
        });

        container.addEventListener('click', function (e) {
            var readMore = e.target.closest('.review-read-more');
            if (!readMore) return;
            e.preventDefault();
            openDescModal(readMore.dataset.full);
        });
    }

    function openDescModal(encoded) {
        var existing = document.getElementById('review-desc-modal');
        if (existing) existing.remove();

        var rawHtml = new TextDecoder().decode(Uint8Array.from(atob(encoded), function (c) { return c.charCodeAt(0); }));

        var modal = document.createElement('div');
        modal.id = 'review-desc-modal';
        modal.style.cssText = 'position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.5);padding:16px;';

        var body = document.createElement('div');
        body.style.cssText = 'background:#fff;border-radius:8px;max-width:750px;width:100%;max-height:80vh;display:flex;flex-direction:column;box-shadow:0 8px 32px rgba(0,0,0,0.2);';
        body.innerHTML =
            '<div style="display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid #e5e7eb;flex-shrink:0;">' +
            '<span style="font-weight:600;font-size:15px;">' + getLabel('description', 'Description') + '</span>' +
            '<button type="button" id="review-desc-modal-close" style="background:none;border:none;cursor:pointer;font-size:20px;line-height:1;color:#6b7280;padding:4px;" aria-label="Close">&times;</button>' +
            '</div>';

        var content = document.createElement('div');
        content.style.cssText = 'padding:20px;overflow-y:auto;font-size:14px;line-height:1.6;color:#374151;';
        content.innerHTML = rawHtml;

        body.appendChild(content);
        modal.appendChild(body);
        document.body.appendChild(modal);

        function closeModal() { modal.remove(); }
        modal.querySelector('#review-desc-modal-close').addEventListener('click', closeModal);
        modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
        document.addEventListener('keydown', function onKey(e) {
            if (e.key === 'Escape') { closeModal(); document.removeEventListener('keydown', onKey); }
        });
    }

    function createAccordion(title, stepNum, fieldsHtml) {
        var wrapper = document.createElement('div');
        wrapper.className = 'review-accordion open';

        wrapper.innerHTML =
            '<div class="review-accordion-header">' +
                '<div class="review-header-left">' +
                    '<div class="review-step-icon"><i class="fas fa-check"></i></div>' +
                    '<span>' + escapeHtml(title) + '</span>' +
                '</div>' +
                '<div class="review-actions">' +
                    '<button type="button" class="review-edit-link" data-goto-step="' + stepNum + '">' +
                        '<i class="fas fa-pen" style="font-size:11px;margin-right:4px;"></i> ' +
                        getLabel('edit', 'Edit') +
                    '</button>' +
                    '<i class="fas fa-chevron-down review-chevron"></i>' +
                '</div>' +
            '</div>' +
            '<div class="review-accordion-body">' + fieldsHtml + '</div>';

        var header = wrapper.querySelector('.review-accordion-header');
        header.addEventListener('click', function (e) {
            if (e.target.closest('.review-edit-link')) return;
            wrapper.classList.toggle('open');
        });

        var editBtn = wrapper.querySelector('.review-edit-link');
        editBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            goToStep(stepNum);
        });

        return wrapper;
    }

    function reviewField(label, value) {
        if (!value || (typeof value === 'string' && value.trim() === '')) return '';
        return '<div class="review-field">' +
            '<span class="review-field-label">' + escapeHtml(label) + '</span>' +
            '<span class="review-field-value">' + value + '</span>' +
        '</div>';
    }

    function toggleBadge(isChecked) {
        return isChecked
            ? '<span class="review-badge-enabled">' + getLabel('enabled', 'Enabled') + '</span>'
            : '<span class="review-badge-disabled">' + getLabel('disabled_label', 'Disabled') + '</span>';
    }

    function fileBadge(filename) {
        var ext = filename.split('.').pop().toLowerCase();
        var icon = (ext === 'pdf') ? 'fa-file-pdf' : 'fa-image';
        return '<span class="review-file-badge"><i class="fas ' + icon + '"></i> ' + escapeHtml(filename) + '</span>';
    }

    // ---- Section Builders ----

    function buildBasicInfoReview() {
        var html = '';
        var defaultLangCode = getLabel('defaultLanguageCode', '');
        var defaultLang = defaultLangCode
            ? document.getElementById('translationDiv-' + defaultLangCode)
            : document.querySelector('[id^="translationDiv-"][style*="display: block"]');
        if (defaultLang) {
            var nameInput = defaultLang.querySelector('[name^="username["]');
            var companyInput = defaultLang.querySelector('[name^="company_name["]');
            var aboutInput = defaultLang.querySelector('[name^="about_provider["]') || defaultLang.querySelector('[name^="about["]');
            html += reviewField(getLabel('name', 'Name'), escapeHtml(val(nameInput)));
            html += reviewField(getLabel('company_name', 'Company Name'), escapeHtml(val(companyInput)));

            var aboutText = (val(aboutInput) || '').trim();
            if (aboutText) {
                var aboutHtml;
                if (aboutText.length > 200) {
                    var aboutEncoded = btoa(String.fromCharCode.apply(null, new TextEncoder().encode(escapeHtml(aboutText))));
                    aboutHtml = escapeHtml(aboutText.substring(0, 200)) + '… ' +
                        '<a href="#" class="review-read-more" data-full="' + aboutEncoded + '" style="font-size:12px;">' +
                        getLabel('read_more', 'Read more') + '</a>';
                } else {
                    aboutHtml = escapeHtml(aboutText);
                }
                html += reviewField(getLabel('about_provider', 'About Provider'), aboutHtml);
            }

            var longInput = defaultLang.querySelector('textarea.summernotes');
            if (longInput) {
                var longText = '';
                if (typeof tinymce !== 'undefined' && tinymce.get(longInput.id)) {
                    longText = tinymce.get(longInput.id).getContent();
                } else {
                    var noteEditor = longInput.closest('.form-group') ? longInput.closest('.form-group').querySelector('.note-editor') : null;
                    if (noteEditor && typeof $.fn.summernote !== 'undefined' && $(longInput).hasClass('summernote-initialized')) {
                        try { longText = $(longInput).summernote('code'); } catch (e) { longText = longInput.value; }
                    } else {
                        longText = longInput.value;
                    }
                }
                var rawHtml = (longText || '').trim();
                var stripped = rawHtml.replace(/<[^>]+>/g, '').trim();
                if (stripped) {
                    var descHtml;
                    if (stripped.length > 200) {
                        descHtml = escapeHtml(stripped.substring(0, 200)) + '… ' +
                            '<a href="#" class="review-read-more" data-full="' + btoa(String.fromCharCode.apply(null, new TextEncoder().encode(rawHtml))) + '" style="font-size:12px;">' +
                            getLabel('read_more', 'Read more') + '</a>';
                    } else {
                        descHtml = escapeHtml(stripped);
                    }
                    html += reviewField(getLabel('description', 'Description'), descHtml);
                }
            }
        }
        html += reviewField(getLabel('email', 'Email'), escapeHtml(val('#email')));
        html += reviewField(getLabel('phone_number', 'Phone'), escapeHtml(val('#country_code') + ' ' + val('#phone')));
        html += reviewField(getLabel('login_type', 'Login Type'), escapeHtml(selectedText('#login_type')));
        return html;
    }

    function buildBusinessSettingsReview() {
        var html = '';
        html += reviewField(getLabel('slug', 'Slug'), escapeHtml(val('#provider_slug')));
        html += reviewField(getLabel('type', 'Type'), escapeHtml(selectedText('#type')));
        html += reviewField(getLabel('visiting_charges', 'Visiting Charges'), escapeHtml(val('#visiting_charges')));
        if (!hasScheduling) {
            html += reviewField(getLabel('advance_booking_days', 'Advance Booking Days'), escapeHtml(val('#advance_booking_days')));
        }
        html += reviewField(getLabel('number_Of_members', 'Members'), escapeHtml(val('#number_of_members')));
        html += reviewField(getLabel('at_store', 'At Store'), toggleBadge(isChecked('#at_store')));
        html += reviewField(getLabel('at_doorstep', 'At Doorstep'), toggleBadge(isChecked('#at_doorstep')));

        var postChat = document.getElementById('post_chat');
        if (postChat) html += reviewField(getLabel('allow_post_booking_chat', 'Post-Booking Chat'), toggleBadge(postChat.checked));

        var preChat = document.getElementById('pre_chat');
        if (preChat) html += reviewField(getLabel('allow_pre_booking_chat', 'Pre-Booking Chat'), toggleBadge(preChat.checked));

        html += reviewField(getLabel('need_approval_for_the_service', 'Service Approval'), toggleBadge(isChecked('#need_approval_for_the_service')));
        return html;
    }

    function buildLocationReview() {
        var html = '';
        var locationRows = document.querySelectorAll('#provider-locations-list .provider-location-row');

        if (locationRows.length > 0 && document.getElementById('provider_locations_section')) {
            locationRows.forEach(function (row, index) {
                var field = function (suffix) {
                    var element = row.querySelector('[name$="[' + suffix + ']"]');
                    if (!element) return '';
                    return element.type === 'checkbox' ? (element.checked ? element.value : '') : element.value;
                };
                html += '<div class="review-location-block">';
                html += '<h6>' + escapeHtml(getLabel('location', 'Location') + ' ' + (index + 1)) + '</h6>';
                html += reviewField(getLabel('city', 'City'), escapeHtml(field('city')));
                html += reviewField(getLabel('address', 'Address'), escapeHtml(field('address')));
                html += reviewField(getLabel('latitude', 'Latitude'), escapeHtml(field('latitude')));
                html += reviewField(getLabel('longitude', 'Longitude'), escapeHtml(field('longitude')));
                html += reviewField(getLabel('default', 'Default'), field('is_default') ? getLabel('yes', 'Yes') : getLabel('no', 'No'));
                html += reviewField(getLabel('active', 'Active'), field('is_active') ? getLabel('yes', 'Yes') : getLabel('no', 'No'));
                html += '</div>';
            });
            return html;
        }

        html += reviewField(getLabel('city', 'City'), escapeHtml(val('[name="city"]')));
        html += reviewField(getLabel('address', 'Address'), escapeHtml(val('#address')));
        html += reviewField(getLabel('latitude', 'Latitude'), escapeHtml(val('#partner_latitude')));
        html += reviewField(getLabel('longitude', 'Longitude'), escapeHtml(val('#partner_longitude')));
        return html;
    }

    function buildWorkingHoursReview() {
        var html = '';
        var days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        var dayLabels = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        var dayHtml = '';
        var shiftLabel = getLabel('shift', 'Shift');
        var closedLabel = getLabel('closed', 'Closed');

        function readShifts(day) {
            var shifts = [];
            var $card = $('.schedule-day-card[data-day="' + day + '"]');
            if ($card.length) {
                $card.find('.day-time-row').each(function () {
                    var $inputs = $(this).find('input[type="time"]');
                    if ($inputs.length < 2) return;
                    var s = $inputs.eq(0).val();
                    var e = $inputs.eq(1).val();
                    if (s && e) shifts.push({ start: s, end: e });
                });
                return shifts;
            }
            // Fallback for edit mode (flat .start_time/.end_time per day index)
            var allStarts = document.querySelectorAll('.start_time');
            var allEnds = document.querySelectorAll('.end_time');
            var idx = days.indexOf(day);
            if (allStarts[idx] && allEnds[idx] && allStarts[idx].value && allEnds[idx].value) {
                shifts.push({ start: allStarts[idx].value, end: allEnds[idx].value });
            }
            return shifts;
        }

        days.forEach(function (day, idx) {
            var checkbox = document.querySelector('input[name="' + day + '"]');
            var isOn = checkbox ? checkbox.checked : false;

            dayHtml += '<div class="review-working-day">';
            dayHtml += '<span class="day-name">' + dayLabels[idx] + '</span>';

            if (isOn) {
                var shifts = readShifts(day);
                if (shifts.length) {
                    var shiftsHtml = shifts.map(function (sh, i) {
                        return '<span class="review-shift">' +
                            '<span class="review-shift-tag">' + escapeHtml(shiftLabel) + ' ' + (i + 1) + '</span>' +
                            '<span class="review-shift-time">' + escapeHtml(sh.start) + ' &ndash; ' + escapeHtml(sh.end) + '</span>' +
                            '</span>';
                    }).join('');
                    dayHtml += '<span class="review-shifts-list">' + shiftsHtml + '</span>';
                } else {
                    dayHtml += '<span class="day-closed">' + escapeHtml(closedLabel) + '</span>';
                }
            } else {
                dayHtml += '<span class="day-closed">' + escapeHtml(closedLabel) + '</span>';
            }
            dayHtml += '</div>';
        });

        html += '<div class="review-field" style="grid-template-columns:1fr;border-bottom:none;padding-bottom:4px;">' +
            '<span class="review-field-label">' + getLabel('working_days', 'Working Hours') + '</span></div>';
        html += '<div style="padding-left:4px;padding-bottom:4px;">' + dayHtml + '</div>';
        return html;
    }

    function buildSchedulingReview() {
        var html = '';
        html += reviewField(getLabel('slot_interval', 'Slot Interval'),
            escapeHtml(val('#slot_interval') + ' ' + getLabel('mins', 'mins')));
        var allowMulti = isChecked('#allow_multiple_bookings');
        html += reviewField(getLabel('allow_multiple_bookings', 'Allow Multiple Concurrent Bookings'),
            toggleBadge(allowMulti));
        if (allowMulti) {
            html += reviewField(getLabel('concurrent_booking_capacity', 'Concurrent Booking Capacity'),
                escapeHtml(val('#slot_capacity')));
        }
        var minAdv = val('#min_advance_booking');
        var minAdvUnit = selectedText('#min_advance_booking_unit');
        if (minAdv !== '') {
            html += reviewField(getLabel('minimum_advance_booking', 'Minimum Advance Booking'),
                escapeHtml(minAdv + ' ' + minAdvUnit));
        }
        html += reviewField(getLabel('maximum_future_booking_days', 'Maximum Future Booking Days'),
            escapeHtml(val('#advance_booking_days')));
        html += reviewField(getLabel('same_day_booking', 'Same Day Booking'),
            toggleBadge(isChecked('#same_day_booking')));
        html += reviewField(getLabel('buffer_before_booking', 'Buffer Before Booking'),
            escapeHtml(val('#buffer_before') + ' ' + getLabel('minutes', 'minutes')));
        html += reviewField(getLabel('buffer_after_booking', 'Buffer After Booking'),
            escapeHtml(val('#buffer_after') + ' ' + getLabel('minutes', 'minutes')));
        return html;
    }

    function buildLeavesReview() {
        var html = '';
        var fromDate = val('#leave_from_date');
        var toDate = val('#leave_to_date');
        if (fromDate) html += reviewField(getLabel('from_date', 'From Date'), escapeHtml(fromDate));
        if (toDate) html += reviewField(getLabel('to_date', 'To Date'), escapeHtml(toDate));

        var dayHtml = '';
        $('#leaves_days_container .leaves-day-card').each(function () {
            var $card = $(this);
            var label = $card.data('day-label') || $card.find('.leaves-day-label').text();
            var $all = $card.find('input[type="checkbox"].leave-shift-checkbox');
            var $checked = $all.filter(':checked');
            if (!$checked.length) return;

            var shiftText;
            // All shifts selected → collapse to "Full Day (firstStart - lastEnd)".
            // Shifts in the card are rendered in chronological order, so first/last
            // checkbox bracket the working window for that date.
            if ($all.length && $checked.length === $all.length) {
                var firstStart = $all.first().attr('data-shift-start') || '';
                var lastEnd = $all.last().attr('data-shift-end') || '';
                shiftText = getLabel('full_day', 'Full Day') + ' (' + firstStart + ' - ' + lastEnd + ')';
            } else {
                shiftText = $checked.map(function () {
                    return $(this).data('shift-label') || $(this).closest('label').text().trim();
                }).get().join(', ');
            }

            dayHtml += '<div class="review-working-day">' +
                '<span class="day-name">' + escapeHtml(label) + '</span>' +
                '<span class="review-shifts-list"><span class="review-shift">' +
                    '<span class="review-shift-time">' + escapeHtml(shiftText) + '</span>' +
                '</span></span>' +
                '</div>';
        });
        if (dayHtml) {
            html += '<div class="review-field" style="grid-template-columns:1fr;border-bottom:none;padding-bottom:4px;">' +
                '<span class="review-field-label">' + getLabel('leave_shifts', 'Leave Shifts') + '</span></div>';
            html += '<div style="padding-left:4px;padding-bottom:4px;">' + dayHtml + '</div>';
        } else {
            // No shifts checked AND no date range selected → provider has no leaves
            // scheduled. Surface that explicitly so the review section doesn't look
            // accidentally blank.
            html = reviewField(getLabel('leaves', 'Leaves'), escapeHtml(getLabel('no_leaves_scheduled', 'No leaves scheduled')));
        }
        return html;
    }

    function buildMediaDocsReview() {
        var html = '';
        var otherImgSelector = isEditMode ? '#other_service_image_selector_edit' : '#other_service_image_selector';
        var pondInputs = [
            { selector: '#image', label: getLabel('image', 'Profile Image') },
            { selector: '#banner_image', label: getLabel('banner_image', 'Banner Image') },
            { selector: otherImgSelector, label: getLabel('other_images', 'Other Images') }
        ];

        pondInputs.forEach(function (item) {
            var el = document.querySelector(item.selector);
            if (!el) return;
            var pond = FilePond.find(el);
            if (pond) {
                var files = pond.getFiles();
                if (files.length > 0) {
                    var badges = files.map(function (f) { return fileBadge(f.filename); }).join('');
                    html += reviewField(item.label, badges);
                }
            }
        });

        // Custom document fields (filepond)
        document.querySelectorAll('.step-panel[data-step="' + STEP_MEDIA + '"] .filepond-custom-field').forEach(function (el) {
            var pond = FilePond.find(el);
            if (pond && pond.getFiles().length > 0) {
                var label = el.closest('.form-group')?.querySelector('label')?.textContent?.trim() || el.name;
                var badges = pond.getFiles().map(function (f) { return fileBadge(f.filename); }).join('');
                html += reviewField(label, badges);
            }
        });

        // Custom document fields (non-file)
        var mediaSel = '.step-panel[data-step="' + STEP_MEDIA + '"]';
        document.querySelectorAll(mediaSel + ' input:not(.filepond):not(.filepond-custom-field):not([type="file"]):not([type="hidden"]), ' + mediaSel + ' textarea:not(.filepond), ' + mediaSel + ' select').forEach(function (el) {
            if (el.closest('.filepond--root')) return;
            var v = el.value?.trim();
            if (!v) return;
            var label = el.closest('.form-group')?.querySelector('label')?.textContent?.trim() || el.name;
            html += reviewField(label, escapeHtml(v));
        });

        return html;
    }

    function buildBankDetailsReview() {
        var html = '';

        // Bank detail custom fields
        var bankSel = '.step-panel[data-step="' + STEP_BANK + '"]';
        document.querySelectorAll(bankSel + ' .bank-details-section input, ' + bankSel + ' .bank-details-section textarea, ' + bankSel + ' .bank-details-section select').forEach(function (el) {
            if (el.closest('.filepond--root')) return;
            var v = el.value?.trim();
            if (!v) return;
            var label = el.closest('.form-group')?.querySelector('label')?.textContent?.trim() || el.name;
            html += reviewField(label, escapeHtml(v));
        });

        return html;
    }

    function buildSeoReview() {
        var html = '';

        // SEO — default language only
        var defaultLangCode = getLabel('defaultLanguageCode', '');
        var seoDiv = defaultLangCode
            ? document.getElementById('translationDivSeo-' + defaultLangCode)
            : document.querySelector('[id^="translationDivSeo-"][style*="display: block"]');
        if (seoDiv) {
            var metaTitle = seoDiv.querySelector('[name^="meta_title["]');
            var metaDesc = seoDiv.querySelector('[name^="meta_description["]');
            html += reviewField(getLabel('meta_title', 'Meta Title'), escapeHtml(val(metaTitle)));
            html += reviewField(getLabel('meta_description', 'Meta Description'), escapeHtml(val(metaDesc)));

            // Meta keywords (Tagify)
            var kwInput = seoDiv.querySelector('[name^="meta_keywords["]');
            if (kwInput && kwInput.value) {
                try {
                    var tags = JSON.parse(kwInput.value);
                    var kwText = tags.map(function (t) { return t.value; }).join(', ');
                    html += reviewField(getLabel('meta_keywords', 'Meta Keywords'), escapeHtml(kwText));
                } catch (e) {
                    html += reviewField(getLabel('meta_keywords', 'Meta Keywords'), escapeHtml(kwInput.value));
                }
            }
        }

        return html;
    }


    // ---- Subscription Review (edit mode only) ----
    function buildSubscriptionReview() {
        var html = '';
        var container = document.getElementById('subscription-active-info');
        if (container) {
            var planName = container.getAttribute('data-plan-name') || '';
            var planPrice = container.getAttribute('data-plan-price') || '';
            var planExpiry = container.getAttribute('data-plan-expiry') || '';
            var planDuration = container.getAttribute('data-plan-duration') || '';
            var planOrders = container.getAttribute('data-plan-orders') || '';

            if (planName) {
                html += reviewField(getLabel('subscription', 'Subscription'), escapeHtml(planName));
                html += reviewField(getLabel('price', 'Price'), escapeHtml(planPrice));
                if (planDuration === 'unlimited') {
                    html += reviewField(getLabel('duration', 'Duration'), getLabel('unlimited', 'Lifetime'));
                } else {
                    html += reviewField(getLabel('duration', 'Duration'), escapeHtml(planDuration) + ' ' + getLabel('days', 'Days'));
                }
                html += reviewField(getLabel('order_limit', 'Order Limit'), planOrders === 'unlimited' ? getLabel('unlimited', 'Unlimited') : escapeHtml(planOrders));
                if (planExpiry) {
                    html += reviewField(getLabel('expiry_date', 'Expiry Date'), escapeHtml(planExpiry));
                }
            } else {
                html += reviewField(getLabel('subscription', 'Subscription'), getLabel('no_active_subscription', 'No active subscription'));
            }
        }
        return html;
    }

    // ============================================================
    //  Helpers
    // ============================================================
    function val(selectorOrEl) {
        var el = (typeof selectorOrEl === 'string') ? document.querySelector(selectorOrEl) : selectorOrEl;
        return el ? (el.value || '').trim() : '';
    }

    function selectedText(selector) {
        var el = document.querySelector(selector);
        if (!el || el.selectedIndex < 0) return '';
        return el.options[el.selectedIndex].text || '';
    }

    function isChecked(selector) {
        var el = document.querySelector(selector);
        return el ? el.checked : false;
    }

    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function getLabel(key, fallback) {
        if (typeof window.stepperLabels !== 'undefined' && window.stepperLabels[key]) {
            return window.stepperLabels[key];
        }
        return fallback;
    }

    // ============================================================
    //  Lat/Lng normalisation — always exactly 7 decimal places
    // ============================================================
    function formatCoord(value) {
        var num = parseFloat(value);
        if (isNaN(num)) return value;
        return num.toFixed(7);
    }

    function normalizeLatLngFields() {
        var latEl = document.getElementById('partner_latitude');
        var lngEl = document.getElementById('partner_longitude');
        if (latEl && latEl.value.trim() !== '') latEl.value = formatCoord(latEl.value);
        if (lngEl && lngEl.value.trim() !== '') lngEl.value = formatCoord(lngEl.value);
    }

    // Normalize on blur so the user sees the formatted value immediately
    document.addEventListener('DOMContentLoaded', function () {
        ['partner_latitude', 'partner_longitude'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) {
                el.addEventListener('blur', function () {
                    if (this.value.trim() !== '') this.value = formatCoord(this.value);
                });
            }
        });
    });

    // Normalize just before form submission to guarantee 7 decimal places
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (form.id === 'add_partner' || form.id === 'edit_partner') {
            normalizeLatLngFields();
        }
    }, true);

    // Expose for scripts.js map interactions
    window.formatCoord = formatCoord;

    // ---- Bootstrap ----
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

// ============================================================
//  Working Hours (Step 4): toggle, apply-to-all, add break/shift
// ============================================================
$(function () {
    var $grid = $('#working_days_grid');
    if (!$grid.length) return;

    function label(key, fallback) {
        return (window.stepperLabels && window.stepperLabels[key]) ? window.stepperLabels[key] : fallback;
    }

    function refreshCardState($card) {
        $card.toggleClass('is-off', !$card.find('.day-toggle').is(':checked'));
    }

    function refreshApplyToAllState() {
        var anyOn = $grid.find('.day-toggle:checked').length > 0;
        var hasTimeError = $grid.find('.day-time-row.invalid').length > 0;
        $('#apply_to_all_days').prop('disabled', !anyOn || hasTimeError).toggleClass('disabled', !anyOn || hasTimeError);
    }

    $grid.find('.schedule-day-card').each(function () { refreshCardState($(this)); });
    refreshApplyToAllState();

    $grid.on('change', '.day-toggle', function () {
        refreshCardState($(this).closest('.schedule-day-card'));
        refreshApplyToAllState();
    });

    $grid.on('click', '.schedule-day-card', function (e) {
        if ($(e.target).closest('button, label, .custom-control, input').length) return;
        $grid.find('.schedule-day-card').removeClass('border-primary border-2 active');
        $(this).addClass('border-primary border-2 active');
    });

    $(document).on('change', '.start_time', function () {
        var v = $(this).val();
        $(this).closest('.day-time-row').find('.end_time').attr('min', v);
    });

    function validateShiftRow($row) {
        var $inputs = $row.find('input[type="time"]');
        if ($inputs.length < 2) return;
        var start = $inputs.eq(0).val();
        var end = $inputs.eq(1).val();
        var invalid = !!(start && end && end <= start);
        $row.toggleClass('invalid', invalid);
        $row.next('.shift-time-hint').remove();
        if (invalid) {
            $row.after('<small class="shift-time-hint shift-error-hint">' + label('shift_end_after_start', 'End time must be after start time') + '</small>');
        }
    }

    function validateCardOverlaps($card) {
        $card.find('.shift-overlap-hint').remove();
        $card.find('.day-time-row.overlap-invalid').removeClass('overlap-invalid invalid');

        var dayName = $card.find('.schedule-day-name').text().trim() || $card.data('day') || '';

        // Collect all rows with 1-based display numbers; preserve position for invalid rows
        var allShifts = [];
        $card.find('.day-time-row').each(function (idx) {
            var $row = $(this);
            var $inputs = $row.find('input[type="time"]');
            if ($inputs.length < 2) return;
            allShifts.push({
                $row:         $row,
                start:        $inputs.eq(0).val(),
                end:          $inputs.eq(1).val(),
                num:          idx + 1,
                hasTimeError: $row.hasClass('invalid')
            });
        });

        // Only check rows with both times set and no end<=start error
        var valid = allShifts.filter(function (sh) {
            return sh.start && sh.end && !sh.hasTimeError;
        });

        // Build map: valid-array index → display nums of conflicting shifts
        var overlapsWith = {};
        for (var i = 0; i < valid.length; i++) {
            for (var j = i + 1; j < valid.length; j++) {
                if (valid[i].start < valid[j].end && valid[j].start < valid[i].end) {
                    if (!overlapsWith[i]) overlapsWith[i] = [];
                    if (!overlapsWith[j]) overlapsWith[j] = [];
                    overlapsWith[i].push(valid[j].num);
                    overlapsWith[j].push(valid[i].num);
                }
            }
        }

        var msgTemplate = label('shift_overlap', 'Shift {shift1} overlaps with Shift {shift2} on {day}');

        for (var k in overlapsWith) {
            var sh = valid[k];
            sh.$row.addClass('invalid overlap-invalid');
            var msg = msgTemplate
                .replace('{shift1}', sh.num)
                .replace('{shift2}', overlapsWith[k].join(', '))
                .replace('{day}', dayName);
            sh.$row.after('<small class="shift-overlap-hint shift-error-hint">' + msg + '</small>');
        }
    }

    function updateWorkingHoursFormButtons() {
        var hasError = $grid.find('.day-time-row.invalid').length > 0;
        $('#partner_working_hours_form .submit_btn').prop('disabled', hasError);
        $('#apply_to_all_days').prop('disabled', hasError || $grid.find('.day-toggle:checked').length === 0);
    }

    $grid.on('change', 'input[type="time"]', function () {
        var $card = $(this).closest('.schedule-day-card');
        validateShiftRow($(this).closest('.day-time-row'));
        validateCardOverlaps($card);
        updateWorkingHoursFormButtons();
    });

    $(document).on('click', '#apply_to_all_days', function () {
        if ($(this).prop('disabled')) return;

        var $source = $grid.find('.schedule-day-card.active').first();
        if (!$source.length || !$source.find('.day-toggle').is(':checked')) {
            $source = $grid.find('.schedule-day-card').filter(function () {
                return $(this).find('.day-toggle').is(':checked');
            }).first();
        }
        if (!$source.length) return;

        var startVal = $source.find('.start_time').first().val();
        var endVal = $source.find('.end_time').first().val();
        var extraShifts = [];
        $source.find('.extra-shifts .day-time-row').each(function () {
            var $inputs = $(this).find('input[type="time"]');
            extraShifts.push({ start: $inputs.eq(0).val(), end: $inputs.eq(1).val() });
        });

        $grid.find('.schedule-day-card').each(function () {
            var $card = $(this);
            if ($card.is($source)) return;
            var day = $card.data('day');
            $card.find('.day-toggle').prop('checked', true);
            $card.find('.start_time').first().val(startVal);
            $card.find('.end_time').first().val(endVal).attr('min', startVal);
            var $extras = $card.find('.extra-shifts').empty();
            extraShifts.forEach(function (sh) {
                $extras.append(buildExtraShiftRow(day, sh.start, sh.end));
            });
            refreshCardState($card);
        });
        refreshApplyToAllState();
    });

    function addHour(timeStr) {
        var parts = (timeStr || '00:00').split(':');
        var h = parseInt(parts[0], 10);
        var m = parseInt(parts[1], 10) || 0;
        if (isNaN(h)) h = 0;
        h = (h + 1) % 24;
        return (h < 10 ? '0' + h : h) + ':' + (m < 10 ? '0' + m : m);
    }

    function buildExtraShiftRow(day, startVal, endVal) {
        var startName = 'extra_shifts[' + day + '][start][]';
        var endName = 'extra_shifts[' + day + '][end][]';
        return $(
            '<div class="day-time-row d-flex align-items-center extra-shift-row">' +
                '<div class="flex-fill">' +
                    '<input type="time" class="form-control form-control-sm extra_start_time" name="' + startName + '" value="' + startVal + '">' +
                '</div>' +
                '<span class="text-muted mx-2 font-weight-bold">—</span>' +
                '<div class="flex-fill">' +
                    '<input type="time" class="form-control form-control-sm extra_end_time" name="' + endName + '" value="' + endVal + '">' +
                '</div>' +
                '<button type="button" class="btn btn-sm btn-remove-shift ml-2" aria-label="Remove shift">' +
                    '<i class="far fa-trash-alt"></i>' +
                '</button>' +
            '</div>'
        );
    }

    $grid.on('click', '.btn-add-shift', function () {
        var $card = $(this).closest('.schedule-day-card');
        var day = $card.data('day');
        var $lastEnd = $card.find('.day-time-row').last().find('input[type="time"]').last();
        var startVal = $lastEnd.val() || '10:00';
        var endVal = addHour(startVal);
        $card.find('.extra-shifts').append(buildExtraShiftRow(day, startVal, endVal));
    });

    $grid.on('click', '.btn-remove-shift', function () {
        var $card = $(this).closest('.schedule-day-card');
        var $row = $(this).closest('.extra-shift-row');
        $row.next('.shift-error-hint').remove();
        $row.remove();
        validateCardOverlaps($card);
        updateWorkingHoursFormButtons();
    });
});
