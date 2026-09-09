<?php

namespace App\Models;

use CodeIgniter\Model;

class Translated_sms_template_model extends Model
{
    protected $table = 'translated_sms_templates';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'template_id',
        'language_code',
        'title',
        'template'
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'template_id' => 'required|integer',
        'language_code' => 'required|max_length[10]',
        'title' => 'permit_empty|max_length[255]',
        'template' => 'permit_empty'
    ];

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
     * Get translated template for specific template and language
     * 
     * @param int $templateId Template ID
     * @param string $languageCode Language code
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
     * Get all translations for a specific template
     * 
     * @param int $templateId Template ID
     * @return array Array of translated templates
     */
    public function getTemplateTranslations(int $templateId): array
    {
        return $this->where('template_id', $templateId)->findAll();
    }

    /**
     * Save or update translated template
     * 
     * @param int $templateId Template ID
     * @param string $languageCode Language code
     * @param array $data Translation data
     * @return bool Success status
     */
    public function saveTranslation(int $templateId, string $languageCode, array $data): bool
    {
        $existing = $this->getTranslatedTemplate($templateId, $languageCode);

        $translationData = [
            'template_id' => $templateId,
            'language_code' => $languageCode,
            'title' => $data['title'] ?? '',
            'template' => $data['template'] ?? '',
            'gateway_template_ids' => $data['gateway_template_ids'] ?? null
        ];

        if ($existing) {
            return $this->update($existing['id'], $translationData);
        } else {
            return $this->insert($translationData) !== false;
        }
    }

    /**
     * Delete translation for specific template and language
     * 
     * @param int $templateId Template ID
     * @param string $languageCode Language code
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
     * Delete all translations for a specific template
     * 
     * @param int $templateId Template ID
     * @return bool Success status
     */
    public function deleteTemplateTranslations(int $templateId): bool
    {
        return $this->where('template_id', $templateId)->delete() !== false;
    }
}
