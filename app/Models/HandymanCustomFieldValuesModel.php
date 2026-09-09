<?php

namespace App\Models;

use CodeIgniter\Model;

class HandymanCustomFieldValuesModel extends Model
{
    protected $table          = 'handyman_custom_field_values';
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
        'custom_field_id',
        'value',
    ];

    protected $validationRules = [
        'handyman_id'     => 'required|integer',
        'custom_field_id' => 'required|integer',
    ];
}
