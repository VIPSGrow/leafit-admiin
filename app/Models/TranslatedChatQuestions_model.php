<?php

namespace App\Models;

use CodeIgniter\Model;

class TranslatedChatQuestions_model extends Model
{
    protected $table = 'translated_chat_questions';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'chat_question_id',
        'language_code',
        'question',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'chat_question_id' => 'required|integer',
        'language_code'    => 'required|max_length[16]',
        'question'         => 'required|max_length[500]',
    ];

    /**
     * Get all translations for a single question, indexed by language_code.
     */
    public function getAllForQuestion(int $questionId): array
    {
        $rows = $this->where('chat_question_id', $questionId)->findAll();
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['language_code']] = $row['question'];
        }
        return $indexed;
    }

    /**
     * Bulk-save translations for a question.
     */
    public function saveTranslations(int $questionId, array $translations): bool
    {
        if (empty($translations)) {
            return true;
        }

        $batch = [];
        foreach ($translations as $langCode => $text) {
            $batch[] = [
                'chat_question_id' => $questionId,
                'language_code'    => $langCode,
                'question'         => $text,
            ];
        }

        return $this->skipValidation(true)->insertBatch($batch) !== false;
    }

    /**
     * Get translations for multiple question IDs at once, for a specific language.
     * Returns array indexed by chat_question_id.
     */
    public function getForMultipleQuestions(array $questionIds, string $languageCode): array
    {
        if (empty($questionIds)) {
            return [];
        }

        $rows = $this->whereIn('chat_question_id', $questionIds)
            ->where('language_code', $languageCode)
            ->findAll();

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['chat_question_id']] = $row['question'];
        }
        return $indexed;
    }

    /**
     * Get ALL translations for multiple question IDs (for admin panel editing).
     * Returns nested array: [question_id => [lang_code => text, ...], ...]
     */
    public function getAllForMultipleQuestions(array $questionIds): array
    {
        if (empty($questionIds)) {
            return [];
        }

        $rows = $this->whereIn('chat_question_id', $questionIds)->findAll();

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[$row['chat_question_id']][$row['language_code']] = $row['question'];
        }
        return $indexed;
    }
}
