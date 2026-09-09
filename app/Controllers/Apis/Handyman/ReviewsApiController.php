<?php

namespace App\Controllers\Apis\Handyman;

use App\Controllers\BaseController;
use App\Models\HandymanReviewModel;

class ReviewsApiController extends BaseController
{
    protected array $user_details = [];
    protected HandymanReviewModel $reviewModel;

    public function __construct()
    {
        helper(['api', 'function', 'ResponceServices']);
        $this->request = \Config\Services::request();
        $this->reviewModel = new HandymanReviewModel();

        $token = verify_app_request();
        if (!$token['error'] && isset($token['data']) && !empty($token['data'])) {
            $this->user_details = $token['data'];
        } else {
            ApiError($token['message'])->setStatusCode($token['status'] ?? 403)->send();
            exit;
        }
    }

    /**
     * Get reviews for the logged-in handyman.
     * POST partner/api/v1/get_my_reviews
     *
     * Params:
     *   limit   (optional, default 10)
     *   offset  (optional, default 0)
     *   search  (optional) — matches customer_id, customer username, or review text
     */
    public function get_my_reviews()
    {
        try {
            $handymanId = (int) $this->user_details['id'];
            $limit      = (int) ($this->request->getPost('limit') ?: 10);
            $offset     = (int) ($this->request->getPost('offset') ?: 0);
            $search     = trim((string) ($this->request->getPost('search') ?? ''));

            $result = $this->reviewModel->listForHandyman($handymanId, $limit, $offset, $search);

            $fileService = service('fileService');
            foreach ($result['data'] as &$row) {
                $row['rating']         = (int) $row['rating'];
                $row['customer_image'] = $fileService->url($row['customer_image'], 'profile');
                $row['images']         = $this->resolveImageUrls($row['images'] ?? null, $fileService);
            }
            unset($row);

            return ApiSuccess(DATA_FETCHED_SUCCESSFULLY, $result['data'], ['total' => $result['total']]);
        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Apis/Handyman/ReviewsApiController.php - get_my_reviews()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return ApiError(SOMETHING_WENT_WRONG);
        }
    }

    private function resolveImageUrls(mixed $imagesJson, $fileService): array
    {
        if (empty($imagesJson)) {
            return [];
        }
        $paths = \is_string($imagesJson) ? (json_decode($imagesJson, true) ?: []) : (array) $imagesJson;
        return array_map(fn($p) => $fileService->url($p, 'handyman_reviews'), $paths);
    }
}
