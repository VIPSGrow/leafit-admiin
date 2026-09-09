<?php

namespace App\Entities;

use CodeIgniter\Entity\Entity;

/**
 * Partner Entity
 * 
 * Handles partner data transformation and language-specific display
 * Implements DRY principles for partner data handling
 */
class PartnerEntity extends Entity
{
    protected $attributes = [
        'partner_id' => null,
        'company_name' => null,
        'about' => null,
        'long_description' => null,
        'partner_name' => null,
        'mobile' => null,
        'email' => null,
        'address' => null,
        'balance' => null,
        'ratings' => null,
        'number_of_ratings' => null,
        'type' => null,
        'status' => null,
        'is_approved' => null,
        'created_at' => null,
        'updated_at' => null
    ];

    protected $casts = [
        'partner_id' => 'integer',
        'balance' => 'float',
        'ratings' => 'float',
        'number_of_ratings' => 'integer',
        'is_approved' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get translated company name based on current language
     * 
     * @return string Translated company name or original if no translation
     */
    public function getTranslatedCompanyName(): string
    {
        $currentLang = get_current_language();
        $defaultLangCode = get_default_language();

        // If current language is the default language, return original
        if ($currentLang === $defaultLangCode) {
            return $this->company_name ?? '';
        }

        try {
            // Get translated data for current language
            $translationModel = new \App\Models\TranslatedPartnerDetails_model();
            $translatedData = $translationModel->getTranslatedDetails($this->partner_id, $currentLang);

            if ($translatedData && !empty($translatedData['company_name'])) {
                return $translatedData['company_name'];
            }
        } catch (\Exception $e) {
            // Log error but don't break the function
            log_message('error', 'Translation processing failed in getTranslatedCompanyName: ' . $e->getMessage());
        }

        // Return original if no translation found
        return $this->company_name ?? '';
    }

    /**
     * Get translated about text based on current language
     * 
     * @return string Translated about text or original if no translation
     */
    public function getTranslatedAbout(): string
    {
        $currentLang = get_current_language();
        $defaultLangCode = get_default_language();

        // If current language is the default language, return original
        if ($currentLang === $defaultLangCode) {
            return $this->about ?? '';
        }

        try {
            // Get translated data for current language
            $translationModel = new \App\Models\TranslatedPartnerDetails_model();
            $translatedData = $translationModel->getTranslatedDetails($this->partner_id, $currentLang);

            if ($translatedData && !empty($translatedData['about'])) {
                return $translatedData['about'];
            }
        } catch (\Exception $e) {
            // Log error but don't break the function
            log_message('error', 'Translation processing failed in getTranslatedAbout: ' . $e->getMessage());
        }

        // Return original if no translation found
        return $this->about ?? '';
    }

    /**
     * Get translated long description based on current language
     * 
     * @return string Translated long description or original if no translation
     */
    public function getTranslatedLongDescription(): string
    {
        $currentLang = get_current_language();
        $defaultLangCode = get_default_language();

        // If current language is the default language, return original
        if ($currentLang === $defaultLangCode) {
            return $this->long_description ?? '';
        }

        try {
            // Get translated data for current language
            $translationModel = new \App\Models\TranslatedPartnerDetails_model();
            $translatedData = $translationModel->getTranslatedDetails($this->partner_id, $currentLang);

            if ($translatedData && !empty($translatedData['long_description'])) {
                return $translatedData['long_description'];
            }
        } catch (\Exception $e) {
            // Log error but don't break the function
            log_message('error', 'Translation processing failed in getTranslatedLongDescription: ' . $e->getMessage());
        }

        // Return original if no translation found
        return $this->long_description ?? '';
    }

    /**
     * Get partner status display text
     * 
     * @return string Status display text
     */
    public function getStatusDisplay(): string
    {
        return $this->is_approved == 1 ? labels('approved', 'Approved') : labels('disapproved', 'Disapproved');
    }

    /**
     * Get partner status badge class
     * 
     * @return string Bootstrap badge class
     */
    public function getStatusBadgeClass(): string
    {
        return $this->is_approved == 1 ? 'badge-success' : 'badge-danger';
    }

    /**
     * Get formatted ratings display
     * 
     * @return string Formatted ratings with stars
     */
    public function getFormattedRatings(): string
    {
        $rating = $this->ratings ?? 0;
        $numberOfRatings = $this->number_of_ratings ?? 0;

        $stars = '';
        for ($i = 1; $i <= 5; $i++) {
            if ($i <= $rating) {
                $stars .= '<i class="fas fa-star text-warning"></i>';
            } else {
                $stars .= '<i class="far fa-star text-warning"></i>';
            }
        }

        return $stars . ' <span class="text-muted">(' . $numberOfRatings . ')</span>';
    }

    /**
     * Get partner profile image URL
     * 
     * @return string Profile image URL
     */
    public function getProfileImageUrl(): string
    {
        return service('fileService')->url($this->image, 'profile', 'public/backend/assets/default.png');
    }

    /**
     * Get contact details for display
     * 
     * @return string Formatted contact details
     */
    public function getContactDetails(): string
    {
        if (!empty($this->email) && !empty($this->mobile)) {
            return ((defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) ? 'wrteam.' . substr($this->email, 6) : $this->email);
        } elseif (!empty($this->email)) {
            return ((defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0)) ? 'wrteam.' . substr($this->email, 6) : $this->email;
        } else {
            return ((defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0)) ? 'XXX-' . substr($this->mobile, 6) : $this->mobile;
        }
    }

    /**
     * Convert entity to array with translated data
     * 
     * @return array Array representation with translated fields
     */
    public function toArrayWithTranslations(): array
    {
        $data = $this->toArray();

        // Add translated fields
        $data['translated_company_name'] = $this->getTranslatedCompanyName();
        $data['translated_about'] = $this->getTranslatedAbout();
        $data['translated_long_description'] = $this->getTranslatedLongDescription();

        return $data;
    }
}
