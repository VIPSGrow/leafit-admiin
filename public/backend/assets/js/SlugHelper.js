/**
 * --------------------------------------------------------
 * Slug Helper Utility
 * --------------------------------------------------------
 * - Handles Unicode transliteration
 * - Converts spaces → hyphens
 * - Removes invalid characters
 * - Prevents overwriting manual slug edits
 * - Works with multiple inputs on the same page
 *
 * Usage:
 *   <input data-slug-source data-slug-target="#slug">
 *   <input id="slug">
 *
 * Optional:
 *   data-slug-lock="true"   → prevents auto updates
 * --------------------------------------------------------
 */

(function () {
    "use strict";

    /**
     * Convert text to URL-safe slug
     */
    function slugify(text) {
        if (!text) return "";

        // Normalize unicode (NFD separates accents)
        let slug = text
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "") // remove accents
            .toLowerCase()
            .trim();

        // Replace unwanted characters
        slug = slug
            .replace(/[^a-z0-9\s-]/g, "")
            .replace(/[\s-]+/g, "-")
            .replace(/^-+|-+$/g, "");

        return slug;
    }

    /**
     * Initialize slug auto-fill behavior.
     * Idempotent: skips elements already bound (data-slug-bound) so safe to call multiple times.
     */
    function initSlugAutoFill() {
        const sources = document.querySelectorAll("[data-slug-source]");

        sources.forEach(source => {
            // Skip if already bound (e.g. re-init on partner forms)
            if (source.dataset.slugBound === "true") return;

            const targetSelector = source.dataset.slugTarget;
            if (!targetSelector) return;

            const target = document.querySelector(targetSelector);
            if (!target) return;

            source.dataset.slugBound = "true";

            // Only lock when the user edits the slug field manually (not when pre-filled on edit/duplicate).
            // So on edit/duplicate, changing the title still updates the slug until the user touches the slug.

            // Update slug on typing in the source (title) field
            source.addEventListener("input", () => {
                if (target.dataset.locked === "true") return;

                const generated = slugify(source.value);
                target.value = generated;
            });

            // Lock slug when user edits it manually
            target.addEventListener("input", () => {
                target.dataset.locked = "true";
            });
        });
    }

    // Always run when DOM is ready so forms (e.g. partner panel) are in the document
    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initSlugAutoFill);
    } else {
        initSlugAutoFill();
    }

    // Expose so partner/admin can re-run after dynamic content (e.g. $(document).ready)
    window.initSlugAutoFill = initSlugAutoFill;
    window.slugify = slugify;

})();
