<?php

namespace App\Controllers\Admin;

use Config\ApiResponseAndNotificationStrings;
use App\Models\Blog_category_model;
use App\Models\Blog_model;
use App\Models\Seo_model;
use App\Models\TranslatedBlogCategoryDetailsModel;
use App\Models\TranslatedBlogDetailsModel;
use App\Models\TranslatedBlogSeoSettings_model;
use App\Services\utility\FileService;
use App\Services\utility\SlugService;

class Blog extends Admin
{
    public $category, $validation, $db, $ionAuth, $creator_id, $service, $blog, $slugService;
    protected FileService $fileService;
    protected $superadmin;
    protected ApiResponseAndNotificationStrings $trans;
    protected Seo_model $seoModel;
    protected TranslatedBlogCategoryDetailsModel $translatedBlogCategoryModel;
    protected TranslatedBlogDetailsModel $translatedBlogModel;
    protected TranslatedBlogSeoSettings_model $translatedBlogSeoModel;

    public function __construct()
    {
        parent::__construct();
        $this->category = new Blog_category_model();
        $this->blog = new Blog_model();
        $this->translatedBlogCategoryModel = new TranslatedBlogCategoryDetailsModel();
        $this->translatedBlogModel = new TranslatedBlogDetailsModel();
        $this->translatedBlogSeoModel = new TranslatedBlogSeoSettings_model();
        $this->db = \Config\Database::connect();
        $this->ionAuth = new \App\Libraries\CustomIonAuth();
        $this->creator_id = $this->userId;
        $this->superadmin = $this->session->get('email');
        $this->trans = new ApiResponseAndNotificationStrings();
        $this->seoModel = new Seo_model();
        $this->slugService = new SlugService();
        $this->fileService = new FileService();
        helper('ResponceServices');
    }

    public function index()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            setPageInfo($this->data, labels('blog', 'Blog') . ' | ' . labels('admin_panel', 'Admin Panel'), 'blog');
            // Use the same approach as add_service - get categories with translated names for current system language
            $this->data['categories_name'] = get_categories_with_translated_names();
            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Services.php - index()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function add_blog_view()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            $permission = is_permitted($this->creator_id, 'create', 'blog');
            if (!$permission) {
                return NoPermission();
            }
            setPageInfo($this->data, labels('add_blog', 'Add Blog') . ' | ' . labels('admin_panel', 'Admin Panel'), 'add_blog');
            $this->data['data'] = fetch_details('users', ['id' => $this->userId])[0];
            // Get categories with translations for all languages for dropdown
            // This allows JavaScript to switch between languages without AJAX calls
            $allLanguages = fetch_details('languages', [], ['code']);
            $categoriesData = [];

            foreach ($allLanguages as $lang) {
                $categoriesData[$lang['code']] = $this->category->getCategoriesWithTranslations($lang['code']);
            }

            // Set categories for current system language for initial load
            $currentLanguage = get_current_language(); // Use current system language instead of default
            $this->data['categories_name'] = $categoriesData[$currentLanguage] ?? $categoriesData[$this->getDefaultLanguageCode()];

            // Add languages for translation support (following blog categories pattern)
            $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ACE');
            $this->data['languages'] = $languages;

            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Services.php - add_service_view()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function add_blog()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            if (isset($_POST) && !empty($_POST)) {
                // Validate main blog fields using Blog_model rules
                $rules = $this->blog->getValidationRules();
                $validation_messages = $this->blog->getValidationMessages();

                if (!$this->validate($rules, $validation_messages)) {
                    return $this->response->setJSON([
                        'error' => true,
                        'message' => $this->validator->getErrors(),
                        'csrfName' => csrf_token(),
                        'csrfHash' => csrf_hash()
                    ]);
                }

                // Get translation data from form (following blog categories pattern)
                $titles = $this->request->getPost('title') ?? [];
                $shortDescriptions = $this->request->getPost('short_description') ?? [];
                $descriptions = $this->request->getPost('description') ?? [];

                // Get default language to use for main table
                $defaultLanguage = $this->getDefaultLanguageCode();

                // Get tags data - handle Tagify JSON format and other formats
                $defaultTagsInput = $this->request->getPost('tags[' . $defaultLanguage . ']') ?? [];
                $defaultTags = $this->parseKeywords($defaultTagsInput);

                // Validate that default language has required fields
                $defaultTitle = isset($titles[$defaultLanguage]) ? trim($titles[$defaultLanguage]) : '';
                $defaultDescription = isset($descriptions[$defaultLanguage]) ? trim($descriptions[$defaultLanguage]) : '';

                if (empty($defaultTitle)) {
                    return ErrorResponse(labels("title_in_default_language_is_required", "Title in default language is required"), true, [], [], 200, csrf_token(), csrf_hash());
                }

                if (empty($defaultDescription)) {
                    return ErrorResponse(labels("description_in_default_language_is_required", "Description in default language is required"), true, [], [], 200, csrf_token(), csrf_hash());
                }

                if (empty($defaultTags)) {
                    return ErrorResponse(labels("tags_in_default_language_are_required", "Tags in default language are required"), true, [], [], 200, csrf_token(), csrf_hash());
                }

                // Handle blog image upload
                $image = '';
                $blogImageFile = $this->request->getFile('blog_image_selector');
                if ($blogImageFile && $blogImageFile->isValid()) {
                    $result = $this->fileService->upload($blogImageFile, 'blogs/images');
                    if ($result['error']) {
                        return ErrorResponse($result['message'], true, [], [], 200, csrf_token(), csrf_hash());
                    }
                    $image = $result['path'];
                }

                // Generate slug using English content if available, otherwise use blog category logic
                $slug = $this->slugService->resolve(
                    currentSlug:null, 
                    inputSlug:$this->request->getPost('slug'), 
                    fallbackName: $defaultTitle, 
                    table:'blogs', 
                    excludeId:null);

                // Prepare blog data for main table
                // Store default language translatable fields in base table (following Services pattern)
                // All translations (including default) will also be stored in translations table
                $blogData = [
                    'category_id' => $this->request->getPost('category_id'),
                    'image' => $image,
                    'slug' => $slug,
                    // Store default language values in main table for quick access and fallback
                    'title' => $this->removeScript(trim($defaultTitle)),
                    'short_description' => $this->removeScript(trim($shortDescriptions[$defaultLanguage] ?? '')),
                    'description' => $this->removeScript(trim($defaultDescription)),
                ];

                // Save blog using Blog_model::createBlog
                $blogResult = $this->blog->createBlog($blogData);
                if (!empty($blogResult['error'])) {
                    $errors = $blogResult['validation_errors'] ?? [];
                    return ErrorResponse($blogResult['message'] . (!empty($errors) ? ': ' . json_encode($errors) : ''), true, [], [], 200, csrf_token(), csrf_hash());
                }
                $blogId = $blogResult['insert_id'];

                // Process and store translations for all languages (following blog categories pattern)
                try {
                    $translations = [];
                    $allTagData = []; // Store all tag data for proper tag processing

                    foreach ($titles as $languageCode => $title) {
                        // Get tags for this language using the correct form field structure
                        $languageTags = $this->request->getPost('tags[' . $languageCode . ']') ?? [];



                        // Parse tags using the existing parseKeywords method to handle Tagify format
                        $tagsString = $this->parseKeywords($languageTags);


                        // Extract individual tag names for translation processing
                        // This handles HTML-encoded JSON strings from Tagify (e.g., [{&quot;value&quot;:&quot;test&quot;}])
                        $tagArray = [];
                        if (!empty($languageTags)) {
                            // Handle different input formats for tags
                            if (is_string($languageTags)) {
                                // First decode HTML entities in case the JSON is HTML-encoded
                                $decodedInput = html_entity_decode($languageTags, ENT_QUOTES | ENT_HTML5, 'UTF-8');

                                // Try to decode as JSON (handles both regular JSON and HTML-decoded JSON)
                                $decoded = json_decode($decodedInput, true);
                                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                    // Successfully decoded JSON array - extract tag values
                                    foreach ($decoded as $item) {
                                        if (is_string($item)) {
                                            $tagArray[] = trim($item);
                                        } elseif (is_array($item) && isset($item['value'])) {
                                            // Tagify object format: {"value": "tag"}
                                            $tagArray[] = trim($item['value']);
                                        }
                                    }
                                } else {
                                    // Not JSON - treat as comma-separated string
                                    $tagArray = array_filter(array_map('trim', explode(',', $languageTags)));
                                }
                            } elseif (is_array($languageTags)) {
                                // Handle array format from form submission
                                foreach ($languageTags as $item) {
                                    if (is_string($item)) {
                                        // Decode HTML entities first
                                        $decodedString = html_entity_decode($item, ENT_QUOTES | ENT_HTML5, 'UTF-8');

                                        // Try to decode as JSON
                                        $decoded = json_decode($decodedString, true);
                                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                            // Successfully decoded JSON array
                                            foreach ($decoded as $tagItem) {
                                                if (is_string($tagItem)) {
                                                    $tagArray[] = trim($tagItem);
                                                } elseif (is_array($tagItem) && isset($tagItem['value'])) {
                                                    // Tagify object format: {"value": "tag"}
                                                    $tagArray[] = trim($tagItem['value']);
                                                }
                                            }
                                        } else {
                                            // Not JSON - treat as simple string tag
                                            $tagArray[] = trim($item);
                                        }
                                    } elseif (is_array($item) && isset($item['value'])) {
                                        // Already parsed Tagify object format
                                        $tagArray[] = trim($item['value']);
                                    }
                                }
                            }
                        }



                        // Store tag data for each language for proper processing
                        $allTagData[$languageCode] = $tagArray;

                        $translations[$languageCode] = [
                            'title' => $this->removeScript(trim($title)),
                            'short_description' => $this->removeScript(trim($shortDescriptions[$languageCode] ?? '')),
                            'description' => $this->removeScript(trim($descriptions[$languageCode] ?? '')),
                            'tags' => $tagsString
                        ];
                    }

                    // CRITICAL: Always ensure default language translation is explicitly included in translations array
                    // This guarantees that default language is stored in BOTH:
                    // 1. Base table (blogs) - for quick access and fallback
                    // 2. Translations table (translated_blog_details) - for consistency and multi-language support
                    // Even if default language was processed in the loop above, we explicitly set it here
                    // to ensure it matches exactly what was stored in the base table
                    $defaultTagsInput = $this->request->getPost('tags[' . $defaultLanguage . ']') ?? [];
                    $defaultTagsString = $this->parseKeywords($defaultTagsInput);

                    // Explicitly set default language translation using the same values stored in base table
                    $translations[$defaultLanguage] = [
                        'title' => $this->removeScript(trim($defaultTitle)),
                        'short_description' => $this->removeScript(trim($shortDescriptions[$defaultLanguage] ?? '')),
                        'description' => $this->removeScript(trim($defaultDescription)),
                        'tags' => $defaultTagsString
                    ];

                    // Also ensure default language tags are in allTagData for tag processing
                    if (!isset($allTagData[$defaultLanguage])) {
                        $defaultTagArray = [];
                        if (!empty($defaultTagsInput)) {
                            if (is_string($defaultTagsInput)) {
                                $decoded = json_decode($defaultTagsInput, true);
                                if (is_array($decoded)) {
                                    foreach ($decoded as $item) {
                                        if (is_array($item) && isset($item['value'])) {
                                            $defaultTagArray[] = trim($item['value']);
                                        }
                                    }
                                } else {
                                    $defaultTagArray = array_filter(array_map('trim', explode(',', $defaultTagsInput)));
                                }
                            } elseif (is_array($defaultTagsInput)) {
                                foreach ($defaultTagsInput as $item) {
                                    if (is_string($item)) {
                                        $decoded = json_decode($item, true);
                                        if (is_array($decoded)) {
                                            foreach ($decoded as $tagItem) {
                                                if (is_array($tagItem) && isset($tagItem['value'])) {
                                                    $defaultTagArray[] = trim($tagItem['value']);
                                                }
                                            }
                                        } else {
                                            $defaultTagArray[] = trim($item);
                                        }
                                    } elseif (is_array($item) && isset($item['value'])) {
                                        $defaultTagArray[] = trim($item['value']);
                                    }
                                }
                            }
                        }
                        $allTagData[$defaultLanguage] = $defaultTagArray;
                    }

                    // Save translations using the optimized model method
                    // This ensures ALL languages (including default) are stored in the translations table
                    $translationResult = $this->translatedBlogModel->saveTranslationsOptimized($blogId, $translations);

                    if (!$translationResult) {
                        log_message('warning', 'Some blog translations may not have been saved for blog ID: ' . $blogId);
                    }

                    // Process tags with multi-language support
                    // This ensures tags are stored in blog_tags table and their translations in translated_blog_tag_details
                    $this->processTagsWithTranslations($allTagData, $blogId);
                } catch (\Exception $e) {
                    log_message('error', 'Exception in blog translation processing: ' . $e->getMessage());
                    // Don't fail the blog creation if translation processing fails
                    // The blog is already created, so we just log the translation error
                }

                // Save SEO settings for this blog (modular, like Partners)
                $this->saveSeoSettings($blogId);

                // Send notifications when a new blog is published
                // NotificationService handles FCM, Email, and SMS notifications using templates
                // This unified approach sends notifications to all customers (not providers and admin)
                try {
                    // Prepare context data for notification templates
                    // This context will be used to populate template variables like [[blog_title]], [[blog_url]], [[company_name]], etc.
                    $notificationContext = [
                        'blog_id' => $blogId,
                        'blog_title' => $defaultTitle, // Use default language title
                        'blog_slug' => $slug,
                        'blog_short_description' => $shortDescriptions[$defaultLanguage] ?? '',
                        'include_logo' => true // Include company logo in email
                    ];

                    // Get blog category name for the notification
                    if (!empty($blogData['category_id'])) {
                        // Fetch category name with translation support
                        $categoryData = fetch_details('blog_categories', ['id' => $blogData['category_id']]);
                        if (!empty($categoryData)) {
                            $notificationContext['blog_category_name'] = $categoryData[0]['name'] ?? '';
                        }
                    }

                    // Queue all notifications (FCM, Email, SMS) to all customers only
                    // user_groups [2] = customers (excluding admin group 1 and providers group 3)
                    // NotificationService automatically handles:
                    // - Translation of templates based on user language
                    // - Variable replacement in templates
                    // - Notification settings checking for each channel
                    // - Fetching user email/phone/FCM tokens
                    // - Unsubscribe status checking for email
                    $result = queue_notification_service(
                        eventType: 'new_blog',
                        recipients: [],
                        context: $notificationContext,
                        options: [
                            'channels' => ['fcm', 'email', 'sms'], // All channels handled by NotificationService
                            'user_groups' => [2], // Send only to customers (group_id 2)
                            'platforms' => ['android', 'ios', 'web'] // Customer platforms only
                        ]
                    );

                    // log_message('info', '[NEW_BLOG] Notification result: ' . json_encode($result));
                } catch (\Throwable $notificationError) {
                    // Log notification error but don't fail the blog creation
                    // log_message('error', '[NEW_BLOG] Notification error: ' . $notificationError->getMessage());
                    log_message('error', '[NEW_BLOG] Notification error trace: ' . $notificationError->getTraceAsString());
                }

                return successResponse(labels(DATA_SAVED_SUCCESSFULLY, "Data saved successfully"), false, [], [], 200, csrf_token(), csrf_hash());
            } else {
                return redirect()->to('admin/blog');
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Blog.php - add_blog()');
            return ErrorResponse($th->getMessage() ?: labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function list()
    {
        try {
            $blog_model = new Blog_model();

            // Get current language for translations (similar to blog categories)
            $currentLanguage = isset($_GET['language_code']) && !empty($_GET['language_code'])
                ? esc($_GET['language_code'])
                : get_current_language();

            $params = [
                'limit' => isset($_GET['limit']) && !empty($_GET['limit']) ? (int)$_GET['limit'] : 10,
                'offset' => isset($_GET['offset']) && !empty($_GET['offset']) ? (int)$_GET['offset'] : 0,
                'sort' => isset($_GET['sort']) && !empty($_GET['sort']) ? esc($_GET['sort']) : 'id',
                'order' => isset($_GET['order']) && in_array(strtoupper($_GET['order']), ['ASC', 'DESC']) ? strtoupper($_GET['order']) : 'DESC',
                'search' => isset($_GET['search']) && !empty($_GET['search']) ? esc($_GET['search']) : '',
                // Read category_id directly from GET (view now sends category_id instead of category_filter)
                'category_id' => isset($_GET['category_id']) && !empty($_GET['category_id']) ? (int)$_GET['category_id'] : '',
                'language_code' => $currentLanguage, // Pass language to model for translations
                'format' => 'datatable',
                'include_operations' => true
            ];

            $data = $blog_model->list($params);
            return $this->response->setJSON($data);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Blog.php - list()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function delete_blog()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }

            $id = $this->request->getPost('id');
            $blog = $this->blog->find($id);
            if ($blog && !empty($blog['image'])) {
                $this->fileService->delete('blogs/images', $blog['image']);
            }

            // Clean up SEO settings and images before deleting blog
            $this->seoModel->cleanupSeoData($id, 'blogs');

            $deleteResult = $this->blog->delete($id);
            if ($deleteResult) {
                return successResponse(labels(DATA_DELETED_SUCCESSFULLY, "Data deleted successfully"), false, [], [], 200, csrf_token(), csrf_hash());
            } else {
                return ErrorResponse(labels(ERROR_OCCURED, "An error occurred"), true, [], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Blog.php - delete_blog()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function edit_blog()
    {
        try {
            helper('function');
            $uri = service('uri');

            if ($this->isLoggedIn && $this->userIsAdmin) {
                $blog_id = $uri->getSegments()[3];

                // Initialize models
                $blogModel = new Blog_model();
                $categoryModel = new Blog_category_model();

                // Fetch blog and categories using models
                $blog = $blogModel->getBlogById($blog_id);

                if (!$blog) {
                    throw new \Exception(labels(BLOG_NOT_FOUND, 'Blog not found'));
                }

                $blog['image'] = $this->fileService->url($blog['image'], 'blogs/images');

                // Fetch SEO settings for this blog
                $this->seoModel->setTableContext('blogs');
                $seo_settings = $this->seoModel->getSeoSettingsByReferenceId($blog_id, 'full');

                // Load SEO translations and merge with main SEO settings
                $seoTranslations = $this->translatedBlogSeoModel->getAllTranslationsForBlog($blog_id);

                // Always merge SEO translations with main SEO settings (even if no translations exist)
                $mergedSeoSettings = $seo_settings;

                // Fetch languages for merging translations
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
                        // Create language-specific SEO settings from translation
                        $mergedSeoSettings['translated_' . $languageCode] = [
                            'title' => $seoTranslation['seo_title'] ?? '',
                            'description' => $seoTranslation['seo_description'] ?? '',
                            'keywords' => $seoTranslation['seo_keywords'] ?? '',
                            'schema_markup' => $seoTranslation['seo_schema_markup'] ?? ''
                        ];
                    } else {
                        // If no SEO translation exists, use base table data for default language, empty for others
                        $mergedSeoSettings['translated_' . $languageCode] = [
                            'title' => $isDefault ? ($seo_settings['title'] ?? '') : '',
                            'description' => $isDefault ? ($seo_settings['description'] ?? '') : '',
                            'keywords' => $isDefault ? ($seo_settings['keywords'] ?? '') : '',
                            'schema_markup' => $isDefault ? ($seo_settings['schema_markup'] ?? '') : ''
                        ];
                    }
                }

                $this->data['blog_seo_settings'] = $mergedSeoSettings;

                $tags = $blogModel->getTagsForBlog($blog_id);
                $this->data['tags'] = $tags;

                // Get all translations for this blog (following blog categories pattern)
                $translations = $this->translatedBlogModel->getAllTranslations($blog_id);

                // Get default language code
                $defaultLanguage = $this->getDefaultLanguageCode();

                // Base table contains default language values, translations table contains all languages
                // If no translations exist for existing blogs, populate with main table data as fallback
                if (empty($translations)) {
                    // Convert tags array to comma-separated string for default language
                    $tagsString = '';
                    if (!empty($tags)) {
                        $tagNames = array_column($tags, 'name');
                        $tagsString = implode(',', $tagNames);
                    }

                    // For legacy blogs that don't have translations yet, populate with main table data
                    // This ensures the edit form is prefilled with existing blog content
                    // Base table now contains default language values (title, short_description, description)
                    $translations = [
                        $defaultLanguage => [
                            'title' => $blog['title'] ?? '',
                            'short_description' => $blog['short_description'] ?? '',
                            'description' => $blog['description'] ?? '',
                            'tags' => $tagsString // Convert tags array to comma-separated string
                        ]
                    ];
                } else {
                    // If translations exist but some languages are missing, add fallback for missing languages
                    $allLanguages = fetch_details('languages', [], ['code']);
                    foreach ($allLanguages as $lang) {
                        $langCode = $lang['code'];
                        if (!isset($translations[$langCode])) {
                            // For missing language translations, use main table data for default language
                            // and empty strings for other languages
                            if ($langCode === $defaultLanguage) {
                                // Convert tags array to comma-separated string for default language
                                $tagsString = '';
                                if (!empty($tags)) {
                                    $tagNames = array_column($tags, 'name');
                                    $tagsString = implode(',', $tagNames);
                                }

                                $translations[$langCode] = [
                                    'title' => $blog['title'] ?? '',
                                    'short_description' => $blog['short_description'] ?? '',
                                    'description' => $blog['description'] ?? '',
                                    'tags' => $tagsString
                                ];
                            } else {
                                $translations[$langCode] = [
                                    'title' => '',
                                    'short_description' => '',
                                    'description' => '',
                                    'tags' => ''
                                ];
                            }
                        }
                    }
                }

                // Add languages for translation support (following blog categories pattern)
                $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ACE');
                $this->data['languages'] = $languages;
                $this->data['translations'] = $translations;

                // Set view data
                setPageInfo($this->data, labels('blog', 'Blog') . ' | ' . labels('admin_panel', 'Admin Panel'), 'blog');
                $this->data['blog'] = $blog;
                // Get categories with translations for all languages for dropdown
                $allLanguages = fetch_details('languages', [], ['code']);
                $categoriesData = [];

                foreach ($allLanguages as $lang) {
                    $categoriesData[$lang['code']] = $categoryModel->getCategoriesWithTranslations($lang['code']);
                }

                // Set categories for current system language for initial load
                $currentLanguage = get_current_language(); // Use current system language instead of default
                $defaultLanguage = $this->getDefaultLanguageCode();

                $this->data['categories_name'] = $categoriesData[$currentLanguage] ?? $categoriesData[$defaultLanguage];
                $this->data['main_page'] = 'edit_blog';
                return view('backend/admin/template', $this->data);
            } else {
                return redirect('admin/login');
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Blog.php - edit_blog()');
            return ErrorResponse($th->getMessage() ?: labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function update_blog()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }

            if (!empty($_POST)) {
                $rules = $this->blog->getValidationRules();
                $validation_messages = $this->blog->getValidationMessages();

                if (!$this->validate($rules, $validation_messages)) {
                    return $this->response->setJSON([
                        'error' => true,
                        'message' => $this->validator->getErrors(),
                        'csrfName' => csrf_token(),
                        'csrfHash' => csrf_hash()
                    ]);
                }

                $blog_id = $this->request->getPost('blog_id');

                // Get translation data from form (following blog categories pattern)
                $titles = $this->request->getPost('title') ?? [];
                $shortDescriptions = $this->request->getPost('short_description') ?? [];
                $descriptions = $this->request->getPost('description') ?? [];

                // Get default language to use for main table
                $defaultLanguage = $this->getDefaultLanguageCode();

                // Get tags data - handle Tagify JSON format and other formats
                $defaultTagsInput = $this->request->getPost('tags[' . $defaultLanguage . ']') ?? [];
                $defaultTags = $this->parseKeywords($defaultTagsInput);

                // Validate that default language has required fields
                $defaultTitle = isset($titles[$defaultLanguage]) ? trim($titles[$defaultLanguage]) : '';
                $defaultDescription = isset($descriptions[$defaultLanguage]) ? trim($descriptions[$defaultLanguage]) : '';

                if (empty($defaultTitle)) {
                    return ErrorResponse(labels("title_in_default_language_is_required", "Title in default language is required"), true, [], [], 200, csrf_token(), csrf_hash());
                }

                if (empty($defaultDescription)) {
                    return ErrorResponse(labels("description_in_default_language_is_required", "Description in default language is required"), true, [], [], 200, csrf_token(), csrf_hash());
                }

                if (empty($defaultTags)) {
                    return ErrorResponse(labels("tags_in_default_language_are_required", "Tags in default language are required"), true, [], [], 200, csrf_token(), csrf_hash());
                }

                // Generate slug using English content if available, otherwise use blog category logic
                $existingSlug = $this->blog->where('id', $blog_id)->select('slug')->first()['slug'];
                $resolvedSlug = $this->slugService->resolve(
                    currentSlug: $existingSlug,
                    inputSlug: $this->request->getPost('slug'),
                    fallbackName: $defaultTitle,
                    table: 'blogs',
                    excludeId: $blog_id
                );

                if ($this->slugService->isLegacySlug($existingSlug)) {
                    $resolvedSlug = $this->slugService->generate(
                        $defaultTitle,
                        $defaultTitle,
                        'blogs',
                        $blog_id
                    );
                }

                // Prepare blog data for main table
                // Store default language translatable fields in base table (following Services pattern)
                // All translations (including default) will also be stored in translations table
                $data = [
                    'category_id' => $this->request->getPost('category_id'),
                    'slug' => $resolvedSlug,
                    // Store default language values in main table for quick access and fallback
                    'title' => $this->removeScript(trim($defaultTitle)),
                    'short_description' => $this->removeScript(trim($shortDescriptions[$defaultLanguage] ?? '')),
                    'description' => $this->removeScript(trim($defaultDescription)),
                ];

                // Prepare files array for model
                $files = [
                    'image' => $this->request->getFile('blog_image_selector'),
                ];

                // Use model for update logic
                $result = $this->blog->updateBlog($blog_id, $data, $files);
                if (!empty($result['error'])) {
                    $errors = $result['validation_errors'] ?? [];
                    return ErrorResponse($result['message'] . (!empty($errors) ? ': ' . json_encode($errors) : ''), true, [], [], 200, csrf_token(), csrf_hash());
                }

                // Process and store translations for all languages (following blog categories pattern)
                try {
                    $translations = [];
                    $allTagData = []; // Store all tag data for proper tag processing

                    foreach ($titles as $languageCode => $title) {
                        // Get tags for this language using the correct form field structure
                        $languageTags = $this->request->getPost('tags[' . $languageCode . ']') ?? [];



                        // Parse tags using the existing parseKeywords method to handle Tagify format
                        $tagsString = $this->parseKeywords($languageTags);


                        // Extract individual tag names for translation processing
                        // This handles HTML-encoded JSON strings from Tagify (e.g., [{&quot;value&quot;:&quot;test&quot;}])
                        $tagArray = [];
                        if (!empty($languageTags)) {
                            // Handle different input formats for tags
                            if (is_string($languageTags)) {
                                // First decode HTML entities in case the JSON is HTML-encoded
                                $decodedInput = html_entity_decode($languageTags, ENT_QUOTES | ENT_HTML5, 'UTF-8');

                                // Try to decode as JSON (handles both regular JSON and HTML-decoded JSON)
                                $decoded = json_decode($decodedInput, true);
                                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                    // Successfully decoded JSON array - extract tag values
                                    foreach ($decoded as $item) {
                                        if (is_string($item)) {
                                            $tagArray[] = trim($item);
                                        } elseif (is_array($item) && isset($item['value'])) {
                                            // Tagify object format: {"value": "tag"}
                                            $tagArray[] = trim($item['value']);
                                        }
                                    }
                                } else {
                                    // Not JSON - treat as comma-separated string
                                    $tagArray = array_filter(array_map('trim', explode(',', $languageTags)));
                                }
                            } elseif (is_array($languageTags)) {
                                // Handle array format from form submission
                                foreach ($languageTags as $item) {
                                    if (is_string($item)) {
                                        // Decode HTML entities first
                                        $decodedString = html_entity_decode($item, ENT_QUOTES | ENT_HTML5, 'UTF-8');

                                        // Try to decode as JSON
                                        $decoded = json_decode($decodedString, true);
                                        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                            // Successfully decoded JSON array
                                            foreach ($decoded as $tagItem) {
                                                if (is_string($tagItem)) {
                                                    $tagArray[] = trim($tagItem);
                                                } elseif (is_array($tagItem) && isset($tagItem['value'])) {
                                                    // Tagify object format: {"value": "tag"}
                                                    $tagArray[] = trim($tagItem['value']);
                                                }
                                            }
                                        } else {
                                            // Not JSON - treat as simple string tag
                                            $tagArray[] = trim($item);
                                        }
                                    } elseif (is_array($item) && isset($item['value'])) {
                                        // Already parsed Tagify object format
                                        $tagArray[] = trim($item['value']);
                                    }
                                }
                            }
                        }



                        // Store tag data for each language for proper processing
                        $allTagData[$languageCode] = $tagArray;

                        $translations[$languageCode] = [
                            'title' => $this->removeScript(trim($title)),
                            'short_description' => $this->removeScript(trim($shortDescriptions[$languageCode] ?? '')),
                            'description' => $this->removeScript(trim($descriptions[$languageCode] ?? '')),
                            'tags' => $tagsString
                        ];
                    }

                    // CRITICAL: Always ensure default language translation is explicitly included in translations array
                    // This guarantees that default language is stored in BOTH:
                    // 1. Base table (blogs) - for quick access and fallback
                    // 2. Translations table (translated_blog_details) - for consistency and multi-language support
                    // Even if default language was processed in the loop above, we explicitly set it here
                    // to ensure it matches exactly what was stored in the base table
                    $defaultTagsInput = $this->request->getPost('tags[' . $defaultLanguage . ']') ?? [];
                    $defaultTagsString = $this->parseKeywords($defaultTagsInput);

                    // Explicitly set default language translation using the same values stored in base table
                    $translations[$defaultLanguage] = [
                        'title' => $this->removeScript(trim($defaultTitle)),
                        'short_description' => $this->removeScript(trim($shortDescriptions[$defaultLanguage] ?? '')),
                        'description' => $this->removeScript(trim($defaultDescription)),
                        'tags' => $defaultTagsString
                    ];

                    // Also ensure default language tags are in allTagData for tag processing
                    if (!isset($allTagData[$defaultLanguage])) {
                        $defaultTagArray = [];
                        if (!empty($defaultTagsInput)) {
                            if (is_string($defaultTagsInput)) {
                                $decoded = json_decode($defaultTagsInput, true);
                                if (is_array($decoded)) {
                                    foreach ($decoded as $item) {
                                        if (is_array($item) && isset($item['value'])) {
                                            $defaultTagArray[] = trim($item['value']);
                                        }
                                    }
                                } else {
                                    $defaultTagArray = array_filter(array_map('trim', explode(',', $defaultTagsInput)));
                                }
                            } elseif (is_array($defaultTagsInput)) {
                                foreach ($defaultTagsInput as $item) {
                                    if (is_string($item)) {
                                        $decoded = json_decode($item, true);
                                        if (is_array($decoded)) {
                                            foreach ($decoded as $tagItem) {
                                                if (is_array($tagItem) && isset($tagItem['value'])) {
                                                    $defaultTagArray[] = trim($tagItem['value']);
                                                }
                                            }
                                        } else {
                                            $defaultTagArray[] = trim($item);
                                        }
                                    } elseif (is_array($item) && isset($item['value'])) {
                                        $defaultTagArray[] = trim($item['value']);
                                    }
                                }
                            }
                        }
                        $allTagData[$defaultLanguage] = $defaultTagArray;
                    }

                    // Save translations using the optimized model method
                    // This ensures ALL languages (including default) are stored in the translations table
                    $translationResult = $this->translatedBlogModel->saveTranslationsOptimized($blog_id, $translations);

                    if (!$translationResult) {
                        log_message('warning', 'Some blog translations may not have been updated for blog ID: ' . $blog_id);
                    }



                    // Process tags with multi-language support for update
                    // This ensures tags are stored in blog_tags table and their translations in translated_blog_tag_details
                    $this->processTagsWithTranslations($allTagData, $blog_id);
                } catch (\Exception $e) {
                    log_message('error', 'Exception in blog translation processing during update: ' . $e->getMessage());
                    // Don't fail the blog update if translation processing fails
                    // The blog is already updated, so we just log the translation error
                }

                // After successfully updating the blog, also update its SEO settings
                $this->saveSeoSettings($blog_id);
                return successResponse(labels(DATA_UPDATED_SUCCESSFULLY, "Data updated successfully"), false, [], [], 200, csrf_token(), csrf_hash());
            } else {
                return redirect()->to('admin/blog');
            }
        } catch (\Throwable $th) {
            throw $th;
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Blog.php - update_blog()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function add_blog_categories_view()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }
            $permission = is_permitted($this->creator_id, 'create', 'blog');
            if (!$permission) {
                return NoPermission();
            }
            setPageInfo($this->data, labels('blog_categories', 'Blog Categories') . ' | ' . labels('admin_panel', 'Admin Panel'), 'add_blog_categories');

            // fetch languages
            $languages = fetch_details('languages', [], ['id', 'language', 'is_default', 'code'], "", '0', 'id', 'ACE');
            $this->data['languages'] = $languages;
            return view('backend/admin/template', $this->data);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Blog.php - add_blog_categories_view()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function list_category()
    {
        try {
            $limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : 10;
            $offset = (isset($_GET['offset']) && !empty($_GET['offset'])) ? $_GET['offset'] : 0;
            $sort = (isset($_GET['sort']) && !empty($_GET['sort'])) ? $_GET['sort'] : 'id';
            $order = (isset($_GET['order']) && !empty($_GET['order'])) ? $_GET['order'] : 'ASC';
            $search = (isset($_GET['search']) && !empty($_GET['search'])) ? $_GET['search'] : '';
            $where = [];

            // Get current language for translations
            $currentLanguage = get_current_language();

            // Use the simple approach like promocodes - call the model's list method with language
            $data = $this->category->list($search, $limit, $offset, $sort, $order, $where, $currentLanguage);

            return $this->response->setJSON($data);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . ' --> app/Controllers/admin/Blog.php - list_category()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function add_category()
    {
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('admin/login');
        }
        
        $creator_id = $this->userId;
        $permission = is_permitted($creator_id, 'create', 'blog');
        if (!$permission) {
            return NoPermission();
        }

        try {
            // Get names from POST data (multi-language format)
            $names = $this->request->getPost('name');

            // Ensure names is an array
            if (!is_array($names)) {
                // Try to get names in alternative format (name[language_code])
                $names = [];
                $languages = fetch_details('languages', [], ['code']);
                foreach ($languages as $lang) {
                    $langCode = $lang['code'];
                    $nameValue = $this->request->getPost("name[{$langCode}]");
                    if ($nameValue !== null) {
                        $names[$langCode] = $nameValue;
                    }
                }
            }

            // Get default language to use as the main name
            $defaultLanguage = $this->getDefaultLanguageCode();
            $defaultName = $names[$defaultLanguage] ?? '';

            // If default name is still empty, try to get from first available language as fallback
            if (empty(trim($defaultName)) && !empty($names)) {
                $defaultName = reset($names); // Use first available name as fallback
            }

            if (empty(trim($defaultName))) {
                return ErrorResponse(labels("category_name_in_default_language_is_required", "Category name in default language is required"), true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Prepare data for main table
            // Store default language translatable fields in base table (following Blogs pattern)
            // All translations (including default) will also be stored in translations table
            $data = [
                'slug' => $this->slugService->resolve(
                    currentSlug: null,
                    inputSlug: $this->request->getPost('slug'),
                    fallbackName: $defaultName,
                    table: 'blog_categories'
                ),
                // Store default language name in main table for quick access and fallback
                // This ensures backward compatibility and provides a fallback when translations are not available
                'name' => $this->removeScript(trim($defaultName))
            ];

            // Create the blog category
            $result = $this->category->createCategory($data);

            if (isset($result['error']) && $result['error'] === false) {
                $categoryId = $result['insert_id'];

                // Process and store translations for all languages
                try {
                    // Save translations using the optimized model method
                    $translationResult = $this->translatedBlogCategoryModel->saveTranslationsOptimized($categoryId, $names);

                    if (!$translationResult) {
                        log_message('warning', 'Some blog category translations may not have been saved for category ID: ' . $categoryId);
                    }
                } catch (\Exception $e) {
                    log_message('error', 'Exception in blog category translation processing: ' . $e->getMessage());
                    // Don't fail the category creation if translation processing fails
                    // The category is already created, so we just log the translation error
                }

                return successResponse($result['message'], false, [], [], 200, csrf_token(), csrf_hash());
            } else {
                return ErrorResponse($result['message'], true, [], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Blog.php - add_category()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function update_category()
    {
        $creatorId = $this->userId;
        $permission = is_permitted($creatorId, 'update', 'blog');
        if (!$permission) {
            return NoPermission();
        }
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('admin/login');
        }

        try {
            // Get names from POST data (multi-language format)
            $names = $this->request->getPost('name');

            // Ensure names is an array
            if (!is_array($names)) {
                // Try to get names in alternative format (name[language_code])
                $names = [];
                $languages = fetch_details('languages', [], ['code']);
                foreach ($languages as $lang) {
                    $langCode = $lang['code'];
                    $nameValue = $this->request->getPost("name[{$langCode}]");
                    if ($nameValue !== null) {
                        $names[$langCode] = $nameValue;
                    }
                }
            }

            // Get default language to use as the main name
            $defaultLanguage = $this->getDefaultLanguageCode();
            $defaultName = $names[$defaultLanguage] ?? '';

            // If default name is still empty, try to get from first available language as fallback
            if (empty(trim($defaultName)) && !empty($names)) {
                $defaultName = reset($names); // Use first available name as fallback
            }

            if (empty(trim($defaultName))) {
                return ErrorResponse(labels("category_name_in_default_language_is_required", "Category name in default language is required"), true, [], [], 200, csrf_token(), csrf_hash());
            }

            $id = $this->request->getPost('id');

            // Prepare data for main table
            // Store default language translatable fields in base table (following Blogs pattern)
            // All translations (including default) will also be stored in translations table
            $existingCategory = $this->category->find($id);

            $resolvedSlug = $this->slugService->resolve(
                currentSlug: $existingCategory['slug'] ?? null,
                inputSlug: $this->request->getPost('slug'),
                fallbackName: $defaultName,
                table: 'blog_categories',
                excludeId: $id
            );

            if($this->slugService->isLegacySlug($existingCategory['slug'])) {
                $resolvedSlug = $this->slugService->generate(
                    raw: $defaultName,
                    fallback: $defaultName,
                    table: 'blog_categories',
                    excludeId: $id
                );
            }

            $data = [
                'slug' => $resolvedSlug,
                // Store default language name in main table for quick access and fallback
                // This ensures backward compatibility and provides a fallback when translations are not available
                'name' => $this->removeScript(trim($defaultName))
            ];

            $upd = $this->category->updateCategory($id, $data);

            if (isset($upd['error']) && $upd['error'] == false) {
                // Process and store translations for all languages
                try {
                    // Save translations using the optimized model method
                    $translationResult = $this->translatedBlogCategoryModel->saveTranslationsOptimized($id, $names);

                    if (!$translationResult) {
                        log_message('warning', 'Some blog category translations may not have been updated for category ID: ' . $id);
                    }
                } catch (\Exception $e) {
                    log_message('error', 'Exception in blog category translation processing during update: ' . $e->getMessage());
                    // Don't fail the category update if translation processing fails
                    // The category is already updated, so we just log the translation error
                }

                return successResponse($upd['message'], false, [], [], 200, csrf_token(), csrf_hash());
            } else {
                return ErrorResponse($upd['message'], true, [], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Blog.php - update_category()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function remove_category()
    {
        $creator_id = $this->userId;
        $permission = is_permitted($creator_id, 'delete', 'blog');
        if (!$permission) {
            return NoPermission();
        }
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('admin/login');
        }

        try {
            $id = $this->request->getPost('id');
            $result = $this->category->deleteCategory($id);

            if (isset($result['error']) && $result['error'] == false) {
                return successResponse($result['message'], false, [], [], 200, csrf_token(), csrf_hash());
            } else {
                return ErrorResponse($result['message'], true, [], [], 200, csrf_token(), csrf_hash());
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Blog.php - remove_category()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    /**
     * Save SEO settings for a blog post with multi-language support
     * Following the pattern from Partners.php
     * @param int $blogId
     */
    private function saveSeoSettings(int $blogId): void
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

        // Log extraction process for each field
        if (!empty($translatedFields['seo_title'][$defaultLanguage])) {
            $defaultSeoTitle = trim($translatedFields['seo_title'][$defaultLanguage]);
        }

        if (!empty($translatedFields['seo_description'][$defaultLanguage])) {
            $defaultSeoDescription = trim($translatedFields['seo_description'][$defaultLanguage]);
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
        }

        if (!empty($translatedFields['seo_schema_markup'][$defaultLanguage])) {
            $defaultSeoSchema = trim($translatedFields['seo_schema_markup'][$defaultLanguage]);
        }

        // Parse meta keywords (Tagify or comma-separated)
        $keywords = $defaultSeoKeywords ? $this->parseKeywords($defaultSeoKeywords) : '';

        // Build SEO data array
        $seoData = [
            'title'         => $defaultSeoTitle,
            'description'   => $defaultSeoDescription,
            'keywords'      => $keywords,
            'schema_markup' => $defaultSeoSchema,
            'blog_id'       => $blogId,
        ];

        // Check if any SEO field is filled (excluding blog_id)
        $hasSeoData = array_filter($seoData, fn($v) => !empty($v) && $v !== $blogId);

        // Check if all SEO fields are intentionally cleared
        $allFieldsCleared = empty($seoData['title']) &&
            empty($seoData['description']) &&
            empty($seoData['keywords']) &&
            empty($seoData['schema_markup']);

        // Handle SEO image upload
        $seoImage = $this->request->getFile('meta_image');
        $hasImage = $seoImage && $seoImage->isValid();

        // Use Seo_model for blog context
        $this->seoModel->setTableContext('blogs');
        $existingSettings = $this->seoModel->getSeoSettingsByReferenceId($blogId);

        $newSeoData = $seoData;
        if ($hasImage) {
            $uploadResult = $this->fileService->upload($seoImage, 'blog_seo_settings');
            if ($uploadResult['error']) {
                throw new \Exception(labels(SEO_IMAGE_UPLOAD_FAILED, 'SEO image upload failed: ' . $uploadResult['message']));
            }
            $newSeoData['image'] = $uploadResult['path'];
        } else {
            $newSeoData['image'] = $existingSettings['image'] ?? '';
        }

        // If no existing settings, create new if data or image exists
        // This ensures that even if only an image is provided, we create the SEO record
        if (!$existingSettings) {
            if ($hasSeoData || $hasImage) {
                $result = $this->seoModel->createSeoSettings($newSeoData);
                if (!empty($result['error'])) {
                    $errors = $result['validation_errors'] ?? [];
                    throw new \Exception($result['message'] . (!empty($errors) ? ': ' . json_encode($errors) : ''));
                }
            }

            // Process SEO translations after creating base SEO settings
            $this->processSeoTranslations($blogId, $translatedFields);
            return;
        }

        // If existing settings exist and all fields are cleared (and no new image), delete the record
        // BUT: If there's an existing image, we should NOT delete the record even if all other fields are empty
        // This preserves the SEO record structure for future use
        if ($existingSettings && $allFieldsCleared && !$hasImage && empty($existingSettings['image'])) {
            $result = $this->seoModel->delete($existingSettings['id']);
            if ($result && !empty($existingSettings['image'])) {
                $this->fileService->delete('blog_seo_settings', $existingSettings['image']);
            }
            // Also clean up SEO translations when deleting base SEO settings
            $this->cleanupSeoTranslations($blogId);
            return;
        }

        // Force clearing removed SEO fields
        $emptyDefaults = [
            'title' => '',
            'description' => '',
            'keywords' => '',
            'schema_markup' => '',
            'image' => $existingSettings['image'] ?? '' // keep old image only if not changed
        ];

        foreach ($emptyDefaults as $key => $defaultVal) {
            if (!array_key_exists($key, $newSeoData) || empty($newSeoData[$key])) {
                $newSeoData[$key] = $defaultVal;
            }
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
        if (!$settingsChanged) {
            // Even if base SEO settings haven't changed, process translations
            $this->processSeoTranslations($blogId, $translatedFields);
            return;
        }

        // Update existing settings with new data
        $result = $this->seoModel->updateSeoSettings($existingSettings['id'], $newSeoData);
        if (!empty($result['error'])) {
            $errors = $result['validation_errors'] ?? [];
            throw new \Exception($result['message'] . (!empty($errors) ? ': ' . json_encode($errors) : ''));
        }

        // Process SEO translations after updating base SEO settings
        $this->processSeoTranslations($blogId, $translatedFields);
    }

    /**
     * Process SEO translations from form data
     * 
     * @param int $blogId Blog ID
     * @param array|null $translatedFields Translated fields data (optional, will fetch from POST if not provided)
     * @return void
     */
    private function processSeoTranslations(int $blogId, ?array $translatedFields = null): void
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
                // Restructure data for the model (convert field[lang] to lang[field] format)
                $restructuredData = $this->restructureTranslatedFieldsForSeoModel($translatedFields);

                // Process and store the SEO translations
                $seoTranslationResult = $this->translatedBlogSeoModel->processSeoTranslations($blogId, $restructuredData);

                // Check if SEO translation processing was successful
                if (!$seoTranslationResult['success']) {
                    throw new \Exception('SEO Translation processing failed: ' . json_encode($seoTranslationResult['errors']));
                }
            }
        } catch (\Exception $e) {
            // Log any exceptions but don't fail the operation
            throw new \Exception('Exception in processSeoTranslations for blog ' . $blogId . ': ' . $e->getMessage());
        }
    }

    /**
     * Transform form data to translated fields structure
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
            // Copy the data directly since it's already in the right structure
            $translatedFields['seo_title'] = $postData['meta_title'] ?? [];
            $translatedFields['seo_description'] = $postData['meta_description'] ?? [];

            // Process keywords data properly - handle array structure with JSON strings
            $metaKeywords = $postData['meta_keywords'] ?? [];
            $processedKeywords = [];
            foreach ($metaKeywords as $langCode => $keywordsData) {
                if (is_array($keywordsData)) {
                    // Handle array format (like from Tagify)
                    if (count($keywordsData) === 1 && is_string($keywordsData[0])) {
                        // Single JSON string in array format - keep as is for parseKeywords to handle
                        $processedKeywords[$langCode] = $keywordsData[0];
                    } else {
                        // Multiple values, join them
                        $processedKeywords[$langCode] = implode(',', $keywordsData);
                    }
                } else {
                    // Direct string value
                    $processedKeywords[$langCode] = $keywordsData;
                }
            }
            $translatedFields['seo_keywords'] = $processedKeywords;

            $translatedFields['seo_schema_markup'] = $postData['schema_markup'] ?? [];

            return $translatedFields;
        }

        // Fallback: Process form data in the old format (field[language] format)
        // Get languages from database
        $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], "", '0', 'id', 'ASC');

        foreach ($languages as $language) {
            $languageCode = $language['code'];

            // Process SEO fields (meta_ prefixed from form)
            // Use array_key_exists to capture empty values so cleared fields overwrite previous data
            $seoTitleField = 'meta_title[' . $languageCode . ']';
            if (array_key_exists($seoTitleField, $postData)) {
                // Record even empty strings so cleared values overwrite previous data
                $seoTitleValue = $postData[$seoTitleField];
                $translatedFields['seo_title'][$languageCode] = trim((string)$seoTitleValue);
            }

            $seoDescriptionField = 'meta_description[' . $languageCode . ']';
            if (array_key_exists($seoDescriptionField, $postData)) {
                // Preserve intent when user submits blank description during edits
                $seoDescriptionValue = $postData[$seoDescriptionField];
                $translatedFields['seo_description'][$languageCode] = trim((string)$seoDescriptionValue);
            }

            $seoKeywordsField = 'meta_keywords[' . $languageCode . ']';
            if (array_key_exists($seoKeywordsField, $postData)) {
                $seoKeywordsValue = $postData[$seoKeywordsField];
                // Handle array format from Tagify
                if (is_array($seoKeywordsValue)) {
                    if (count($seoKeywordsValue) === 1 && is_string($seoKeywordsValue[0])) {
                        // Single JSON string in array format - keep as is for parseKeywords to handle
                        $translatedFields['seo_keywords'][$languageCode] = $seoKeywordsValue[0];
                    } else {
                        // Multiple values, join them
                        $translatedFields['seo_keywords'][$languageCode] = implode(',', $seoKeywordsValue);
                    }
                } else {
                    // Record even empty strings so cleared keywords overwrite previous data
                    $translatedFields['seo_keywords'][$languageCode] = trim((string)$seoKeywordsValue);
                }
            }

            $seoSchemaField = 'schema_markup[' . $languageCode . ']';
            if (array_key_exists($seoSchemaField, $postData)) {
                // Preserve intent when user submits blank schema during edits
                $seoSchemaValue = $postData[$seoSchemaField];
                $translatedFields['seo_schema_markup'][$languageCode] = trim((string)$seoSchemaValue);
            }
        }

        return $translatedFields;
    }

    /**
     * Restructure translated fields from field[lang] format to lang[field] format
     * This is required for the model's processSeoTranslations method
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

    /**
     * Clean up SEO translations when base SEO settings are deleted
     * 
     * @param int $blogId The blog ID
     * @return void
     */
    private function cleanupSeoTranslations(int $blogId): void
    {
        try {
            // Load the SEO translation model
            $seoTranslationModel = $this->translatedBlogSeoModel;

            // Delete all SEO translations for this blog
            $seoTranslationModel->deleteBlogSeoTranslations($blogId);
        } catch (\Exception $e) {
            // Log any exceptions but don't fail the operation
            log_message('error', 'Error cleaning up SEO translations for blog ' . $blogId . ': ' . $e->getMessage());
        }
    }

    /**
     * Remove SEO image for a blog
     * This method handles AJAX requests to remove SEO images
     * @return \CodeIgniter\HTTP\Response
     */
    public function remove_seo_image()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return ErrorResponse(labels(UNAUTHORIZED_ACCESS, "Unauthorized access"), true, [], [], 200, csrf_token(), csrf_hash());
            }

            $blogId = $this->request->getPost('blog_id');
            $seoId = $this->request->getPost('seo_id');

            if (!$blogId) {
                return ErrorResponse(labels(BLOG_ID_REQUIRED, "Blog ID is required"), true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Set SEO model context for blogs
            $this->seoModel->setTableContext('blogs');

            // Get existing SEO settings
            $existingSettings = $this->seoModel->getSeoSettingsByReferenceId($blogId);

            if (!$existingSettings) {
                return ErrorResponse(labels(SEO_SETTINGS_NOT_FOUND_FOR_THIS_BLOG, "SEO settings not found for this blog"), true, [], [], 200, csrf_token(), csrf_hash());
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
                'blog_id' => $blogId
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
                $this->fileService->delete('blog_seo_settings', $imageToDelete);
            }

            return successResponse(labels(SEO_IMAGE_REMOVED_SUCCESSFULLY, "SEO image removed successfully"), false, [], [], 200, csrf_token(), csrf_hash());
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/admin/Blog.php - remove_seo_image()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something went wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    /**
     * Parse keywords/tags from Tagify or comma-separated string
     * Handles HTML-encoded JSON strings from Tagify (e.g., [{&quot;value&quot;:&quot;test&quot;}])
     * Matches the same logic used in Services controller
     * 
     * @param mixed $input Input can be string, array, or HTML-encoded JSON string
     * @return string Comma-separated tag values (e.g., "test, new")
     */
    private function parseKeywords($input): string
    {
        // If input is empty, return empty string
        if (empty($input)) {
            return '';
        }

        // If input is a string, it might be JSON (possibly HTML-encoded) or comma-separated
        if (is_string($input)) {
            // First, decode HTML entities in case the JSON is HTML-encoded (e.g., &quot; -> ")
            // This handles cases where Tagify sends data as HTML-encoded JSON
            $decodedInput = html_entity_decode($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            // Try to decode as JSON (handles both regular JSON and HTML-decoded JSON)
            $jsonDecoded = json_decode($decodedInput, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($jsonDecoded)) {
                // Successfully decoded JSON array - extract tag values
                $tagValues = [];
                foreach ($jsonDecoded as $item) {
                    if (is_string($item)) {
                        // Direct string value
                        $tagValues[] = trim($item);
                    } elseif (is_array($item) && isset($item['value'])) {
                        // Tagify object format: {"value": "tag"}
                        $tagValues[] = trim($item['value']);
                    }
                }
                // Return comma-separated with space (matching Services format)
                return implode(', ', array_filter($tagValues));
            }

            // Not JSON - treat as comma-separated string
            return trim($input);
        }

        // If input is an array
        if (is_array($input)) {
            $tagValues = [];

            // Handle case where array contains a single JSON string (possibly HTML-encoded)
            if (count($input) === 1 && is_string($input[0])) {
                // Decode HTML entities first
                $decodedString = html_entity_decode($input[0], ENT_QUOTES | ENT_HTML5, 'UTF-8');

                // Try to decode as JSON
                $jsonDecoded = json_decode($decodedString, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($jsonDecoded)) {
                    // Successfully decoded JSON array
                    foreach ($jsonDecoded as $item) {
                        if (is_string($item)) {
                            $tagValues[] = trim($item);
                        } elseif (is_array($item) && isset($item['value'])) {
                            $tagValues[] = trim($item['value']);
                        }
                    }
                    // Return comma-separated with space (matching Services format)
                    return implode(', ', array_filter($tagValues));
                }
            }

            // Handle array of objects or strings (direct Tagify format)
            foreach ($input as $item) {
                if (is_string($item)) {
                    // Check if string item is JSON (possibly HTML-encoded)
                    $decodedString = html_entity_decode($item, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $jsonDecoded = json_decode($decodedString, true);

                    if (json_last_error() === JSON_ERROR_NONE && is_array($jsonDecoded)) {
                        // It's a JSON string - extract values
                        foreach ($jsonDecoded as $jsonItem) {
                            if (is_string($jsonItem)) {
                                $tagValues[] = trim($jsonItem);
                            } elseif (is_array($jsonItem) && isset($jsonItem['value'])) {
                                $tagValues[] = trim($jsonItem['value']);
                            }
                        }
                    } else {
                        // Direct string value
                        $tagValues[] = trim($item);
                    }
                } elseif (is_array($item)) {
                    // Direct Tagify object format: {"value": "tag"}
                    if (isset($item['value'])) {
                        $tagValues[] = trim($item['value']);
                    }
                }
            }

            // Return comma-separated with space (matching Services format)
            return implode(', ', array_filter($tagValues));
        }

        // Fallback: return empty string for unexpected input
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


    /**
     * Get blog data with translations for editing
     * Follows the same pattern as get_blog_category_data
     * 
     * @return \CodeIgniter\HTTP\Response JSON response with blog data
     */
    public function get_blog_data()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return $this->response->setJSON(['error' => true, 'message' => labels(UNAUTHORIZED_ACCESS)]);
            }

            $blogId = $this->request->getPost('id');
            if (empty($blogId)) {
                return $this->response->setJSON(['error' => true, 'message' => labels(BLOG_ID_REQUIRED)]);
            }

            // Get the blog data
            $blog = $this->blog->find($blogId);
            if (!$blog) {
                return $this->response->setJSON(['error' => true, 'message' => labels(DATA_NOT_FOUND)]);
            }

            // Get all translations for this blog
            $translations = $this->translatedBlogModel->getAllTranslations($blogId);

            // Get default language code
            $defaultLanguage = $this->getDefaultLanguageCode();

            // For new implementation: translatable fields should only come from translations table
            // If no translations exist for existing blogs, this indicates legacy data that needs migration
            if (empty($translations)) {
                // For legacy blogs that don't have translations yet, create empty structure
                // Note: In production, you may want to migrate existing blog data to translations table
                $translations = [
                    $defaultLanguage => [
                        'title' => '',
                        'short_description' => '',
                        'description' => '',
                        'tags' => ''
                    ]
                ];
            }

            // Prepare response data
            $responseData = [
                'id' => $blog['id'],
                'slug' => $blog['slug'],
                'category_id' => $blog['category_id'],
                'image' => $blog['image'],
                'status' => $blog['status'] ?? 1,
                'translations' => $translations
            ];

            return $this->response->setJSON([
                'error' => false,
                'data' => $responseData
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Error getting blog data: ' . $e->getMessage());
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(ERROR_OCCURED)
            ]);
        }
    }

    /**
     * Get categories with translations for a specific language (AJAX endpoint)
     * Used by JavaScript to update category dropdown when language changes
     * 
     * @return \CodeIgniter\HTTP\Response JSON response with categories
     */
    public function get_categories_by_language()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return $this->response->setJSON(['error' => true, 'message' => labels(UNAUTHORIZED_ACCESS)]);
            }

            $languageCode = $this->request->getPost('language_code');
            if (empty($languageCode)) {
                return $this->response->setJSON(['error' => true, 'message' => labels(LANGUAGE_CODE_IS_REQUIRED)]);
            }

            // Get categories with translations for the specified language
            $categories = $this->category->getCategoriesWithTranslations($languageCode);

            return $this->response->setJSON([
                'error' => false,
                'data' => $categories
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Error getting categories by language: ' . $e->getMessage());
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(ERROR_OCCURED)
            ]);
        }
    }

    /**
     * Get blog category data with translations for editing
     * 
     * @return \CodeIgniter\HTTP\Response JSON response with blog category data
     */
    public function get_blog_category_data()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return $this->response->setJSON(['error' => true, 'message' => labels(UNAUTHORIZED_ACCESS)]);
            }

            $categoryId = $this->request->getPost('id');
            if (empty($categoryId)) {
                return $this->response->setJSON(['error' => true, 'message' => labels('category_id_is_required')]);
            }

            // Get the blog category data
            $category = $this->category->find($categoryId);
            if (!$category) {
                return $this->response->setJSON(['error' => true, 'message' => labels(DATA_NOT_FOUND)]);
            }

            // Get all translations for this category
            $translations = $this->translatedBlogCategoryModel->getAllTranslations($categoryId);

            // Get default language code
            $defaultLanguage = $this->getDefaultLanguageCode();

            // If no translations exist, create fallback data using the main table data
            if (empty($translations)) {
                // Create fallback translations object with default language
                // Use the name from main table if it exists, otherwise empty string
                // Decode HTML entities so they display correctly in edit form inputs
                $translations = [
                    $defaultLanguage => html_entity_decode($category['name'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8')
                ];
            } else {
                // Decode HTML entities for all translations
                // This allows the values to display properly in input fields
                // The GlobalSanitizer will re-encode them when the form is submitted
                foreach ($translations as $langCode => $name) {
                    $translations[$langCode] = html_entity_decode($name ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }

                // If translations exist, ensure default language has a value
                // If default language translation is missing, use main table name as fallback
                if (!isset($translations[$defaultLanguage]) || empty($translations[$defaultLanguage])) {
                    $translations[$defaultLanguage] = html_entity_decode($category['name'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
                }
            }

            // Prepare response data
            $responseData = [
                'id' => $category['id'],
                'slug' => $category['slug'],
                'status' => $category['status'],
                'translations' => $translations
            ];

            return $this->response->setJSON([
                'error' => false,
                'data' => $responseData
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Error getting blog category data: ' . $e->getMessage());
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(ERROR_OCCURED)
            ]);
        }
    }

    /**
     * Process tags with multi-language translations for blog creation
     * This method handles the storage of individual tags and their translations
     * 
     * @param array $allTagData Array of tag data organized by language code
     * @param int $blogId The blog ID to associate tags with
     * @return void
     */
    private function processTagsWithTranslations(array $allTagData, int $blogId): void
    {
        try {
            // Get all unique tag names from all languages to avoid duplicates
            $uniqueTagNames = [];
            $tagTranslations = []; // Store translations for each tag

            // Collect all unique tag names and their translations
            foreach ($allTagData as $languageCode => $tags) {
                if (!is_array($tags)) {
                    continue;
                }

                foreach ($tags as $tagName) {
                    $tagName = trim($tagName);
                    if (empty($tagName)) {
                        continue;
                    }

                    // Store the tag name (use lowercase for uniqueness check)
                    $lowerTagName = strtolower($tagName);
                    if (!isset($uniqueTagNames[$lowerTagName])) {
                        $uniqueTagNames[$lowerTagName] = $tagName; // Keep original case for storage
                        $tagTranslations[$tagName] = []; // Initialize translations array
                    }

                    // Store translation for this tag
                    $tagTranslations[$tagName][$languageCode] = $tagName;
                }
            }

            // If no tags found, return early
            if (empty($uniqueTagNames)) {
                return;
            }

            // Format data according to what processTagsWithTranslations expects
            $formattedTagData = [];
            foreach ($uniqueTagNames as $tagName) {
                $formattedTagData[] = [
                    'name' => $tagName,
                    'translations' => $tagTranslations[$tagName] ?? []
                ];
            }

            // Use the blog model's method to process tags with translations
            $this->blog->processTagsWithTranslations($formattedTagData, $blogId);
        } catch (\Exception $e) {
            log_message('error', 'Error processing tags with translations in Blog controller: ' . $e->getMessage());
            // Don't throw exception to avoid breaking blog creation
        }
    }
}
