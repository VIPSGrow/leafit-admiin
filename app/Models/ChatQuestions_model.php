<?php

namespace App\Models;

use CodeIgniter\Model;

class ChatQuestions_model extends Model
{
    protected $table = 'chat_questions';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'type',
        'question',
        'sort_order',
    ];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    protected $validationRules = [
        'type'     => 'required|in_list[pre_booking,customer_post_booking,provider_post_booking,customer_admin_support,provider_admin_support]',
        'question' => 'required|max_length[500]',
    ];

    /**
     * Get all questions for a given type, ordered by sort_order.
     */
    public function getByType(string $type): array
    {
        return $this->where('type', $type)
            ->orderBy('sort_order', 'ASC')
            ->findAll();
    }

}
