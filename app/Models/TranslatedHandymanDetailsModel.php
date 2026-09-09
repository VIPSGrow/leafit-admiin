<?php

namespace App\Models;

use CodeIgniter\Model;

class TranslatedHandymanDetailsModel extends Model
{
    protected $table          = 'translated_handyman_details';
    protected $primaryKey     = 'id';
    protected $useAutoIncrement = true;
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $dateFormat     = 'datetime';
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';
    protected $deletedField   = 'deleted_at';

    protected $allowedFields = [
        'handyman_id',
        'language_id',
        'username',
    ];

    protected $validationRules = [
        'handyman_id' => 'required|integer',
        'language_id' => 'required|integer',
        'username'    => 'permit_empty|max_length[255]',
    ];
}
