<?php

namespace App\Controllers\Apis\Customer;

use App\Controllers\BaseController;
use App\Models\Service_model;
use App\Models\Partners_model;

class ServicesApiController extends BaseController
{
    protected $request, $trans, $db, $data;
    protected $user_details = [];

    public function __construct()
    {
        helper('api');
        helper("function");
        helper('ResponceServices');
        $this->request = \Config\Services::request();
    }

    public function index()
    {
        $response = \Config\Services::response();
        helper("filesystem");
        $response->setHeader('content-type', 'Text');
        return $response->setBody(file_get_contents(base_url('apidocs.txt')));
    }

    public function get_services()
    {
        try {
            $Service_model = new Service_model();
            $limit = !empty($this->request->getPost('limit')) ? $this->request->getPost('limit') : 10;
            $offset = ($this->request->getPost('offset') && !empty($this->request->getPost('offset'))) ? $this->request->getPost('offset') : 0;
            $sort = ($this->request->getPost('sort') && !empty($this->request->getPost('sort'))) ? $this->request->getPost('sort') : 'id';
            $order = ($this->request->getPost('order') && !empty($this->request->getPost('order'))) ? $this->request->getPost('order') : 'ASC';
            $search = ($this->request->getPost('search') && !empty($this->request->getPost('search'))) ? $this->request->getPost('search') : '';
            $where = $additional_data = [];
            $where = [];
            $where['s.status'] = 1;
            $where['s.approved_by_admin'] = 1;
            $at_store = 0;
            $at_doorstep = 0;

            // Populate additional_data with location information if provided
            // This is needed to calculate is_Available_at_location in Service_model
            // Follows the same pattern as other APIs in V1.php
            // If latitude/longitude are not provided or null (as string), is_Available_at_location will be "0"
            $settings = get_settings('general_settings', true);

            // Helper function to check if latitude/longitude are valid
            // Returns true if both are provided and not null/empty/"null" string
            $isValidLocation = function ($lat, $lng) {
                return !empty($lat) && $lat !== 'null' && $lat !== null &&
                    !empty($lng) && $lng !== 'null' && $lng !== null;
            };

            // Get latitude and longitude from request
            $latitude = $this->request->getPost('latitude');
            $longitude = $this->request->getPost('longitude');
            $hasValidLocation = $isValidLocation($latitude, $longitude);

            // Only add location data if valid
            // If invalid, Service_model will set is_Available_at_location to "0" as string
            if ($hasValidLocation) {
                $additional_data = [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'max_serviceable_distance' => !empty($settings['max_serviceable_distance']) ? $settings['max_serviceable_distance'] : 0,
                ];
            }

            $provider_slug = $this->request->getPost('provider_slug');
            $service_slug = $this->request->getPost('slug');

            if (!empty($provider_slug)) {
                $where['pd.slug'] = $provider_slug;
            }
            if (!empty($service_slug)) {
                $where['s.slug'] = $service_slug;
            }

            if (!empty($provider_slug)) {
                $provider_row = (new Partners_model())
                    ->select('at_store, at_doorstep')
                    ->where('slug', $provider_slug)
                    ->first();
                if (empty($provider_row)) {
                    return response_helper(labels(PROVIDER_NOT_FOUND, 'Provider not found'));
                }
                $at_store = $provider_row['at_store'] ?? 0;
                $at_doorstep = $provider_row['at_doorstep'] ?? 0;
            }

            if ($this->request->getPost('partner_id') && !empty($this->request->getPost('partner_id'))) {
                $partner_details = fetch_details('partner_details', ['partner_id' => $this->request->getPost('partner_id')]);
                if (isset($partner_details[0]['at_store']) && $partner_details[0]['at_store'] == 1) {
                    $at_store = 1;
                }
                if (isset($partner_details[0]['at_doorstep']) && $partner_details[0]['at_doorstep'] == 1) {
                    $at_doorstep = 1;
                }
                $where['s.user_id'] = $this->request->getPost('partner_id');
            }
            if ($this->request->getPost('category_id') && !empty($this->request->getPost('category_id'))) {
                $where['s.category_id'] = $this->request->getPost('category_id');
            }
            if ($this->request->getPost('id') && !empty($this->request->getPost('id'))) {
                $where['s.id'] = $this->request->getPost('id');
            }

            if (isset($this->user_details['id']) && $this->user_details['id']) {
            }

            $data = $Service_model->list(true, $search, $limit, $offset, $sort, $order, $where, $additional_data, '', '', '', $at_store, $at_doorstep);

            if (isset($data['error'])) {
                return response_helper($data['message']);
            }
            if (!empty($data['data'])) {
                // Apply translations to services data
                $data['data'] = apply_translations_to_services_for_api($data['data']);

                // Ensure is_Available_at_location is always a string ("0" or "1")
                // This ensures consistency even if the model returns it as an integer
                foreach ($data['data'] as &$item) {
                    if (isset($item['is_Available_at_location'])) {
                        $item['is_Available_at_location'] = (string)$item['is_Available_at_location'];
                    } else {
                        // Set to "0" if not set (shouldn't happen, but safety check)
                        $item['is_Available_at_location'] = "0";
                    }
                }
                unset($item);

                return response_helper(labels(SERVICES_FETCHED_SUCCESSFULLY, 'services fetched successfully'), false, $data['data'], 200, ['total' => $data['total']]);
            } else {
                return response_helper(labels(SERVICES_NOT_FOUND, 'services not found'));
            }
        } catch (\Exception $th) {
            // throw $th;
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - get_services()');
            return $this->response->setJSON($response);
        }
    }
}
