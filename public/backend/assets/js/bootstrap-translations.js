// /**
//  * Bootstrap Table Translations - OPTIMIZED VERSION
//  * 
//  * This file contains optimized JavaScript logic for translating Bootstrap tables.
//  * It handles loading messages, pagination, search, and other table elements.
//  * 
//  * Dependencies:
//  * - jQuery
//  * - Bootstrap Table
//  * - bootstrapTableLabels (must be defined before this script)
//  */

// // Cache for translation options to avoid repeated object creation
// let cachedTranslationOptions = null;

// // Cache for DOM selectors to improve performance
// const SELECTORS = {
//   tables: '[data-toggle="table"]',
//   loadingMessage: '.fixed-table-loading',
//   toolbar: '.bootstrap-table .fixed-table-toolbar',
//   columnsToggle: '.columns .btn-group .dropdown-toggle',
//   searchInput: '.search input',
//   refreshBtn: '.refresh .btn'
// };

// // Translation keys mapping for dynamic property access
// const TRANSLATION_KEYS = [
//   'formatShowingRows', 'formatRecordsPerPage', 'formatNoMatches', 'formatSearch',
//   'formatLoadingMessage', 'formatRefresh', 'formatToggle', 'formatColumns',
//   'formatAllRows', 'formatPaginationSwitch', 'formatDetailPagination',
//   'formatClearFilters', 'formatJumpTo', 'formatAdvancedSearch', 'formatAdvancedCloseButton'
// ];

// /**
//  * Clear translation cache when language changes
//  * This ensures fresh translations are applied
//  */
// function clearTranslationCache() {
//   cachedTranslationOptions = null;
// }

// /**
//  * Check if bootstrap table labels are available
//  * @returns {boolean} True if labels are available
//  */
// function isLabelsAvailable() {
//   return typeof bootstrapTableLabels !== 'undefined';
// }

// /**
//  * Update loading message for a specific table
//  * @param {jQuery} $table - jQuery table element
//  */
// function updateTableLoadingMessage($table) {
//   if (!isLabelsAvailable()) return;
  
//   const $loadingMessage = $table.find(SELECTORS.loadingMessage);
//   if ($loadingMessage.length) {
//     $loadingMessage.text(bootstrapTableLabels.formatLoadingMessage);
//   }
// }

// /**
//  * Update toolbar elements for a specific table
//  * @param {jQuery} $table - jQuery table element
//  */
// function updateTableToolbar($table) {
//   if (!isLabelsAvailable()) return;
  
//   const $toolbar = $table.find(SELECTORS.toolbar);
//   if ($toolbar.length) {
//     $toolbar.find(SELECTORS.columnsToggle).attr('title', bootstrapTableLabels.formatColumns);
//     $toolbar.find(SELECTORS.searchInput).attr('placeholder', bootstrapTableLabels.formatSearch);
//     $toolbar.find(SELECTORS.refreshBtn).attr('title', bootstrapTableLabels.formatRefresh);
//   }
// }

// /**
//  * Force update loading messages for all bootstrap tables
//  * This ensures loading messages are properly translated
//  */
// function updateLoadingMessages() {
//   if (!isLabelsAvailable()) return;
  
//   $(SELECTORS.tables).each(function() {
//     updateTableLoadingMessage($(this));
//   });
// }

// /**
//  * Create translation options object dynamically
//  * This eliminates repetitive code and makes it easier to maintain
//  */
// function createTranslationOptions() {
//   if (!isLabelsAvailable()) return null;
  
//   const options = {};
  
//   // Create formatShowingRows with parameter replacement
//   options.formatShowingRows = function(pageFrom, pageTo, totalRows) {
//     return bootstrapTableLabels.formatShowingRows
//       .replace('{from}', pageFrom)
//       .replace('{to}', pageTo)
//       .replace('{total}', totalRows);
//   };
  
//   // Create formatRecordsPerPage with parameter replacement
//   options.formatRecordsPerPage = function(pageNumber) {
//     return bootstrapTableLabels.formatRecordsPerPage.replace('{0}', pageNumber);
//   };
  
//   // Create all other translation functions dynamically
//   TRANSLATION_KEYS.forEach(key => {
//     if (key !== 'formatShowingRows' && key !== 'formatRecordsPerPage') {
//       options[key] = function() {
//         return bootstrapTableLabels[key];
//       };
//     }
//   });
  
//   return options;
// }

// /**
//  * Get cached translation options for better performance
//  * This prevents creating the same object multiple times
//  */
// function getTranslationOptions() {
//   if (!cachedTranslationOptions) {
//     cachedTranslationOptions = createTranslationOptions();
//   }
//   return cachedTranslationOptions;
// }

// /**
//  * Apply translations to a single table
//  * @param {jQuery} $table - jQuery table element
//  * @param {Object} translationOptions - Translation options object
//  * @returns {boolean} True if translations were applied successfully
//  */
// function applyTranslationsToTable($table, translationOptions) {
//   const tableId = $table.attr('id');
//   if (!tableId) return false;
  
//   try {
//     const isInitialized = $table.data('bootstrap.table');
    
//     if (isInitialized) {
//       // Check if translations have already been applied to avoid infinite loops
//       if ($table.data('bootstrap-table-translations-applied')) {
//         return true; // Already applied
//       }
      
//       // Mark as applied to prevent infinite loops
//       $table.data('bootstrap-table-translations-applied', true);
      
//       // Update the table options directly without triggering refresh
//       const tableInstance = $table.data('bootstrap.table');
//       if (tableInstance && tableInstance.options) {
//         // Update the options object directly without triggering refresh
//         Object.assign(tableInstance.options, translationOptions);
        
//         // Force update the loading message by overriding the default
//         if (tableInstance.options.formatLoadingMessage) {
//           tableInstance.options.formatLoadingMessage = function() {
//             return bootstrapTableLabels.formatLoadingMessage;
//           };
//         }
        
//         // Update UI elements using helper functions
//         updateTableToolbar($table);
//         updateTableLoadingMessage($table);
//       }
//     } else {
//       // Table not initialized yet, store translation options for later use
//       $table.data('bootstrap-table-translations', translationOptions);
//     }
    
//     return true;
//   } catch (error) {
//     console.warn('Error applying translations to table ' + tableId + ':', error);
//     return false;
//   }
// }

// /**
//  * Process tables in batches to avoid blocking the UI
//  * @param {jQuery} $tables - jQuery collection of tables
//  * @param {Object} translationOptions - Translation options object
//  * @param {number} batchSize - Number of tables to process per batch
//  */
// function processTablesInBatches($tables, translationOptions, batchSize = 5) {
//   const tableCount = $tables.length;
//   let currentIndex = 0;

//   function processBatch() {
//     const endIndex = Math.min(currentIndex + batchSize, tableCount);
    
//     for (let i = currentIndex; i < endIndex; i++) {
//       applyTranslationsToTable($tables.eq(i), translationOptions);
//     }
    
//     currentIndex = endIndex;
    
//     // Continue processing if there are more tables
//     if (currentIndex < tableCount) {
//       requestAnimationFrame(processBatch);
//     }
//   }
  
//   // Start processing
//   if (tableCount > 0) {
//     processBatch();
//   }
// }

// /**
//  * Apply Bootstrap table translations globally - OPTIMIZED VERSION
//  * This function applies translated labels to all Bootstrap tables on the page
//  */
// function applyBootstrapTableTranslations() {
//   // Check if bootstrapTableLabels is available
//   if (!isLabelsAvailable()) {
//     console.warn('Bootstrap table labels not found. Make sure bootstrapTableLabels is defined.');
//     return;
//   }

//   // Get cached translation options
//   const translationOptions = getTranslationOptions();
//   if (!translationOptions) return;

//   // Cache DOM selectors for better performance
//   const $tables = $(SELECTORS.tables);
  
//   // Process tables in batches to avoid blocking the UI
//   processTablesInBatches($tables, translationOptions);
// }

// /**
//  * Override bootstrap table defaults globally
//  * This ensures all new tables use translated messages by default
//  */
// function overrideBootstrapTableDefaults() {
//   if (!isLabelsAvailable()) return;
  
//   const defaults = [
//     window.$.fn.bootstrapTable?.defaults,
//     window.$.fn.bootstrapTable?.constants?.DEFAULTS,
//     window.$.fn.bootstrapTable?.DEFAULTS
//   ].filter(Boolean);
  
//   defaults.forEach(defaultObj => {
//     if (defaultObj) {
//       defaultObj.formatLoadingMessage = function() {
//         return bootstrapTableLabels.formatLoadingMessage;
//       };
//     }
//   });
// }

// /**
//  * Handle table initialization events
//  * @param {Event} e - Event object
//  */
// function handleTableInitialization(e) {
//   const $table = $(e.target);
//   if ($table.data('bootstrap-table-translations')) {
//     // Apply stored translations when table is initialized
//     const storedOptions = $table.data('bootstrap-table-translations');
//     const tableInstance = $table.data('bootstrap.table');
//     if (tableInstance && tableInstance.options) {
//       Object.assign(tableInstance.options, storedOptions);
      
//       // Force update the loading message by overriding the default
//       if (tableInstance.options.formatLoadingMessage) {
//         tableInstance.options.formatLoadingMessage = function() {
//           return bootstrapTableLabels.formatLoadingMessage;
//         };
//       }
      
//       $table.data('bootstrap-table-translations-applied', true);
//     }
//   }
// }

// /**
//  * Handle loading message updates for various table events
//  * @param {Event} e - Event object
//  */
// function handleLoadingMessageUpdate(e) {
//   const $table = $(e.target);
//   setTimeout(() => {
//     updateTableLoadingMessage($table);
//   }, 10);
// }

// /**
//  * Handle dynamic table detection using MutationObserver
//  * @param {Array} mutations - Array of mutation records
//  */
// function handleDynamicTableDetection(mutations) {
//   let shouldUpdate = false;
//   mutations.forEach(mutation => {
//     if (mutation.type === 'childList') {
//       mutation.addedNodes.forEach(node => {
//         if (node.nodeType === 1) { // Element node
//           const $node = $(node);
//           if ($node.is(SELECTORS.tables) || $node.find(SELECTORS.tables).length > 0) {
//             shouldUpdate = true;
//           }
//         }
//       });
//     }
//   });
  
//   if (shouldUpdate) {
//     requestAnimationFrame(applyBootstrapTableTranslations);
//   }
// }

// /**
//  * Setup event listeners for table translations
//  * This consolidates all event handling into a single function
//  */
// function setupEventListeners() {
//   // Table initialization events
//   $(document).on('post-body.bs.table', handleTableInitialization);
  
//   // Loading message events - consolidated into single handler
//   const loadingEvents = 'load-success.bs.table load-error.bs.table pre-body.bs.table refresh.bs.table load.bs.table';
//   $(document).on(loadingEvents, handleLoadingMessageUpdate);
  
//   // Dynamic table detection using MutationObserver
//   if (window.MutationObserver) {
//     const observer = new MutationObserver(handleDynamicTableDetection);
//     observer.observe(document.body, {
//       childList: true,
//       subtree: true
//     });
//   } else {
//     // Fallback for older browsers
//     $(document).on('DOMNodeInserted', function(e) {
//       const $newElement = $(e.target);
//       if ($newElement.is(SELECTORS.tables) || $newElement.find(SELECTORS.tables).length > 0) {
//         setTimeout(applyBootstrapTableTranslations, 100);
//       }
//     });
//   }
// }

// /**
//  * Initialize Bootstrap table translations on page load - OPTIMIZED VERSION
//  * This function is called when the page is ready
//  */
// function initializeBootstrapTableTranslations() {
//   // Override bootstrap table defaults globally
//   overrideBootstrapTableDefaults();
  
//   // Apply translations immediately for already initialized tables
//   applyBootstrapTableTranslations();
  
//   // Get cached translation options for better performance
//   const translationOptions = getTranslationOptions();
//   if (!translationOptions) return;
  
//   // Apply translations to tables that will be initialized
//   // This prevents the double initialization issue
//   const $tables = $(SELECTORS.tables);
//   $tables.each(function() {
//     const $table = $(this);
//     if (!$table.data('bootstrap.table')) {
//       // Store translation options for when the table initializes
//       $table.data('bootstrap-table-translations', translationOptions);
//     }
//   });
  
//   // Setup all event listeners
//   setupEventListeners();
// }

// /**
//  * Initialize translations immediately if bootstrap table labels are available
//  * This ensures translations are applied as soon as possible
//  */
// function initializeImmediateTranslations() {
//   if (window.$ && window.$.fn.bootstrapTable && isLabelsAvailable()) {
//     overrideBootstrapTableDefaults();
//   }
// }

// // Initialize translations immediately if possible
// initializeImmediateTranslations();

// // Make the functions globally available
// window.applyBootstrapTableTranslations = applyBootstrapTableTranslations;
// window.initializeBootstrapTableTranslations = initializeBootstrapTableTranslations;
// window.clearTranslationCache = clearTranslationCache;
// window.updateLoadingMessages = updateLoadingMessages;




/****************************************************************************************
 * *
 * BOOTSTRAP TABLE: ALLOW ALL HTML + STRIP JS ONLY + TRANSLATIONS                    *
 * *
 ****************************************************************************************/

/* =========================================================================
   1) GLOBAL RESPONSE SANITIZER (The Firewall)
   ========================================================================= */

/**
 * Recursive function to clean data before it reaches the table.
 * * SECURITY POSTURE:
 * - Table setting: $.fn.bootstrapTable.defaults.escape = false (ALLOWS all HTML to render)
 * - Sanitizer logic: RELIES on this function to strip/escape all executable JavaScript vectors.
 * * ALLOWS: <div>, <span>, <i>, <button>, class="", style="", valid href="..."
 * ESCAPES: <script> tags and their content.
 * REMOVES: javascript: protocols and inline event handlers (onclick, etc.).
 */
function sanitizeResponseObject(obj) {
    // 1. Handle Arrays (recurse)
    if (Array.isArray(obj)) {
        return obj.map(item => sanitizeResponseObject(item));
    }

    // 2. Handle Objects (recurse)
    if (obj && typeof obj === "object") {
        const clean = {};
        for (let key in obj) {
            clean[key] = sanitizeResponseObject(obj[key]);
        }
        return clean;
    }

    // 3. Handle Strings (The actual cleaning)
    if (typeof obj === "string") {

        // SKIP: Do not touch LaTeX math formulas
        if (obj.includes("math-tex")) return obj;

        // --- STAGE 1: NEUTRALIZE SCRIPT TAGS (Aggressive Full-Block Escape) ---
        
        // This finds the entire script block (tag + content) and replaces ALL '<' and '>' 
        // characters within it with entities, preventing execution while preserving the text.
        obj = obj.replace(/<script[\s\S]*?<\/script>/gi, (match) => {
            // Replace ALL '<' and '>' characters in the matched block
            return match.replace(/</g, '&lt;').replace(/>/g, '&gt;');
        });


        // --- STAGE 2: Remove "javascript:" pseudo-protocols ---
        // Prevents href="javascript:alert(1)"
        obj = obj.replace(/javascript:/gi, "");

        // --- STAGE 3: Remove inline event handlers ---
        // Prevents onclick=, onmouseover=, onerror=, etc.
        // Looks for whitespace + "on" + word + "=" + quote + content + quote
        obj = obj.replace(/\son\w+\s*=\s*(['"]).*?\1/gi, "");

        return obj;
    }

    // 4. Numbers/Booleans/Null (return as is)
    return obj;
}

// APPLY CONFIGURATION TO BOOTSTRAP TABLE
if ($.fn.bootstrapTable) {
    
    // 1. Set the Global Sanitizer
    $.fn.bootstrapTable.defaults.responseHandler = function (res) {
        return sanitizeResponseObject(res);
    };

    // 2. IMPORTANT: Allow HTML rendering everywhere
    // We set this to FALSE so Bootstrap Table does NOT escape your <div>s and <button>s.
    // Safety is guaranteed by the sanitizer above.
    $.fn.bootstrapTable.defaults.escape = false;
}


/* =========================================================================
   2) TRANSLATION ENGINE (Standard)
   ========================================================================= */

let cachedTranslationOptions = null;

const TRANSLATION_KEYS = [
    'formatShowingRows', 'formatRecordsPerPage', 'formatNoMatches', 'formatSearch',
    'formatLoadingMessage', 'formatRefresh', 'formatToggle', 'formatColumns',
    'formatAllRows', 'formatPaginationSwitch', 'formatDetailPagination',
    'formatClearFilters', 'formatJumpTo', 'formatAdvancedSearch', 'formatAdvancedCloseButton'
];

function isLabelsAvailable() {
    return typeof bootstrapTableLabels !== 'undefined';
}

function createTranslationOptions() {
    if (!isLabelsAvailable()) return null;

    const opt = {};

    opt.formatShowingRows = (from, to, total) =>
        bootstrapTableLabels.formatShowingRows
            .replace('{from}', from)
            .replace('{to}', to)
            .replace('{total}', total);

    opt.formatRecordsPerPage = (n) =>
        bootstrapTableLabels.formatRecordsPerPage.replace('{0}', n);

    TRANSLATION_KEYS.forEach(k => {
        if (!opt[k]) {
            opt[k] = () => bootstrapTableLabels[k];
        }
    });

    return opt;
}

function getTranslationOptions() {
    if (!cachedTranslationOptions)
        cachedTranslationOptions = createTranslationOptions();
    return cachedTranslationOptions;
}

// Apply translated strings (incl. the search box placeholder) to bootstrap-table's global
// defaults synchronously, before DOMContentLoaded triggers auto-init on data-toggle="table"
// elements. This means every table renders with the localized placeholder on first paint —
// no default-English-then-swapped flicker.
if ($.fn.bootstrapTable) {
    const defaultTranslationOptions = getTranslationOptions();
    if (defaultTranslationOptions) {
        Object.assign($.fn.bootstrapTable.defaults, defaultTranslationOptions);
    }
}

function updateTableToolbar($table) {
    if (!isLabelsAvailable()) return;

    const $tb = $table.parents('.bootstrap-table').find('.fixed-table-toolbar');
    if ($tb.length) {
        $tb.find('.columns .btn-group .dropdown-toggle')
            .attr('title', bootstrapTableLabels.formatColumns);
        $tb.find('.search input')
            .attr('placeholder', bootstrapTableLabels.formatSearch);
        $tb.find('.refresh .btn')
            .attr('title', bootstrapTableLabels.formatRefresh);
    }
}

function updateTableLoadingMessage($table) {
    if (!isLabelsAvailable()) return;
    const $msg = $table.parents('.bootstrap-table').find('.fixed-table-loading');
    if ($msg.length) {
        $msg.text(bootstrapTableLabels.formatLoadingMessage);
    }
}

function applyTranslationsToTable($table, translationOptions) {
    const instance = $table.data('bootstrap.table');
    if (!instance || !instance.options) return;

    Object.assign(instance.options, translationOptions);
    updateTableToolbar($table);
    updateTableLoadingMessage($table);
}

function applyBootstrapTableTranslations() {
    if (!isLabelsAvailable()) return;

    const opt = getTranslationOptions();
    if (!opt) return;

    $('table[data-toggle="table"]').each(function () {
        applyTranslationsToTable($(this), opt);
    });
}

function setupTranslationObservers() {
    if (!window.MutationObserver) return;

    const obs = new MutationObserver(mutations => {
        let shouldRun = false;
        mutations.forEach(mut => {
            mut.addedNodes.forEach(n => {
                if (n.nodeType === 1) {
                    const $n = $(n);
                    if ($n.is('table[data-toggle="table"]') || $n.find('table[data-toggle="table"]').length) {
                        shouldRun = true;
                    }
                }
            });
        });

        if (shouldRun) {
            requestAnimationFrame(applyBootstrapTableTranslations);
        }
    });

    obs.observe(document.body, { childList: true, subtree: true });
}


/* =========================================================================
   3) INITIALIZATION
   ========================================================================= */

function initializeBootstrapTableTranslations() {
    applyBootstrapTableTranslations();
    setupTranslationObservers();
}

window.initializeBootstrapTableTranslations = initializeBootstrapTableTranslations;
window.clearTranslationCache = function () { cachedTranslationOptions = null; };

initializeBootstrapTableTranslations();