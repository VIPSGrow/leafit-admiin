<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

/**
 * Output Escaper Filter
 * 
 * This filter escapes HTML/JavaScript in response output to prevent XSS attacks.
 * It processes the response body and escapes unescaped user content.
 * 
 * This filter should run AFTER controllers to escape output before sending to browser.
 * 
 * @package App\Filters
 */
class OutputEscaper implements FilterInterface
{
    /**
     * Before controller execution - no action needed
     * 
     * @param RequestInterface $request The incoming request
     * @param array|null $arguments Filter arguments
     * @return RequestInterface
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        // No action needed before controller execution
        return $request;
    }

    /**
     * After controller execution - escape output in response
     * 
     * This method processes the response body and escapes HTML entities
     * in user-entered text fields to prevent XSS attacks.
     * 
     * @param RequestInterface $request The request
     * @param ResponseInterface $response The response
     * @param array|null $arguments Filter arguments
     * @return ResponseInterface
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Only process HTML responses
        $contentType = $response->getHeaderLine('Content-Type');
        if (strpos($contentType, 'text/html') === false) {
            // Not HTML content, skip processing
            return $response;
        }

        // Get the response body
        $body = $response->getBody();

        // Skip if body is empty
        if (empty($body)) {
            return $response;
        }

        // Process the body to escape unescaped content
        // This is a safety net - ideally views should escape, but this catches any missed cases
        $escapedBody = $this->escapeResponseContent($body);

        // Set the escaped body back to response
        $response->setBody($escapedBody);

        return $response;
    }

    /**
     * Escape HTML content in response body
     * 
     * This method identifies and escapes dangerous HTML/JavaScript patterns
     * in form fields and text content to prevent XSS attacks.
     * 
     * Note: This is a safety measure. Views should use esc() or htmlspecialchars()
     * for proper escaping. This filter catches any cases that were missed.
     * 
     * Strategy: Only escape content that contains dangerous patterns (HTML tags,
     * JavaScript, event handlers) and isn't already escaped. This prevents
     * double-escaping and breaking legitimate HTML.
     * 
     * @param string $body The response body HTML
     * @return string The escaped response body
     */
    private function escapeResponseContent($body)
    {
        // Check if content is already HTML-escaped (contains HTML entities)
        $isAlreadyEscaped = function ($text) {
            // If text contains HTML entities, it's likely already escaped
            return preg_match('/&(lt|gt|amp|quot|#\d+);/i', $text);
        };

        // Check if content contains dangerous patterns
        $isDangerous = function ($text) {
            // Check for HTML tags, JavaScript, or event handlers
            return preg_match('/<[^>]+>|javascript:|on\w+\s*=|data:text\/html/i', $text);
        };

        // Pattern 1: Escape dangerous content in value attributes
        // Only escape if content is dangerous AND not already escaped
        $body = preg_replace_callback(
            '/value=(["\'])(.*?)\1/i',
            function ($matches) use ($isDangerous, $isAlreadyEscaped) {
                $quote = $matches[1];
                $value = $matches[2];

                // Only escape if dangerous and not already escaped
                if ($isDangerous($value) && !$isAlreadyEscaped($value)) {
                    return 'value=' . $quote . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . $quote;
                }
                return $matches[0];
            },
            $body
        );

        // Pattern 2: Escape dangerous content in placeholder attributes
        $body = preg_replace_callback(
            '/placeholder=(["\'])(.*?)\1/i',
            function ($matches) use ($isDangerous, $isAlreadyEscaped) {
                $quote = $matches[1];
                $value = $matches[2];

                if ($isDangerous($value) && !$isAlreadyEscaped($value)) {
                    return 'placeholder=' . $quote . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . $quote;
                }
                return $matches[0];
            },
            $body
        );

        // Pattern 3: Escape dangerous content in title attributes
        $body = preg_replace_callback(
            '/title=(["\'])(.*?)\1/i',
            function ($matches) use ($isDangerous, $isAlreadyEscaped) {
                $quote = $matches[1];
                $value = $matches[2];

                if ($isDangerous($value) && !$isAlreadyEscaped($value)) {
                    return 'title=' . $quote . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . $quote;
                }
                return $matches[0];
            },
            $body
        );

        // Pattern 4: Escape dangerous content in textarea tags
        // This is more complex - we need to preserve the textarea tag structure
        $body = preg_replace_callback(
            '/<textarea([^>]*)>(.*?)<\/textarea>/is',
            function ($matches) use ($isDangerous, $isAlreadyEscaped) {
                $attributes = $matches[1];
                $content = $matches[2];

                // Only escape if content is dangerous and not already escaped
                if ($isDangerous($content) && !$isAlreadyEscaped($content)) {
                    $escapedContent = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
                    return '<textarea' . $attributes . '>' . $escapedContent . '</textarea>';
                }
                return $matches[0];
            },
            $body
        );

        // Pattern 5: Escape dangerous content in other common form field attributes
        // Check alt, aria-label, and data-* attributes that might contain user input
        $body = preg_replace_callback(
            '/(alt|aria-label|data-[^=]+)=(["\'])(.*?)\2/i',
            function ($matches) use ($isDangerous, $isAlreadyEscaped) {
                $attr = $matches[1];
                $quote = $matches[2];
                $value = $matches[3];

                if ($isDangerous($value) && !$isAlreadyEscaped($value)) {
                    return $attr . '=' . $quote . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . $quote;
                }
                return $matches[0];
            },
            $body
        );

        // Pattern 6: Remove any remaining dangerous event handlers that might have slipped through
        // This is a final safety check - remove ALL event handlers
        $body = preg_replace(
            '/\s*on\w+\s*=\s*["\'][^"\']*["\']/i',
            '',
            $body
        );

        // Pattern 7: Remove javascript: and data: protocols in href/src/action attributes
        $body = preg_replace(
            '/(href|src|action)=["\'](javascript|data):[^"\']*["\']/i',
            '$1="#"',
            $body
        );

        // Pattern 8: Remove any <script> tags that might have been missed
        $body = preg_replace(
            '#<script[^>]*>.*?</script>#is',
            '',
            $body
        );

        // Pattern 9: Remove any <iframe> tags that might contain malicious content
        $body = preg_replace(
            '#<iframe[^>]*>.*?</iframe>#is',
            '',
            $body
        );

        return $body;
    }
}
