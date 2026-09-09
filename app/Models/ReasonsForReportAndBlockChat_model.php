<?php

namespace App\Models;

use CodeIgniter\Model;

class ReasonsForReportAndBlockChat_model extends Model
{
    protected $table = 'reasons_for_report_and_block_chat';
    // Keep 'reason' in allowedFields for backward compatibility during migration
    protected $allowedFields = ['reason', 'needs_additional_info', 'type', 'created_at', 'updated_at'];
    protected $primaryKey = 'id';

    public function list($is_admin_panel, $where, $search = '', $limit = 10, $offset = 0, $sort = 'r.id', $order = 'DESC', $from_app = false)
    {
        $db      = \Config\Database::connect();
        $builder = $db->table('reasons_for_report_and_block_chat r');
        $search = trim($search);
        $shouldApplySearch = ($search !== '');

        $condition = $bulkData = $rows = $tempRow = [];
        if ((isset($search) && !empty($search) && $search != "") || (isset($_GET['search']) && $_GET['search'] != '')) {
            $search = trim((isset($_GET['search']) && $_GET['search'] != '') ? $_GET['search'] : $search);
            $shouldApplySearch = ($search !== '');
        }
        if (isset($_GET['offset']))
            $offset = $_GET['offset'];
        if (isset($_GET['limit'])) {
            $limit = $_GET['limit'];
        }
        if (isset($_GET['sort'])) {
            if ($_GET['sort'] == 'pc.id') {
                $sort = "r.id";
            } else {
                $sort = $_GET['sort'];
            }
        }
        if (isset($_GET['order'])) {
            $order = $_GET['order'];
        }

        // Get current language for translation filtering
        // This ensures we only join translations for the current language, preventing duplicate counts
        if (!function_exists('get_current_language')) {
            helper('function');
        }
        $currentLang = get_current_language();

        // Count query - build separately to ensure accurate count
        // Create a separate builder instance for count to avoid state issues
        // This ensures the count query structure matches the main query structure
        $countBuilder = $db->table('reasons_for_report_and_block_chat r');
        $countBuilder->select('COUNT(DISTINCT r.id) as `total`');

        if ($shouldApplySearch) {
            // Search should work for multilingual content, so include translation table
            // Filter by current language to prevent duplicate rows from multiple translations
            // This matches the pattern used in other models for accurate counting
            $countBuilder->join('translated_reasons_for_report_and_block_chat tr', "tr.reason_id = r.id AND tr.language_code = '$currentLang'", 'left');
            $countBuilder->groupStart();
            $countBuilder->orLike('r.id', $search);
            $countBuilder->orLike('r.reason', $search);
            $countBuilder->orLike('tr.reason', $search);
            $countBuilder->groupEnd();
        }

        if (isset($where) && !empty($where)) {
            $countBuilder->where($where);
        }

        $count = $countBuilder->get()->getResultArray();
        $total = $count[0]['total'];
        // Reinitialize builder for listing query
        // Create a fresh builder instance for the main query to ensure clean state
        $builder = $db->table('reasons_for_report_and_block_chat r');
        $builder->select('r.id, r.reason, r.needs_additional_info, r.type, r.created_at, r.updated_at');

        if ($shouldApplySearch) {
            // Repeat multilingual search filters for the listing query
            // Filter by current language to match the count query and prevent duplicate rows
            // This ensures consistency between count and data queries
            $builder->join('translated_reasons_for_report_and_block_chat tr', "tr.reason_id = r.id AND tr.language_code = '$currentLang'", 'left');
            $builder->groupStart();
            $builder->orLike('r.id', $search);
            $builder->orLike('r.reason', $search);
            $builder->orLike('tr.reason', $search);
            $builder->groupEnd();
        }

        if (isset($where) && !empty($where)) {
            $builder->where($where);
        }

        // Group by to ensure unique results (in case of any edge cases with translations)
        // This is a safety measure to prevent duplicate rows in results
        $builder->groupBy('r.id');
        $admin_contact_query = $builder->orderBy($sort, $order)->limit($limit, $offset)->get()->getResultArray();
        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();

        // Ensure helper functions are available
        // Load function helper if not already loaded
        if (!function_exists('get_current_language')) {
            helper('function');
        }

        // Get current language for displaying translated reason
        // This is the language selected in the admin panel
        // Use helper function to get current language from session or default
        $currentLanguage = get_current_language();

        // Get default language code for fallback
        // Use helper function to get default language from database
        $defaultLanguage = get_default_language();

        // Get all reason IDs to fetch translations
        $reasonIds = array_column($admin_contact_query, 'id');

        // Initialize translated reason model for fetching translations
        $translatedReasonModel = new \App\Models\TranslatedReasonsForReportAndBlockChat_model();

        foreach ($admin_contact_query as $row) {
            $tempRow['id'] = $row['id'];

            // Implement proper fallback chain for displaying reason:
            // 1. First try current language translation
            // 2. If not available, try default language translation (only if different from current)
            // 3. If still not available, use base table data
            $displayReason = null;

            // Step 1: Try to get translation for current language
            // Always try current language first, even if it's the same as default
            $currentTranslation = $translatedReasonModel->getTranslatedDetails($row['id'], $currentLanguage);
            if ($currentTranslation && !empty($currentTranslation['reason'])) {
                $displayReason = $currentTranslation['reason'];
            }

            // Step 2: If current language translation not found, try default language translation
            // Only try default language if it's different from current language
            if (empty($displayReason) && $currentLanguage !== $defaultLanguage) {
                $defaultTranslation = $translatedReasonModel->getTranslatedDetails($row['id'], $defaultLanguage);
                if ($defaultTranslation && !empty($defaultTranslation['reason'])) {
                    $displayReason = $defaultTranslation['reason'];
                }
            }

            // Step 3: If still not found, use base table data as final fallback
            if (empty($displayReason)) {
                $displayReason = $row['reason'] ?? '';
            }

            // Set the reason field with the properly resolved value following fallback chain
            $tempRow['reason'] = $displayReason;

            // Keep translated_reason for backward compatibility (same as reason now)
            $tempRow['translated_reason'] = $displayReason;

            $tempRow['needs_additional_info'] = $row['needs_additional_info'];
            $tempRow['created_at'] = $row['created_at'];
            $tempRow['updated_at'] = $row['updated_at'];

            $needs_additional_info_badge = ($row['needs_additional_info'] == 1) ?
                "<div class='  text-emerald-success  ml-3 mr-3 mx-5'>" . labels('yes', 'Yes') . "
            </div>" :
                "<div class=' text-emerald-danger ml-3 mr-3 '>" . labels('no', 'No') . "
            </div>";
            $tempRow['needs_additional_info_badge'] = $needs_additional_info_badge;
            $operations = '<div class="dropdown">
            <a class="" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <button class="btn btn-secondary   btn-sm px-3"> <i class="fas fa-ellipsis-v "></i></button>
            </a><div class="dropdown-menu" aria-labelledby="dropdownMenuLink">';
            // Edit: Bootstrap Table event handler fires the AJAX and opens the modal
            $operations .= '<a class="dropdown-item edit_reason" href="#" data-id="' . $row['id'] . '"><i class="fa fa-pen mr-1 text-primary"></i> ' . labels('edit', 'Edit') . '</a>';
            $operations .= '<a class="dropdown-item remove_reason" data-id="' . $row['id'] . '" onclick="reason_id(this)" data-toggle="modal" data-target="#delete_modal"> <i class="fa fa-trash text-danger mr-1"></i> ' . labels('delete', 'Delete') . '</a>';
            $operations .= '</div></div>';
            $tempRow['operations'] = $operations;
            $tempRow['type'] = $row['type'];


            $rows[] = $tempRow;
        }
        $bulkData['rows'] = $rows;
        if ($from_app) {
            return $rows;
        } else {
            return json_encode($bulkData);
        }
    }

    /**
     * Get translated reason text with fallback logic
     * 
     * @param int $reasonId Reason ID
     * @param string $languageCode Language code (optional, uses current language if not provided)
     * @return string Reason text in the specified language or fallback
     */
    public function getTranslatedReasonText(int $reasonId, ?string $languageCode = null): string
    {
        // If no language code provided, get current language
        if (!$languageCode) {
            $session = session();
            $languageCode = $session->get('lang') ?? 'en';

            // If no language set in session, try to get from database
            if ($languageCode === 'en') {
                $defaultLanguage = fetch_details('languages', ['is_default' => 1], ['code']);
                if (!empty($defaultLanguage)) {
                    $languageCode = $defaultLanguage[0]['code'];
                }
            }
        }

        // Get default language code
        $defaultLanguage = fetch_details('languages', ['is_default' => 1], ['code'])[0]['code'] ?? 'en';

        $translatedReasonModel = new \App\Models\TranslatedReasonsForReportAndBlockChat_model();
        $translatedText = $translatedReasonModel->getTranslatedReasonText($reasonId, $languageCode, $defaultLanguage);

        return $translatedText ?? 'No translation available';
    }
}
