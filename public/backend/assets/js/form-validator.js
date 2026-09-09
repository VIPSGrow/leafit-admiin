'use strict';
(function ($) {

    // ── Inject CSS ────────────────────────────────────────────────────────────
    var style = document.createElement('style');
    style.textContent = [
        '.fv-field-error{display:block;color:#e54b4b;font-size:.78rem;margin-top:.3rem;line-height:1.3}',
        '.has-fv-error .form-control{border-color:#e54b4b!important;box-shadow:none!important}',
        '.has-fv-error .select2-selection{border-color:#e54b4b!important}',
        '.has-fv-error .phone-input-group{border-color:#e54b4b!important}',
        '.has-fv-error .note-editor.card{border-color:#e54b4b!important}',
        '.has-fv-error .filepond--root .filepond--drop-label{color:#e54b4b!important}',
        '.has-fv-error .filepond--root{border:1px dashed #e54b4b!important;border-radius:.375rem!important}',
    ].join('');
    document.head.appendChild(style);

    // ── Label helpers ─────────────────────────────────────────────────────────
    function L(key, fallback) {
        return (window.fvLabels && window.fvLabels[key]) || fallback;
    }

    function labelFromGroup($fg) {
        var raw = $fg.find('label').first().clone().children().remove().end().text().trim();
        return raw.replace(/\s*\*\s*$/, '').trim() || L('field', 'Field');
    }

    // ── DOM helpers ───────────────────────────────────────────────────────────
    function formGroup(field) {
        return $(field).closest('.form-group')[0] || field.parentElement;
    }

    function fieldLabel(field) {
        var $g = $(formGroup(field));
        var raw = $g.find('label').first().clone().children().remove().end().text().trim();
        return raw.replace(/\s*\*\s*$/, '').trim() || field.placeholder || field.name || L('field', 'Field');
    }

    function insertAfterControl(field, $el) {
        var $wrap = $(field).closest('.input-group, .phone-input-group');
        ($wrap.length ? $wrap : $(field)).after($el);
    }

    // ── Widget detection (Select2 / Summernote) ───────────────────────────────
    // NOTE: FilePond completely REMOVES the original input from the DOM via
    // replaceElement(). It cannot be detected by querying relative to the field.
    // FilePond inputs are pre-scanned at bindForm time and validated separately.

    function widgetType(field) {
        if ($(field).hasClass('select2-hidden-accessible')) return 'select2';
        if ($(field).next('.note-editor').length) return 'summernote';
        return null;
    }

    function widgetRoot(field, type) {
        if (type === 'select2') return $(field).nextAll('.select2-container').first();
        if (type === 'summernote') return $(field).next('.note-editor');
        return $();
    }

    // ── Standard error display ────────────────────────────────────────────────
    function showError(field, msg) {
        clearError(field);
        $(formGroup(field)).addClass('has-fv-error');
        var $err = $('<div class="fv-field-error"></div>').text(msg);
        var type = widgetType(field);

        if (type === 'select2') {
            $(field).nextAll('.select2-container').first().after($err);
            $(field).one('change.fv', function () { clearError(field); });
        } else if (type === 'summernote') {
            $(field).next('.note-editor').after($err);
            $(field).one('summernote.change', function () { clearError(field); });
        } else {
            insertAfterControl(field, $err);
            $(field).one('input.fv change.fv', function () { clearError(field); });
        }
    }

    function clearError(field) {
        $(formGroup(field)).removeClass('has-fv-error').find('.fv-field-error').remove();
        $(field).off('.fv');
    }

    function clearFormErrors(form) {
        $(form).find('.fv-field-error').remove();
        $(form).find('.has-fv-error').removeClass('has-fv-error');
    }

    // ── FilePond error display ────────────────────────────────────────────────
    // FilePond removes the original input from the DOM (replaceElement removes it).
    // All DOM interaction uses pond.element — the .filepond--root div in the DOM.

    function showFilePondError(pond, msg) {
        clearFilePondError(pond);
        var $root = $(pond.element);
        $root.closest('.form-group').addClass('has-fv-error');
        $root.after($('<div class="fv-field-error"></div>').text(msg));
        $root.one('FilePond:addfile', function () { clearFilePondError(pond); });
    }

    function clearFilePondError(pond) {
        var $root = $(pond.element);
        $root.closest('.form-group').removeClass('has-fv-error').find('.fv-field-error').remove();
    }

    // ── Rule engine ───────────────────────────────────────────────────────────
    function runRules(field) {
        var type = widgetType(field);

        if (type) {
            // Widget fields: visibility check against the widget root, not the hidden native input.
            var $root = widgetRoot(field, type);
            if ($root.length && !$root.is(':visible')) return null;
        } else {
            // Plain fields: skip invisible ones (e.g. hidden multilang panels).
            if (!$(field).is(':visible')) return null;
        }

        var rules = ($(field).attr('data-rules') || '').toString().split('|').map(function (r) { return r.trim(); }).filter(Boolean);
        if (!rules.length) return null;

        var label = fieldLabel(field);

        var val;
        if (type === 'summernote') {
            try {
                val = $(field).summernote('isEmpty') ? '' : 'nonempty';
            } catch (e) {
                val = $(field).val().trim();
            }
        } else {
            val = (field.value != null ? field.value : '').trim();
        }

        for (var i = 0; i < rules.length; i++) {
            var rule = rules[i];

            if (rule === 'required' && val === '') {
                return L('required', '%s is required').replace('%s', label);
            }

            if (type) continue; // email/min/max only apply to plain text fields

            if (rule === 'email' && val !== '' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
                return L('email', 'Please enter a valid email address');
            }

            var minM = rule.match(/^min:(\d+)$/);
            if (minM) {
                var min = parseInt(minM[1], 10);
                if (val !== '' && val.length < min) {
                    return L('min', 'Must be at least %d characters').replace('%d', min);
                }
            }

            var maxM = rule.match(/^max:(\d+)$/);
            if (maxM) {
                var max = parseInt(maxM[1], 10);
                if (val.length > max) {
                    return L('max', 'Must not exceed %d characters').replace('%d', max);
                }
            }
        }
        return null;
    }

    // ── Validation ────────────────────────────────────────────────────────────
    function validateForm(form) {
        clearFormErrors(form);
        var valid = true;
        var $first = null;

        // Standard fields (Select2 + Summernote original elements remain in DOM).
        // Scope to actual form controls — excludes filepond--root divs that inherit data-rules.
        $(form).find('input[data-rules], select[data-rules], textarea[data-rules]').each(function () {
            var msg = runRules(this);
            if (msg) {
                showError(this, msg);
                if (!$first) $first = $(this);
                valid = false;
            }
        });

        // FilePond fields — original inputs removed from DOM by FilePond.
        // Refs stored at bindForm time; FilePond.find() matches by reference even on detached elements.
        if (typeof FilePond !== 'undefined') {
            var fpInputs = $(form).data('fv-fp-inputs') || [];
            fpInputs.forEach(function (field) {
                var rules = ($(field).attr('data-rules') || '').toString().split('|').map(function (r) { return r.trim(); }).filter(Boolean);
                if (!rules.length) return;

                var pond = FilePond.find(field);
                if (!pond) return; // FilePond not yet initialized for this input

                var $root = $(pond.element);
                if (!$root.is(':visible')) return; // inside hidden panel

                var label = labelFromGroup($root.closest('.form-group')) || field.name || L('field', 'Field');
                var hasFiles = pond.getFiles().length > 0;

                for (var i = 0; i < rules.length; i++) {
                    if (rules[i] === 'required' && !hasFiles) {
                        var msg = L('required', '%s is required').replace('%s', label);
                        showFilePondError(pond, msg);
                        if (!$first) $first = $root;
                        valid = false;
                        break;
                    }
                }
            });
        }

        // Password strength integration.
        if (typeof window.passwordStrengthValid === 'function') {
            var $pwd = $(form).find('#password');
            if ($pwd.length && $pwd.is(':visible') && $pwd.val().trim() !== '') {
                if (!window.passwordStrengthValid()) {
                    var strengthMsg = L('pwdRequirements', 'Password does not meet the requirements. Please check the rules above.');
                    showError($pwd[0], strengthMsg);
                    if (!$first) $first = $pwd;
                    valid = false;
                }
            }
        }

        if ($first) {
            var $modalBody = $first.closest('.modal-body');
            if ($modalBody.length) {
                var top = $first.closest('.form-group').position();
                if (top) {
                    $modalBody.animate({ scrollTop: $modalBody.scrollTop() + top.top - 20 }, 200);
                }
            }
        }

        return valid;
    }

    // ── Hooks ─────────────────────────────────────────────────────────────────
    function bindForm(form) {
        if (!form.id) {
            console.warn('FormValidator: form[data-fv] has no id — skipped', form);
            return;
        }

        // Pre-scan FilePond inputs BEFORE FilePond removes them from the DOM.
        // Must run at DOMReady (before shown.bs.modal triggers FilePond.parse).
        var fpInputs = Array.from(form.querySelectorAll('input[type="file"][data-rules]'));
        if (fpInputs.length) {
            $(form).data('fv-fp-inputs', fpInputs);
        }

        $('#' + form.id).on('submit.fv', function (e) {
            if (!validateForm(this)) {
                e.preventDefault();
                e.stopImmediatePropagation();
            }
        });
    }

    $(function () {
        $('form[data-fv]').each(function () { bindForm(this); });
    });

    // Clear errors when a modal closes (form reset fires after this).
    $(document).on('hidden.bs.modal', '.modal', function () {
        $(this).find('form[data-fv]').each(function () { clearFormErrors(this); });
    });

    // ── Public API ────────────────────────────────────────────────────────────
    window.FormValidator = {
        validate: validateForm,
        clearErrors: clearFormErrors,
        showFieldError: showError,
        clearFieldError: clearError,
        // For forms added to the DOM after DOMReady (dynamically rendered modals).
        bind: bindForm,
    };

}(jQuery));
