<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Model for managing translated email templates
 * 
 * This model handles multi-language support for email templates.
 * It stores translations in the translated_email_templates table.
 */
class Translated_email_template_model extends Model
{
    // Table configuration
    protected $table = 'translated_email_templates';
    protected $primaryKey = 'id';
    
    // Fields that can be inserted/updated
    protected $allowedFields = [
        'template_id',
        'language_code',
        'subject',
        'template'
    ];

    // Enable automatic timestamp handling
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    // Validation rules for data integrity
    protected $validationRules = [
        'template_id' => 'required|integer',
        'language_code' => 'required|max_length[10]',
        'subject' => 'permit_empty|max_length[255]',
        'template' => 'permit_empty'
    ];

    // Validation error messages
    protected $validationMessages = [
        'template_id' => [
            'required' => 'Template ID is required',
            'integer' => 'Template ID must be an integer'
        ],
        'language_code' => [
            'required' => 'Language code is required',
            'max_length' => 'Language code cannot exceed 10 characters'
        ]
    ];

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    /**
     * Get translated validation messages
     * 
     * Returns validation messages with proper labels for display
     * 
     * @return array Translated validation messages
     */
    public function getTranslatedValidationMessages()
    {
        return [
            'template_id' => [
                'required' => labels('template_id_is_required', 'Template ID is required'),
                'integer' => labels('template_id_must_be_an_integer', 'Template ID must be an integer')
            ],
            'language_code' => [
                'required' => labels('language_code_is_required', 'Language code is required'),
                'max_length' => labels('language_code_cannot_exceed_10_characters', 'Language code cannot exceed 10 characters')
            ]
        ];
    }

    /**
     * Get translated email template for specific template and language
     * 
     * Retrieves a single translation record for the given template ID and language code.
     * 
     * @param int $templateId Template ID from email_templates table
     * @param string $languageCode Language code (e.g., 'en', 'ar', 'hi')
     * @return array|null Translated template or null if not found
     */
    public function getTranslatedTemplate(int $templateId, string $languageCode): ?array
    {
        return $this->where([
            'template_id' => $templateId,
            'language_code' => $languageCode
        ])->first();
    }

    /**
     * Get all translations for a specific email template
     * 
     * Returns all language translations for a given template.
     * Useful for editing forms where all languages are shown.
     * 
     * @param int $templateId Template ID from email_templates table
     * @return array Array of translated templates
     */
    public function getTemplateTranslations(int $templateId): array
    {
        return $this->where('template_id', $templateId)->findAll();
    }

    /**
     * Save or update translated email template
     * 
     * If a translation exists for the given template ID and language code,
     * it will be updated. Otherwise, a new translation record is created.
     * 
     * @param int $templateId Template ID from email_templates table
     * @param string $languageCode Language code (e.g., 'en', 'ar', 'hi')
     * @param array $data Translation data (subject and template)
     * @return bool Success status
     */
    public function saveTranslation(int $templateId, string $languageCode, array $data): bool
    {
        // Check if translation already exists
        $existing = $this->getTranslatedTemplate($templateId, $languageCode);

        // Prepare translation data for saving
        $translationData = [
            'template_id' => $templateId,
            'language_code' => $languageCode,
            'subject' => $data['subject'] ?? '',
            'template' => $data['template'] ?? ''
        ];

        // Update existing or insert new translation
        if ($existing) {
            return $this->update($existing['id'], $translationData);
        } else {
            return $this->insert($translationData) !== false;
        }
    }

    /**
     * Delete translation for specific template and language
     * 
     * Removes a single translation record for the given template and language.
     * 
     * @param int $templateId Template ID from email_templates table
     * @param string $languageCode Language code to delete
     * @return bool Success status
     */
    public function deleteTranslation(int $templateId, string $languageCode): bool
    {
        return $this->where([
            'template_id' => $templateId,
            'language_code' => $languageCode
        ])->delete() !== false;
    }

    /**
     * Delete all translations for a specific email template
     * 
     * Removes all language translations when a template is deleted.
     * This is usually called automatically by foreign key cascade.
     * 
     * @param int $templateId Template ID from email_templates table
     * @return bool Success status
     */
    public function deleteTemplateTranslations(int $templateId): bool
    {
        return $this->where('template_id', $templateId)->delete() !== false;
    }
}

