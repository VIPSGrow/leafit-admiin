<?php

namespace App\Controllers\Apis\Customer;

use App\Controllers\BaseController;
use App\Models\HandymanReviewModel;
use App\Models\BookingHandymenModel;
use App\Models\Orders_model;

class HandymanReviewApiController extends BaseController
{
    protected array $user_details = [];

    public function __construct()
    {
        helper(['api', 'function', 'ResponceServices']);
        $this->request = \Config\Services::request();

        $token = verify_app_request();
        if ($token['error']) {
            header('Content-Type: application/json');
            http_response_code($token['status']);
            echo json_encode($token);
            exit;
        }
        $this->user_details = $token['data'];
    }

    public function save_handyman_review()
    {
        try {
            $userId = (int) $this->user_details['id'];
            $reviewId = (int) $this->request->getPost('id');
            $isEdit = $reviewId > 0;

            $validation = \Config\Services::validation();
            $validation->setRules([
                'order_id' => [
                    'rules' => 'required|integer',
                    'errors' => [
                        'required' => '{field} ' . labels('is_required'),
                        'integer' => '{field} ' . labels('must_be_an_integer'),
                    ],
                ],
                'handyman_id' => [
                    'rules' => 'required|integer',
                    'errors' => [
                        'required' => '{field} ' . labels('is_required'),
                        'integer' => '{field} ' . labels('must_be_an_integer'),
                    ],
                ],
                'rating' => [
                    'rules' => 'required|integer|greater_than[0]|less_than_equal_to[5]',
                    'errors' => [
                        'required' => '{field} ' . labels('is_required'),
                        'integer' => '{field} ' . labels('must_be_an_integer'),
                        'greater_than' => '{field} ' . labels('must_be_greater_than') . ' {param}',
                        'less_than_equal_to' => '{field} ' . labels('must_be_less_than_equal_to') . ' {param}',
                    ],
                ],
                'review' => [
                    'rules' => 'permit_empty|string',
                    'errors' => ['string' => 'review ' . labels('must_be_a_string')],
                ],
            ]);

            if (!$validation->withRequest($this->request)->run()) {
                $errors = $validation->getErrors();
                $firstError = reset($errors);
                return ApiError($firstError);
            }

            $orderId = (int) $this->request->getPost('order_id');
            $handymanId = (int) $this->request->getPost('handyman_id');
            $rating = (int) $this->request->getPost('rating');
            $review = (string) ($this->request->getPost('review') ?? '');

            $ordersModel = new Orders_model();
            $bookingHandymen = new BookingHandymenModel();
            $reviewModel = new HandymanReviewModel();

            $order = $ordersModel->where('id', $orderId)->where('user_id', $userId)->first();
            if (empty($order)) {
                return ApiError(labels(ORDER_DOES_NOT_EXIST));
            }
            if ($order['status'] !== 'completed') {
                return ApiError(labels('you_can_only_review_a_completed_booking'));
            }

            $assigned = $bookingHandymen->where('order_id', $orderId)->where('handyman_id', $handymanId)->first();
            if (empty($assigned)) {
                return ApiError(labels('handyman_not_assigned_to_this_booking'));
            }

            $db = \Config\Database::connect();

            if ($isEdit) {
                $existing = $reviewModel->where('id', $reviewId)->where('user_id', $userId)->first();
                if (empty($existing)) {
                    return ApiError(labels(DATA_NOT_FOUND, 'Review not found'));
                }

                $currentImages = json_decode($existing['images'] ?? '[]', true) ?: [];
                $imagesToDelete = $this->parseImagesToDelete();
                foreach ($imagesToDelete as $path) {
                    service('fileService')->delete('handyman_reviews', basename($path));
                    $currentImages = array_values(array_filter($currentImages, fn($img) => !str_ends_with($img, basename($path))));
                }

                $newImages = $this->uploadReviewImages();
                if ($newImages === false) {
                    return ApiError(labels(INVALID_IMAGE, 'Invalid image'));
                }

                $finalImages = array_values([...$currentImages, ...$newImages]);

                $db->transStart();

                $reviewModel->update($reviewId, [
                    'rating' => $rating,
                    'review' => $review,
                    'images' => !empty($finalImages) ? json_encode($finalImages) : null,
                ]);
                $reviewModel->recalculateAggregates($existing['handyman_id']);

                $db->transComplete();
                if (!$db->transStatus()) {
                    foreach ($newImages as $path) {
                        service('fileService')->delete('handyman_reviews', basename($path));
                    }
                    return ApiError(labels(ERROR_OCCURED, 'An error occurred'));
                }

                $fileService = service('fileService');
                $saved = $reviewModel->find($reviewId);
                $saved['images'] = $this->resolveImageUrls($saved['images'] ?? null, $fileService);
                return ApiSuccess(labels(DATA_UPDATED_SUCCESSFULLY, 'Review updated successfully'), $saved);
            }

            $duplicate = $reviewModel->where('order_id', $orderId)->where('user_id', $userId)->where('handyman_id', $handymanId)->first();
            if (!empty($duplicate)) {
                return ApiError(labels('already_reviewed_handyman'));
            }

            $images = $this->uploadReviewImages();
            if ($images === false) {
                return ApiError(labels(INVALID_IMAGE));
            }

            $db->transStart();

            $reviewModel->insert([
                'order_id' => $orderId,
                'user_id' => $userId,
                'handyman_id' => $handymanId,
                'rating' => $rating,
                'review' => $review,
                'images' => !empty($images) ? json_encode($images) : null,
            ]);
            $insertId = $reviewModel->insertID;
            $reviewModel->recalculateAggregates($handymanId);

            $db->transComplete();
            if (!$db->transStatus()) {
                foreach ($images as $path) {
                    service('fileService')->delete('handyman_reviews', basename($path));
                }
                return ApiError(labels(ERROR_OCCURED, 'An error occurred'));
            }

            $fileService = service('fileService');
            $saved = $reviewModel->find($insertId);
            $saved['images'] = $this->resolveImageUrls($saved['images'] ?? null, $fileService);
            return ApiSuccess(labels(DATA_SAVED_SUCCESSFULLY, 'Review saved successfully'), $saved);
        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Apis/Customer/HandymanReviewApiController.php - save_handyman_review()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return ApiError(labels(SOMETHING_WENT_WRONG, 'Something went wrong'));
        }
    }

    public function delete_handyman_review()
    {
        try {
            $userId = (int) $this->user_details['id'];

            $validation = \Config\Services::validation();
            $validation->setRules([
                'id' => [
                    'rules' => 'required|integer',
                    'errors' => [
                        'required' => '{field} ' . labels('is_required'),
                        'integer' => '{field} ' . labels('must_be_integer'),
                    ],
                ],
            ]);
            if (!$validation->withRequest($this->request)->run()) {
                $errors = $validation->getErrors();
                $firstError = reset($errors);
                return ApiError($firstError);
            }

            $reviewId = (int) $this->request->getPost('id');
            $reviewModel = new HandymanReviewModel();

            $existing = $reviewModel->where('id', $reviewId)->where('user_id', $userId)->first();
            if (empty($existing)) {
                return ApiError(labels(DATA_NOT_FOUND, 'Review not found'));
            }

            $handymanId = (int) $existing['handyman_id'];

            $db = \Config\Database::connect();
            $db->transStart();

            $reviewModel->delete($reviewId);
            $reviewModel->recalculateAggregates($handymanId);

            $db->transComplete();
            if (!$db->transStatus()) {
                return ApiError(labels(ERROR_OCCURED, 'An error occurred'));
            }

            return ApiSuccess(labels(DATA_DELETED_SUCCESSFULLY, 'Review deleted successfully'));
        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Apis/Customer/HandymanReviewApiController.php - delete_handyman_review()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return ApiError(labels(SOMETHING_WENT_WRONG, 'Something went wrong'));
        }
    }

    private function uploadReviewImages(): array|false
    {
        $uploaded = $this->request->getFiles();
        $paths = [];

        if (empty($uploaded['images'])) {
            return $paths;
        }

        $fileService = service('fileService');
        foreach ($uploaded['images'] as $file) {
            if (valid_image($file)) {
                return false;
            }
            $result = $fileService->upload($file, 'handyman_reviews');
            if ($result['error']) {
                return false;
            }
            $paths[] = $result['path'];
        }

        return $paths;
    }

    private function parseImagesToDelete(): array
    {
        $raw = $this->request->getPost('images_to_delete');
        if (empty($raw)) {
            return [];
        }
        if (\is_string($raw)) {
            $decoded = json_decode($raw, true);
            return (json_last_error() === JSON_ERROR_NONE && \is_array($decoded)) ? $decoded : [];
        }
        return \is_array($raw) ? $raw : [];
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
