<?php

namespace App\Models;

use CodeIgniter\Model;

class ProviderLocationsModel extends Model
{
    protected $table = 'provider_locations';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'provider_id',
        'address',
        'city',
        'latitude',
        'longitude',
        'is_default',
        'is_active',
        'created_at',
        'updated_at',
    ];
    protected $useTimestamps = true;
}