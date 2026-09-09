<?php

namespace App\Models;

use CodeIgniter\Model;

class Featured_sections_model extends Model
{
    protected $DBGroup = 'default';
    protected $table = 'sections';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType     = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields = ['title', 'section_type', 'category_ids', 'partners_ids', 'status', 'limit', 'rank', 'banner_type', 'banner_url', 'app_banner_image', 'web_banner_image', 'description'];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    public $base, $admin_id, $db;

    /**
     * Get translated section title with fallback to main table for default language only
     * 
     * @param int $sectionId Section ID
     * @param string $languageCode Language code (optional, uses current language if not provided)
     * @return string Section title in the specified language or main table title as fallback for default language
     */
    public function getTranslatedSectionTitle(int $sectionId, ?string $languageCode = null): string
    {
        // If no language code provided, get current language
        if ($languageCode === null) {
            $languageCode = get_current_language();
        }

        try {
            // Get translation from translated_featured_sections table
            $db = \Config\Database::connect();
            $builder = $db->table('translated_featured_sections');

            $translation = $builder->select('title')
                ->where('section_id', $sectionId)
                ->where('language_code', $languageCode)
                ->get()
                ->getRowArray();

            // If translation exists and has a title, return it
            if ($translation && !empty($translation['title'])) {
                return $translation['title'];
            }

            // Check if this is the default language
            $defaultLanguage = get_default_language();
            if ($languageCode === $defaultLanguage) {
                // For default language, fallback to main table title
                $mainSection = $this->select('title')->where('id', $sectionId)->first();
                return !empty($mainSection) ? $mainSection['title'] : '';
            } else {
                // For non-default languages, return empty string
                return '';
            }
        } catch (\Exception $e) {
            // Log error and fallback to main table for default language only
            log_message('error', 'Error fetching translated section title: ' . $e->getMessage());

            // Check if this is the default language
            $defaultLanguage = get_default_language();
            if ($languageCode === $defaultLanguage) {
                // For default language, fallback to main table
                $mainSection = $this->select('title')->where('id', $sectionId)->first();
                return !empty($mainSection) ? $mainSection['title'] : '';
            } else {
                // For non-default languages, return empty string
                return '';
            }
        }
    }

    /**
     * Get translated section description with fallback to main table for default language only
     * 
     * @param int $sectionId Section ID
     * @param string $languageCode Language code (optional, uses current language if not provided)
     * @return string Section description in the specified language or main table description as fallback for default language
     */
    public function getTranslatedSectionDescription(int $sectionId, ?string $languageCode = null): string
    {
        // If no language code provided, get current language
        if ($languageCode === null) {
            $languageCode = get_current_language();
        }

        try {
            // Get translation from translated_featured_sections table
            $db = \Config\Database::connect();
            $builder = $db->table('translated_featured_sections');

            $translation = $builder->select('description')
                ->where('section_id', $sectionId)
                ->where('language_code', $languageCode)
                ->get()
                ->getRowArray();

            // If translation exists and has a description, return it
            if ($translation && !empty($translation['description'])) {
                return $translation['description'];
            }

            // Check if this is the default language
            $defaultLanguage = get_default_language();
            if ($languageCode === $defaultLanguage) {
                // For default language, fallback to main table description
                $mainSection = $this->select('description')->where('id', $sectionId)->first();
                return !empty($mainSection) ? $mainSection['description'] : '';
            } else {
                // For non-default languages, return empty string
                return '';
            }
        } catch (\Exception $e) {
            // Log error and fallback to main table for default language only
            log_message('error', 'Error fetching translated section description: ' . $e->getMessage());

            // Check if this is the default language
            $defaultLanguage = get_default_language();
            if ($languageCode === $defaultLanguage) {
                // For default language, fallback to main table
                $mainSection = $this->select('description')->where('id', $sectionId)->first();
                return !empty($mainSection) ? $mainSection['description'] : '';
            } else {
                // For non-default languages, return empty string
                return '';
            }
        }
    }

    /**
     * Get translated titles for multiple sections
     * 
     * @param array $sectionIds Array of section IDs
     * @param string $languageCode Language code (optional, uses current language if not provided)
     * @return array Array of section titles indexed by section_id
     */
    public function getTranslatedSectionTitles(array $sectionIds, ?string $languageCode = null): array
    {
        if (empty($sectionIds)) {
            return [];
        }

        // If no language code provided, get current language
        if ($languageCode === null) {
            $languageCode = get_current_language();
        }

        try {
            $db = \Config\Database::connect();
            $builder = $db->table('translated_featured_sections');

            // Get all translations for the given sections and language
            $translations = $builder->select('section_id, title')
                ->whereIn('section_id', $sectionIds)
                ->where('language_code', $languageCode)
                ->get()
                ->getResultArray();

            // Create a map of section_id to translated title
            $translatedTitles = [];
            foreach ($translations as $translation) {
                if (!empty($translation['title'])) {
                    $translatedTitles[$translation['section_id']] = $translation['title'];
                }
            }

            // Get titles from main table for sections without translations (default language only)
            $defaultLanguage = get_default_language();
            if ($languageCode === $defaultLanguage) {
                $sectionsWithoutTranslation = array_diff($sectionIds, array_keys($translatedTitles));

                if (!empty($sectionsWithoutTranslation)) {
                    $mainSections = $this->select('id, title')
                        ->whereIn('id', $sectionsWithoutTranslation)
                        ->findAll();

                    foreach ($mainSections as $section) {
                        if (!empty($section['title'])) {
                            $translatedTitles[$section['id']] = $section['title'];
                        }
                    }
                }
            }

            return $translatedTitles;
        } catch (\Exception $e) {
            // Log error and fallback to main table for default language only
            log_message('error', 'Error fetching translated section titles: ' . $e->getMessage());

            // Check if this is the default language
            $defaultLanguage = get_default_language();
            if ($languageCode === $defaultLanguage) {
                // For default language, fallback to main table
                $mainSections = $this->select('id, title')
                    ->whereIn('id', $sectionIds)
                    ->findAll();

                $fallbackTitles = [];
                foreach ($mainSections as $section) {
                    if (!empty($section['title'])) {
                        $fallbackTitles[$section['id']] = $section['title'];
                    }
                }
                return $fallbackTitles;
            } else {
                // For non-default languages, return empty array
                return [];
            }
        }
    }

    /**
     * Get translated descriptions for multiple sections
     * 
     * @param array $sectionIds Array of section IDs
     * @param string $languageCode Language code (optional, uses current language if not provided)
     * @return array Array of section descriptions indexed by section_id
     */
    public function getTranslatedSectionDescriptions(array $sectionIds, ?string $languageCode = null): array
    {
        if (empty($sectionIds)) {
            return [];
        }

        // If no language code provided, get current language
        if ($languageCode === null) {
            $languageCode = get_current_language();
        }

        try {
            $db = \Config\Database::connect();
            $builder = $db->table('translated_featured_sections');

            // Get all translations for the given sections and language
            $translations = $builder->select('section_id, description')
                ->whereIn('section_id', $sectionIds)
                ->where('language_code', $languageCode)
                ->get()
                ->getResultArray();

            // Create a map of section_id to translated description
            $translatedDescriptions = [];
            foreach ($translations as $translation) {
                if (!empty($translation['description'])) {
                    $translatedDescriptions[$translation['section_id']] = $translation['description'];
                }
            }

            // Get descriptions from main table for sections without translations (default language only)
            $defaultLanguage = get_default_language();
            if ($languageCode === $defaultLanguage) {
                $sectionsWithoutTranslation = array_diff($sectionIds, array_keys($translatedDescriptions));

                if (!empty($sectionsWithoutTranslation)) {
                    $mainSections = $this->select('id, description')
                        ->whereIn('id', $sectionsWithoutTranslation)
                        ->findAll();

                    foreach ($mainSections as $section) {
                        if (!empty($section['description'])) {
                            $translatedDescriptions[$section['id']] = $section['description'];
                        }
                    }
                }
            }

            return $translatedDescriptions;
        } catch (\Exception $e) {
            // Log error and fallback to main table for default language only
            log_message('error', 'Error fetching translated section descriptions: ' . $e->getMessage());

            // Check if this is the default language
            $defaultLanguage = get_default_language();
            if ($languageCode === $defaultLanguage) {
                // For default language, fallback to main table
                $mainSections = $this->select('id, description')
                    ->whereIn('id', $sectionIds)
                    ->findAll();

                $fallbackDescriptions = [];
                foreach ($mainSections as $section) {
                    if (!empty($section['description'])) {
                        $fallbackDescriptions[$section['id']] = $section['description'];
                    }
                }
                return $fallbackDescriptions;
            } else {
                // For non-default languages, return empty array
                return [];
            }
        }
    }
}
