<?php

namespace App\Controllers\Apis;

use App\Controllers\BaseController;
use Config\Services;

class PlacesApiController extends BaseController
{

    protected $excluded_routes = [
        "api/v1/get_places_for_app",
        "api/v1/get_place_details_for_app",
        "api/v1/get_places_for_web",
        "api/v1/get_place_details_for_web",
        "partner/api/v1/get_places_for_app",
        "partner/api/v1/get_place_details_for_app",
    ];

    public function __construct()
    {
        helper("function");
        helper('ResponceServices');
    }


    /**
     * Unified autocomplete endpoint for both app and web.
     *
     * This method:
     * - Accepts "input" as a GET parameter.
     * - Applies the same length and basic validation currently used.
     * - Uses the configured map provider (Google Places, or Nominatim when OpenStreetMap is selected).
     * - Returns a Google-Places-shaped envelope regardless of provider, so mobile/web clients
     *   never need to know which provider is active.
     *
     * It effectively replaces:
     * - Customer V1::get_places_for_app()
     * - Customer V1::get_places_for_web()
     * - Provider V1::get_places_for_app()
     */
    public function get_places()
    {
        try {
            // Use a consistent validation flow similar to existing methods
            $validation = Services::validation();
            $validation->setRules([
                'input' => 'required',
            ]);

            if (!$validation->withRequest($this->request)->run()) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => $validation->getErrors(),
                    'data'    => [],
                ]);
            }

            // Use trim and length checks as in the customer app version
            $rawInput = trim((string) $this->request->getGet('input'));

            if ($rawInput === '' || mb_strlen($rawInput) > 150) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels('invalid_input_provided', 'Invalid input provided'),
                ]);
            }

            if (get_map_provider() === 'openstreetmap') {
                $results = $this->nominatimRequest('search', [
                    'q'              => $rawInput,
                    'format'         => 'json',
                    'addressdetails' => 1,
                    'limit'          => 10,
                ]);

                return $this->response->setJSON([
                    'error' => false,
                    'data'  => [
                        'predictions' => $this->normalizeNominatimPredictions($results ?? []),
                    ],
                ]);
            }

            // Fetch API key using the same settings structure
            $key = get_settings('api_key_settings', true);

            // Use Places API key if available
            if (isset($key['google_places_api']) && !empty($key['google_places_api'])) {
                $google_api_key = $key['google_places_api'];
            } else {
                // Reuse the more generic, reusable label where possible
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels(PLACES_API_KEY_NOT_SET, 'Places API key is not set'),
                ]);
            }

            $baseUrl = "https://maps.googleapis.com/maps/api/place/autocomplete/json";
            $query = http_build_query([
                'key'   => $google_api_key,
                'input' => $rawInput,
            ]);
            $url = $baseUrl . "?" . $query;

            // Secure cURL request to Google Places Autocomplete API
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            $response = curl_exec($ch);
            unset($ch);

            return $this->response->setJSON([
                'error' => false,
                'data'  => json_decode($response, true) ?? [],
            ]);
        } catch (\Throwable $th) {
            // Keep logging consistent with existing pattern
            log_message('error', date("Y-m-d H:i:s") . '--> app/Controllers/Apis/PlacesApiController.php - get_places()'
                . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());

            return $this->response->setJSON([
                'error'   => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }

    /**
     * Unified place details endpoint for both app and web.
     *
     * This method supports:
     * - Details by "placeid" (used by app endpoints).
     * - Details by "place_id" OR by "latitude" + "longitude" (used by web endpoint).
     *
     * Behaviour:
     * - If "placeid" or "place_id" is provided, it will be used as the primary identifier.
     * - If no place id is provided, but "latitude" and "longitude" are provided,
     *   it behaves like the existing web endpoint that accepts coordinates.
     * - Uses the configured map provider (Google Geocoding/Places, or Nominatim when
     *   OpenStreetMap is selected). Response envelope always matches the Google shape.
     *
     * This mirrors:
     * - Customer V1::get_place_details_for_app()
     * - Customer V1::get_place_details_for_web()
     * - Provider V1::get_place_details_for_app()
     */
    public function get_place_details()
    {
        try {
            $rawLatitude   = trim((string) $this->request->getGet('latitude'));
            $rawLongitude  = trim((string) $this->request->getGet('longitude'));
            $rawPlaceId    = trim((string) $this->request->getGet('placeid'));
            $rawPlaceIdWeb = trim((string) $this->request->getGet('place_id'));

            // Prefer explicit placeid / place_id when present
            $effectivePlaceId = $rawPlaceId !== '' ? $rawPlaceId : $rawPlaceIdWeb;

            $isOpenStreetMap = get_map_provider() === 'openstreetmap';

            // If place id is present, resolve it directly (by placeid / place_id)
            if ($effectivePlaceId !== '') {
                if (mb_strlen($effectivePlaceId) > 150) {
                    return $this->response->setJSON([
                        'error'   => true,
                        'message' => labels('invalid_input_provided', 'Invalid input provided'),
                    ]);
                }

                if ($isOpenStreetMap) {
                    $osmId = $this->parseOsmPlaceId($effectivePlaceId);
                    if ($osmId === null) {
                        return $this->response->setJSON([
                            'error'   => true,
                            'message' => labels('invalid_input_provided', 'Invalid input provided'),
                        ]);
                    }

                    $results = $this->nominatimRequest('lookup', [
                        'osm_ids'        => $osmId,
                        'format'         => 'json',
                        'addressdetails' => 1,
                    ]);

                    if (empty($results[0])) {
                        return $this->response->setJSON([
                            'error'   => true,
                            'message' => labels(DATA_NOT_FOUND, 'Data not found'),
                        ]);
                    }

                    return $this->response->setJSON([
                        'error' => false,
                        'data'  => ['result' => $this->normalizeNominatimResult($results[0])],
                    ]);
                }

                $key = get_settings('api_key_settings', true);

                if (isset($key['google_places_api']) && !empty($key['google_places_api'])) {
                    $google_api_key = $key['google_places_api'];
                } else {
                    return $this->response->setJSON([
                        'error'   => true,
                        'message' => labels(PLACES_API_KEY_NOT_SET, 'Places API key is not set'),
                    ]);
                }

                $baseUrl = "https://maps.googleapis.com/maps/api/place/details/json";
                $query = http_build_query([
                    'key'     => $google_api_key,
                    'placeid' => $effectivePlaceId,
                ]);
                $url = $baseUrl . "?" . $query;

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

                $response = curl_exec($ch);
                unset($ch);

                return $this->response->setJSON([
                    'error' => false,
                    'data'  => json_decode($response, true) ?? [],
                ]);
            }

            // If no place id, fall back to latitude/longitude (reverse geocoding) behaviour
            if ($rawLatitude !== '' && !preg_match('/^-?\d{1,3}(\.\d+)?$/', $rawLatitude)) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels('please_enter_valid_latitude', 'Please enter valid latitude'),
                ]);
            }

            if ($rawLongitude !== '' && !preg_match('/^-?\d{1,3}(\.\d+)?$/', $rawLongitude)) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels('please_enter_valid_longitude', 'Please enter valid longitude'),
                ]);
            }

            if ($rawPlaceIdWeb !== '' && mb_strlen($rawPlaceIdWeb) > 200) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels('invalid_input_provided', 'Invalid input provided'),
                ]);
            }

            if ($rawPlaceIdWeb === '' && ($rawLatitude === '' || $rawLongitude === '')) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels('invalid_input_provided', 'Invalid input provided'),
                ]);
            }

            if ($isOpenStreetMap) {
                if ($rawLatitude !== '' && $rawLongitude !== '') {
                    $result = $this->nominatimRequest('reverse', [
                        'lat'            => $rawLatitude,
                        'lon'            => $rawLongitude,
                        'format'         => 'json',
                        'addressdetails' => 1,
                    ]);

                    if (empty($result) || isset($result['error'])) {
                        return $this->response->setJSON([
                            'error'   => true,
                            'message' => labels(DATA_NOT_FOUND, 'Data not found'),
                        ]);
                    }

                    return $this->response->setJSON([
                        'error' => false,
                        'data'  => ['result' => $this->normalizeNominatimResult($result)],
                    ]);
                }

                // place_id (web) fallback via OSM lookup
                $osmId = $this->parseOsmPlaceId($rawPlaceIdWeb);
                if ($osmId === null) {
                    return $this->response->setJSON([
                        'error'   => true,
                        'message' => labels('invalid_input_provided', 'Invalid input provided'),
                    ]);
                }

                $results = $this->nominatimRequest('lookup', [
                    'osm_ids' => $osmId,
                    'format'  => 'json',
                ]);

                if (empty($results[0])) {
                    return $this->response->setJSON([
                        'error'   => true,
                        'message' => labels(DATA_NOT_FOUND, 'Data not found'),
                    ]);
                }

                return $this->response->setJSON([
                    'error' => false,
                    'data'  => ['result' => $this->normalizeNominatimResult($results[0])],
                ]);
            }

            $key = get_settings('api_key_settings', true);

            if (empty($key['google_places_api'])) {
                return $this->response->setJSON([
                    'error'   => true,
                    'message' => labels(PLACES_API_KEY_NOT_SET, 'Places API key is not set'),
                ]);
            }

            $google_api_key = $key['google_places_api'];

            // Use the same Geocoding endpoint and parameters as the original web implementation
            $baseUrl = "https://maps.googleapis.com/maps/api/geocode/json";

            $queryParams = [
                'key' => $google_api_key,
            ];

            if ($rawLatitude !== '' && $rawLongitude !== '') {
                $queryParams['latlng'] = "{$rawLatitude},{$rawLongitude}";
            }

            if ($rawPlaceIdWeb !== '') {
                $queryParams['place_id'] = $rawPlaceIdWeb;
            }

            $finalUrl = $baseUrl . '?' . http_build_query($queryParams);

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $finalUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_TIMEOUT        => 15,
            ]);

            $response = curl_exec($ch);
            unset($ch);

            return $this->response->setJSON([
                'error' => false,
                'data'  => json_decode($response, true) ?? [],
            ]);
        } catch (\Throwable $th) {
            log_message('error', date("Y-m-d H:i:s") . '--> app/Controllers/Apis/PlacesApiController.php - get_place_details()'
                . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());

            return $this->response->setJSON([
                'error'   => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }

    /**
     * Call the Nominatim (OpenStreetMap) API. Free/no-key, but usage policy requires a
     * descriptive User-Agent header and reasonable rate limiting (~1 req/sec).
     *
     * @param string $endpoint 'search' | 'reverse' | 'lookup'
     * @param array  $params
     * @return array|null decoded JSON (array for search/lookup, assoc array for reverse), null on failure
     */
    private function nominatimRequest(string $endpoint, array $params): ?array
    {
        $url = "https://nominatim.openstreetmap.org/{$endpoint}?" . http_build_query($params);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => ['User-Agent: eDemand-App/1.0 (map-provider-integration)'],
        ]);
        $response = curl_exec($ch);
        unset($ch);

        $decoded = json_decode((string) $response, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Parse our synthetic "osm:<N|W|R>:<id>" place_id back into Nominatim's osm_ids format (e.g. "N12345").
     */
    private function parseOsmPlaceId(string $placeId): ?string
    {
        if (!preg_match('/^osm:([NWR]):(\d+)$/', $placeId, $m)) {
            return null;
        }
        return $m[1] . $m[2];
    }

    /**
     * Normalize a list of Nominatim search results into Google Places Autocomplete "predictions" shape.
     */
    private function normalizeNominatimPredictions(array $results): array
    {
        $predictions = [];
        foreach ($results as $r) {
            if (empty($r['osm_type']) || !isset($r['osm_id'])) {
                continue;
            }
            $osmTypeLetter = strtoupper(substr((string) $r['osm_type'], 0, 1));
            $description   = $r['display_name'] ?? '';
            $parts         = explode(',', $description, 2);

            $predictions[] = [
                'place_id'              => 'osm:' . $osmTypeLetter . ':' . $r['osm_id'],
                'description'           => $description,
                'structured_formatting' => [
                    'main_text'      => trim($parts[0] ?? $description),
                    'secondary_text' => trim($parts[1] ?? ''),
                ],
                'geometry' => [
                    'location' => [
                        'lat' => isset($r['lat']) ? (float) $r['lat'] : null,
                        'lng' => isset($r['lon']) ? (float) $r['lon'] : null,
                    ],
                ],
            ];
        }
        return $predictions;
    }

    /**
     * Normalize a single Nominatim result (search/lookup/reverse item) into Google Place
     * Details / Geocoding "result" shape.
     */
    private function normalizeNominatimResult(array $r): array
    {
        $description = $r['display_name'] ?? '';
        $parts       = explode(',', $description, 2);
        $address     = $r['address'] ?? [];
        $city        = $address['city'] ?? $address['town'] ?? $address['village']
            ?? $address['municipality'] ?? $address['county'] ?? '';

        return [
            'name'              => trim($parts[0] ?? $description),
            'formatted_address' => $description,
            'city'              => $city,
            'geometry'          => [
                'location' => [
                    'lat' => isset($r['lat']) ? (float) $r['lat'] : null,
                    'lng' => isset($r['lon']) ? (float) $r['lon'] : null,
                ],
            ],
        ];
    }
}
