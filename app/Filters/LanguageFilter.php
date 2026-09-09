<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;

/**
 * Language Filter
 * 
 * This filter detects language from request headers and sets the session language
 * to ensure proper translation handling for API responses.
 * 
 * IMPORTANT: This filter should ONLY be applied to API routes (/api/v1/* and /partner/api/v1/*)
 * and NOT to admin panel routes (/admin/*) or partner panel routes (/partner/*).
 * 
 * @package App\Filters
 */
class LanguageFilter implements FilterInterface
{
    /**
     * Set language from request headers before controller execution
     * 
     * @param RequestInterface $request The incoming request
     * @param array|null $arguments Filter arguments
     * @return RequestInterface
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        try {
            // Get session and language services
            $session = \Config\Services::session();
            $language = \Config\Services::language();

            // Get current language from request headers
            $requestedLanguage = $this->getLanguageFromRequest($request);

            // Get default language from database
            $defaultLanguage = $this->getDefaultLanguage();

            // Validate if the requested language is supported
            if ($this->isLanguageSupported($requestedLanguage)) {
                // Set session language
                $session->set('lang', $requestedLanguage);
                $session->set('language', $requestedLanguage);

                // Set language service locale
                $language->setLocale($requestedLanguage);

                // Get RTL setting for the language
                $isRtl = $this->getRtlSetting($requestedLanguage);
                $session->set('is_rtl', $isRtl);

                // Log language detection for debugging
                // log_message('info', "Language filter: Set language to '{$requestedLanguage}' from request headers");
            } else {
                // Use default language if requested language is not supported
                $session->set('lang', $defaultLanguage);
                $session->set('language', $defaultLanguage);
                $language->setLocale($defaultLanguage);

                // Get RTL setting for default language
                $isRtl = $this->getRtlSetting($defaultLanguage);
                $session->set('is_rtl', $isRtl);

                // log_message('info', "Language filter: Requested language '{$requestedLanguage}' not supported, using default '{$defaultLanguage}'");
            }
        } catch (\Exception $e) {
            // Log error but don't break the request
            log_message('error', 'Language filter error: ' . $e->getMessage());

            // Set fallback language
            $session = \Config\Services::session();
            $language = \Config\Services::language();
            $session->set('lang', 'en');
            $session->set('language', 'en');
            $language->setLocale('en');
            $session->set('is_rtl', 0);
        }

        return $request;
    }

    /**
     * After controller execution (no action needed)
     * 
     * @param RequestInterface $request The request
     * @param ResponseInterface $response The response
     * @param array|null $arguments Filter arguments
     * @return ResponseInterface
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No action needed after controller execution
        return $response;
    }

    /**
     * Get language from request headers
     * 
     * @param RequestInterface $request
     * @return string
     */
    private function getLanguageFromRequest(RequestInterface $request): string
    {
        try {
            // Check Content-Language header first
            $contentLanguage = $request->getHeaderLine('Content-Language');
            if (!empty($contentLanguage)) {
                // Extract language code (e.g., "en-US" -> "en")
                $languageCode = explode('-', $contentLanguage)[0];
                return strtolower($languageCode);
            }

            // Check Accept-Language header as fallback
            $acceptLanguage = $request->getHeaderLine('Accept-Language');
            if (!empty($acceptLanguage)) {
                // Parse Accept-Language header (e.g., "en-US,en;q=0.9,es;q=0.8")
                $languages = explode(',', $acceptLanguage);
                if (!empty($languages[0])) {
                    $languageCode = explode('-', trim($languages[0]))[0];
                    return strtolower($languageCode);
                }
            }

            return 'en'; // Default fallback
        } catch (\Exception $e) {
            log_message('error', 'Error getting language from request: ' . $e->getMessage());
            return 'en';
        }
    }

    /**
     * Get default language from database
     * 
     * @return string
     */
    private function getDefaultLanguage(): string
    {
        try {
            $defaultLanguage = fetch_details('languages', ['is_default' => '1']);
            if (!empty($defaultLanguage) && isset($defaultLanguage[0]['code'])) {
                return $defaultLanguage[0]['code'];
            }
            return 'en';
        } catch (\Exception $e) {
            log_message('error', 'Error getting default language: ' . $e->getMessage());
            return 'en';
        }
    }

    /**
     * Check if language is supported
     * 
     * @param string $languageCode
     * @return bool
     */
    private function isLanguageSupported(string $languageCode): bool
    {
        try {
            $languages = fetch_details('languages', ['code' => $languageCode]);
            return !empty($languages);
        } catch (\Exception $e) {
            log_message('error', 'Error checking language support: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get RTL setting for language
     * 
     * @param string $languageCode
     * @return int
     */
    private function getRtlSetting(string $languageCode): int
    {
        try {
            $languageData = fetch_details('languages', ['code' => $languageCode], ['is_rtl']);
            if (!empty($languageData) && isset($languageData[0]['is_rtl'])) {
                return (int) $languageData[0]['is_rtl'];
            }
            return 0; // Default to LTR
        } catch (\Exception $e) {
            log_message('error', 'Error getting RTL setting: ' . $e->getMessage());
            return 0;
        }
    }
}
