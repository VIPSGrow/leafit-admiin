<?php

namespace App\Models;

use CodeIgniter\Model;

class Settings extends Model
{
    protected $DBGroup = 'default';
    protected $table = 'settings';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType     = 'array';
    protected $useSoftDeletes = false; // Disabled: settings table doesn't have deleted_at column
    protected $allowedFields = ['variable', 'value'];

    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    /**
     * Insert or update a settings row by variable name.
     */
    public function upsert(string $variable, string $value): bool
    {
        $existing = $this->where('variable', $variable)->first();

        if ($existing) {
            return $this->update($existing['id'], ['value' => $value]);
        }

        return $this->insert(['variable' => $variable, 'value' => $value]) !== false;
    }
}
