<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

class AdminPanelSanitizer implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Sanitize POST data
        $post = $request->getPost();
        foreach ($post as $key => $value) {
            if (is_string($value)) {
                $post[$key] = $this->sanitizeInput($value);
            }
        }
        $request->setGlobal('post', $post);

        // Sanitize GET data
        $get = $request->getGet();
        foreach ($get as $key => $value) {
            if (is_string($value)) {
                $get[$key] = $this->sanitizeInput($value);
            }
        }
        $request->setGlobal('get', $get);

        return $request;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Do nothing after the controller is executed
    }

    // private function sanitizeInput($input)
    // {
    //     // Remove all HTML tags except for a whitelist
    //     $input = strip_tags($input, '<p><br><strong><em><ul><ol><li><a><h1><h2><h3><h4><h5><h6>><iframe>');

    //     // Convert special characters to HTML entities
    //     $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');

    //     return $input;
    // }

    /**
     * Sanitize input value to prevent XSS attacks
     * 
     * This method removes dangerous HTML/JavaScript while allowing
     * safe formatting tags for rich content.
     * 
     * @param string $input The input value to sanitize
     * @return string The sanitized input
     */
    private function sanitizeInput($input)
    {
        // First, remove any script tags and event handlers completely
        // Remove script tags and their content
        $input = preg_replace('#<script[^>]*>.*?</script>#is', '', $input);

        // Remove event handlers (onclick, onerror, etc.)
        $input = preg_replace('#\s*on\w+\s*=\s*["\'][^"\']*["\']#i', '', $input);
        $input = preg_replace('#\s*on\w+\s*=\s*[^\s>]+#i', '', $input);

        // Remove javascript: and vbscript: protocols
        $input = preg_replace('#javascript:#i', '', $input);
        $input = preg_replace('#vbscript:#i', '', $input);
        $input = preg_replace('#data:text/html#i', '', $input);

        // Remove iframe tags completely (they can be dangerous)
        $input = preg_replace('#<iframe[^>]*>.*?</iframe>#is', '', $input);

        // Remove object and embed tags (can execute code)
        $input = preg_replace('#<object[^>]*>.*?</object>#is', '', $input);
        $input = preg_replace('#<embed[^>]*>.*?</embed>#is', '', $input);

        // Allow only safe HTML tags for formatting
        // Removed iframe from allowed tags for security
        $allowedTags = '<p><br><strong><em><ul><ol><li><a><h1><h2><h3><h4><h5><h6><span><div>';

        // Strip unwanted tags while allowing specific safe ones
        $input = strip_tags($input, $allowedTags);

        // Remove any remaining dangerous attributes from allowed tags
        // This removes style attributes that might contain expressions
        $input = preg_replace('#\s*style\s*=\s*["\'][^"\']*expression[^"\']*["\']#i', '', $input);
        $input = preg_replace('#\s*style\s*=\s*["\'][^"\']*javascript[^"\']*["\']#i', '', $input);

        // Clean up any remaining dangerous patterns
        $input = preg_replace('#<[^>]*\s*on\w+\s*=[^>]*>#i', '', $input);

        return $input;
    }
}
