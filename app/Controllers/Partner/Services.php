<?php

namespace App\Controllers\Partner;

use App\Models\Service_model;
use App\Models\Seo_model;
use App\Services\ServicesService;
use App\Services\Service\ServiceCreateService;
use App\Services\Service\ServiceUpdateService;
use App\Services\Service\ServiceSeoService;
use App\Services\utility\FileService;

class Services extends Partner
{
    public $service, $validations, $db, $seoModel;
    protected ServicesService $serviceService;
    protected ServiceCreateService $createService;
    protected ServiceUpdateService $updateService;
    protected ServiceSeoService $seoService;
    protected FileService $fileService;

    public function __construct()
    {
        parent::__construct();
        $this->service = new Service_model();
        $this->seoModel = new Seo_model();
        $this->validation = \Config\Services::validation();
        $this->db = \Config\Database::connect();
        $this->serviceService = new ServicesService();
        $this->createService = new ServiceCreateService();
        $this->updateService = new ServiceUpdateService();
        $this->seoService = new ServiceSeoService();
        $this->fileService = new FileService();
        helper('ResponceServices');
    }

    public function index()
    {
        if ($this->isLoggedIn) {
            if (!exists(['partner_id' => $this->userId, 'is_approved' => 1], 'partner_details')) {
                return redirect('partner/profile');
            }
            $tax_details = fetch_details('taxes', ['status' => 1]);
            setPageInfo($this->data, labels('services', 'Services') . ' | ' . labels('provider_panel', 'Provider Panel'), 'services');
            $this->data['tax_details'] = $tax_details;
            $this->data['tax'] = get_settings('system_tax_settings', true);
            // $this->data['categories'] = fetch_details('categories', []);
            $this->data['categories'] = get_categories_with_translated_names();

            // get currency symbole
            $this->data['currency'] = get_settings('general_settings', true)['currency'];

            // Fetch taxes with translated names based on current language
            $tax_data = get_taxes_with_translated_names(['status' => 1], ['id', 'title', 'percentage']);
            $this->data['tax_data'] = $tax_data;
            return view('backend/partner/template', $this->data);
        } else {
            return redirect('partner/login');
        }
    }

    public function view_service()
    {
        if (!$this->isLoggedIn) {
            return redirect('partner/login');
        }

        $serviceId = (int) service('uri')->getSegments()[3];
        $service = fetch_details('services', ['id' => $serviceId, 'user_id' => $this->userId])[0] ?? null;
        if (empty($service)) {
            return redirect('partner/services');
        }

        $service['image'] = $this->fileService->exists('services', $service['image'] ?? null)
            ? $this->fileService->url($service['image'], 'services')
            : '';
        $service['category_name'] = get_categories_with_translated_names(
            whereConditions: ['id' => (int) ($service['category_id'] ?? 0)]
        )[0]['name'] ?? '';

        $faqs = json_decode((string) ($service['faqs'] ?? ''), true);
        $service['faqs'] = is_array($faqs) ? $faqs : [];

        $this->data['service'] = $service;
        setPageInfo($this->data, labels('view_service', 'View Service') . ' | ' . labels('provider_panel', 'Provider Panel'), 'service_details');
        return view('backend/partner/template', $this->data);
    }

    public function add()
    {
        if ($this->isLoggedIn) {
            if (!exists(['partner_id' => $this->userId, 'is_approved' => 1], 'partner_details')) {
                return redirect('partner/profile');
            }
            $this->data['mode'] = 'add';
            setPageInfo($this->data, labels('services', 'Services') . ' | ' . labels('provider_panel', 'Provider Panel'), FORMS . 'service_form');
            // $this->data['categories'] = fetch_details('categories', []);
            $this->data['categories'] = get_categories_with_translated_names();
            $this->data['tax'] = get_settings('system_tax_settings', true);
            $tax_details = fetch_details('taxes', ['status' => 1]);
            $this->data['tax_details'] = $tax_details;
            // Fetch taxes with translated names based on current language
            $tax_data = get_taxes_with_translated_names(['status' => 1], ['id', 'title', 'percentage']);
            $this->data['tax_data'] = $tax_data;

            // fetch languages
            $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ACE');
            $this->data['languages'] = $languages;

            $this->data['currency'] = get_settings('general_settings', true)['currency'];

            return view('backend/partner/template', $this->data);
        } else {
            return redirect('partner/login');
        }
    }

    public function add_service()
    {
        try {
            if (!$this->isLoggedIn) {
                return redirect('partner/login');
            }
            if (!isset($_POST) || empty($_POST)) {
                return redirect()->to('partner/services');
            }

            $partner_data = fetch_details('partner_details', ['partner_id' => $this->userId]);
            if (empty($partner_data)) {
                return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
            }
            $approvedByAdmin = ($partner_data[0]['need_approval_for_the_service'] == 1) ? 0 : 1;

            $result = $this->createService->create($this->request, $this->userId, $this->userId, $approvedByAdmin);

            if ($result['error']) {
                return ErrorResponse($result['message'], true, [], [], 200, csrf_token(), csrf_hash());
            }

            $serviceId = $result['service_id'] ?? 0;
            $serviceData = $serviceId ? fetch_details('services', ['id' => $serviceId]) : [];
            $categoryData = !empty($serviceData[0]['category_id'])
                ? fetch_details('categories', ['id' => $serviceData[0]['category_id']], ['id', 'name'])
                : [];

            $eventData = [
                'clarity_event' => 'service_created',
                'service_id' => $serviceId,
                'service_name' => $serviceData[0]['title'] ?? '',
                'service_price' => $serviceData[0]['price'] ?? '',
                'category_id' => $serviceData[0]['category_id'] ?? '',
                'category_name' => !empty($categoryData) ? ($categoryData[0]['name'] ?? '') : '',
            ];

            return successResponse(labels(SERVICE_SAVED_SUCCESSFULLY, 'Service saved successfully'), false, $eventData, ['redirect_url' => base_url('partner/services')], 200, csrf_token(), csrf_hash());
        } catch (\Throwable $th) {
            log_message('error', 'Exception in Services::add_service() --> ' . $th->getMessage() . ' --> ' . $th->getTraceAsString());
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function list()
    {
        $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
        $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
        $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
        $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
        // Normalize search term: trim and replace multiple spaces with single space
        // This fixes issues where search doesn't work with multiple spaces in long names
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search = trim($_GET['search']);
            // Replace multiple consecutive spaces with a single space
            $search = preg_replace('/\s+/', ' ', $search);
        } else {
            $search = '';
        }
        $service_model = new Service_model();
        $where['s.user_id'] = $_SESSION['user_id'];
        $services = $service_model->list(false, $search, $limit, $offset, $sort, $order, $where);
        return $services;
    }
    public function update_service()
    {
        try {
            if (!$this->isLoggedIn) {
                return redirect('partner/login');
            }
            if (!$_POST || empty($_POST)) {
                return redirect()->to('partner/services');
            }

            $user_id = $this->ionAuth->user()->row()->id;
            $partner_details = fetch_details('partner_details', ['partner_id' => $user_id]);
            if (empty($partner_details)) {
                return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
            }
            $approvedByAdmin = ($partner_details[0]['need_approval_for_the_service'] == 1) ? 0 : 1;

            $result = $this->updateService->update($this->request, $this->userId, $user_id, $approvedByAdmin);

            if ($result['error']) {
                return ErrorResponse($result['message'], true, [], [], 200, csrf_token(), csrf_hash());
            }

            $id = $this->request->getPost('service_id');
            $category = $this->request->getPost('categories');

            try {
                $providerName = get_translated_partner_field($user_id, 'user_name');
                if (empty($providerName)) {
                    $providerData = fetch_details('users', ['id' => $user_id], ['username']);
                    $providerName = !empty($providerData) ? $providerData[0]['username'] : 'Provider';
                }
                $categoryData = fetch_details('categories', ['id' => $category], ['name']);
                $categoryName = !empty($categoryData) ? $categoryData[0]['name'] : 'Category';
                $currency = get_settings('general_settings', true)['currency'] ?? 'USD';
                $serviceInfo = fetch_details('services', ['id' => $id], ['title', 'price', 'discounted_price', 'description']);
                queue_notification_service(
                    eventType: 'provider_edits_service_details',
                    recipients: [],
                    context: [
                        'provider_name' => $providerName,
                        'provider_id' => $user_id,
                        'service_id' => $id,
                        'service_title' => $serviceInfo[0]['title'] ?? '',
                        'service_description' => $serviceInfo[0]['description'] ?? '',
                        'category_name' => $categoryName,
                        'category_id' => $category,
                        'service_price' => number_format($serviceInfo[0]['price'] ?? 0, 2),
                        'service_discounted_price' => number_format($serviceInfo[0]['discounted_price'] ?? 0, 2),
                        'currency' => $currency,
                    ],
                    options: ['user_groups' => [1], 'channels' => ['fcm', 'email', 'sms']]
                );
            } catch (\Throwable $notificationError) {
                log_message('error', '[PROVIDER_EDITS_SERVICE_DETAILS] Notification error trace: ' . $notificationError->getTraceAsString());
            }

            $serviceData = fetch_details('services', ['id' => $id]);
            $eventData = [
                'clarity_event' => 'service_updated',
                'service_id' => $id,
                'service_name' => $serviceData[0]['title'] ?? '',
            ];

            return successResponse(labels(DATA_SAVED_SUCCESSFULLY, 'Data saved successfully'), false, $eventData, ['redirect_url' => base_url('partner/services')], 200, csrf_token(), csrf_hash());
        } catch (\Throwable $th) {
            log_message('error', 'Exception in Services::update_service() --> ' . $th->getMessage() . ' --> ' . $th->getTraceAsString());
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function delete()
    {
        try {
            if ($this->isLoggedIn) {
                $id = $this->request->getPost('id');
                $db = \Config\Database::connect();
                $old_data = fetch_details('services', ['id' => $id]);
                if (!empty($old_data) && isset($old_data[0]['user_id']) && (int) $old_data[0]['user_id'] === 50) {
                    return ErrorResponse("Service cannot be deleted for this provider", true, [], [], 200, csrf_token(), csrf_hash());
                }
                if ((defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) && !empty($old_data[0]['image'])) {
                    $this->fileService->delete('services', $old_data[0]['image']);
                }
                if (!empty($old_data[0]['other_images'])) {
                    $other_images = json_decode($old_data[0]['other_images'], true);
                    foreach ($other_images as $oi) {
                        $this->fileService->delete('services', $oi);
                    }
                }
                if (!empty($old_data[0]['files'])) {
                    $files = json_decode($old_data[0]['files'], true);
                    foreach ($files as $oi) {
                        $this->fileService->delete('services', $oi);
                    }
                }

                // Clean up SEO settings and images before deleting service
                $this->seoModel->cleanupSeoData($id, 'services');

                $db->transStart();
                $db->table('services')->delete(['id' => $id]);
                $db->table('cart')->delete(['service_id' => $id]);
                $db->transComplete();
                if ($db->transStatus() !== false) {
                    // Get service details for event tracking before deletion
                    $serviceData = $old_data[0] ?? [];
                    $eventData = [
                        'clarity_event' => 'service_deleted',
                        'service_id' => $id
                    ];

                    return successResponse(labels(SERVICE_DELETED_SUCCESSFULLY, "Service deleted successfully"), false, $eventData, [], 200, csrf_token(), csrf_hash());
                } else {
                    return ErrorResponse(labels(SERVICE_CANNOT_BE_DELETED, "Service can not be deleted!"), true, [], [], 200, csrf_token(), csrf_hash());
                }
            } else {
                return redirect('partner/login');
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Services.php - delete()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function edit_service()
    {
        try {
            helper('function');
            $uri = service('uri');
            if ($this->isLoggedIn) {
                if (!exists(['partner_id' => $this->userId, 'is_approved' => 1], 'partner_details')) {
                    return redirect('partner/profile');
                }
                $service_id = $uri->getSegments()[3];
                $this->data['mode'] = 'edit';
                setPageInfo($this->data, labels('edit_service', 'Edit Service') . ' | ' . labels('provider_panel', 'Provider Panel'), FORMS . 'service_form');
                // $this->data['categories'] = fetch_details('categories', []);
                $this->data['categories'] = get_categories_with_translated_names();
                $this->data['tax'] = get_settings('system_tax_settings', true);
                $tax_details = fetch_details('taxes', ['status' => 1]);
                $this->data['tax_details'] = $tax_details;
                // Fetch taxes with translated names based on current language
                $tax_data = get_taxes_with_translated_names(['status' => 1], ['id', 'title', 'percentage']);
                $service = fetch_details('services', ['id' => $service_id])[0];

                // Normalize FAQ payloads exactly like fetch_cart() does so both places
                // accept the older numeric format and the newer keyed format safely.
                $normalizeFaqData = static function ($rawFaqs) {
                    if (empty($rawFaqs)) {
                        return [];
                    }

                    if (is_string($rawFaqs)) {
                        $decoded = json_decode($rawFaqs, true);
                        $rawFaqs = is_array($decoded) ? $decoded : [];
                    }

                    if (!is_array($rawFaqs)) {
                        return [];
                    }

                    $normalized = [];

                    foreach ($rawFaqs as $faq) {
                        if (!is_array($faq) || empty($faq)) {
                            continue;
                        }

                        if (isset($faq['question'], $faq['answer'])) {
                            $question = trim((string) $faq['question']);
                            $answer = trim((string) $faq['answer']);
                        } elseif (isset($faq[0], $faq[1])) {
                            $question = trim((string) $faq[0]);
                            $answer = trim((string) $faq[1]);
                        } else {
                            continue;
                        }

                        if ($question === '' || $answer === '') {
                            continue;
                        }

                        $normalized[] = [
                            'question' => $question,
                            'answer' => $answer,
                        ];
                    }

                    return $normalized;
                };

                // Check if service belongs to the logged-in partner
                if ($service['user_id'] != $this->userId) {
                    return redirect('partner/services')->with('error', 'Access denied');
                }

                $service['image'] = $this->fileService->exists('services', $service['image'] ?? null)
                    ? $this->fileService->url($service['image'], 'services')
                    : '';

                $decodedOtherImages = !empty($service['other_images']) ? json_decode($service['other_images'], true) : null;
                $service['other_images'] = is_array($decodedOtherImages)
                    ? array_map(fn($p) => $this->fileService->url($p, 'services'), $decodedOtherImages)
                    : [];

                $decodedFiles = !empty($service['files']) ? json_decode($service['files'], true) : null;
                $service['files'] = is_array($decodedFiles)
                    ? array_map(fn($p) => $this->fileService->url($p, 'services'), $decodedFiles)
                    : [];

                // Process FAQs data - decode JSON string to array for proper handling in view
                // Keep the default language FAQs consistent regardless of the legacy format stored.
                $service['faqs'] = $normalizeFaqData(isset($service['faqs']) ? $service['faqs'] : []);

                $this->data['service'] = $service;

                $this->data['tax_data'] = $tax_data;
                $this->data['main_page'] = FORMS . 'service_form';

                // Prepare event data for service_viewed tracking
                $categoryData = [];
                if (!empty($service['category_id'])) {
                    $categoryData = fetch_details('categories', ['id' => $service['category_id']], ['id', 'name']);
                }
                $this->data['clarity_event_data'] = [
                    'clarity_event' => 'service_viewed',
                    'service_id' => $service_id,
                    'service_name' => $service['title'] ?? '',
                    'service_price' => $service['price'] ?? '',
                    'category_id' => $service['category_id'] ?? '',
                    'category_name' => !empty($categoryData) ? $categoryData[0]['name'] ?? '' : ''
                ];


                $this->seoModel->setTableContext('services');
                $seo_settings = $this->seoModel->getSeoSettingsByReferenceId($service_id, 'full');
                $this->data['service_seo_settings'] = $seo_settings;

                // fetch languages
                $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ACE');
                $this->data['languages'] = $languages;

                // Load translated service details using ServiceService
                $translatedData = $this->serviceService->getServiceWithTranslations($service_id);

                // Process FAQ data with proper fallback logic
                // For each language, try to get FAQs from translations table first, then fall back to main table
                $mergedServiceDetails = $service;

                // Load SEO translations for each language (must be done AFTER $mergedServiceDetails is initialized)
                $seoTranslationModel = model('TranslatedServiceSeoSettings_model');
                $seoTranslations = $seoTranslationModel->getAllTranslationsForService($service_id);

                // Attach SEO translations to service data by language code
                if (!empty($seoTranslations)) {
                    foreach ($seoTranslations as $translation) {
                        $languageCode = $translation['language_code'];
                        $mergedServiceDetails['translated_seo_' . $languageCode] = [
                            'seo_title' => $translation['seo_title'] ?? '',
                            'seo_description' => $translation['seo_description'] ?? '',
                            'seo_keywords' => $translation['seo_keywords'] ?? '',
                            'seo_schema_markup' => $translation['seo_schema_markup'] ?? ''
                        ];
                    }
                }

                foreach ($languages as $language) {
                    $languageCode = $language['code'];
                    $isDefaultLanguage = $language['is_default'] == 1;

                    // Initialize FAQ data for this language
                    $languageFaqs = [];

                    // First, try to get FAQs from translations table
                    if ($translatedData['success'] && isset($translatedData['translated_data'][$languageCode])) {
                        $translation = $translatedData['translated_data'][$languageCode];

                        // Normalize translated FAQs as well so the edit form never receives mixed structures.
                        $languageFaqs = $normalizeFaqData(isset($translation['faqs']) ? $translation['faqs'] : []);
                    }

                    // If no FAQs found in translations table, fall back to main table (for default language)
                    if (empty($languageFaqs) && $isDefaultLanguage) {
                        if (isset($service['faqs']) && is_array($service['faqs']) && !empty($service['faqs'])) {
                            $languageFaqs = $service['faqs'];
                        }
                    }

                    // Set FAQs for this language
                    if ($isDefaultLanguage) {
                        // For default language, set directly in main service data
                        $mergedServiceDetails['faqs'] = $languageFaqs;
                    } else {
                        // For other languages, set in translated data
                        if (!isset($mergedServiceDetails['translated_' . $languageCode])) {
                            $mergedServiceDetails['translated_' . $languageCode] = [];
                        }
                        $mergedServiceDetails['translated_' . $languageCode]['faqs'] = $languageFaqs;
                    }

                    // Set other translated fields with proper fallback logic
                    if ($translatedData['success'] && isset($translatedData['translated_data'][$languageCode])) {
                        $translation = $translatedData['translated_data'][$languageCode];

                        // Process other translatable fields with fallback logic
                        $translatedTitle = !empty($translation['title']) ? $translation['title'] : ($isDefaultLanguage ? $service['title'] : '');
                        $translatedDescription = !empty($translation['description']) ? $translation['description'] : ($isDefaultLanguage ? $service['description'] : '');
                        $translatedLongDescription = !empty($translation['long_description']) ? $translation['long_description'] : ($isDefaultLanguage ? $service['long_description'] : '');
                        $translatedTags = !empty($translation['tags']) ? $translation['tags'] : ($isDefaultLanguage ? $service['tags'] : '');

                        if (!$isDefaultLanguage) {
                            $mergedServiceDetails['translated_' . $languageCode] = [
                                'title' => $translatedTitle,
                                'description' => $translatedDescription,
                                'long_description' => $translatedLongDescription,
                                'tags' => $translatedTags,
                                'faqs' => $languageFaqs
                            ];
                        } else {
                            // For default language, update main service data with translated data if available
                            if (!empty($translation['title'])) {
                                $mergedServiceDetails['title'] = $translation['title'];
                            }
                            if (!empty($translation['description'])) {
                                $mergedServiceDetails['description'] = $translation['description'];
                            }
                            if (!empty($translation['long_description'])) {
                                $mergedServiceDetails['long_description'] = $translation['long_description'];
                            }
                            if (!empty($translation['tags'])) {
                                $mergedServiceDetails['tags'] = $translation['tags'];
                            }
                        }
                    }
                }

                $this->data['service'] = $mergedServiceDetails;
                $this->data['currency'] = get_settings('general_settings', true)['currency'];

                return view('backend/partner/template', $this->data);
            } else {
                return redirect('partner/login');
            }
        } catch (\Throwable $th) {
            throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Services.php - edit_service()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function duplicate()
    {
        try {
            helper('function');
            $uri = service('uri');
            if ($this->isLoggedIn) {
                if (!exists(['partner_id' => $this->userId, 'is_approved' => 1], 'partner_details')) {
                    return redirect('partner/profile');
                }
                $service_id = $uri->getSegments()[3];
                $this->data['mode'] = 'clone';
                setPageInfo($this->data, labels('duplicate_service', 'Duplicate Service') . ' | ' . labels('provider_panel', 'Provider Panel'), FORMS . 'service_form');
                // $this->data['categories'] = fetch_details('categories', []);
                $this->data['categories'] = get_categories_with_translated_names();
                $this->data['tax'] = get_settings('system_tax_settings', true);
                $tax_details = fetch_details('taxes', ['status' => 1]);
                $this->data['tax_details'] = $tax_details;
                // Fetch taxes with translated names based on current language
                $tax_data = get_taxes_with_translated_names(['status' => 1], ['id', 'title', 'percentage']);

                // Fetch the original service data
                $serviceData = fetch_details('services', ['id' => $service_id]);

                // Check if service exists
                if (empty($serviceData)) {
                    return redirect('partner/services')->with('error', 'Service not found');
                }

                $service = $serviceData[0];

                // Check if service belongs to the logged-in partner
                if ($service['user_id'] != $this->userId) {
                    return redirect('partner/services');
                }

                if (!empty($service['image'])) {
                    $service['image'] = $this->fileService->url($service['image'], 'services');
                }

                $decodedOtherImages = !empty($service['other_images']) ? json_decode($service['other_images'], true) : null;
                $service['other_images'] = is_array($decodedOtherImages)
                    ? array_map(fn($p) => $this->fileService->url($p, 'services'), $decodedOtherImages)
                    : [];

                $decodedFiles = !empty($service['files']) ? json_decode($service['files'], true) : null;
                $service['files'] = is_array($decodedFiles)
                    ? array_map(fn($p) => $this->fileService->url($p, 'services'), $decodedFiles)
                    : [];

                // Process FAQs data with enhanced handling for multiple formats
                if (!empty($service['faqs'])) {
                    $faqsData = json_decode($service['faqs'], true);
                    if (is_array($faqsData)) {
                        $faqs = [];
                        // Handle both old array format [["question","answer"]] and new object format [{"question":"q","answer":"a"}]
                        if (isset($faqsData[0])) {
                            if (is_array($faqsData[0]) && count($faqsData[0]) >= 2 && !isset($faqsData[0]['question'])) {
                                // Old array format - direct array of pairs [["question","answer"]]
                                foreach ($faqsData as $pair) {
                                    if (is_array($pair) && count($pair) >= 2) {
                                        $faq = [
                                            'question' => $pair[0],
                                            'answer' => $pair[1]
                                        ];
                                        $faqs[] = $faq;
                                    }
                                }
                            } elseif (is_array($faqsData[0]) && isset($faqsData[0]['question']) && isset($faqsData[0]['answer'])) {
                                // New object format - array of objects [{"question":"q","answer":"a"}]
                                $faqs = $faqsData;
                            } else {
                                // Object format - object with numeric keys {"1":["question","answer"]}
                                foreach ($faqsData as $key => $pair) {
                                    if (is_array($pair) && count($pair) >= 2) {
                                        $faq = [
                                            'question' => $pair[0],
                                            'answer' => $pair[1]
                                        ];
                                        $faqs[] = $faq;
                                    }
                                }
                            }
                        }
                        $service['faqs'] = $faqs;
                    } else {
                        $service['faqs'] = [];
                    }
                } else {
                    $service['faqs'] = [];
                }

                $this->data['service'] = $service;
                $this->data['tax_data'] = $tax_data;

                // Fetch SEO settings for the service
                $this->seoModel->setTableContext('services');
                $seo_settings = $this->seoModel->getSeoSettingsByReferenceId($service_id, 'full');
                $this->data['service_seo_settings'] = $seo_settings;

                // Fetch languages for translation support
                $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ACE');
                $this->data['languages'] = $languages;

                // Load translated service details using ServiceService
                $translatedData = $this->serviceService->getServiceWithTranslations($service_id);

                // Process FAQ data with proper fallback logic
                // For each language, try to get FAQs from translations table first, then fall back to main table
                $mergedServiceDetails = $service;

                // Load SEO translations for each language (must be done AFTER $mergedServiceDetails is initialized)
                $seoTranslationModel = model('TranslatedServiceSeoSettings_model');
                $seoTranslations = $seoTranslationModel->getAllTranslationsForService($service_id);

                // Attach SEO translations to service data by language code
                if (!empty($seoTranslations)) {
                    foreach ($seoTranslations as $translation) {
                        $languageCode = $translation['language_code'];
                        $mergedServiceDetails['translated_seo_' . $languageCode] = [
                            'seo_title' => $translation['seo_title'] ?? '',
                            'seo_description' => $translation['seo_description'] ?? '',
                            'seo_keywords' => $translation['seo_keywords'] ?? '',
                            'seo_schema_markup' => $translation['seo_schema_markup'] ?? ''
                        ];
                    }
                }

                foreach ($languages as $language) {
                    $languageCode = $language['code'];
                    $isDefaultLanguage = $language['is_default'] == 1;

                    // Initialize FAQ data for this language
                    $languageFaqs = [];

                    // First, try to get FAQs from translations table
                    if ($translatedData['success'] && isset($translatedData['translated_data'][$languageCode])) {
                        $translation = $translatedData['translated_data'][$languageCode];

                        if (!empty($translation['faqs'])) {
                            // Process translated FAQs data - decode JSON string to array if needed
                            if (is_array($translation['faqs'])) {
                                $languageFaqs = $translation['faqs'];
                            } else {
                                $translatedFaqsData = json_decode($translation['faqs'], true);
                                if (is_array($translatedFaqsData)) {
                                    // Handle both old array format [["question","answer"]] and new object format [{"question":"q","answer":"a"}]
                                    if (isset($translatedFaqsData[0])) {
                                        if (is_array($translatedFaqsData[0]) && count($translatedFaqsData[0]) >= 2 && !isset($translatedFaqsData[0]['question'])) {
                                            // Old array format - direct array of pairs [["question","answer"]]
                                            foreach ($translatedFaqsData as $pair) {
                                                if (is_array($pair) && count($pair) >= 2) {
                                                    $faq = [
                                                        'question' => $pair[0],
                                                        'answer' => $pair[1]
                                                    ];
                                                    $languageFaqs[] = $faq;
                                                }
                                            }
                                        } elseif (is_array($translatedFaqsData[0]) && isset($translatedFaqsData[0]['question']) && isset($translatedFaqsData[0]['answer'])) {
                                            // New object format - array of objects [{"question":"q","answer":"a"}]
                                            $languageFaqs = $translatedFaqsData;
                                        } else {
                                            // Object format - object with numeric keys {"1":["question","answer"]}
                                            foreach ($translatedFaqsData as $key => $pair) {
                                                if (is_array($pair) && count($pair) >= 2) {
                                                    $faq = [
                                                        'question' => $pair[0],
                                                        'answer' => $pair[1]
                                                    ];
                                                    $languageFaqs[] = $faq;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }

                    // If no FAQs found in translations table, fall back to main table (for default language)
                    if (empty($languageFaqs) && $isDefaultLanguage) {
                        if (isset($service['faqs']) && is_array($service['faqs']) && !empty($service['faqs'])) {
                            $languageFaqs = $service['faqs'];
                        }
                    }

                    // Set FAQs for this language
                    if ($isDefaultLanguage) {
                        // For default language, set directly in main service data
                        $mergedServiceDetails['faqs'] = $languageFaqs;
                    } else {
                        // For other languages, set in translated data
                        if (!isset($mergedServiceDetails['translated_' . $languageCode])) {
                            $mergedServiceDetails['translated_' . $languageCode] = [];
                        }
                        $mergedServiceDetails['translated_' . $languageCode]['faqs'] = $languageFaqs;
                    }

                    // Set other translated fields with proper fallback logic
                    if ($translatedData['success'] && isset($translatedData['translated_data'][$languageCode])) {
                        $translation = $translatedData['translated_data'][$languageCode];

                        // Process other translatable fields with fallback logic
                        $translatedTitle = !empty($translation['title']) ? $translation['title'] : ($isDefaultLanguage ? $service['title'] : '');
                        $translatedDescription = !empty($translation['description']) ? $translation['description'] : ($isDefaultLanguage ? $service['description'] : '');
                        $translatedLongDescription = !empty($translation['long_description']) ? $translation['long_description'] : ($isDefaultLanguage ? $service['long_description'] : '');
                        $translatedTags = !empty($translation['tags']) ? $translation['tags'] : ($isDefaultLanguage ? $service['tags'] : '');

                        if (!$isDefaultLanguage) {
                            $mergedServiceDetails['translated_' . $languageCode] = [
                                'title' => $translatedTitle,
                                'description' => $translatedDescription,
                                'long_description' => $translatedLongDescription,
                                'tags' => $translatedTags,
                                'faqs' => $languageFaqs
                            ];
                        } else {
                            // For default language, update main service data with translated data if available
                            if (!empty($translation['title'])) {
                                $mergedServiceDetails['title'] = $translation['title'];
                            }
                            if (!empty($translation['description'])) {
                                $mergedServiceDetails['description'] = $translation['description'];
                            }
                            if (!empty($translation['long_description'])) {
                                $mergedServiceDetails['long_description'] = $translation['long_description'];
                            }
                            if (!empty($translation['tags'])) {
                                $mergedServiceDetails['tags'] = $translation['tags'];
                            }
                        }
                    }
                }

                $this->data['service'] = $mergedServiceDetails;
                $this->data['currency'] = get_settings('general_settings', true)['currency'];

                return view('backend/partner/template', $this->data);
            } else {
                return redirect('partner/login');
            }
        } catch (\Throwable $th) {
            throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Services.php - duplicate()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    /**
     * Remove SEO image for a service (partner side)
     * This method handles AJAX requests to remove SEO images
     * @return \CodeIgniter\HTTP\Response
     */
    public function remove_seo_image()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsPartner) {
                return ErrorResponse(labels(UNAUTHORIZED_ACCESS, 'Unauthorized access'), true, [], [], 200, csrf_token(), csrf_hash());
            }

            $serviceId = $this->request->getPost('service_id');
            $seoId = $this->request->getPost('seo_id');

            if (!$serviceId) {
                return ErrorResponse(labels(SERVICE_ID_IS_REQUIRED, 'Service ID is required'), true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Verify that the service belongs to the logged-in partner
            $service = fetch_details('services', ['id' => $serviceId, 'user_id' => $this->userId]);
            if (empty($service)) {
                return ErrorResponse(labels(DATA_NOT_FOUND, 'Data not found'), true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Set SEO model context for services
            $this->seoModel->setTableContext('services');

            // Get existing SEO settings
            $existingSettings = $this->seoModel->getSeoSettingsByReferenceId($serviceId);

            if (!$existingSettings) {
                return ErrorResponse(labels(DATA_NOT_FOUND, 'Data not found'), true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Check if there's an image to remove
            if (empty($existingSettings['image'])) {
                return ErrorResponse(labels(NO_SEO_IMAGE_FOUND_TO_REMOVE, 'No SEO image found to remove'), true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Store the image name for cleanup
            $imageToDelete = $existingSettings['image'];

            // Prepare update data - remove image but keep other fields
            $updateData = [
                'title' => $existingSettings['title'] ?? '',
                'description' => $existingSettings['description'] ?? '',
                'keywords' => $existingSettings['keywords'] ?? '',
                'schema_markup' => $existingSettings['schema_markup'] ?? '',
                'image' => '', // Clear the image field
                'service_id' => $serviceId
            ];

            // Check if all other SEO fields are empty
            $hasOtherSeoData = !empty($updateData['title']) ||
                !empty($updateData['description']) ||
                !empty($updateData['keywords']) ||
                !empty($updateData['schema_markup']);

            // If all other fields are empty, we should NOT delete the record
            // Instead, we keep the record with empty image but preserve the structure
            // This ensures the SEO record exists for future use
            $result = $this->seoModel->updateSeoSettings($existingSettings['id'], $updateData);

            if (!empty($result['error'])) {
                return ErrorResponse($result['message'], true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Clean up the image file from storage
            if (!empty($imageToDelete)) {
                $this->fileService->delete('service_seo_settings', $imageToDelete);
            }

            return successResponse(labels('seo_image_removed_successfully', 'SEO Image Removed Successfully'), false, [], [], 200, csrf_token(), csrf_hash());
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/Services.php - remove_seo_image()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something went wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
}
