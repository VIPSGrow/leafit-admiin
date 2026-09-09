(function() {
    'use strict';

    // Global configuration for counters
    window.SEOCounters = {
        MAX_LENGTHS: {
            title: 255,
            description: 500,
            keywords: 500
        }
    };

    /** COUNTER FUNCTIONS */

    // Update keyword counter for keywords fields
    function updateKeywordCounter(fieldId) {
        const field = document.getElementById(fieldId);
        if (!field) {
            return;
        }
        
        // Always create the counter, even if Tagify isn't ready yet
        let counter = field.parentNode.querySelector('.keyword-counter');
        if (!counter) {
            counter = document.createElement('small');
            counter.className = 'keyword-counter text-muted';
            counter.style.display = 'block';
            counter.style.marginTop = '5px';
            counter.style.fontSize = '12px';
            counter.style.color = '#6c757d';
            counter.style.fontWeight = 'normal';
            counter.id = fieldId + '_counter'; // Add ID for easier debugging
            field.parentNode.appendChild(counter);
        }
        
        // If Tagify is not ready, show 0 count
        if (!field._tagify) {
            counter.textContent = '0/10 keywords';
            counter.className = 'keyword-counter text-muted';
            return;
        }

        // Get current count from Tagify instance
        let currentCount = 0;
        try {
            // Tagify stores tags in the value property as an array
            if (field._tagify.value && Array.isArray(field._tagify.value)) {
                currentCount = field._tagify.value.length;
            } else {
                // Fallback: try to get from DOM
                const tagElements = field.parentNode.querySelectorAll('tag');
                currentCount = tagElements.length;
            }
        } catch (error) {
            currentCount = 0;
        }
        
        const maxOptimal = 10; // Optimal number for SEO
        
        // Update counter text
        counter.textContent = `${currentCount}/${maxOptimal} keywords`;
        
        // Apply color based on keyword count
        counter.className = 'keyword-counter';
        if (currentCount > maxOptimal) {
            counter.classList.add('text-warning');
            counter.textContent = `${currentCount}/${maxOptimal} keywords`;
        } else if (currentCount === maxOptimal) {
            counter.classList.add('text-success');
        } else {
            counter.classList.add('text-muted');
        }
    }

    // Update character counter for non-keywords fields
    function updateCharacterCounter(fieldId, maxLength) {
        const field = document.getElementById(fieldId);
        if (!field) return;

        const currentLength = field.value.length;
        
        // Find or create counter element
        let counter = field.parentNode.querySelector('.char-counter');
        if (!counter) {
            counter = document.createElement('small');
            counter.className = 'char-counter';
            counter.style.display = 'block';
            counter.style.marginTop = '5px';
            field.parentNode.appendChild(counter);
        }

        // Update counter text
        counter.textContent = `${currentLength}/${maxLength}`;
        
        // Apply color based on character count
        counter.className = 'char-counter';
        if (currentLength > maxLength) {
            counter.classList.add('text-danger');
        } else if (currentLength > maxLength * 0.9) {
            counter.classList.add('text-warning');
        } else {
            counter.classList.add('text-muted');
        }
    }

    // Setup counters for all form fields
    function setupCounters() {
        const formFields = [
            { id: 'title', maxLength: window.SEOCounters.MAX_LENGTHS.title, type: 'character' },
            { id: 'description', maxLength: window.SEOCounters.MAX_LENGTHS.description, type: 'character' },
            { id: 'edit_title', maxLength: window.SEOCounters.MAX_LENGTHS.title, type: 'character' },
            { id: 'edit_description', maxLength: window.SEOCounters.MAX_LENGTHS.description, type: 'character' }
        ];

        formFields.forEach(({ id, maxLength, type }) => {
            const element = document.getElementById(id);
            if (element) {
                // Only setup character counters here, keyword counters are handled in initializeTagify
                updateCharacterCounter(id, maxLength);
                element.addEventListener('input', () => {
                    updateCharacterCounter(id, maxLength);
                });
            }
        });
        
        // Initialize keyword counters immediately (will show 0/10 until Tagify is ready)
        updateKeywordCounter('keywords');
        updateKeywordCounter('edit_keywords');
    }

    /** INITIALIZATION */

    function initializeCounters() {
        setupCounters();
    }

    // Export counter functions for external use
    window.SEOCounters.init = function() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializeCounters);
        } else {
            initializeCounters();
        }

        if (typeof $ !== 'undefined') {
            $(document).ready(initializeCounters);
        }
    };

    // Export individual functions for external use
    window.SEOCounters.updateKeywordCounter = updateKeywordCounter;
    window.SEOCounters.updateCharacterCounter = updateCharacterCounter;

    // Reset all counters to initial state
    window.SEOCounters.resetCounters = function() {
        // Reset character counters for main form fields
        updateCharacterCounter('title', window.SEOCounters.MAX_LENGTHS.title);
        updateCharacterCounter('description', window.SEOCounters.MAX_LENGTHS.description);
        
        // Reset character counters for edit form fields
        updateCharacterCounter('edit_title', window.SEOCounters.MAX_LENGTHS.title);
        updateCharacterCounter('edit_description', window.SEOCounters.MAX_LENGTHS.description);
        
        // Reset keyword counters
        updateKeywordCounter('keywords');
        updateKeywordCounter('edit_keywords');
    };

    // Auto-initialize
    window.SEOCounters.init();

})();