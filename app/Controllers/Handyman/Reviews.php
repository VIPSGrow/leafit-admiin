<?php

namespace App\Controllers\Handyman;

use App\Models\HandymanReviewModel;

class Reviews extends Handyman
{
    public function __construct()
    {
        parent::__construct();
        helper('ResponceServices');
    }

    public function index()
    {
        $this->data['title'] = labels('my_reviews', 'My Reviews');
        $this->data['breadcrumbs'] = [
            ['label' => labels('Dashboard', 'Dashboard'), 'url' => base_url('handyman/dashboard'), 'icon' => 'fas fa-home-alt'],
            ['label' => labels('my_reviews', 'My Reviews')],
        ];

        return view('backend/handyman/pages/reviews', $this->data);
    }

    public function list_data()
    {
        $handymanId = (int) $this->ionAuth->user()->row()->id;

        $limit  = (int) ($this->request->getGet('limit') ?: 10);
        $offset = (int) ($this->request->getGet('offset') ?: 0);
        $search = trim((string) ($this->request->getGet('search') ?: ''));

        $reviewModel = model(HandymanReviewModel::class);
        $result = $reviewModel->listForHandyman($handymanId, $limit, $offset, $search);

        $fileService = service('fileService');

        foreach ($result['data'] as &$row) {
            $rating = (int) $row['rating'];

            $row['customer_image'] = $fileService->url($row['customer_image'] ?? '', 'profile');
            $row['customer_cell'] = '<div class="d-flex align-items-center">'
                . '<img src="' . esc($row['customer_image']) . '" class="rounded-circle me-2" width="36" height="36" style="object-fit: cover;">'
                . '<span>' . esc($row['customer_name'] ?? '-') . '</span>'
                . '</div>';

            $row['rating_stars'] = $this->renderStars($rating);

            $images = $this->resolveImageUrls($row['images'] ?? null, $fileService);
            $row['images_cell'] = !empty($images)
                ? '<button type="button" class="btn btn-sm btn-light border view-review-images" data-images=\'' . json_encode($images) . '\'>'
                    . '<i class="fas fa-images"></i> ' . labels('view_images', 'View Images') . '</button>'
                : '<span class="text-muted">' . labels('no_images', 'No Images') . '</span>';

            $row['rated_on'] = !empty($row['created_at']) ? date('M d, Y h:i A', strtotime($row['created_at'])) : '-';
            $row['review_text'] = !empty($row['review']) ? esc($row['review']) : '<span class="text-muted">-</span>';

            unset($row['images'], $row['review'], $row['created_at']);
        }
        unset($row);

        return $this->response->setJSON(['total' => $result['total'], 'rows' => $result['data']]);
    }

    private function renderStars(int $rating): string
    {
        $rating = max(0, min(5, $rating));
        $html = '<div class="text-warning" title="' . $rating . '/5">';
        for ($i = 1; $i <= 5; $i++) {
            $html .= '<i class="fa' . ($i <= $rating ? 's' : 'r') . ' fa-star"></i>';
        }
        $html .= '</div>';

        return $html;
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
