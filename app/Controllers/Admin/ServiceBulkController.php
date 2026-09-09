<?php

namespace App\Controllers\Admin;

use App\Models\Language_model;
use App\Models\Seo_model;
use App\Models\Service_model;
use App\Models\TranslatedServiceDetails_model;
use App\Services\ServicesService;
use App\Services\Service\ServiceBulkImportService;
use App\Services\Service\ServiceSeoService;
use App\Services\utility\FileService;
use Config\ApiResponseAndNotificationStrings;

/**
 * Handles bulk service import/export, duplicate, and download routes.
 *
 * Extracted from Admin\Services as Phase C of the Services refactor.
 * Routes continue to resolve to the same URLs — only the controller class changed.
 */
class ServiceBulkController extends Admin
{
    public $validation, $db, $ionAuth, $creator_id;
    protected $superadmin;
    protected ApiResponseAndNotificationStrings $trans;
    protected ServicesService $serviceService;
    protected Seo_model $seoModel;
    protected ServiceBulkImportService $bulkImportService;
    protected ServiceSeoService $seoService;
    protected FileService $fileService;
    protected $defaultLanguage;

    public function __construct()
    {
        parent::__construct();
        $this->validation = \Config\Services::validation();
        $this->db = \Config\Database::connect();
        $this->ionAuth = new \App\Libraries\CustomIonAuth();
        $this->creator_id = $this->userId;
        $this->superadmin = $this->session->get('email');
        $this->trans = new ApiResponseAndNotificationStrings();
        $this->serviceService = new ServicesService();
        $this->seoModel = new Seo_model();
        $this->bulkImportService = new ServiceBulkImportService();
        $this->seoService = new ServiceSeoService();
        $this->fileService = new FileService();
        $this->defaultLanguage = get_default_language();
        helper('ResponceServices');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Duplicate (clone mode view)
    // ──────────────────────────────────────────────────────────────────────────

    public function duplicate()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }

            if (!is_permitted($this->creator_id, 'create', 'services')) {
                $session = \Config\Services::session();
                if ($session) {
                    $_SESSION['toastMessage'] = labels('NO_PERMISSION_TO_TAKE_THIS_ACTION', 'Sorry! You are not permitted to take this action');
                    $_SESSION['toastMessageType'] = 'error';
                    $session->markAsFlashdata('toastMessage');
                    $session->markAsFlashdata('toastMessageType');
                }
                return redirect()->to(base_url('admin/services'));
            }

            $service_id = service('uri')->getSegments()[3];

            $this->data['title'] = labels('services', 'Services') . ' | ' . labels('admin_panel', 'Admin Panel');
            $this->data['main_page'] = 'service_form';
            $this->data['mode'] = 'clone';

            $this->data['categories_name'] = get_categories_with_translated_names();
            $this->data['tax_data'] = get_taxes_with_translated_names(['status' => 1], ['id', 'title', 'percentage']);

            $original_service = (new Service_model())->where('id', $service_id)->first();

            if (empty($original_service)) {
                return redirect()->to(base_url('admin/services'));
            }

            // Resolve file URLs via FileService
            if (!empty($original_service['image'])) {
                $original_service['image'] = $this->fileService->url($original_service['image'], 'services');
            }

            $otherImages = json_decode($original_service['other_images'] ?? '[]', true);
            $original_service['other_images'] = is_array($otherImages)
                ? array_map(fn($path) => $this->fileService->url($path, 'services'), $otherImages)
                : [];

            $files = json_decode($original_service['files'] ?? '[]', true);
            $original_service['files'] = is_array($files)
                ? array_map(fn($path) => $this->fileService->url($path, 'services'), $files)
                : [];

            $original_service['faqs'] = $this->normalizeFaqs($original_service['faqs'] ?? null);

            $currentLanguage = get_current_language();

            $partner_data = $this->db->table('users u')
                ->select('u.id, u.username, pd.company_name, pd.at_store, pd.at_doorstep, pd.need_approval_for_the_service, tpd.company_name as translated_company_name')
                ->join('partner_details pd', 'pd.partner_id = u.id')
                ->join('translated_partner_details tpd', 'tpd.partner_id = pd.partner_id AND tpd.language_code = "' . $currentLanguage . '"', 'left')
                ->where('pd.is_approved', '1')
                ->get()->getResultArray();

            foreach ($partner_data as &$partner) {
                $partner['display_company_name'] = !empty($partner['translated_company_name'])
                    ? $partner['translated_company_name']
                    : $partner['company_name'];
            }
            unset($partner);

            $this->data['partner_name'] = $partner_data;

            $this->seoModel->setTableContext('services');
            $this->data['service_seo_settings'] = $this->seoModel->getSeoSettingsByReferenceId($service_id, 'full');

            $languageModel = new Language_model();
            $languages = $languageModel->select('id, language, is_default, code')->orderBy('id', 'ASC')->findAll();
            $this->data['languages'] = $languages;

            $translatedData = $this->serviceService->getServiceWithTranslations($service_id);
            $mergedServiceDetails = $original_service;

            $seoTranslationModel = model('TranslatedServiceSeoSettings_model');
            $translatedSeoSettings = $seoTranslationModel->getAllTranslationsForService($service_id);

            foreach ($translatedSeoSettings as $translation) {
                $mergedServiceDetails['translated_seo_' . $translation['language_code']] = [
                    'seo_title' => $translation['seo_title'],
                    'seo_description' => $translation['seo_description'],
                    'seo_keywords' => $translation['seo_keywords'],
                    'seo_schema_markup' => $translation['seo_schema_markup'],
                ];
            }

            foreach ($languages as $language) {
                $languageCode = $language['code'];
                $isDefaultLanguage = $language['is_default'] == 1;
                $languageFaqs = [];

                if ($translatedData['success'] && isset($translatedData['translated_data'][$languageCode])) {
                    $translation = $translatedData['translated_data'][$languageCode];
                    $languageFaqs = $this->normalizeFaqs($translation['faqs'] ?? null);

                    $translatedTitle = !empty($translation['title']) ? $translation['title'] : ($isDefaultLanguage ? $original_service['title'] : '');
                    $translatedDescription = !empty($translation['description']) ? $translation['description'] : ($isDefaultLanguage ? $original_service['description'] : '');
                    $translatedLongDescription = !empty($translation['long_description']) ? $translation['long_description'] : ($isDefaultLanguage ? $original_service['long_description'] : '');
                    $translatedTags = !empty($translation['tags']) ? $translation['tags'] : ($isDefaultLanguage ? $original_service['tags'] : '');

                    if ($isDefaultLanguage) {
                        if (!empty($translatedTitle)) {
                            $mergedServiceDetails['title'] = $translatedTitle;
                        }
                        if (!empty($translatedDescription)) {
                            $mergedServiceDetails['description'] = $translatedDescription;
                        }
                        if (!empty($translatedLongDescription)) {
                            $mergedServiceDetails['long_description'] = $translatedLongDescription;
                        }
                        if (!empty($translatedTags)) {
                            $mergedServiceDetails['tags'] = $translatedTags;
                        }
                    } else {
                        $mergedServiceDetails['translated_' . $languageCode] = [
                            'title' => $translatedTitle,
                            'description' => $translatedDescription,
                            'long_description' => $translatedLongDescription,
                            'tags' => $translatedTags,
                            'faqs' => $languageFaqs,
                        ];
                    }
                }

                if (empty($languageFaqs) && $isDefaultLanguage && !empty($original_service['faqs'])) {
                    $languageFaqs = $original_service['faqs'];
                }

                if ($isDefaultLanguage) {
                    $mergedServiceDetails['faqs'] = $languageFaqs;
                } else {
                    $mergedServiceDetails['translated_' . $languageCode]['faqs'] = $languageFaqs;
                }
            }

            $this->data['service'] = $mergedServiceDetails;

            $this->data['currency'] = get_settings('general_settings', true)['currency'];

            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/Admin/ServiceBulkController.php - duplicate()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    /**
     * Normalize stored FAQ data into [{question, answer}] format.
     * Handles both legacy indexed pairs and keyed-object arrays.
     */
    private function normalizeFaqs(mixed $raw): array
    {
        if (empty($raw)) {
            return [];
        }

        $data = is_array($raw) ? $raw : json_decode($raw, true);

        if (!is_array($data) || empty($data)) {
            return [];
        }

        if (isset($data[0]['question'])) {
            return $data;
        }

        $faqs = [];
        foreach ($data as $pair) {
            if (is_array($pair) && count($pair) >= 2) {
                $faqs[] = ['question' => $pair[0], 'answer' => $pair[1]];
            }
        }
        return $faqs;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Bulk import view
    // ──────────────────────────────────────────────────────────────────────────

    public function bulk_import_services()
    {
        if ($this->isLoggedIn && $this->userIsAdmin) {
            $hasCreate = is_permitted($this->creator_id, 'create', 'services');
            $hasUpdate = is_permitted($this->creator_id, 'update', 'services');

            if (!$hasCreate && !$hasUpdate) {
                $session = \Config\Services::session();
                if ($session) {
                    $_SESSION['toastMessage'] = labels('NO_PERMISSION_TO_TAKE_THIS_ACTION', 'Sorry! You are not permitted to use bulk import');
                    $_SESSION['toastMessageType'] = 'error';
                    $session->markAsFlashdata('toastMessage');
                    $session->markAsFlashdata('toastMessageType');
                }
                return redirect()->to(base_url('admin/services'));
            }

            setPageInfo($this->data, labels('services', 'Services') . ' | ' . labels('admin_panel', 'Admin Panel'), 'bulk_import_services');
            $partner_data = $this->db->table('users u')
                ->select('u.id,u.username,pd.company_name,pd.number_of_members')
                ->join('partner_details pd', 'pd.partner_id = u.id')
                ->where('is_approved', '1')
                ->get()->getResultArray();
            $this->data['partner_name'] = $partner_data;
            return view('backend/admin/template', $this->data);
        } else {
            return redirect('admin/login');
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Bulk import upload (delegates to ServiceBulkImportService)
    // ──────────────────────────────────────────────────────────────────────────

    public function bulk_import_service_upload()
    {
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('admin/login');
        }

        $result = $this->bulkImportService->upload($this->request, $this->creator_id);

        if ($result['error']) {
            return ErrorResponse($result['message'], true, [], [], 200, csrf_token(), csrf_hash());
        }

        return successResponse($result['message'], false, [], [], 200, csrf_token(), csrf_hash());
    }

    // ──────────────────────────────────────────────────────────────────────────
    // CSV downloads
    // ──────────────────────────────────────────────────────────────────────────

    public function downloadSampleForInsert()
    {
        try {
            $languages = fetch_details('languages', [], ['code', 'language', 'is_default'], "", '0', 'id', 'ASC');

            $headers = [
                'Provider ID',
                'Category ID',
                'Duration to perform task',
                'Members Required to Perform Task',
                'Max Quantity allowed for services',
                'Price Type',
                'Tax ID',
                'Price',
                'Discounted Price',
                'Is Cancelable',
                'Cancelable before',
                'Pay Later Allowed',
                'At Store',
                'At Doorstep',
                'Status',
                'Approve Service',
                'Image',
            ];

            foreach ($languages as $language) {
                $langCode = $language['code'];
                $headers[] = "Title[$langCode]";
                $headers[] = "Description[$langCode]";
                $headers[] = "Long Description[$langCode]";
                $headers[] = "Tags[$langCode]";
                $headers[] = "faq[$langCode][question][1]";
                $headers[] = "faq[$langCode][answer][1]";
                $headers[] = "faq[$langCode][question][2]";
                $headers[] = "faq[$langCode][answer][2]";
            }

            $headers[] = 'Other Image[1]';
            $headers[] = 'Other Image[2]';
            $headers[] = 'Files[1]';
            $headers[] = 'Files[2]';

            foreach ($languages as $language) {
                $langCode = $language['code'];
                $headers[] = "SEO Title ($langCode)";
                $headers[] = "SEO Description ($langCode)";
                $headers[] = "SEO Keywords ($langCode)";
                $headers[] = "SEO Schema Markup ($langCode)";
            }

            $sampleRow = [
                '1',
                '2',
                '60',
                '1',
                '5',
                'included',
                '1',
                '100',
                '80',
                '1',
                '24',
                '1',
                '1',
                '1',
                '1',
                '1',
                'public/uploads/services/sample.jpg',
            ];

            foreach ($languages as $language) {
                $langCode = $language['code'];
                if ($langCode === 'en') {
                    $sampleRow[] = 'House Cleaning Service';
                    $sampleRow[] = 'Professional house cleaning service';
                    $sampleRow[] = 'We provide thorough cleaning of your home including all rooms, kitchen, and bathrooms';
                    $sampleRow[] = 'cleaning,house,professional';
                    $sampleRow[] = 'What areas do you clean?';
                    $sampleRow[] = 'We clean all rooms, kitchen, bathrooms, and common areas';
                    $sampleRow[] = 'How long does it take?';
                    $sampleRow[] = 'Typically 2-3 hours depending on home size';
                } else {
                    $sampleRow[] = 'Other language title';
                    $sampleRow[] = 'Other language description';
                    $sampleRow[] = 'Other language long description';
                    $sampleRow[] = 'Other language tags';
                    $sampleRow[] = 'Other language faq[question][1]';
                    $sampleRow[] = 'Other language faq[answer][1]';
                    $sampleRow[] = 'Other language faq[question][2]';
                    $sampleRow[] = 'Other language faq[answer][2]';
                }
            }

            $sampleRow[] = 'public/uploads/services/image1.jpg';
            $sampleRow[] = 'public/uploads/services/image2.jpg';
            $sampleRow[] = 'public/uploads/services/document1.pdf';
            $sampleRow[] = 'public/uploads/services/document2.pdf';

            foreach ($languages as $language) {
                $langCode = $language['code'];
                $sampleRow[] = 'Sample SEO Title (' . $langCode . ')';
                $sampleRow[] = 'Sample SEO Description (' . $langCode . ')';
                $sampleRow[] = 'keyword1, keyword2, keyword3 (' . $langCode . ')';
                $sampleRow[] = '{"@type":"Service"} (' . $langCode . ')';
            }

            $output = fopen('php://output', 'w');
            if ($output === false) {
                throw new \Exception('Failed to open output stream.');
            }
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="service_sample_without_data_multilanguage.csv"');
            fputcsv($output, $headers);
            fputcsv($output, $sampleRow);
            fclose($output);
            exit;
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/Admin/ServiceBulkController.php - downloadSampleForInsert()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function downloadSampleForUpdate()
    {
        try {
            $languageModel = new Language_model();
            $languages = $languageModel->findAll();

            $headers = [
                'ID',
                'Provider ID',
                'Category ID',
                'Duration to perform task',
                'Members Required to Perform Task',
                'Max Quantity allowed for services',
                'Price Type',
                'Tax ID',
                'Price',
                'Discounted Price',
                'Is Cancelable',
                'Cancelable before',
                'Pay Later Allowed',
                'At Store',
                'At Doorstep',
                'Status',
                'Approve Service',
                'Image',
            ];

            foreach ($languages as $language) {
                $langCode = $language['code'];
                $headers[] = "Title[$langCode]";
                $headers[] = "Description[$langCode]";
                $headers[] = "Long Description[$langCode]";
                $headers[] = "Tags[$langCode]";
                $headers[] = "faq[$langCode][question][1]";
                $headers[] = "faq[$langCode][answer][1]";
                $headers[] = "faq[$langCode][question][2]";
                $headers[] = "faq[$langCode][answer][2]";
            }

            $headers[] = 'Other Image[1]';
            $headers[] = 'Other Image[2]';
            $headers[] = 'Files[1]';
            $headers[] = 'Files[2]';

            foreach ($languages as $language) {
                $langCode = $language['code'];
                $headers[] = "SEO Title ($langCode)";
                $headers[] = "SEO Description ($langCode)";
                $headers[] = "SEO Keywords ($langCode)";
                $headers[] = "SEO Schema Markup ($langCode)";
            }

            $partners = $this->request->getPost('partners');
            if (is_string($partners)) {
                $partners = array_filter(array_map('trim', explode(',', $partners)), static function ($value) {
                    return $value !== '';
                });
            } elseif (!empty($partners) && !is_array($partners)) {
                $partners = [$partners];
            }

            if (!empty($partners)) {
                $services = fetch_details('services', [], [], "", 0, 'id', 'DESC', 'user_id', $partners);
            } else {
                $services = fetch_details('services');
            }

            $all_data = [];
            $translationModel = new TranslatedServiceDetails_model();

            foreach ($services as $service) {
                $row = [
                    strval($service['id'] ?? ''),
                    strval($service['user_id'] ?? ''),
                    strval($service['category_id'] ?? ''),
                    strval($service['duration'] ?? ''),
                    strval($service['number_of_members_required'] ?? ''),
                    strval($service['max_quantity_allowed'] ?? ''),
                    strval($service['tax_type'] ?? ''),
                    strval($service['tax_id'] ?? ''),
                    strval($service['price'] ?? ''),
                    strval($service['discounted_price'] ?? ''),
                    strval($service['is_cancelable'] ?? ''),
                    strval($service['cancelable_till'] ?? ''),
                    strval($service['is_pay_later_allowed'] ?? ''),
                    strval($service['at_store'] ?? ''),
                    strval($service['at_doorstep'] ?? ''),
                    strval($service['status'] ?? ''),
                    strval($service['approved_by_admin'] ?? ''),
                    strval($service['image'] ?? ''),
                ];

                $translations = $translationModel->where('service_id', $service['id'])->findAll();

                $translationsByLang = [];
                foreach ($translations as $trans) {
                    $translationsByLang[$trans['language_code']] = $trans;
                }

                $defaultLangCode = null;
                foreach ($languages as $lang) {
                    if (isset($lang['is_default']) && $lang['is_default'] == 1) {
                        $defaultLangCode = $lang['code'];
                        break;
                    }
                }

                foreach ($languages as $language) {
                    $langCode = $language['code'];
                    $isDefault = isset($language['is_default']) && $language['is_default'] == 1;

                    if (isset($translationsByLang[$langCode])) {
                        $trans = $translationsByLang[$langCode];

                        $row[] = strval($trans['title'] ?? '');
                        $row[] = strval($trans['description'] ?? '');
                        $row[] = strval(strip_tags(htmlspecialchars_decode(stripslashes($trans['long_description'] ?? ''))));
                        $row[] = strval($trans['tags'] ?? '');

                        $faqs = @json_decode($trans['faqs'] ?? '[]', true);
                        if (!is_array($faqs)) {
                            $faqs = [];
                        }

                        $row[] = isset($faqs[0]) && is_array($faqs[0]) ? strval($faqs[0]['question'] ?? '') : '';
                        $row[] = isset($faqs[0]) && is_array($faqs[0]) ? strval($faqs[0]['answer'] ?? '') : '';
                        $row[] = isset($faqs[1]) && is_array($faqs[1]) ? strval($faqs[1]['question'] ?? '') : '';
                        $row[] = isset($faqs[1]) && is_array($faqs[1]) ? strval($faqs[1]['answer'] ?? '') : '';
                    } else {
                        if ($isDefault) {
                            $fallbackTrans = ($defaultLangCode && isset($translationsByLang[$defaultLangCode]))
                                ? $translationsByLang[$defaultLangCode]
                                : null;

                            $title = $fallbackTrans['title'] ?? $service['title'] ?? '';
                            $description = $fallbackTrans['description'] ?? $service['description'] ?? '';
                            $longDescription = $fallbackTrans['long_description'] ?? $service['long_description'] ?? '';
                            $tags = $fallbackTrans['tags'] ?? $service['tags'] ?? '';
                            $faqsJson = $fallbackTrans['faqs'] ?? $service['faqs'] ?? '[]';
                        } else {
                            $title = '';
                            $description = '';
                            $longDescription = '';
                            $tags = '';
                            $faqsJson = '[]';
                        }

                        $row[] = strval($title);
                        $row[] = strval($description);
                        $row[] = strval(strip_tags(htmlspecialchars_decode(stripslashes($longDescription))));
                        $row[] = strval($tags);

                        $faqs = @json_decode($faqsJson, true);
                        if (!is_array($faqs))
                            $faqs = [];

                        $row[] = isset($faqs[0]['question']) ? strval($faqs[0]['question']) : '';
                        $row[] = isset($faqs[0]['answer']) ? strval($faqs[0]['answer']) : '';
                        $row[] = isset($faqs[1]['question']) ? strval($faqs[1]['question']) : '';
                        $row[] = isset($faqs[1]['answer']) ? strval($faqs[1]['answer']) : '';
                    }
                }

                $otherImages = @json_decode($service['other_images'] ?? '[]', true);
                if (!is_array($otherImages))
                    $otherImages = [];
                $row[] = isset($otherImages[0]) && is_string($otherImages[0]) ? $otherImages[0] : '';
                $row[] = isset($otherImages[1]) && is_string($otherImages[1]) ? $otherImages[1] : '';

                $files = @json_decode($service['files'] ?? '[]', true);
                if (!is_array($files))
                    $files = [];
                $row[] = isset($files[0]) && is_string($files[0]) ? $files[0] : '';
                $row[] = isset($files[1]) && is_string($files[1]) ? $files[1] : '';

                $this->seoModel->setTableContext('services');
                $baseSeoSettings = $this->seoModel->getSeoSettingsByReferenceId($service['id']);

                $seoTranslationModel = model('TranslatedServiceSeoSettings_model');
                $seoTranslations = $seoTranslationModel->where('service_id', $service['id'])->findAll();
                $seoTranslationsByLang = [];
                foreach ($seoTranslations as $seoTrans) {
                    $seoTranslationsByLang[$seoTrans['language_code']] = $seoTrans;
                }

                foreach ($languages as $language) {
                    $langCode = $language['code'];
                    $isDefault = $language['is_default'] == 1;
                    $seoTranslation = $seoTranslationsByLang[$langCode] ?? null;

                    $row[] = strval($seoTranslation['seo_title'] ?? ($isDefault ? ($baseSeoSettings['title'] ?? '') : ''));
                    $row[] = strval($seoTranslation['seo_description'] ?? ($isDefault ? ($baseSeoSettings['description'] ?? '') : ''));
                    $row[] = strval($seoTranslation['seo_keywords'] ?? ($isDefault ? ($baseSeoSettings['keywords'] ?? '') : ''));
                    $row[] = strval($seoTranslation['seo_schema_markup'] ?? ($isDefault ? ($baseSeoSettings['schema_markup'] ?? '') : ''));
                }

                foreach ($row as $index => $value) {
                    if (gettype($value) !== 'string') {
                        log_message('error', 'NON-STRING VALUE at index ' . $index . ' - Type: ' . gettype($value) . ', Value: ' . print_r($value, true));
                    }
                }

                $all_data[] = $row;
            }

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="service_update_sample_with_data_multilanguage.csv"');

            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);
            foreach ($all_data as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
            exit;
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/Admin/ServiceBulkController.php - downloadSampleForUpdate()');
            header('Content-Type: application/json');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // PDF instruction downloads
    // ──────────────────────────────────────────────────────────────────────────

    public function ServiceAddInstructions()
    {
        try {
            $filePath = FCPATH . '/public/uploads/site/Service-Add-Instructions.pdf';
            $fileName = 'Service-Add-Instructions.pdf';
            if (file_exists($filePath)) {
                return $this->response->download($filePath, null)->setFileName($fileName);
            } else {
                $_SESSION['toastMessage'] = labels('cant_download', 'Cannot download');
                $_SESSION['toastMessageType'] = 'error';
                $this->session->markAsFlashdata('toastMessage');
                $this->session->markAsFlashdata('toastMessageType');
                return redirect()->to('admin/services/bulk_import_services')->withCookies();
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/Admin/ServiceBulkController.php - ServiceAddInstructions()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function ServiceUpdateInstructions()
    {
        try {
            $filePath = FCPATH . '/public/uploads/site/Service-Update-Instructions.pdf';
            $fileName = 'Service-Update-Instructions.pdf';
            if (file_exists($filePath)) {
                return $this->response->download($filePath, null)->setFileName($fileName);
            } else {
                $_SESSION['toastMessage'] = labels('cant_download', 'Cannot download');
                $_SESSION['toastMessageType'] = 'error';
                $this->session->markAsFlashdata('toastMessage');
                $this->session->markAsFlashdata('toastMessageType');
                return redirect()->to('admin/services')->withCookies();
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/Admin/ServiceBulkController.php - ServiceUpdateInstructions()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
}
