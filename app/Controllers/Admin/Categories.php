<?php

namespace App\Controllers\Admin;

use App\Models\Category_model;
use App\Models\Service_model;
use App\Models\Seo_model;
use App\Models\TranslatedCategoryDetails_model;
use App\Services\utility\FileService;
use App\Services\utility\SlugService;
use Exception;

class Categories extends Admin
{
    public $category, $validation, $seoModel, $slugService;
    protected $superadmin;
    protected $service;
    protected $translatedCategoryModel;
    protected FileService $fileService;

    public function __construct()
    {
        parent::__construct();
        $this->category = new Category_model();
        $this->validation = \Config\Services::validation();
        $this->service = new Service_model();
        $this->seoModel = new Seo_model();
        $this->translatedCategoryModel = new TranslatedCategoryDetails_model();
        $this->superadmin = $this->session->get('email');
        $this->slugService = new SlugService();
        $this->fileService = new FileService();
        helper('ResponceServices');
    }

    public function index()
    {
        setPageInfo($this->data, labels('categories', 'Categories') . ' | ' . labels('admin_panel', 'Admin Panel'), 'categories');

        // Get categories with translated names
        $this->data['categories'] = get_categories_with_translated_names();

        // Get parent categories with translated names
        $this->data['parent_categories'] = get_categories_with_translated_names(['parent_id' => 0]);

        // fetch languages
        $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ASC');
        $this->data['languages'] = $languages;
        return view('backend/admin/template', $this->data);
    }

    public function add_category()
    {
        try {
            // For categories, all languages are stored in the translations table.
            // We also store the *default language* name in the base table for backward compatibility and fallbacks.

            $rules = [
                'image' => ["rules" => 'uploaded[image]', "errors" => ["uploaded" => labels('please_select_an_image', "Please select an image"),]],
                'category_slug' => ["rules" => 'required|trim', "errors" => ["required" => labels('please_enter_slug_for_category', "Please enter slug for category"),]],
                'meta_title' => ["rules" => 'permit_empty', "errors" => ["permit_empty" => "Meta title is optional"]],
                'meta_description' => ["rules" => 'permit_empty', "errors" => ["permit_empty" => "Meta description is optional"]],
                'meta_keywords' => ["rules" => 'permit_empty', "errors" => ["permit_empty" => "Meta keywords are optional"]],
                'meta_image' => ["rules" => 'permit_empty|uploaded[meta_image]|is_image[meta_image]', "errors" => ["permit_empty" => "Meta image is optional", "uploaded" => "Invalid meta image", "is_image" => "Meta image must be a valid image"]],
                'schema_markup' => ["rules" => 'permit_empty', "errors" => ["permit_empty" => "Schema markup is optional"]],
            ];

            // Add validation for translated name fields
            // Get all available languages to validate name fields
            $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], "", '0', 'id', 'ASC');

            foreach ($languages as $language) {
                $languageCode = $language['code'];
                $isDefaultLanguage = $language['is_default'] == 1;

                // For default language, name is required
                if ($isDefaultLanguage) {
                    $rules["name.{$languageCode}"] = [
                        "rules" => 'required|trim',
                        "errors" => ["required" => "Name for default language ({$language['language']}) is required"]
                    ];
                }
            }
            $type = $this->request->getPost('make_parent');
            if (isset($type) && $type == "1") {
                $rules['parent_id'] = [
                    "rules" => 'required|trim',
                    "errors" => [
                        "required" => labels(PLEASE_SELECT_PARENT_CATEGORY, "Please select parent category")
                    ]
                ];
            }
            $this->validation->setRules($rules);

            // Run validation for all fields including translated fields
            if (!$this->validation->withRequest($this->request)->run()) {
                $errors = $this->validation->getErrors();
                return ErrorResponse($errors, true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Process translated name fields once so we can:
            // - store all languages in the translations table
            // - also store default language name into the base categories table for fallbacks
            $translatedFields = $this->processTranslatedFields($_POST);

            // Find default language code
            $defaultLanguage = get_default_language();
            $defaultName = $translatedFields['name'][$defaultLanguage] ?? '';

            $rawSlugInput = (string) $this->request->getPost('category_slug');
            $slug = $this->slugService->resolve(currentSlug: null, inputSlug: $rawSlugInput, fallbackName: $defaultName, table: 'categories', excludeId: null);

            $categoryImage = $this->request->getFile('image');

            $result = $this->fileService->upload($categoryImage, 'categories');
            if ($result['error']) {
                return ErrorResponse($result['message'], true, [], [], 200, csrf_token(), csrf_hash());
            }
            $url = $result['path'];
            $parent_id = !empty($_POST['parent_id']) ? (int) $_POST['parent_id'] : 0;
            // Store non-translatable fields + default language name in the main table
            // Default language name in base table improves backward compatibility and simple fallbacks.
            $data['name'] = $defaultName;
            $data['image'] = $url;
            $data['slug'] = $slug;
            $data['admin_commission'] = "0";
            $data['parent_id'] = $parent_id;
            $data['dark_color'] = $_POST['dark_theme_color'] != "#000000" ? $_POST['dark_theme_color'] : "#2A2C3E";
            $data['light_color'] = $_POST['light_theme_color'] != "#000000" ? $_POST['light_theme_color'] : "#FFFFFF";
            $data['status'] = 1;
            if ($this->category->save($data)) {
                $categoryId = $this->category->getInsertID();

                // Process and store translations for all languages
                try {
                    // Store translations using the helper function
                    $translationResult = $this->storeCategoryTranslations($categoryId, $translatedFields);

                    if (!$translationResult) {
                        log_message('warning', 'Some category translations may not have been saved for category ID: ' . $categoryId);
                    }
                } catch (\Exception $e) {
                    log_message('error', 'Exception in category translation processing: ' . $e->getMessage());
                    // Don't fail the category creation if translation processing fails
                    // The category is already created, so we just log the translation error
                }

                try {
                    $this->saveCategorySeoSettings($categoryId);
                } catch (\Throwable $th) {
                    log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Categories.php - add_category()');
                    return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
                }

                // Send notifications when new category is created/activated
                // NotificationService handles FCM, Email, and SMS notifications using templates
                // This unified approach sends notifications to all users (customers and providers)
                try {
                    // Get category name with translation support for default language
                    $defaultLanguage = get_default_language();
                    $categoryName = $this->category->getTranslatedCategoryName($categoryId, $defaultLanguage);

                    // If translation not found, try to get from POST data (default language name)
                    if (empty($categoryName)) {
                        $translatedFields = $this->processTranslatedFields($_POST);
                        $categoryName = $translatedFields[$defaultLanguage]['name'] ?? '';
                    }

                    // Fallback: if still empty, use a generic name
                    if (empty($categoryName)) {
                        $categoryName = 'New Category';
                    }

                    // Prepare context data for notification templates
                    // This context will be used to populate template variables like [[category_name]], [[category_id]], etc.
                    $notificationContext = [
                        'category_id' => $categoryId,
                        'category_name' => $categoryName
                    ];

                    // Queue all notifications (FCM, Email, SMS) to providers only
                    // NotificationService automatically handles:
                    // - Translation of templates based on user language
                    // - Variable replacement in templates
                    // - Notification settings checking for each channel
                    // - Fetching user email/phone/FCM tokens
                    // - Unsubscribe status checking for email
                    $result = queue_notification_service(
                        eventType: 'new_category_available',
                        recipients: [],
                        context: $notificationContext,
                        options: [
                            'channels' => ['fcm', 'email', 'sms'], // All channels handled by NotificationService
                            'user_groups' => [3], // Send only to providers (group_id 3)
                            'platforms' => ['android', 'ios', 'provider_panel'] // Provider platforms only
                        ]
                    );

                    // log_message('info', '[NEW_CATEGORY_AVAILABLE] Notification result: ' . json_encode($result));
                } catch (\Throwable $notificationError) {
                    // Log error but don't fail the category creation
                    // log_message('error', '[NEW_CATEGORY_AVAILABLE] Notification error: ' . $notificationError->getMessage());
                    log_message('error', '[NEW_CATEGORY_AVAILABLE] Notification error trace: ' . $notificationError->getTraceAsString());
                }

                return successResponse(labels(DATA_SAVED_SUCCESSFULLY, "Category added successfully"), false, ['category_id' => $categoryId], [], 200, csrf_token(), csrf_hash());
            } else {
                return ErrorResponse(labels(ERROR_OCCURED, "some error while addding category"), true, [], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Throwable $th) {
            // throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Categories.php - add_category()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
        $creator_id = $this->userId;
        $permission = is_permitted($creator_id, 'create', 'categories');
        if (!$permission) {
            return NoPermission();
        }
    }
    public function list()
    {
        try {
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
            $where = [];
            $from_app = false;
            if (isset($_POST['id']) && !empty($_POST['id'])) {
                $where['parent_id'] = $_POST['id'];
                $from_app = true;
            }
            $data = $this->category->list($from_app, $search, $limit, $offset, $sort, $order, $where);

            $decodedData = json_decode($data, true); // Decode JSON to array

            $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], "", '0', 'id', 'ASC');
            $seoTranslationModel = model('TranslatedCategorySeoSettings_model');

            foreach ($decodedData['rows'] as $key => $row) {
                $this->seoModel->setTableContext('categories');
                $categorySeoSettings = $this->seoModel->getSeoSettingsByReferenceId($row['id'], 'meta');

                $decodedData['rows'][$key]['meta_title'] = $categorySeoSettings['title'] ?? '';
                $decodedData['rows'][$key]['meta_description'] = $categorySeoSettings['description'] ?? '';
                $decodedData['rows'][$key]['meta_keywords'] = $categorySeoSettings['keywords'] ?? '';
                $decodedData['rows'][$key]['schema_markup'] = html_entity_decode($categorySeoSettings['schema_markup'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $decodedData['rows'][$key]['meta_image'] = $categorySeoSettings['image'] ?? '';

                // Raw image URL for modal preview
                $decodedData['rows'][$key]['raw_image'] = $this->fileService->url($row['image'] ?? '', 'categories', 'public/backend/assets/default.png');

                // Multilanguage name translations
                $categoryTranslations = $this->translatedCategoryModel->getAllTranslationsForCategory($row['id']);
                $existingTranslations = [];
                foreach ($categoryTranslations as $translation) {
                    $existingTranslations[$translation['language_code']] = html_entity_decode($translation['name'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
                $translatedData = [];
                foreach ($languages as $language) {
                    $code = $language['code'];
                    $isDefault = $language['is_default'] == 1;
                    if (isset($existingTranslations[$code])) {
                        $translatedData[$code] = ['name' => $existingTranslations[$code]];
                    } else {
                        $translatedData[$code] = ['name' => $isDefault ? html_entity_decode($row['name'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') : ''];
                    }
                }
                $decodedData['rows'][$key]['translations'] = $translatedData;

                // Multilanguage SEO translations
                $categorySeoTranslations = $seoTranslationModel->getAllTranslationsForCategory($row['id']);
                $seoTranslationsByLanguage = [];
                foreach ($categorySeoTranslations as $seoTranslation) {
                    $langCode = $seoTranslation['language_code'];
                    $seoTranslationsByLanguage[$langCode] = [
                        'seo_title' => $seoTranslation['seo_title'] ?? '',
                        'seo_description' => $seoTranslation['seo_description'] ?? '',
                        'seo_keywords' => $seoTranslation['seo_keywords'] ?? '',
                        'seo_schema_markup' => html_entity_decode($seoTranslation['seo_schema_markup'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    ];
                }
                $seoTranslatedData = [];
                foreach ($languages as $language) {
                    $code = $language['code'];
                    $isDefault = $language['is_default'] == 1;
                    if (isset($seoTranslationsByLanguage[$code])) {
                        $seoTranslatedData[$code] = $seoTranslationsByLanguage[$code];
                    } else {
                        $seoTranslatedData[$code] = [
                            'seo_title' => $isDefault ? ($decodedData['rows'][$key]['meta_title'] ?? '') : '',
                            'seo_description' => $isDefault ? ($decodedData['rows'][$key]['meta_description'] ?? '') : '',
                            'seo_keywords' => $isDefault ? ($decodedData['rows'][$key]['meta_keywords'] ?? '') : '',
                            'seo_schema_markup' => $isDefault ? ($decodedData['rows'][$key]['schema_markup'] ?? '') : '',
                        ];
                    }
                }
                $decodedData['rows'][$key]['seo_translations'] = $seoTranslatedData;
            }

            // Optionally re-encode to JSON if that's what you need downstream
            $data = json_encode($decodedData);

            if (isset($_POST['id']) && !empty($_POST['id'])) {
                if (!empty($data['data'])) {
                    return successResponse(labels(SUB_CATEGORIES_FETCHED_SUCCESSFULLY, "Sub Categories fetched successfully"), false, $data['data'], [], 200, csrf_token(), csrf_hash());
                } else {
                    return ErrorResponse(labels(DATA_NOT_FOUND, "Sub Categories not found on this category"), true, $data['data'], [], 200, csrf_token(), csrf_hash());
                }
            }

            return $data;
        } catch (\Throwable $th) {
            // throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Categories.php - list()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function get_categories()
    {
        try {
            $limit = $_GET['limit'] ?? 10;
            $offset = $_GET['offset'] ?? 0;
            $sort = $_GET['sort'] ?? 'id';
            $order = $_GET['order'] ?? 'ASC';
            $search = $_GET['search'] ?? '';
            $where = [];
            $fromApp = false;
            if (isset($_POST['id']) && !empty($_POST['id'])) {
                $where['parent_id'] = $_POST['id'];
                $fromApp = true;
            }
            // The list method now automatically uses translated names
            $data = $this->category->list($fromApp, $search, $limit, $offset, $sort, $order, $where);
            return $this->response->setJSON($data);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Categories.php - get_categories()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function update_category()
    {
        try {
            $type = $this->request->getPost('edit_make_parent');
            $rules = [
                'category_slug' => ["rules" => 'required|trim', "errors" => ["required" => labels(PLEASE_ENTER_SLUG_FOR_CATEGORY, "Please enter slug for category")]],
                'meta_title' => ["rules" => 'permit_empty', "errors" => ["permit_empty" => labels(META_TITLE_IS_OPTIONAL, "Meta title is optional")]],
                'meta_description' => ["rules" => 'permit_empty', "errors" => ["permit_empty" => labels(META_DESCRIPTION_IS_OPTIONAL, "Meta description is optional")]],
                'meta_keywords' => ["rules" => 'permit_empty', "errors" => ["permit_empty" => labels(META_KEYWORDS_ARE_OPTIONAL, "Meta keywords are optional")]],
                'meta_image' => ["rules" => 'permit_empty|uploaded[meta_image]|is_image[meta_image]', "errors" => ["permit_empty" => labels(META_IMAGE_IS_OPTIONAL, "Meta image is optional"), "uploaded" => labels(INVALID_META_IMAGE, "Invalid meta image"), "is_image" => labels(META_IMAGE_MUST_BE_A_VALID_IMAGE, "Meta image must be a valid image")]],
                'schema_markup' => ["rules" => 'permit_empty', "errors" => ["permit_empty" => labels(SCHEMA_MARKUP_IS_OPTIONAL, "Schema markup is optional")]],
            ];

            // Add validation for translated name fields
            // Get all available languages to validate name fields
            $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], "", '0', 'id', 'ASC');

            foreach ($languages as $language) {
                $languageCode = $language['code'];
                $isDefaultLanguage = $language['is_default'] == 1;

                // For default language, name is required
                if ($isDefaultLanguage) {
                    $rules["name.{$languageCode}"] = [
                        "rules" => 'required',
                        "errors" => ["required" => "Name for default language ({$language['language']}) is required"]
                    ];
                }
            }
            if (isset($type) && $type == "1") {
                $rules['edit_parent_id'] = ["rules" => 'required', "errors" => ["required" => labels(PLEASE_SELECT_PARENT_CATEGORY, "Please select parent category")]];
            }
            $this->validation->setRules($rules);

            if (!$this->validation->withRequest($this->request)->run()) {
                $errors = $this->validation->getErrors();
                return ErrorResponse($errors, true, [], [], 200, csrf_token(), csrf_hash());
            }

            $id = $this->request->getPost('id');

            // Process translated name fields once so we can:
            // - store all languages in the translations table
            // - also keep default language name in the base categories table
            $translatedFields = $this->processTranslatedFields($_POST);

            // Resolve default language code and name
            $defaultLanguage = get_default_language();
            $defaultName = $translatedFields['name'][$defaultLanguage] ?? '';
            $oldData = fetch_details('categories', ['id' => $id]);
            $oldImage = $oldData[0]['image'];

            // When a category already has subcategories, prevent switching it to a subcategory.
            $existingSubcategories = fetch_details('categories', ['parent_id' => $id], ['id']);
            $hasSubcategories = !empty($existingSubcategories);

            $currentParentId = $oldData[0]['parent_id']; // 0 = top-level

            // Block ONLY when converting a parent category (with children) into a subcategory
            if (
                $type == "1" &&          // user wants to make it a subcategory
                $currentParentId == 0 && // currently a top-level category
                $hasSubcategories        // it already has children
            ) {
                return ErrorResponse(labels('cannot_convert_parent_category_with_children', 'This category already has subcategories, so it cannot be converted into a subcategory.'), true, [], [], 200, csrf_token(), csrf_hash());
            }

            $image = $this->request->getFile('image');
            $imageName = !empty($image) && $image->getName() != "" ? $image->getName() : $oldImage;

            $existingSlug = $oldData[0]['slug'] ?? null;
            $resolvedSlug = $this->slugService->resolve(
                currentSlug: $existingSlug,
                inputSlug: $this->request->getPost('category_slug'),
                fallbackName: $defaultName,
                table: 'categories',
                excludeId: $id
            );

            if ($this->slugService->isLegacySlug($existingSlug)) {
                $resolvedSlug = $this->slugService->generate(
                    $defaultName,
                    $defaultName,
                    'categories',
                    $id
                );
            }

            $data = [
                'parent_id' => $type == "1" ? $this->request->getPost(('edit_parent_id')) : "0",
                'admin_commission' => "0",
                'dark_color' => $_POST['edit_dark_theme_color'],
                'light_color' => $_POST['edit_light_theme_color'],
                'status' => 1,
                'slug' => $resolvedSlug,
            ];
            // Keep default language name in base table so existing consumers
            // depending on categories.name continue to work safely.
            $data['name'] = $defaultName;

            $categoryImage = $this->request->getFile('image');

            if ($categoryImage && $categoryImage->isValid() && !$categoryImage->hasMoved()) {
                $result = $this->fileService->replace($oldImage ?: null, $categoryImage, 'categories');
                if ($result['error']) {
                    return ErrorResponse($result['message'], true, [], [], 200, csrf_token(), csrf_hash());
                }
                $imageName = $result['path'];
            } else {
                $imageName = $oldImage;
            }

            $data['image'] = $imageName;
            $upd = $this->category->update($id, $data);
            if ($upd) {
                // Process and store translations for all languages
                try {
                    // Store translations using the helper function
                    $translationResult = $this->storeCategoryTranslations($id, $translatedFields);

                    if (!$translationResult) {
                        log_message('warning', 'Some category translations may not have been updated for category ID: ' . $id);
                    }
                } catch (\Exception $e) {
                    log_message('error', 'Exception in category translation processing during update: ' . $e->getMessage());
                    // Don't fail the category update if translation processing fails
                    // The category is already updated, so we just log the translation error
                }

                try {
                    $this->saveCategorySeoSettings($id);
                } catch (\Throwable $th) {
                    log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Admin/Categories.php - update_category()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
                    return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
                }
                return successResponse(labels(DATA_UPDATED_SUCCESSFULLY, "Category updated successfully"), false, [], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Admin/Categories.php - update_category()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function remove_category()
    {
        try {
            $id = $this->request->getPost('user_id');
            $db = \Config\Database::connect();
            $builder = $db->table('categories');
            $cart_builder = $db->table('cart');
            $icons = fetch_details('categories', ['id' => $id]);
            $subcategories = fetch_details('categories', ['parent_id' => $id], ['id', 'name']);
            $services = fetch_details('services', ['category_id' => $id], ['id']);

            // Stop deletion when live services still depend on this category.
            // This preserves service/category mappings and avoids orphaned services.
            if (!empty($services)) {
                return ErrorResponse(
                    labels('category_has_services_delete_not_allowed', 'This category has services assigned. Please reassign or remove those services before deleting the category.'),
                    true,
                    [],
                    [],
                    200,
                    csrf_token(),
                    csrf_hash()
                );
            }

            foreach ($subcategories as $sb) {
                $sb['status'] = 0;
                $this->category->update($sb['id'], $sb);
            }
            foreach ($services as $s) {
                $s['status'] = 0;
                $this->service->update($s['id'], $s);
                $cart_builder->delete(['service_id' => $s['id']]);
            }
            $categoryImage = $icons[0]['image'];

            // Clean up SEO settings and images before deleting category
            $this->seoModel->cleanupSeoData($id, 'categories');

            // Get category name with translation support before deleting translations
            // This is needed for notification templates
            $defaultLanguage = get_default_language();
            $categoryName = $this->category->getTranslatedCategoryName($id, $defaultLanguage);

            // Fallback: if translation not found, try to get from main table
            if (empty($categoryName) && !empty($icons[0]['name'])) {
                $categoryName = $icons[0]['name'];
            }

            // Final fallback: if still empty, use a generic name
            if (empty($categoryName)) {
                $categoryName = 'Category';
            }

            // Send notifications when category is removed/deleted
            // NotificationService handles FCM, Email, and SMS notifications using templates
            // This unified approach sends notifications only to providers
            try {

                // Prepare context data for notification templates
                // This context will be used to populate template variables like [[category_name]], [[category_id]], etc.
                $notificationContext = [
                    'category_id' => $id,
                    'category_name' => $categoryName
                ];

                // Send all notifications (FCM, Email, SMS) to providers only
                // NotificationService automatically handles:
                // - Translation of templates based on user language
                // - Variable replacement in templates
                // - Notification settings checking for each channel
                // - Fetching user email/phone/FCM tokens
                // - Unsubscribe status checking for email
                $result = queue_notification_service(
                    eventType: 'category_removed',
                    recipients: [],
                    context: $notificationContext,
                    options: [
                        'channels' => ['fcm', 'email', 'sms'], // All channels handled by NotificationService
                        'user_groups' => [3], // Send only to providers (group_id 3)
                        'platforms' => ['android', 'ios', 'provider_panel'] // Provider platforms only
                    ]
                );

                // log_message('info', '[CATEGORY_REMOVED] Notification result: ' . json_encode($result));
            } catch (\Throwable $notificationError) {
                // Log error but don't fail the category deletion
                log_message('error', '[CATEGORY_REMOVED] Notification error: ' . $notificationError->getMessage());
                log_message('error', '[CATEGORY_REMOVED] Notification error trace: ' . $notificationError->getTraceAsString());
            }

            // Clean up category translations before deleting category
            try {
                $this->translatedCategoryModel->deleteCategoryTranslations($id);
            } catch (\Exception $e) {
                log_message('error', 'Failed to delete category translations for category ID ' . $id . ': ' . $e->getMessage());
                // Continue with category deletion even if translation cleanup fails
            }

            if ($builder->delete(['id' => $id])) {
                $this->fileService->delete('categories', $categoryImage);
                return successResponse(labels(DATA_DELETED_SUCCESSFULLY, "Category Removed successfully"), false, [], [], 200, csrf_token(), csrf_hash());
            }
            return ErrorResponse(labels(ERROR_OCCURED, "An error occured"), true, [], [], 200, csrf_token(), csrf_hash());
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Categories.php - remove_category()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    /**
     * Remove SEO image for a category
     * This method handles AJAX requests to remove SEO images
     * @return \CodeIgniter\HTTP\Response
     */
    public function remove_seo_image()
    {
        try {
            $categoryId = $this->request->getPost('category_id');
            $seoId = $this->request->getPost('seo_id');

            if (!$categoryId) {
                return ErrorResponse(labels('category_id_is_required', 'Category ID is required'), true, [], [], 200, csrf_token(), csrf_hash());
            }
            // Set SEO model context for categories
            $this->seoModel->setTableContext('categories');

            // Get existing SEO settings
            $existingSettings = $this->seoModel->getSeoSettingsByReferenceId($categoryId);

            if (!$existingSettings) {
                return ErrorResponse(labels(DATA_NOT_FOUND, 'SEO settings not found for this category'), true, [], [], 200, csrf_token(), csrf_hash());
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
                'category_id' => $categoryId
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
                $this->fileService->delete('category_seo_settings', $imageToDelete);
            }

            return successResponse(labels(SEO_IMAGE_REMOVED_SUCCESSFULLY, 'SEO image removed successfully'), false, [], [], 200, csrf_token(), csrf_hash());
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Categories.php - remove_seo_image()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    /**
     * Save SEO settings for a category
     * 
     * This method handles both creating new SEO settings and updating existing ones.
     * It properly handles empty field updates by comparing against original POST data
     * rather than processed data that may have empty fields removed.
     * 
     * @param int $categoryId - The category ID to save SEO settings for
     */
    private function saveCategorySeoSettings(int $categoryId): void
    {
        try {
            // Get default language for base SEO data
            $defaultLanguage = get_default_language();

            // Get all POST data for extracting SEO data if needed
            $allPostData = $this->request->getPost();

            // Get multilingual SEO data from form - try both approaches
            $translatedFields = $this->request->getPost('translated_fields');

            // If translated fields are provided as JSON string, decode it
            if (is_string($translatedFields)) {
                $translatedFields = json_decode($translatedFields, true);
            }

            // If translated_fields is null/empty, extract SEO data directly from POST data
            if (empty($translatedFields)) {
                $translatedFields = $this->extractSeoDataFromPost($allPostData);
            }

            // Extract default language SEO data for base SEO settings
            $defaultSeoTitle = '';
            $defaultSeoDescription = '';
            $defaultSeoKeywords = '';
            $defaultSeoSchema = '';

            // Try to get SEO data from translated_fields first (multilingual approach)
            if (!empty($translatedFields['seo_title'][$defaultLanguage])) {
                $defaultSeoTitle = trim($translatedFields['seo_title'][$defaultLanguage]);
            } elseif ($this->request->getPost('meta_title')) {
                // Fallback to single-language field for backward compatibility
                $metaTitlePost = $this->request->getPost('meta_title');
                // Handle array case (multilanguage form) - use first value or default language value
                if (is_array($metaTitlePost)) {
                    $defaultSeoTitle = trim((string) ($metaTitlePost[$defaultLanguage] ?? (reset($metaTitlePost) ?: '')));
                } else {
                    $defaultSeoTitle = trim((string) $metaTitlePost);
                }
            }

            if (!empty($translatedFields['seo_description'][$defaultLanguage])) {
                $defaultSeoDescription = trim($translatedFields['seo_description'][$defaultLanguage]);
            } elseif ($this->request->getPost('meta_description')) {
                // Fallback to single-language field for backward compatibility
                $metaDescriptionPost = $this->request->getPost('meta_description');
                // Handle array case (multilanguage form) - use first value or default language value
                if (is_array($metaDescriptionPost)) {
                    $defaultSeoDescription = trim((string) ($metaDescriptionPost[$defaultLanguage] ?? (reset($metaDescriptionPost) ?: '')));
                } else {
                    $defaultSeoDescription = trim((string) $metaDescriptionPost);
                }
            }

            if (!empty($translatedFields['seo_keywords'][$defaultLanguage])) {
                $keywordsData = $translatedFields['seo_keywords'][$defaultLanguage];

                // Handle different data structures for keywords
                if (is_array($keywordsData)) {
                    // If it's an array, it might contain JSON strings or direct values
                    if (count($keywordsData) === 1 && is_string($keywordsData[0])) {
                        // Single JSON string in array format
                        $defaultSeoKeywords = $keywordsData[0];
                    } else {
                        // Multiple values, join them
                        $defaultSeoKeywords = implode(',', $keywordsData);
                    }
                } else {
                    // Direct string value
                    $defaultSeoKeywords = $keywordsData;
                }
            } elseif ($this->request->getPost('meta_keywords')) {
                // Fallback to single-language field for backward compatibility
                $metaKeywordsPost = $this->request->getPost('meta_keywords');
                // Handle array case (multilanguage form) - could be meta_keywords[lang][] format
                if (is_array($metaKeywordsPost)) {
                    // Check if it's indexed by language code
                    if (isset($metaKeywordsPost[$defaultLanguage])) {
                        $defaultSeoKeywords = $metaKeywordsPost[$defaultLanguage];
                    } else {
                        // Use first available value
                        $defaultSeoKeywords = reset($metaKeywordsPost) ?: '';
                    }
                } else {
                    $defaultSeoKeywords = $metaKeywordsPost;
                }
            }

            if (!empty($translatedFields['seo_schema_markup'][$defaultLanguage])) {
                $defaultSeoSchema = trim($translatedFields['seo_schema_markup'][$defaultLanguage]);
            } elseif ($this->request->getPost('schema_markup')) {
                // Fallback to single-language field for backward compatibility
                $schemaMarkupPost = $this->request->getPost('schema_markup');
                // Handle array case (multilanguage form) - use first value or default language value
                if (is_array($schemaMarkupPost)) {
                    $defaultSeoSchema = trim((string) ($schemaMarkupPost[$defaultLanguage] ?? (reset($schemaMarkupPost) ?: '')));
                } else {
                    $defaultSeoSchema = trim((string) $schemaMarkupPost);
                }
            }

            // Parse meta keywords (Tagify or comma-separated)
            $keywords = $defaultSeoKeywords ? $this->parseKeywords($defaultSeoKeywords) : '';

            // Build SEO data array for base table (uses default language values)
            $seoData = [
                'title' => $defaultSeoTitle,
                'description' => $defaultSeoDescription,
                'keywords' => $keywords,
                'schema_markup' => $defaultSeoSchema,
                'category_id' => $categoryId,
            ];

            // Check if any SEO field is filled (excluding category_id)
            $hasSeoData = array_filter($seoData, fn($v) => !empty($v) && $v !== $categoryId);

            // Check if all SEO fields are intentionally cleared
            $allFieldsCleared = empty($seoData['title']) &&
                empty($seoData['description']) &&
                empty($seoData['keywords']) &&
                empty($seoData['schema_markup']);

            // Handle SEO image upload
            $seoImage = $this->request->getFile('meta_image');
            $hasImage = $seoImage && $seoImage->isValid();

            // Set table context to categories
            $this->seoModel->setTableContext('categories');
            $existingSettings = $this->seoModel->getSeoSettingsByReferenceId($categoryId);

            $newSeoData = $seoData;
            if ($hasImage) {
                $uploadResult = $this->fileService->upload($seoImage, 'category_seo_settings');
                if ($uploadResult['error']) {
                    throw new Exception(labels(SEO_IMAGE_UPLOAD_FAILED, "SEO image upload failed: " . $uploadResult['message']));
                }
                $newSeoData['image'] = $uploadResult['path'];
            } else {
                $newSeoData['image'] = $existingSettings['image'] ?? '';
            }

            // If no existing settings, create new if data or image exists
            if (!$existingSettings) {
                if ($hasSeoData || $hasImage) {
                    $result = $this->seoModel->createSeoSettings($newSeoData);
                    if (!empty($result['error'])) {
                        $errors = $result['validation_errors'] ?? [];
                        throw new Exception($result['message'] . (!empty($errors) ? ': ' . json_encode($errors) : ''));
                    }
                }

                // Process SEO translations after creating base SEO settings
                $this->processSeoTranslations($categoryId, $translatedFields);
                return;
            }

            // If existing settings exist and all fields are cleared (and no new image), delete the record
            // BUT: If there's an existing image, we should NOT delete the record even if all other fields are empty
            // This preserves the SEO record structure for future use
            if ($existingSettings && $allFieldsCleared && !$hasImage && empty($existingSettings['image'])) {
                $result = $this->seoModel->delete($existingSettings['id']);
                if ($result) {
                    // Clean up old image if it exists
                    if (!empty($existingSettings['image'])) {
                        $this->fileService->delete('category_seo_settings', $existingSettings['image']);
                    }
                }
                // Also clean up SEO translations when deleting base SEO settings
                $this->cleanupSeoTranslations($categoryId);
                return;
            }

            // Compare existing and new settings
            $settingsChanged = false;
            foreach ($newSeoData as $key => $value) {
                $existingValue = $existingSettings[$key] ?? '';
                $newValue = $value ?? '';
                if ($existingValue !== $newValue) {
                    $settingsChanged = true;
                    break;
                }
            }

            // Also check if a new image was uploaded (this forces an update)
            if (!$settingsChanged && $hasImage) {
                $settingsChanged = true;
            }

            // Even if base SEO settings haven't changed, process translations
            // (translations might have changed even if default language hasn't)
            if (!$settingsChanged) {
                // Process translations even if base SEO hasn't changed
                $this->processSeoTranslations($categoryId, $translatedFields);
                return;
            }

            // Update existing settings with new data
            $result = $this->seoModel->updateSeoSettings($existingSettings['id'], $newSeoData);
            if (!empty($result['error'])) {
                $errors = $result['validation_errors'] ?? [];
                throw new Exception($result['message'] . (!empty($errors) ? ': ' . json_encode($errors) : ''));
            }

            // Process SEO translations after updating base SEO settings
            $this->processSeoTranslations($categoryId, $translatedFields);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Categories.php - saveCategorySeoSettings()');
            throw $th; // Re-throw to handle in add_category
        }
    }

    private function parseKeywords($input): string
    {
        // If input is empty, return empty string
        if (empty($input)) {
            return '';
        }

        // If input is a string, it might be JSON or comma-separated
        if (is_string($input)) {
            // Check if it's a JSON string
            if (json_decode($input, true) !== null) {
                $decoded = json_decode($input, true);
                if (is_array($decoded)) {
                    // Handle array of objects (e.g., [{value: "tag1"}, {value: "tag2"}])
                    $tags = array_map(function ($item) {
                        return is_array($item) && isset($item['value']) ? trim($item['value']) : trim($item);
                    }, $decoded);
                    return implode(',', $tags);
                }
            }
            // Treat as comma-separated string
            return trim($input);
        }

        // If input is an array
        if (is_array($input)) {
            // Handle case where array contains a single JSON string (e.g., ['[{value: "tag1"}, {value: "tag2"}]'])
            if (count($input) === 1 && is_string($input[0]) && json_decode($input[0], true) !== null) {
                $decoded = json_decode($input[0], true);
                if (is_array($decoded)) {
                    $tags = array_map(function ($item) {
                        return is_array($item) && isset($item['value']) ? trim($item['value']) : trim($item);
                    }, $decoded);
                    return implode(',', $tags);
                }
            }
            // Handle array of objects (e.g., [{value: "tag1"}, {value: "tag2"}])
            $tags = array_map(function ($item) {
                return is_array($item) && isset($item['value']) ? trim($item['value']) : trim($item);
            }, $input);
            return implode(',', $tags);
        }

        // Fallback: return empty string for unexpected input
        return '';
    }

    /**
     * Process translated fields from form data
     * 
     * This method extracts translated fields from POST data and organizes them
     * into a structured format for the translation service
     * 
     * @param array $postData POST data from the form
     * @return array Structured translated fields data
     */
    private function processTranslatedFields(array $postData): array
    {
        $translatedFields = [
            'name' => [],
        ];

        // Check if the data is already in the correct format (as objects with language keys)
        if (isset($postData['name']) && is_array($postData['name'])) {
            // Copy the data directly since it's already in the right structure
            $translatedFields['name'] = $postData['name'] ?? [];

            return $translatedFields;
        }

        // Fallback: Process form data in the old format (field[language] format)
        // Get languages from database
        $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], "", '0', 'id', 'ASC');

        foreach ($languages as $language) {
            $languageCode = $language['code'];

            // Process name
            $nameField = 'name[' . $languageCode . ']';
            $nameValue = $postData[$nameField] ?? null;
            if (!empty($nameValue)) {
                $translatedFields['name'][$languageCode] = trim($nameValue);
            }
        }

        return $translatedFields;
    }

    /**
     * Store category translations in the database
     * 
     * This private helper function uses the TranslatedCategoryDetails_model
     * to store multi-language translations for category fields
     * 
     * @param int $categoryId The category ID to store translations for
     * @param array $translatedFields Array containing translated data organized by field and language
     * @return bool Success status
     * @throws Exception If translation storage fails
     */
    private function storeCategoryTranslations(int $categoryId, array $translatedFields): bool
    {
        try {
            // Validate that we have a valid category ID
            if (empty($categoryId)) {
                throw new Exception(labels('category_id_is_required_for_storing_translations', 'Category ID is required for storing translations'));
            }

            // Get all available languages from database
            $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], "", '0', 'id', 'ASC');

            if (empty($languages)) {
                throw new Exception(labels('no_languages_found_in_database', 'No languages found in database'));
            }

            $successCount = 0;
            $totalLanguages = count($languages);

            // Process each language
            foreach ($languages as $language) {
                $languageCode = $language['code'];

                // Prepare translation data for this language
                $translationData = [];

                // Add name translation if available
                if (isset($translatedFields['name'][$languageCode]) && !empty(trim($translatedFields['name'][$languageCode]))) {
                    $translationData['name'] = trim($translatedFields['name'][$languageCode]);
                }

                // Only save if we have translation data for this language
                if (!empty($translationData)) {
                    $result = $this->translatedCategoryModel->saveTranslatedDetails(
                        $categoryId,
                        $languageCode,
                        $translationData
                    );

                    if ($result) {
                        $successCount++;
                    } else {
                        // Log the failure but continue with other languages
                        log_message('error', "Failed to save translation for category {$categoryId}, language {$languageCode}");
                    }
                } else {
                    // Count as success if no data to save (empty translations are valid)
                    $successCount++;
                }
            }

            // Consider it successful if we processed all languages
            // Even if some translations were empty (which is valid)
            return $successCount === $totalLanguages;
        } catch (\Exception $e) {
            log_message('error', 'Exception in storeCategoryTranslations: ' . $e->getMessage());
            throw new Exception(labels('failed_to_store_category_translations', 'Failed to store category translations: ' . $e->getMessage()));
        }
    }

    /**
     * Extract SEO data from POST data for multilanguage support
     * 
     * Converts form field names (meta_title, meta_description, etc.) to SEO field names
     * and organizes them by language code
     * 
     * @param array $postData POST data from the form
     * @return array Structured SEO data organized by field and language
     */
    private function extractSeoDataFromPost(array $postData): array
    {
        $seoData = [];

        // SEO fields mapping from form field names to SEO field names
        $seoFields = [
            'meta_title' => 'seo_title',
            'meta_description' => 'seo_description',
            'meta_keywords' => 'seo_keywords',
            'schema_markup' => 'seo_schema_markup'
        ];

        foreach ($seoFields as $formField => $seoField) {
            if (isset($postData[$formField]) && is_array($postData[$formField])) {
                foreach ($postData[$formField] as $languageCode => $value) {
                    // Skip invalid language codes
                    if (empty($languageCode) || $languageCode === '0') {
                        continue;
                    }

                    if ($seoField === 'seo_keywords') {
                        // Process keywords using parseKeywords function
                        $processedValue = $this->parseKeywords($value);
                        $seoData[$seoField][$languageCode] = $processedValue;
                    } else {
                        // For other SEO fields, just trim the value
                        $processedValue = trim($value);
                        $seoData[$seoField][$languageCode] = $processedValue;
                    }
                }
            }
        }
        return $seoData;
    }

    /**
     * Process SEO translations from form data
     * 
     * Handles saving multilanguage SEO translations for categories
     * Converts field[lang] format to lang[field] format and saves via model
     * 
     * @param int $categoryId The category ID
     * @param array|null $translatedFields Translated fields data (optional, will get from POST if null)
     * @return void
     * @throws Exception If SEO translation processing fails
     */
    private function processSeoTranslations(int $categoryId, ?array $translatedFields = null): void
    {
        try {
            // Use provided translated fields or get from POST request (fallback)
            if ($translatedFields === null) {
                $translatedFields = $this->request->getPost('translated_fields');

                // If translated fields are provided as JSON string, decode it
                if (is_string($translatedFields)) {
                    $translatedFields = json_decode($translatedFields, true);
                }
            }

            // Process SEO translations if data is provided
            if (!empty($translatedFields) && is_array($translatedFields)) {
                // Load the SEO translation model
                $seoTranslationModel = model('TranslatedCategorySeoSettings_model');

                // Restructure data for the model (convert field[lang] to lang[field] format)
                $restructuredData = $this->restructureTranslatedFieldsForSeoModel($translatedFields);

                // Process and store the SEO translations
                $seoTranslationResult = $seoTranslationModel->processSeoTranslations($categoryId, $restructuredData);

                // Check if SEO translation processing was successful
                if (!$seoTranslationResult['success']) {
                    throw new Exception('SEO Translation processing failed: ' . json_encode($seoTranslationResult['errors']));
                }
            }
        } catch (\Exception $e) {
            // Log any exceptions but don't fail the operation
            throw new Exception('Exception in processSeoTranslations for category ' . $categoryId . ': ' . $e->getMessage());
        }
    }

    /**
     * Clean up SEO translations when base SEO settings are deleted
     * 
     * Removes all multilanguage SEO translations for a category when the base SEO record is deleted
     * 
     * @param int $categoryId The category ID
     * @return void
     * @throws Exception If cleanup fails
     */
    private function cleanupSeoTranslations(int $categoryId): void
    {
        try {
            // Load the SEO translation model
            $seoTranslationModel = model('TranslatedCategorySeoSettings_model');

            // Delete all SEO translations for this category
            $result = $seoTranslationModel->deleteCategorySeoTranslations($categoryId);
        } catch (\Exception $e) {
            // Log any exceptions but don't fail the operation
            throw new Exception('Exception in cleanupSeoTranslations for category ' . $categoryId . ': ' . $e->getMessage());
        }
    }

    /**
     * Restructure translated fields for SEO model
     * Convert from field[lang] format to lang[field] format
     * 
     * The model expects data in lang[field] format, but form data comes in field[lang] format
     * This method restructures the data to match what the model expects
     * 
     * @param array $translatedFields Translated fields in field[lang] format
     * @return array Restructured data in lang[field] format
     */
    private function restructureTranslatedFieldsForSeoModel(array $translatedFields): array
    {
        $restructured = [];

        // SEO fields we want to process
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

            // Keep the language entry even if every field is empty.
            // This allows the translation model to overwrite stale values with blanks
            // when user intentionally clears SEO fields for a specific language.
        }

        return $restructured;
    }

    public function get_all_for_select()
    {
        try {
            $categories = get_categories_with_translated_names();
            return $this->response->setJSON(array_values(array_map(fn($c) => is_array($c) ? $c : (array) $c, $categories)));
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Categories.php - get_all_for_select()');
            return $this->response->setJSON([]);
        }
    }
}
