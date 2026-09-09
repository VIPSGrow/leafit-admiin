<?php

namespace App\Controllers\Admin;

use App\Models\Service_model;
use App\Models\Seo_model;
use App\Services\ServicesService;
use App\Services\Service\ServiceCreateService;
use App\Services\Service\ServiceUpdateService;
use App\Services\Service\ServiceSeoService;
use App\Services\utility\FileService;
use Config\ApiResponseAndNotificationStrings;

/**
 * Handles service CRUD, moderation, and read-only views.
 *
 * Extracted from Admin\Services as Phase B of the Services refactor.
 * Routes continue to resolve to the same URLs — only the controller class changed.
 */
class ServiceController extends Admin
{
    public $validation, $db, $ionAuth, $creator_id;
    protected $superadmin;
    protected ApiResponseAndNotificationStrings $trans;
    protected ServicesService $serviceService;
    protected Seo_model $seoModel;
    protected ServiceCreateService $createService;
    protected ServiceUpdateService $updateService;
    protected ServiceSeoService $seoService;
    protected FileService $fileService;
    protected Service_model $service;

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
        $this->createService = new ServiceCreateService();
        $this->updateService = new ServiceUpdateService();
        $this->seoService = new ServiceSeoService();
        $this->fileService = new FileService();
        $this->service = new Service_model();
        helper('ResponceServices');
    }

    public function index()
    {
        try {
            setPageInfo($this->data, labels('services', 'Services') . ' | ' . labels('admin_panel', 'Admin Panel'), 'services');
            $this->data['categories_name'] = get_categories_with_translated_names();
            $this->data['categories_tree'] = $this->getCategoriesTree();

            $currentLanguage = $this->db->escape(get_current_language());
            $defaultLanguage = $this->db->escape(get_default_language());

            $this->data['currency'] = get_settings('general_settings', true)['currency'];

            $partner_data = $this->db->table('users u')
                ->select("
                u.id,
                COALESCE(
                    tpd_current.username,
                    tpd_default.username,
                    u.username
                ) AS username,
                COALESCE(
                    tpd_current.company_name,
                    tpd_default.company_name,
                    pd.company_name
                ) AS display_company_name,
                pd.number_of_members,
                pd.type,
                pd.at_store,
                pd.at_doorstep,
                pd.need_approval_for_the_service
            ")
                ->join('partner_details pd', 'pd.partner_id = u.id')
                ->join('translated_partner_details tpd_current', 'tpd_current.partner_id = pd.partner_id AND tpd_current.language_code = "' . $currentLanguage . '"', 'left')
                ->join('translated_partner_details tpd_default', 'tpd_default.partner_id = pd.partner_id AND tpd_default.language_code = "' . $defaultLanguage . '"', 'left')
                ->where('pd.is_approved', '1')
                ->get()->getResultArray();

            $this->data['partner_name'] = $partner_data;
            $this->data['tax_data'] = get_taxes_with_translated_names(['status' => 1], ['id', 'title', 'percentage']);
            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            // throw $th;
            log_message('error', 'Exception in ServiceController::index() --> ' . $th->getMessage() . ' --> ' . $th->getTraceAsString());
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }

    public function list($from_app = false, $search = '', $limit = 10, $offset = 0, $sort = 'id', $order = 'ASC', $where = [], $additional_data = [], $column_name = '', $whereIn = [])
    {
        try {
            $Service_model = new Service_model();
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
            return $Service_model->list(false, $search, $limit, $offset, $sort, $order);
        } catch (\Throwable $th) {
            // throw $th;
            log_message('error', 'Exception in ServiceController::list() --> ' . $th->getMessage() . ' --> ' . $th->getTraceAsString());
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }

    public function add_service_view()
    {
        try {
            setPageInfo($this->data, labels('add_service', 'Add Service') . ' | ' . labels('admin_panel', 'Admin Panel'), 'service_form');
            $this->data['mode'] = 'add';
            $currency = get_settings('general_settings', true);
            if (empty($currency)) {
                session()->setFlashdata(
                    'toastMessage',
                    labels(
                        'Please first add currency and basic details in general settings',
                        'Please first add currency and basic details in general settings'
                    )
                );
                session()->setFlashdata('toastMessageType', 'error');

                return redirect()->to('admin/settings/general-settings');
            }
            $this->data['currency'] = $currency['currency'];
            $this->data['categories_name'] = get_categories_with_translated_names();

            $currentLanguage = $this->db->escape(get_current_language());
            $defaultLanguage = $this->db->escape(get_default_language());

            $partner_data = $this->db->table('users u')
                ->select("u.id,
                COALESCE(
                    tpd_current.username,
                    tpd_default.username,
                    u.username
                ) AS username,
                COALESCE(
                    tpd_current.company_name,
                    tpd_default.company_name,
                    pd.company_name
                ) AS display_company_name,
                pd.number_of_members,
                pd.type,
                pd.at_store,
                pd.at_doorstep,
                pd.need_approval_for_the_service
            ")
                ->join('partner_details pd', 'pd.partner_id = u.id')
                ->join(
                    'translated_partner_details tpd_current',
                    "tpd_current.partner_id = pd.partner_id AND tpd_current.language_code = $currentLanguage",
                    'left'
                )
                ->join(
                    'translated_partner_details tpd_default',
                    "tpd_default.partner_id = pd.partner_id AND tpd_default.language_code = $defaultLanguage",
                    'left'
                )
                ->where('pd.is_approved', '1')
                ->get()
                ->getResultArray();

            $this->data['partner_name'] = $partner_data;
            $this->data['tax_data'] = get_taxes_with_translated_names(['status' => 1], ['id', 'title', 'percentage']);
            $this->data['languages'] = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], '', '0', 'id', 'ACE');

            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            // throw $th;
            log_message('error', 'Exception in ServiceController::add_service_view() --> ' . $th->getMessage() . ' --> ' . $th->getTraceAsString());
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }

    public function add_service()
    {
        try {
            $result = $this->createService->create($this->request, $this->creator_id);

            if ($result['error']) {
                return JsonError($result['message']);
            }

            return JsonSuccess(message: $result['message'], extra: ['redirect_url' => base_url('admin/services')]);
        } catch (\Throwable $th) {
            // throw $th;
            log_message('error', 'Exception in ServiceController::add_service() --> ' . $th->getMessage() . ' --> ' . $th->getTraceAsString());
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }

    public function edit_service()
    {
        try {
            $uri = service('uri');
            $service_id = $uri->getSegments()[3];

            setPageInfo($this->data, labels('services', 'Services') . ' | ' . labels('admin_panel', 'Admin Panel'), 'service_form');
            $this->data['categories_name'] = get_categories_with_translated_names();

            $service = $this->service->where('id', $service_id)->first();

            $service['image'] = $this->fileService->exists('services', $service['image'] ?? null)
                ? $this->fileService->url($service['image'], 'services')
                : '';

            if (!empty($service['other_images'])) {
                $decodedOther = json_decode($service['other_images'], true);
                $service['other_images'] = is_array($decodedOther)
                    ? array_map(fn($p) => $this->fileService->url($p, 'services'), $decodedOther)
                    : [];
            } else {
                $service['other_images'] = [];
            }

            if (!empty($service['files'])) {
                $decodedFiles = json_decode($service['files'], true);
                $service['files'] = is_array($decodedFiles)
                    ? array_map(fn($p) => $this->fileService->url($p, 'services'), $decodedFiles)
                    : [];
            } else {
                $service['files'] = [];
            }

            $service['faqs'] = $this->normalizeFaqsForView($service['faqs'] ?? '');

            $this->data['service'] = $service;

            $currentLanguage = $this->db->escape(get_current_language());
            $defaultLanguage = $this->db->escape(get_default_language());

            $partner_data = $this->db->table('users u')
                ->select(
                    "u.id,u.username,
                COALESCE(
                    tpd_current.company_name,
                    tpd_default.company_name,
                    pd.company_name
                ) AS display_company_name,
                pd.number_of_members,
                pd.type,
                pd.at_store,
                pd.at_doorstep,
                pd.need_approval_for_the_service
            "
                )
                ->join('partner_details pd', 'pd.partner_id = u.id')
                ->join(
                    'translated_partner_details tpd_current',
                    "tpd_current.partner_id = pd.partner_id AND tpd_current.language_code = $currentLanguage",
                    'left'
                )
                ->join(
                    'translated_partner_details tpd_default',
                    "tpd_default.partner_id = pd.partner_id AND tpd_default.language_code = $defaultLanguage",
                    'left'
                )
                ->where('pd.is_approved', '1')
                ->get()->getResultArray();

            $this->data['partner_name'] = $partner_data;
            $this->data['tax_data'] = get_taxes_with_translated_names(['status' => 1], ['id', 'title', 'percentage']);
            $this->data['main_page'] = 'service_form';
            $this->data['mode'] = 'edit';

            $this->seoModel->setTableContext('services');
            $this->data['service_seo_settings'] = $this->seoModel->getSeoSettingsByReferenceId($service_id, 'full');

            $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], '', '0', 'id', 'ACE');
            $this->data['languages'] = $languages;

            $translatedData = $this->serviceService->getServiceWithTranslations($service_id);
            $mergedServiceDetails = $service;

            $seoTranslationModel = model('TranslatedServiceSeoSettings_model');
            $translatedSeoSettings = $seoTranslationModel->getAllTranslationsForService($service_id);

            foreach ($translatedSeoSettings as $translation) {
                $lc = $translation['language_code'];
                $mergedServiceDetails['translated_seo_' . $lc] = [
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
                    if (!empty($translation['faqs'])) {
                        $languageFaqs = is_array($translation['faqs'])
                            ? $translation['faqs']
                            : $this->normalizeFaqsFromJson($translation['faqs']);
                    }
                }

                if (empty($languageFaqs) && $isDefaultLanguage) {
                    if (!empty($service['faqs']) && is_array($service['faqs'])) {
                        $languageFaqs = $service['faqs'];
                    }
                }

                if ($isDefaultLanguage) {
                    $mergedServiceDetails['faqs'] = $languageFaqs;
                } else {
                    $mergedServiceDetails['translated_' . $languageCode]['faqs'] = $languageFaqs;
                }

                if ($translatedData['success'] && isset($translatedData['translated_data'][$languageCode])) {
                    $translation = $translatedData['translated_data'][$languageCode];
                    $tTitle = !empty($translation['title']) ? $translation['title'] : ($isDefaultLanguage ? $service['title'] : '');
                    $tDescription = !empty($translation['description']) ? $translation['description'] : ($isDefaultLanguage ? $service['description'] : '');
                    $tLongDesc = !empty($translation['long_description']) ? $translation['long_description'] : ($isDefaultLanguage ? $service['long_description'] : '');
                    $tTags = !empty($translation['tags']) ? $translation['tags'] : ($isDefaultLanguage ? $service['tags'] : '');

                    if (!$isDefaultLanguage) {
                        $mergedServiceDetails['translated_' . $languageCode] = [
                            'title' => $tTitle,
                            'description' => $tDescription,
                            'long_description' => $tLongDesc,
                            'tags' => $tTags,
                            'faqs' => $languageFaqs,
                        ];
                    } else {
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

            $currency = get_settings('general_settings', true);
            if (empty($currency)) {
                session()->setFlashdata(
                    'toastMessage',
                    labels(
                        'Please first add currency and basic details in general settings',
                        'Please first add currency and basic details in general settings'
                    )
                );
                session()->setFlashdata('toastMessageType', 'error');

                return redirect()->to('admin/settings/general-settings');
            }

            $this->data['currency'] = $currency['currency'];

            $this->data['service'] = $mergedServiceDetails;
            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            // throw $th;
            log_message('error', 'Exception in ServiceController::edit_service() --> ' . $th->getMessage() . ' --> ' . $th->getTraceAsString());
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }

    public function update_service()
    {
        try {
            $result = $this->updateService->update($this->request, $this->creator_id);

            if ($result['error']) {
                return JsonError($result['message']);
            }

            return JsonSuccess(message: $result['message'], extra: ['redirect_url' => base_url('admin/services')]);
        } catch (\Throwable $th) {
            log_message('error', 'Exception in ServiceController::update_service() --> ' . $th->getMessage() . ' --> ' . $th->getTraceAsString());
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }

    public function delete_service()
    {
        try {
            $id = $this->request->getPost('id');
            $old_data = $this->service->where('id', $id)->first();
            if ((defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) && !empty($old_data) && isset($old_data['user_id']) && (int) $old_data['user_id'] === 50) {
                return JsonError(labels(DEMO_MODE_ERROR, 'Modification in demo version is not allowed.'));
            }

            if (!empty($old_data['image'])) {
                $this->fileService->delete('services', $old_data['image']);
            }
            if (!empty($old_data['other_images'])) {
                $other_images = json_decode($old_data['other_images'], true);
                if (is_array($other_images)) {
                    foreach ($other_images as $oi) {
                        $this->fileService->delete('services', $oi);
                    }
                }
            }
            if (!empty($old_data['files'])) {
                $files = json_decode($old_data['files'], true);
                if (is_array($files)) {
                    foreach ($files as $oi) {
                        $this->fileService->delete('services', $oi);
                    }
                }
            }

            $this->seoModel->cleanupSeoData($id, 'services');
            $this->seoService->cleanupForService($id);
            $this->serviceService->deleteServiceTranslations($id);

            $builder = $this->db->table('services')->delete(['id' => $id]);
            $builder = $this->db->table('cart')->delete(['service_id' => $id]);
            $builder = $this->db->table('services_ratings')->delete(['service_id' => $id]);

            if ($builder) {
                return JsonSuccess(labels('data_deleted_successfully', 'Data deleted successfully'));
            } else {
                return JsonError(labels('error_occured', 'An error Occured'));
            }
        } catch (\Throwable $th) {
            // throw $th;
            log_message('error', 'Exception in ServiceController::delete_service() --> ' . $th->getMessage() . ' --> ' . $th->getTraceAsString());
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }
    public function service_detail()
    {
        try {
            $uri = service('uri');
            $service_id = $uri->getSegments()[3];

            setPageInfo($this->data, labels('services', 'Services') . ' | ' . labels('admin_panel', 'Admin Panel'), 'service_details');

            $currentLanguage = $this->db->escape(get_current_language());
            $defaultLanguage = $this->db->escape(get_default_language());

            $partner_data = $this->db->table('users u')
                ->select("
                u.id,
                COALESCE(
                    tpd_current.username,
                    tpd_default.username,
                    u.username
                ) AS username,
                COALESCE(
                    tpd_current.company_name,
                    tpd_default.company_name,
                    pd.company_name
                ) AS provider_company_name,
                pd.number_of_members,
                pd.type
            ")
                ->join('partner_details pd', 'pd.partner_id = u.id')
                ->join(
                    'translated_partner_details tpd_current',
                    "tpd_current.partner_id = pd.partner_id AND tpd_current.language_code = $currentLanguage",
                    'left'
                )
                ->join(
                    'translated_partner_details tpd_default',
                    "tpd_default.partner_id = pd.partner_id AND tpd_default.language_code = $defaultLanguage",
                    'left'
                )
                ->where('is_approved', '1')
                ->get()->getResultArray();
            $this->data['partner_name'] = $partner_data;
            $this->data['tax_data'] = get_taxes_with_translated_names(['status' => 1], ['id', 'title', 'percentage']);

            $service = $this->service->where('id', $service_id)->first();

            if (empty($service)) {
                return redirect('admin/login');
            }

            $service['image'] = $this->fileService->exists('services', $service['image'] ?? null)
                ? $this->fileService->url($service['image'], 'services')
                : $this->fileService->url(null, 'services');

            if (!empty($service['other_images'])) {
                $decodedOtherImages = json_decode($service['other_images'], true);
                if (is_array($decodedOtherImages)) {
                    $existingImages = array_filter($decodedOtherImages, fn($path) => !empty($path) && $this->fileService->exists('services', $path));
                    $service['other_images'] = array_values(array_map(fn($path) => $this->fileService->url($path, 'services'), $existingImages));
                } else {
                    $service['other_images'] = [];
                }
            } else {
                $service['other_images'] = [];
            }

            if (!empty($service['files'])) {
                $decodedFiles = json_decode($service['files'], true);
                $service['files'] = is_array($decodedFiles)
                    ? array_map(fn($p) => $this->fileService->url($p, 'services'), $decodedFiles)
                    : [];
            } else {
                $service['files'] = [];
            }

            $translatedServiceModel = new \App\Models\TranslatedServiceDetails_model();

            $categoryName = get_categories_with_translated_names(whereConditions: ['id' => (int) $service['category_id']]);

            $service['category_name'] = !empty($categoryName) ? $categoryName[0]['name'] : "N/A";

            $allServiceTranslations = $translatedServiceModel->getAllTranslationsForService($service_id);
            $serviceTranslationsByLang = [];
            foreach ($allServiceTranslations as $translation) {
                $serviceTranslationsByLang[$translation['language_code']] = $translation;
            }
            $currentServiceTranslation = $serviceTranslationsByLang[$currentLanguage] ?? null;
            $defaultServiceTranslation = $serviceTranslationsByLang[$defaultLanguage] ?? null;

            foreach (['title', 'description', 'long_description'] as $field) {
                if (!empty($currentServiceTranslation[$field])) {
                    $service[$field] = $currentServiceTranslation[$field];
                } elseif (!empty($defaultServiceTranslation[$field])) {
                    $service[$field] = $defaultServiceTranslation[$field];
                }
            }
            if (!empty($currentServiceTranslation['faqs'])) {
                $service['faqs'] = $currentServiceTranslation['faqs'];
            } elseif (!empty($defaultServiceTranslation['faqs'])) {
                $service['faqs'] = $defaultServiceTranslation['faqs'];
            }

            // Normalize FAQs
            $normalizedFaqs = [];
            if (!empty($service['faqs'])) {
                $decodedFaqs = json_decode($service['faqs'], true);
                if (is_array($decodedFaqs)) {
                    foreach ($decodedFaqs as $faq) {
                        if (!is_array($faq))
                            continue;
                        if (isset($faq[0], $faq[1])) {
                            $question = $faq[0];
                            $answer = $faq[1];
                        } elseif (isset($faq['question'], $faq['answer'])) {
                            $question = $faq['question'];
                            $answer = $faq['answer'];
                        } else {
                            continue;
                        }
                        if (trim((string) $question) !== '' && trim((string) $answer) !== '') {
                            $normalizedFaqs[] = ['question' => $question, 'answer' => $answer];
                        }
                    }
                }
            }
            $service['faqs'] = $normalizedFaqs;

            $this->data['service'] = $service;
            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            throw $th;
            log_message('error', 'Exception in ServiceController::service_detail() --> ' . $th->getMessage() . ' --> ' . $th->getTraceAsString());
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }

    public function disapprove_service()
    {
        try {
            $partner_id = $this->request->getPost('partner_id');
            $service_id = $this->request->getPost('service_id');

            $service_approval = $this->service->set('approved_by_admin', 0)->where('user_id', $partner_id)->where('id', $service_id)->update();

            if ($service_approval) {
                try {
                    $service_details = $this->service->select('title')->where('id', $service_id)->first();
                    $service_title = $service_details['title'] ?? '';
                    queue_notification_service(
                        eventType: 'service_disapproved',
                        recipients: ['user_id' => $partner_id],
                        context: ['provider_id' => $partner_id, 'user_id' => $partner_id, 'service_id' => $service_id, 'service_title' => $service_title],
                        options: [
                            'channels' => ['fcm', 'email', 'sms'],
                            'language' => get_default_language(),
                            'platforms' => ['android', 'ios', 'provider_panel'],
                            'type' => 'service_request_status',
                            'data' => ['status' => 'reject', 'type_id' => (string) $partner_id, 'click_action' => 'FLUTTER_NOTIFICATION_CLICK'],
                        ]
                    );
                } catch (\Throwable $notificationError) {
                    log_message('error', '[SERVICE_DISAPPROVED] Notification error trace: ' . $notificationError->getTraceAsString());
                }
                return JsonSuccess('Service is disapproved');
            } else {
                return JsonError('Could not disapprove service');
            }
        } catch (\Throwable $th) {
            log_message('error', 'Exception in ServiceController::disapprove_service() --> ' . $th->getMessage() . ' --> ' . $th->getTraceAsString());
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }

    public function approve_service()
    {
        try {
            $partner_id = $this->request->getPost('partner_id');
            $service_id = $this->request->getPost('service_id');

            $service_approval = $this->service->set('approved_by_admin', 1)->where('user_id', $partner_id)->where('id', $service_id)->update();

            if ($service_approval) {
                try {
                    $service_details = $this->service->select('title')->where('id', $service_id)->first();
                    $service_title = $service_details['title'] ?? '';
                    queue_notification_service(
                        eventType: 'service_approved',
                        recipients: ['user_id' => $partner_id],
                        context: ['provider_id' => $partner_id, 'user_id' => $partner_id, 'service_id' => $service_id, 'service_title' => $service_title],
                        options: [
                            'channels' => ['fcm', 'email', 'sms'],
                            'language' => get_default_language(),
                            'platforms' => ['android', 'ios', 'provider_panel'],
                            'type' => 'service_request_status',
                            'data' => ['status' => 'approve', 'type_id' => (string) $partner_id, 'click_action' => 'FLUTTER_NOTIFICATION_CLICK'],
                        ]
                    );
                } catch (\Throwable $notificationError) {
                    log_message('error', '[SERVICE_APPROVED] Notification error trace: ' . $notificationError->getTraceAsString());
                }
                return JsonSuccess('Service is approved');
            } else {
                return JsonError('Could not Approve service');
            }
        } catch (\Throwable $th) {
            log_message('error', 'Exception in ServiceController::approve_service() --> ' . $th->getMessage() . ' --> ' . $th->getTraceAsString());
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }

    public function remove_seo_image()
    {
        try {
            $serviceId = $this->request->getPost('service_id');
            if (!$serviceId) {
                return JsonError('Service ID is required');
            }

            $this->seoModel->setTableContext('services');
            $existingSettings = $this->seoModel->getSeoSettingsByReferenceId($serviceId);

            if (!$existingSettings) {
                return JsonError('SEO settings not found for this service');
            }
            if (empty($existingSettings['image'])) {
                return JsonError('No SEO image found to remove');
            }

            $imageToDelete = $existingSettings['image'];
            $updateData = [
                'title' => $existingSettings['title'] ?? '',
                'description' => $existingSettings['description'] ?? '',
                'keywords' => $existingSettings['keywords'] ?? '',
                'schema_markup' => $existingSettings['schema_markup'] ?? '',
                'image' => '',
                'service_id' => $serviceId,
            ];

            $result = $this->seoModel->updateSeoSettings($existingSettings['id'], $updateData);
            if (!empty($result['error'])) {
                return JsonError($result['message']);
            }

            if (!empty($imageToDelete)) {
                $this->fileService->delete('service_seo_settings', $imageToDelete);
            }

            return JsonSuccess('SEO image removed successfully');
        } catch (\Throwable $th) {
            log_message('error', 'Exception in ServiceController::remove_seo_image() --> ' . $th->getMessage() . ' --> ' . $th->getTraceAsString());
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something went wrong'));
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function getCategoriesTree(): array
    {
        try {
            $categories = $this->db->table('categories')->get()->getResultArray();
            $tree = [];
            foreach ($categories as $category) {
                if (!$category['parent_id']) {
                    $tree[] = $this->buildTree($categories, $category);
                }
            }
            return $tree;
        } catch (\Throwable $th) {
            log_message('error', 'Exception in ServiceController::getCategoriesTree() --> ' . $th->getMessage() . ' --> ' . $th->getTraceAsString());
            return [];
        }
    }

    private function buildTree(array &$categories, array $currentCategory): array
    {
        $tree = ['id' => $currentCategory['id'], 'text' => $currentCategory['name']];
        $children = [];
        foreach ($categories as $category) {
            if ($category['parent_id'] == $currentCategory['id']) {
                $children[] = $this->buildTree($categories, $category);
            }
        }
        if (!empty($children)) {
            $tree['children'] = $children;
        }
        return $tree;
    }

    private function normalizeFaqsForView(string $faqsJson): array
    {
        if (empty($faqsJson)) {
            return [];
        }
        $faqsData = json_decode($faqsJson, true);
        if (!is_array($faqsData)) {
            return [];
        }
        return $this->normalizeFaqsFromDecoded($faqsData);
    }

    private function normalizeFaqsFromJson(string $faqsJson): array
    {
        $data = json_decode($faqsJson, true);
        return is_array($data) ? $this->normalizeFaqsFromDecoded($data) : [];
    }

    private function normalizeFaqsFromDecoded(array $faqsData): array
    {
        $faqs = [];
        if (!isset($faqsData[0])) {
            return $faqs;
        }
        if (is_array($faqsData[0]) && count($faqsData[0]) >= 2 && !isset($faqsData[0]['question'])) {
            foreach ($faqsData as $pair) {
                if (is_array($pair) && count($pair) >= 2) {
                    $faqs[] = ['question' => $pair[0], 'answer' => $pair[1]];
                }
            }
        } elseif (isset($faqsData[0]['question'], $faqsData[0]['answer'])) {
            $faqs = $faqsData;
        } else {
            foreach ($faqsData as $pair) {
                if (is_array($pair) && count($pair) >= 2) {
                    $faqs[] = ['question' => $pair[0], 'answer' => $pair[1]];
                }
            }
        }
        return $faqs;
    }
}
