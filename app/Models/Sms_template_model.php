<?php

namespace App\Models;

use CodeIgniter\Model;

class Sms_template_model extends Model
{
    protected $table = 'sms_templates';
    protected $primaryKey = 'id';
    protected $allowedFields = [
        'type',
        'title',
        'template',
        'parameters',
        'gateway_template_ids'
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'type' => 'required|max_length[255]',
        'title' => 'required|max_length[255]',
        'template' => 'required'
    ];

    protected $validationMessages = [
        'type' => [
            'required' => 'Template type is required',
            'max_length' => 'Template type cannot exceed 255 characters'
        ],
        'title' => [
            'required' => 'Template title is required',
            'max_length' => 'Template title cannot exceed 255 characters'
        ],
        'template' => [
            'required' => 'Template content is required'
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
            'type' => [
                'required' => labels('template_type_is_required', 'Template type is required'),
                'max_length' => labels('template_type_cannot_exceed_255_characters', 'Template type cannot exceed 255 characters')
            ],
            'title' => [
                'required' => labels('template_title_is_required', 'Template title is required'),
                'max_length' => labels('template_title_cannot_exceed_255_characters', 'Template title cannot exceed 255 characters')
            ],
            'template' => [
                'required' => labels('template_content_is_required', 'Template content is required')
            ]
        ];
    }

    /**
     * Get SMS template by ID
     * 
     * @param int $id Template ID
     * @return array|null Template data or null if not found
     */
    public function getTemplateById(int $id): ?array
    {
        return $this->where('id', $id)->first();
    }

    /**
     * Get SMS template by type
     * 
     * @param string $type Template type
     * @return array|null Template data or null if not found
     */
    public function getTemplateByType(string $type): ?array
    {
        return $this->where('type', $type)->first();
    }

    /**
     * Update SMS template
     * 
     * @param int $id Template ID
     * @param array $data Template data
     * @return bool Success status
     */
    public function updateTemplate(int $id, array $data): bool
    {
        return $this->update($id, $data);
    }

    /**
     * Create new SMS template
     * 
     * @param array $data Template data
     * @return int|false Inserted ID or false on failure
     */
    public function createTemplate(array $data)
    {
        return $this->insert($data);
    }
}
