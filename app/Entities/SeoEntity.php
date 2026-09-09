<?php

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class SeoEntity extends Entity
{
    protected $datamap = [];
    protected $dates = ['created_at', 'updated_at'];
    protected $casts = [];

    /**
     * Get formatted SEO data for API responses
     * 
     * @return array
     */
    public function getFormattedData(): array
    {
        $data = [
            'id' => $this->id ?? null,
            'title' => $this->title ?? '',
            'description' => $this->description ?? '',
            'keywords' => $this->keywords ?? '',
            'schema_markup' => $this->schema_markup ?? '',
            'image' => $this->getFormattedImage(),
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
            'updated_at' => $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : null,
        ];

        // Add reference ID based on context
        if (isset($this->service_id)) {
            $data['service_id'] = $this->service_id;
        }
        if (isset($this->category_id)) {
            $data['category_id'] = $this->category_id;
        }
        if (isset($this->partner_id)) {
            $data['partner_id'] = $this->partner_id;
        }
        if (isset($this->blog_id)) {
            $data['blog_id'] = $this->blog_id;
        }
        if (isset($this->page)) {
            $data['page'] = $this->page;
        }

        return $data;
    }

    /**
     * Get formatted image URL
     * 
     * @return string
     */
    public function getFormattedImage(): string
    {
        if (empty($this->image)) {
            return '';
        }

        return service('fileService')->url($this->image, $this->getSeoImageFolder($this->getSeoType()));
    }

    /**
     * Determine SEO type based on available fields
     * 
     * @return string
     */
    private function getSeoType(): string
    {
        if (isset($this->service_id)) {
            return 'services';
        }
        if (isset($this->category_id)) {
            return 'categories';
        }
        if (isset($this->partner_id)) {
            return 'providers';
        }
        if (isset($this->blog_id)) {
            return 'blogs';
        }
        if (isset($this->page) && strpos($this->page, 'custom_page_') === 0) {
            return 'custom_pages';
        }
        return 'general';
    }

    /**
     * Map SEO type to its FileService folder key.
     *
     * @param string $seoType
     * @return string
     */
    private function getSeoImageFolder(string $seoType): string
    {
        switch ($seoType) {
            case 'services':
                return 'service_seo_settings';
            case 'categories':
                return 'category_seo_settings';
            case 'providers':
                return 'provider_seo_settings';
            case 'blogs':
                return 'blog_seo_settings';
            case 'custom_pages':
                return 'custom_page_seo_settings';
            default:
                return 'seo_settings';
        }
    }

    /**
     * Get compact SEO data for API responses (minimal fields)
     * 
     * @return array
     */
    public function getCompactData(): array
    {
        return [
            'title' => $this->title ?? '',
            'description' => $this->description ?? '',
            'keywords' => $this->keywords ?? '',
            'image' => $this->getFormattedImage(),
        ];
    }

    /**
     * Get SEO data for meta tags
     * 
     * @return array
     */
    public function getMetaData(): array
    {
        return [
            'title' => $this->title ?? '',
            'description' => $this->description ?? '',
            'keywords' => $this->keywords ?? '',
            'image' => $this->getFormattedImage(),
            'schema_markup' => $this->schema_markup ?? '',
        ];
    }

    /**
     * Check if SEO data exists (has any meaningful content)
     * 
     * @return bool
     */
    public function hasContent(): bool
    {
        return !empty($this->title) ||
            !empty($this->description) ||
            !empty($this->keywords) ||
            !empty($this->image) ||
            !empty($this->schema_markup);
    }
}
