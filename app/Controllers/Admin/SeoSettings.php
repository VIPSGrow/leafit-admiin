<?php

namespace App\Controllers\Admin;

use App\Models\Seo_model;
use App\Services\utility\FileService;

/**
 * SEO Settings Controller
 * Handles all SEO settings related operations in the admin panel
 * Follows CI4 best practices and MVC architecture
 */
class SeoSettings extends Admin
{
    public $creator_id;
    protected $superadmin;
    protected $validation;
    protected $seoModel;
    protected $userPermissions;
    protected $translatedGeneralSeoModel;
    protected FileService $fileService;

    public function __construct()
    {
        parent::__construct();
        $this->creator_id = $this->userId;
        $this->validation = \Config\Services::validation();
        $this->superadmin = $this->session->get('email');
        // Load the SEO model using CI4 best practices
        $this->seoModel = model(Seo_model::class);
        // Load the translated SEO model for multilanguage support
        $this->translatedGeneralSeoModel = model('TranslatedGeneralSeoSettings_model');
        $this->fileService = new FileService();
        $this->userPermissions = get_permission($this->userId);

        helper('ResponceServices');
    }

    /**
     * Display the SEO settings page
     * 
     * @return \CodeIgniter\HTTP\RedirectResponse|string
     */
    public function index()
    {
        // Check authentication and authorization
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('admin/login');
        }

        // Ensure the current user has read permission for SEO settings, otherwise redirect.
        $permissionGuard = enforce_permission_or_redirect('read', 'seo_settings', $this->userId);
        if ($permissionGuard !== true) {
            return $permissionGuard;
        }

        // Set page information for the view
        setPageInfo(
            $this->data,
            labels('seo_settings', 'SEO Settings') . ' | ' . labels('admin_panel', 'Admin Panel'),
            'seo_settings'
        );

        return view('backend/admin/template', $this->data);
    }

    /**
     * Create new SEO settings record
     * Handles form submission and validation using CI4 best practices
     * 
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function add_seo_settings()
    {
        // Check authentication and authorization
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('admin/login');
        }

        // Check demo mode restrictions
        $result = checkModificationInDemoMode($this->superadmin);
        if ($result !== true) {
            return $this->response->setJSON($result);
        }

        try {
            // Validate only default language fields are required
            // Other languages are optional
            $defaultLanguage = get_default_language();
            $validationErrors = [];

            // Validate page (always required)
            $page = $this->request->getPost('page');
            if (empty($page)) {
                $validationErrors['page'] = labels('page_field_is_required', 'Page field is required');
            }

            // Validate default language SEO fields
            $metaTitle = $this->request->getPost('meta_title');
            $defaultTitle = '';
            if (is_array($metaTitle) && !empty($metaTitle[$defaultLanguage])) {
                $defaultTitle = trim((string)$metaTitle[$defaultLanguage]);
            }
            if (empty($defaultTitle)) {
                $validationErrors['meta_title'] = labels('seo_title_in_default_language_is_required', 'SEO title in default language is required');
            } elseif (strlen($defaultTitle) > 255) {
                $validationErrors['meta_title'] = labels('title_cannot_exceed_255_characters', 'Title cannot exceed 255 characters');
            }

            $metaDescription = $this->request->getPost('meta_description');
            $defaultDescription = '';
            if (is_array($metaDescription) && !empty($metaDescription[$defaultLanguage])) {
                $defaultDescription = trim((string)$metaDescription[$defaultLanguage]);
            }
            if (empty($defaultDescription)) {
                $validationErrors['meta_description'] = labels('seo_description_in_default_language_is_required', 'SEO description in default language is required');
            } elseif (strlen($defaultDescription) > 500) {
                $validationErrors['meta_description'] = labels('description_cannot_exceed_500_characters', 'Description cannot exceed 500 characters');
            }

            $metaKeywords = $this->request->getPost('meta_keywords');
            $defaultKeywords = '';
            if (is_array($metaKeywords) && !empty($metaKeywords[$defaultLanguage])) {
                $keywordValue = $metaKeywords[$defaultLanguage];
                // Use parseKeywords to normalize Tagify JSON format to comma-separated string
                $defaultKeywords = $this->parseKeywords($keywordValue);
            }
            if (empty($defaultKeywords)) {
                $validationErrors['meta_keywords'] = labels('seo_keywords_in_default_language_are_required', 'SEO keywords in default language are required');
            }

            $schemaMarkupArray = $this->request->getPost('schema_markup');
            $defaultSchemaMarkup = '';
            if (is_array($schemaMarkupArray) && !empty($schemaMarkupArray[$defaultLanguage])) {
                $defaultSchemaMarkup = trim((string)$schemaMarkupArray[$defaultLanguage]);
            }
            if (empty($defaultSchemaMarkup)) {
                $validationErrors['schema_markup'] = labels('schema_markup_in_default_language_is_required', 'Schema markup in default language is required');
            }

            // Return validation errors if any
            if (!empty($validationErrors)) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => $validationErrors,
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash()
                ]);
            }

            // Get form data - this method extracts ONLY default language data
            $formData = $this->getValidatedData();

            // Handle image upload
            $imageResult = $this->handleImageUpload();
            if ($imageResult['error']) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => $imageResult['message'],
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash()
                ]);
            }

            // Prepare data for model
            $seoData = array_merge($formData, [
                'image' => $imageResult['path']
            ]);

            // Use model to create the SEO settings
            $result = $this->seoModel->createSeoSettings($seoData);

            // Process SEO translations if creation was successful
            if (!$result['error'] && !empty($result['insert_id'])) {
                try {
                    // Get translated fields from form data
                    $translatedFields = $this->extractTranslatedFieldsFromPost();
                    if (!empty($translatedFields)) {
                        // Process translations using the seo_id from the insert
                        $this->processSeoTranslations($result['insert_id'], $translatedFields);
                    }
                } catch (\Exception $e) {
                    // Log error but don't fail the operation
                    log_message('error', 'SEO Translation processing error in add_seo_settings: ' . $e->getMessage());
                }
            }

            return $this->response->setJSON([
                'error' => $result['error'],
                'message' => $result['message'],
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash()
            ]);
        } catch (\Exception $e) {
            // Log error for debugging
            log_message('error', 'SEO Settings creation error in controller: ' . var_export($e, true));

            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash()
            ]);
        }
    }

    /**
     * Get paginated list of SEO settings
     * Uses the model to fetch data following CI4 best practices
     * 
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function seo_settings_list()
    {
        // Check authentication and authorization
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('admin/login');
        }

        try {
            // Get parameters from request using CI4 IncomingRequest methods
            $params = [
                'limit' => $this->request->getGet('limit') ?? 10,
                'offset' => $this->request->getGet('offset') ?? 0,
                'sort' => $this->request->getGet('sort') ?? 'id',
                'order' => $this->request->getGet('order') ?? 'DESC',
                'search' => $this->request->getGet('search') ?? ''
            ];

            // Use model to get formatted list data
            $result = $this->seoModel->getFormattedSeoSettingsList($params);

            $permission = [
                'update' => $this->userPermissions['update']['seo_settings'],
                'delete' => $this->userPermissions['delete']['seo_settings']
            ];

            // Add operation buttons to each row
            foreach ($result['rows'] as &$row) {
                $row['operations'] = $this->generateOperationButtons($row['id'], $permission);
            }

            return $this->response->setJSON([
                'total' => $result['total'],
                'rows' => $result['rows']
            ]);
        } catch (\Exception $e) {
            // throw $e;
            log_message('error', 'SEO Settings list error in controller: ' . $e->getMessage());

            return $this->response->setJSON([
                'total' => 0,
                'rows' => [],
                'error' => labels(FAILED_TO_LOAD_SEO_SETTINGS, 'Failed to load SEO settings')
            ]);
        }
    }

    /**
     * Update existing SEO settings record
     * 
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function update_seo_settings()
    {
        // Check authentication and authorization
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('admin/login');
        }

        // Check demo mode restrictions
        $result = checkModificationInDemoMode($this->superadmin);
        if ($result !== true) {
            return $this->response->setJSON($result);
        }

        try {
            // Validate only default language fields are required
            // Other languages are optional
            $defaultLanguage = get_default_language();
            $validationErrors = [];

            // Validate ID (required for update)
            $id = $this->request->getPost('id');
            if (empty($id) || !is_numeric($id)) {
                $validationErrors['id'] = labels('id_is_required', 'ID is required');
            }

            // Validate page (always required)
            $page = $this->request->getPost('page');
            if (empty($page)) {
                $validationErrors['page'] = labels('page_field_is_required', 'Page field is required');
            }

            // Validate default language SEO fields
            $metaTitle = $this->request->getPost('meta_title');
            $defaultTitle = '';
            if (is_array($metaTitle) && !empty($metaTitle[$defaultLanguage])) {
                $defaultTitle = trim((string)$metaTitle[$defaultLanguage]);
            }
            if (empty($defaultTitle)) {
                $validationErrors['meta_title'] = labels('seo_title_in_default_language_is_required', 'SEO title in default language is required');
            } elseif (strlen($defaultTitle) > 255) {
                $validationErrors['meta_title'] = labels('title_cannot_exceed_255_characters', 'Title cannot exceed 255 characters');
            }

            $metaDescription = $this->request->getPost('meta_description');
            $defaultDescription = '';
            if (is_array($metaDescription) && !empty($metaDescription[$defaultLanguage])) {
                $defaultDescription = trim((string)$metaDescription[$defaultLanguage]);
            }
            if (empty($defaultDescription)) {
                $validationErrors['meta_description'] = labels('seo_description_in_default_language_is_required', 'SEO description in default language is required');
            } elseif (strlen($defaultDescription) > 500) {
                $validationErrors['meta_description'] = labels('description_cannot_exceed_500_characters', 'Description cannot exceed 500 characters');
            }

            $metaKeywords = $this->request->getPost('meta_keywords');
            $defaultKeywords = '';
            if (is_array($metaKeywords) && !empty($metaKeywords[$defaultLanguage])) {
                $keywordValue = $metaKeywords[$defaultLanguage];
                // Use parseKeywords to normalize Tagify JSON format to comma-separated string
                $defaultKeywords = $this->parseKeywords($keywordValue);
            }
            if (empty($defaultKeywords)) {
                $validationErrors['meta_keywords'] = labels('seo_keywords_in_default_language_are_required', 'SEO keywords in default language are required');
            }

            $schemaMarkupArray = $this->request->getPost('schema_markup');
            $defaultSchemaMarkup = '';
            if (is_array($schemaMarkupArray) && !empty($schemaMarkupArray[$defaultLanguage])) {
                $defaultSchemaMarkup = trim((string)$schemaMarkupArray[$defaultLanguage]);
            }
            if (empty($defaultSchemaMarkup)) {
                $validationErrors['schema_markup'] = labels('schema_markup_in_default_language_is_required', 'Schema markup in default language is required');
            }

            // Return validation errors if any
            if (!empty($validationErrors)) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => $validationErrors,
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash()
                ]);
            }

            // Get form data - this method extracts ONLY default language data
            $formData = $this->getValidatedData();

            // Handle image upload if new image is provided
            $image = $this->request->getFile('image');
            if ($image && $image->isValid() && !$image->hasMoved()) {
                // Get existing record to access old image
                $existingRecord = $this->seoModel->getSeoSettingsById($id);
                $oldImageName = $existingRecord ? $existingRecord['image'] : null;

                $imageResult = $this->handleImageUpload();
                if ($imageResult['error']) {
                    return $this->response->setJSON([
                        'error' => true,
                        'message' => $imageResult['message'],
                        'csrfName' => csrf_token(),
                        'csrfHash' => csrf_hash()
                    ]);
                }

                // Set new image name in form data
                $formData['image'] = $imageResult['path'];

                // Delete old image file if it exists and is different from new one
                if ($oldImageName && $oldImageName !== $imageResult['path']) {
                    $this->cleanupImageFile($oldImageName);
                }
            }

            // Use model to update the SEO settings
            $result = $this->seoModel->updateSeoSettings($id, $formData);

            // Process SEO translations if update was successful
            if (!$result['error']) {
                try {
                    // Get translated fields from form data
                    $translatedFields = $this->extractTranslatedFieldsFromPost();
                    if (!empty($translatedFields)) {
                        // Process translations using the seo_id
                        $this->processSeoTranslations($id, $translatedFields);
                    }
                } catch (\Exception $e) {
                    // Log error but don't fail the operation
                    log_message('error', 'SEO Translation processing error in update_seo_settings: ' . $e->getMessage());
                }
            }

            return $this->response->setJSON([
                'error' => $result['error'],
                'message' => $result['message'],
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash()
            ]);
        } catch (\Exception $e) {
            log_message('error', 'SEO Settings update error in controller: ' . $e->getMessage());

            return $this->response->setJSON([
                'error' => true,
                'message' => labels(ERROR_OCCURED, 'An error occurred'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash()
            ]);
        }
    }

    /**
     * Delete SEO settings record
     * 
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function delete_seo_settings()
    {
        // Check authentication and authorization
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('admin/login');
        }

        // Check demo mode restrictions
        $result = checkModificationInDemoMode($this->superadmin);
        if ($result !== true) {
            return $this->response->setJSON($result);
        }

        try {
            // Get and validate the record ID
            $id = $this->request->getPost('id');
            if (!$id || !is_numeric($id)) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(INVALID_RECORD_ID_PROVIDED, 'Invalid record ID provided'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash()
                ]);
            }

            // Clean up translations before deleting base SEO settings
            // Note: This happens before deletion, translations will be deleted via CASCADE anyway,
            // but we do it explicitly for consistency and potential logging
            try {
                $this->cleanupSeoTranslations($id);
            } catch (\Exception $e) {
                // Log error but continue with deletion
                log_message('error', 'SEO Translation cleanup error in delete_seo_settings: ' . $e->getMessage());
            }

            // Use model to delete the SEO settings
            $result = $this->seoModel->deleteSeoSettings($id);

            // If deletion was successful and there's an image, clean it up
            if (!$result['error'] && isset($result['deleted_record']['image'])) {
                $this->cleanupImageFile($result['deleted_record']['image']);
            }

            return $this->response->setJSON([
                'error' => $result['error'],
                'message' => $result['message'],
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash()
            ]);
        } catch (\Exception $e) {
            log_message('error', 'SEO Settings delete error in controller: ' . $e->getMessage());

            return $this->response->setJSON([
                'error' => true,
                'message' => labels(ERROR_OCCURED, 'An error occurred'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash()
            ]);
        }
    }

    /**
     * Get SEO settings data by ID for editing
     * 
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function get_seo_settings()
    {
        // Check authentication and authorization
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(UNAUTHORIZED_ACCESS, 'Unauthorized access'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash()
            ]);
        }

        try {
            // Get and validate the record ID
            $id = $this->request->getGet('id');
            if (!$id || !is_numeric($id)) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(INVALID_RECORD_ID_PROVIDED, 'Invalid record ID provided'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash()
                ]);
            }

            // Use model to get the SEO settings
            $seoData = $this->seoModel->getSeoSettingsById($id);

            if (!$seoData) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(DATA_NOT_FOUND, 'Data not found'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash()
                ]);
            }

            // Add image URL for display using CI4 URL helper
            if (!empty($seoData['image'])) {
                $seoData['image_url'] = $this->seoModel->getImageUrl($seoData['image']);
            }

            // Get all translations for this SEO setting
            try {
                $allLanguages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], "", '0', 'id', 'ASC');
                $seoTranslations = $this->translatedGeneralSeoModel->getAllTranslationsForSeo($id);

                // Merge translations into response similar to Blog controller pattern
                $mergedSeoSettings = $seoData;
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
                            'title' => $isDefault ? ($seoData['title'] ?? '') : '',
                            'description' => $isDefault ? ($seoData['description'] ?? '') : '',
                            'keywords' => $isDefault ? ($seoData['keywords'] ?? '') : '',
                            'schema_markup' => $isDefault ? ($seoData['schema_markup'] ?? '') : ''
                        ];
                    }
                }
                $seoData = $mergedSeoSettings;
            } catch (\Exception $e) {
                // Log error but don't fail the operation
                log_message('error', 'Error loading SEO translations in get_seo_settings: ' . $e->getMessage());
            }

            return $this->response->setJSON([
                'error' => false,
                'data' => $seoData,
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash()
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Get SEO Settings error in controller: ' . $e->getMessage());

            return $this->response->setJSON([
                'error' => true,
                'message' => labels(FAILED_TO_LOAD_DATA, 'Failed to load data'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash()
            ]);
        }
    }

    /**
     * Get existing pages that already have SEO settings
     * Uses the model to fetch data following CI4 best practices
     * 
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function get_existing_pages()
    {
        // Check authentication and authorization
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(UNAUTHORIZED_ACCESS, 'Unauthorized access'),
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash()
            ]);
        }

        try {
            // Use model to get existing pages
            $existingPages = $this->seoModel->getExistingPages();

            return $this->response->setJSON([
                'error' => false,
                'existing_pages' => $existingPages,
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash()
            ]);
        } catch (\Exception $e) {
            log_message('error', 'Get existing pages error in controller: ' . $e->getMessage());

            return $this->response->setJSON([
                'error' => true,
                'message' => labels(FAILED_TO_LOAD_DATA, 'Failed to load data'),
                'existing_pages' => [],
                'csrfName' => csrf_token(),
                'csrfHash' => csrf_hash()
            ]);
        }
    }

    // PRIVATE HELPER METHODS FOR CONTROLLER LOGIC

    /**
     * Get validated form data using CI4 validation
     * 
     * IMPORTANT: Only extracts DEFAULT LANGUAGE data for validation
     * Only default language fields are required. Other languages are optional.
     * 
     * This method prioritizes extracting default language values from multilingual fields
     * (meta_title[lang], meta_description[lang], etc.) for validation.
     * 
     * @return array - Cleaned and validated form data (only default language)
     */
    private function getValidatedData()
    {
        $defaultLanguage = get_default_language();

        // Get page (always required, not language-specific)
        $page = $this->request->getPost('page');

        // Extract DEFAULT LANGUAGE data from multilingual fields
        // Priority: multilingual fields (meta_title[lang]) > base fields (title)
        // This ensures we validate only default language data

        // Title: Get from meta_title[default_lang] or fallback to base 'title' field
        $title = '';
        $metaTitle = $this->request->getPost('meta_title');
        if (is_array($metaTitle) && !empty($metaTitle[$defaultLanguage])) {
            $title = trim((string)$metaTitle[$defaultLanguage]);
        } else {
            // Fallback to base field for backward compatibility
            $baseTitle = $this->request->getPost('title');
            $title = is_string($baseTitle) ? trim($baseTitle) : '';
        }

        // Description: Get from meta_description[default_lang] or fallback to base 'description' field
        $description = '';
        $metaDescription = $this->request->getPost('meta_description');
        if (is_array($metaDescription) && !empty($metaDescription[$defaultLanguage])) {
            $description = trim((string)$metaDescription[$defaultLanguage]);
        } else {
            // Fallback to base field for backward compatibility
            $baseDescription = $this->request->getPost('description');
            $description = is_string($baseDescription) ? trim($baseDescription) : '';
        }

        // Keywords: Get from meta_keywords[default_lang] or fallback to base 'keywords' field
        $keywords = '';
        $metaKeywords = $this->request->getPost('meta_keywords');
        if (is_array($metaKeywords) && !empty($metaKeywords[$defaultLanguage])) {
            $keywordValue = $metaKeywords[$defaultLanguage];
            // Use parseKeywords to normalize Tagify JSON format to comma-separated string
            $keywords = $this->parseKeywords($keywordValue);
        } else {
            // Fallback to base field for backward compatibility
            $baseKeywords = $this->request->getPost('keywords');
            $keywords = $this->parseKeywords($baseKeywords);
        }

        // Schema Markup: Get from schema_markup[default_lang] or fallback to base 'schema_markup' field
        $schemaMarkup = '';
        $schemaMarkupArray = $this->request->getPost('schema_markup');
        if (is_array($schemaMarkupArray) && !empty($schemaMarkupArray[$defaultLanguage])) {
            $schemaMarkup = trim((string)$schemaMarkupArray[$defaultLanguage]);
        } else {
            // Fallback to base field for backward compatibility
            $baseSchemaMarkup = $this->request->getPost('schema_markup');
            // Handle case where base field might also be an array (shouldn't happen, but safety check)
            if (is_array($baseSchemaMarkup)) {
                $schemaMarkup = '';
            } else {
                $schemaMarkup = is_string($baseSchemaMarkup) ? trim($baseSchemaMarkup) : '';
            }
        }

        // Return data - only default language fields are validated as required
        return [
            'page' => $page,
            'title' => $title,
            'description' => $description,
            'keywords' => $keywords,
            'schema_markup' => $schemaMarkup
        ];
    }

    /**
     * Handle image file upload with validation using CI4 file upload
     * Supports both local server and AWS S3 storage based on disk configuration
     * 
     * @return array - Upload result with success/error status
     */
    private function handleImageUpload()
    {
        $image = $this->request->getFile('image');

        // Check if image is provided and valid
        if (!$image || !$image->isValid() || $image->hasMoved()) {
            return [
                'error' => true,
                'message' => labels(INVALID_IMAGE_FILE, 'Invalid image file')
            ];
        }

        // Validate image file type using CI4 validation
        $validationRules = [
            'image' => [
                'rules' => 'uploaded[image]|is_image[image]|mime_in[image,image/jpg,image/jpeg,image/png]|max_size[image,5120]',
                'errors' => [
                    'uploaded' => labels(PLEASE_UPLOAD_AN_IMAGE_FILE, 'Please upload an image file'),
                    'is_image' => labels(FILE_MUST_BE_A_VALID_IMAGE, 'File must be a valid image'),
                    'mime_in' => labels(ONLY_JPEG_JPG_AND_PNG_FILES_ARE_ALLOWED, 'Only JPEG, JPG, and PNG files are allowed'),
                    'max_size' => labels(IMAGE_FILE_SIZE_MUST_NOT_EXCEED_5MB, 'Image file size must not exceed 5MB')
                ]
            ]
        ];

        if (!$this->validate($validationRules)) {
            $errors = $this->validator->getErrors();
            return [
                'error' => true,
                'message' => reset($errors) // Get first error message
            ];
        }


        $uploadResult = $this->fileService->upload(
            file: $image,
            folder: 'seo_settings'
        );

        // Check if upload was successful
        if ($uploadResult['error']) {
            return [
                'error' => true,
                'message' => $uploadResult['message']
            ];
        }

        return [
            'error' => false,
            'path' => $uploadResult['path'],
            'disk' => $uploadResult['disk'],
            'message' => $uploadResult['message']
        ];
    }

    /**
     * Clean up image file when record is deleted
     * Supports both local server and AWS S3 storage based on disk configuration
     * 
     * @param string $imageName - Name of the image file to delete
     * @return void
     */
    private function cleanupImageFile($imageName)
    {
        if (empty($imageName)) {
            return;
        }

        try {
            // Use the project's delete helper function which handles both local and AWS S3
            $result = $this->fileService->delete(folder: 'seo_settings', path: $imageName);

            if ($result) {
                log_message('info', 'SEO image file deleted successfully: ' . $imageName);
            } else {
                log_message('error', 'Failed to delete SEO image file: ' . $imageName);
            }
        } catch (\Exception $e) {
            log_message('error', 'Failed to cleanup SEO image file: ' . $e->getMessage());
        }
    }

    /**
     * Generate operation buttons (Edit/Delete) for each SEO setting record
     * Using CSS classes for Bootstrap Table events instead of onclick
     * 
     * @param int $id - Record ID
     * @return string - HTML for operation buttons
     */
    private function generateOperationButtons($id, $permissions)
    {
        $operations = '<div class="dropdown">
        <a class="" href="#" role="button" id="dropdownMenuLink_' . $id . '" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <button class="btn btn-secondary btn-sm px-3">
                <i class="fas fa-ellipsis-v"></i>
            </button>
        </a>
        <div class="dropdown-menu" aria-labelledby="dropdownMenuLink_' . $id . '">';

        // Edit button
        if (!empty($permissions['update']) && $permissions['update'] == 1) {
            $operations .= '<a class="dropdown-item edit_seo_setting" data-id="' . $id . '" title="Edit">
            <i class="fa fa-pen text-primary mr-1" aria-hidden="true"></i>' . labels('edit', 'Edit') . '</a>';
        }

        // Delete button
        if (!empty($permissions['delete']) && $permissions['delete'] == 1) {
            $operations .= '<a class="dropdown-item delete_seo_setting" data-id="' . $id . '">
            <i class="fa fa-trash text-danger mr-1" aria-hidden="true"></i>' . labels('delete', 'Delete') . '</a>';
        }

        $operations .= '</div></div>';

        return $operations;
    }

    /**
     * Parse keywords from various formats (Tagify JSON, array, string) to comma-separated string
     * 
     * Handles:
     * - JSON string format: '[{"value":"tag1"},{"value":"tag2"}]'
     * - Array containing JSON string: ['[{"value":"tag1"}]']
     * - Array of objects: [{"value":"tag1"},{"value":"tag2"}]
     * - Comma-separated string: 'tag1,tag2'
     * 
     * @param mixed $input - Keywords in various formats
     * @return string - Comma-separated keywords string
     */
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
     * Extract translated SEO fields from POST data
     * 
     * Handles both direct field[lang] format and translated_fields JSON format
     * 
     * @return array Translated fields in field[lang] format
     */
    private function extractTranslatedFieldsFromPost(): array
    {
        $translatedFields = [
            'seo_title' => [],
            'seo_description' => [],
            'seo_keywords' => [],
            'seo_schema_markup' => []
        ];

        // Try to get from translated_fields JSON first
        $translatedFieldsData = $this->request->getPost('translated_fields');
        if (is_string($translatedFieldsData)) {
            $translatedFieldsData = json_decode($translatedFieldsData, true);
        }

        // If translated_fields exists and has SEO data, use it
        if (!empty($translatedFieldsData) && is_array($translatedFieldsData)) {
            if (isset($translatedFieldsData['seo_title'])) {
                $translatedFields['seo_title'] = $translatedFieldsData['seo_title'] ?? [];
            }
            if (isset($translatedFieldsData['seo_description'])) {
                $translatedFields['seo_description'] = $translatedFieldsData['seo_description'] ?? [];
            }
            if (isset($translatedFieldsData['seo_keywords'])) {
                $translatedFields['seo_keywords'] = $translatedFieldsData['seo_keywords'] ?? [];
            }
            if (isset($translatedFieldsData['seo_schema_markup'])) {
                $translatedFields['seo_schema_markup'] = $translatedFieldsData['seo_schema_markup'] ?? [];
            }

            // If we have data, return it
            if (!empty($translatedFields['seo_title']) || !empty($translatedFields['seo_description'])) {
                return $translatedFields;
            }
        }

        // Fallback: Extract from direct field[lang] format in POST
        $allPostData = $this->request->getPost();
        $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], "", '0', 'id', 'ASC');

        foreach ($languages as $language) {
            $languageCode = $language['code'];

            // Extract SEO fields (using meta_ prefix from form)
            $metaTitleField = 'meta_title[' . $languageCode . ']';
            $metaDescriptionField = 'meta_description[' . $languageCode . ']';
            $metaKeywordsField = 'meta_keywords[' . $languageCode . ']';
            $schemaMarkupField = 'schema_markup[' . $languageCode . ']';

            // Check for array format first (modern form submission)
            if (isset($allPostData['meta_title']) && is_array($allPostData['meta_title'])) {
                if (isset($allPostData['meta_title'][$languageCode])) {
                    $translatedFields['seo_title'][$languageCode] = trim((string)$allPostData['meta_title'][$languageCode]);
                }
            } elseif (isset($allPostData[$metaTitleField])) {
                $translatedFields['seo_title'][$languageCode] = trim((string)$allPostData[$metaTitleField]);
            }

            if (isset($allPostData['meta_description']) && is_array($allPostData['meta_description'])) {
                if (isset($allPostData['meta_description'][$languageCode])) {
                    $translatedFields['seo_description'][$languageCode] = trim((string)$allPostData['meta_description'][$languageCode]);
                }
            } elseif (isset($allPostData[$metaDescriptionField])) {
                $translatedFields['seo_description'][$languageCode] = trim((string)$allPostData[$metaDescriptionField]);
            }

            if (isset($allPostData['meta_keywords']) && is_array($allPostData['meta_keywords'])) {
                if (isset($allPostData['meta_keywords'][$languageCode])) {
                    $keywordValue = $allPostData['meta_keywords'][$languageCode];
                    // Use parseKeywords to normalize Tagify JSON format to comma-separated string
                    $translatedFields['seo_keywords'][$languageCode] = $this->parseKeywords($keywordValue);
                }
            } elseif (isset($allPostData[$metaKeywordsField])) {
                $keywordValue = $allPostData[$metaKeywordsField];
                // Use parseKeywords to normalize Tagify JSON format to comma-separated string
                $translatedFields['seo_keywords'][$languageCode] = $this->parseKeywords($keywordValue);
            }

            if (isset($allPostData['schema_markup']) && is_array($allPostData['schema_markup'])) {
                if (isset($allPostData['schema_markup'][$languageCode])) {
                    $translatedFields['seo_schema_markup'][$languageCode] = trim((string)$allPostData['schema_markup'][$languageCode]);
                }
            } elseif (isset($allPostData[$schemaMarkupField])) {
                $translatedFields['seo_schema_markup'][$languageCode] = trim((string)$allPostData[$schemaMarkupField]);
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
                    // Parse keywords similar to getValidatedData
                    if (!empty($value)) {
                        if (is_array($value)) {
                            $restructured[$languageCode][$field] = implode(',', array_map('trim', $value));
                        } else {
                            $restructured[$languageCode][$field] = trim((string)$value);
                        }
                    } else {
                        $restructured[$languageCode][$field] = '';
                    }
                } else {
                    $restructured[$languageCode][$field] = $value !== null ? trim((string)$value) : '';
                }
            }

            // Remove language only if *everything* truly empty
            if (implode('', $restructured[$languageCode]) === '') {
                unset($restructured[$languageCode]);
            }
        }

        return $restructured;
    }

    /**
     * Process SEO translations from form data
     * 
     * @param int $seoId SEO ID from seo_settings table
     * @param array|null $translatedFields Translated fields data (optional, will extract from POST if not provided)
     * @return void
     */
    private function processSeoTranslations(int $seoId, ?array $translatedFields = null): void
    {
        try {
            // Use provided translated fields or extract from POST request
            if ($translatedFields === null) {
                $translatedFields = $this->extractTranslatedFieldsFromPost();
            }

            // Process SEO translations if data is provided
            if (!empty($translatedFields) && is_array($translatedFields)) {
                // Restructure data for the model (convert field[lang] to lang[field] format)
                $restructuredData = $this->restructureTranslatedFieldsForSeoModel($translatedFields);

                // Process and store the SEO translations
                $seoTranslationResult = $this->translatedGeneralSeoModel->processSeoTranslations($seoId, $restructuredData);

                // Check if SEO translation processing was successful
                if (!$seoTranslationResult['success']) {
                    throw new \Exception('SEO Translation processing failed: ' . json_encode($seoTranslationResult['errors']));
                }
            }
        } catch (\Exception $e) {
            // Log any exceptions but don't fail the operation
            log_message('error', 'Exception in processSeoTranslations for seo_id ' . $seoId . ': ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Clean up SEO translations when base SEO settings are deleted
     * 
     * Removes all multilanguage SEO translations when the base SEO record is deleted
     * 
     * @param int $seoId The SEO ID from seo_settings table
     * @return void
     */
    private function cleanupSeoTranslations(int $seoId): void
    {
        try {
            // Delete all SEO translations for this seo_id
            $this->translatedGeneralSeoModel->deleteSeoTranslations($seoId);
        } catch (\Exception $e) {
            log_message('error', 'Exception in cleanupSeoTranslations for seo_id ' . $seoId . ': ' . $e->getMessage());
            throw $e;
        }
    }
}
