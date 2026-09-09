<?php

namespace App\Controllers\Apis\Customer;

use App\Controllers\BaseController;
use App\Libraries\JWT;

class SeoSettingsApiController extends BaseController
{
    protected $request, $trans, $db, $data;
    protected JWT $JWT;
    protected $toDateTime;
    protected $user_details = [];
    protected $excluded_routes =
    [
        "api/v1/index",
        "api/v1",
        "api/v1/get_site_map_data",
        "api/v1/get_seo_settings"
    ];
    public function __construct()
    {
        helper('api');
        helper("function");
        helper('ResponceServices');
        $this->request = \Config\Services::request();
        $this->JWT = new JWT();
        $current_uri = uri_string();
        if (!in_array($current_uri, $this->excluded_routes)) {
            $token = verify_app_request();
            if ($token['error']) {
                header('Content-Type: application/json');
                http_response_code($token['status']);
                print_r(json_encode($token));
                die();
            }
            $this->user_details = $token['data'];
        } else {
            $token = verify_app_request();
            if (!$token['error'] && isset($token['data']) && !empty($token['data'])) {
                $this->user_details = $token['data'];
            }
        }
    }

    public function index()
    {
        $response = \Config\Services::response();
        helper("filesystem");
        $response->setHeader('content-type', 'Text');
        return $response->setBody(file_get_contents(base_url('apidocs.txt')));
    }

    public function get_seo_settings()
    {
        try {
            $page = $this->request->getPost('page');
            $slug = $this->request->getPost('slug');

            if (empty($page)) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels('page_is_required', 'Page is required'),
                ]);
            }

            $seo_settings = null;

            // Known general pages
            $generalPages = ['home', 'become-provider', 'landing-page', 'about-us', 'contact-us', 'providers-page', 'services-page', 'terms-and-conditions', 'privacy-policy', 'faqs', 'blogs', 'site-map'];

            // Known entity pages requiring a slug
            $entityPages = ['service-details', 'provider-details', 'blog-details', 'category-details'];

            if (in_array($page, $generalPages)) {
                helper('seo_translations');

                $requestedLanguage = get_current_language_from_request() ?: 'en';
                $defaultLanguage = get_default_language();

                $seo_settings = getGeneralSeoWithTranslations($page, $requestedLanguage, $defaultLanguage);
            } elseif (in_array($page, $entityPages)) {
                // Entity-specific pages
                switch ($page) {
                    case 'service-details':
                        $service_id = $this->fetchEntityIdBySlug('services', $slug);
                        if (is_array($service_id)) {
                            return $this->response->setJSON($service_id);
                        }

                        helper('seo_translations');

                        $requestedLanguage = get_current_language_from_request() ?: 'en';
                        $defaultLanguage = get_default_language();

                        $seo_settings = getServiceSeoWithTranslations($service_id, $requestedLanguage, $defaultLanguage);
                        break;

                    case 'provider-details':
                        $provider_id = $this->fetchEntityIdBySlug('partner_details', $slug);
                        if (is_array($provider_id)) {
                            return $this->response->setJSON($provider_id);
                        }

                        helper('seo_translations');

                        $requestedLanguage = get_current_language_from_request() ?: 'en';
                        $defaultLanguage = get_default_language();

                        $seo_settings = getProviderSeoWithTranslations($provider_id, $requestedLanguage, $defaultLanguage);
                        break;

                    case 'blog-details':
                        $blog_id = $this->fetchEntityIdBySlug('blogs', $slug);
                        if (is_array($blog_id)) {
                            return $this->response->setJSON($blog_id);
                        }

                        helper('seo_translations');

                        $requestedLanguage = get_current_language_from_request() ?: 'en';
                        $defaultLanguage = get_default_language();

                        $seo_settings = getBlogSeoWithTranslations($blog_id, $requestedLanguage, $defaultLanguage);
                        break;

                    case 'category-details':
                        $category_id = $this->fetchCategoryIdBySlug($slug);
                        if (is_array($category_id)) {
                            return $this->response->setJSON($category_id);
                        }

                        helper('seo_translations');

                        $requestedLanguage = get_current_language_from_request() ?: 'en';
                        $defaultLanguage = get_default_language();

                        $seo_settings = getCategorySeoWithTranslations($category_id, $requestedLanguage, $defaultLanguage);
                        break;
                }
            } else {
                // Auto-detect as custom page slug
                $customPageModel = new \App\Models\CustomPageModel();
                $customPage = $customPageModel->getBySlug($page);

                if (empty($customPage)) {
                    return $this->response->setJSON([
                        'error' => true,
                        'message' => labels('custom_page_not_found', 'Custom page not found'),
                    ]);
                }

                helper('seo_translations');

                $requestedLanguage = get_current_language_from_request() ?: 'en';
                $defaultLanguage = get_default_language();

                $seo_settings = getGeneralSeoWithTranslations('custom_page_' . $page, $requestedLanguage, $defaultLanguage);
            }

            // Strip translated_ prefix from keys so the response uses clean field names
            $data = [];
            foreach (($seo_settings ?? []) as $key => $value) {
                $cleanKey = strpos($key, 'translated_') === 0 ? substr($key, strlen('translated_')) : $key;
                $data[$cleanKey] = $value;
            }

            return $this->response->setJSON([
                'error' => false,
                'message' => labels(SEO_SETTINGS_FETCHED_SUCCESSFULLY, 'SEO settings fetched successfully!'),
                'data' => $data
            ]);
        } catch (\Throwable $th) {
            log_the_responce($this->request->header('Authorization') . ' Params: ' . json_encode($_POST) . " Issue: " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - get_seo_settings()');
            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }

    /**
     * Get site map data endpoint
     * 
     * This endpoint returns arrays of categories, providers, blogs, and services
     * Each array contains items with title and slug fields
     * Translations are applied based on the requested language
     * 
     * @return \CodeIgniter\HTTP\Response JSON response with site map data
     */
    public function get_site_map_data()
    {
        try {
            // Get current language from request headers for translations
            $languageCode = get_current_language_from_request();
            $defaultLanguage = get_default_language();

            // Initialize database connection
            $db = \Config\Database::connect();

            // Initialize result arrays
            $categories = [];
            $providers = [];
            $blogs = [];
            $services = [];

            // Fetch categories with title (name) and slug
            // Only get active categories (status = 1)
            $categoryData = fetch_details('categories', ['status' => 1], ['id', 'name', 'slug']);

            if (!empty($categoryData)) {
                foreach ($categoryData as $category) {
                    // Get translated category name
                    $translatedData = get_translated_category_data_for_api($category['id'], $category, ['name']);
                    $title = !empty($translatedData['translated_name'])
                        ? $translatedData['translated_name']
                        : ($category['name'] ?? '');

                    $categories[] = [
                        'title' => $title,
                        'slug' => $category['slug'] ?? ''
                    ];
                }
            }

            // Fetch providers with title (company_name) and slug
            // Only get approved providers (is_approved = 1) with active subscription
            $builder = $db->table('partner_details pd');
            $builder->select('pd.partner_id, pd.company_name, pd.slug')
                ->join('partner_subscriptions ps', 'ps.partner_id = pd.partner_id', 'inner')
                ->where('pd.is_approved', 1)
                ->where('ps.status', 'active')
                ->groupBy('pd.partner_id');

            $providerData = $builder->get()->getResultArray();

            if (!empty($providerData)) {
                foreach ($providerData as $provider) {
                    // Get translated provider company name
                    $translatedData = get_translated_partner_data_for_api($provider['partner_id'], ['company_name' => $provider['company_name']], $languageCode);
                    $title = !empty($translatedData['translated_company_name'])
                        ? $translatedData['translated_company_name']
                        : ($provider['company_name'] ?? '');

                    $providers[] = [
                        'title' => $title,
                        'slug' => $provider['slug'] ?? ''
                    ];
                }
            }

            // Fetch blogs with title and slug
            // Only get active blogs (status = 1)
            $blogData = fetch_details('blogs', [], ['id', 'title', 'slug']);

            if (!empty($blogData)) {
                // Get blog translations using TranslatedBlogDetailsModel
                $translatedBlogModel = new \App\Models\TranslatedBlogDetailsModel();

                foreach ($blogData as $blog) {
                    // Get translated blog title
                    // Try to get translation for requested language first
                    $requestedTranslation = $translatedBlogModel->getTranslation($blog['id'], $languageCode);
                    $translatedTitle = !empty($requestedTranslation['title']) ? $requestedTranslation['title'] : null;

                    // Fallback to default language if requested language not found
                    if (empty($translatedTitle) && $languageCode !== $defaultLanguage) {
                        $defaultTranslation = $translatedBlogModel->getTranslation($blog['id'], $defaultLanguage);
                        $translatedTitle = !empty($defaultTranslation['title']) ? $defaultTranslation['title'] : null;
                    }

                    // Final fallback to original title from main table
                    $title = !empty($translatedTitle) ? $translatedTitle : ($blog['title'] ?? '');

                    $blogs[] = [
                        'title' => $title,
                        'slug' => $blog['slug'] ?? ''
                    ];
                }
            }

            // Fetch services with title, slug, and provider information
            // Only get active and approved services (status = 1, approved_by_admin = 1)
            // Join with partner_details to get provider company_name and slug
            $builder = $db->table('services s');
            $builder->select('s.id, s.title, s.slug, s.user_id as partner_id, pd.company_name, pd.slug as provider_slug')
                ->join('partner_details pd', 'pd.partner_id = s.user_id', 'left')
                ->where('s.status', 1)
                ->where('s.approved_by_admin', 1);

            $serviceData = $builder->get()->getResultArray();

            if (!empty($serviceData)) {
                // Get service translations in batch for efficiency
                $serviceIds = array_column($serviceData, 'id');
                $serviceModel = new \App\Models\TranslatedServiceDetails_model();
                $serviceTranslations = $serviceModel->getAllTranslationsForMultipleServices($serviceIds);

                foreach ($serviceData as $service) {
                    $serviceId = $service['id'];
                    $translatedTitle = null;

                    // Try to get translation for requested language
                    if (
                        isset($serviceTranslations[$serviceId][$languageCode]['title'])
                        && !empty(trim($serviceTranslations[$serviceId][$languageCode]['title']))
                    ) {
                        $translatedTitle = trim($serviceTranslations[$serviceId][$languageCode]['title']);
                    }
                    // Fallback to default language
                    elseif (
                        isset($serviceTranslations[$serviceId][$defaultLanguage]['title'])
                        && !empty(trim($serviceTranslations[$serviceId][$defaultLanguage]['title']))
                    ) {
                        $translatedTitle = trim($serviceTranslations[$serviceId][$defaultLanguage]['title']);
                    }

                    // Final fallback to original title
                    $title = !empty($translatedTitle) ? $translatedTitle : ($service['title'] ?? '');

                    // Get translated provider company name with language fallback
                    // Use get_translated_partner_data_for_api helper function for proper translation handling
                    $providerCompanyName = '';
                    $providerSlug = $service['provider_slug'] ?? '';

                    if (!empty($service['partner_id'])) {
                        // Get translated partner data with requested language and fallback to default
                        $partnerData = get_translated_partner_data_for_api(
                            $service['partner_id'],
                            ['company_name' => $service['company_name'] ?? ''],
                            $languageCode
                        );

                        // Use translated_company_name if available, otherwise fallback to company_name
                        $providerCompanyName = !empty($partnerData['translated_company_name'])
                            ? $partnerData['translated_company_name']
                            : ($partnerData['company_name'] ?? $service['company_name'] ?? '');
                    }

                    $services[] = [
                        'title' => $title,
                        'slug' => $service['slug'] ?? '',
                        'provider_company_name' => $providerCompanyName,
                        'provider_slug' => $providerSlug
                    ];
                }
            }

            // Return successful response with all arrays
            return $this->response->setJSON([
                'error' => false,
                'message' => labels(DATA_FETCHED_SUCCESSFULLY, 'Data fetched successfully'),
                'data' => [
                    'categories' => $categories,
                    'providers' => $providers,
                    'blogs' => $blogs,
                    'services' => $services
                ]
            ]);
        } catch (\Throwable $th) {
            // throw $th;
            // Log the error for debugging
            log_the_responce(
                $this->request->header('Authorization') .
                    ' Params passed :: ' . json_encode($_POST) .
                    " Issue => " . $th->getMessage(),
                date("Y-m-d H:i:s") .
                    '--> app/Controllers/api/V1.php - get_site_map_data()'
            );

            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
                'data' => [
                    'categories' => [],
                    'providers' => [],
                    'blogs' => [],
                    'services' => []
                ]
            ]);
        }
    }

    private function validatePageAndSlug($page, $slug)
    {
        $validation = \Config\Services::validation();
        $validation->setRules([
            'page' => 'required|in_list[home,become-provider,landing-page,about-us,contact-us,providers-page,services-page,terms-and-conditions,privacy-policy,faqs,blogs,site-map,service-details,provider-details,category-details,blog-details]',
            'slug' => 'permit_empty'
        ], [
            'page' => [
                'required' => 'Page is required',
                'in_list' => 'Invalid page name'
            ]
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return [
                'error' => true,
                'message' => $validation->getErrors(),
                'data' => []
            ];
        }
        return null;
    }

    private function fetchEntityIdBySlug($table, $slug)
    {
        $field = $table == 'partner_details' ? 'partner_id' : 'id';
        $result = fetch_details($table, ['slug' => $slug], [$field]);
        if (empty($result)) {
            return [
                'error' => true,
                'message' => ucfirst(str_replace('_', ' ', $table)) . ' not found',
                'data' => []
            ];
        }
        return $result[0][$field];
    }

    private function fetchCategoryIdBySlug($slug)
    {
        $result = fetch_details('categories', ['slug' => $slug], ['id']);
        if (empty($result)) {
            return [
                'error' => true,
                'message' => 'Category or subcategory not found',
                'data' => []
            ];
        }
        return $result[0]['id'];
    }
}
