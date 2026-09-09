<?php

namespace App\Models;

use CodeIgniter\Model;

class UsersGroupsModel extends Model
{
    protected $table          = 'users_groups';
    protected $primaryKey     = 'id';
    protected $useAutoIncrement = true;
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = false;

    protected $allowedFields = [
        'user_id',
        'group_id',
    ];
}
