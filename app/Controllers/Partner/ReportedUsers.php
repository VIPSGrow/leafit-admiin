<?php

namespace App\Controllers\Partner;

use App\Controllers\BaseController;

class ReportedUsers extends Partner
{
    public function __construct()
    {
        parent::__construct();
        $this->userId = $_SESSION['user_id'];
    }

    public function index()
    {
        if (!$this->isLoggedIn && !$this->userIsPartner) {
            return redirect('partner/login');
        }
        setPageInfo($this->data, labels('reported_users', 'Reported Users') . ' | ' . labels('provider_panel', 'Provider Panel'), 'reported_users');
        return view('backend/partner/template', $this->data);
    }
    public function list()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsPartner) {
                return redirect('partner/login');
            }

            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'DESC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';

            $db = \Config\Database::connect();

            // Get current language and default language for searching translated reasons
            $session = session();
            $currentLanguage = $session->get('lang') ?? 'en';
            $defaultLanguageData = fetch_details('languages', ['is_default' => 1], ['code']);
            $defaultLanguage = !empty($defaultLanguageData) ? $defaultLanguageData[0]['code'] : 'en';

            // If no language set in session, try to get from database
            if ($currentLanguage === 'en') {
                if (!empty($defaultLanguageData)) {
                    $currentLanguage = $defaultLanguageData[0]['code'];
                }
            }

            // Show both cases: provider blocks user (provider is reporter) AND user blocks provider (provider is reported)
            // This ensures we see all blocking relationships involving the current provider
            // Fix: Added r.reason as report_reason to SELECT so it's available for fallback when translations are missing
            $builder = $db->table('user_reports ur')
                ->select('ur.*, 
                    reporter.username as reporter_name, 
                    reported.username as reported_name,
                    r.id as reason_id,
                    r.reason as report_reason')
                ->groupStart()
                ->where('ur.reporter_id', $this->userId)  // Provider blocks user
                ->orWhere('ur.reported_user_id', $this->userId)  // User blocks provider
                ->groupEnd()
                ->join('users reporter', 'reporter.id = ur.reporter_id')
                ->join('users reported', 'reported.id = ur.reported_user_id')
                ->join('reasons_for_report_and_block_chat r', 'r.id = ur.reason_id', 'left');

            // Search functionality: search by user name and translated report reasons
            if (!empty($search)) {
                // Start a group for OR conditions
                $builder->groupStart();

                // Search by both reporter and reported user names
                // This allows searching regardless of who blocked whom
                $builder->like('reported.username', $search)
                    ->orLike('reporter.username', $search);

                // Search in translated reasons
                // Use a subquery to find reason IDs that have matching translations
                // Search in both current language and default language
                $translationBuilder = $db->table('translated_reasons_for_report_and_block_chat')
                    ->select('reason_id')
                    ->distinct()
                    ->groupStart()
                    ->like('reason', $search)
                    ->whereIn('language_code', [$currentLanguage, $defaultLanguage])
                    ->groupEnd();

                $matchingReasonIds = [];
                $translationResults = $translationBuilder->get()->getResultArray();
                if (!empty($translationResults)) {
                    $matchingReasonIds = array_column($translationResults, 'reason_id');
                }

                // If we found matching reason IDs, filter by them
                // If no matches found, we don't add any condition here
                // This means only username matches will be shown (which is correct)
                if (!empty($matchingReasonIds)) {
                    $builder->orWhereIn('r.id', $matchingReasonIds);
                }
                // If no translation matches, we don't add any OR condition
                // The username search above will still work

                // Close the main group
                $builder->groupEnd();
            }

            $total = $builder->countAllResults(false);
            $builder->orderBy($sort, $order);
            $builder->limit($limit, $offset);
            $reports = $builder->get()->getResultArray();

            // Get current language for translations
            $session = session();
            $currentLanguage = $session->get('lang') ?? 'en';

            // If no language set in session, try to get from database
            if ($currentLanguage === 'en') {
                $defaultLanguage = fetch_details('languages', ['is_default' => 1], ['code']);
                if (!empty($defaultLanguage)) {
                    $currentLanguage = $defaultLanguage[0]['code'];
                }
            }

            // Get all reason IDs to fetch translations
            $reasonIds = array_column($reports, 'reason_id');
            $translatedReasonModel = new \App\Models\TranslatedReasonsForReportAndBlockChat_model();
            $translations = [];

            if (!empty($reasonIds)) {
                $translations = $translatedReasonModel->getTranslationsForReasons($reasonIds, $currentLanguage);
            }

            // Create lookup array for translations
            $translationLookup = [];
            foreach ($translations as $translation) {
                $translationLookup[$translation['reason_id']] = $translation['reason'];
            }

            // Get default language code
            $defaultLanguage = fetch_details('languages', ['is_default' => 1], ['code'])[0]['code'] ?? 'en';

            // Get default language translations
            $defaultTranslations = [];
            if (!empty($reasonIds)) {
                $defaultTranslations = $translatedReasonModel->getTranslationsForReasons($reasonIds, $defaultLanguage);
            }

            // Create lookup array for default translations
            $defaultTranslationLookup = [];
            foreach ($defaultTranslations as $translation) {
                $defaultTranslationLookup[$translation['reason_id']] = $translation['reason'];
            }

            $data = [];
            foreach ($reports as $report) {
                $tempRow = $report;
                // Add translated reason text
                // Priority: current language translation -> default language translation -> main table data
                // This matches the admin panel behavior for consistency
                $tempRow['report_reason'] = $translationLookup[$report['reason_id']]
                    ?? $defaultTranslationLookup[$report['reason_id']]
                    ?? $report['report_reason']
                    ?? '';
                $tempRow['translated_report_reason'] = $translationLookup[$report['reason_id']] ?? null;

                // Format the created_at time with timezone conversion
                // This ensures the table and detail view show the same formatted time
                // The database timezone is set in Events.php, so dates might already be in system timezone
                // We parse in system timezone first, then convert if needed (similar to diffForHumans function)
                if (!empty($tempRow['created_at'])) {
                    try {
                        // Get system timezone settings
                        $settings = get_settings('general_settings', true);
                        $timezoneName = $settings['system_timezone'] ?? date_default_timezone_get();
                        $timezone = new \DateTimeZone($timezoneName);

                        // Parse the datetime in system timezone first
                        // If the database timezone is set, dates are already in system timezone
                        // If parsing fails, fall back to UTC conversion
                        try {
                            $createdAt = new \DateTime($tempRow['created_at'], $timezone);
                        } catch (\Exception $e) {
                            // Fall back to parsing without timezone, then convert to system timezone
                            // This handles cases where the date is stored in UTC
                            $createdAt = new \DateTime($tempRow['created_at']);
                            $createdAt->setTimezone($timezone);
                        }

                        // Format as readable date and time (same format as detail view)
                        $tempRow['created_at'] = $createdAt->format('d/m/Y - h:i A');
                    } catch (\Exception $e) {
                        // If timezone conversion fails, keep original value
                        log_message('error', 'Timezone conversion error in ReportedUsers list: ' . $e->getMessage());
                    }
                }

                $tempRow['operations'] = '<div class="btn-group">
                    <button type="button" class="btn btn-primary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item view-user-report" data-id="' . $report['id'] . '">
                            <i class="fas fa-eye text-primary"></i> ' . labels('view_details', 'View Details') . '
                        </a>
                    </div>
                </div>';
                $data[] = $tempRow;
            }

            return $this->response->setJSON([
                'total' => $total,
                'rows' => $data
            ]);
        } catch (\Throwable $th) {
            throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/ReportedUsers.php - list()');
            return $this->response->setJSON(['error' => true, 'message' => 'Something went wrong']);
        }
    }

    public function view($id)
    {
        $db = \Config\Database::connect();
        $builder = $db->table('user_reports ur')
            ->select('ur.*, 
            reporter.username as reporter_name, 
            reporter.email as reporter_email,
            reported.username as reported_name,
            reported.email as reported_email,
            r.id as reason_id')
            ->join('users reporter', 'reporter.id = ur.reporter_id')
            ->join('users reported', 'reported.id = ur.reported_user_id')
            ->join('reasons_for_report_and_block_chat r', 'r.id = ur.reason_id', 'left')
            ->where('ur.id', $id);

        $report = $builder->get()->getRowArray();

        if (!$report) {
            return $this->response->setJSON([
                'error' => true,
                'message' => labels('report_not_found', 'Report not found')
            ]);
        }

        // Security check: Ensure the report is related to the current provider
        // The provider can only view reports where they are either the reporter or the reported user
        $isProviderReporter = ($report['reporter_id'] == $this->userId);
        $isProviderReported = ($report['reported_user_id'] == $this->userId);

        if (!$isProviderReporter && !$isProviderReported) {
            return $this->response->setJSON([
                'error' => true,
                'message' => labels('unauthorized_access', 'Unauthorized access')
            ]);
        }

        // Validate that reporter_id and reported_user_id are different
        // This ensures the report is valid (a user can't report themselves)
        if ($report['reporter_id'] == $report['reported_user_id']) {
            log_message('error', 'ReportedUsers view: Reporter and Reported user IDs are the same for report ID: ' . $id);
            return $this->response->setJSON([
                'error' => true,
                'message' => labels('invalid_report_data', 'Invalid report data')
            ]);
        }

        // Validate that reporter and reported emails are different
        // This helps identify if there's a data issue with the joins
        if (!empty($report['reporter_email']) && !empty($report['reported_email'])) {
            if ($report['reporter_email'] === $report['reported_email']) {
                // Log this as a potential data issue - this shouldn't happen if the joins are correct
                log_message('warning', 'ReportedUsers view: Reporter and Reported emails are the same for report ID: ' . $id .
                    ' (Reporter ID: ' . $report['reporter_id'] . ', Reported ID: ' . $report['reported_user_id'] . ')');
                // Re-fetch the emails directly to ensure we have the correct data
                $reporterData = fetch_details('users', ['id' => $report['reporter_id']], ['email']);
                $reportedData = fetch_details('users', ['id' => $report['reported_user_id']], ['email']);
                if (!empty($reporterData) && !empty($reportedData)) {
                    $report['reporter_email'] = $reporterData[0]['email'] ?? $report['reporter_email'];
                    $report['reported_email'] = $reportedData[0]['email'] ?? $report['reported_email'];
                }
            }
        }

        // Ensure we have the correct emails based on context
        // When provider blocks user: reporter = provider, reported = user
        // When user blocks provider: reporter = user, reported = provider
        // The query already joins correctly, but we need to verify the data is correct
        // If provider is the reporter, then reporter_email is provider's email (correct)
        // If provider is the reported, then reported_email is provider's email (correct)
        // So the data should already be correct, but we'll add a flag to help with display
        $report['is_provider_reporter'] = $isProviderReporter;
        $report['is_provider_reported'] = $isProviderReported;

        // Get translated reason text
        if (!empty($report['reason_id'])) {
            $session = session();
            $currentLanguage = $session->get('lang') ?? 'en';

            // If no language set in session, try to get from database
            if ($currentLanguage === 'en') {
                $defaultLanguage = fetch_details('languages', ['is_default' => 1], ['code']);
                if (!empty($defaultLanguage)) {
                    $currentLanguage = $defaultLanguage[0]['code'];
                }
            }

            $translatedReasonModel = new \App\Models\TranslatedReasonsForReportAndBlockChat_model();

            // Get default language translation
            $defaultLanguage = fetch_details('languages', ['is_default' => 1], ['code'])[0]['code'] ?? 'en';
            $defaultTranslation = $translatedReasonModel->getTranslatedReasonText($report['reason_id'], $defaultLanguage);

            // Get current language translation
            $currentTranslation = $translatedReasonModel->getTranslatedReasonText($report['reason_id'], $currentLanguage);

            // Set report_reason with current language translation first, then fallback to default language or main table data
            // Priority: current language translation -> default language translation -> main table data
            // This matches the admin panel behavior for consistency
            $report['report_reason'] = $currentTranslation ?? $defaultTranslation ?? $report['report_reason'] ?? '';

            // Set translated_report_reason with current language translation if available
            $report['translated_report_reason'] = $currentTranslation;
        } else {
            $report['report_reason'] = labels('no_reason_specified', 'No reason specified');
            $report['translated_report_reason'] = null;
        }

        // Format the created_at time with timezone conversion
        // This fixes the incorrect time display issue
        // Uses the same formatting logic as the list() method for consistency
        // The database timezone is set in Events.php, so dates might already be in system timezone
        // We parse in system timezone first, then convert if needed (similar to diffForHumans function)
        if (!empty($report['created_at'])) {
            try {
                // Get system timezone settings
                $settings = get_settings('general_settings', true);
                $timezoneName = $settings['system_timezone'] ?? date_default_timezone_get();
                $timezone = new \DateTimeZone($timezoneName);

                // Parse the datetime in system timezone first
                // If the database timezone is set, dates are already in system timezone
                // If parsing fails, fall back to UTC conversion
                try {
                    $createdAt = new \DateTime($report['created_at'], $timezone);
                } catch (\Exception $e) {
                    // Fall back to parsing without timezone, then convert to system timezone
                    // This handles cases where the date is stored in UTC
                    $createdAt = new \DateTime($report['created_at']);
                    $createdAt->setTimezone($timezone);
                }

                // Format as readable date and time (same format as table)
                $formattedTime = $createdAt->format('d/m/Y - h:i A');
                $report['created_at_formatted'] = $formattedTime;
                // Also update the original for consistency with table display
                $report['created_at'] = $formattedTime;
            } catch (\Exception $e) {
                // If timezone conversion fails, use the original value
                $report['created_at_formatted'] = $report['created_at'];
                log_message('error', 'Timezone conversion error in ReportedUsers view: ' . $e->getMessage());
            }
        } else {
            $report['created_at_formatted'] = '';
        }

        return $this->response->setJSON([
            'error' => false,
            'data' => $report
        ]);
    }
}
