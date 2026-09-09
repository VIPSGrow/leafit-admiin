/**
 * Service Stepper Form Controller
 *
 * Manages step navigation, per-step validation, sidebar state,
 * progress bar, and review step rendering for service_form.php
 * (add / edit / clone service in the admin panel).
 *
 * window.stepperMode = 'edit'  -> all sidebar items unlocked from the start.
 * window.stepperMode = 'add'   -> sequential walk; later steps unlock as the
 *                                 user passes each preceding step.
 *
 * Both 'edit' and 'clone' use 'edit' mode (controller-side) since clone
 * loads existing data the user just needs to review and tweak.
 */
(function () {
    'use strict';

    var TOTAL_STEPS = 8;
    var STEP_REVIEW = 8;
    var isEditMode = (window.stepperMode === 'edit');
    var currentStep = 1;
    var highestReached = isEditMode ? TOTAL_STEPS : 1;

    var STEP_META = [
        null,
        { icon: 'fa-info-circle', titleKey: 'basic_info', title: 'Basic Info', subtitleKey: 'basic_info_subtitle', subtitle: 'Service title, tags, and description per language' },
        { icon: 'fa-tools', titleKey: 'service_details', title: 'Service Details', subtitleKey: 'service_details_subtitle', subtitle: 'Provider, category and task configuration' },
        { icon: 'fa-images', titleKey: 'media_and_files', title: 'Media & Files', subtitleKey: 'media_and_files_subtitle', subtitle: 'Service image, gallery and supporting documents' },
        { icon: 'fa-dollar-sign', titleKey: 'price_details', title: 'Price Details', subtitleKey: 'price_details_subtitle', subtitle: 'Pricing and tax configuration' },
        { icon: 'fa-question-circle', titleKey: 'faqs', title: 'FAQs', subtitleKey: 'faqs_subtitle', subtitle: 'Common questions and answers per language' },
        { icon: 'fa-magnifying-glass', titleKey: 'seo_settings', title: 'SEO Settings', subtitleKey: 'seo_settings_subtitle', subtitle: 'Search engine optimization meta tags' },
        { icon: 'fa-sliders-h', titleKey: 'service_option', title: 'Service Options', subtitleKey: 'service_options_subtitle', subtitle: 'Booking rules and visibility toggles' },
        { icon: 'fa-check-circle', titleKey: 'review_step', title: 'Review', subtitleKey: 'review_subtitle', subtitle: 'Verify the service details before submitting' }
    ];

    var sidebarItems, horizontalItems, stepPanels, progressFill, progressBar,
        btnBack, btnNext, btnSubmit, stepTitle, stepSubtitle;

    // ============================================================
    //  Initialisation
    // ============================================================
    function init() {
        sidebarItems = Array.from(document.querySelectorAll('.stepper-sidebar .step-item'));
        horizontalItems = Array.from(document.querySelectorAll('.stepper-horizontal .step-h-item'));
        stepPanels = Array.from(document.querySelectorAll('.step-panel'));
        progressFill = document.querySelector('.stepper-progress-bar .progress-fill');
        progressBar = document.querySelector('.stepper-progress-bar');
        btnBack = document.querySelector('.btn-step-back');
        btnNext = document.querySelector('.btn-step-next');
        btnSubmit = document.querySelector('.btn-step-submit');
        stepTitle = document.getElementById('stepper-step-title');
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

        // Stepper toggle cards: card "active" class mirrors the checkbox.
        $(document).on('change', '.stepper-toggle-card input[type="checkbox"].custom-control-input', function () {
            $(this).closest('.stepper-toggle-card').toggleClass('active', this.checked);
        });
        $(document).on('click', '.stepper-toggle-card', function (e) {
            if ($(e.target).closest('.custom-control').length) return;
            var $cb = $(this).find('input[type="checkbox"].custom-control-input');
            if ($cb.length) $cb.prop('checked', !$cb.prop('checked')).trigger('change');
        });

        var membersField = document.getElementById('members');
        if (membersField) {
            membersField.addEventListener('blur', function () {
                var g = fieldGroup(this);
                if (g) {
                    g.classList.remove('has-error');
                    var existing = g.querySelector('.stepper-field-error');
                    if (existing) existing.remove();
                }
                if (!this.validity.valid) {
                    reportError(this, getValidationMessage(this, fieldLabelText(this)));
                }
            });
        }

        goToStep(1);
    }

    // ============================================================
    //  Navigation
    // ============================================================
    function goToStep(step) {
        if (step < 1 || step > TOTAL_STEPS) return;

        currentStep = step;
        if (step > highestReached) highestReached = step;

        stepPanels.forEach(function (panel, idx) {
            panel.classList.toggle('active', idx + 1 === step);
        });

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

        var activeHItem = document.querySelector('.stepper-horizontal .step-h-item.active');
        if (activeHItem) activeHItem.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });

        var pct = Math.round((step / TOTAL_STEPS) * 100);
        if (progressFill) progressFill.style.width = pct + '%';
        if (progressBar) progressBar.classList.toggle('complete', step === TOTAL_STEPS);

        if (btnBack) {
            btnBack.disabled = (step === 1);
            btnBack.style.visibility = (step === 1) ? 'hidden' : 'visible';
        }
        if (btnNext) btnNext.style.display = (step === TOTAL_STEPS) ? 'none' : 'inline-flex';
        if (btnSubmit) btnSubmit.style.display = (step === TOTAL_STEPS) ? 'inline-flex' : 'none';

        if (stepTitle) stepTitle.textContent = getLabel(STEP_META[step].titleKey, STEP_META[step].title);
        if (stepSubtitle) stepSubtitle.textContent = getLabel(STEP_META[step].subtitleKey, STEP_META[step].subtitle);

        if (step === STEP_REVIEW) buildReview();

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function attemptNext() {
        if (validateCurrentStep()) goToStep(currentStep + 1);
    }

    // ============================================================
    //  Validation
    // ============================================================
    function fieldGroup(field) { return field.closest('.form-group') || field.parentElement; }

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
            if (anchor && anchor.parentNode) anchor.insertAdjacentElement('afterend', div);
            else g.appendChild(div);
        }
        var clear = function () {
            g.classList.remove('has-error');
            var e = g.querySelector('.stepper-field-error');
            if (e) e.remove();
        };
        $(field).off('.stepperVal').on('input.stepperVal change.stepperVal', clear);
    }

    function isSelect2(el) {
        return el.tagName === 'SELECT'
            && (el.classList.contains('select2') || el.classList.contains('select2-hidden-accessible'));
    }
    function select2Container(sel) {
        var g = sel.closest('.form-group');
        return g ? g.querySelector('.select2-container') : null;
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
    function controlVisible(field) {
        if (isSelect2(field)) {
            var c = select2Container(field);
            return c ? isVisible(c) : isVisible(field);
        }
        return isVisible(field);
    }
    function findByLabelFor(group, htmlFor) {
        if (!htmlFor) return null;
        return group.querySelector('#' + CSS.escape(htmlFor));
    }

    function getValidationMessage(field, fieldLabel) {
        var v = field.validity;
        if (v.valueMissing) return getLabel('validation_required', '{field} is required.').replace('{field}', fieldLabel);
        if (v.typeMismatch) return getLabel('validation_invalid_value', 'Please enter a valid value for {field}.').replace('{field}', fieldLabel);
        if (v.patternMismatch) return field.title || getLabel('validation_invalid_format', 'Please enter a valid format for {field}.').replace('{field}', fieldLabel);
        if (v.rangeUnderflow) return getLabel('validation_min_value', '{field} must be at least {min}.').replace('{field}', fieldLabel).replace('{min}', field.min);
        if (v.rangeOverflow) {
            var customMaxMsg = field.getAttribute('data-max-message');
            if (customMaxMsg) return customMaxMsg;
            return getLabel('validation_max_value', '{field} must be at most {max}.').replace('{field}', fieldLabel).replace('{max}', field.max);
        }
        return getLabel('validation_invalid_value', 'Please enter a valid value for {field}.').replace('{field}', fieldLabel);
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
            if (field.classList.contains('filepond')) return;
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
            }
        });

        // Rich-text editors (TinyMCE / Summernote)
        panel.querySelectorAll('textarea.summernotes[required]').forEach(function (sn) {
            var isEmpty = false;
            var anchor = sn;
            if (typeof tinymce !== 'undefined' && tinymce.get(sn.id)) {
                var editor = tinymce.get(sn.id);
                var content = editor.getContent({ format: 'text' }).trim();
                isEmpty = (content === '');
                anchor = editor.getContainer() || sn;
            } else {
                var noteEditor = sn.closest('.form-group') ? sn.closest('.form-group').querySelector('.note-editor') : null;
                if (noteEditor && isVisible(noteEditor)) {
                    var $sn = $(sn);
                    if ($sn.summernote && $sn.summernote('isEmpty')) {
                        isEmpty = true;
                        anchor = noteEditor;
                    }
                } else {
                    isEmpty = (sn.value.trim() === '');
                }
            }
            if (isEmpty) {
                fail(sn,
                    getLabel('validation_required', '{field} is required.').replace('{field}', fieldLabelText(sn)),
                    anchor);
            }
        });

        // FilePond required (service image on add mode)
        panel.querySelectorAll('input.filepond[required]').forEach(function (fp) {
            if (!isVisible(fp)) return;
            if (typeof FilePond === 'undefined') return;
            var pond = FilePond.find(fp);
            if (pond && pond.getFiles().length === 0) {
                var fg = fp.closest('.form-group');
                fail(fp,
                    getLabel('validation_required', '{field} is required.').replace('{field}', fieldLabelText(fp)),
                    (fg && fg.querySelector('.filepond--root')) || fp);
            }
        });

        // Select2 required
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

        // Step 4: discounted_price < price
        if (currentStep === 4) {
            var price = parseFloat($('#price').val());
            var discounted = parseFloat($('#discounted_price').val());
            if (!isNaN(price) && !isNaN(discounted) && discounted >= price) {
                fail(document.getElementById('discounted_price'),
                    getLabel('discounted_price_less_than_price', 'Discounted price must be less than price.'));
            }
        }

        if (firstInvalid) {
            var grp = fieldGroup(firstInvalid);
            (grp || firstInvalid).scrollIntoView({ behavior: 'smooth', block: 'center' });
            try {
                if (isVisible(firstInvalid)) firstInvalid.focus({ preventScroll: true });
            } catch (e) { /* select2 hides native — ignore */ }
            return false;
        }
        return true;
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
            { step: 2, title: getLabel('service_details', 'Service Details'), builder: buildServiceDetailsReview },
            { step: 3, title: getLabel('media_and_files', 'Media & Files'), builder: buildMediaReview },
            { step: 4, title: getLabel('price_details', 'Price Details'), builder: buildPriceReview },
            { step: 5, title: getLabel('faqs', 'FAQs'), builder: buildFaqsReview },
            { step: 6, title: getLabel('seo_settings', 'SEO Settings'), builder: buildSeoReview },
            { step: 7, title: getLabel('service_option', 'Service Options'), builder: buildOptionsReview }
        ];

        sections.forEach(function (sec) {
            var html = sec.builder();
            if (!html || html.trim() === '') return;
            container.appendChild(createAccordion(sec.title, sec.step, html));
        });

        container.addEventListener('click', function (e) {
            var readMore = e.target.closest('.review-read-more');
            if (!readMore) return;
            e.preventDefault();
            openDescModal(readMore.dataset.full);
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
        if (value === null || value === undefined) return '';
        if (typeof value === 'string' && value.trim() === '') return '';
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

    // ---- Section Builders ----

    function buildBasicInfoReview() {
        var defaultLangCode = getLabel('defaultLanguageCode', '');
        var defaultLang = defaultLangCode
            ? document.getElementById('translationDiv-' + defaultLangCode)
            : document.querySelector('[id^="translationDiv-"][style*="display: block"]');
        var html = '';
        if (defaultLang) {
            var titleInput = defaultLang.querySelector('[name^="title["]');
            var tagsInput = defaultLang.querySelector('[name^="tags["]');
            var descInput = defaultLang.querySelector('[name^="description["]');
            html += reviewField(getLabel('title', 'Title'), escapeHtml(val(titleInput)));
            var tagsVal = val(tagsInput);
            var parsedTags = '';
            try {
                var parsed = JSON.parse(tagsVal);
                if (Array.isArray(parsed)) {
                    parsedTags = parsed.map(function (t) { return t.value; }).join(', ');
                } else {
                    parsedTags = tagsVal;
                }
            } catch (e) {
                parsedTags = tagsVal;
            }
            html += reviewField(getLabel('tags', 'Tags'), escapeHtml(parsedTags));
            html += reviewField(getLabel('short_description', 'Short Description'), escapeHtml(val(descInput)));

            // Rich-text editor: pull text content
            var longInput = defaultLang.querySelector('textarea.summernotes');
            if (longInput) {
                var longText = '';
                if (typeof tinymce !== 'undefined' && tinymce.get(longInput.id)) {
                    longText = tinymce.get(longInput.id).getContent();
                } else {
                    var noteEditor = longInput.closest('.form-group') ? longInput.closest('.form-group').querySelector('.note-editor') : null;
                    if (noteEditor && typeof $.fn.summernote !== 'undefined' && $(longInput).hasClass('summernote-initialized')) {
                        try {
                            longText = $(longInput).summernote('code');
                        } catch (e) {
                            longText = longInput.value;
                        }
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
        return html;
    }

    function buildServiceDetailsReview() {
        var html = '';
        html += reviewField(getLabel('select_provider', 'Provider'), escapeHtml(selectedText('#partner')));
        var categoryText = selectedText('#category_item');
        // render_categories_options() prefixes nested options with non-breaking
        // spaces + em-dash for indentation; strip those for the review summary.
        categoryText = categoryText.replace(/ /g, '').replace(/^[\s\-—–]+/, '').trim();
        html += reviewField(getLabel('category', 'Category'), escapeHtml(categoryText));
        html += reviewField(getLabel('slug', 'Slug'), escapeHtml(val('#service_slug')));
        var dur = val('#duration');
        if (dur) html += reviewField(getLabel('duration_to_perform_task', 'Duration'), escapeHtml(dur + ' ' + getLabel('minutes', 'minutes')));
        html += reviewField(getLabel('members_required_to_perform_task', 'Members'), escapeHtml(val('#members')));
        html += reviewField(getLabel('max_quantity_allowed_for_services', 'Max Quantity'), escapeHtml(val('#max_qty')));
        return html;
    }

    function buildMediaReview() {
        var html = '';
        // Main image: filename from filepond or preview
        var mainEl = document.getElementById('service_image_selector');
        var mainName = filepondFirstName(mainEl);
        if (!mainName) {
            var prev = document.getElementById('image_preview');
            if (prev && prev.src) mainName = prev.src.split('/').pop().split('?')[0];
        }
        if (mainName) html += reviewField(getLabel('image', 'Image'), escapeHtml(mainName));

        // Other images: filepond count + existing not marked-for-removal
        var otherEl = document.getElementById('other_service_image_selector');
        var otherCount = filepondCount(otherEl);
        var existingOther = 0;
        document.querySelectorAll('#other_images_container .other-image-container .remove-flag').forEach(function (rf) {
            if (rf.value === '0') existingOther++;
        });
        var totalOther = otherCount + existingOther;
        if (totalOther > 0) {
            html += reviewField(getLabel('other_images', 'Other Images'),
                '<span class="review-file-badge"><i class="fas fa-images"></i> ' + totalOther + '</span>');
        }

        // Files: filepond count + existing
        var filesEl = document.getElementById('files');
        var filesCount = filepondCount(filesEl);
        var existingFiles = 0;
        document.querySelectorAll('#files_container .file-container .remove-flag').forEach(function (rf) {
            if (rf.value === '0') existingFiles++;
        });
        var totalFiles = filesCount + existingFiles;
        if (totalFiles > 0) {
            html += reviewField(getLabel('files', 'Files'),
                '<span class="review-file-badge"><i class="fas fa-file"></i> ' + totalFiles + '</span>');
        }
        return html;
    }

    function buildPriceReview() {
        var html = '';
        var currency = getLabel('currency', '');
        html += reviewField(getLabel('tax_type', 'Price Type'), escapeHtml(selectedText('#tax_type')));
        html += reviewField(getLabel('tax', 'Tax'), escapeHtml(selectedText('#tax')));
        html += reviewField(getLabel('price', 'Price'), escapeHtml(currency + val('#price')));
        html += reviewField(getLabel('discounted_price', 'Discounted Price'), escapeHtml(currency + val('#discounted_price')));
        return html;
    }

    function buildFaqsReview() {
        var html = '';
        var totalsByLang = [];
        $('.faq-container').each(function () {
            var lang = $(this).data('language');
            var count = 0;
            $(this).find('.faq-item').each(function () {
                var q = $(this).find('.faq-question').val();
                var a = $(this).find('.faq-answer').val();
                if ((q && q.trim()) || (a && a.trim())) count++;
            });
            if (count > 0) totalsByLang.push({ lang: lang, count: count });
        });
        if (!totalsByLang.length) {
            html += reviewField(getLabel('faqs', 'FAQs'), escapeHtml(getLabel('no_faqs_added', 'No FAQs added')));
            return html;
        }
        totalsByLang.forEach(function (t) {
            html += reviewField(t.lang.toUpperCase(), t.count + ' Q&amp;A');
        });
        return html;
    }

    function buildSeoReview() {
        var defaultLangCode = getLabel('defaultLanguageCode', '');
        var html = '';
        if (defaultLangCode) {
            var t = val('#meta_title' + defaultLangCode);
            var k = val('#meta_keywords' + defaultLangCode);
            var d = val('#meta_description' + defaultLangCode);
            if (t) html += reviewField(getLabel('meta_title', 'Meta Title'), escapeHtml(t));
            if (k) {
                var parsedK = '';
                try {
                    var parsed = JSON.parse(k);
                    if (Array.isArray(parsed)) {
                        parsedK = parsed.map(function (t) { return t.value; }).join(', ');
                    } else {
                        parsedK = k;
                    }
                } catch (e) {
                    parsedK = k;
                }
                html += reviewField(getLabel('meta_keywords', 'Meta Keywords'), escapeHtml(parsedK));
            }
            if (d) html += reviewField(getLabel('meta_description', 'Meta Description'), escapeHtml(d.length > 200 ? d.substring(0, 200) + '…' : d));
        }
        return html;
    }

    function buildOptionsReview() {
        var html = '';
        html += reviewField(getLabel('is_cancelable', 'Is Cancelable'), toggleBadge(isChecked('#is_cancelable')));
        if (isChecked('#is_cancelable')) {
            html += reviewField(getLabel('cancelable_before', 'Cancelable Before'),
                escapeHtml((val('#cancelable_till') || '0') + ' ' + getLabel('minutes', 'minutes')));
        }
        var payLater = document.getElementById('pay_later');
        if (payLater) html += reviewField(getLabel('pay_later_allowed', 'Pay Later Allowed'), toggleBadge(payLater.checked));
        if (isVisible(document.getElementById('service_at_store') || document.body)) {
            html += reviewField(getLabel('at_store', 'At Store'), toggleBadge(isChecked('#at_store')));
        }
        if (isVisible(document.getElementById('service_at_doorstep') || document.body)) {
            html += reviewField(getLabel('at_doorstep', 'At Doorstep'), toggleBadge(isChecked('#at_doorstep')));
        }
        var apr = document.getElementById('service_approve_service');
        if (apr && isVisible(apr)) {
            html += reviewField(getLabel('approve_service', 'Approve Service'), toggleBadge(isChecked('#approve_service')));
        }
        html += reviewField(getLabel('status', 'Status'), toggleBadge(isChecked('#status')));
        return html;
    }

    // ============================================================
    //  Helpers
    // ============================================================
    function getLabel(key, fallback) {
        if (window.stepperLabels && window.stepperLabels[key]) return window.stepperLabels[key];
        return fallback || '';
    }

    function val(target) {
        var el;
        if (typeof target === 'string') el = document.querySelector(target);
        else el = target;
        if (!el) return '';
        if (el.tagName === 'SELECT') return el.value || '';
        return el.value || '';
    }

    function selectedText(selector) {
        var el = document.querySelector(selector);
        if (!el || !el.selectedOptions || !el.selectedOptions[0]) return '';
        return el.selectedOptions[0].text || '';
    }

    function isChecked(selector) {
        var el = document.querySelector(selector);
        return !!(el && el.checked);
    }

    function filepondCount(el) {
        if (!el || typeof FilePond === 'undefined') return 0;
        var pond = FilePond.find(el);
        return pond ? pond.getFiles().length : 0;
    }

    function filepondFirstName(el) {
        if (!el || typeof FilePond === 'undefined') return '';
        var pond = FilePond.find(el);
        if (!pond) return '';
        var files = pond.getFiles();
        return files.length ? files[0].filename : '';
    }

    function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // ---- Bootstrap ----
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
