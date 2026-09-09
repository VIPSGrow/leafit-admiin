<?php

namespace App\Controllers\Apis\Customer;

use App\Controllers\BaseController;
use App\Models\Service_ratings_model;
use App\Models\Partners_model;
use App\Models\Service_model;
use App\Libraries\JWT;

class RatingsApiController extends BaseController
{
    protected $request, $trans, $db, $data;
    protected JWT $JWT;
    protected $user_details = [];
    protected $excluded_routes =
        [
            "api/v1/index",
            "api/v1",
            "api/v1/get_ratings",
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

    public function get_ratings()
    {
        try {
            $validation = \Config\Services::validation();
            $validation->setRules(
                [
                    'partner_id' => 'permit_empty',
                ],
            );
            if (!$validation->withRequest($this->request)->run()) {
                $errors = $validation->getErrors();
                $firstError = reset($errors);
                $response = [
                    'error' => true,
                    'message' => $firstError,
                ];
                return $this->response->setJSON($response);
            }
            $limit = (isset($_POST['limit']) && !empty($_POST['limit'])) ? $_POST['limit'] : 10;
            $offset = (isset($_POST['offset']) && !empty($_POST['offset'])) ? $_POST['offset'] : 0;
            $sort = (isset($_POST['sort']) && !empty($_POST['sort'])) ? $_POST['sort'] : 'id';
            $order = (isset($_POST['order']) && !empty($_POST['order'])) ? $_POST['order'] : 'ASC';
            $search = (isset($_POST['search']) && !empty($_POST['search'])) ? $_POST['search'] : '';
            $partner_id = ($this->request->getPost('partner_id') != '') ? $this->request->getPost('partner_id') : '';
            $defaultSort = 'id';
            $defaultOrder = 'ASC';
            $validSortColumns = ['id', 'rating', 'created_at'];
            if (in_array($sort, $validSortColumns)) {
                $defaultSort = $sort;
            }
            $validOrders = ['ASC', 'DESC'];
            if (in_array($order, $validOrders)) {
                $defaultOrder = $order;
            }

            $service_slug = ($this->request->getPost('slug') != '') ? $this->request->getPost('slug') : '';
            $provider_slug = ($this->request->getPost('provider_slug') != '') ? $this->request->getPost('provider_slug') : '';
            $service_id = $this->request->getPost('service_id') ?: '';

            $partners_model = new Partners_model();
            $service_model = new Service_model();

            if (!empty($provider_slug)) {
                $provider_row = $partners_model->where('slug', $provider_slug)->first();
                if (empty($provider_row)) {
                    return response_helper(labels(PROVIDER_NOT_FOUND, 'Provider not found'), true);
                }
                $partner_id = $provider_row['partner_id'];

                if (!empty($service_slug)) {
                    $service_row = $service_model
                        ->where('slug', $service_slug)
                        ->where('user_id', $partner_id)
                        ->first();
                    if (empty($service_row)) {
                        return response_helper(labels(SERVICE_NOT_FOUND_FOR_THIS_PROVIDER, 'Service not found for this provider'), true);
                    }
                    $service_id = $service_row['id'];
                }
            } elseif (!empty($service_slug)) {
                $service_row = $service_model->where('slug', $service_slug)->first();
                if (empty($service_row)) {
                    return response_helper(labels(SERVICE_NOT_FOUND, 'Service not found'), true);
                }
                $service_id = $service_row['id'];
            }

            $additional_data = array_filter([
                'partner_id' => $partner_id !== '' ? $partner_id : null,
                'service_id' => $service_id !== '' ? $service_id : null,
            ]);

            $ratings = new Service_ratings_model();
            $data = $ratings->ratings_list(true, $search, $limit, $offset, $sort, $order, [], 'id', [], $additional_data);

            return response_helper(labels(DATA_RETRIEVED_SUCCESSFULLY, 'Data Retrieved successfully'), false, remove_null_values($data['data']), 200, ['total' => $data['total']]);
        } catch (\Exception $th) {
            // throw $th;
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - get_ratings()');
            return $this->response->setJSON($response);
        }
    }

    public function add_rating()
    {
        try {
            $validation = \Config\Services::validation();
            $ratings_model = new Service_ratings_model();
            $validation->setRules(
                [
                    'rating' => 'required|numeric|greater_than[0]|less_than_equal_to[5]',
                    'comment' => 'permit_empty',
                ],
            );
            if (!$validation->withRequest($this->request)->run()) {
                $errors = $validation->getErrors();
                $response = [
                    'error' => true,
                    'message' => $errors,
                    'data' => [],
                ];
                return $this->response->setJSON($response);
            }


            $user_id = $this->user_details['id'];
            $service_id = $this->request->getPost('service_id');
            $custom_job_request_id = $this->request->getPost('custom_job_request_id');
            if ($service_id) {
                $orders = has_ordered($user_id, $service_id);
                if ($orders['error'] == true) {
                    return response_helper($orders['message'], true, [], 200);
                }
            } else if ($custom_job_request_id) {
                $orders = has_ordered($user_id, $service_id, $custom_job_request_id);
                if ($orders['error'] == true) {
                    return response_helper($orders['message'], true, [], 200);
                }
            }
            if (isset($custom_job_request_id)) {
                $rd = fetch_details('services_ratings', ['user_id' => $user_id, 'custom_job_request_id' => $custom_job_request_id]);
            } else {
                $rd = fetch_details('services_ratings', ['user_id' => $user_id, 'service_id' => $service_id]);
            }
            if (empty($rd)) {
                $rating = $this->request->getPost('rating');
                $comment = (isset($_POST['comment']) && $_POST['comment'] != "") ? $this->request->getPost('comment') : "";
                $uploaded_images = $this->request->getFiles();
                $data = [];
                if (isset($custom_job_request_id)) {
                    $data['custom_job_request_id'] = $custom_job_request_id;
                } else {
                    $data['service_id'] = $service_id;
                }
                // Merge user_id, rating, and comment into the existing $data array
                // Timestamps (created_at and updated_at) are now handled automatically by Service_ratings_model
                // This ensures consistent timezone usage for both created_at and updated_at
                $data = array_merge($data, [
                    'user_id' => $user_id,
                    'rating' => $rating,
                    'comment' => $comment,
                ]);
                $names = "";
                $image_names['name'] = [];
                $data['images'] = [];
                $fileService = service('fileService');
                if (isset($uploaded_images['images'])) {
                    foreach ($uploaded_images['images'] as $images) {
                        $validate_image = valid_image($images);
                        if ($validate_image == true) {
                            return response_helper(labels(INVALID_IMAGE, "Invalid Image"), true, []);
                        }
                        $file = $images;
                        if ($file) {
                            $result = $fileService->upload($file, 'ratings');
                            if ($result['error'] === false) {
                                array_push($image_names['name'], $result['path']);
                            } else {
                                return ErrorResponse($result['message'] ?? labels(FAILED_TO_CREATE_RATINGS_FOLDERS, 'Failed to create ratings folders'), true, [], [], 200, csrf_token(), csrf_hash());
                            }
                        }
                    }
                    $names = json_encode($image_names['name']);
                }
                $data['images'] = $names;
                $saved_data = $ratings_model->save($data);
                if ($saved_data) {
                    update_ratings($service_id, $rating);
                    // Process images to match the ratings list API format (array instead of string)
                    // If images exist, decode and format them; otherwise set to empty array
                    if (!empty($data['images'])) {
                        $images_array = json_decode($data['images'], true);
                        foreach ($images_array as $key => $img) {
                            $images_array[$key] = $fileService->url($img, 'ratings');
                        }
                        $data['images'] = ($images_array);
                    } else {
                        // Set images to empty array to match ratings list API format
                        $data['images'] = [];
                    }
                    // Send notifications to provider when customer gives a rating
                    try {
                        $customer_details = fetch_details('users', ['id' => $user_id]);
                        $partner_id_result = fetch_details('services', ['id' => $service_id], ['user_id']);
                        $provider_id = !empty($partner_id_result) ? $partner_id_result[0]['user_id'] : null;

                        if ($provider_id) {
                            // Fetch service details for template variables
                            $service_details = fetch_details('services', ['id' => $service_id], ['title']);
                            $service_title = !empty($service_details) ? $service_details[0]['title'] : '';

                            // Prepare context data for notification templates
                            // This context will be used to populate template variables like [[user_name]], [[rating]], [[service_title]], etc.
                            $notificationContext = [
                                'provider_id' => $provider_id,
                                'user_id' => $user_id, // Customer who gave the rating
                                'service_id' => $service_id,
                                'service_title' => $service_title,
                                'rating' => $rating,
                                'user_name' => !empty($customer_details) ? ($customer_details[0]['username'] ?? '') : ''
                            ];

                            // Queue all notifications (FCM, Email, SMS) to provider
                            // NotificationService automatically handles:
                            // - Translation of templates based on user language
                            // - Variable replacement in templates
                            // - Notification settings checking for each channel
                            // - Fetching user email/phone/FCM tokens
                            // - Unsubscribe status checking for email
                            $language = get_current_language_from_request();
                            queue_notification_service(
                                eventType: 'new_rating_given_by_customer',
                                recipients: ['user_id' => $provider_id],
                                context: $notificationContext,
                                options: [
                                    'channels' => ['fcm', 'email', 'sms'], // All channels handled by NotificationService
                                    'language' => $language,
                                    'platforms' => ['android', 'ios', 'provider_panel'] // Provider platforms for FCM
                                ]
                            );

                            // log_message('info', '[NEW_RATING_GIVEN_BY_CUSTOMER] Notification result: ' . json_encode($result));
                        }
                    } catch (\Throwable $notificationError) {
                        // Log error but don't fail the rating save
                        log_message('error', '[NEW_RATING_GIVEN_BY_CUSTOMER] Notification error trace: ' . $notificationError->getTraceAsString());
                    }

                    $rating_data = fetch_details('services_ratings', ['id' => $ratings_model->insertID]);

                    // Format dates to match the ratings list API response format
                    // Convert created_at to rated_on and updated_at to rate_updated_on
                    // Use the same date format as in Service_ratings_model: 'd-m-Y H:i'
                    $data['rated_on'] = date('d-m-Y H:i', strtotime($rating_data[0]['created_at']));
                    $data['rate_updated_on'] = date('d-m-Y H:i', strtotime($rating_data[0]['updated_at']));
                    // Remove the original timestamp fields from response
                    unset($data['created_at']);
                    unset($data['updated_at']);
                    return response_helper(labels(RATING_SAVED, "Rating Saved"), false, remove_null_values($data), 200);
                } else {
                    return response_helper(labels(COULD_NOT_SAVE_RATINGS, "Could not save ratings"), true, [], 200);
                }
            } else {

                // NEW: Process rating images to delete for "rating images" only (ratings folder)
                $imagesToDelete = $this->getImagesToDeleteFromRequest($this->request, 'images_to_delete');

                $fileService = service('fileService');

                // Get current images from database
                $currentImages = json_decode($rd[0]['images'], true) ?? [];

                // Delete specified rating images before processing uploads
                if (!empty($imagesToDelete)) {
                    $deletionResults = $this->processRatingImageDeletion($imagesToDelete);

                    // Log deletion results
                    foreach ($deletionResults as $result) {
                        if ($result['result']['error']) {
                            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $result['url'] . " - " . $result['result']['message'], date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - update_rating()');
                        } else {
                            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $result['url'], date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - update_rating()');
                        }
                    }

                    // Update current images list by removing deleted images from database
                    foreach ($imagesToDelete as $imageToDelete) {
                        // Extract the filename from the URL to match against database entries
                        $parsedInfo = $this->parseRatingImageUrl($imageToDelete);
                        if ($parsedInfo['filename']) {
                            // Remove from current images array
                            $currentImages = array_filter($currentImages, function ($img) use ($parsedInfo) {
                                return !str_contains($img, $parsedInfo['filename']);
                            });
                        }
                    }
                    // Re-index array to avoid gaps
                    $currentImages = array_values($currentImages);
                }
                $rating_id = $rd[0]['id'];
                $rating = (isset($_POST['rating'])) ? $this->request->getPost('rating') : "";
                $comment = (isset($_POST['comment'])) ? $this->request->getPost('comment') : "";
                // When updating an existing rating, updated_at is automatically set by Service_ratings_model
                // This ensures consistent timezone usage matching created_at
                $data = [
                    'rating' => ($rating != "") ? $rating : $rd[0]['rating'],
                    'comment' => ($comment != "") ? $comment : $rd[0]['comment'],
                ];

                // Start with the current images (after deletions)
                $finalImages = $currentImages;
                $uploaded_images = $this->request->getFiles();
                $path = "public/uploads/ratings/";

                if (isset($uploaded_images['images'])) {
                    // Process new image uploads
                    foreach ($uploaded_images['images'] as $images) {
                        $validate_image = valid_image($images);
                        if ($validate_image == true) {
                            return response_helper(labels(INVALID_IMAGE, "Invalid Image"), true, []);
                        }
                        $file = $images;
                        if ($file) {
                            $result = $fileService->upload($file, 'ratings');
                            if ($result['error'] === false) {
                                $finalImages[] = $result['path'];
                            } else {
                                return ErrorResponse($result['message'] ?? labels(FAILED_TO_CREATE_RATINGS_FOLDERS, 'Failed to create ratings folders'), true, [], [], 200, csrf_token(), csrf_hash());
                            }
                        }
                    }
                }

                // Set the final images array (existing images after deletions + new uploads)
                $data['images'] = json_encode($finalImages);
                $updated_data = $ratings_model->update($rating_id, $data);
                if ($updated_data) {
                    update_ratings($service_id, $rating);
                    // Process images to match the ratings list API format (array instead of string)
                    // If images exist, decode and format them; otherwise set to empty array
                    if (!empty($data['images'])) {
                        $images_array = json_decode($data['images'], true);
                        if (!empty($images_array)) {
                            foreach ($images_array as $key => $img) {
                                $images_array[$key] = $fileService->url($img, 'ratings');
                            }
                            $data['images'] = ($images_array);
                        } else {
                            // Set images to empty array if decoded result is empty
                            $data['images'] = [];
                        }
                    } else {
                        // Set images to empty array to match ratings list API format
                        $data['images'] = [];
                    }
                    // Send notifications to provider when customer updates a rating
                    try {
                        $customer_details = fetch_details('users', ['id' => $user_id]);
                        $partner_id_result = fetch_details('services', ['id' => $service_id], ['user_id']);
                        $provider_id = !empty($partner_id_result) ? $partner_id_result[0]['user_id'] : null;

                        if ($provider_id) {
                            // Fetch service details for template variables
                            $service_details = fetch_details('services', ['id' => $service_id], ['title']);
                            $service_title = !empty($service_details) ? $service_details[0]['title'] : '';

                            // Prepare context data for notification templates
                            // This context will be used to populate template variables like [[user_name]], [[rating]], [[service_title]], etc.
                            $notificationContext = [
                                'provider_id' => $provider_id,
                                'user_id' => $user_id, // Customer who gave the rating
                                'service_id' => $service_id,
                                'service_title' => $service_title,
                                'rating' => $rating,
                                'user_name' => !empty($customer_details) ? ($customer_details[0]['username'] ?? '') : ''
                            ];

                            // Queue all notifications (FCM, Email, SMS) to provider
                            // NotificationService automatically handles:
                            // - Translation of templates based on user language
                            // - Variable replacement in templates
                            // - Notification settings checking for each channel
                            // - Fetching user email/phone/FCM tokens
                            // - Unsubscribe status checking for email
                            $language = get_current_language_from_request();
                            queue_notification_service(
                                eventType: 'new_rating_given_by_customer',
                                recipients: ['user_id' => $provider_id],
                                context: $notificationContext,
                                options: [
                                    'channels' => ['fcm', 'email', 'sms'], // All channels handled by NotificationService
                                    'language' => $language,
                                    'platforms' => ['android', 'ios', 'provider_panel'] // Provider platforms for FCM
                                ]
                            );

                            // log_message('info', '[NEW_RATING_GIVEN_BY_CUSTOMER] Notification result (update): ' . json_encode($result));
                        }
                    } catch (\Throwable $notificationError) {
                        // Log error but don't fail the rating update
                        log_message('error', '[NEW_RATING_GIVEN_BY_CUSTOMER] Notification error trace (update): ' . $notificationError->getTraceAsString());
                    }
                    // Add service_id and user_id to response to match the add_rating API response format
                    // These fields are needed for consistency with the create rating response
                    $data['service_id'] = $rd[0]['service_id'] ?? $service_id;
                    $data['user_id'] = $user_id;
                    // Format dates to match the ratings list API response format
                    // Convert created_at to rated_on and updated_at to rate_updated_on
                    // Use the same date format as in Service_ratings_model: 'd-m-Y H:i'
                    // Get the original created_at from the database record for rated_on
                    $data['rated_on'] = $rd[0]['created_at'];
                    $data['rate_updated_on'] = $rd[0]['updated_at'];
                    // Remove the original timestamp fields from response
                    unset($data['updated_at']);
                    return response_helper(labels(RATING_UPDATED_SUCCESSFULLY, "Rating Updated Successfully"), false, remove_null_values($data), 200);
                } else {
                    return response_helper(labels(RATING_COULD_NOT_BE_UPDATED, "Rating couldn't be Updated"), true, [], 200);
                }
            }
        } catch (\Exception $th) {
            // throw $th;
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - add_rating()');
            return $this->response->setJSON($response);
        }
    }

    public function update_rating()
    {
        try {
            $validation = \Config\Services::validation();
            $ratings_model = new Service_ratings_model();
            $validation->setRules(
                [
                    'rating_id' => 'required',
                    'rating' => 'permit_empty',
                    'comment' => 'permit_empty',
                    'image' => 'permit_empty',
                ],
            );
            if (!$validation->withRequest($this->request)->run()) {
                $errors = $validation->getErrors();
                $response = [
                    'error' => true,
                    'message' => $errors,
                    'data' => [],
                ];
                return $this->response->setJSON($response);
            }
            $user_id = $this->user_details['id'];
            $rating_id = $this->request->getPost('rating_id');
            $ratings = has_rated($user_id, $rating_id);
            if ($ratings['error']) {
                return response_helper($ratings['message'], true, [], 200);
            }
            $rating = (isset($_POST['rating'])) ? $this->request->getPost('rating') : "";
            $comment = (isset($_POST['comment'])) ? $this->request->getPost('comment') : "";
            if ($rating > 5) {
                return response_helper(labels(CAN_NOT_RATE_MORE_THAN_5, "Can not rate More than 5"), true, [], 200);
            }
            // When updating an existing rating, updated_at is automatically set by Service_ratings_model
            // This ensures consistent timezone usage matching created_at
            $data = [
                'rating' => ($rating != "") ? $rating : $ratings['data'][0]['rating'],
                'comment' => ($comment != "") ? $comment : $ratings['data'][0]['comment'],
            ];
            $data['images'] = [];
            $uploaded_images = $this->request->getFiles();
            if (isset($uploaded_images['images'])) {
                $fileService = service('fileService');
                foreach ($uploaded_images['images'] as $images) {
                    $validate_image = valid_image($images);
                    if ($validate_image == true) {
                        return response_helper(labels(INVALID_IMAGE, "Invalid Image"), true, []);
                    }
                    $file = $images;
                    if ($file) {
                        $result = $fileService->upload($file, 'ratings');
                        if ($result['error'] === false) {
                            array_push($data['images'], $result['path']);
                        } else {
                            return ErrorResponse($result['message'] ?? labels(FAILED_TO_CREATE_RATINGS_FOLDERS, 'Failed to create ratings folders'), true, [], [], 200, csrf_token(), csrf_hash());
                        }
                    }
                }
                $data['images'] = json_encode($data['images']);
            } else {
                $data['images'] = $ratings['data'][0]['images'];
            }
            $updated_data = $ratings_model->update($rating_id, $data);
            if ($updated_data) {
                return response_helper(labels(RANKING_UPDATED_SUCCESSFULLY, "Ranking Updated Successfully"), false, [], 200);
            } else {
                return response_helper(labels(RANKING_UPDATED_UNSUCCESSFUL, "Ranking Updated UnSuccessful"), true, [], 200);
            }
        } catch (\Exception $th) {
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - update_rating()');
            return $this->response->setJSON($response);
        }
    }

    // Private Helper Functions
    private function getImagesToDeleteFromRequest($request, $paramName = 'images_to_delete')
    {
        $imagesToDelete = [];

        if ($request->getPost($paramName)) {
            $imagesToDeleteData = $request->getPost($paramName);

            if (is_string($imagesToDeleteData)) {
                $decoded = json_decode($imagesToDeleteData, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $imagesToDelete = $decoded;
                }
            } elseif (is_array($imagesToDeleteData)) {
                $imagesToDelete = $imagesToDeleteData;
            }
        }

        return $imagesToDelete;
    }

    private function processRatingImageDeletion($imageUrls)
    {
        $deletionResults = [];

        if (empty($imageUrls) || !is_array($imageUrls)) {
            return $deletionResults;
        }

        $fileService = service('fileService');
        foreach ($imageUrls as $imageUrl) {
            if (empty($imageUrl)) {
                continue;
            }

            // Extract folder and filename from URL for ratings
            $parsedInfo = $this->parseRatingImageUrl($imageUrl);

            if ($parsedInfo['folder'] && $parsedInfo['filename']) {
                $result = $fileService->delete($parsedInfo['folder'], $parsedInfo['filename']);

                $deletionResults[] = [
                    'url' => $imageUrl,
                    'folder' => $parsedInfo['folder'],
                    'filename' => $parsedInfo['filename'],
                    'result' => $result
                ];
            }
        }

        return $deletionResults;
    }

    private function parseRatingImageUrl($imageUrl)
    {
        $folder = '';
        $filename = '';

        // ONLY allow deletion of rating images (ratings folder)
        if (strpos($imageUrl, 'public/uploads/ratings/') !== false) {
            $folder = 'ratings';
            $filename = basename($imageUrl);
        } else {
            // Try to extract from URL pattern for ratings folder only
            $urlParts = parse_url($imageUrl);
            if (isset($urlParts['path'])) {
                $pathParts = explode('/', trim($urlParts['path'], '/'));
                $filename = end($pathParts);

                // Only allow ratings folder
                if (in_array('ratings', $pathParts)) {
                    $folder = 'ratings';
                }
            }
        }
        return [
            'folder' => $folder,
            'filename' => $filename
        ];
    }
}
