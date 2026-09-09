<?php

namespace App\Controllers\Admin;

use Config\ApiResponseAndNotificationStrings;
use App\Models\CustomPageModel;
use App\Models\Seo_model;
use App\Models\TranslatedCustomPageDetailsModel;
use App\Models\TranslatedGeneralSeoSettings_model;
use App\Services\utility\SlugService;
use App\Services\utility\PermissionService;

class CustomPages extends Admin
{
    protected $superadmin;
    protected ApiResponseAndNotificationStrings $trans;
    protected CustomPageModel $customPageModel;
    protected TranslatedCustomPageDetailsModel $translatedCustomPageModel;
    protected TranslatedGeneralSeoSettings_model $translatedGeneralSeoModel;
    protected Seo_model $seoModel;
    protected SlugService $slugService;
    protected PermissionService $permissionService;

    public function __construct()
    {
        parent::__construct();
        $this->customPageModel = new CustomPageModel();
        $this->translatedCustomPageModel = new TranslatedCustomPageDetailsModel();
        $this->translatedGeneralSeoModel = new TranslatedGeneralSeoSettings_model();
        $this->seoModel = new Seo_model();
        $this->slugService = new SlugService();
        $this->permissionService = new PermissionService();
        $this->superadmin = $this->session->get('email');
        $this->trans = new ApiResponseAndNotificationStrings();
        helper('ResponceServices');
    }

    /**
     * List page view for custom pages
     */
    public function index()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            if (!$this->permissionService->can($this->userId, 'read', 'custom_pages')) {
                return NoPermission();
            }
            $this->data['custom_page_permissions'] = [
                'create' => $this->permissionService->can($this->userId, 'create', 'custom_pages'),
                'read' => true,
                'update' => $this->permissionService->can($this->userId, 'update', 'custom_pages'),
                'delete' => $this->permissionService->can($this->userId, 'delete', 'custom_pages'),
            ];
            setPageInfo($this->data, labels('custom_pages', 'Custom Pages') . ' | ' . labels('admin_panel', 'Admin Panel'), 'custom_pages');
            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/CustomPages.php - index()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    /**
     * Add form view for custom pages
     */
    public function add()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            if (!$this->permissionService->can($this->userId, 'create', 'custom_pages')) {
                return NoPermission();
            }
            setPageInfo($this->data, labels('add_custom_page', 'Add Custom Page') . ' | ' . labels('admin_panel', 'Admin Panel'), 'add_custom_page');

            // Load languages for translation support
            $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ACE');
            $this->data['languages'] = $languages;
            $this->data['can_create'] = true;
            $this->data['can_update'] = false;

            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/CustomPages.php - add()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    /**
     * Handle add form POST - insert new custom page
     */
    public function insert()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            if (!$this->permissionService->can($this->userId, 'create', 'custom_pages')) {
                return ErrorResponse(labels('no_permission_to_take_this_action', 'You do not have permission to perform this action'), true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Get translation data from form
            $titles = $this->request->getPost('title') ?? [];
            $contents = $this->request->getPost('content') ?? [];
            $slug = $this->request->getPost('slug');
            $status = $this->request->getPost('status') ?? 1;

            // Get default language
            $defaultLanguage = get_default_language();

            // Validate that default language has required fields
            $defaultTitle = isset($titles[$defaultLanguage]) ? trim($titles[$defaultLanguage]) : '';
            $defaultContent = isset($contents[$defaultLanguage]) ? trim($contents[$defaultLanguage]) : '';

            if (empty($defaultTitle)) {
                return ErrorResponse(labels("title_in_default_language_is_required", "Title in default language is required"), true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Resolve slug
            $resolvedSlug = $this->slugService->resolve(
                currentSlug: null,
                inputSlug: $slug,
                fallbackName: $defaultTitle,
                table: 'custom_pages',
                excludeId: null
            );

            // Insert base record
            $pageData = [
                'title' => $this->removeScript(trim($defaultTitle)),
                'slug' => $resolvedSlug,
                'content' => $this->removeScript(trim($defaultContent)),
                'status' => $status,
            ];

            $pageId = (int) $this->customPageModel->insert($pageData);
            if (!$pageId) {
                $errors = $this->customPageModel->errors();
                $message = !empty($errors) ? reset($errors) : labels(ERROR_OCCURED, "An error occurred");
                return ErrorResponse($message, true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Save translations for all languages
            $translations = [];
            foreach ($titles as $languageCode => $title) {
                $translations[$languageCode] = [
                    'title' => $this->removeScript(trim($title)),
                    'content' => $this->removeScript(trim($contents[$languageCode] ?? '')),
                ];
            }

            // Ensure default language translation is included
            $translations[$defaultLanguage] = [
                'title' => $this->removeScript(trim($defaultTitle)),
                'content' => $this->removeScript(trim($defaultContent)),
            ];

            $this->translatedCustomPageModel->saveTranslations($pageId, $translations);

            // Save SEO settings
            $this->saveSeoSettings($pageId, $resolvedSlug);

            return successResponse(labels(DATA_SAVED_SUCCESSFULLY, "Data saved successfully"), false, [], ['redirect_url' => base_url('admin/custom-pages')], 200, csrf_token(), csrf_hash());
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/CustomPages.php - insert()');
            return ErrorResponse($th->getMessage() ?: labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    /**
     * Edit form view for custom page
     *
     * @param int $id Custom page ID
     */
    public function edit($id)
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            if (!$this->permissionService->can($this->userId, 'update', 'custom_pages')) {
                return NoPermission();
            }

            // Load page by ID
            $page = $this->customPageModel->getById($id);
            if (!$page) {
                throw new \Exception(labels('custom_page_not_found', 'Custom page not found'));
            }

            // Load all translations
            $translations = $this->translatedCustomPageModel->getAllTranslations($id);
            $defaultLanguage = $this->getDefaultLanguageCode();

            // If no translations exist, populate with base table data as fallback
            if (empty($translations)) {
                $translations = [
                    $defaultLanguage => [
                        'title' => $page['title'] ?? '',
                        'content' => $page['content'] ?? '',
                    ]
                ];
            } else {
                // Ensure all languages have entries
                $allLanguages = fetch_details('languages', [], ['code']);
                foreach ($allLanguages as $lang) {
                    $langCode = $lang['code'];
                    if (!isset($translations[$langCode])) {
                        if ($langCode === $defaultLanguage) {
                            $translations[$langCode] = [
                                'title' => $page['title'] ?? '',
                                'content' => $page['content'] ?? '',
                            ];
                        } else {
                            $translations[$langCode] = [
                                'title' => '',
                                'content' => '',
                            ];
                        }
                    }
                }
            }

            // Fetch SEO settings for this custom page using general context
            $this->seoModel->setTableContext('general');
            $seo_settings = $this->seoModel->getSeoSettingsByPage('custom_page_' . $page['slug']);

            // Load SEO translations and merge with main SEO settings
            $seoTranslations = [];
            if (!empty($seo_settings) && !empty($seo_settings['id'])) {
                $seoTranslations = $this->translatedGeneralSeoModel->getAllTranslationsForSeo($seo_settings['id']);
            }

            // Build full image URL for SEO preview
            if (!empty($seo_settings['image'])) {
                $disk = fetch_current_file_manager();
                if ($disk === 'aws_s3') {
                    $seo_settings['image'] = fetch_cloud_front_url('custom_page_seo_settings', $seo_settings['image']);
                } else {
                    $seo_settings['image'] = base_url('public/uploads/seo_settings/custom_page_seo_settings/' . $seo_settings['image']);
                }
            }

            // Always merge SEO translations with main SEO settings
            $mergedSeoSettings = $seo_settings ?? [];
            $allLanguages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ASC');

            foreach ($allLanguages as $language) {
                $languageCode = $language['code'];
                $isDefault = $language['is_default'] == 1;

                // Find SEO translation for this language
                $seoTranslation = null;
                if (!empty($seoTranslations)) {
                    foreach ($seoTranslations as $translation) {
                        if ($translation['language_code'] === $languageCode) {
                            $seoTranslation = $translation;
                            break;
                        }
                    }
                }

                if ($seoTranslation) {
                    $mergedSeoSettings['translated_' . $languageCode] = [
                        'title' => $seoTranslation['seo_title'] ?? '',
                        'description' => $seoTranslation['seo_description'] ?? '',
                        'keywords' => $seoTranslation['seo_keywords'] ?? '',
                        'schema_markup' => $seoTranslation['seo_schema_markup'] ?? ''
                    ];
                } else {
                    $mergedSeoSettings['translated_' . $languageCode] = [
                        'title' => $isDefault ? ($seo_settings['title'] ?? '') : '',
                        'description' => $isDefault ? ($seo_settings['description'] ?? '') : '',
                        'keywords' => $isDefault ? ($seo_settings['keywords'] ?? '') : '',
                        'schema_markup' => $isDefault ? ($seo_settings['schema_markup'] ?? '') : ''
                    ];
                }
            }

            // Load languages for translation support
            $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ACE');
            $this->data['languages'] = $languages;
            $this->data['translations'] = $translations;
            $this->data['custom_page'] = $page;
            $this->data['custom_page_seo_settings'] = $mergedSeoSettings;
            $this->data['edit_mode'] = true;
            $this->data['can_create'] = false;
            $this->data['can_update'] = $this->permissionService->can($this->userId, 'update', 'custom_pages');

            setPageInfo($this->data, labels('edit_custom_page', 'Edit Custom Page') . ' | ' . labels('admin_panel', 'Admin Panel'), 'add_custom_page');
            $this->data['main_page'] = 'add_custom_page';
            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/CustomPages.php - edit()');
            return ErrorResponse($th->getMessage() ?: labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    /**
     * Handle edit form POST - update existing custom page
     *
     * @param int $id Custom page ID
     */
    public function update($id)
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }

            if (!$this->permissionService->can($this->userId, 'update', 'custom_pages')) {
                return ErrorResponse(labels('no_permission_to_take_this_action', 'You do not have permission to perform this action'), true, [], [], 200, csrf_token(), csrf_hash());
            }

            if (!empty($_POST)) {
                // Get translation data from form
                $titles = $this->request->getPost('title') ?? [];
                $contents = $this->request->getPost('content') ?? [];
                $newSlug = $this->request->getPost('slug');
                $status = $this->request->getPost('status') ?? 1;

                // Get default language
                $defaultLanguage = get_default_language();

                // Validate that default language has required fields
                $defaultTitle = isset($titles[$defaultLanguage]) ? trim($titles[$defaultLanguage]) : '';
                $defaultContent = isset($contents[$defaultLanguage]) ? trim($contents[$defaultLanguage]) : '';

                if (empty($defaultTitle)) {
                    return ErrorResponse(labels("title_in_default_language_is_required", "Title in default language is required"), true, [], [], 200, csrf_token(), csrf_hash());
                }

                // Get existing slug
                $existingPage = $this->customPageModel->find($id);
                if (!$existingPage) {
                    return ErrorResponse(labels('custom_page_not_found', 'Custom page not found'), true, [], [], 200, csrf_token(), csrf_hash());
                }
                $currentSlug = $existingPage['slug'];

                // Re-resolve slug
                $resolvedSlug = $this->slugService->resolve(
                    currentSlug: $currentSlug,
                    inputSlug: $newSlug,
                    fallbackName: $defaultTitle,
                    table: 'custom_pages',
                    excludeId: $id
                );

                // Update base record
                $pageData = [
                    'id' => $id,
                    'title' => $this->removeScript(trim($defaultTitle)),
                    'slug' => $resolvedSlug,
                    'content' => $this->removeScript(trim($defaultContent)),
                    'status' => $status,
                ];

                // Use save() instead of update() if we want to follow CI4 model conventions more closely,
                // but update() is also fine if $id is passed.
                $updateResult = $this->customPageModel->save($pageData);
            
                if (!$updateResult) {
                    $errors = $this->customPageModel->errors();
                    $message = !empty($errors) ? reset($errors) : labels(ERROR_OCCURED, "An error occurred");
                    return ErrorResponse($message, true, [], [], 200, csrf_token(), csrf_hash());
                }

                // Save translations for all languages
                $translations = [];
                foreach ($titles as $languageCode => $title) {
                    $translations[$languageCode] = [
                        'title' => $this->removeScript(trim($title)),
                        'content' => $this->removeScript(trim($contents[$languageCode] ?? '')),
                    ];
                }

                // Ensure default language translation is included
                $translations[$defaultLanguage] = [
                    'title' => $this->removeScript(trim($defaultTitle)),
                    'content' => $this->removeScript(trim($defaultContent)),
                ];

                $this->translatedCustomPageModel->saveTranslations($id, $translations);

                // If slug changed, update SEO page field too
                if ($currentSlug !== $resolvedSlug) {
                    $this->seoModel->setTableContext('general');
                    $existingSeo = $this->seoModel->getSeoSettingsByPage('custom_page_' . $currentSlug);
                    if ($existingSeo) {
                        $this->seoModel->update($existingSeo['id'], ['page' => 'custom_page_' . $resolvedSlug]);
                    }
                }

                // Save SEO settings
                $this->saveSeoSettings($id, $resolvedSlug);

                return successResponse(labels(DATA_UPDATED_SUCCESSFULLY, "Data updated successfully"), false, [], ['redirect_url' => base_url('admin/custom-pages')], 200, csrf_token(), csrf_hash());
            } else {
                return redirect()->to('admin/custom-pages');
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/CustomPages.php - update()');
            return ErrorResponse($th->getMessage() ?: labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    /**
     * Delete custom page
     */
    public function delete()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
    
            if (!$this->permissionService->can($this->userId, 'delete', 'custom_pages')) {
                return ErrorResponse(labels('no_permission_to_take_this_action', 'You do not have permission to perform this action'), true, [], [], 200, csrf_token(), csrf_hash());
            }

            $id = $this->request->getPost('id');
            $page = $this->customPageModel->find($id);

            if (!$page) {
                return ErrorResponse(labels('custom_page_not_found', 'Custom page not found'), true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Delete SEO settings for this custom page
            $this->seoModel->setTableContext('general');
            $existingSeo = $this->seoModel->getSeoSettingsByPage('custom_page_' . $page['slug']);
            if ($existingSeo) {
                // Delete SEO translations first
                if (!empty($existingSeo['id'])) {
                    $this->translatedGeneralSeoModel->deleteSeoTranslations($existingSeo['id']);
                }
                // Clean up SEO image if exists
                if (!empty($existingSeo['image'])) {
                    $disk = fetch_current_file_manager();
                    delete_file_based_on_server('custom_page_seo_settings', $existingSeo['image'], $disk);
                }
                $this->seoModel->delete($existingSeo['id']);
            }

            // Delete base record (translations cascade via FK)
            $deleteResult = $this->customPageModel->delete($id);
            if ($deleteResult) {
                return successResponse(labels(DATA_DELETED_SUCCESSFULLY, "Data deleted successfully"), false, [], [], 200, csrf_token(), csrf_hash());
            } else {
                return ErrorResponse(labels(ERROR_OCCURED, "An error occurred"), true, [], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/CustomPages.php - delete()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    /**
     * AJAX status toggle for custom page
     */
    public function toggle_status()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return ErrorResponse(labels(UNAUTHORIZED_ACCESS, "Unauthorized access"), true, [], [], 200, csrf_token(), csrf_hash());
            }
    
            if (!$this->permissionService->can($this->userId, 'update', 'custom_pages')) {
                return ErrorResponse(labels('no_permission_to_take_this_action', 'You do not have permission to perform this action'), true, [], [], 200, csrf_token(), csrf_hash());
            }

            $id = $this->request->getPost('id');
            $status = $this->request->getPost('status');

            if (empty($id)) {
                return ErrorResponse(labels('id_required', 'ID is required'), true, [], [], 200, csrf_token(), csrf_hash());
            }

            $updateResult = $this->customPageModel->update($id, ['status' => $status]);
            if ($updateResult) {
                return successResponse(labels(DATA_UPDATED_SUCCESSFULLY, "Status updated successfully"), false, [], [], 200, csrf_token(), csrf_hash());
            } else {
                return ErrorResponse(labels(ERROR_OCCURED, "An error occurred"), true, [], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/CustomPages.php - toggle_status()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    /**
     * DataTable AJAX endpoint for listing custom pages
     */
    public function list()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            if (!$this->permissionService->can($this->userId, 'read', 'custom_pages')) {
                return ErrorResponse(labels(UNAUTHORIZED_ACCESS, "Unauthorized access"), true, [], [], 200, csrf_token(), csrf_hash());
            }

            $params = [
                'limit' => isset($_GET['limit']) && !empty($_GET['limit']) ? (int) $_GET['limit'] : 10,
                'offset' => isset($_GET['offset']) && !empty($_GET['offset']) ? (int) $_GET['offset'] : 0,
                'sort' => isset($_GET['sort']) && !empty($_GET['sort']) ? esc($_GET['sort']) : 'id',
                'order' => isset($_GET['order']) && in_array(strtoupper($_GET['order']), ['ASC', 'DESC']) ? strtoupper($_GET['order']) : 'DESC',
                'search' => isset($_GET['search']) && !empty($_GET['search']) ? esc($_GET['search']) : '',
                'language_code' => isset($_GET['language_code']) && !empty($_GET['language_code'])
                    ? esc($_GET['language_code'])
                    : get_current_language(),
                'include_operations' => true,
                'permissions' => [
                    'update' => $this->permissionService->can($this->userId, 'update', 'custom_pages'),
                    'delete' => $this->permissionService->can($this->userId, 'delete', 'custom_pages'),
                ],
            ];

            $data = $this->customPageModel->list($params);
            return $this->response->setJSON($data);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/CustomPages.php - list()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    /**
     * Remove SEO image for a custom page (AJAX)
     */
    public function remove_seo_image()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return ErrorResponse(labels(UNAUTHORIZED_ACCESS, "Unauthorized access"), true, [], [], 200, csrf_token(), csrf_hash());
            }
            if (!$this->permissionService->can($this->userId, 'update', 'custom_pages')) {
                return ErrorResponse(labels(UNAUTHORIZED_ACCESS, "Unauthorized access"), true, [], [], 200, csrf_token(), csrf_hash());
            }

            $customPageId = $this->request->getPost('custom_page_id');
            $seoId = $this->request->getPost('seo_id');

            if (!$customPageId) {
                return ErrorResponse(labels('custom_page_id_required', "Custom page ID is required"), true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Get the custom page to find the slug
            $page = $this->customPageModel->find($customPageId);
            if (!$page) {
                return ErrorResponse(labels('custom_page_not_found', "Custom page not found"), true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Set SEO model context for general
            $this->seoModel->setTableContext('general');

            // Get existing SEO settings
            $existingSettings = $this->seoModel->getSeoSettingsByPage('custom_page_' . $page['slug']);

            if (!$existingSettings) {
                return ErrorResponse(labels('seo_settings_not_found', "SEO settings not found for this custom page"), true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Check if there's an image to remove
            if (empty($existingSettings['image'])) {
                return ErrorResponse(labels(NO_SEO_IMAGE_FOUND_TO_REMOVE, "No SEO image found to remove"), true, [], [], 200, csrf_token(), csrf_hash());
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
                'page' => 'custom_page_' . $page['slug']
            ];

            $result = $this->seoModel->updateSeoSettings($existingSettings['id'], $updateData);

            if (!empty($result['error'])) {
                return ErrorResponse($result['message'], true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Clean up the image file from storage
            if (!empty($imageToDelete)) {
                $disk = fetch_current_file_manager();
                delete_file_based_on_server('custom_page_seo_settings', $imageToDelete, $disk);
            }

            return successResponse(labels(SEO_IMAGE_REMOVED_SUCCESSFULLY, "SEO image removed successfully"), false, [], [], 200, csrf_token(), csrf_hash());
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/CustomPages.php - remove_seo_image()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something went wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    // ========================================================================
    // Private Helper Methods
    // ========================================================================

    /**
     * Save SEO settings for a custom page with multi-language support.
     * Uses the general seo_settings table with page = 'custom_page_<slug>'.
     *
     * @param int $customPageId
     * @param string $slug
     */
    private function saveSeoSettings(int $customPageId, string $slug): void
    {
        // Get default language for SEO data
        $defaultLanguage = get_default_language();

        // Get all POST data and transform it to translated fields structure
        $postData = $this->request->getPost();

        // Transform form data to translated fields structure
        $translatedFields = $this->transformFormDataToTranslatedFields($postData, $defaultLanguage);

        // Extract default language SEO data
        $defaultSeoTitle = '';
        $defaultSeoDescription = '';
        $defaultSeoKeywords = '';
        $defaultSeoSchema = '';

        if (!empty($translatedFields['seo_title'][$defaultLanguage])) {
            $defaultSeoTitle = trim($translatedFields['seo_title'][$defaultLanguage]);
        }

        if (!empty($translatedFields['seo_description'][$defaultLanguage])) {
            $defaultSeoDescription = trim($translatedFields['seo_description'][$defaultLanguage]);
        }

        if (!empty($translatedFields['seo_keywords'][$defaultLanguage])) {
            $keywordsData = $translatedFields['seo_keywords'][$defaultLanguage];
            if (is_array($keywordsData)) {
                if (count($keywordsData) === 1 && is_string($keywordsData[0])) {
                    $defaultSeoKeywords = $keywordsData[0];
                } else {
                    $defaultSeoKeywords = implode(',', $keywordsData);
                }
            } else {
                $defaultSeoKeywords = $keywordsData;
            }
        }

        if (!empty($translatedFields['seo_schema_markup'][$defaultLanguage])) {
            $defaultSeoSchema = trim($translatedFields['seo_schema_markup'][$defaultLanguage]);
        }

        // Parse meta keywords (Tagify or comma-separated)
        $keywords = $defaultSeoKeywords ? $this->parseKeywords($defaultSeoKeywords) : '';

        // Build SEO data array - use 'page' for general context
        $seoData = [
            'title' => $defaultSeoTitle,
            'description' => $defaultSeoDescription,
            'keywords' => $keywords,
            'schema_markup' => $defaultSeoSchema,
            'page' => 'custom_page_' . $slug,
        ];

        // Check if any SEO field is filled (excluding page)
        $hasSeoData = !empty($seoData['title']) || !empty($seoData['description']) ||
            !empty($seoData['keywords']) || !empty($seoData['schema_markup']);

        // Check if all SEO fields are intentionally cleared
        $allFieldsCleared = empty($seoData['title']) &&
            empty($seoData['description']) &&
            empty($seoData['keywords']) &&
            empty($seoData['schema_markup']);

        // Handle SEO image upload
        $seoImage = $this->request->getFile('meta_image');
        $hasImage = $seoImage && $seoImage->isValid();

        // Use Seo_model for general context with relaxed validation (SEO is optional for custom pages)
        $this->seoModel->setTableContext('general');
        $this->seoModel->setValidationRules([
            'page' => 'required|max_length[100]',
            'title' => 'permit_empty|max_length[255]',
            'description' => 'permit_empty|max_length[500]',
            'keywords' => 'permit_empty',
            'schema_markup' => 'permit_empty',
        ]);
        $existingSettings = $this->seoModel->getSeoSettingsByPage('custom_page_' . $slug);

        $newSeoData = $seoData;
        if ($hasImage) {
            try {
                if (!is_dir(FCPATH . 'public/uploads/seo_settings/custom_page_seo_settings/')) {
                    mkdir(FCPATH . 'public/uploads/seo_settings/custom_page_seo_settings/', 0775, true);
                }
                $uploadResult = upload_file(
                    $seoImage,
                    'public/uploads/seo_settings/custom_page_seo_settings/',
                    labels(FAILED_TO_UPLOAD_SEO_IMAGE, 'Failed to upload SEO image'),
                    'seo_settings/custom_page_seo_settings'
                );
                if ($uploadResult['error'] == false) {
                    $newSeoData['image'] = $uploadResult['file_name'];
                } else {
                    throw new \Exception($uploadResult['message']);
                }
            } catch (\Throwable $t) {
                throw new \Exception(labels(SEO_IMAGE_UPLOAD_FAILED, 'SEO image upload failed: ' . $t->getMessage()));
            }
        } else {
            $newSeoData['image'] = $existingSettings['image'] ?? '';
        }

        // If no existing settings, create new if data or image exists
        if (!$existingSettings) {
            if ($hasSeoData || $hasImage) {
                $result = $this->seoModel->createSeoSettings($newSeoData);
                if (!empty($result['error'])) {
                    $errors = $result['validation_errors'] ?? [];
                    throw new \Exception($result['message'] . (!empty($errors) ? ': ' . json_encode($errors) : ''));
                }
                // Get the inserted SEO ID for translations
                $seoId = $result['insert_id'] ?? null;
                if ($seoId) {
                    $this->processSeoTranslations($seoId, $translatedFields);
                }
            }
            return;
        }

        // If existing settings exist and all fields are cleared (and no new image), delete the record
        if ($existingSettings && $allFieldsCleared && !$hasImage && empty($existingSettings['image'])) {
            // Clean up SEO translations first
            if (!empty($existingSettings['id'])) {
                $this->translatedGeneralSeoModel->deleteSeoTranslations($existingSettings['id']);
            }
            $this->seoModel->delete($existingSettings['id']);
            return;
        }

        // Force clearing removed SEO fields
        $emptyDefaults = [
            'title' => '',
            'description' => '',
            'keywords' => '',
            'schema_markup' => '',
            'image' => $existingSettings['image'] ?? ''
        ];

        foreach ($emptyDefaults as $key => $defaultVal) {
            if (!array_key_exists($key, $newSeoData) || empty($newSeoData[$key])) {
                $newSeoData[$key] = $defaultVal;
            }
        }

        // Compare existing and new settings
        $settingsChanged = false;
        foreach ($newSeoData as $key => $value) {
            if ($key === 'page')
                continue; // Skip page comparison
            $existingValue = $existingSettings[$key] ?? '';
            $newValue = $value ?? '';
            if ($existingValue !== $newValue) {
                $settingsChanged = true;
                break;
            }
        }
        if (!$settingsChanged) {
            // Even if base SEO settings haven't changed, process translations
            $this->processSeoTranslations($existingSettings['id'], $translatedFields);
            return;
        }

        // Update existing settings with new data
        $result = $this->seoModel->updateSeoSettings($existingSettings['id'], $newSeoData);
        if (!empty($result['error'])) {
            $errors = $result['validation_errors'] ?? [];
            throw new \Exception($result['message'] . (!empty($errors) ? ': ' . json_encode($errors) : ''));
        }

        // Process SEO translations after updating base SEO settings
        $this->processSeoTranslations($existingSettings['id'], $translatedFields);
    }

    /**
     * Transform form data to translated fields structure.
     * Extracts SEO fields from form data (meta_title[lang], meta_description[lang], etc.)
     * and converts to structure: seo_title[lang], seo_description[lang], etc.
     *
     * @param array $postData POST data from form
     * @param string $defaultLanguage Default language code
     * @return array Transformed translated fields
     */
    private function transformFormDataToTranslatedFields(array $postData, string $defaultLanguage): array
    {
        $translatedFields = [
            'seo_title' => [],
            'seo_description' => [],
            'seo_keywords' => [],
            'seo_schema_markup' => []
        ];

        // Check if the data is already in the correct format (as objects with language keys)
        if (isset($postData['meta_title']) && is_array($postData['meta_title'])) {
            $translatedFields['seo_title'] = $postData['meta_title'] ?? [];
            $translatedFields['seo_description'] = $postData['meta_description'] ?? [];

            // Process keywords data properly - handle array structure with JSON strings
            $metaKeywords = $postData['meta_keywords'] ?? [];
            $processedKeywords = [];
            foreach ($metaKeywords as $langCode => $keywordsData) {
                if (is_array($keywordsData)) {
                    if (count($keywordsData) === 1 && is_string($keywordsData[0])) {
                        $processedKeywords[$langCode] = $keywordsData[0];
                    } else {
                        $processedKeywords[$langCode] = implode(',', $keywordsData);
                    }
                } else {
                    $processedKeywords[$langCode] = $keywordsData;
                }
            }
            $translatedFields['seo_keywords'] = $processedKeywords;
            $translatedFields['seo_schema_markup'] = $postData['schema_markup'] ?? [];

            return $translatedFields;
        }

        // Fallback: Process form data in the old format (field[language] format)
        $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], "", '0', 'id', 'ASC');

        foreach ($languages as $language) {
            $languageCode = $language['code'];

            $seoTitleField = 'meta_title[' . $languageCode . ']';
            if (array_key_exists($seoTitleField, $postData)) {
                $translatedFields['seo_title'][$languageCode] = trim((string) $postData[$seoTitleField]);
            }

            $seoDescriptionField = 'meta_description[' . $languageCode . ']';
            if (array_key_exists($seoDescriptionField, $postData)) {
                $translatedFields['seo_description'][$languageCode] = trim((string) $postData[$seoDescriptionField]);
            }

            $seoKeywordsField = 'meta_keywords[' . $languageCode . ']';
            if (array_key_exists($seoKeywordsField, $postData)) {
                $seoKeywordsValue = $postData[$seoKeywordsField];
                if (is_array($seoKeywordsValue)) {
                    if (count($seoKeywordsValue) === 1 && is_string($seoKeywordsValue[0])) {
                        $translatedFields['seo_keywords'][$languageCode] = $seoKeywordsValue[0];
                    } else {
                        $translatedFields['seo_keywords'][$languageCode] = implode(',', $seoKeywordsValue);
                    }
                } else {
                    $translatedFields['seo_keywords'][$languageCode] = trim((string) $seoKeywordsValue);
                }
            }

            $seoSchemaField = 'schema_markup[' . $languageCode . ']';
            if (array_key_exists($seoSchemaField, $postData)) {
                $translatedFields['seo_schema_markup'][$languageCode] = trim((string) $postData[$seoSchemaField]);
            }
        }

        return $translatedFields;
    }

    /**
     * Process SEO translations from form data.
     * Uses TranslatedGeneralSeoSettings_model for general SEO translations.
     *
     * @param int $seoId SEO settings ID from the seo_settings table
     * @param array|null $translatedFields Translated fields data
     */
    private function processSeoTranslations(int $seoId, ?array $translatedFields = null): void
    {
        try {
            if ($translatedFields === null) {
                $translatedFields = $this->request->getPost('translated_fields');
                if (is_string($translatedFields)) {
                    $translatedFields = json_decode($translatedFields, true);
                }
            }

            if (!empty($translatedFields) && is_array($translatedFields)) {
                // Restructure data for the model (convert field[lang] to lang[field] format)
                $restructuredData = $this->restructureTranslatedFieldsForSeoModel($translatedFields);

                // Process and store the SEO translations using general SEO translations model
                $seoTranslationResult = $this->translatedGeneralSeoModel->processSeoTranslations($seoId, $restructuredData);

                if (!$seoTranslationResult['success']) {
                    throw new \Exception('SEO Translation processing failed: ' . json_encode($seoTranslationResult['errors']));
                }
            }
        } catch (\Exception $e) {
            throw new \Exception('Exception in processSeoTranslations for custom page SEO ID ' . $seoId . ': ' . $e->getMessage());
        }
    }

    /**
     * Restructure translated fields from field[lang] format to lang[field] format.
     * Required for the model's processSeoTranslations method.
     *
     * @param array $translatedFields Translated fields in field[lang] format
     * @return array Restructured data in lang[field] format
     */
    private function restructureTranslatedFieldsForSeoModel(array $translatedFields): array
    {
        $restructured = [];
        $seoFields = ['seo_title', 'seo_description', 'seo_keywords', 'seo_schema_markup'];

        // Get all available languages from the translated fields
        $languages = [];
        foreach ($seoFields as $field) {
            if (isset($translatedFields[$field]) && is_array($translatedFields[$field])) {
                $languages = array_merge($languages, array_keys($translatedFields[$field]));
            }
        }
        $languages = array_unique($languages);

        // Restructure data: from field[lang] to lang[field]
        foreach ($languages as $languageCode) {
            $restructured[$languageCode] = [];

            foreach ($seoFields as $field) {
                $value = $translatedFields[$field][$languageCode] ?? '';

                if ($field === 'seo_keywords') {
                    $restructured[$languageCode][$field] = !empty($value)
                        ? $this->parseKeywords($value)
                        : '';
                } else {
                    $restructured[$languageCode][$field] = $value !== null
                        ? $value
                        : '';
                }
            }
        }

        return $restructured;
    }

    /**
     * Parse keywords/tags from Tagify or comma-separated string.
     * Handles HTML-encoded JSON strings from Tagify.
     *
     * @param mixed $input Input can be string, array, or HTML-encoded JSON string
     * @return string Comma-separated tag values
     */
    private function parseKeywords($input): string
    {
        if (empty($input)) {
            return '';
        }

        if (is_string($input)) {
            $decodedInput = html_entity_decode($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $jsonDecoded = json_decode($decodedInput, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($jsonDecoded)) {
                $tagValues = [];
                foreach ($jsonDecoded as $item) {
                    if (is_string($item)) {
                        $tagValues[] = trim($item);
                    } elseif (is_array($item) && isset($item['value'])) {
                        $tagValues[] = trim($item['value']);
                    }
                }
                return implode(', ', array_filter($tagValues));
            }
            return trim($input);
        }

        if (is_array($input)) {
            $tagValues = [];

            if (count($input) === 1 && is_string($input[0])) {
                $decodedString = html_entity_decode($input[0], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $jsonDecoded = json_decode($decodedString, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($jsonDecoded)) {
                    foreach ($jsonDecoded as $item) {
                        if (is_string($item)) {
                            $tagValues[] = trim($item);
                        } elseif (is_array($item) && isset($item['value'])) {
                            $tagValues[] = trim($item['value']);
                        }
                    }
                    return implode(', ', array_filter($tagValues));
                }
            }

            foreach ($input as $item) {
                if (is_string($item)) {
                    $decodedString = html_entity_decode($item, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $jsonDecoded = json_decode($decodedString, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($jsonDecoded)) {
                        foreach ($jsonDecoded as $jsonItem) {
                            if (is_string($jsonItem)) {
                                $tagValues[] = trim($jsonItem);
                            } elseif (is_array($jsonItem) && isset($jsonItem['value'])) {
                                $tagValues[] = trim($jsonItem['value']);
                            }
                        }
                    } else {
                        $tagValues[] = trim($item);
                    }
                } elseif (is_array($item)) {
                    if (isset($item['value'])) {
                        $tagValues[] = trim($item['value']);
                    }
                }
            }

            return implode(', ', array_filter($tagValues));
        }

        return '';
    }

    /**
     * Get the default language code from the database
     *
     * @return string The default language code
     */
    private function getDefaultLanguageCode(): string
    {
        $languages = fetch_details('languages', ['is_default' => 1], ['code']);
        return $languages[0]['code'] ?? 'en';
    }
}
