<?php

namespace App\Controllers\Admin;

use App\Controllers\Admin\Admin;

class UserReports extends Admin
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        if ($this->isLoggedIn && $this->userIsAdmin) {
            setPageInfo($this->data, labels('user_reports', 'User Reports') . ' | ' . labels('admin_panel', 'Admin Panel'), 'user_reports');
            return view('backend/admin/template', $this->data);
        } else {
            return redirect('admin/login');
        }
    }

    public function list()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }

            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'DESC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';

            $db = \Config\Database::connect();
            $builder = $db->table('user_reports ur')
                ->select('ur.*, 
                    reporter.username as reporter_name, 
                    reported.username as reported_name,
                    r.id as reason_id,
                    r.reason as report_reason')
                ->join('users reporter', 'reporter.id = ur.reporter_id')
                ->join('users reported', 'reported.id = ur.reported_user_id')
                ->join('reasons_for_report_and_block_chat r', 'r.id = ur.reason_id', 'left');

            if (!empty($search)) {
                $builder->groupStart()
                    ->like('reporter.username', $search)
                    ->orLike('reported.username', $search)
                    ->orLike('ur.additional_info', $search)
                    ->groupEnd();
            }

            $total = $builder->countAllResults(false);
            $builder->orderBy($sort, $order);
            $builder->limit($limit, $offset);
            $reports = $builder->get()->getResultArray();

            // echo "<pre>";
            // print_r($reports);
            // echo "</pre>";
            // die();
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
                        log_message('error', 'Timezone conversion error in UserReports list: ' . $e->getMessage());
                    }
                }

                $tempRow['operations'] = '<div class="btn-group">
                    <button type="button" class="btn btn-primary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                    <div class="dropdown-menu">
                        <a class="dropdown-item view-report" data-id="' . $report['id'] . '">
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
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/UserReports.php - list()');
            return $this->response->setJSON(['error' => true, 'message' => 'Something went wrong']);
        }
    }

    public function view($id)
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }

            $db = \Config\Database::connect();
            $builder = $db->table('user_reports ur')
                ->select('ur.*, 
                    reporter.username as reporter_name, 
                    reporter.email as reporter_email,
                    reported.username as reported_name,
                    reported.email as reported_email,
                    r.id as reason_id,
                    r.reason as report_reason')
                ->join('users reporter', 'reporter.id = ur.reporter_id')
                ->join('users reported', 'reported.id = ur.reported_user_id')
                ->join('reasons_for_report_and_block_chat r', 'r.id = ur.reason_id', 'left')
                ->where('ur.id', $id);

            $report = $builder->get()->getRowArray();

            if (!$report) {
                return $this->response->setJSON(['error' => true, 'message' => 'Report not found']);
            }

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
                $report['report_reason'] = $currentTranslation ?? $defaultTranslation ?? $report['report_reason'] ?? '';

                // Set translated_report_reason with current language translation if available
                $report['translated_report_reason'] = $currentTranslation;
            } else {
                $report['report_reason'] = 'No reason specified';
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
                    log_message('error', 'Timezone conversion error in UserReports view: ' . $e->getMessage());
                }
            } else {
                $report['created_at_formatted'] = '';
            }

            return $this->response->setJSON([
                'error' => false,
                'data' => $report
            ]);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/UserReports.php - view()');
            return $this->response->setJSON(['error' => true, 'message' => 'Something went wrong']);
        }
    }
}
