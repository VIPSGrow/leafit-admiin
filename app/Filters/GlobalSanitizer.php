<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

/**
 * Global Sanitizer Filter
 * 
 * Enterprise-level XSS protection middleware for CodeIgniter 4.
 * This filter sanitizes ALL incoming GET and POST data before it reaches controllers.
 * 
 * Features:
 * - Recursively sanitizes nested arrays
 * - Removes script tags, inline JS attributes, and dangerous protocols
 * - Preserves LaTeX mathematical expressions exactly
 * - Allows excluded fields to keep raw HTML
 * - Overwrites superglobals for complete protection
 * 
 * @package App\Filters
 */
class GlobalSanitizer implements FilterInterface
{
    private $allowedRawCharacters = ["'", "&", "-", "_", ".", ",", "/", "\\", "!"];

    /**
     * Fields that are completely excluded from sanitization
     * These fields pass through completely unchanged - no sanitization at all
     * 
     * This is used for sensitive fields like passwords and phone numbers
     * that should never be modified by the sanitizer.
     * 
     * @var array
     */
    private $completelyExcludedFields = [
        // Password fields - must pass through unchanged for security
        'password',
        'old', // Old password field
        'new', // New password field
        'new_password',
        'new_confirm', // Password confirmation field
        'password_confirm',
        'confirm_password',
        'current_password',
        'old_password',

        // Phone number fields - must pass through unchanged to preserve formatting
        'phone',
        'mobile',
        'mobile_number',
        'phone_number',
        'telephone',
        'tel',
        'country_code', // Country code for phone numbers

        // SEO keywords fields - must pass through unchanged to avoid escaping quotes
        // Keywords are stored as comma-separated strings or JSON, no HTML content
        'seo_keywords', // SEO keywords field
        'meta_keywords', // Alternative SEO keywords field name
        'keywords', // Generic keywords field
        'tags',

        // JSON config fields - must pass through unchanged so json_decode works
        'file_config',

        // Schema markup fields - JSON-LD structured data, must pass through unchanged
        // htmlspecialchars would corrupt the quotes/braces needed for valid JSON-LD
        'schema_markup',
        'seo_schema_markup',

        // Map embed fields - iframe HTML must reach controller intact for src extraction
        'company_map_location',
    ];

    /**
     * Fields that are allowed to contain raw HTML
     * These fields will have script tags removed but HTML preserved
     * 
     * This list includes all fields that use TinyMCE or other rich text editors
     * found throughout the project (provider, service, blog, email templates, content pages).
     * 
     * To add more excluded fields, use the addExcludedField() method
     * or modify this array directly.
     * 
     * @var array
     */
    private $excludedFields = [
        // Generic content fields
        'page_content',
        'content_value',
        'content',
        'body',
        'html_content',
        'rich_text',
        'editor_content',

        // Title fields (posted as title[lang] arrays) — must not be htmlspecialchars-encoded
        'title',

        // Blog fields (uses TinyMCE)
        'description', // Blog description field

        // Provider/Partner fields (uses TinyMCE)
        'long_description', // Provider and service long descriptions

        // Email template fields (uses TinyMCE)
        'template', // Email template content
        'translations', // Email template translations array (contains template field)

        // Content page fields (uses TinyMCE)
        'contact_us', // Contact us page content
        'terms_conditions', // Terms and conditions page
        'privacy_policy', // Privacy policy page
        'customer_terms_conditions', // Customer terms and conditions
        'customer_privacy_policy', // Customer privacy policy
        'handyman_terms_conditions', // Handyman terms and conditions
        'handyman_privacy_policy', // Handyman privacy policy
        'about_us', // About us page content
        'system_timezone_gmt',
        'system_timezone',

        // Note: company_map_location moved to $completelyExcludedFields for iframe passthrough
        // Note: seo_keywords moved to $completelyExcludedFields to avoid escaping
    ];

    /**
     * LaTeX patterns to preserve
     * Matches inline math ($...$), display math ($$...$$), and environments
     * 
     * @var array
     */
    private $latexPatterns = [
        // Inline math: $...$ (non-greedy to avoid matching across paragraphs)
        '/\$([^$\n]+?)\$/',
        // Display math: $$...$$
        '/\$\$([^$]+?)\$\$/s',
        // LaTeX environments: \begin{...}...\end{...}
        '/\\\begin\{[^}]+\}(.*?)\\\end\{[^}]+\}/s',
        // LaTeX commands: \command{...} or \command[...]
        '/\\\[a-zA-Z]+\{([^}]*)\}/',
        '/\\\[a-zA-Z]+\[([^\]]*)\]/',
    ];

    /**
     * Execute sanitization before controller runs
     * 
     * This method processes all GET and POST data, sanitizes it recursively,
     * and overwrites both the request object and PHP superglobals.
     * 
     * @param IncomingRequest $request The incoming request
     * @param array|null $arguments Filter arguments (unused)
     * @return RequestInterface
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        if (!$request instanceof IncomingRequest) {
            return $request;
        }

        // Get all POST and GET data
        $postData = $request->getPost();
        $getData = $request->getGet();

        // Recursively sanitize POST data
        $sanitizedPost = $this->sanitizeRecursive($postData, 'post');

        // Recursively sanitize GET data
        $sanitizedGet = $this->sanitizeRecursive($getData, 'get');

        // Update the request object with sanitized data
        $request->setGlobal('post', $sanitizedPost);
        $request->setGlobal('get', $sanitizedGet);

        // Overwrite PHP superglobals for complete protection
        // This ensures any code accessing $_POST, $_GET, or $_REQUEST directly gets sanitized data
        $_POST = $sanitizedPost;
        $_GET = $sanitizedGet;
        $_REQUEST = array_merge($sanitizedGet, $sanitizedPost);

        return $request;
    }

    /**
     * No action needed after controller execution
     * 
     * @param RequestInterface $request The request
     * @param ResponseInterface $response The response
     * @param array|null $arguments Filter arguments (unused)
     * @return void
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No action needed after controller execution
    }

    /**
     * Add a field to the excluded fields list
     * 
     * This allows fields to preserve HTML content while still removing
     * dangerous script tags and event handlers.
     * 
     * @param string|array $field Field name(s) to exclude from HTML stripping
     * @return void
     */
    public function addExcludedField($field)
    {
        if (is_array($field)) {
            $this->excludedFields = array_merge($this->excludedFields, $field);
        } else {
            $this->excludedFields[] = $field;
        }

        // Remove duplicates
        $this->excludedFields = array_unique($this->excludedFields);
    }

    /**
     * Get the list of excluded fields
     * 
     * @return array List of field names that preserve HTML
     */
    public function getExcludedFields()
    {
        return $this->excludedFields;
    }

    /**
     * Recursively sanitize arrays and strings
     * 
     * This method handles nested arrays of any depth and sanitizes
     * each string value while preserving array structure.
     * 
     * @param mixed $data The data to sanitize (array or string)
     * @param string $context The context ('post' or 'get') for determining field paths
     * @param string $fieldPath Current field path for excluded field checking
     * @return mixed Sanitized data with same structure
     */
    private function sanitizeRecursive($data, $context = 'post', $fieldPath = '')
    {
        // Check if this field is completely excluded from sanitization
        // If so, return it completely unchanged (no sanitization at all)
        if ($this->isCompletelyExcludedField($fieldPath)) {
            return $data;
        }

        // Handle arrays recursively
        if (is_array($data)) {
            $sanitized = [];
            foreach ($data as $key => $value) {
                // Build field path for excluded field checking
                $currentPath = $fieldPath ? $fieldPath . '.' . $key : $key;

                // Recursively sanitize nested values
                $sanitized[$key] = $this->sanitizeRecursive($value, $context, $currentPath);
            }
            return $sanitized;
        }

        // Handle strings
        if (is_string($data)) {
            // Check if this field is in the excluded list (for HTML preservation)
            $isExcluded = $this->isExcludedField($fieldPath);

            // Sanitize the string value
            return $this->sanitizeString($data, $isExcluded);
        }

        // Return other types (int, float, bool, null) as-is
        return $data;
    }

    /**
     * Check if a field path is completely excluded from sanitization
     * 
     * These fields pass through completely unchanged - no sanitization at all.
     * Used for sensitive fields like passwords and phone numbers.
     * 
     * Supports both direct field names and nested paths (e.g., 'user.password')
     * 
     * @param string $fieldPath The field path to check
     * @return bool True if field should pass through completely unchanged
     */
    private function isCompletelyExcludedField($fieldPath)
    {
        // Check exact match
        if (in_array($fieldPath, $this->completelyExcludedFields, true)) {
            return true;
        }

        // Check if any completely excluded field is part of the path
        // This handles nested fields like 'user.password' or 'data.phone'
        foreach ($this->completelyExcludedFields as $excluded) {
            if (
                strpos($fieldPath, $excluded) === 0 ||
                strpos($fieldPath, '.' . $excluded) !== false ||
                strpos($fieldPath, $excluded . '.') !== false
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a field path is in the excluded fields list
     * 
     * Supports both direct field names and nested paths (e.g., 'content.body')
     * 
     * @param string $fieldPath The field path to check
     * @return bool True if field should preserve HTML
     */
    private function isExcludedField($fieldPath)
    {
        // Check exact match
        if (in_array($fieldPath, $this->excludedFields, true)) {
            return true;
        }

        // Check if any excluded field is part of the path
        foreach ($this->excludedFields as $excluded) {
            if (
                strpos($fieldPath, $excluded) === 0 ||
                strpos($fieldPath, '.' . $excluded) !== false ||
                strpos($fieldPath, $excluded . '.') !== false
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sanitize a string value to prevent XSS attacks
     * 
     * This method:
     * - Preserves LaTeX mathematical expressions exactly
     * - Removes script tags completely
     * - Removes inline JavaScript event handlers
     * - Removes dangerous protocols (javascript:, vbscript:, etc.)
     * - Strips HTML tags (unless field is excluded)
     * 
     * @param string $input The input string to sanitize
     * @param bool $allowHtml Whether to allow HTML tags (for excluded fields)
     * @return string The sanitized string
     */
    private function sanitizeString($input, $allowHtml = false)
    {
        // Step 0: Allow single quote, plus sign, or double quote to pass through unchanged
        // These single-character symbols should not be escaped
        // Check if input is exactly one character and it's one of these allowed symbols
        if (strlen($input) === 1 && in_array($input, ["'", '+', '"'], true)) {
            return $input;
        }

        // Step 1: Extract and preserve LaTeX content
        // We'll replace LaTeX with placeholders, sanitize, then restore
        // Using a unique prefix to avoid collisions with user input
        $latexPlaceholders = [];
        $placeholderIndex = 0;
        $uniquePrefix = '___LATEX_' . md5($input) . '_';

        // Extract all LaTeX patterns and replace with unique placeholders
        foreach ($this->latexPatterns as $pattern) {
            $input = preg_replace_callback($pattern, function ($matches) use (&$latexPlaceholders, &$placeholderIndex, $uniquePrefix) {
                $placeholder = $uniquePrefix . $placeholderIndex . '___';
                $latexPlaceholders[$placeholder] = $matches[0]; // Store original LaTeX
                $placeholderIndex++;
                return $placeholder;
            }, $input);
        }

        // Step 2: Remove script tags - but only for excluded fields
        // For non-excluded fields, we'll convert script tags to entities in Step 7
        // For excluded fields, we remove script tags to keep HTML safe but script-free
        if ($allowHtml) {
            // For excluded fields (rich text editors), remove script tags completely
            // This regex matches <script> tags with any attributes and all content inside
            $input = preg_replace('#<script[^>]*>.*?</script>#is', '', $input);

            // Also remove script tags that might be malformed or self-closing
            $input = preg_replace('#<script[^>]*>.*?<#is', '', $input);
            $input = preg_replace('#<script[^>]*/?>#i', '', $input);
        }
        // For non-excluded fields, script tags will pass through and be converted to entities in Step 7

        // Step 3: Remove all inline JavaScript event handlers
        // Matches onclick, onerror, onload, etc. with various quote styles
        $input = preg_replace('#\s*on\w+\s*=\s*["\'][^"\']*["\']#i', '', $input);
        $input = preg_replace('#\s*on\w+\s*=\s*[^\s>]+#i', '', $input);

        // Remove event handlers that might be in attribute lists
        $input = preg_replace('#<[^>]*\s+on\w+\s*=[^>]*>#i', '', $input);

        // Step 4: Remove dangerous protocols from links and other attributes
        // Remove javascript: protocol
        $input = preg_replace('#javascript\s*:#i', '', $input);

        // Remove vbscript: protocol
        $input = preg_replace('#vbscript\s*:#i', '', $input);

        // Remove data:text/html and data:text/javascript
        $input = preg_replace('#data\s*:\s*text/(html|javascript)#i', '', $input);

        // Remove expression() in CSS (IE-specific XSS vector)
        $input = preg_replace('#expression\s*\(#i', '', $input);

        // Step 5: Remove dangerous HTML elements
        // Remove iframe tags completely
        $input = preg_replace('#<iframe[^>]*>.*?</iframe>#is', '', $input);
        $input = preg_replace('#<iframe[^>]*/?>#i', '', $input);

        // Remove object and embed tags (can execute code)
        $input = preg_replace('#<object[^>]*>.*?</object>#is', '', $input);
        $input = preg_replace('#<embed[^>]*>.*?</embed>#is', '', $input);

        // Remove form tags (prevents form injection attacks)
        $input = preg_replace('#<form[^>]*>.*?</form>#is', '', $input);

        // Remove input tags with dangerous types
        $input = preg_replace('#<input[^>]*type\s*=\s*["\']?file["\']?[^>]*>#i', '', $input);

        // Step 6: Remove dangerous style attributes
        // Remove style attributes containing expression or javascript
        $input = preg_replace('#\s*style\s*=\s*["\'][^"\']*expression[^"\']*["\']#i', '', $input);
        $input = preg_replace('#\s*style\s*=\s*["\'][^"\']*javascript[^"\']*["\']#i', '', $input);
        $input = preg_replace('#\s*style\s*=\s*["\'][^"\']*@import[^"\']*["\']#i', '', $input);

        // Step 7: Handle HTML for excluded vs non-excluded fields
        if (!$allowHtml) {
            // For non-excluded fields, convert HTML to entities instead of stripping
            // This preserves the content for display while preventing XSS
            // Script tags were NOT removed in Step 2, so they'll be converted to entities here

            // Convert all HTML characters to entities (including script tags)
            // This makes HTML safe to display without executing
            // htmlspecialchars converts: < > & " ' to &lt; &gt; &amp; &quot; &#039;
            // Example: <script>alert('1')</script> becomes &lt;script&gt;alert('1')&lt;/script&gt;
            // ENT_QUOTES handles both single and double quotes
            // ENT_HTML5 uses HTML5 entity encoding
            $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $input = $this->restoreAllowedEntities($input, $this->allowedRawCharacters);
        } else {
            // For excluded fields, only allow safe HTML tags
            // Define safe tags for rich text editors (TinyMCE / CKEditor)
            $allowedTags = '<p><br><strong><em><b><i><u><s><del><ins><mark><sub><sup>'
                . '<ul><ol><li>'
                . '<a><img><figure><figcaption><picture><source>'
                . '<h1><h2><h3><h4><h5><h6>'
                . '<span><div><section><article><aside><header><footer>'
                . '<blockquote><pre><code><hr>'
                . '<table><caption><colgroup><col><thead><tbody><tfoot><tr><td><th>'
                . '<video><audio>';

            // Strip unwanted tags while allowing specific safe ones
            $input = strip_tags($input, $allowedTags);

            // Clean up any remaining dangerous attributes from allowed tags
            // Remove any remaining event handlers
            $input = preg_replace('#<[^>]*\s+on\w+\s*=[^>]*>#i', '', $input);

            // Remove javascript: from href attributes
            $input = preg_replace('#href\s*=\s*["\']?\s*javascript\s*:[^"\'>\s]*#i', 'href="#"', $input);

            // Remove dangerous protocols from src attributes.
            // Allow data:image/* (base64 images) so TinyMCE pasted images are preserved for email.
            // Only remove javascript:, vbscript:, and data:text/html or data:text/javascript.
            $input = preg_replace('#src\s*=\s*["\']?\s*(javascript\s*:[^"\'>\s]*|vbscript\s*:[^"\'>\s]*|data\s*:(?!image/)[^"\'>\s]*)#i', '', $input);
        }

        // Step 8: Clean up any remaining dangerous patterns
        // Remove any tags that still have event handlers
        $input = preg_replace('#<[^>]*\s*on\w+\s*=[^>]*>#i', '', $input);

        // Remove any remaining script-like content
        $input = preg_replace('#<[^>]*script[^>]*>#i', '', $input);

        // Step 9: Restore LaTeX placeholders with original content
        foreach ($latexPlaceholders as $placeholder => $originalLatex) {
            $input = str_replace($placeholder, $originalLatex, $input);
        }

        // Step 10: Clean up extra whitespace that might have been created
        // Replace multiple spaces with single space (but preserve LaTeX spacing)
        $input = preg_replace('/[ \t]+/', ' ', $input);

        // Clean up multiple newlines (but preserve intentional line breaks)
        $input = preg_replace('/\n{3,}/', "\n\n", $input);

        return trim($input);
    }

    /**
     * Revert selected HTML entities back to raw characters.
     * Extensible for future languages/locales
     */
    private function restoreAllowedEntities(string $input, array $allowed = ["'"]): string
    {
        $map = [
            "'" => ['&#039;', '&apos;'],
            '"' => ['&quot;'],
            '&' => ['&amp;'],   // if someday you want raw &
            // Add more as needed...
        ];

        foreach ($allowed as $char) {
            if (!isset($map[$char])) {
                continue;
            }
            foreach ($map[$char] as $entity) {
                $input = str_replace($entity, $char, $input);
            }
        }

        return $input;
    }
}
