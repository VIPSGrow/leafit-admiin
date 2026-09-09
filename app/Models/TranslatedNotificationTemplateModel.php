<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * TranslatedNotificationTemplateModel
 *
 * Handles multi-language storage for notification templates.
 * Stores only translatable fields (title, body) in the translations table.
 */
class TranslatedNotificationTemplateModel extends Model
{
    protected $table = 'translated_notification_templates';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = [
        'template_id',
        'language_code',
        'title',
        'body',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    /**
     * Get translated template for a specific template and language.
     */
    public function getTranslatedTemplate(int $templateId, string $languageCode): ?array
    {
        $result = $this->where([
            'template_id' => $templateId,
            'language_code' => $languageCode,
        ])->first();

        return $result ?: null;
    }

    /**
     * Get all translations for a specific template.
     */
    public function getTemplateTranslations(int $templateId): array
    {
        return $this->where('template_id', $templateId)->findAll();
    }

    /**
     * Save or update a single translation.
     * If a translation exists, it is updated; otherwise a new one is inserted.
     */
    public function saveTranslation(int $templateId, string $languageCode, array $data): bool
    {
        $existing = $this->getTranslatedTemplate($templateId, $languageCode);

        $translationData = [
            'template_id' => $templateId,
            'language_code' => $languageCode,
            'title' => $data['title'] ?? '',
            'body' => $data['body'] ?? '',
        ];

        if ($existing) {
            return $this->update($existing['id'], $translationData);
        }

        return $this->insert($translationData) !== false;
    }

    /**
     * Delete translation for a specific template and language.
     */
    public function deleteTranslation(int $templateId, string $languageCode): bool
    {
        return $this->where([
            'template_id' => $templateId,
            'language_code' => $languageCode,
        ])->delete() !== false;
    }
}
