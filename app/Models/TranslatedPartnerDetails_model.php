<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Translated Partner Details Model
 * 
 * Handles basic database operations for translated partner details
 * Contains only relationships and basic CRUD operations
 */
class TranslatedPartnerDetails_model extends Model
{
    protected $table = 'translated_partner_details';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;

    protected $allowedFields = [
        'partner_id',
        'language_code',
        'username',
        'company_name',
        'about',
        'long_description'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    // Validation
    protected $validationRules = [
        'partner_id' => 'required|integer',
        'language_code' => 'required|max_length[10]',
        'username' => 'permit_empty|max_length[255]',
        'company_name' => 'permit_empty|max_length[255]',
        'about' => 'permit_empty',
        'long_description' => 'permit_empty'
    ];

    protected $validationMessages = [
        'partner_id' => [
            'required' => 'Partner ID is required',
            'integer' => 'Partner ID must be a valid integer'
        ],
        'language_code' => [
            'required' => 'Language code is required',
            'max_length' => 'Language code cannot exceed 10 characters'
        ]
    ];

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    /**
     * Get translated details for a specific partner and language
     * 
     * @param int $partnerId Partner ID
     * @param string $languageCode Language code
     * @return array|null Translated details or null if not found
     */
    public function getTranslatedDetails(int $partnerId, string $languageCode): ?array
    {
        $result = $this->where([
            'partner_id' => $partnerId,
            'language_code' => $languageCode
        ])->first();

        return $result ?: null;
    }

    /**
     * Get all translations for a specific partner
     * 
     * @param int $partnerId Partner ID
     * @return array Array of translated details
     */
    public function getAllTranslationsForPartner(int $partnerId): array
    {
        return $this->where('partner_id', $partnerId)->findAll();
    }

    /**
     * Save or update translated details for a partner
     * 
     * @param int $partnerId Partner ID
     * @param string $languageCode Language code
     * @param array $translatedData Array containing company_name, about, long_description
     * @return bool Success status
     */
    public function saveTranslatedDetails(int $partnerId, string $languageCode, array $translatedData): bool
    {
        // Check if translation already exists
        $existing = $this->where([
            'partner_id' => $partnerId,
            'language_code' => $languageCode
        ])->first();

        // Merge existing data with new data to preserve other fields
        $data = [
            'partner_id' => $partnerId,
            'language_code' => $languageCode,
            'username' => $existing['username'] ?? null,
            'company_name' => $existing['company_name'] ?? null,
            'about' => $existing['about'] ?? null,
            'long_description' => $existing['long_description'] ?? null
        ];

        // Update with new translated data
        foreach ($translatedData as $field => $value) {
            if (in_array($field, ['username', 'company_name', 'about', 'long_description'])) {
                $data[$field] = $value;
            }
        }

        if ($existing) {
            // Update existing record
            return $this->update($existing['id'], $data);
        } else {
            // Insert new record
            return $this->insert($data) !== false;
        }
    }

    /**
     * Delete all translations for a specific partner
     * 
     * @param int $partnerId Partner ID
     * @return bool Success status
     */
    public function deletePartnerTranslations(int $partnerId): bool
    {
        return $this->where('partner_id', $partnerId)->delete() !== false;
    }

    /**
     * Delete specific translation for a partner and language
     * 
     * @param int $partnerId Partner ID
     * @param string $languageCode Language code
     * @return bool Success status
     */
    public function deleteTranslation(int $partnerId, string $languageCode): bool
    {
        return $this->where([
            'partner_id' => $partnerId,
            'language_code' => $languageCode
        ])->delete() !== false;
    }

    /**
     * Get partners with their default language details
     * 
     * @param string $defaultLanguage Default language code
     * @param array $where Additional where conditions
     * @param int $limit Limit for results
     * @param int $offset Offset for pagination
     * @return array Partners with their default language details
     */
    public function getPartnersWithDefaultLanguage(string $defaultLanguage, array $where = [], int $limit = 10, int $offset = 0): array
    {
        $builder = $this->db->table('partner_details pd');

        $builder->select('pd.*, tpd.username as translated_username, tpd.company_name as translated_company_name, tpd.about as translated_about, tpd.long_description as translated_long_description')
            ->join('translated_partner_details tpd', 'tpd.partner_id = pd.partner_id AND tpd.language_code = ?', 'left')
            ->where($where)
            ->limit($limit, $offset);

        return $builder->get()->getResultArray();
    }

    /**
     * Get batch translations for multiple partners - OPTIMIZED for performance
     * 
     * @param array $partnerIds Array of partner IDs
     * @param string $languageCode Language code to fetch translations for
     * @return array Array of translations indexed by partner_id
     */
    public function getBatchTranslations(array $partnerIds, string $languageCode): array
    {
        if (empty($partnerIds)) {
            return [];
        }

        try {
            // Fetch all translations for the given partners and language in a single query
            $results = $this->whereIn('partner_id', $partnerIds)
                ->where('language_code', $languageCode)
                ->select('partner_id, username, company_name, about, long_description')
                ->findAll();

            // Organize results by partner_id for easy lookup
            $translations = [];
            foreach ($results as $result) {
                $translations[$result['partner_id']] = [
                    'username' => $result['username'],
                    'company_name' => $result['company_name'],
                    'about' => $result['about'],
                    'long_description' => $result['long_description']
                ];
            }

            return $translations;
        } catch (\Exception $e) {
            log_message('error', 'Error in getBatchTranslations: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all translations for multiple partners - OPTIMIZED for performance
     * 
     * @param array $partnerIds Array of partner IDs
     * @return array Array of translations indexed by partner_id and then by language_code
     */
    public function getAllTranslationsForPartners(array $partnerIds): array
    {

        if (empty($partnerIds)) {
            return [];
        }

        try {
            // Fetch all translations for the given partners in a single query
            $results = $this->whereIn('partner_id', $partnerIds)
                ->select('partner_id, language_code, username, company_name, about, long_description')
                ->findAll();


            // Organize results by partner_id and then by language_code for easy lookup
            $translations = [];
            foreach ($results as $result) {
                $partnerId = $result['partner_id'];
                $languageCode = $result['language_code'];

                if (!isset($translations[$partnerId])) {
                    $translations[$partnerId] = [];
                }

                $translations[$partnerId][$languageCode] = [
                    'username' => $result['username'],
                    'company_name' => $result['company_name'],
                    'about' => $result['about'],
                    'long_description' => $result['long_description']
                ];
            }

            return $translations;
        } catch (\Exception $e) {
            log_message('error', 'Error in getAllTranslationsForPartners: ' . $e->getMessage());
            return [];
        }
    }
}
