<?php

namespace App\Controllers\Apis\Provider;

use App\Controllers\BaseController;
use App\Models\ProviderLocationsModel;

class LocationsApiController extends BaseController
{
    protected $request;
    protected $user_details = [];

    public function __construct()
    {
        helper('api');
        helper('function');
        $this->request = \Config\Services::request();

        $token = verify_app_request();
        if (!$token['error'] && !empty($token['data'])) {
            $this->user_details = $token['data'];
            return;
        }

        $this->response->setStatusCode($token['status'])->setJSON([
            'error' => true,
            'message' => $token['message'] ?? 'Unauthorized',
            'status' => 401,
        ])->send();
        exit;
    }

    public function get_locations()
    {
        try {
            $model = new ProviderLocationsModel();
            $locations = $model->where('provider_id', (int) $this->user_details['id'])
                ->orderBy('is_default', 'DESC')
                ->orderBy('id', 'ASC')
                ->findAll();

            return $this->response->setJSON([
                'error' => false,
                'message' => 'Locations fetched successfully',
                'data' => $locations,
            ]);
        } catch (\Throwable $e) {
            log_message('error', '[PROVIDER_LOCATIONS_GET] ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'error' => true,
                'message' => 'Unable to fetch provider locations',
                'data' => [],
            ]);
        }
    }

    public function manage_location()
    {
        $rules = [
            'location_id' => 'permit_empty|is_natural',
            'address' => 'required|trim',
            'city' => 'permit_empty|trim|max_length[191]',
            'latitude' => 'required|numeric|greater_than_equal_to[-90]|less_than_equal_to[90]',
            'longitude' => 'required|numeric|greater_than_equal_to[-180]|less_than_equal_to[180]',
            'is_default' => 'permit_empty|in_list[0,1]',
            'is_active' => 'permit_empty|in_list[0,1]',
        ];
        if (!$this->validate($rules)) {
            return $this->response->setStatusCode(422)->setJSON([
                'error' => true,
                'message' => $this->validator->getErrors(),
            ]);
        }

        $providerId = (int) $this->user_details['id'];
        $model = new ProviderLocationsModel();
        $locationId = (int) ($this->request->getPost('location_id') ?: 0);
        $isDefault = (int) ($this->request->getPost('is_default') ?: 0);
        $isActive = (int) ($this->request->getPost('is_active') ?? 1);
        $now = date('Y-m-d H:i:s');
        $data = [
            'provider_id' => $providerId,
            'address' => trim((string) $this->request->getPost('address')),
            'city' => trim((string) ($this->request->getPost('city') ?? '')),
            'latitude' => number_format((float) $this->request->getPost('latitude'), 7, '.', ''),
            'longitude' => number_format((float) $this->request->getPost('longitude'), 7, '.', ''),
            'is_default' => $isDefault,
            'is_active' => $isActive,
            'updated_at' => $now,
        ];

        if ($locationId > 0) {
            $existing = $model->where('id', $locationId)->where('provider_id', $providerId)->first();
            if (!$existing) {
                return $this->response->setStatusCode(404)->setJSON([
                    'error' => true,
                    'message' => 'Location not found',
                ]);
            }
            $model->update($locationId, $data);
        } else {
            $data['created_at'] = $now;
            $locationId = $model->insert($data, true);
        }

        if (!$locationId) {
            return $this->response->setStatusCode(400)->setJSON([
                'error' => true,
                'message' => 'Location could not be saved',
            ]);
        }

        $saved = $model->find($locationId);
        if ($isDefault || !$this->hasDefaultLocation($model, $providerId)) {
            $this->setDefaultLocation($model, $saved);
            $saved = $model->find($locationId);
        }

        return $this->response->setJSON([
            'error' => false,
            'message' => 'Location saved successfully',
            'data' => $saved,
        ]);
    }

    public function delete_location()
    {
        $locationId = (int) ($this->request->getPost('location_id') ?: 0);
        $providerId = (int) $this->user_details['id'];
        $model = new ProviderLocationsModel();
        $location = $model->where('id', $locationId)->where('provider_id', $providerId)->first();

        if (!$location) {
            return $this->response->setStatusCode(404)->setJSON([
                'error' => true,
                'message' => 'Location not found',
            ]);
        }

        $model->delete($locationId);
        if ((int) $location['is_default'] === 1) {
            $replacement = $model->where('provider_id', $providerId)->where('is_active', 1)->orderBy('id', 'ASC')->first();
            if ($replacement) {
                $this->setDefaultLocation($model, $replacement);
            }
        }

        return $this->response->setJSON([
            'error' => false,
            'message' => 'Location deleted successfully',
        ]);
    }

    private function hasDefaultLocation(ProviderLocationsModel $model, int $providerId): bool
    {
        return $model->where('provider_id', $providerId)->where('is_default', 1)->countAllResults() > 0;
    }

    private function setDefaultLocation(ProviderLocationsModel $model, array $location): void
    {
        $providerId = (int) $location['provider_id'];
        $model->where('provider_id', $providerId)->set(['is_default' => 0])->update();
        $model->update((int) $location['id'], ['is_default' => 1]);

        update_details([
            'latitude' => $location['latitude'],
            'longitude' => $location['longitude'],
            'city' => $location['city'],
        ], ['id' => $providerId], 'users');
        update_details([
            'address' => $location['address'],
        ], ['partner_id' => $providerId], 'partner_details', false);
    }
}
