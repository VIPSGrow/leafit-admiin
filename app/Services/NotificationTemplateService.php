<?php

namespace App\Services;

use App\Models\Translated_email_template_model;
use App\Models\Translated_sms_template_model;
use App\Models\TranslatedNotificationTemplateModel;

/**
 * NotificationTemplateService
 * 
 * Handles template retrieval, translation support, and variable replacement
 * for Email and SMS notifications. FCM templates are deferred for future implementation.
 * 
 * This service provides a unified interface for working with notification templates,
 * ensuring consistent behavior across all notification channels.
 */
class NotificationTemplateService
{
    /**
     * Get email template with translation support
     * 
     * Fetches email template with multi-language support:
     * 1. First tries to get translation for current/requested language
     * 2. If not found, tries default language translation
     * 3. If no translations exist, uses original template from main table
     * 
     * @param string $type Email template type (e.g., 'booking_status_updated')
     * @param string|null $languageCode Specific language code (optional, uses current language if not provided)
     * @return array|false Template data with translated content or false if template not found
     */
    public function getEmailTemplate(string $type, ?string $languageCode = null): array|false
    {
        // Get language codes for fallback mechanism using helper functions
        $currentLanguage = $languageCode ?? get_current_language();
        $defaultLanguage = get_default_language();

        // Fetch base email template from database
        $db = \Config\Database::connect();
        $builder = $db->table('email_templates');
        $template_data = $builder->where('type', $type)->get()->getResultArray();

        if (empty($template_data)) {
            return false;
        }

        // Initialize with original template data
        $result = $template_data[0];

        // Try to get translated template for requested language
        $translationModel = new Translated_email_template_model();
        $translatedTemplate = $translationModel->getTranslatedTemplate($template_data[0]['id'], $currentLanguage);

        // If translation exists for current language, use it
        if (!empty($translatedTemplate)) {
            $result['template'] = !empty($translatedTemplate['template']) ? $translatedTemplate['template'] : $result['template'];
            $result['subject'] = !empty($translatedTemplate['subject']) ? $translatedTemplate['subject'] : $result['subject'];
        }
        // If no translation for current language, try default language (if different from current)
        else if ($currentLanguage !== $defaultLanguage) {
            $defaultTranslatedTemplate = $translationModel->getTranslatedTemplate($template_data[0]['id'], $defaultLanguage);
            if (!empty($defaultTranslatedTemplate)) {
                $result['template'] = !empty($defaultTranslatedTemplate['template']) ? $defaultTranslatedTemplate['template'] : $result['template'];
                $result['subject'] = !empty($defaultTranslatedTemplate['subject']) ? $defaultTranslatedTemplate['subject'] : $result['subject'];
            }
        }
        // If no translations available, use original template from main table (already set in $result)

        return $result;
    }

    /**
     * Get SMS template with translation support
     * 
     * Fetches SMS template with multi-language support:
     * 1. First tries to get translation for current/requested language
     * 2. If not found, tries default language translation
     * 3. If no translations exist, uses original template from main table
     * 
     * @param string $type SMS template type (e.g., 'booking_status_updated')
     * @param string|null $languageCode Specific language code (optional, uses current language if not provided)
     * @return array|false Template data with translated content or false if template not found
     */
    public function getSmsTemplate(string $type, ?string $languageCode = null): array|false
    {
        // Get language codes for fallback mechanism using helper functions
        $currentLanguage = $languageCode ?? get_current_language();
        $defaultLanguage = get_default_language();

        // Fetch base SMS template from database
        $db = \Config\Database::connect();
        $builder = $db->table('sms_templates');
        $template_data = $builder->where('type', $type)->get()->getResultArray();

        if (empty($template_data)) {
            return false;
        }

        // Initialize with original template data
        $result = $template_data[0];

        // Try to get translated template for requested language
        $translationModel = new Translated_sms_template_model();
        $translatedTemplate = $translationModel->getTranslatedTemplate($template_data[0]['id'], $currentLanguage);

        // If translation exists for current language, use it
        if (!empty($translatedTemplate)) {
            $result['template'] = !empty($translatedTemplate['template']) ? $translatedTemplate['template'] : $result['template'];
            $result['title'] = !empty($translatedTemplate['title']) ? $translatedTemplate['title'] : $result['title'];
        }
        // If no translation for current language, try default language (if different from current)
        else if ($currentLanguage !== $defaultLanguage) {
            $defaultTranslatedTemplate = $translationModel->getTranslatedTemplate($template_data[0]['id'], $defaultLanguage);
            if (!empty($defaultTranslatedTemplate)) {
                $result['template'] = !empty($defaultTranslatedTemplate['template']) ? $defaultTranslatedTemplate['template'] : $result['template'];
                $result['title'] = !empty($defaultTranslatedTemplate['title']) ? $defaultTranslatedTemplate['title'] : $result['title'];
            }
        }
        // gateway_template_ids comes from base sms_templates table (already in $result)

        return $result;
    }

    /**
     * Get the gateway-specific template/message ID for template-based SMS gateways.
     *
     * SMS template row may have gateway_template_ids (JSON object keyed by gateway name,
     * e.g. {"fast2sms": "111111"}). Used when the active gateway sends by template ID
     * instead of raw message body.
     *
     * @param array  $templateData Template row as returned by getSmsTemplate() (may include gateway_template_ids).
     * @param string $gateway      Gateway identifier (e.g. 'fast2sms'), must match key in gateway_template_ids.
     * @return string|null The template ID for this gateway, or null if not set or column not present.
     */
    public function getGatewayTemplateId(array $templateData, string $gateway): ?string
    {
        $raw = $templateData['gateway_template_ids'] ?? null;
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($raw)) {
            return null;
        }
        $id = $raw[$gateway] ?? null;
        return $id !== null && $id !== '' ? (string) $id : null;
    }

    /**
     * Get ordered variable values for SMS template (for template-ID-based gateways).
     *
     * Gateways like Fast2SMS DLT require variables in a fixed order (pipe-separated).
     * This method uses the template's parameters (JSON array of variable names) to build
     * an ordered list of values from context. Order matches the template's parameter list.
     *
     * @param array $templateData Template row as returned by getSmsTemplate() (must include 'type' and 'parameters').
     * @param array $context      Context data for variable extraction (user_id, booking_id, etc.).
     * @return array List of values in the same order as the template's parameters.
     */
    public function getOrderedVariableValuesForSms(array $templateData, array $context): array
    {
        $variables = $this->extractVariablesFromContext(
            $templateData['type'] ?? '',
            $context
        );
        $paramNames = $this->normalizeSmsTemplateParameters($templateData['parameters'] ?? '');
        $ordered = [];
        foreach ($paramNames as $name) {
            // Prefer variables extracted from context (rich, type-aware values).
            // If a variable was not added there but exists directly in the context,
            // fall back to the raw context value so gateways like 2factor that
            // require all template variables (e.g. customer_name, service_name,
            // contact_number) still receive them correctly.
            if (array_key_exists($name, $variables)) {
                $ordered[] = $variables[$name];
            } elseif (array_key_exists($name, $context)) {
                $ordered[] = $context[$name];
            } else {
                $ordered[] = '';
            }
        }
        return $ordered;
    }

    /**
     * Normalize SMS template parameters from DB (JSON string or array) to list of variable names.
     *
     * @param mixed $parameters Raw value from sms_templates.parameters
     * @return array List of parameter names in order
     */
    private function normalizeSmsTemplateParameters($parameters): array
    {
        if (is_array($parameters)) {
            return array_values($parameters);
        }
        if (! is_string($parameters) || trim($parameters) === '') {
            return [];
        }
        $decoded = json_decode($parameters, true);
        if (! is_array($decoded)) {
            return [];
        }
        return array_values($decoded);
    }

    /**
     * Get FCM template with translation support
     * 
     * Fetches FCM template with multi-language support:
     * 1. First tries to get translation for current/requested language
     * 2. If not found, tries default language translation
     * 3. If no translations exist, uses original template from main table
     * 
     * @param string $eventKey FCM template event key (e.g., 'booking_status_updated')
     * @param string|null $languageCode Specific language code (optional, uses current language if not provided)
     * @return array|false Template data with translated content or false if template not found
     */
    public function getFcmTemplate(string $eventKey, ?string $languageCode = null): array|false
    {
        // Get language codes for fallback mechanism using helper functions
        $currentLanguage = $languageCode ?? get_current_language();
        $defaultLanguage = get_default_language();

        // Fetch base FCM template from database using event_key
        $db = \Config\Database::connect();
        $builder = $db->table('notification_templates');
        $template_data = $builder->where('event_key', $eventKey)->get()->getResultArray();

        if (empty($template_data)) {
            return false;
        }

        // Initialize with original template data
        $result = $template_data[0];

        // Try to get translated template for requested language
        $translationModel = new TranslatedNotificationTemplateModel();
        $translatedTemplate = $translationModel->getTranslatedTemplate($template_data[0]['id'], $currentLanguage);

        // If translation exists for current language, use it
        if (!empty($translatedTemplate)) {
            $result['title'] = !empty($translatedTemplate['title']) ? $translatedTemplate['title'] : $result['title'];
            $result['body'] = !empty($translatedTemplate['body']) ? $translatedTemplate['body'] : $result['body'];
        }
        // If no translation for current language, try default language (if different from current)
        else if ($currentLanguage !== $defaultLanguage) {
            $defaultTranslatedTemplate = $translationModel->getTranslatedTemplate($template_data[0]['id'], $defaultLanguage);
            if (!empty($defaultTranslatedTemplate)) {
                $result['title'] = !empty($defaultTranslatedTemplate['title']) ? $defaultTranslatedTemplate['title'] : $result['title'];
                $result['body'] = !empty($defaultTranslatedTemplate['body']) ? $defaultTranslatedTemplate['body'] : $result['body'];
            }
        }
        // If no translations available, use original template from main table (already set in $result)

        return $result;
    }

    /**
     * Replace template variables with actual values
     * 
     * Replaces placeholders like [[provider_name]], [[company_name]], etc.
     * with actual values from the provided variables array.
     * 
     * @param string $template Template string with placeholders
     * @param array $variables Associative array of variable names and values
     * @return string Template with variables replaced
     */
    public function replaceVariables(string $template, array $variables): string
    {
        foreach ($variables as $key => $value) {
            // Handle null values by replacing with empty string
            $replaceValue = $value !== null ? (string)$value : '';
            $template = str_replace("[[{$key}]]", $replaceValue, $template);
        }

        // Clean up any remaining placeholders that weren't replaced
        // This prevents placeholders from appearing in the final notification
        $template = preg_replace('/\[\[[^\]]+\]\]/', '', $template);

        return $template;
    }

    /**
     * Extract and prepare template variables from context
     * 
     * Automatically extracts variables from context array and prepares them
     * for template replacement. Handles special cases like provider names,
     * booking details, company settings, etc.
     * 
     * @param string $type Template type (for context-specific variable extraction)
     * @param array $context Context data containing user_id, booking_id, provider_id, etc.
     * @return array Prepared variables array ready for template replacement
     */
    public function extractVariablesFromContext(string $type, array $context): array
    {
        $variables = [];

        // Get company settings once using helper function
        $company_settings = get_settings('general_settings', true);
        $company_name = get_company_title_with_fallback($company_settings);

        // Basic variables that are always available
        $variables['company_name'] = $company_name;

        // Get public-facing site URL using reliable method
        // This handles proxies, load balancers, and containerized environments
        $variables['site_url'] = $this->getPublicSiteUrl();

        // Currency
        $currency = $company_settings['currency'] ?? 'USD';
        $variables['currency'] = $currency;

        // Company contact info using helper function
        $contact_us = getTranslatedSetting('contact_us', 'contact_us');
        $contact_info = !empty($contact_us) ? $contact_us : 'Contact us for more information';
        $variables['company_contact_info'] = htmlspecialchars_decode(strip_tags(html_entity_decode($contact_info)));

        // Provider/Partner information
        if (isset($context['provider_id']) && $context['provider_id'] !== null) {
            $variables['provider_id'] = $context['provider_id'];

            // Get provider name with translation support
            $provider_name = $this->getProviderName($context['provider_id']);
            if ($provider_name) {
                $variables['provider_name'] = $provider_name;
            }
        }

        // User information
        if (isset($context['user_id']) && $context['user_id'] !== null) {
            $variables['user_id'] = $context['user_id'];

            // Use helper function to fetch user details
            $user = fetch_details('users', ['id' => $context['user_id']]);
            if (!empty($user)) {
                $variables['user_name'] = $user[0]['username'] ?? '';
                // Also extract email and phone if available in user data
                if (isset($user[0]['email'])) {
                    $variables['user_email'] = $user[0]['email'] ?? '';

                    // Generate unsubscribe link when user_id and email are available
                    // This is used in admin custom emails and other email notifications
                    // Use getPublicSiteUrl() to ensure correct domain in all server environments
                    $siteUrl = $this->getPublicSiteUrl();
                    $variables['unsubscribe_link'] = $siteUrl . '/unsubscribe_link/' . unsubscribe_link_user_encrypt($context['user_id'], $user[0]['email']);
                }
                if (isset($user[0]['phone'])) {
                    $variables['user_phone'] = $user[0]['phone'] ?? '';
                }
            }
        }

        // User email and phone from context (for new user registration notifications)
        // These may be passed directly in context for immediate use
        if (isset($context['user_email']) && $context['user_email'] !== null) {
            $variables['user_email'] = $context['user_email'];

            // Generate unsubscribe link if user_id is also available in context
            // This handles cases where user_email is passed directly but user_id is also in context
            if (isset($context['user_id']) && $context['user_id'] !== null && !isset($variables['unsubscribe_link'])) {
                $siteUrl = $this->getPublicSiteUrl();
                $variables['unsubscribe_link'] = $siteUrl . '/unsubscribe_link/' . unsubscribe_link_user_encrypt($context['user_id'], $context['user_email']);
            }
        }
        if (isset($context['user_phone']) && $context['user_phone'] !== null) {
            $variables['user_phone'] = $context['user_phone'];
        }
        if (isset($context['user_name']) && $context['user_name'] !== null) {
            $variables['user_name'] = $context['user_name'];
        }

        // Amount
        if (isset($context['amount']) && $context['amount'] !== null) {
            $variables['amount'] = $context['amount'];
        }

        // Rating information (for new_rating_given_by_customer notifications)
        // Extract rating-related variables from context if present
        if (isset($context['rating']) && $context['rating'] !== null) {
            $variables['rating'] = $context['rating'];
        }

        // Booking/Order information
        if (isset($context['booking_id']) && $context['booking_id'] !== null) {
            // Use helper function to fetch booking details
            $booking = fetch_details('orders', ['id' => $context['booking_id']]);
            if (!empty($booking)) {
                $booking = $booking[0];
                $variables['booking_id'] = $booking['id'];
                $variables['booking_date'] = $booking['date_of_service'] ?? '';
                $variables['booking_time'] = $booking['starting_time'] ?? '';
                $variables['booking_status'] = $booking['status'] ?? '';
                $variables['booking_address'] = $booking['address'] ?? '';
                // Include total additional charge from order record when available
                if (isset($booking['total_additional_charge'])) {
                    $variables['total_additional_charge'] = $booking['total_additional_charge'];
                }

                // Get booking service names using helper function
                $services = fetch_details('order_services', ['order_id' => $context['booking_id']]);
                $service_names = '';
                if (!empty($services)) {
                    foreach ($services as $row) {
                        $service_names .= ($row['service_title'] ?? '') . ', ';
                    }
                    $service_names = rtrim($service_names, ', ');
                }
                $variables['booking_service_names'] = $service_names;
            }

            // Extract booking status-specific variables from context if provided
            // These are formatted values passed directly in context for status change notifications
            if (isset($context['date_of_service']) && $context['date_of_service'] !== null) {
                $variables['date_of_service'] = $context['date_of_service'];
            }
            if (isset($context['service_time']) && $context['service_time'] !== null) {
                $variables['service_time'] = $context['service_time'];
            }
            if (isset($context['final_total']) && $context['final_total'] !== null) {
                $variables['final_total'] = $context['final_total'];
            }
            if (isset($context['status_message']) && $context['status_message'] !== null) {
                $variables['status_message'] = $context['status_message'];
            }
            if (isset($context['previous_status']) && $context['previous_status'] !== null) {
                $variables['previous_status'] = $context['previous_status'];
            }
            if (isset($context['updated_by_name']) && $context['updated_by_name'] !== null) {
                $variables['updated_by_name'] = $context['updated_by_name'];
            }
            if (isset($context['updated_by_type']) && $context['updated_by_type'] !== null) {
                $variables['updated_by_type'] = $context['updated_by_type'];
            }
            // Allow context to override total additional charge when explicitly provided
            if (isset($context['total_additional_charge']) && $context['total_additional_charge'] !== null) {
                $variables['total_additional_charge'] = $context['total_additional_charge'];
            }
            // Override booking_status with translated version if provided in context
            if (isset($context['booking_status']) && $context['booking_status'] !== null) {
                $variables['booking_status'] = $context['booking_status'];
            }
        }

        // Custom Job Request information
        // Extract custom job request variables from context if present
        // These variables are used in email templates for new custom job request notifications
        if (isset($context['custom_job_request_id']) && $context['custom_job_request_id'] !== null) {
            $variables['custom_job_request_id'] = $context['custom_job_request_id'];
        }

        // Customer information for custom job requests
        if (isset($context['customer_name']) && $context['customer_name'] !== null) {
            $variables['customer_name'] = $context['customer_name'];
        }
        if (isset($context['customer_id']) && $context['customer_id'] !== null) {
            $variables['customer_id'] = $context['customer_id'];
        }

        // Service information for custom job requests
        if (isset($context['service_title']) && $context['service_title'] !== null) {
            $variables['service_title'] = $context['service_title'];
        }
        if (isset($context['service_short_description']) && $context['service_short_description'] !== null) {
            $variables['service_short_description'] = $context['service_short_description'];
        }

        // Category information for custom job requests and new category notifications
        if (isset($context['category_name']) && $context['category_name'] !== null) {
            $variables['category_name'] = $context['category_name'];
        }
        if (isset($context['category_id']) && $context['category_id'] !== null) {
            $variables['category_id'] = $context['category_id'];

            // If category_name not provided but category_id is, fetch category name with translation support
            if (!isset($context['category_name']) || empty($context['category_name'])) {
                $categoryModel = new \App\Models\Category_model();
                $defaultLanguage = get_default_language();
                $categoryName = $categoryModel->getTranslatedCategoryName($context['category_id'], $defaultLanguage);
                if (!empty($categoryName)) {
                    $variables['category_name'] = $categoryName;
                }
            }
        }

        // Subscription information (for subscription_changed notifications)
        if (isset($context['subscription_id']) && $context['subscription_id'] !== null) {
            $variables['subscription_id'] = $context['subscription_id'];
        }
        if (isset($context['subscription_name']) && $context['subscription_name'] !== null) {
            $variables['subscription_name'] = $context['subscription_name'];
        }
        if (isset($context['subscription_price']) && $context['subscription_price'] !== null) {
            $variables['subscription_price'] = $context['subscription_price'];
        }
        if (isset($context['subscription_duration']) && $context['subscription_duration'] !== null) {
            $variables['subscription_duration'] = $context['subscription_duration'];
        }
        // Also support 'duration' as an alias for subscription_duration (for subscription_purchased templates)
        if (isset($context['duration']) && $context['duration'] !== null) {
            $variables['duration'] = $context['duration'];
        }
        if (isset($context['purchase_date']) && $context['purchase_date'] !== null) {
            $variables['purchase_date'] = $context['purchase_date'];
        }
        if (isset($context['expiry_date']) && $context['expiry_date'] !== null) {
            $variables['expiry_date'] = $context['expiry_date'];
        }
        // Transaction ID for subscription purchases and other transactions
        if (isset($context['transaction_id']) && $context['transaction_id'] !== null) {
            $variables['transaction_id'] = $context['transaction_id'];
        }
        // Failure reason for failed payments
        if (isset($context['failure_reason']) && $context['failure_reason'] !== null) {
            $variables['failure_reason'] = $context['failure_reason'];
        }
        // Refund information (for payment_refund_executed notifications)
        if (isset($context['refund_id']) && $context['refund_id'] !== null) {
            $variables['refund_id'] = $context['refund_id'];
        }
        if (isset($context['processed_date']) && $context['processed_date'] !== null) {
            $variables['processed_date'] = $context['processed_date'];
        }
        // Order ID can also be used as booking_id
        if (isset($context['order_id']) && $context['order_id'] !== null) {
            $variables['order_id'] = $context['order_id'];
            // If booking_id not set, use order_id as booking_id
            if (!isset($variables['booking_id'])) {
                $variables['booking_id'] = $context['order_id'];
            }
        }

        // Price information for custom job requests
        if (isset($context['min_price']) && $context['min_price'] !== null) {
            $variables['min_price'] = $context['min_price'];
        }
        if (isset($context['max_price']) && $context['max_price'] !== null) {
            $variables['max_price'] = $context['max_price'];
        }
        // Counter price for partner bids on custom job requests
        // This variable is used in notification templates when a partner places a bid
        if (isset($context['counter_price']) && $context['counter_price'] !== null) {
            $variables['counter_price'] = $context['counter_price'];
        }

        // Date and time information for custom job requests
        if (isset($context['requested_start_date']) && $context['requested_start_date'] !== null) {
            $variables['requested_start_date'] = $context['requested_start_date'];
        }
        if (isset($context['requested_start_time']) && $context['requested_start_time'] !== null) {
            $variables['requested_start_time'] = $context['requested_start_time'];
        }
        if (isset($context['requested_end_date']) && $context['requested_end_date'] !== null) {
            $variables['requested_end_date'] = $context['requested_end_date'];
        }
        if (isset($context['requested_end_time']) && $context['requested_end_time'] !== null) {
            $variables['requested_end_time'] = $context['requested_end_time'];
        }

        // Service information (for provider edits service details notifications)
        // Extract service-related variables from context if present
        if (isset($context['service_id']) && $context['service_id'] !== null) {
            $variables['service_id'] = $context['service_id'];
        }
        if (isset($context['service_title']) && $context['service_title'] !== null) {
            $variables['service_title'] = $context['service_title'];
        }
        if (isset($context['service_description']) && $context['service_description'] !== null) {
            $variables['service_description'] = $context['service_description'];
        }
        if (isset($context['service_price']) && $context['service_price'] !== null) {
            $variables['service_price'] = $context['service_price'];
        }
        if (isset($context['service_discounted_price']) && $context['service_discounted_price'] !== null) {
            $variables['service_discounted_price'] = $context['service_discounted_price'];
        }

        // User query/contact form information
        // Extract query-related variables from context if present
        // These variables are used in templates for user_query_submitted notifications
        if (isset($context['customer_name']) && $context['customer_name'] !== null) {
            $variables['customer_name'] = $context['customer_name'];
        }
        if (isset($context['customer_email']) && $context['customer_email'] !== null) {
            $variables['customer_email'] = $context['customer_email'];
        }
        if (isset($context['query_subject']) && $context['query_subject'] !== null) {
            $variables['query_subject'] = $context['query_subject'];
        }
        if (isset($context['query_message']) && $context['query_message'] !== null) {
            $variables['query_message'] = $context['query_message'];
        }

        // Chat message information
        // Extract message-related variables from context if present
        // These variables are used in templates for new_message notifications
        // Works for: customer to admin, provider to admin, customer to provider, provider to customer
        if (isset($context['sender_name']) && $context['sender_name'] !== null) {
            $variables['sender_name'] = $context['sender_name'];
        }
        if (isset($context['sender_type']) && $context['sender_type'] !== null) {
            $variables['sender_type'] = $context['sender_type'];
        }
        if (isset($context['receiver_name']) && $context['receiver_name'] !== null) {
            $variables['receiver_name'] = $context['receiver_name'];
        }

        // OTP information (for OTP email and SMS notifications)
        // Extract OTP variable from context if present
        if (isset($context['otp']) && $context['otp'] !== null) {
            $variables['otp'] = $context['otp'];
        }
        if (isset($context['receiver_type']) && $context['receiver_type'] !== null) {
            $variables['receiver_type'] = $context['receiver_type'];
        }
        if (isset($context['message_content']) && $context['message_content'] !== null) {
            $variables['message_content'] = $context['message_content'];
        }
        if (isset($context['booking_id']) && $context['booking_id'] !== null) {
            $variables['booking_id'] = $context['booking_id'];
        }

        // User report information
        // Extract report-related variables from context if present
        // These variables are used in templates for user_reported notifications
        // Works for: customer reports provider, provider reports customer
        if (isset($context['reporter_name']) && $context['reporter_name'] !== null) {
            $variables['reporter_name'] = $context['reporter_name'];
        }
        if (isset($context['reporter_type']) && $context['reporter_type'] !== null) {
            $variables['reporter_type'] = $context['reporter_type'];
        }
        if (isset($context['reporter_id']) && $context['reporter_id'] !== null) {
            $variables['reporter_id'] = $context['reporter_id'];
        }
        if (isset($context['reported_user_name']) && $context['reported_user_name'] !== null) {
            $variables['reported_user_name'] = $context['reported_user_name'];
        }
        if (isset($context['reported_user_type']) && $context['reported_user_type'] !== null) {
            $variables['reported_user_type'] = $context['reported_user_type'];
        }
        if (isset($context['reported_user_id']) && $context['reported_user_id'] !== null) {
            $variables['reported_user_id'] = $context['reported_user_id'];
        }
        if (isset($context['report_reason']) && $context['report_reason'] !== null) {
            $variables['report_reason'] = $context['report_reason'];
        }
        if (isset($context['report_reason_id']) && $context['report_reason_id'] !== null) {
            $variables['report_reason_id'] = $context['report_reason_id'];
        }
        if (isset($context['additional_info']) && $context['additional_info'] !== null) {
            $variables['additional_info'] = $context['additional_info'];
        }
        if (isset($context['notification_message']) && $context['notification_message'] !== null) {
            $variables['notification_message'] = $context['notification_message'];
        }
        if (isset($context['action_message']) && $context['action_message'] !== null) {
            $variables['action_message'] = $context['action_message'];
        }

        // User blocking information
        // Extract blocking-related variables from context if present
        // These variables are used in templates for user_blocked notifications
        // Works for: customer blocks provider, provider blocks customer
        if (isset($context['blocker_name']) && $context['blocker_name'] !== null) {
            $variables['blocker_name'] = $context['blocker_name'];
        }
        if (isset($context['blocker_type']) && $context['blocker_type'] !== null) {
            $variables['blocker_type'] = $context['blocker_type'];
        }
        if (isset($context['blocker_id']) && $context['blocker_id'] !== null) {
            $variables['blocker_id'] = $context['blocker_id'];
        }
        if (isset($context['blocked_user_name']) && $context['blocked_user_name'] !== null) {
            $variables['blocked_user_name'] = $context['blocked_user_name'];
        }
        if (isset($context['blocked_user_type']) && $context['blocked_user_type'] !== null) {
            $variables['blocked_user_type'] = $context['blocked_user_type'];
        }
        if (isset($context['blocked_user_id']) && $context['blocked_user_id'] !== null) {
            $variables['blocked_user_id'] = $context['blocked_user_id'];
        }

        // Promo code information
        // Extract promo code-related variables from context if present
        // These variables are used in templates for promo_code_added notifications
        // Works for: provider adds promo code (notify admin), admin adds promo code (notify provider)
        if (isset($context['provider_name']) && $context['provider_name'] !== null) {
            $variables['provider_name'] = $context['provider_name'];
        }
        if (isset($context['provider_id']) && $context['provider_id'] !== null) {
            $variables['provider_id'] = $context['provider_id'];
        }
        if (isset($context['promo_code']) && $context['promo_code'] !== null) {
            $variables['promo_code'] = $context['promo_code'];
        }
        if (isset($context['promo_code_id']) && $context['promo_code_id'] !== null) {
            $variables['promo_code_id'] = $context['promo_code_id'];
        }
        if (isset($context['discount']) && $context['discount'] !== null) {
            $variables['discount'] = $context['discount'];
        }
        if (isset($context['discount_type']) && $context['discount_type'] !== null) {
            $variables['discount_type'] = $context['discount_type'];
            // Set discount type symbol (% or currency symbol)
            if ($context['discount_type'] == 'percentage') {
                $variables['discount_type_symbol'] = '%';
            } else {
                $currency = $company_settings['currency'] ?? 'USD';
                $variables['discount_type_symbol'] = $currency;
            }
        }
        if (isset($context['minimum_order_amount']) && $context['minimum_order_amount'] !== null) {
            $variables['minimum_order_amount'] = $context['minimum_order_amount'];
        }
        if (isset($context['max_discount_amount']) && $context['max_discount_amount'] !== null) {
            $variables['max_discount_amount'] = $context['max_discount_amount'];
        }
        if (isset($context['start_date']) && $context['start_date'] !== null) {
            $variables['start_date'] = $context['start_date'];
        }
        if (isset($context['end_date']) && $context['end_date'] !== null) {
            $variables['end_date'] = $context['end_date'];
        }
        if (isset($context['no_of_users']) && $context['no_of_users'] !== null) {
            $variables['no_of_users'] = $context['no_of_users'];
        }

        // Handle logo for email templates
        // Include logo if explicitly requested OR if user_id is present (for admin custom emails)
        // This ensures company_logo is available for admin panel email sending
        $shouldIncludeLogo = (isset($context['include_logo']) && $context['include_logo']) ||
            (isset($context['user_id']) && $context['user_id'] !== null);

        if ($shouldIncludeLogo) {
            $logo = $company_settings['logo'] ?? '';
            if (!empty($logo)) {
                $logoPath = "public/uploads/site/" . $logo;
                if (file_exists($logoPath)) {
                    // Always use fixed CID: company_logo (constant string, not filename-based)
                    $variables['company_logo'] = '<img src="cid:company_logo" alt="Company Logo">';
                } else {
                    $variables['company_logo'] = '';
                }
            } else {
                $variables['company_logo'] = '';
            }
        }

        // Blog information (for new_blog notifications)
        // Extract blog-related variables from context if present
        // These variables are used in templates for new_blog notifications
        if (isset($context['blog_id']) && $context['blog_id'] !== null) {
            $variables['blog_id'] = $context['blog_id'];

            // Fetch blog details to get title, slug, short_description, category
            $blog = fetch_details('blogs', ['id' => $context['blog_id']]);
            if (!empty($blog)) {
                $blog = $blog[0];

                // Get blog title (from main table or translations)
                if (isset($context['blog_title']) && $context['blog_title'] !== null) {
                    $variables['blog_title'] = $context['blog_title'];
                } else {
                    $variables['blog_title'] = $blog['title'] ?? '';
                }

                // Get blog slug
                if (isset($context['blog_slug']) && $context['blog_slug'] !== null) {
                    $variables['blog_slug'] = $context['blog_slug'];
                } else {
                    $variables['blog_slug'] = $blog['slug'] ?? '';
                }

                // Get blog short description
                if (isset($context['blog_short_description']) && $context['blog_short_description'] !== null) {
                    $variables['blog_short_description'] = $context['blog_short_description'];
                } else {
                    $variables['blog_short_description'] = $blog['short_description'] ?? '';
                }

                // Get blog category name with translation support
                if (isset($context['blog_category_name']) && $context['blog_category_name'] !== null) {
                    $variables['blog_category_name'] = $context['blog_category_name'];
                } else if (isset($blog['category_id']) && !empty($blog['category_id'])) {
                    // Fetch category name from database
                    $categoryData = fetch_details('blog_categories', ['id' => $blog['category_id']]);
                    if (!empty($categoryData)) {
                        $variables['blog_category_name'] = $categoryData[0]['name'] ?? '';
                    }
                }

                // Generate blog URL for redirecting to blog read screen
                // Format: base_url('blog-details/' . slug) or similar based on your routing
                if (isset($context['blog_url']) && $context['blog_url'] !== null) {
                    $variables['blog_url'] = $context['blog_url'];
                } else {
                    // Generate blog URL using slug
                    // Use getPublicSiteUrl() to ensure correct domain in all server environments
                    $siteUrl = $this->getPublicSiteUrl();
                    $blogSlug = $variables['blog_slug'] ?? '';
                    if (!empty($blogSlug)) {
                        $variables['blog_url'] = $siteUrl . '/blog-details/' . $blogSlug;
                    } else {
                        $variables['blog_url'] = $siteUrl . '/blog-details/' . $context['blog_id'];
                    }
                }
            }
        }
        return $variables;
    }

    /**
     * Get public-facing site URL that works reliably across different server environments
     * 
     * This method handles various server configurations:
     * - Behind proxies/load balancers (checks X-Forwarded-Host, X-Forwarded-Proto)
     * - Containerized environments
     * - CLI environments (queue workers, cron jobs)
     * - Direct server access
     * 
     * Priority order for CLI/Queue context:
     * 1. Site URL from general_settings (if configured by admin) - MOST RELIABLE FOR CLI
     * 2. Environment variable (APP_URL or SITE_URL from .env file) - RECOMMENDED FOR CLI
     * 3. APP_URL constant (from Constants.php) - fallback
     * 
     * Priority order for Web context:
     * 1. Site URL from general_settings (if configured by admin)
     * 2. Environment variable (APP_URL or SITE_URL from .env file)
     * 3. HTTP headers from proxy/load balancer (X-Forwarded-Host, X-Forwarded-Proto)
     * 4. HTTP_HOST header
     * 5. APP_URL constant (from Constants.php)
     * 6. base_url() with improved parsing (last resort, may return localhost in CLI)
     * 
     * @return string Public-facing site URL (without trailing slash)
     */
    public function getPublicSiteUrl(): string
    {
        $isCli = (php_sapi_name() === 'cli' || defined('STDIN'));

        // 1. DB setting (highest priority)
        $companySettings = get_settings('general_settings', true);
        if ($url = $this->normalizePublicUrl($companySettings['site_url'] ?? null)) {
            return $url;
        }

        // 2. .env variables
        $envUrl = env('APP_URL') ?: env('SITE_URL');
        if ($url = $this->normalizePublicUrl($envUrl)) {
            return $url;
        }

        // 3. CLI: APP_URL constant
        if ($isCli && defined('APP_URL')) {
            if ($url = $this->normalizePublicUrl(APP_URL)) {
                return $url;
            }

            log_message(
                'critical',
                '[getPublicSiteUrl] CLI context: No valid site_url found. Please configure either general_settings.site_url or APP_URL/SITE_URL in .env.'
            );

            return '';
        }

        // 4. Web context: headers
        try {
            $request = service('request');

            if ($request) {
                // Proxy headers
                $forwardedHost  = $request->getHeaderLine('X-Forwarded-Host');
                $forwardedProto = $request->getHeaderLine('X-Forwarded-Proto');

                if (!empty($forwardedHost)) {
                    $scheme = $forwardedProto ? strtolower($forwardedProto) : 'https';
                    if ($url = $this->normalizePublicUrl($scheme . '://' . $forwardedHost)) {
                        return $url;
                    }
                }

                // Normal Host header
                $host = $request->getHeaderLine('Host');
                if (!empty($host)) {
                    $scheme = (
                        $request->getServer('HTTPS') === 'on' ||
                        $request->getServer('HTTP_X_FORWARDED_PROTO') === 'https' ||
                        $request->getServer('HTTP_X_FORWARDED_SSL') === 'on'
                    ) ? 'https' : 'http';

                    if ($url = $this->normalizePublicUrl($scheme . '://' . $host)) {
                        return $url;
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore and continue fallback
        }

        // 5. Fallback: APP_URL constant
        if (defined('APP_URL')) {
            if ($url = $this->normalizePublicUrl(APP_URL)) {
                return $url;
            }
        }

        // 6. Last resort: base_url()
        if ($url = $this->normalizePublicUrl(base_url())) {
            return $url;
        }

        log_message(
            'error',
            '[getPublicSiteUrl] Could not determine valid public site URL. Configure general_settings.site_url or APP_URL/SITE_URL in .env.'
        );

        return '';
    }


    /**
     * Normalize and validate a public URL
     */
    private function normalizePublicUrl(?string $url): ?string
    {
        if (empty($url)) {
            return null;
        }

        $url = rtrim(trim($url), '/');

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $parsed = parse_url($url);
        if (!isset($parsed['scheme'], $parsed['host'])) {
            return null;
        }

        // Reject localhost
        if (
            $parsed['host'] === 'localhost' ||
            $parsed['host'] === '127.0.0.1' ||
            preg_match('/^localhost(:|$)/i', $parsed['host'])
        ) {
            return null;
        }

        $scheme = $parsed['scheme'];
        $host   = $parsed['host'];

        // Keep non-standard ports only
        if (
            isset($parsed['port']) &&
            !(($scheme === 'http' && $parsed['port'] == 80) ||
                ($scheme === 'https' && $parsed['port'] == 443))
        ) {
            $host .= ':' . $parsed['port'];
        }

        return $scheme . '://' . $host;
    }


    /**
     * Get provider/partner name with translation support
     * 
     * @param int $providerId Provider ID
     * @return string|null Provider name or null if not found
     */
    private function getProviderName(int $providerId): ?string
    {
        // Use helper function to fetch partner details
        $partner_data = fetch_details('partner_details', ['partner_id' => $providerId]);
        if (empty($partner_data)) {
            return null;
        }

        $provider_name = $partner_data[0]['company_name'] ?? '';

        // Try to get translated partner name using helper function
        $defaultLanguageCode = get_default_language();
        $translationModel = new \App\Models\TranslatedPartnerDetails_model();
        $translatedPartnerDetails = $translationModel->getTranslatedDetails($providerId, $defaultLanguageCode);

        if (!empty($translatedPartnerDetails) && !empty($translatedPartnerDetails['company_name'])) {
            $provider_name = $translatedPartnerDetails['company_name'];
        }

        return $provider_name;
    }
}
