/**
 * Password strength indicator – configurable via window.PASSWORD_RULES from Authentication Settings.
 * Shared by register, forgot password, add partner, duplicate provider.
 * When PASSWORD_RULES.minLength === 0, indicator is hidden and no rules apply.
 * Otherwise only enabled rules are shown and validated. Requires jQuery.
 */
(function() {
    "use strict";

    var RULES = window.PASSWORD_RULES || {
        minLength: 8,
        requireUppercase: 1,
        requireLowercase: 1,
        requireNumber: 1,
        requireSpecial: 1
    };

    function getRules() {
        return RULES;
    }

    function setRules(r) {
        RULES = r || RULES;
    }

    function checkPasswordStrength() {
        var $indicator = $("#password-strength-indicator");
        var $pwdInput = $("#password");
        if (!$indicator.length || !$pwdInput.length) return;

        if (RULES.minLength === 0) {
            $indicator.hide();
            return;
        }
        $indicator.show();

        var pwd = $pwdInput.val() || "";
        var minLen = parseInt(RULES.minLength, 10) || 8;
        var hasMinLength = pwd.length >= minLen;
        var hasNumber = /\d/.test(pwd);
        var hasUppercase = /[A-Z]/.test(pwd);
        var hasLowercase = /[a-z]/.test(pwd);
        var hasSpecial = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(pwd);

        var byRule = {
            length: hasMinLength,
            number: hasNumber,
            uppercase: hasUppercase,
            lowercase: hasLowercase,
            special: hasSpecial
        };
        var enabled = {
            length: minLen > 0,
            number: RULES.requireNumber === 1,
            uppercase: RULES.requireUppercase === 1,
            lowercase: RULES.requireLowercase === 1,
            special: RULES.requireSpecial === 1
        };

        ["length", "number", "uppercase", "lowercase", "special"].forEach(function(rule) {
            var $el = $indicator.find("[data-rule=\"" + rule + "\"]").closest(".password-rule");
            if (!$el.length) return;
            if (enabled[rule]) {
                $el.show().toggleClass("fulfilled", byRule[rule]);
            } else {
                $el.hide();
            }
        });
    }

    /**
     * Returns true if the current #password value satisfies all enabled rules.
     * Use before form submit for client-side validation.
     */
    function isPasswordValid() {
        if (RULES.minLength === 0) return true;
        var pwd = ($("#password").val() || "").trim();
        var minLen = parseInt(RULES.minLength, 10) || 8;
        if (pwd.length < minLen) return false;
        if (RULES.requireNumber === 1 && !/\d/.test(pwd)) return false;
        if (RULES.requireUppercase === 1 && !/[A-Z]/.test(pwd)) return false;
        if (RULES.requireLowercase === 1 && !/[a-z]/.test(pwd)) return false;
        if (RULES.requireSpecial === 1 && !/[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(pwd)) return false;
        return true;
    }

    function init() {
        if (!$("#password-strength-indicator").length || !$("#password").length) {
            return;
        }
        if (typeof window.PASSWORD_RULES !== "undefined") {
            setRules(window.PASSWORD_RULES);
        }
        $("#password").on("input", checkPasswordStrength);
        checkPasswordStrength();
    }

    if (typeof $ !== "undefined" && $.fn && $.fn.jquery) {
        $(document).ready(init);
    } else {
        document.addEventListener("DOMContentLoaded", function() {
            if (typeof $ !== "undefined") {
                $(document).ready(init);
            }
        });
    }

    window.passwordStrengthValid = isPasswordValid;
    window.passwordStrengthSetRules = setRules;
})();
