<?php

namespace App\Controllers\Apis\Customer;

use App\Controllers\BaseController;
use App\Libraries\JWT;
use App\Models\Blog_model;
use App\Models\Blog_category_model;

class BlogApiController extends BaseController
{
    protected $request, $trans, $db, $data;
    protected JWT $JWT;
    protected $user_details = [];
    protected $excluded_routes =
    [
        "api/v1/index",
        "api/v1",
        "api/v1/get_blogs",
        "api/v1/get_blog_details",
        "api/v1/get_blog_categories",
        "api/v1/get_blog_tags",
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

    public function get_blogs()
    {
        try {
            // Initialize blog model
            $blogModel = new Blog_model();

            // Get filter parameters from request
            $params = $this->extractBlogParams();

            // Get current language from request headers for translations
            $languageCode = get_current_language_from_request();
            $params['language_code'] = $languageCode;

            // Use model's list method for data fetching and filtering
            // This leverages the model's built-in pagination, search, and sorting capabilities
            $blogData = $blogModel->list($params);

            // Check if we have data - handle both 'rows' (datatable format) and 'data' (array format)
            $blogRows = null;
            $total = 0;

            if (isset($blogData['data'])) {
                $blogRows = $blogData['data'];
                $total = $blogData['total'] ?? 0;
            }


            if (!$blogRows || empty($blogRows)) {
                return $this->response->setJSON([
                    'error' => false,
                    'message' => labels(NO_BLOGS_FOUND, 'No blogs found'),
                    'total' => 0,
                    'data' => []
                ]);
            }

            // Process and format blog data for API response
            $formattedBlogs = $this->formatBlogsForApi($blogRows);

            // Return successful response with blogs data only
            return $this->response->setJSON([
                'error' => false,
                'message' => labels(BLOGS_FETCHED_SUCCESSFULLY, 'Blogs fetched successfully'),
                'total' => $total,
                'data' => $formattedBlogs
            ]);
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_GET) . " Issue => " . $th->getMessage(), date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - get_blogs()');
            return $this->response->setJSON($response);
        }
    }

    public function get_blog_details()
    {
        try {
            // Get the slug and id from POST data
            $slug = $this->request->getPost('slug');
            $id = $this->request->getPost('id');

            // Get current language from request headers for translations
            $languageCode = get_current_language_from_request();

            // Use Blog_model to fetch the blog by id or slug with category details
            $blogModel = new Blog_model();
            if (!empty($id)) {
                // If id is provided, fetch by id with category details and translations
                $blog_details = $blogModel->getBlogByIdWithCategory($id, $languageCode);
            } elseif (!empty($slug)) {
                // If slug is provided, fetch by slug with category details and translations
                $blog_details = $blogModel->getBlogBySlugWithCategory($slug, $languageCode);
            } else {
                // If neither is provided, return error
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(EITHER_ID_OR_SLUG_IS_REQUIRED, 'Either id or slug is required')
                ]);
            }

            // If no blog found, return error
            if (empty($blog_details)) {
                return $this->response->setJSON([
                    'error' => true,
                    'message' => labels(BLOG_NOT_FOUND, 'Blog not found')
                ]);
            }

            $fileService = service('fileService');

            // Build full image URL if image exists
            $blog_details['image'] = !empty($blog_details['image'])
                ? $fileService->url($blog_details['image'], 'blogs/images')
                : '';

            // Fetch tags for this blog using the model method with language support
            $blog_details['tags'] = $blogModel->getTagsForBlog($blog_details['id'], $languageCode);

            // Fetch SEO settings with translations for the requested language
            helper('seo_translations');
            $defaultLanguage = get_default_language();
            $seoSettings = getBlogSeoWithTranslations($blog_details['id'], $languageCode, $defaultLanguage);

            // Format SEO settings for API response
            $formattedSeoSettings = [];
            if (!empty($seoSettings)) {
                $formattedSeoSettings['seo_title'] = $seoSettings['translated_title'] ?? $seoSettings['title'] ?? '';
                $formattedSeoSettings['seo_description'] = $seoSettings['translated_description'] ?? $seoSettings['description'] ?? '';
                $formattedSeoSettings['seo_keywords'] = $seoSettings['translated_keywords'] ?? $seoSettings['keywords'] ?? '';

                // Format SEO image URL if exists
                $formattedSeoSettings['seo_og_image'] = !empty($seoSettings['image'])
                    ? $fileService->url($seoSettings['image'], 'blog_seo_settings')
                    : '';

                $formattedSeoSettings['seo_schema_markup'] = $seoSettings['translated_schema_markup'] ?? $seoSettings['schema_markup'] ?? '';
            }

            // Merge SEO settings into blog details
            $blog_details = array_merge($blog_details, $formattedSeoSettings);

            // Fetch related blogs by category using Blog_model with category details and translations
            $related_blogs = $blogModel->getRelatedBlogsWithTranslations(
                $blog_details['category_id'],
                $blog_details['id'],
                5,
                $languageCode
            );

            // Add image URLs and tags to related blogs with language support
            foreach ($related_blogs as &$related) {
                $related['image'] = !empty($related['image']) ? $fileService->url($related['image'], 'blogs/images') : '';
                $related['tags'] = $blogModel->getTagsForBlog($related['id'], $languageCode);
            }

            // Return structured response
            return $this->response->setJSON([
                'error' => false,
                'message' => labels(BLOG_FETCHED_SUCCESSFULLY, 'Blog fetched successfully'),
                'data' => [
                    'blog' => $blog_details,
                    'related_blogs' => $related_blogs
                ]
            ]);
        } catch (\Throwable $th) {
            // Log and return error response
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_GET) . " Issue => " . $th->getMessage(), date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - get_blog_details()');
            return $this->response->setJSON($response);
        }
    }

    public function get_blog_categories()
    {
        try {
            // Get current language from request headers for translations
            $languageCode = get_current_language_from_request();

            // Instantiate the Blog_category_model
            $categoryModel = new Blog_category_model();
            // Fetch all active categories with translations for the specified language
            // Use the enhanced list method that supports translations
            $categories = $categoryModel->list([
                'format' => 'array',
                'language_code' => $languageCode,
                'status_filter' => 1, // Only active categories
                'limit' => 1000, // Get all categories
                'sort' => 'id',
                'order' => 'ASC'
            ]);
            if (!empty($categories['data'])) {
                // Instantiate Blog_model for counting blogs
                $blogModel = new Blog_model();

                // For each category, count the number of blogs
                // The model now properly handles name and translated_name fields with fallbacks
                foreach ($categories['data'] as &$category) {
                    // Add blog count
                    $category['blog_count'] = $blogModel->where('category_id', $category['id'])->countAllResults();
                }

                // Build response with translated names
                return $this->response->setJSON([
                    'error' => false,
                    'message' => labels(BLOG_CATEGORIES_FETCHED_SUCCESSFULLY, 'Blog categories fetched successfully'),
                    'data' => $categories['data']
                ]);
            } else {
                // No categories found
                return $this->response->setJSON([
                    'error' => false,
                    'message' => labels(NO_BLOG_CATEGORIES_FOUND, 'No blog categories found'),
                    'data' => []
                ]);
            }
        } catch (\Throwable $th) {
            // Log and return error response
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_GET) . " Issue => " . $th->getMessage(), date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - get_blog_categories()');
            return $this->response->setJSON($response);
        }
    }

    public function get_blog_tags()
    {
        try {
            // Get current language from request headers for translations
            $languageCode = get_current_language_from_request();

            // Use Blog_model to fetch all tags with translations
            $blogModel = new Blog_model();
            $tags = $blogModel->getAllTags($languageCode);

            // Filter tags to only include those with at least one blog
            $filteredTags = [];
            foreach ($tags as $tag) {
                $blogs = $blogModel->getBlogsByTag($tag['id']);
                if (!empty($blogs)) {
                    // Include both original and translated names for backward compatibility
                    $tagData = [
                        'id' => $tag['id'],
                        'name' => $tag['name'], // Original name
                        'slug' => $tag['slug'],
                        'translated_name' => $tag['translated_name'], // Translated name
                    ];
                    $filteredTags[] = $tagData;
                }
            }

            // Build response
            return $this->response->setJSON([
                'error' => false,
                'message' => labels(BLOG_TAGS_FETCHED_SUCCESSFULLY, 'Blog tags fetched successfully'),
                'data' => [
                    'tags' => $filteredTags,
                    'total_tags' => count($filteredTags)
                ]
            ]);
        } catch (\Throwable $th) {
            // Fix the error logging - remove throw before log
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_GET) . " Issue => " . $th->getMessage(), date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - get_blog_tags()');
            return $this->response->setJSON($response);
        }
    }


    // Private Helper Functions
    private function extractBlogParams()
    {
        // Get parameters from GET request
        $params = [
            'limit' => (int) $this->request->getGet('limit') ?: 10,
            'offset' => (int) $this->request->getGet('offset') ?: 0,
            'search' => $this->request->getGet('search') ?: '',
            'sort' => $this->request->getGet('sort') ?: 'created_at',
            'order' => $this->request->getGet('order') ?: 'DESC',
            // Category slug filter (string)
            'category_slug' => $this->request->getGet('category') ?: '',
            // Category ID filter (int)
            'category_id' => $this->request->getGet('category_id') ? (int)$this->request->getGet('category_id') : null,
            // Tag filter: can be a single slug or an array of slugs
            'tag_filter' => $this->request->getGet('tag'),
            'format' => 'array' // Use array format instead of datatable
        ];

        // If tag_filter is a JSON array, decode it
        if (is_string($params['tag_filter'])) {
            // Try to decode as JSON array
            $decoded = json_decode($params['tag_filter'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $params['tag_filter'] = $decoded;
            }
        }

        // Validate and sanitize parameters
        $params['limit'] = max(1, min(100, $params['limit'])); // Limit between 1 and 100
        $params['offset'] = max(0, $params['offset']); // Offset must be non-negative

        // Validate sort field
        $allowedSortFields = ['id', 'title', 'created_at', 'updated_at'];
        if (!in_array($params['sort'], $allowedSortFields)) {
            $params['sort'] = 'created_at';
        }

        // Validate order
        $params['order'] = strtoupper($params['order']) === 'ASC' ? 'ASC' : 'DESC';

        return $params;
    }

    private function formatBlogsForApi($blogRows)
    {
        $formattedBlogs = [];
        $fileService = service('fileService');

        foreach ($blogRows as $blog) {
            // Build full image path
            $image = !empty($blog['image']) ? $fileService->url($blog['image'], 'blogs/images') : '';

            // Format blog data for API response
            $formattedBlog = [
                'id' => (int) $blog['id'],
                'title' => $blog['title'] ?? '',
                'slug' => $blog['slug'] ?? '',
                'image' => $image,
                'description' => $blog['description'] ?? '',
                'short_description' => $blog['short_description'] ?? '',
                'created_at' => $blog['created_at'] ?? '',
                'translated_category_name' => $blog['translated_category_name'] ?? '',
                // Add translated fields for localized content
                'translated_title' => $blog['translated_title'] ?? '',
                'translated_short_description' => $blog['translated_short_description'] ?? '',
                'translated_description' => $blog['translated_description'] ?? '',
            ];

            // Add category information if available
            if (isset($blog['category_name']) && !empty($blog['category_name'])) {
                $formattedBlog['category_name'] = $blog['category_name'];
            }

            $formattedBlogs[] = $formattedBlog;
        }

        return $formattedBlogs;
    }
}
