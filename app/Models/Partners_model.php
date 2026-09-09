<?php

namespace App\Models;

use CodeIgniter\Model;

class Partners_model extends Model
{
    protected $table = 'partner_details';
    protected $primaryKey = 'id';
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $allowedFields = [
        'partner_id',
        'company_name',
        'about',
        'address',
        'banner',
        'advance_booking_days',
        'type',
        'number_of_members',
        'admin_commission',
        'visiting_charges',
        'is_approved',
        'ratings',
        'number_of_ratings',
        'payable_commision',
        'other_images',
        'long_description',
        'at_store',
        'at_doorstep',
        'chat',
        'need_approval_for_the_service',
        'pre_chat',
        'custom_job_categories',
        'slug',
        'max_serviceable_distance',
    ];

    /**
     * Private helper function to handle translation logic for partner data
     * 
     * @param array $partnerData Raw partner data from database
     * @param array $translations All translations for partners (indexed by partner_id then language_code)
     * @param string $requestedLang Language code requested by user
     * @param string $defaultLang Default language of the system
     * @return array Partner data with proper translation fields applied
     */
    private function applyTranslations($partnerData, $translations, $requestedLang, $defaultLang)
    {
        $partnerId = $partnerData['partner_id'];

        // Store original base table values before any processing
        // This ensures we can fallback to original base table data when translations are missing
        $originalCompanyName = $partnerData['company_name'] ?? '';
        $originalPartnerName = $partnerData['partner_name'] ?? '';
        $originalAbout = $partnerData['about'] ?? '';
        $originalLongDescription = $partnerData['long_description'] ?? '';

        // Get translations for this specific partner
        $partnerTranslations = $translations[$partnerId] ?? [];

        // Determine the best translation for requested language
        $requestedTranslation = $this->getBestTranslation($partnerTranslations, $requestedLang, $defaultLang);

        // Determine the best translation for default language (for original fields)
        $defaultTranslation = $this->getBestTranslation($partnerTranslations, $defaultLang, $defaultLang);

        // Helper function to get best available value with proper fallback chain
        // Priority: translation value → main table value → empty string
        // This ensures that when default language changes and new language has no translations,
        // we fall back to main table data (which contains the old default language data)
        $getBestValue = function ($field, $translation, $mainTableValue, $isUsername = false) {
            // For username, the main table field is 'partner_name', not 'username'
            $mainValue = $isUsername ? $mainTableValue : ($mainTableValue ?? '');

            // Check if translation exists and has non-empty value for this field
            // This handles cases where translation array exists but field is empty or missing
            if (is_array($translation) && isset($translation[$field]) && !empty($translation[$field])) {
                return trim($translation[$field]);
            }

            // Otherwise, fallback to main table value
            // This is critical when default language changes - main table still has old default data
            return trim($mainValue ?? '');
        };

        // Apply original fields (use default language translation, fallback to main table)
        // This ensures that when default language changes, we still show data from main table or old translations
        $partnerData['company_name'] = $getBestValue('company_name', $defaultTranslation, $originalCompanyName);

        $partnerData['about'] = !empty($defaultTranslation['about'])
            ? $defaultTranslation['about']
            : $originalAbout;

        $partnerData['long_description'] = !empty($defaultTranslation['long_description'])
            ? $defaultTranslation['long_description']
            : $originalLongDescription;

        // Apply username translation with fallback to main table username
        // Note: username in translations table maps to partner_name in main table
        $partnerData['partner_name'] = $getBestValue('username', $defaultTranslation, $originalPartnerName, true);

        // Apply translated fields (use requested language translation with fallback logic)
        // Fallback chain: requested translation (if exists and not empty) → default translation (if exists and not empty) → main table → empty
        // This ensures proper fallback when translations exist but are empty strings
        // IMPORTANT: Use original base table values for final fallback, not processed values

        // Helper function to check if a translation value is valid (not empty after trimming)
        $isValidTranslation = function ($value) {
            return isset($value) && !empty(trim($value));
        };

        // Company name: requested language → default language → original base table
        if ($isValidTranslation($requestedTranslation['company_name'] ?? null)) {
            $partnerData['translated_company_name'] = trim($requestedTranslation['company_name']);
        } elseif ($isValidTranslation($defaultTranslation['company_name'] ?? null)) {
            $partnerData['translated_company_name'] = trim($defaultTranslation['company_name']);
        } else {
            // Fallback to original base table value
            $partnerData['translated_company_name'] = trim($originalCompanyName);
        }

        // About: requested language → default language → original base table
        if ($isValidTranslation($requestedTranslation['about'] ?? null)) {
            $partnerData['translated_about'] = $requestedTranslation['about'];
        } elseif ($isValidTranslation($defaultTranslation['about'] ?? null)) {
            $partnerData['translated_about'] = $defaultTranslation['about'];
        } else {
            // Fallback to original base table value
            $partnerData['translated_about'] = $originalAbout;
        }

        // Long description: requested language → default language → original base table
        if ($isValidTranslation($requestedTranslation['long_description'] ?? null)) {
            $partnerData['translated_long_description'] = $requestedTranslation['long_description'];
        } elseif ($isValidTranslation($defaultTranslation['long_description'] ?? null)) {
            $partnerData['translated_long_description'] = $defaultTranslation['long_description'];
        } else {
            // Fallback to original base table value
            $partnerData['translated_long_description'] = $originalLongDescription;
        }

        // Username (partner name): requested language → default language → original base table
        if ($isValidTranslation($requestedTranslation['username'] ?? null)) {
            $partnerData['translated_partner_name'] = trim($requestedTranslation['username']);
        } elseif ($isValidTranslation($defaultTranslation['username'] ?? null)) {
            $partnerData['translated_partner_name'] = trim($defaultTranslation['username']);
        } else {
            // Fallback to original base table value
            $partnerData['translated_partner_name'] = trim($originalPartnerName);
        }

        return $partnerData;
    }

    /**
     * Get the best available translation based on priority
     * 
     * Priority order:
     * 1. Preferred language translation (if exists, even if empty - caller handles fallback)
     * 2. Default language translation (if exists, even if empty - caller handles fallback)
     * 3. First available translation with non-empty data (ONLY when preferredLang != defaultLang)
     *    When looking for default language specifically, if it doesn't exist, return empty array
     *    so caller can fallback to main table data (which contains the default language data)
     * 4. First available translation (even if empty - allows caller to fallback to main table)
     *    ONLY when preferredLang != defaultLang
     * 5. Empty array (will fallback to main table data)
     * 
     * @param array $partnerTranslations All translations for a partner (indexed by language_code)
     * @param string $preferredLang Preferred language code
     * @param string $defaultLang Default language code
     * @return array Best translation data
     */
    private function getBestTranslation($partnerTranslations, $preferredLang, $defaultLang)
    {
        // Helper function to check if translation has meaningful data
        $hasData = function ($translation) {
            return !empty($translation['company_name']) || !empty($translation['username']);
        };

        // Priority 1: Check preferred language translation (return even if empty)
        // The caller will handle fallback to main table if empty
        if (isset($partnerTranslations[$preferredLang])) {
            return $partnerTranslations[$preferredLang];
        }

        // Priority 2: Check default language translation (return even if empty)
        // The caller will handle fallback to main table if empty
        if (isset($partnerTranslations[$defaultLang])) {
            return $partnerTranslations[$defaultLang];
        }

        // IMPORTANT: If we're specifically looking for the default language and it doesn't exist,
        // return empty array so caller can use main table data (which contains default language data)
        // This fixes the issue where default language is English but English translation doesn't exist,
        // and we incorrectly fall back to German instead of using main table English data
        if ($preferredLang === $defaultLang) {
            // Default language translation doesn't exist, return empty array
            // This allows getBestValue to fallback to main table data
            return [];
        }

        // Priority 3: Find first available translation with data
        // This handles the case where requested language is different from default language
        // For example: requested is 'de', default is 'en', but 'de' translation doesn't exist
        // We can use 'en' translation data as fallback for the requested language
        foreach ($partnerTranslations as $langCode => $translation) {
            if ($hasData($translation)) {
                return $translation;
            }
        }

        // Priority 4: Return first available translation (even if empty)
        // This allows the caller to fallback to main table data
        // Only applies when preferredLang != defaultLang (already handled above)
        if (!empty($partnerTranslations)) {
            return reset($partnerTranslations);
        }

        // Priority 5: No translations available, return empty array
        // Caller will use main table data
        return [];
    }

    /**
     * Get the requested language with proper priority
     * 
     * For admin/partner panel requests (from_app=true), prioritize session language.
     * For API requests, prioritize header language.
     * 
     * @param string|null $languageCode Explicit language code from parameter
     * @param bool $fromApp Whether this is from admin/partner panel (true) or API (false)
     * @return string Language code to use
     */
    private function getRequestedLanguage($languageCode = null, $fromApp = false)
    {
        // Priority 1: Explicit parameter
        if ($languageCode) {
            return $languageCode;
        }

        // For admin/partner panel requests, prioritize session language over header language
        // This ensures the dashboard and other panel pages use the language selected in the UI
        if ($fromApp) {
            // Priority 2: Session language (for admin/partner panel)
            $sessionLang = get_current_language();
            if (!empty($sessionLang)) {
                return $sessionLang;
            }
        }

        // For API requests, check header language first
        // Priority 2: Header language (for API requests)
        if (function_exists('get_current_language_from_request')) {
            $headerLang = get_current_language_from_request();
            if ($headerLang) {
                return $headerLang;
            }
        }

        // Priority 3: Session language (fallback for API if no header language)
        return get_current_language();
    }

    public function list($from_app = false, $search = '', $limit = 10, $offset = 0, $sort = 'id', $order = 'ASC', $where = [], $column_name = 'pd.id', $whereIn = [], $additional_data = [], $limit_for_subscription = null, $languageCode = null)
    {
        $fileService = service('fileService');

        // Get current language for translation with proper priority based on request type
        // For admin/partner panel (from_app=true), prioritize session language
        // For API requests (from_app=false), prioritize header language
        $currentLang = $this->getRequestedLanguage($languageCode, $from_app);
        $defaultLang = get_default_language();
        $settings = get_settings('general_settings', true);

        $multipleWhere = '';
        $db      = \Config\Database::connect();
        $builder = $db->table('partner_details pd');
        $values = ['7'];
        // Trim search term to remove leading/trailing whitespace that might cause issues
        // This fixes the issue where pasting full names with spaces doesn't work
        if ($search and $search != '') {
            $search = trim($search);
        }

        // Build base query with joins first
        // This ensures all tables are available when we apply search conditions
        $builder->select(' COUNT( DISTINCT pd.id) as `total`')
            ->join('users u', 'pd.partner_id = u.id')
            ->join('users_groups ug', 'ug.user_id = u.id')
            ->join('partner_subscriptions ps', 'ps.partner_id = pd.partner_id', 'left')
            ->where('ug.group_id', 3)->whereNotIn('pd.is_approved', $values);

        // Apply search conditions AFTER joins are set up
        // This ensures all fields are available and search works correctly
        if ($search and $search != '') {
            // Escape search term for safe LIKE queries
            // This prevents SQL injection and handles special characters correctly
            $escapedSearch = $db->escapeLikeString($search);

            // Focus search on visible/relevant fields only:
            // - Provider ID (exact match)
            // - Company Name (base and translated)
            // - Provider Name/Username (base and translated)
            // - Phone and Email for convenience
            // This prevents matching irrelevant hidden fields like tax numbers, bank details, etc.
            $builder->groupStart();

            // Search in base fields using LIKE with proper escaping
            $builder->like('pd.id', $escapedSearch);
            $builder->orLike('pd.company_name', $escapedSearch);
            $builder->orLike('u.username', $escapedSearch);
            $builder->orLike('u.email', $escapedSearch);
            $builder->orLike('u.phone', $escapedSearch);

            // Search in translated fields across ALL languages, not just current language
            // This allows users to search for provider names in any language translation
            // Focus only on company_name and username (provider name) - not descriptions
            // This makes the search more accurate and focused on what users see in the table
            $translationSearchCondition = "EXISTS (
                SELECT 1 FROM translated_partner_details tpd_search 
                WHERE tpd_search.partner_id = pd.partner_id 
                AND (
                    tpd_search.company_name LIKE '%{$escapedSearch}%' 
                    OR tpd_search.username LIKE '%{$escapedSearch}%'
                )
            )";
            $builder->orWhere($translationSearchCondition, null, false);

            $builder->groupEnd();
        }

        if (isset($where) && !empty($where)) {
            $builder->where($where);
        }

        // Note: We no longer need to join translated_partner_details here
        // because we're using a subquery in the WHERE clause to search across all languages
        // This allows searching for provider names in any language translation
        // Skip distance filtering when slug or partner_id is passed - data should be returned even if outside max_serviceable_distance
        if (isset($additional_data['latitude']) && !empty($additional_data['latitude'])) {
            // Check if slug or partner_id is in where clause - if so, skip distance filtering
            $skipDistanceFilter = false;
            if (isset($where) && !empty($where)) {
                $skipDistanceFilter = array_key_exists('pd.partner_id', $where) || array_key_exists('pd.slug', $where);
            }
            
            // Only filter by distance if slug/partner_id is not passed
            // This ensures data is returned when slug/id is passed even if outside max_serviceable_distance
            if (!$skipDistanceFilter) {
                $parnter_ids = get_near_partners($additional_data['latitude'], $additional_data['longitude'], $additional_data['max_serviceable_distance'], true);
                if (isset($parnter_ids) && !empty($parnter_ids) && !isset($parnter_ids['error'])) {
                    $builder->whereIn('pd.partner_id', $parnter_ids);
                }
            }
        }
        if (isset($_GET['partner_filter']) && $_GET['partner_filter'] != '') {
            if ($_GET['partner_filter'] == 'individual_partner') {
                $builder->where('pd.type', 0);
            } elseif ($_GET['partner_filter'] == 'orgenization_partner') {
                $builder->where('pd.type', 1);
            } else {
                $builder->where('pd.is_approved', $_GET['partner_filter']);
            }
        }
        if (isset($whereIn) && !empty($whereIn)) {
            $builder->where('ps.status', 'active')->whereIn($column_name, $whereIn);
            // print_r($builder->get()->getResultArray()); die;
        }
        if (isset($additional_data['latitude']) && !empty($additional_data['latitude'])) {
            $latitude = $additional_data['latitude'];
            $longitude = $additional_data['longitude'];
        }

        $partner_count = $builder->get()->getResultArray();
        $defaultLanguage = get_default_language();
        // print_r($db->getLastQuery());
        // die;
        $total = $partner_count[0]['total'];

        // Create a new builder instance for the data query to avoid state conflicts
        // The count query builder has already executed, so we need a fresh builder for the data query
        // This prevents SQL errors that occur when reusing a builder after get() has been called
        $dataBuilder = $db->table('partner_details pd');

        if (isset($additional_data['latitude']) && !empty($additional_data['latitude']) &&  !empty($limit_for_subscription)  && isset($limit_for_subscription)) {
            if (isset($where) && !empty($where)) {
                $dataBuilder->where($where);
            }
            // Apply search conditions if search term exists
            // Use same pattern as count query to ensure consistency
            if ($search and $search != '') {
                $escapedSearch = $db->escapeLikeString($search);
                $dataBuilder->groupStart();

                // Search in base fields
                $dataBuilder->like('pd.id', $escapedSearch);
                $dataBuilder->orLike('pd.company_name', $escapedSearch);
                $dataBuilder->orLike('u.username', $escapedSearch);
                $dataBuilder->orLike('u.email', $escapedSearch);
                $dataBuilder->orLike('u.phone', $escapedSearch);

                // Search in translated fields across all languages
                $translationSearchCondition = "EXISTS (
                    SELECT 1 FROM translated_partner_details tpd_search 
                    WHERE tpd_search.partner_id = pd.partner_id 
                    AND (
                        tpd_search.company_name LIKE '%{$escapedSearch}%' 
                        OR tpd_search.username LIKE '%{$escapedSearch}%'
                    )
                )";
                $dataBuilder->orWhere($translationSearchCondition, null, false);
                $dataBuilder->groupEnd();
            }

            // Note: We no longer need to join translated_partner_details here
            // because we're using a subquery in the WHERE clause to search across all languages
            // This allows searching for provider names in any language translation

            $dataBuilder->select("
                pd.*,
                u.username as partner_name, 
                u.balance, u.image, u.active, u.email, u.phone, u.country_code, u.city, 
                u.longitude, u.latitude, u.payable_commision,
                ug.user_id, ug.group_id,
                ps.id as partner_subscription_id, 
                ps.status as partner_subscription_status, 
                ps.max_order_limit,

                COALESCE(COUNT(DISTINCT CASE WHEN pd.partner_id AND o.status = 'completed' AND (o.payment_status != 2 OR o.payment_status IS NULL) THEN o.id END), 0) as number_of_orders,

                " . get_provider_distance_sql($latitude, $longitude, 'u.id') . " as distance,

                MAX(DISTINCT CASE WHEN pd.partner_id THEN pc.discount END) as maximum_discount_percentage,
                MAX(DISTINCT CASE WHEN pd.partner_id THEN pc.max_discount_amount END) as maximum_discount_up_to,

                CAST((" . get_provider_distance_sql($latitude, $longitude, 'u.id') . ") < " .
                get_max_serviceable_distance_condition($settings, 'pd.max_serviceable_distance', $additional_data['max_serviceable_distance']) . " AS CHAR) as is_Available_at_location
            ");
            $dataBuilder
                ->join('users u', 'pd.partner_id = u.id')
                ->join('users_groups ug', 'ug.user_id = u.id')
                ->join('orders o', 'o.partner_id = pd.partner_id AND o.parent_id IS NULL', 'left')
                ->join('partner_subscriptions ps', 'ps.partner_id = pd.partner_id')
                ->join('promo_codes pc', 'pc.partner_id = pd.partner_id', 'left')
                ->where('ug.group_id', 3)
                ->where('pd.is_approved', '1')
                ->groupBy(['pd.partner_id', 'pd.id']);

            // for web :: web ma scroll ma issue ave ena mate aa karel che  jyare partner id pass thay tyare distance vadi condition n check thavi joiye
            // Also skip distance filter when slug is passed - data should be returned even if outside max_serviceable_distance
            // This ensures that when slug or partner_id is passed, providers are returned regardless of distance
            $skipDistanceFilter = false;
            if (isset($where) && !empty($where)) {
                $skipDistanceFilter = array_key_exists('pd.partner_id', $where) || array_key_exists('pd.slug', $where);
            }
            if (!$skipDistanceFilter) {
                $dataBuilder->having('distance < ' . get_max_serviceable_distance_condition($settings, 'pd.max_serviceable_distance', $additional_data['max_serviceable_distance']));
            }
            $dataBuilder->where('ps.status', 'active')
                ->groupBy(['pd.partner_id', 'pd.id']);
        } else if (isset($additional_data['latitude']) && !empty($additional_data['latitude'])) {
            // Note: We no longer need to join translated_partner_details here
            // because we're using a subquery in the WHERE clause to search across all languages
            // This allows searching for provider names in any language translation

            $dataBuilder->select("
                pd.*,
                u.username as partner_name,
                u.balance, u.image, u.active, u.email, u.phone, u.country_code, u.city,
                u.longitude, u.latitude, u.payable_commision,
                ug.user_id, ug.group_id,
                ps.id as partner_subscription_id, 
                ps.status as partner_subscription_status, 
                ps.max_order_limit,

            
                COALESCE(COUNT(DISTINCT CASE WHEN pd.partner_id AND o.status = 'completed' AND (o.payment_status != 2 OR o.payment_status IS NULL) THEN o.id END), 0) as number_of_orders,

                " . get_provider_distance_sql($latitude, $longitude, 'u.id') . " as distance,

                MAX(DISTINCT CASE WHEN pd.partner_id THEN pc.discount END) as maximum_discount_percentage,
                MAX(DISTINCT CASE WHEN pd.partner_id THEN pc.max_discount_amount END) as maximum_discount_up_to,

                CAST((" . get_provider_distance_sql($latitude, $longitude, 'u.id') . ") < " .
                get_max_serviceable_distance_condition($settings, 'pd.max_serviceable_distance', $additional_data['max_serviceable_distance']) . " AS CHAR) as is_Available_at_location
            ");

            $dataBuilder
                ->join('users u', 'pd.partner_id = u.id')
                ->join('users_groups ug', 'ug.user_id = u.id')
                ->join('orders o', 'o.partner_id = pd.partner_id AND o.parent_id IS NULL', 'left')
                ->join('partner_subscriptions ps', 'ps.partner_id = pd.partner_id', 'left')
                ->join('promo_codes pc', 'pc.partner_id = pd.partner_id', 'left')
                ->where('ug.group_id', 3)
                ->where('pd.is_approved', '1')
                ->groupBy(['pd.partner_id', 'pd.id']);

            // Skip distance filter when slug or partner_id is passed
            // This ensures data is returned when slug/id is passed even if outside max_serviceable_distance
            $skipDistanceFilter = false;
            if (isset($where) && !empty($where)) {
                $skipDistanceFilter = array_key_exists('pd.partner_id', $where) || array_key_exists('pd.slug', $where);
            }
            if (!$skipDistanceFilter) {
                $dataBuilder->having('distance < ' . get_max_serviceable_distance_condition($settings, 'pd.max_serviceable_distance', $additional_data['max_serviceable_distance']));
            }
        } else {
            // Note: We no longer need to join translated_partner_details here
            // because we're using a subquery in the WHERE clause to search across all languages
            // This allows searching for provider names in any language translation

            $subQueryOrders = "(SELECT o.partner_id, COUNT(o.id) AS number_of_orders
                    FROM orders o
                    WHERE o.status = 'completed' 
                    AND o.parent_id IS NULL
                    AND (o.payment_status != 2 OR o.payment_status IS NULL)
                    GROUP BY o.partner_id)";

            $subQueryDiscounts = "(SELECT pc.partner_id, 
                              MAX(pc.discount) AS maximum_discount_percentage, 
                              MAX(pc.max_discount_amount) AS maximum_discount_up_to
                       FROM promo_codes pc
                       GROUP BY pc.partner_id)";

            $dataBuilder->select("
                pd.*,
                u.username as partner_name,
                u.balance, u.image, u.active, u.email, u.phone, u.country_code, 
                u.city, u.longitude, u.latitude, u.payable_commision,
                ug.user_id, ug.group_id,
                ps.id as partner_subscription_id, 
                ps.status as partner_subscription_status,

                pt.day, pt.opening_time, pt.closing_time, pt.is_open, pt.shift_number,

                COALESCE(OrdersSummary.number_of_orders, 0) AS number_of_orders,
                COALESCE(DiscountSummary.maximum_discount_percentage, 0) AS maximum_discount_percentage,
                COALESCE(DiscountSummary.maximum_discount_up_to, 0) AS maximum_discount_up_to,
                '0' as is_Available_at_location
            ");

            $dataBuilder
                ->join('users u', 'pd.partner_id = u.id')
                ->join('users_groups ug', 'ug.user_id = u.id')
                ->join("($subQueryOrders) AS OrdersSummary", 'OrdersSummary.partner_id = pd.partner_id', 'left')
                ->join("($subQueryDiscounts) AS DiscountSummary", 'DiscountSummary.partner_id = pd.partner_id', 'left')
                ->join('partner_subscriptions ps', 'ps.partner_id = pd.partner_id', 'left')
                ->join('provider_shifts pt', 'pt.partner_id = pd.partner_id AND pt.shift_number = 1', 'left')
                ->where('ug.group_id', 3)
                ->whereNotIn('pd.is_approved', $values)
                ->groupBy(['pd.partner_id', 'pd.id']);
        }
        if (isset($_GET['partner_filter']) && $_GET['partner_filter'] != '') {
            if ($_GET['partner_filter'] == 'individual_partner') {
                $dataBuilder->where('pd.type', 0);
            } elseif ($_GET['partner_filter'] == 'orgenization_partner') {
                $dataBuilder->where('pd.type', 1);
            } else {
                $dataBuilder->where('pd.is_approved', $_GET['partner_filter']);
            }
        }

        // Note: We no longer need to join translated_partner_details here
        // because we're using a subquery in the WHERE clause to search across all languages
        // This allows searching for provider names in any language translation

        // Apply search conditions if search term exists
        // Use same pattern as count query to ensure consistency
        if ($search and $search != '') {
            $escapedSearch = $db->escapeLikeString($search);
            $dataBuilder->groupStart();

            // Search in base fields
            $dataBuilder->like('pd.id', $escapedSearch);
            $dataBuilder->orLike('pd.company_name', $escapedSearch);
            $dataBuilder->orLike('u.username', $escapedSearch);
            $dataBuilder->orLike('u.email', $escapedSearch);
            $dataBuilder->orLike('u.phone', $escapedSearch);

            // Search in translated fields across all languages
            $translationSearchCondition = "EXISTS (
                SELECT 1 FROM translated_partner_details tpd_search 
                WHERE tpd_search.partner_id = pd.partner_id 
                AND (
                    tpd_search.company_name LIKE '%{$escapedSearch}%' 
                    OR tpd_search.username LIKE '%{$escapedSearch}%'
                )
            )";
            $dataBuilder->orWhere($translationSearchCondition, null, false);
            $dataBuilder->groupEnd();
        }
        if (isset($whereIn) && !empty($whereIn)) {
            $dataBuilder->where('ps.status', 'active')->whereIn($column_name, $whereIn);
        }
        if (isset($where) && !empty($where)) {
            $dataBuilder->where($where);
        }
        // When sorting by number_of_orders, add secondary sort by partner_id for consistent ordering
        // This ensures providers with same order count are ordered consistently
        if ($sort == 'number_of_orders') {
            $dataBuilder->orderBy($sort, $order)->orderBy('pd.partner_id', 'ASC');
        } else {
            $dataBuilder->orderBy($sort, $order);
        }

        // Execute query and handle potential SQL errors
        // get() returns false on SQL errors, so we need to check before calling getResultArray()
        // Using new dataBuilder instance prevents state conflicts from the count query
        $queryResult = $dataBuilder->limit($limit, $offset)->get();

        // Check if query failed (returns false on SQL error)
        if ($queryResult === false) {
            // // Log the SQL error for debugging
            // $error = $db->error();
            // log_message('error', 'Partners_model->list() SQL Error: ' . json_encode($error));
            // log_message('error', 'Partners_model->list() Last Query: ' . $db->getLastQuery());

            // Return empty result set instead of crashing
            // This prevents fatal error and allows the page to load with empty data
            $partner_record = [];
        } else {
            $partner_record = $queryResult->getResultArray();
        }

        // Batch fetch all translations for all partners in a single query
        $allTranslations = [];
        if (!empty($partner_record)) {
            $partnerIds = array_column($partner_record, 'partner_id');

            $translatedPartnerDetailsModel = new \App\Models\TranslatedPartnerDetails_model();

            // Get all translations for all partners (not just current language)
            $allTranslations = $translatedPartnerDetailsModel->getAllTranslationsForPartners($partnerIds);
        }

        $bulkData = array();
        $bulkData['total'] = $total;
        if ($from_app == false) {
            $db      = \Config\Database::connect();
            $builder = $db->table('users u');
            $builder->select('u.*,ug.group_id')
                ->join('users_groups ug', 'ug.user_id = u.id')
                ->where('ug.group_id', 1)
                ->where(['phone' => $_SESSION['identity']]);
            $user1 = $builder->get()->getResultArray();
            $permissions = get_permission($user1[0]['id']);
        }
        $rows = array();
        $tempRow = array();
        foreach ($partner_record as $row) {
            // Apply translations using our helper function
            $row = $this->applyTranslations($row, $allTranslations, $currentLang, $defaultLang);

            $profile = "";

            // Handle profile image with consistent fallback logic
            $imageSrc = service('fileService')->url($row['image'] ?? '', 'profile', 'public/backend/assets/default.png');
            // Safe session check: API requests may have no session or no 'email' key (fixes Undefined array key "email")
            $sessionEmail = $_SESSION['email'] ?? '';
            $isMasked = defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0 && $sessionEmail != 'superadmin@gmail.com';

            $email = $row['email'] ?? '';
            $phone = $row['phone'] ?? '';
            $countryCode = trim($row['country_code'] ?? '');

            // Build display phone with country code; ensure leading + for international format
            if ($countryCode !== '' && $phone !== '') {
                $displayPhone = (strpos($countryCode, '+') === 0 ? $countryCode : '+' . $countryCode) . $phone;
            } else {
                $displayPhone = $phone;
            }

            $maskEmail = function ($value) {
                return strlen($value) > 6 ? 'wrteam.' . substr($value, 6) : 'wrteam.***';
            };
            
            $maskPhone = function ($value) {
                return strlen($value) > 6 ? 'XXXXX' . substr($value, 6) : 'XXXXX';
            };

            $partner_email = $isMasked ? $maskEmail($email) : $email;

            if (!empty($email)) {
                $contact_detail = $partner_email;
                if (!empty($phone)) {
                    $contact_detail = "<span>{$contact_detail}</span>";
                }
            } else {
                $contact_detail = $isMasked ? $maskPhone($displayPhone) : $displayPhone;
            }

            $display_partner_name = $row['translated_partner_name'] ?? $row['partner_name'] ?? '';
            $partner_company_name = $row['translated_company_name'] ?? $row['company_name'] ?? '';

            $partner_mobile = $isMasked ? $maskPhone($displayPhone) : $displayPhone;

            $profile = '
            <div class="o-media o-media--middle">
                <a href="' . $imageSrc . '" data-lightbox="image-1">
                    <img class="o-media__img images_in_card" 
                         src="' . $imageSrc . '" 
                         alt="' . htmlspecialchars($display_partner_name, ENT_QUOTES) . '">
                </a>
                <a href="' . base_url('/admin/partners/general_outlook/' . $row['partner_id']) . '">
                    <div class="o-media__body">
                        <div class="provider_name_table">' . $display_partner_name . '</div>
                        <div class="provider_email_table">' . $partner_company_name . '</div>
                        <div class="provider_email_table">
                            ' . $contact_detail . ' (' . $partner_mobile . ')
                        </div>
                    </div>
                </a>
            </div>';

            $status = '';
            $status = '<div class="dropdown ">
            <a class="" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <button class="btn btn-secondary   btn-sm px-3"><i class="fas fa-ellipsis-v "></i></button>
          </a>
            <div class="dropdown-menu dropdown-scrollbar custom_dropdown" aria-labelledby="dropdownMenuButton">';
            if ($from_app == false) {
                if ($permissions['update']['partner'] == 1) {
                    $status .= '<a class="dropdown-item" href="' . base_url('/admin/partners/edit_partner/' . $row['partner_id']) . '"><i class="fa fa-pen mr-1 text-primary"></i>' . labels('edit_provider', 'Edit Provider') . '</a>';
                }
                if ($permissions['delete']['partner'] == 1) {
                    $status .= '<a class="dropdown-item delete_partner" href="#" id="delete_partner"> <i class="fa fa-trash mr-1 text-danger"></i>' . labels('delete_provider', 'Delete Provider') . '</a>';
                }
                if ($permissions['read']['partner'] == 1) {
                    $status .= '</i><a class="dropdown-item" href="' . base_url('/admin/partners/general_outlook/' . $row['partner_id']) . '"> <i class="fa fa-eye mr-1 text-success"></i>' . labels('view_provider', 'View Provider') . '</a>';
                }
                $status .= '<a class="dropdown-item" href="' . base_url('/admin/partners/duplicate/' . $row['partner_id']) . '"><i class="fa fa-copy mr-1 text-primary"></i>' . labels('duplicate_provider', 'Duplicate Provider') . '</a>';
            }
            $status .= ($row['is_approved'] == 1) ?
                '<a class="dropdown-item disapprove_partner" href="#" id="disapprove_partner"> <i class="fas fa-times text-danger mr-1"></i>' . labels('disapprove_provider', 'Disapprove Provider') . '</a>' :
                '<a class="dropdown-item approve_partner" href="#" id="approve_partner" ><i class="fas fa-check text-success mr-1"></i>' . labels('approve_provider', 'Approve Provider') . '</a>';
            $status .= '</div></div>';
            if ($from_app) {
                // Handle app-specific image logic with consistent fallback
                if (isset($additional_data['customer_id']) && !empty($additional_data['customer_id'])) {
                    $is_bookmarked = is_bookmarked($additional_data['customer_id'], $row['partner_id'])[0]['total'];
                    if (isset($is_bookmarked) && $is_bookmarked == 1) {
                        $tempRow['is_bookmarked'] = '1';
                    } else if (isset($is_bookmarked) && $is_bookmarked == 0) {
                        $tempRow['is_bookmarked'] = '0';
                    } else {
                        $tempRow['is_bookmarked'] = '0';
                    }
                }

                $tempRow['image'] = $fileService->url($row['image'], 'profile');
            }
            $tempRow['address'] = (!empty($row['address']) && isset($row['address'])) ? $row['address'] : '-';
            if (($row['type'] == 0)) {
                $type = ucfirst(labels('individual', 'Individual'));
            } else {
                $type = ucfirst(labels('organization', 'Organization'));
            }
            $label = ($row['is_approved'] == 1) ?
                "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-success text-emerald-success dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3 mx-5'>" . labels('approved', 'Approved') . "
                    </div>" :
                "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-danger text-emerald-danger dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3 '>" . labels('disapproved', 'Disapproved') . "
                    </div>";

            // Convert to raw SQL query for better performance and clarity
            // This query calculates rating statistics for a partner from both services and custom job requests
            $rating_data = $db->query("
                SELECT 
                    COUNT(sr.rating) AS number_of_rating,
                    SUM(sr.rating) AS total_rating,
                    (SUM(sr.rating) / COUNT(sr.rating)) AS average_rating
                FROM services_ratings sr
                LEFT JOIN services s ON sr.service_id = s.id
                WHERE s.user_id = {$row['partner_id']}
                   OR (
                       sr.custom_job_request_id IS NOT NULL
                       AND EXISTS (
                           SELECT 1
                           FROM partner_bids pb
                           WHERE pb.custom_job_request_id = sr.custom_job_request_id
                             AND pb.partner_id = {$row['partner_id']}
                       )
                   )
            ")->getResultArray();

            // print_r($db->getLastQuery());
            // die;

            $tempRow['banner_edit']  = $fileService->url($row['banner'], 'banner');
            $tempRow['banner_image'] = !empty($row['banner']) ? $fileService->url($row['banner'], 'banner') : '';

            $decodedOther = !empty($row['other_images']) ? (json_decode($row['other_images'], true) ?: []) : [];
            $row['other_images'] = array_map(
                fn ($p) => $fileService->url($p, 'partner'),
                is_array($decodedOther) ? $decodedOther : []
            );

            $cash_collection_button = '<button class="btn btn-success btn-sm edit_cash_collection" data-id="' . $row['id'] . '" data-toggle="modal" data-target="#update_modal"><i class="fa fa-pen" aria-hidden="true"></i> </button> ';

            $tempRow['id'] = $row['id'];
            // Ensure is_Available_at_location is always a string ("0" or "1")
            // Default to "0" if not set
            $tempRow['is_Available_at_location'] = isset($row['is_Available_at_location']) ? (string)$row['is_Available_at_location'] : "0";
            $tempRow['partner_id'] = $row['partner_id'];
            $tempRow['city'] = $row['city'];
            $tempRow['partner_profile'] = $profile;
            // Use the translated values from our helper function
            $tempRow['company_name'] = $row['company_name'];
            $tempRow['balance'] = $row['balance'];
            $tempRow['longitude'] = $row['longitude'];
            $tempRow['latitude'] = $row['latitude'];
            $tempRow['mobile'] = $partner_mobile;
            // Use the translated values from our helper function
            $tempRow['about'] = $row['about'];
            // Use the translated values from our helper function
            $tempRow['long_description'] = $row['long_description'];

            // Use the translated fields from our helper function
            $tempRow['translated_company_name'] = $row['translated_company_name'];
            $tempRow['translated_about'] = $row['translated_about'];
            $tempRow['translated_long_description'] = $row['translated_long_description'];
            $tempRow['translated_partner_name'] = $row['translated_partner_name'];
            $tempRow['address'] = (!empty($row['address']) && isset($row['address'])) ? $row['address'] : '-';

            $tempRow['national_id'] = $fileService->url($row['national_id'] ?? '', 'national_id');
            $tempRow['address_id']  = $fileService->url($row['address_id'] ?? '', 'address_id');
            $tempRow['passport']    = $fileService->url($row['passport'] ?? '', 'passport');

            $tempRow['partner_name'] = !empty($row['translated_partner_name']) ? $row['translated_partner_name'] : $row['partner_name'];
            $tempRow['tax_name'] = $row['tax_name'] ?? null;
            $tempRow['tax_number'] = $row['tax_number'] ?? null;
            $tempRow['bank_name'] = $row['bank_name'] ?? null;
            $tempRow['account_number'] = $row['account_number'] ?? null;
            $tempRow['account_name'] = $row['account_name'] ?? null;
            $tempRow['bank_code'] = $row['bank_code'] ?? null;
            $tempRow['swift_code'] = $row['swift_code'] ?? null;
            $tempRow['number_of_members'] = $row['number_of_members'];
            $tempRow['admin_commission'] = $row['admin_commission'];
            $tempRow['type'] = $type;
            $tempRow['email'] = $partner_email;
            // $tempRow['image'] = $row['image'] ?? "public/backend/assets/default.png"; // Removed: This was overwriting the properly processed image field with base_url
            $tempRow['advance_booking_days'] = $row['advance_booking_days'];
            $tempRow['number_of_members'] = $row['number_of_members'];
            // Round to one decimal place, same as merged_ratings (sprintf '%0.1f')
            $tempRow['ratings'] = ($row['ratings'] !== '' && $row['ratings'] !== null) ? sprintf('%0.1f', (float) $row['ratings']) : '0.0';
            $tempRow['number_of_ratings'] = $rating_data[0]['number_of_rating'];
            $tempRow['visiting_charges'] = $row['visiting_charges'];
            $tempRow['contact_detail'] = $contact_detail;
            $tempRow['is_approved_edit'] = $row['is_approved'];
            $tempRow['payable_commision'] = intval($row['payable_commision']);
            $tempRow['cash_collection_button'] = $cash_collection_button;
            $tempRow['checkbox'] = "  <input type='checkbox' class='select-item checkbox' name='select-item'";
            $tempRow['other_images'] = $row['other_images'];
            $tempRow['at_doorstep'] = isset($row['at_doorstep']) ? $row['at_doorstep'] : "0";
            $tempRow['at_store'] = isset($row['at_store']) ? $row['at_store'] : "0";
            $tempRow['post_booking_chat'] = isset($row['chat']) ? $row['chat'] : "0";
            $tempRow['pre_booking_chat'] = isset($row['pre_chat']) ? $row['pre_chat'] : "0";
            // $tempRow['address_id'] = $row['address_id']; // Removed: This was overwriting the properly processed address_id field with base_url
            $tempRow['slug'] = $row['slug'];



            if (isset($additional_data['latitude']) && !empty($additional_data['latitude'])) {
                $tempRow['distance'] = $row['distance'];
            }


            // Count only approved and active services for the provider
            $total_services_of_providers = fetch_details('services', [
                'user_id' => $row['partner_id'],
                'at_store' => $row['at_store'],
                'at_doorstep' => $row['at_doorstep'],
                'status' => 1,  // Only active services
                'approved_by_admin' => 1  // Only approved services
            ], ['id']);
            $tempRow['total_services'] = count($total_services_of_providers);


            if (check_partner_availibility($row['partner_id'])) {
                $tempRow['is_available_now'] = true;
            } else {
                $tempRow['is_available_now'] = false;
            }
            $tempRow['status'] = $label;
            if (!empty($rating_data)) {
                $tempRow['merged_ratings'] = '<i class="fa-solid fa-star text-warning"></i>' . ($rating_data[0]['average_rating'] != "") ? sprintf('%0.1f', $rating_data[0]['average_rating']) : '0.0';
                if ($from_app == false) {
                    $tempRow['merged_ratings'] = '<i class="fa-solid fa-star text-warning"></i>' . (($rating_data[0]['average_rating'] != "") ? sprintf('%0.1f', $rating_data[0]['average_rating']) : '0.0');
                } else {
                    $tempRow['merged_ratings'] =  (($rating_data[0]['average_rating'] != "") ? sprintf('%0.1f', $rating_data[0]['average_rating']) : '0.0');
                }
                if($rating_data[0]['number_of_rating'] != 0){
                    $tempRow['merged_ratings'] .= '(' . $rating_data[0]['number_of_rating'] . ')';
                }
            }
            
            $rate_data = get_ratings($row['partner_id']);
            $tempRow['1_star'] = $rate_data[0]['rating_1'];
            $tempRow['2_star'] = $rate_data[0]['rating_2'];
            $tempRow['3_star'] = $rate_data[0]['rating_3'];
            $tempRow['4_star'] = $rate_data[0]['rating_4'];
            $tempRow['5_star'] = $rate_data[0]['rating_5'];
            // Backward-compat legacy fields come from shift_number=1 of provider_shifts.
            $partner_timings = fetch_details('provider_shifts', ['partner_id' => $row['partner_id'], 'shift_number' => 1]);
            foreach ($partner_timings as $pt) {
                $tempRow[$pt['day'] . '_is_open'] = $pt['is_open'];
                $tempRow[$pt['day'] . '_opening_time'] = $pt['opening_time'];
                $tempRow[$pt['day'] . '_closing_time'] = $pt['closing_time'];
            }

            // Build shifts grouped by day from provider_shifts table.
            // Frontend-friendly shape: { day: { is_open, shifts: [ { shift_number, opening_time, closing_time, is_open } ] } }
            $days_order = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            $shifts_by_day = [];
            foreach ($days_order as $d) {
                $shifts_by_day[$d] = [
                    'is_open' => isset($tempRow[$d . '_is_open']) ? (string)$tempRow[$d . '_is_open'] : "0",
                    'shifts'  => [],
                ];
            }
            $provider_shifts = fetch_details(
                table: 'provider_shifts',
                where: ['partner_id' => $row['partner_id']],
                sort: 'day',
                order: 'ASC'
            );
            if (!empty($provider_shifts)) {
                // Secondary sort by shift_number to keep order deterministic per day.
                usort($provider_shifts, function ($a, $b) {
                    return ((int)$a['shift_number']) <=> ((int)$b['shift_number']);
                });
                foreach ($provider_shifts as $ps) {
                    $day = $ps['day'];
                    if (!isset($shifts_by_day[$day])) {
                        $shifts_by_day[$day] = ['is_open' => "0", 'shifts' => []];
                    }
                    $shifts_by_day[$day]['shifts'][] = [
                        'shift_number' => (string)$ps['shift_number'],
                        'opening_time' => $ps['opening_time'],
                        'closing_time' => $ps['closing_time'],
                        'is_open'      => (string)$ps['is_open'],
                        'on_leave'     => "0",
                    ];
                }
            }
            // Mark shifts with on_leave=1 for any active leave in the upcoming week
            // (today + remaining days of current week). is_open is NOT modified — caller
            // can decide how to render. DB values remain unchanged.
            // Window = today through end of current week (Sunday inclusive).
            // Week is Monday→Sunday to match $days_order above.
            $today_ts        = strtotime('today');
            $current_dow     = (int) date('N', $today_ts); // 1=Mon..7=Sun
            $days_remaining  = 7 - $current_dow;           // 0 when today is Sunday
            $day_to_date     = [];
            $dates_window    = [];
            for ($i = 0; $i <= $days_remaining; $i++) {
                $ts  = strtotime("+{$i} day", $today_ts);
                $d   = strtolower(date('l', $ts));
                $iso = date('Y-m-d', $ts);
                $day_to_date[$d] = $iso;
                $dates_window[]  = $iso;
            }
            $leave_rows = fetch_details(
                table: 'provider_leaves',
                where: ['partner_id' => $row['partner_id']],
                fields: ['leave_date', 'day', 'shift_number'],
                where_in_key: 'leave_date',
                where_in_value: $dates_window
            );
            $leave_map = [];
            if (!empty($leave_rows)) {
                foreach ($leave_rows as $lr) {
                    $leave_map[$lr['day']][(int) $lr['shift_number']] = true;
                }
            }
            foreach ($shifts_by_day as $day_key => &$day_block) {
                if (empty($day_block['shifts']) || empty($leave_map[$day_key])) {
                    continue;
                }
                foreach ($day_block['shifts'] as &$shift) {
                    if (isset($leave_map[$day_key][(int) $shift['shift_number']])) {
                        $shift['on_leave'] = "1";
                    }
                }
                unset($shift);
            }
            unset($day_block);

            $tempRow['shifts_by_day'] = $shifts_by_day;
            if ($from_app == false) {
                $tempRow['discount'] =  $row['maximum_discount_percentage'];
                $tempRow['discount_up_to'] =  $row['maximum_discount_up_to'];
                $tempRow['is_approved'] = ($from_app == true) ? $row['is_approved'] : $status;
                $tempRow['created_at'] = $row['created_at'];
            } else {
                if (isset($additional_data['customer_id']) && !empty($additional_data['customer_id'])) {
                    $customer_id = $additional_data['customer_id'];
                    $is_favorite = is_favorite($customer_id, $row['partner_id']);
                    $tempRow['is_favorite'] = ($is_favorite) ? '1' : '0';
                }
                $tempRow['discount'] =  $row['maximum_discount_percentage'];
                $tempRow['discount_up_to'] =  $row['maximum_discount_up_to'];
                $tempRow['number_of_orders'] = $row['number_of_orders'];
                $tempRow['status'] = $row['is_approved'];
                unset($tempRow['partner_profile']);
                unset($tempRow['contact_detail']);
            }
            $rows[] = $tempRow;
        }
        if ($from_app) {
            $response['total'] = $total; //count($rows)
            $response['data'] = $rows;
            return $response;
        } else {
            $bulkData['rows'] = $rows;
        }
        // echo "<pre>";
        // print_r($bulkData);
        // die;
        return $bulkData;
    }
    public function unsettled_commission_list($from_app = false, $search = '', $limit = 10, $offset = 0, $sort = 'id', $order = 'ASC', $where = [], $column_name = 'pd.id', $whereIn = [], $additional_data = [], $languageCode = null)
    {
        // Get current language for translation with proper priority based on request type
        // For admin/partner panel (from_app=true), prioritize session language
        // For API requests (from_app=false), prioritize header language
        // This needs to be done early to support translated field searches
        $currentLang = $this->getRequestedLanguage($languageCode, $from_app);
        $defaultLang = get_default_language();

        // Trim search term to remove leading/trailing whitespace that might cause issues
        // This fixes the issue where pasting full names with spaces doesn't work
        if ($search and $search != '') {
            $search = trim($search);
        }

        $multipleWhere = '';
        $db      = \Config\Database::connect();
        $builder = $db->table('partner_details pd');
        $values = ['7'];

        // Build base query with joins first
        // This ensures all tables are available when we apply search conditions
        $builder->select(' COUNT(pd.id) as `total` ')->join('users u', 'pd.partner_id = u.id')
            ->join('users_groups ug', 'ug.user_id = u.id')
            ->where('ug.group_id', 3)->whereNotIn('pd.is_approved', $values);

        // Apply search conditions AFTER joins are set up
        // This ensures all fields are available and search works correctly
        if ($search and $search != '') {
            // Escape search term for safe LIKE queries
            // This prevents SQL injection and handles special characters correctly
            $escapedSearch = $db->escapeLikeString($search);

            // Focus search on visible/relevant fields only:
            // - Provider ID (exact match)
            // - Company Name (base and translated)
            // - Provider Name/Username (base and translated)
            // - Phone and Email for convenience
            // This prevents matching irrelevant hidden fields like tax numbers, bank details, etc.
            $builder->groupStart();

            // Search in base fields using LIKE with proper escaping
            $builder->like('pd.id', $escapedSearch);
            $builder->orLike('pd.company_name', $escapedSearch);
            $builder->orLike('u.username', $escapedSearch);
            $builder->orLike('u.email', $escapedSearch);
            $builder->orLike('u.phone', $escapedSearch);

            // Search in translated fields across ALL languages, not just current language
            // This allows users to search for provider names in any language translation
            // Focus only on company_name and username (provider name) - not descriptions
            // This makes the search more accurate and focused on what users see in the table
            $translationSearchCondition = "EXISTS (
                SELECT 1 FROM translated_partner_details tpd_search 
                WHERE tpd_search.partner_id = pd.partner_id 
                AND (
                    tpd_search.company_name LIKE '%{$escapedSearch}%' 
                    OR tpd_search.username LIKE '%{$escapedSearch}%'
                )
            )";
            $builder->orWhere($translationSearchCondition, null, false);

            $builder->groupEnd();
        }
        if (isset($where) && !empty($where)) {
            $builder->where($where);
        }
        if (isset($whereIn) && !empty($whereIn)) {
            $builder->whereIn($column_name, $whereIn);
        }
        if (isset($additional_data['latitude']) && !empty($additional_data['latitude'])) {
            $parnter_ids = get_near_partners($additional_data['latitude'], $additional_data['longitude'], $additional_data['max_serviceable_distance'], true);
            if (isset($parnter_ids) && !empty($parnter_ids) && !isset($parnter_ids['error'])) {
                $builder->whereIn('pd.partner_id', $parnter_ids);
            }
        }
        $partner_count = $builder->get()->getResultArray();
        $total = $partner_count[0]['total'];

        // Create a new builder instance for the data query to avoid conflicts
        // This ensures clean joins and proper query construction
        $dataBuilder = $db->table('partner_details pd');

        // Current language and default language are already set above for search support
        if (isset($additional_data['latitude']) && !empty($additional_data['latitude'])) {
            $parnter_ids = get_near_partners($additional_data['latitude'], $additional_data['longitude'], $additional_data['city_id'], true);
            if (isset($parnter_ids) && !empty($parnter_ids) && !isset($parnter_ids['error'])) {
                $dataBuilder->whereIn('pd.partner_id', $parnter_ids);
            }
        }

        // Build data query with necessary joins
        $dataBuilder->select("
            pd.*,
            u.username as partner_name,
            u.balance,u.image,u.active,u.email,u.phone,u.country_code,
            ug.user_id,ug.group_id
        ")
            ->join('users u', 'pd.partner_id = u.id')
            ->join('users_groups ug', 'ug.user_id = u.id')
            ->where('ug.group_id', 3);

        // Note: We no longer need to join translated_partner_details here
        // because we're using a subquery in the WHERE clause to search across all languages
        // This allows searching for provider names in any language translation

        // Apply search conditions if search term exists
        // Use same pattern as count query to ensure consistency
        if ($search and $search != '') {
            $escapedSearch = $db->escapeLikeString($search);
            $dataBuilder->groupStart();

            // Search in base fields
            $dataBuilder->like('pd.id', $escapedSearch);
            $dataBuilder->orLike('pd.company_name', $escapedSearch);
            $dataBuilder->orLike('u.username', $escapedSearch);
            $dataBuilder->orLike('u.email', $escapedSearch);
            $dataBuilder->orLike('u.phone', $escapedSearch);

            // Search in translated fields across all languages
            $translationSearchCondition = "EXISTS (
                SELECT 1 FROM translated_partner_details tpd_search 
                WHERE tpd_search.partner_id = pd.partner_id 
                AND (
                    tpd_search.company_name LIKE '%{$escapedSearch}%' 
                    OR tpd_search.username LIKE '%{$escapedSearch}%'
                )
            )";
            $dataBuilder->orWhere($translationSearchCondition, null, false);
            $dataBuilder->groupEnd();
        }
        if (isset($where) && !empty($where)) {
            $dataBuilder->where($where);
        }
        if (isset($whereIn) && !empty($whereIn)) {
            $dataBuilder->whereIn($column_name, $whereIn);
        }
        $dataBuilder->whereNotIn('pd.is_approved', $values);
        $partner_record = $dataBuilder->orderBy($sort, $order)->limit($limit, $offset)->get()->getResultArray();

        // Batch fetch all translations for all partners in a single query
        $allTranslations = [];
        if (!empty($partner_record)) {
            $partnerIds = array_column($partner_record, 'partner_id');

            $translatedPartnerDetailsModel = new \App\Models\TranslatedPartnerDetails_model();

            // Get all translations for all partners (not just current language)
            $allTranslations = $translatedPartnerDetailsModel->getAllTranslationsForPartners($partnerIds);
        }

        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();
        foreach ($partner_record as $row) {
            // Apply translations using our helper function
            $row = $this->applyTranslations($row, $allTranslations, $currentLang, $defaultLang);
            $operations =  '<button class="btn btn-success btn-sm pay-out" data-toggle="modal" data-target="#exampleModal"> 
            <i class="fa fa-pencil" aria-hidden="true"></i> 
            </button> ';
            $tempRow['partner_id'] = $row['partner_id'];
            $tempRow['balance'] = $row['balance'];
            $tempRow['company_name'] = $row['company_name'];
            $tempRow['operations'] = $operations;
            $tempRow['partner_name'] = $row['partner_name'] ;

            // Handle profile image with consistent fallback logic
            $imageSrc = service('fileService')->url($row['image'] ?? '', 'profile', 'public/backend/assets/default.png');

            $profile = '<div class="o-media o-media--middle">
                        <a href="' . $imageSrc . '" data-lightbox="image-1">
                            <img class="o-media__img images_in_card" src="' . $imageSrc . '" alt="' . $row['partner_name'] . '">
                        </a>';
            // Format phone with country code and leading + for display
            $phone = $row['phone'] ?? '';
            $countryCode = trim($row['country_code'] ?? '');
            $displayPhone = ($countryCode !== '' && $phone !== '')
                ? (strpos($countryCode, '+') === 0 ? $countryCode : '+' . $countryCode) . $phone
                : $phone;
            $profile .= '<a href="' . base_url('/admin/partners/general_outlook/' . $row['partner_id']) . '"><div class="o-media__body">
                <div class="provider_name_table" >' . $row['translated_partner_name'] .'</span></div>
                <div class="provider_email_table">' . $row['translated_company_name'] . '</div>
                <div class="provider_email_table">' . $row['email'] . '(' . htmlspecialchars($displayPhone, ENT_QUOTES) . ')</div>
                </div>
                </div></a>';

            // Use the translated fields from our helper function
            $tempRow['translated_company_name'] = $row['translated_company_name'];
            $tempRow['translated_partner_name'] = $profile;
            if ($from_app == false) {
                $tempRow['created_at'] = $row['created_at'];
            } else {
                $tempRow['status'] = $row['is_approved'];
            }
            $rows[] = $tempRow;
        }
        if ($from_app) {
            $response['total'] = $total;
            $response['data'] = $rows;
            return $response;
        } else {
            $bulkData['rows'] = $rows;
        }
        return $bulkData;
    }
    public function review()
    {
        $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
        $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
        $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
        $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
        $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
        $ratings = new Service_ratings_model();
        $data = $ratings->ratings_list(true, $search, $limit, $offset, $sort, $order, ['s.user_id' => $this->user_details['id']]);
        $bulkData = array();
        $rows = array();
        $tempRow = array();
        foreach ($data['data'] as $row) {
            $tempRow['id'] = $row['id'];
            $tempRow['user_name'] = $row['user_name'];
            $tempRow['profile_image'] = (!empty($row['profile_image']) && isset($row['profile_image'])) ? $row['profile_image'] : '';
            $tempRow['service_name'] = $row['service_name'];
            $tempRow['rating'] = $row['rating'];
            $tempRow['comment'] = $row['comment'];
            $tempRow['rated_on'] = $row['rated_on'];
            $tempRow['images'] = $row['images'];
            $rate_data = get_ratings($row['partner_id']);
            $tempRow['1_star'] = $rate_data[0]['rating_1'];
            $tempRow['2_star'] = $rate_data[0]['rating_2'];
            $tempRow['3_star'] = $rate_data[0]['rating_3'];
            $tempRow['4_star'] = $rate_data[0]['rating_4'];
            $tempRow['5_star'] = $rate_data[0]['rating_5'];
            $rows[] = $tempRow;
        }
        return $bulkData;
    }

    public function getApprovedProviders(): array
    {
        return $this->db->table('partner_details pd')
            ->select('pd.partner_id as id, pd.company_name, pd.max_serviceable_distance, u.username as name, u.email, u.phone, u.image')
            ->join('users u', 'u.id = pd.partner_id')
            ->join('users_groups ug', 'ug.user_id = u.id')
            ->where('ug.group_id', 3)
            ->where('pd.is_approved', 1)
            ->orderBy('pd.company_name', 'ASC')
            ->get()->getResultArray();
    }

    public function getCustomJobPreferences(int $partnerId): array
    {
        $row = $this->builder()
            ->select('custom_job_categories, is_accepting_custom_jobs')
            ->where('partner_id', $partnerId)
            ->get()->getRowArray();

        $rawCategories = $row['custom_job_categories'] ?? '';
        $categoryIds   = [];
        if (!empty($rawCategories)) {
            $decoded     = json_decode($rawCategories, true);
            $categoryIds = is_array($decoded) ? $decoded : [];
        }

        return [
            'custom_job_categories'    => $categoryIds,
            'is_accepting_custom_jobs' => $row['is_accepting_custom_jobs'] ?? null,
        ];
    }

    /**
     * Map each given category id to the active providers that serve it.
     *
     * Mirrors the provider filters used by get_providers/list(): partner is in
     * the providers group, approved, has an active subscription, has at least
     * one service in the category, and (when location is supplied) is within
     * the max serviceable distance. One set-based query is run for ALL category
     * ids so callers can aggregate counts in PHP and avoid an N+1.
     *
     * @param array $categoryIds     Category ids to resolve providers for.
     * @param array $additional_data Optional ['latitude','longitude','max_serviceable_distance'].
     * @return array Map of [category_id => [partner_id, ...]] (distinct).
     */
    public function getActiveProviderIdsByCategory(array $categoryIds, array $additional_data = []): array
    {
        $categoryIds = array_values(array_unique(array_map('intval', $categoryIds)));
        if (empty($categoryIds)) {
            return [];
        }

        $db = \Config\Database::connect();
        $builder = $db->table('services s');
        $builder->distinct()
            ->select('s.category_id, s.user_id as partner_id')
            ->join('partner_details pd', 'pd.partner_id = s.user_id')
            ->join('users_groups ug', 'ug.user_id = s.user_id')
            ->join('users u', 'u.id = s.user_id')
            ->join('partner_subscriptions ps', 'ps.partner_id = s.user_id')
            ->where('ug.group_id', 3)
            ->where('pd.is_approved', 1)
            ->where('ps.status', 'active')
            ->whereIn('s.category_id', $categoryIds);

        // Distance filter matches list(): ST_Distance_Sphere in km <= max.
        // Cast coordinates to float since they are interpolated into the spatial expression.
        if (!empty($additional_data['latitude']) && !empty($additional_data['longitude']) && !empty($additional_data['max_serviceable_distance'])) {
            $latitude  = (float) $additional_data['latitude'];
            $longitude = (float) $additional_data['longitude'];
            $settings  = get_settings('general_settings', true);
            $builder->where(
                get_provider_distance_sql($latitude, $longitude, 'u.id') . " <= " .
                    get_max_serviceable_distance_condition($settings, 'pd.max_serviceable_distance', $additional_data['max_serviceable_distance']),
                null,
                false
            );
        }

        $rows = $builder->get()->getResultArray();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['category_id']][] = $row['partner_id'];
        }
        return $map;
    }
}
