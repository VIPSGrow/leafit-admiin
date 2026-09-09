<?php

namespace App\Models;

use CodeIgniter\Model;

class Seo_model extends Model
{
    protected $table = 'seo_settings';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'page',
        'title',
        'description',
        'keywords',
        'image',
        'schema_markup',
        'created_at',
        'updated_at'
    ];

    // Enable automatic timestamps using CI4 best practices
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $dateFormat = 'datetime';

    // Return type configuration
    protected $returnType = 'array';
    protected $returnTypeFormatted = 'App\Entities\SeoEntity';

    // Validation rules for SEO settings following CI4 best practices
    protected $validationRules = [
        'page' => 'required|max_length[100]|alpha_dash',
        'title' => 'required|max_length[255]',
        'description' => 'required|max_length[500]',
        'keywords' => 'required',
        'schema_markup' => 'required'
    ];

    protected $validationMessages = [
        'page' => [
            'required' => 'page_field_is_required',
            'max_length' => 'page_name_cannot_exceed_100_characters',
            'alpha_dash' => 'page_name_can_only_contain_alphanumeric_characters_and_dashes'
        ],
        'title' => [
            'required' => 'seo_title_is_required',
            'max_length' => 'title_cannot_exceed_255_characters'
        ],
        'description' => [
            'required' => 'seo_description_is_required',
            'max_length' => 'description_cannot_exceed_500_characters'
        ],
        'keywords' => [
            'required' => 'seo_keywords_are_required',
        ],
        'schema_markup' => [
            'required' => 'schema_markup_is_required',
        ]
    ];

    // Skip validation for certain operations
    protected $skipValidation = false;

    // SEO table mapping for different types
    protected $seoTables = [
        'general' => 'seo_settings',
        'services' => 'services_seo_settings',
        'categories' => 'categories_seo_settings',
        'providers' => 'partners_seo_settings',
        'blogs' => 'blogs_seo_settings'
    ];

    protected $currentTable = 'seo_settings'; // Default table
    protected $currentType = 'general'; // Default type

    public function getValidationRules(array $options = []): array
    {
        return $this->validationRules;
    }

    public function getValidationMessages(): array
    {
        return $this->validationMessages;
    }

    // Set table context for different SEO types
    public function setTableContext($type)
    {
        if (isset($this->seoTables[$type])) {
            $this->currentType = $type;
            $this->currentTable = $this->seoTables[$type];
            $this->table = $this->currentTable;

            // Update allowed fields based on type
            $this->updateAllowedFields($type);

            // Adjust validation rules based on type
            $this->adjustValidationRules($type);
        }
        return $this;
    }

    // Update allowed fields based on SEO type
    private function updateAllowedFields($type)
    {
        // Base fields for all types (no page field for individual items)
        $baseFields = [
            'title',
            'description',
            'keywords',
            'image',
            'schema_markup',
            'created_at',
            'updated_at'
        ];

        switch ($type) {
            case 'services':
                $this->allowedFields = array_merge($baseFields, ['service_id']);
                break;
            case 'categories':
                $this->allowedFields = array_merge($baseFields, ['category_id']);
                break;
            case 'providers':
                $this->allowedFields = array_merge($baseFields, ['partner_id']);
                break;
            case 'blogs':
                $this->allowedFields = array_merge($baseFields, ['blog_id']);
                break;
            default:
                // Keep original allowed fields for general (includes page field)
                $this->allowedFields = [
                    'page',
                    'title',
                    'description',
                    'keywords',
                    'image',
                    'schema_markup',
                    'created_at',
                    'updated_at'
                ];
                break;
        }
    }

    // Adjust validation rules based on SEO type
    private function adjustValidationRules($type)
    {
        switch ($type) {
            case 'services':
                // Optional validation for services - only validate if provided
                $this->validationRules = [
                    'service_id' => 'required|numeric', // This is required to identify which service
                    'title' => 'permit_empty|max_length[255]',
                    'description' => 'permit_empty|max_length[500]',
                    'keywords' => 'permit_empty',
                    'schema_markup' => 'permit_empty'
                ];
                break;
            case 'categories':
                // Optional validation for categories - only validate if provided
                $this->validationRules = [
                    'category_id' => 'required|numeric', // This is required to identify which category
                    'title' => 'permit_empty|max_length[255]',
                    'description' => 'permit_empty|max_length[500]',
                    'keywords' => 'permit_empty',
                    'schema_markup' => 'permit_empty'
                ];
                break;
            case 'providers':
                // Optional validation for providers - only validate if provided
                $this->validationRules = [
                    'partner_id' => 'required|numeric', // This is required to identify which provider
                    'title' => 'permit_empty|max_length[255]',
                    'description' => 'permit_empty|max_length[500]',
                    'keywords' => 'permit_empty',
                    'schema_markup' => 'permit_empty'
                ];
                break;
            case 'blogs':
                $this->validationRules = [
                    'blog_id' => 'required|numeric', // This is required to identify which blog
                    'title' => 'permit_empty|max_length[255]',
                    'description' => 'permit_empty|max_length[500]',
                    'keywords' => 'permit_empty',
                    'schema_markup' => 'permit_empty'
                ];
                break;
            default:
                // Keep strict validation for general pages (required fields)
                $this->validationRules = [
                    'page' => 'required|max_length[100]|alpha_dash',
                    'title' => 'required|max_length[255]',
                    'description' => 'required|max_length[500]',
                    'keywords' => 'required',
                    'schema_markup' => 'required'
                ];
                break;
        }

        // Update validation messages based on type
        $this->updateValidationMessages($type);
    }

    // Update validation messages based on SEO type
    private function updateValidationMessages($type)
    {
        switch ($type) {
            case 'services':
                $this->validationMessages = [
                    'service_id' => [
                        'required' => 'service_selection_is_required',
                        'numeric' => 'service_id_must_be_a_valid_number'
                    ],
                    'title' => [
                        'max_length' => 'title_cannot_exceed_255_characters'
                    ],
                    'description' => [
                        'max_length' => 'description_cannot_exceed_500_characters'
                    ],
                    'keywords' => [
                        'required' => 'seo_keywords_are_required',
                    ],
                    'schema_markup' => [
                        'required' => 'schema_markup_is_required',
                    ]
                ];
                break;
            case 'categories':
                $this->validationMessages = [
                    'category_id' => [
                        'required' => 'category_selection_is_required',
                        'numeric' => 'category_id_must_be_a_valid_number'
                    ],
                    'title' => [
                        'max_length' => 'title_cannot_exceed_255_characters'
                    ],
                    'description' => [
                        'max_length' => 'description_cannot_exceed_500_characters'
                    ],
                    'keywords' => [
                        'required' => 'seo_keywords_are_required',
                    ],
                    'schema_markup' => [
                        'required' => 'schema_markup_is_required',
                    ]
                ];
                break;
            case 'providers':
                $this->validationMessages = [
                    'partner_id' => [
                        'required' => 'provider_selection_is_required',
                        'numeric' => 'provider_id_must_be_a_valid_number'
                    ],
                    'title' => [
                        'max_length' => 'title_cannot_exceed_255_characters'
                    ],
                    'description' => [
                        'max_length' => 'description_cannot_exceed_500_characters'
                    ],
                    'keywords' => [
                        'required' => 'seo_keywords_are_required',
                    ],
                    'schema_markup' => [
                        'required' => 'schema_markup_is_required',
                    ]
                ];
                break;
            case 'blogs':
                $this->validationMessages = [
                    'blog_id' => [
                        'required' => 'blog_selection_is_required',
                        'numeric' => 'blog_id_must_be_a_valid_number'
                    ],
                    'title' => [
                        'max_length' => 'title_cannot_exceed_255_characters'
                    ],
                    'description' => [
                        'max_length' => 'description_cannot_exceed_500_characters'
                    ],
                    'keywords' => [
                        'required' => 'seo_keywords_are_required',
                    ],
                    'schema_markup' => [
                        'required' => 'schema_markup_is_required',
                    ]
                ];
                break;
            default:
                // Keep strict validation messages for general pages
                $this->validationMessages = [
                    'page' => [
                        'required' => 'page_field_is_required',
                        'max_length' => 'page_name_cannot_exceed_100_characters',
                        'alpha_dash' => 'page_name_can_only_contain_alphanumeric_characters_and_dashes'
                    ],
                    'title' => [
                        'required' => 'seo_title_is_required',
                        'max_length' => 'title_cannot_exceed_255_characters'
                    ],
                    'description' => [
                        'required' => 'seo_description_is_required',
                        'max_length' => 'description_cannot_exceed_500_characters'
                    ],
                    'keywords' => [
                        'required' => 'seo_keywords_are_required',
                    ],
                    'schema_markup' => [
                        'required' => 'schema_markup_is_required',
                    ]
                ];
                break;
        }
    }

    public function getSeoSettingsList($params = [])
    {
        // Set default parameters with type casting
        $limit = (int) ($params['limit'] ?? 10);
        $offset = (int) ($params['offset'] ?? 0);
        $sort = esc($params['sort'] ?? 'id');
        $order = in_array(strtoupper($params['order'] ?? 'DESC'), ['ASC', 'DESC']) ? strtoupper($params['order']) : 'DESC';
        $search = esc($params['search'] ?? '');

        // Build the query using model's builder - CI4 best practice
        $builder = $this->builder();

        // Add joins and select fields based on current type
        switch ($this->currentType) {
            case 'services':
                $builder->join('services s', 's.id = ' . $this->currentTable . '.service_id', 'left');
                $builder->select($this->currentTable . '.*, s.title as item_title, s.status as item_status');

                // Add search conditions for services
                if (!empty($search)) {
                    $builder->groupStart()
                        ->like('s.title', $search)
                        ->orLike($this->currentTable . '.title', $search)
                        ->orLike($this->currentTable . '.description', $search)
                        ->orLike($this->currentTable . '.keywords', $search)
                        ->groupEnd();
                }
                break;

            case 'categories':
                $builder->join('categories c', 'c.id = ' . $this->currentTable . '.category_id', 'left');
                $builder->select($this->currentTable . '.*, c.name as item_title, c.status as item_status');

                // Add search conditions for categories
                if (!empty($search)) {
                    $builder->groupStart()
                        ->like('c.name', $search)
                        ->orLike($this->currentTable . '.title', $search)
                        ->orLike($this->currentTable . '.description', $search)
                        ->orLike($this->currentTable . '.keywords', $search)
                        ->groupEnd();
                }
                break;

            case 'providers':
                $builder->join('partner_details pd', 'pd.partner_id = ' . $this->currentTable . '.partner_id', 'left');
                $builder->join('users u', 'u.id = pd.partner_id', 'left');
                $builder->select($this->currentTable . '.*, pd.company_name as item_title, pd.is_approved as item_status');

                // Add search conditions for providers
                if (!empty($search)) {
                    $builder->groupStart()
                        ->like('pd.company_name', $search)
                        ->orLike($this->currentTable . '.title', $search)
                        ->orLike($this->currentTable . '.description', $search)
                        ->orLike($this->currentTable . '.keywords', $search)
                        ->groupEnd();
                }
                break;

            default:
                // General pages (existing logic)
                $builder->select($this->currentTable . '.*');

                // Exclude custom page SEO entries — those are managed via Custom Pages
                $builder->notLike('page', 'custom_page_', 'after');

                // Add search conditions for general pages
                if (!empty($search)) {
                    $builder->groupStart()
                        ->like('page', $search)
                        ->orLike('title', $search)
                        ->orLike('description', $search)
                        ->orLike('keywords', $search)
                        ->groupEnd();
                }
                break;
        }

        // Get total count for pagination
        $totalCount = $builder->countAllResults(false);

        // Apply sorting and pagination
        $builder->orderBy($sort, $order);
        $builder->limit($limit, $offset);

        // Get the results
        $results = $builder->get()->getResultArray();

        return [
            'total' => $totalCount,
            'data' => $results
        ];
    }

    /**
     * Create new SEO settings record using CI4 best practices
     * 
     * @param array $data - SEO settings data
     * @return array - Response with success/error status
     */
    public function createSeoSettings($data)
    {
        try {
            // For individual items, clean empty fields before saving
            if ($this->currentType !== 'general') {
                $data = $this->cleanEmptyFields($data);

                // Restore NOT NULL text fields removed by cleanEmptyFields so MySQL doesn't error
                foreach (['title', 'description', 'keywords', 'schema_markup', 'image'] as $f) {
                    if (!array_key_exists($f, $data)) {
                        $data[$f] = '';
                    }
                }

                // If all SEO fields are empty, don't create the record
                if ($this->areAllSeoFieldsEmpty($data)) {
                    return [
                        'error' => false,
                        'message' => labels(DATA_NOT_FOUND),
                        'insert_id' => null
                    ];
                }
            }

            // Check for existing record based on type
            $existingCheck = $this->checkExistingRecord($data);
            if ($existingCheck) {
                return [
                    'error' => true,
                    'message' => $existingCheck
                ];
            }

            // Insert the data using model's insert method with automatic validation
            $insertId = $this->insert($data);

            if ($insertId) {
                return [
                    'error' => false,
                    'message' => labels(DATA_SAVED_SUCCESSFULLY),
                    'insert_id' => $insertId
                ];
            } else {
                return [
                    'error' => true,
                    'message' => labels(ERROR_OCCURED),
                    'validation_errors' => $this->errors()
                ];
            }
        } catch (\Exception $e) {
            log_message('error', 'SEO Model createSeoSettings error: ' . $e->getMessage());
            return [
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG)
            ];
        }
    }

    /**
     * Update existing SEO settings record using CI4 best practices
     * 
     * This method properly handles clearing individual SEO fields by preserving
     * empty values in the data array instead of removing them entirely.
     * This allows users to clear individual fields and have the changes reflected.
     * 
     * @param int $id - Record ID to update
     * @param array $data - Updated SEO settings data
     * @return array - Response with success/error status
     */
    public function updateSeoSettings($id, $data)
    {
        try {
            // Check if record exists
            $existing = $this->find($id);
            if (!$existing) {
                return [
                    'error' => true,
                    'message' => labels(DATA_NOT_FOUND)
                ];
            }

            // For individual items, handle empty fields appropriately for updates
            if ($this->currentType !== 'general') {
                // For updates, we need to preserve empty values that were intentionally set
                // This allows clearing individual fields to work properly
                // Don't use cleanEmptyFields for updates as it removes empty values

                // Check if all SEO fields are empty (excluding image and ID fields)
                $seoFields = ['title', 'description', 'keywords', 'schema_markup'];
                $allSeoFieldsEmpty = true;
                foreach ($seoFields as $field) {
                    if (isset($data[$field]) && !empty(trim($data[$field]))) {
                        $allSeoFieldsEmpty = false;
                        break;
                    }
                }

                // If all SEO fields are empty AND there's no image, delete the record instead of updating
                // BUT: If there's an image present, we should NOT delete the record even if all other fields are empty
                // This preserves the SEO record structure for future use
                $hasImage = !empty($existing['image']) || (isset($data['image']) && !empty($data['image']));
                if ($allSeoFieldsEmpty && !$hasImage) {
                    $deleted = $this->delete($id);
                    if ($deleted) {
                        return [
                            'error' => false,
                            'message' => labels(DATA_DELETED_SUCCESSFULLY),
                            'old_image' => $existing['image']
                        ];
                    }
                }
            }

            // Check for duplicate based on type (if changing the reference ID)
            $duplicateCheck = $this->checkDuplicateOnUpdate($id, $data, $existing);
            if ($duplicateCheck) {
                return [
                    'error' => true,
                    'message' => $duplicateCheck
                ];
            }

            if (!array_key_exists('image', $data)) {
                $data['image'] = $existing['image']; // only preserve if image key is completely missing
            }


            // Update the record using CI4 model method with automatic validation
            $updated = $this->update($id, $data);

            if ($updated) {
                return [
                    'error' => false,
                    'message' => labels(DATA_UPDATED_SUCCESSFULLY),
                    'old_image' => $existing['image'] // Return old image for cleanup if needed
                ];
            } else {
                return [
                    'error' => true,
                    'message' => labels(ERROR_OCCURED),
                    'validation_errors' => $this->errors()
                ];
            }
        } catch (\Exception $e) {
            log_message('error', 'SEO Model updateSeoSettings error: ' . $e->getMessage());
            return [
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG)
            ];
        }
    }

    /**
     * Delete SEO settings record using CI4 best practices
     * 
     * @param int $id - Record ID to delete
     * @return array - Response with success/error status
     */
    public function deleteSeoSettings($id)
    {
        try {
            // Check if record exists
            $existing = $this->find($id);
            if (!$existing) {
                return [
                    'error' => true,
                    'message' => labels(DATA_NOT_FOUND)
                ];
            }

            // Delete the record using CI4 model method
            $deleted = $this->delete($id);

            if ($deleted) {
                return [
                    'error' => false,
                    'message' => labels(DATA_DELETED_SUCCESSFULLY),
                    'deleted_record' => $existing
                ];
            } else {
                return [
                    'error' => true,
                    'message' => labels(ERROR_OCCURED)
                ];
            }
        } catch (\Exception $e) {
            log_message('error', 'SEO Model deleteSeoSettings error: ' . $e->getMessage());
            return [
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG)
            ];
        }
    }

    /**
     * Get SEO settings by ID using CI4 best practices
     * 
     * @param int $id - Record ID
     * @return array|null - SEO settings data or null if not found
     */
    public function getSeoSettingsById($id)
    {
        try {
            return $this->find($id);
        } catch (\Exception $e) {
            log_message('error', 'SEO Model getSeoSettingsById error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get SEO settings by page name with optional formatting
     * 
     * @param string $page - Page name
     * @param string $format - Format type: 'raw', 'full', 'compact', 'meta'
     * @return array|null - SEO settings data or null if not found
     */
    public function getSeoSettingsByPage($page, $format = 'raw')
    {
        try {
            // If raw format is requested, return array data directly
            if ($format === 'raw') {
                return $this->where('page', $page)->first();
            }

            // For formatted data, temporarily switch to entity return type
            $originalReturnType = $this->returnType;
            $this->returnType = $this->returnTypeFormatted;

            $seoEntity = $this->where('page', $page)->first();

            // Restore original return type
            $this->returnType = $originalReturnType;

            if (!$seoEntity) {
                return null;
            }

            // Ensure we have a SeoEntity object, not an array
            if (is_array($seoEntity)) {
                $seoEntity = new \App\Entities\SeoEntity($seoEntity);
            }

            // Return formatted data based on requested format
            switch ($format) {
                case 'compact':
                    return $seoEntity->getCompactData();
                case 'meta':
                    return $seoEntity->getMetaData();
                case 'full':
                default:
                    return $seoEntity->getFormattedData();
            }
        } catch (\Exception $e) {
            log_message('error', 'SEO Model getSeoSettingsByPage error: ' . $e->getMessage());

            // Ensure return type is restored even on error
            if (isset($originalReturnType)) {
                $this->returnType = $originalReturnType;
            }

            return null;
        }
    }

    /**
     * Get SEO data by reference ID (service_id, category_id, provider_id) with optional formatting
     * 
     * @param int $referenceId - Reference ID
     * @param string $format - Format type: 'raw', 'full', 'compact', 'meta'
     * @return array|null - SEO settings data or null if not found
     */
    public function getSeoSettingsByReferenceId($referenceId, $format = 'raw')
    {
        try {
            // If raw format is requested, return array data directly
            if ($format === 'raw') {
                switch ($this->currentType) {
                    case 'services':
                        return $this->where('service_id', $referenceId)->first();
                    case 'categories':
                        return $this->where('category_id', $referenceId)->first();
                    case 'providers':
                        return $this->where('partner_id', $referenceId)->first();
                    case 'blogs':
                        return $this->where('blog_id', $referenceId)->first();
                    default:
                        return null;
                }
            }

            // For formatted data, temporarily switch to entity return type
            $originalReturnType = $this->returnType;
            $this->returnType = $this->returnTypeFormatted;

            $seoEntity = null;
            switch ($this->currentType) {
                case 'services':
                    $seoEntity = $this->where('service_id', $referenceId)->first();
                    break;
                case 'categories':
                    $seoEntity = $this->where('category_id', $referenceId)->first();
                    break;
                case 'providers':
                    $seoEntity = $this->where('partner_id', $referenceId)->first();
                    break;
                case 'blogs':
                    $seoEntity = $this->where('blog_id', $referenceId)->first();
                    break;
                default:
                    $seoEntity = null;
                    break;
            }

            // Restore original return type
            $this->returnType = $originalReturnType;

            if (!$seoEntity) {
                return null;
            }

            // Ensure we have a SeoEntity object, not an array
            if (is_array($seoEntity)) {
                $seoEntity = new \App\Entities\SeoEntity($seoEntity);
            }

            // Return formatted data based on requested format
            switch ($format) {
                case 'compact':
                    return $seoEntity->getCompactData();
                case 'meta':
                    return $seoEntity->getMetaData();
                case 'full':
                default:
                    return $seoEntity->getFormattedData();
            }
        } catch (\Exception $e) {
            log_message('error', 'SEO Model getSeoSettingsByReferenceId error: ' . $e->getMessage());

            // Ensure return type is restored even on error
            if (isset($originalReturnType)) {
                $this->returnType = $originalReturnType;
            }

            return null;
        }
    }


    /**
     * Check if an item has SEO settings
     * 
     * @param int $referenceId - Reference ID
     * @return bool - True if has SEO settings, false otherwise
     */
    public function hasSeoSettings($referenceId)
    {
        return $this->getSeoSettingsByReferenceId($referenceId) !== null;
    }

    /**
     * Check if an item has meaningful SEO content (not just empty fields)
     * 
     * @param int $referenceId - Reference ID
     * @return bool - True if has meaningful content, false otherwise
     */
    public function hasSeoContent($referenceId)
    {
        try {
            // Temporarily switch to entity return type
            $originalReturnType = $this->returnType;
            $this->returnType = $this->returnTypeFormatted;

            $seoEntity = null;
            switch ($this->currentType) {
                case 'services':
                    $seoEntity = $this->where('service_id', $referenceId)->first();
                    break;
                case 'categories':
                    $seoEntity = $this->where('category_id', $referenceId)->first();
                    break;
                case 'providers':
                    $seoEntity = $this->where('partner_id', $referenceId)->first();
                    break;
                case 'blogs':
                    $seoEntity = $this->where('blog_id', $referenceId)->first();
                    break;
                default:
                    $seoEntity = null;
                    break;
            }

            // Restore original return type
            $this->returnType = $originalReturnType;

            return $seoEntity ? $seoEntity->hasContent() : false;
        } catch (\Exception $e) {
            log_message('error', 'SEO Model hasSeoContent error: ' . $e->getMessage());

            // Ensure return type is restored even on error
            if (isset($originalReturnType)) {
                $this->returnType = $originalReturnType;
            }

            return false;
        }
    }



    /**
     * Get all existing page names that have SEO settings
     * 
     * @return array - Array of page names
     */
    public function getExistingPages()
    {
        try {
            $results = $this->select('page')->findAll();
            return array_column($results, 'page');
        } catch (\Exception $e) {
            log_message('error', 'SEO Model getExistingPages error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if a page already has SEO settings
     * 
     * @param string $page - Page name
     * @param int $excludeId - ID to exclude from check (for updates)
     * @return bool - True if page exists, false otherwise
     */
    public function pageHasSeoSettings($page, $excludeId = null)
    {
        try {
            $builder = $this->where('page', $page);

            if ($excludeId) {
                $builder->where('id !=', $excludeId);
            }

            return $builder->first() !== null;
        } catch (\Exception $e) {
            log_message('error', 'SEO Model pageHasSeoSettings error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get formatted SEO settings list for display in admin panel
     * Uses CI4 best practices for data formatting
     * 
     * @param array $params - Parameters for pagination, search, sorting
     * @return array - Formatted response ready for display
     */
    public function getFormattedSeoSettingsList($params = [])
    {
        helper('text');
        helper('function_helper'); // Load function helper for get_current_language and get_default_language

        // Get raw data from the database
        $result = $this->getSeoSettingsList($params);

        if (!$result || !isset($result['data'])) {
            return [
                'total' => 0,
                'rows' => []
            ];
        }

        // Get current language and default language for translations
        // Use helper functions to get language from session
        $currentLanguage = function_exists('get_current_language') ? get_current_language() : 'en';
        $defaultLanguage = function_exists('get_default_language') ? get_default_language() : 'en';

        // For general SEO settings, fetch translations for all records
        $translationsBySeoId = [];
        if ($this->currentType === 'general') {
            $seoIds = array_column($result['data'], 'id');
            if (!empty($seoIds)) {
                try {
                    // Check if translation table exists before querying
                    $db = \Config\Database::connect();
                    
                    // Check if table exists
                    $tableExists = $db->tableExists('translated_seo_settings');
                    
                    if ($tableExists) {
                        // Fetch all translations for all SEO IDs in one query
                        $builder = $db->table('translated_seo_settings');
                        $builder->whereIn('seo_id', $seoIds);
                        $allTranslations = $builder->get()->getResultArray();
                        
                        // Group translations by seo_id and language_code
                        foreach ($allTranslations as $translation) {
                            $seoId = $translation['seo_id'];
                            $langCode = $translation['language_code'];
                            if (!isset($translationsBySeoId[$seoId])) {
                                $translationsBySeoId[$seoId] = [];
                            }
                            $translationsBySeoId[$seoId][$langCode] = $translation;
                        }
                    }
                } catch (\Exception $e) {
                    // If translation model fails, continue without translations
                    log_message('error', 'Error fetching SEO translations for list: ' . $e->getMessage());
                }
            }
        }

        // Format the results for display using CI4 string helpers
        $formattedResults = [];
        foreach ($result['data'] as $row) {
            // Format display based on current type
            switch ($this->currentType) {
                case 'services':
                    $itemName = $row['item_title'] ?? 'Unknown Service';
                    $displayName = 'Service: ' . $itemName;
                    break;
                case 'categories':
                    $itemName = $row['item_title'] ?? 'Unknown Category';
                    $displayName = 'Category: ' . $itemName;
                    break;
                case 'providers':
                    $itemName = $row['item_title'] ?? 'Unknown Provider';
                    $displayName = 'Provider: ' . $itemName;
                    break;
                case 'blogs':
                    $itemName = $row['item_title'] ?? 'Unknown Blog';
                    $displayName = 'Blog: ' . $itemName;
                    break;
                default:
                    // Format page name using labels function for translation
                    $displayName = $this->getTranslatedPageName($row['page']);
                    break;
            }

            // For general SEO settings, get translated data based on current language
            // Priority: current language translation -> default language translation -> main table data
            $displayTitle = '';
            $displayDescription = '';
            $displayKeywords = '';
            
            if ($this->currentType === 'general') {
                // Get translations for this SEO setting if available
                $seoTranslations = $translationsBySeoId[$row['id']] ?? [];
                
                // Get title with fallback: current language -> default language -> main table
                if (!empty($seoTranslations[$currentLanguage]['seo_title']) && trim($seoTranslations[$currentLanguage]['seo_title']) !== '') {
                    $displayTitle = trim($seoTranslations[$currentLanguage]['seo_title']);
                } elseif (!empty($seoTranslations[$defaultLanguage]['seo_title']) && trim($seoTranslations[$defaultLanguage]['seo_title']) !== '') {
                    $displayTitle = trim($seoTranslations[$defaultLanguage]['seo_title']);
                } else {
                    $displayTitle = trim($row['title'] ?? '');
                }
                
                // Get description with fallback: current language -> default language -> main table
                if (!empty($seoTranslations[$currentLanguage]['seo_description']) && trim($seoTranslations[$currentLanguage]['seo_description']) !== '') {
                    $displayDescription = trim($seoTranslations[$currentLanguage]['seo_description']);
                } elseif (!empty($seoTranslations[$defaultLanguage]['seo_description']) && trim($seoTranslations[$defaultLanguage]['seo_description']) !== '') {
                    $displayDescription = trim($seoTranslations[$defaultLanguage]['seo_description']);
                } else {
                    $displayDescription = trim($row['description'] ?? '');
                }
                
                // Get keywords with fallback: current language -> default language -> main table
                if (!empty($seoTranslations[$currentLanguage]['seo_keywords']) && trim($seoTranslations[$currentLanguage]['seo_keywords']) !== '') {
                    $displayKeywords = trim($seoTranslations[$currentLanguage]['seo_keywords']);
                } elseif (!empty($seoTranslations[$defaultLanguage]['seo_keywords']) && trim($seoTranslations[$defaultLanguage]['seo_keywords']) !== '') {
                    $displayKeywords = trim($seoTranslations[$defaultLanguage]['seo_keywords']);
                } else {
                    $displayKeywords = trim($row['keywords'] ?? '');
                }
            } else {
                // For non-general types, use main table data
                $displayTitle = trim($row['title'] ?? '');
                $displayDescription = trim($row['description'] ?? '');
                $displayKeywords = trim($row['keywords'] ?? '');
            }

            // Truncate long descriptions for table display using CI4 text helper
            $shortDescription = character_limiter($displayDescription, 100);

            // Truncate keywords for better display using CI4 text helper
            $shortKeywords = character_limiter($displayKeywords, 80);

            // Get image URL using public method
            $imageUrl = $this->getImageUrl($row['image'] ?? '');

            $formattedResults[] = [
                'id' => $row['id'],
                'page' => $displayName,
                'title' => esc($displayTitle),
                'description' => esc($shortDescription),
                'keywords' => esc($shortKeywords),
                'image' => $imageUrl ? '<img src="' . $imageUrl . '" alt="SEO Image" style="width: 50px; height: 50px; object-fit: cover;">' : 'No Image',
                'created_at' => date('M d, Y', strtotime($row['created_at'])),
                'updated_at' => date('M d, Y', strtotime($row['updated_at']))
            ];
        }

        return [
            'total' => $result['total'],
            'rows' => $formattedResults
        ];
    }

    /**
     * Get image URL based on file manager configuration
     * Made public to follow CI4 best practices for reusability
     * 
     * @param string $imageName - Image filename
     * @return string - Complete image URL
     */
    public function getImageUrl($imageName)
    {
        if (empty($imageName)) {
            return '';
        }

        return service('fileService')->url($imageName, 'seo_settings');
    }

    // Check for existing record based on type
    private function checkExistingRecord($data)
    {
        switch ($this->currentType) {
            case 'services':
                if (isset($data['service_id']) && $this->where('service_id', $data['service_id'])->first()) {
                    return labels(SEO_SETTINGS_FOR_THIS_SERVICE_ALREADY_EXISTS, 'SEO settings for this service already exists. Please edit the existing record.');
                }
                break;
            case 'categories':
                if (isset($data['category_id']) && $this->where('category_id', $data['category_id'])->first()) {
                    return labels(SEO_SETTINGS_FOR_THIS_CATEGORY_ALREADY_EXISTS, 'SEO settings for this category already exists. Please edit the existing record.');
                }
                break;
            case 'providers':
                if (isset($data['partner_id']) && $this->where('partner_id', $data['partner_id'])->first()) {
                    return labels(SEO_SETTINGS_FOR_THIS_PROVIDER_ALREADY_EXISTS, 'SEO settings for this provider already exists. Please edit the existing record.');
                }
                break;
            case 'blogs':
                if (isset($data['blog_id']) && $this->where('blog_id', $data['blog_id'])->first()) {
                    return labels(SEO_SETTINGS_FOR_THIS_BLOG_ALREADY_EXISTS, 'SEO settings for this blog already exists. Please edit the existing record.');
                }
                break;
            default:
                // General page check (existing logic)
                if (isset($data['page']) && $this->where('page', $data['page'])->first()) {
                    return labels(SEO_SETTINGS_FOR_THIS_PAGE_ALREADY_EXISTS, 'SEO settings for this page already exists. Please edit the existing record.');
                }
                break;
        }
        return false;
    }

    // Check for duplicates when updating based on type
    private function checkDuplicateOnUpdate($id, $data, $existing)
    {
        switch ($this->currentType) {
            case 'services':
                if (isset($data['service_id']) && $data['service_id'] !== $existing['service_id']) {
                    $serviceExists = $this->where('service_id', $data['service_id'])
                        ->where('id !=', $id)
                        ->first();
                    if ($serviceExists) {
                        return labels(SEO_SETTINGS_FOR_THE_SELECTED_SERVICE_ALREADY_EXISTS, 'SEO settings for the selected service already exists');
                    }
                }
                break;
            case 'categories':
                if (isset($data['category_id']) && $data['category_id'] !== $existing['category_id']) {
                    $categoryExists = $this->where('category_id', $data['category_id'])
                        ->where('id !=', $id)
                        ->first();
                    if ($categoryExists) {
                        return labels(SEO_SETTINGS_FOR_THE_SELECTED_CATEGORY_ALREADY_EXISTS, 'SEO settings for the selected category already exists');
                    }
                }
                break;
            case 'providers':
                if (isset($data['partner_id']) && $data['partner_id'] !== $existing['partner_id']) {
                    $providerExists = $this->where('partner_id', $data['partner_id'])
                        ->where('id !=', $id)
                        ->first();
                    if ($providerExists) {
                        return labels(SEO_SETTINGS_FOR_THE_SELECTED_PROVIDER_ALREADY_EXISTS, 'SEO settings for the selected provider already exists');
                    }
                }
                break;
            case 'blogs':
                if (isset($data['blog_id']) && $data['blog_id'] !== $existing['blog_id']) {
                    $blogExists = $this->where('blog_id', $data['blog_id'])
                        ->where('id !=', $id)
                        ->first();
                }
                break;
            default:
                // General page check (existing logic)
                if (isset($data['page']) && $data['page'] !== $existing['page']) {
                    $pageExists = $this->where('page', $data['page'])
                        ->where('id !=', $id)
                        ->first();
                    if ($pageExists) {
                        return labels(SEO_SETTINGS_FOR_THE_SELECTED_PAGE_ALREADY_EXISTS, 'SEO settings for the selected page already exists');
                    }
                }
                break;
        }
        return false;
    }

    // Clean empty fields for optional SEO data
    private function cleanEmptyFields($data)
    {
        // Fields to check for emptiness (excluding ID fields)
        $seoFields = ['title', 'description', 'keywords', 'schema_markup', 'image'];

        foreach ($seoFields as $field) {
            if (isset($data[$field]) && (empty($data[$field]) || trim($data[$field]) === '')) {
                unset($data[$field]);
            }
        }

        return $data;
    }

    // Check if all SEO fields are empty
    private function areAllSeoFieldsEmpty($data)
    {
        $seoFields = ['title', 'description', 'keywords', 'schema_markup', 'image'];

        foreach ($seoFields as $field) {
            if (isset($data[$field]) && !empty(trim($data[$field]))) {
                return false; // At least one field has content
            }
        }

        return true; // All SEO fields are empty
    }

    /**
     * Before insert callback - CI4 best practice for data validation and manipulation
     * 
     * @param array $data
     * @return array
     */
    protected function beforeInsert(array $data)
    {
        // Sanitize and validate data before insertion
        if (isset($data['data']['page'])) {
            $data['data']['page'] = strtolower(trim($data['data']['page']));
        }

        if (isset($data['data']['title'])) {
            $data['data']['title'] = trim($data['data']['title']);
        }

        if (isset($data['data']['description'])) {
            $data['data']['description'] = trim($data['data']['description']);
        }

        if (isset($data['data']['keywords'])) {
            $data['data']['keywords'] = trim($data['data']['keywords']);
        }

        return $data;
    }

    /**
     * Before update callback - CI4 best practice for data validation and manipulation
     * 
     * @param array $data
     * @return array
     */
    protected function beforeUpdate(array $data)
    {
        // Sanitize and validate data before update
        return $this->beforeInsert($data);
    }

    /**
     * Get available page options for SEO settings
     * CI4 best practice for centralized configuration
     * 
     * @return array
     */
    public function getAvailablePages()
    {
        return [
            'home' => 'Home',
            'become-provider' => 'Become Provider',
            'landing-page' => 'Landing Page',
            'about-us' => 'About Us',
            'contact-us' => 'Contact Us',
            'providers-page' => 'Providers Page',
            'services-page' => 'Services Page',
            'terms-and-conditions' => 'Terms and Conditions',
            'privacy-policy' => 'Privacy Policy',
            'faqs' => 'FAQs',
            'blogs' => 'Blogs'
        ];
    }

    /**
     * Get translated page name using the labels function
     * This ensures consistent translation between dropdown and table display
     * 
     * @param string $page - Page identifier
     * @return string - Translated page name
     */
    private function getTranslatedPageName($page)
    {
        // Map page identifiers to their corresponding label keys
        $pageLabels = [
            'home' => 'home',
            'become-provider' => 'become_provider',
            'landing-page' => 'landing_page',
            'about-us' => 'about_us',
            'contact-us' => 'contact_us',
            'providers-page' => 'providers_page',
            'services-page' => 'services_page',
            'terms-and-conditions' => 'terms_and_conditions',
            'privacy-policy' => 'privacy_policy',
            'faqs' => 'faqs',
            'blogs' => 'blogs'
        ];

        // Get the label key for this page
        $labelKey = $pageLabels[$page] ?? $page;

        // Use the labels function to get translated text
        // Fallback to formatted page name if labels function is not available
        if (function_exists('labels')) {
            return labels($labelKey, ucwords(str_replace('-', ' ', $page)));
        } else {
            // Fallback to formatted page name if labels function is not available
            return ucwords(str_replace('-', ' ', $page));
        }
    }

    // Get current SEO type for reference
    public function getCurrentType()
    {
        return $this->currentType;
    }

    // Get current table name for reference
    public function getCurrentTable()
    {
        return $this->currentTable;
    }

    /**
     * Clean up SEO settings and associated files when an entity is deleted
     * This method should be called before entity deletion to clean up orphaned SEO files
     * 
     * @param int $referenceId - The ID of the entity being deleted
     * @param string $context - The entity type ('services', 'categories', 'providers', 'blogs')
     * @return void
     */
    public function cleanupSeoData($referenceId, $context = null)
    {
        try {
            // If context is provided, set it; otherwise use current context
            if ($context) {
                $this->setTableContext($context);
            }

            // Get existing SEO settings for this entity
            $seoSettings = $this->getSeoSettingsByReferenceId($referenceId, 'raw');

            if (!empty($seoSettings) && !empty($seoSettings['image'])) {
                // Determine the storage folder based on context
                $folder = $this->getSeoImageFolder($this->currentType);

                // Clean up the image file
                $this->cleanupSeoImageFile($seoSettings['image'], $folder);

                log_message('info', "SEO image cleaned up for {$this->currentType} ID: {$referenceId}");
            }
        } catch (\Exception $e) {
            log_message('error', "Failed to cleanup SEO data for {$this->currentType} ID {$referenceId}: " . $e->getMessage());
        }
    }

    /**
     * Delete SEO image file from storage
     * 
     * @param string $imageName - Name of the image file
     * @param string $folder - Storage folder name
     * @return void
     */
    private function cleanupSeoImageFile($imageName, $folder)
    {
        if (empty($imageName)) {
            return;
        }

        try {
            $fileName = basename($imageName);

            if (service('fileService')->delete($folder, $fileName)) {
                log_message('info', "SEO image file deleted successfully: {$fileName} from {$folder}");
            } else {
                log_message('error', "Failed to delete SEO image file: {$fileName} from {$folder}");
            }
        } catch (\Exception $e) {
            log_message('error', "Exception while deleting SEO image file: " . $e->getMessage());
        }
    }

    /**
     * Get SEO image storage folder name based on context
     * 
     * @param string $context - Entity type
     * @return string - FileService folder key for the SEO image
     */
    private function getSeoImageFolder($context)
    {
        switch ($context) {
            case 'services':
                return 'service_seo_settings';
            case 'categories':
                return 'category_seo_settings';
            case 'providers':
                return 'provider_seo_settings';
            case 'blogs':
                return 'blog_seo_settings';
            case 'general':
            default:
                return 'seo_settings';
        }
    }
}
