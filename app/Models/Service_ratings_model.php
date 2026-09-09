<?php

namespace App\Models;

use CodeIgniter\Model;

class Service_ratings_model extends Model
{
    protected $table = 'services_ratings';
    protected $primaryKey = 'id';
    // Include updated_at and created_at in allowedFields so they can be updated when rating is modified
    // This ensures the updated_at timestamp is properly saved to the database when ratings are updated
    protected $allowedFields = ['user_id', 'service_id', 'rating', 'comment', 'images', 'custom_job_request_id', 'created_at', 'updated_at'];

    // Enable automatic timestamp handling to ensure consistent timezone usage
    // CodeIgniter will automatically set created_at on insert and updated_at on update
    // This ensures both timestamps use the same timezone (system timezone set in Events.php)
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    public function ratings_list($from_app = false, $search = '', $limit = 10, $offset = 0, $sort = 'id', $order = 'ASC', $where = [], $column_name = 'id', $whereIn = [], $additional_data = [])
    {
        // Validate and sanitize parameters to prevent SQL injection
        // Cast limit and offset to integers to ensure they're safe
        $limit = max(1, (int) $limit);
        $offset = max(0, (int) $offset);

        // Validate sort field against whitelist to prevent SQL injection
        // Only allow sorting by safe column names that exist in the query
        $allowedSortFields = ['id', 'user_id', 'service_id', 'rating', 'comment', 'created_at', 'updated_at'];
        if (!in_array($sort, $allowedSortFields)) {
            $sort = 'id'; // Default to id if invalid sort field provided
        }

        // Validate order to be either 'ASC' or 'DESC' (case-insensitive)
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        // The partner_bids (pb) LEFT JOIN is only needed by callers that pass raw SQL
        // WHERE conditions referencing pb.* (numeric-keyed $where entries). Callers using
        // the parameterized additional_data['partner_id'] filter resolve partner ownership
        // via an EXISTS sub-query instead, so joining partner_bids there would only fan-out
        // rows (many bids per custom_job_request) and inflate COUNT()/breakdown numbers.
        $has_raw_where = false;
        if (!empty($where)) {
            foreach (array_keys($where) as $whereKey) {
                if (is_numeric($whereKey)) {
                    $has_raw_where = true;
                    break;
                }
            }
        }
        $partner_bids_join = $has_raw_where
            ? 'LEFT JOIN partner_bids pb ON pb.custom_job_request_id = sr.custom_job_request_id'
            : '';

        $multipleWhere = '';
        $db = \Config\Database::connect();
        $builder = $db->table('services_ratings sr');
        if ($search and $search != '') {
            // NOTE: keep column names unquoted so SQL engine recognizes aliases correctly.
            // Wrapping "sr.id" inside backticks creates an identifier literally named "sr.id",
            // which causes "Unknown column 'sr.id'" when MySQL tries to resolve it.
            $multipleWhere = [
                'sr.id' => $search,
                'sr.user_id' => $search,
                'sr.rating' => $search,
                'sr.comment' => $search,
                'sr.created_at' => $search,
                'u.username' => $search,
                's.title' => $search,
            ];
        }

        // $builder->select(' COUNT(sr.id) as `total` ')
        //     ->join('users u', 'u.id = sr.user_id')
        //     ->join('services s', 's.id = sr.service_id');

        // Get partner_id first if it exists for count query
        $partner_id = isset($additional_data['partner_id']) ? $additional_data['partner_id'] : null;

        // Build dynamic WHERE conditions for count query
        $count_where_conditions = [];
        $count_where_params = [];

        // Add search conditions if specified
        if (isset($multipleWhere) && !empty($multipleWhere)) {
            $search_conditions = [];
            foreach ($multipleWhere as $key => $value) {
                $search_conditions[] = "{$key} LIKE ?";
                $count_where_params[] = "%{$value}%";
            }
            if (!empty($search_conditions)) {
                $count_where_conditions[] = "(" . implode(' OR ', $search_conditions) . ")";
            }
        }

        // Add rating filter if specified
        if (isset($_GET['rating_star_filter']) && $_GET['rating_star_filter'] != '') {
            $count_where_conditions[] = 'sr.rating = ?';
            $count_where_params[] = $_GET['rating_star_filter'];
        }

        // Add partner filter if specified
        if (isset($additional_data['partner_id']) && !empty($additional_data['partner_id'])) {
            $count_where_conditions[] = "(s.user_id = ? OR (sr.custom_job_request_id IS NOT NULL AND EXISTS (SELECT 1 FROM partner_bids pbid WHERE pbid.custom_job_request_id = sr.custom_job_request_id AND pbid.partner_id = ?)))";
            $count_where_params[] = $partner_id;
            $count_where_params[] = $partner_id;
        }

        if (isset($additional_data['service_id']) && !empty($additional_data['service_id'])) {
            $count_where_conditions[] = "sr.service_id = ?";
            $count_where_params[] = (int) $additional_data['service_id'];
        }

        // Add existing WHERE conditions
        // Handle both array of strings (raw SQL) and associative arrays (key-value pairs)
        // WARNING: Raw SQL conditions should only be used with trusted, validated input
        // For user-provided data, always use key-value pairs which are parameterized
        if (isset($where) && !empty($where)) {
            foreach ($where as $key => $value) {
                // Check if this is a numeric key (array of strings) or string key (associative array)
                if (is_numeric($key)) {
                    // Raw SQL condition - WARNING: Only use with trusted, pre-validated input
                    // This should not contain any user-provided data without proper validation
                    $count_where_conditions[] = $value;
                } else {
                    // Key-value pair - use parameterized query to prevent SQL injection
                    // Column name should be validated, but value is safely parameterized
                    $count_where_conditions[] = "{$key} = ?";
                    $count_where_params[] = $value;
                }
            }
        }

        // Add WHERE IN conditions
        if (isset($whereIn) && !empty($whereIn)) {
            $placeholders = str_repeat('?,', count($whereIn) - 1) . '?';
            $count_where_conditions[] = "{$column_name} IN ({$placeholders})";
            $count_where_params = array_merge($count_where_params, $whereIn);
        }

        // Build the final WHERE clause for count
        $count_where_clause = '';
        if (!empty($count_where_conditions)) {
            $count_where_clause = 'WHERE ' . implode(' AND ', $count_where_conditions);
        }

        // Build the count SQL query
        // Add LEFT JOIN for partner_bids to support WHERE conditions that reference pb.partner_id
        $count_sql = "
            SELECT COUNT(sr.id) as total
            FROM services_ratings sr
            LEFT JOIN users u ON u.id = sr.user_id
            LEFT JOIN services s ON s.id = sr.service_id
            LEFT JOIN custom_job_requests cj ON cj.id = sr.custom_job_request_id
            {$partner_bids_join}
            {$count_where_clause}
        ";

        $ratings_total_count = $db->query($count_sql, $count_where_params)->getResultArray();
        $total = $ratings_total_count[0]['total'];

        // Get partner_id first if it exists for main query
        $partner_id = isset($additional_data['partner_id']) ? $additional_data['partner_id'] : null;

        // Build dynamic WHERE conditions
        $where_conditions = [];
        $where_params = [];

        // Add search conditions if specified
        // This ensures search filtering works in the main query, not just the count query
        if (isset($multipleWhere) && !empty($multipleWhere)) {
            $search_conditions = [];
            foreach ($multipleWhere as $key => $value) {
                $search_conditions[] = "{$key} LIKE ?";
                $where_params[] = "%{$value}%";
            }
            if (!empty($search_conditions)) {
                $where_conditions[] = "(" . implode(' OR ', $search_conditions) . ")";
            }
        }

        // Add rating filter if specified
        if (isset($_GET['rating_star_filter']) && $_GET['rating_star_filter'] != '') {
            $where_conditions[] = 'sr.rating = ?';
            $where_params[] = $_GET['rating_star_filter'];
        }

        // Add partner filter if specified
        if (isset($additional_data['partner_id']) && !empty($additional_data['partner_id'])) {
            $where_conditions[] = "(s.user_id = ? OR (sr.custom_job_request_id IS NOT NULL AND EXISTS (SELECT 1 FROM partner_bids pbid WHERE pbid.custom_job_request_id = sr.custom_job_request_id AND pbid.partner_id = ?)))";
            $where_params[] = $partner_id;
            $where_params[] = $partner_id;
        }

        if (isset($additional_data['service_id']) && !empty($additional_data['service_id'])) {
            $where_conditions[] = "sr.service_id = ?";
            $where_params[] = (int) $additional_data['service_id'];
        }

        // Add existing WHERE conditions
        // Handle both array of strings (raw SQL) and associative arrays (key-value pairs)
        // WARNING: Raw SQL conditions should only be used with trusted, validated input
        // For user-provided data, always use key-value pairs which are parameterized
        if (isset($where) && !empty($where)) {
            foreach ($where as $key => $value) {
                // Check if this is a numeric key (array of strings) or string key (associative array)
                if (is_numeric($key)) {
                    // Raw SQL condition - WARNING: Only use with trusted, pre-validated input
                    // This should not contain any user-provided data without proper validation
                    $where_conditions[] = $value;
                } else {
                    // Key-value pair - use parameterized query to prevent SQL injection
                    // Column name should be validated, but value is safely parameterized
                    $where_conditions[] = "{$key} = ?";
                    $where_params[] = $value;
                }
            }
        }

        // Add WHERE IN conditions
        if (isset($whereIn) && !empty($whereIn)) {
            $placeholders = str_repeat('?,', count($whereIn) - 1) . '?';
            $where_conditions[] = "{$column_name} IN ({$placeholders})";
            $where_params = array_merge($where_params, $whereIn);
        }

        // Build the final WHERE clause
        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }

        // Get current language and default language for translation fallback
        $currentLang = get_current_language();
        $defaultLang = get_default_language();

        // Build the raw SQL query with translation support
        // Use parameter binding for partner_id to prevent SQL injection
        // When partner_id is provided, use parameter binding; otherwise use s.user_id (column reference) or NULL
        $partner_id_param = null;

        if (!empty($partner_id)) {
            // Validate partner_id is an integer to prevent SQL injection
            $partner_id_param = (int) $partner_id;
        }

        // Build SQL with proper parameter binding
        // If partner_id is provided, use parameters; otherwise use column reference or NULL
        if ($partner_id_param !== null) {
            // Partner ID provided: use parameter binding for both CASE branches
            $sql = "
                SELECT 
                    sr.*,
                    u.image AS profile_image,
                    u.username,
                    CASE 
                        WHEN sr.service_id IS NOT NULL THEN ?
                        WHEN sr.custom_job_request_id IS NOT NULL THEN ?
                        ELSE NULL
                    END AS partner_id,
                    COALESCE(s.title, cj.service_title) AS service_name,
                    s.title AS original_service_title,
                    cj.service_title AS custom_job_title
                FROM services_ratings sr
                LEFT JOIN users u ON u.id = sr.user_id
                LEFT JOIN services s ON s.id = sr.service_id
                LEFT JOIN custom_job_requests cj ON cj.id = sr.custom_job_request_id
                {$partner_bids_join}
                {$where_clause}
                ORDER BY sr.{$sort} {$order}
                LIMIT ? OFFSET ?
            ";
            // Add partner_id parameters at the beginning (before WHERE params), then limit and offset
            $query_params = array_merge([$partner_id_param, $partner_id_param], $where_params, [$limit, $offset]);
        } else {
            // No partner_id: use column reference s.user_id for first case, NULL for second case
            $sql = "
                SELECT 
                    sr.*,
                    u.image AS profile_image,
                    u.username,
                    CASE 
                        WHEN sr.service_id IS NOT NULL THEN s.user_id
                        WHEN sr.custom_job_request_id IS NOT NULL THEN NULL
                        ELSE NULL
                    END AS partner_id,
                    COALESCE(s.title, cj.service_title) AS service_name,
                    s.title AS original_service_title,
                    cj.service_title AS custom_job_title
                FROM services_ratings sr
                LEFT JOIN users u ON u.id = sr.user_id
                LEFT JOIN services s ON s.id = sr.service_id
                LEFT JOIN custom_job_requests cj ON cj.id = sr.custom_job_request_id
                {$partner_bids_join}
                {$where_clause}
                ORDER BY sr.{$sort} {$order}
                LIMIT ? OFFSET ?
            ";
            // Just add limit and offset parameters
            $query_params = array_merge($where_params, [$limit, $offset]);
        }

        $rating_records = $db->query($sql, $query_params)->getResultArray();

        // Get service translations for all services in the results
        $serviceIds = array_filter(array_column($rating_records, 'service_id'));

        $serviceTranslations = [];
        if (!empty($serviceIds)) {
            $serviceTranslations = $this->getServiceTranslations($serviceIds);
        }

        // print_r($db->getLastQuery());
        // die;
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();
        $fileService = service('fileService');

        foreach ($rating_records as $row) {

            $partnerDetails = fetch_details('users', ['id' => $row['partner_id']], ['username']);
            $partner_name = !empty($partnerDetails[0]['username']) ? $partnerDetails[0]['username'] : "";
            $tempRow['id'] = $row['id'];
            $tempRow['partner_id'] = $row['partner_id'];
            $tempRow['partner_name'] = $partner_name;
            $tempRow['user_name'] = $row['username'];

            $tempRow['profile_image'] = $fileService->url($row['profile_image'] ?? '', 'profile', 'public/uploads/profiles/default.png');

            $tempRow['user_id'] = $row['user_id'];
            $tempRow['service_id'] = $row['service_id'];

            // Keep original service name
            $tempRow['service_name'] = $row['service_name'];

            // Add translated service name with fallback logic
            if (!empty($row['service_id'])) {
                // Get translations for this specific service
                $serviceId = $row['service_id'];
                $serviceTrans = $serviceTranslations[$serviceId] ?? [];

                // Apply fallback chain: current language → default language → main table → first available
                $tempRow['translated_service_name'] = $this->getTranslatedServiceNameWithFallback(
                    $row,
                    $currentLang,
                    $defaultLang,
                    $serviceTrans
                );
            } else {
                // For custom job requests, use the custom job title directly
                $tempRow['translated_service_name'] = $row['custom_job_title'] ?? $row['service_name'];
            }

            $tempRow['rating'] = $row['rating'];
            $tempRow['comment'] = ($row['comment'] != "") ? $row['comment'] : "";

            $tempRow['rated_on'] = $row['created_at'] ? \CodeIgniter\I18n\Time::parse($row['created_at'])->setTimezone('UTC')->format('Y-m-d\TH:i:s.000\Z') : null;

            $tempRow['created_at'] = $row['created_at'];

            $tempRow['custom_job_request_id'] = $row['custom_job_request_id'];

            // Set the updated_at timestamp for when the rating was last modified
            // This field is now properly saved when ratings are updated thanks to updated_at being in allowedFields
            $tempRow['rate_updated_on'] = $row['updated_at'];
            if ($from_app == false) {
                $tempRow['stars'] = '<i class="fa-solid fa-star text-warning"></i>' . $row['rating'];
                if ($row['images'] != "") {
                    $images = rating_images($row['id'], false);
                    $tempRow['images'] = $images;
                } else {
                    $tempRow['images'] = array();
                }

                $default_image_url = base_url('public/backend/assets/default.png');
                $images_html = '';

                if ($row['images'] != "") {
                    $rating_images = json_decode($row['images'], true);

                    if (is_array($rating_images) && !empty($rating_images)) {
                        foreach ($rating_images as $image_path) {
                            if (!empty($image_path)) {
                                $image_url = $fileService->url($image_path, 'ratings', 'public/backend/assets/default.png');

                                // Create image HTML with lightbox support, similar to provider profile images
                                $images_html .= '<a href="' . $image_url . '" data-lightbox="image-1">
                                    <img class="o-media__img images_in_card" src="' . $image_url . '" alt="Rating Image" style="height: 50px; width: 50px; object-fit: cover; margin: 2px;">
                                </a>';
                            }
                        }
                    }
                }

                // If no images were processed or no images exist, show default image
                // Display default image in the same format as actual images
                if (empty($images_html)) {
                    $tempRow['view_images'] = '<a href="' . $default_image_url . '" data-lightbox="image-1">
                        <img class="o-media__img images_in_card" src="' . $default_image_url . '" alt="No Image" style="height: 50px; width: 50px; object-fit: cover; margin: 2px;">
                    </a>';
                } else {
                    $tempRow['view_images'] = $images_html;
                }
            } else {
                if ($row['images'] != "") {
                    $images = rating_images($row['id'], true);
                    $tempRow['images'] = $images;
                } else {
                    $tempRow['images'] = array();
                }
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


    /**
     * Aggregated rating summary for a provider's review screen.
     *
     * Uses the SAME criteria as ratings_list() with additional_data['partner_id']:
     * service-owned ratings (s.user_id) OR custom-job ratings the partner bid on,
     * resolved via an EXISTS partner_bids semi-join (no JOIN fan-out). This guarantees
     * the summary card total/breakdown match the paginated table exactly.
     *
     * @param int|string $partner_id
     * @return array
     */
    public function partner_ratings_summary($partner_id): array
    {
        $partner_id = (int) $partner_id;
        $db = \Config\Database::connect();

        $sql = "
            SELECT
                COUNT(sr.id) AS total_ratings,
                COALESCE(AVG(sr.rating), 0) AS average_rating,
                SUM(CASE WHEN sr.rating = 5 THEN 1 ELSE 0 END) AS rating_5,
                SUM(CASE WHEN sr.rating = 4 THEN 1 ELSE 0 END) AS rating_4,
                SUM(CASE WHEN sr.rating = 3 THEN 1 ELSE 0 END) AS rating_3,
                SUM(CASE WHEN sr.rating = 2 THEN 1 ELSE 0 END) AS rating_2,
                SUM(CASE WHEN sr.rating = 1 THEN 1 ELSE 0 END) AS rating_1
            FROM services_ratings sr
            LEFT JOIN services s ON s.id = sr.service_id
            WHERE (
                s.user_id = ?
                OR (
                    sr.custom_job_request_id IS NOT NULL
                    AND EXISTS (
                        SELECT 1 FROM partner_bids pbid
                        WHERE pbid.custom_job_request_id = sr.custom_job_request_id
                          AND pbid.partner_id = ?
                    )
                )
            )
        ";

        $row = $db->query($sql, [$partner_id, $partner_id])->getRowArray() ?: [];

        $total = (int) ($row['total_ratings'] ?? 0);
        $pct = static function ($count) use ($total) {
            return $total > 0 ? ($count * 100) / $total : 0;
        };

        $r5 = (int) ($row['rating_5'] ?? 0);
        $r4 = (int) ($row['rating_4'] ?? 0);
        $r3 = (int) ($row['rating_3'] ?? 0);
        $r2 = (int) ($row['rating_2'] ?? 0);
        $r1 = (int) ($row['rating_1'] ?? 0);

        return [
            'average_rating' => number_format((float) ($row['average_rating'] ?? 0), 2),
            'total_ratings' => $total,
            'rating_5' => $r5,
            'rating_4' => $r4,
            'rating_3' => $r3,
            'rating_2' => $r2,
            'rating_1' => $r1,
            'rating_5_percentage' => $pct($r5),
            'rating_4_percentage' => $pct($r4),
            'rating_3_percentage' => $pct($r3),
            'rating_2_percentage' => $pct($r2),
            'rating_1_percentage' => $pct($r1),
        ];
    }

    /**
     * Get service translations for multiple service IDs
     *
     * @param array $serviceIds Array of service IDs
     * @return array Service translations grouped by service ID and language
     */
    private function getServiceTranslations(array $serviceIds): array
    {
        if (empty($serviceIds)) {
            return [];
        }

        $db = \Config\Database::connect();

        // Validate and sanitize service IDs to prevent SQL injection
        // Cast all IDs to integers and filter out invalid values
        $validServiceIds = array_filter(array_map('intval', $serviceIds), function ($id) {
            return $id > 0; // Only allow positive integers
        });

        // Re-index array to ensure sequential indices starting from 0
        // array_filter() preserves original keys, which can cause issues with query binding
        // CodeIgniter's query binding expects a sequential array with indices 0, 1, 2, ...
        $validServiceIds = array_values($validServiceIds);

        if (empty($validServiceIds)) {
            return [];
        }

        // Use parameter binding for IN clause to prevent SQL injection
        // Create placeholders for each service ID
        $placeholders = str_repeat('?,', count($validServiceIds) - 1) . '?';

        $sql = "
            SELECT 
                service_id,
                language_code,
                title,
                description,
                long_description
            FROM translated_service_details 
            WHERE service_id IN ({$placeholders})
        ";

        // Pass validated service IDs as parameters
        $translations = $db->query($sql, $validServiceIds)->getResultArray();

        // Group translations by service ID and language
        $groupedTranslations = [];
        foreach ($translations as $translation) {
            $serviceId = $translation['service_id'];
            $languageCode = $translation['language_code'];

            if (!isset($groupedTranslations[$serviceId])) {
                $groupedTranslations[$serviceId] = [];
            }

            $groupedTranslations[$serviceId][$languageCode] = $translation;
        }

        return $groupedTranslations;
    }

    /**
     * Get translated service name with fallback logic
     * Priority: current language → default language → main table → first available translation
     * 
     * @param array $serviceData Service data from database
     * @param string $currentLang Current language code
     * @param string $defaultLang Default language code
     * @param array $serviceTranslations Translations for this service
     * @return string Service name with fallback
     */
    private function getTranslatedServiceNameWithFallback(array $serviceData, string $currentLang, string $defaultLang, array $serviceTranslations): string
    {
        // If no translations available, use main table title
        if (empty($serviceTranslations)) {
            return $serviceData['original_service_title'] ?? $serviceData['service_name'] ?? '';
        }

        $currentTranslation = null;
        $defaultTranslation = null;
        $firstAvailable = null;

        // Loop through all language translations for this service
        foreach ($serviceTranslations as $languageCode => $translation) {
            if ($firstAvailable === null && !empty($translation['title'])) {
                $firstAvailable = $translation['title'];
            }
            if ($languageCode === $currentLang && !empty($translation['title'])) {
                $currentTranslation = $translation['title'];
            }
            if ($languageCode === $defaultLang && !empty($translation['title'])) {
                $defaultTranslation = $translation['title'];
            }
        }

        // Apply fallback chain: current language → default language → main table → first available
        return $currentTranslation
            ?? $defaultTranslation
            ?? ($serviceData['original_service_title'] ?? '')
            ?? $firstAvailable
            ?? '';
    }
}
