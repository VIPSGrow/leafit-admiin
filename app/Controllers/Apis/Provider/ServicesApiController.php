<?php
namespace App\Controllers\Apis\Provider;

use App\Controllers\BaseController;
use App\Models\Seo_model;
use App\Models\Service_model;
use App\Services\utility\SlugService;

class ServicesApiController extends BaseController
{
    protected $request, $db, $data, $validation, $slugService, $seoModel;
    protected $user_details = [];
   
    public function __construct()
    {
        helper('api');
        helper("function");
        helper('ResponceServices');
        $this->request = \Config\Services::request();
        $this->db = \Config\Database::connect();
        $this->slugService = new SlugService();
        $this->seoModel = new Seo_model();

        $token = verify_app_request();
        if (!$token['error'] && isset($token['data']) && !empty($token['data'])) {
            $this->user_details = $token['data'];
        } else {
            header('Content-Type: application/json');
            http_response_code($token['status']);
            print_r(json_encode([
                'error' => true,
                'message' => $token['message'],
                'status' => 401,
            ]));
            die();
        }
    }

    public function get_services()
    {
        try {
            $seoModel = new Seo_model();
            $seoModel->setTableContext('services');
            $Service_model = new Service_model();
            $limit = $this->request->getPost('limit') ?: 10;
            $offset = $this->request->getPost('offset') ?: 0;
            $sort = $this->request->getPost('sort') ?: 'id';
            $order = $this->request->getPost('order') ?: 'DESC';
            $search = $this->request->getPost('search') ?: '';
            $category_ids = $this->request->getPost('category_ids');
            $min_budget = $this->request->getPost('min_budget');
            $max_budget = $this->request->getPost('max_budget');
            $rating = $this->request->getPost('rating');
            $where_in = [];
            $additional_data = [];

            if (isset($category_ids) && !empty($category_ids)) {
                $where_in = explode(",", $category_ids);
            }

            if ($sort == 'price') {
                $sort = 'discounted_price'; // Sort by discounted price first
            }

            $settings = get_settings('general_settings', true);
            if (($this->request->getPost('latitude') && !empty($this->request->getPost('latitude')) && ($this->request->getPost('longitude') && !empty($this->request->getPost('longitude'))))) {
                $additional_data = [
                    'latitude' => $this->request->getPost('latitude'),
                    'longitude' => $this->request->getPost('longitude'),
                    'city_id' => $this->user_details['city_id'],
                    'max_serviceable_distance' => $settings['max_serviceable_distance'],
                ];
            }
            $where = 's.user_id = ' . $this->user_details['id'] . ' ';

            // If service_id is provided, filter by that specific service
            $service_id = $this->request->getPost('service_id');
            if (isset($service_id) && !empty($service_id)) {
                $where .= ' AND s.id = ' . (int)$service_id . ' ';
            }

            if (isset($min_budget) && !empty($min_budget) && isset($max_budget) && !empty($max_budget)) {
                if (isset($where)) {
                    $where .= '  AND (`s`.`price` BETWEEN "' . $min_budget . '" AND "' . $max_budget . '" OR `s`.`discounted_price` BETWEEN "' . $min_budget . '" AND "' . $max_budget . '")';
                } else {
                    $where = ' AND (`s`.`price` BETWEEN "' . $min_budget . '" AND "' . $max_budget . '" OR `s`.`discounted_price` BETWEEN "' . $min_budget . '" AND "' . $max_budget . '")';
                }
            } elseif (isset($min_budget) && !empty($min_budget)) {
                if (isset($where)) {
                    $where .= ' AND (`s`.`price` >= "' . $min_budget . '" OR `s`.`discounted_price` >= "' . $min_budget . '")';
                } else {
                    $where = '  AND (`s`.`price` >= "' . $min_budget . '" OR `s`.`discounted_price` >= "' . $min_budget . '")';
                }
            } elseif (isset($max_budget) && !empty($max_budget)) {
                if (isset($where)) {
                    $where .= ' AND (`s`.`price` <= "' . $max_budget . '" OR `s`.`discounted_price` <= "' . $max_budget . '")';
                } else {
                    $where = ' AND (`s`.`price` <= "' . $max_budget . '" OR `s`.`discounted_price` <= "' . $max_budget . '")';
                }
            }
            $at_store = 0;
            $at_doorstep = 0;
            $partner_details = fetch_details('partner_details', ['partner_id' =>  $this->user_details['id']]);
            if (isset($partner_details[0]['at_store']) && $partner_details[0]['at_store'] == 1) {
                $at_store = 1;
            }
            if (isset($partner_details[0]['at_doorstep']) && $partner_details[0]['at_doorstep'] == 1) {
                $at_doorstep = 1;
            }
            $data = $Service_model->list(true, $search, $limit, $offset, $sort, $order, $where, $additional_data, 'category_id', $where_in, $this->user_details['id'], '', '');

            $fileService = service('fileService'); // For image URL formatting

            foreach ($data['data'] as $key => $value) {
                $averageRating = $value['average_rating'];
                $shouldUnset = false;
                if (isset($rating) && !empty($rating)) {


                    if ($rating == 1) {
                        if (!($averageRating >= 1 && $averageRating < 2)) {
                            $shouldUnset = true;
                        }
                    } elseif ($rating == 2) {
                        if (!($averageRating >= 2 && $averageRating < 3)) {
                            $shouldUnset = true;
                        }
                    } elseif ($rating == 3) {
                        if (!($averageRating >= 3 && $averageRating < 4)) {
                            $shouldUnset = true;
                        }
                    } elseif ($rating == 4) {
                        if (!($averageRating >= 4 && $averageRating < 5)) {
                            $shouldUnset = true;
                        }
                    } elseif ($rating == 5) {
                        if ($averageRating != 5) {
                            $shouldUnset = true;
                        }
                    }
                }
                if ($shouldUnset) {
                    unset($data['data'][$key]);
                    continue;
                }

                // Fix image_of_the_service if it's empty but image exists in database
                if ((!isset($value['image_of_the_service']) || empty($value['image_of_the_service'])) &&
                    isset($value['image']) && !empty($value['image'])
                ) {

                    $data['data'][$key]['image_of_the_service'] = $fileService->url($value['image'], 'services');
                }

                $seo_settings = $seoModel->getSeoSettingsByReferenceId($value['id'], 'meta');
                $formatted_seo_settings = [];
                if (!empty($seo_settings)) {
                    $formatted_seo_settings['seo_title'] = $seo_settings['title'];
                    $formatted_seo_settings['seo_description'] = $seo_settings['description'];
                    $formatted_seo_settings['seo_keywords'] = $seo_settings['keywords'];
                    $formatted_seo_settings['seo_og_image'] = $seo_settings['image']; // Already formatted with proper URL
                    $formatted_seo_settings['seo_schema_markup'] = $seo_settings['schema_markup'];
                }

                // Get service details for translation fallback
                $serviceFallbackData = [
                    'title' => $value['title'] ?? '',
                    'description' => $value['description'] ?? '',
                    'long_description' => $value['long_description'] ?? '',
                    'tags' => $value['tags'] ?? '',
                    'faqs' => $value['faqs'] ?? ''
                ];

                // Get translated data for this service based on Content-Language header
                $translatedServiceData = $this->getServiceTranslatedFields($value['id'], $serviceFallbackData);

                // Merge all data: original service data + SEO settings + translated data
                $data['data'][$key] = array_merge($data['data'][$key], $formatted_seo_settings, $translatedServiceData);

                // Augment response with multilingual SEO in translated_fields (same as manage_service create/update)
                try {
                    $requestedLang = function_exists('get_current_language_from_request') ? (get_current_language_from_request() ?: (function_exists('get_default_language') ? get_default_language() : 'en')) : (function_exists('get_default_language') ? get_default_language() : 'en');
                    $effectiveDefaultLang = function_exists('get_default_language') ? get_default_language() : $requestedLang;

                    // Fetch SEO translations for this service
                    $seoTransModel = model('TranslatedServiceSeoSettings_model');
                    $seoTranslations = $seoTransModel->getAllTranslationsForService($value['id']);

                    // Build per-language maps from translations
                    $tfSeoTitle = [];
                    $tfSeoDesc = [];
                    $tfSeoKeywords = [];
                    $tfSeoSchema = [];
                    foreach ($seoTranslations as $trow) {
                        $langCode = $trow['language_code'] ?? '';
                        if ($langCode === '') {
                            continue;
                        }
                        if (isset($trow['seo_title']) && $trow['seo_title'] !== '') {
                            $tfSeoTitle[$langCode] = $trow['seo_title'];
                        }
                        if (isset($trow['seo_description']) && $trow['seo_description'] !== '') {
                            $tfSeoDesc[$langCode] = $trow['seo_description'];
                        }
                        if (isset($trow['seo_keywords']) && $trow['seo_keywords'] !== '') {
                            $tfSeoKeywords[$langCode] = $trow['seo_keywords'];
                        }
                        if (isset($trow['seo_schema_markup']) && $trow['seo_schema_markup'] !== '') {
                            $tfSeoSchema[$langCode] = $trow['seo_schema_markup'];
                        }
                    }

                    // Per-language fallback for translated_fields using service translated title/description
                    $serviceTitles = $translatedServiceData['translated_fields']['title'] ?? [];
                    $serviceDescs  = $translatedServiceData['translated_fields']['description'] ?? [];
                    $allLangs = array_unique(array_merge(array_keys($serviceTitles), array_keys($serviceDescs), array_keys($tfSeoTitle), array_keys($tfSeoDesc)));
                    foreach ($allLangs as $lcode) {
                        if (!isset($tfSeoTitle[$lcode]) && isset($serviceTitles[$lcode])) {
                            $tfSeoTitle[$lcode] = $serviceTitles[$lcode];
                        }
                        if (!isset($tfSeoDesc[$lcode]) && isset($serviceDescs[$lcode])) {
                            $tfSeoDesc[$lcode] = $serviceDescs[$lcode];
                        }
                    }

                    // Attach to translated_fields (preserve existing content)
                    $data['data'][$key]['translated_fields']['seo_title'] = ($data['data'][$key]['translated_fields']['seo_title'] ?? []) + $tfSeoTitle;
                    $data['data'][$key]['translated_fields']['seo_description'] = ($data['data'][$key]['translated_fields']['seo_description'] ?? []) + $tfSeoDesc;
                    $data['data'][$key]['translated_fields']['seo_keywords'] = ($data['data'][$key]['translated_fields']['seo_keywords'] ?? []) + $tfSeoKeywords;
                    $data['data'][$key]['translated_fields']['seo_schema_markup'] = ($data['data'][$key]['translated_fields']['seo_schema_markup'] ?? []) + $tfSeoSchema;
                } catch (\Throwable $e) {
                    // Do not fail the listing if SEO translation aggregation has issues
                    log_message('error', 'Failed to assemble multilingual SEO for service ' . $value['id'] . ': ' . $e->getMessage());
                }
                $data['data'][$key]['translated_status'] = getTranslatedValue($data['data'][$key]['status'], 'panel');
            }

            $data['data'] = array_values($data['data']);
            if (isset($data['error'])) {
                return response_helper($data['message']);
            }
            if (!empty($data['data'])) {
                return response_helper(
                    labels(SERVICES_FETCHED_SUCCESSFULLY, 'services fetched successfully'),
                    false,
                    $data['data'],
                    200,
                    [
                        'total' => $data['new_total'],
                        'min_price' => $data['new_min_price'],
                        'max_price' => $data['new_max_price'],
                        'min_discount_price' => $data['new_min_discount_price'],
                        'max_discount_price' => $data['new_max_discount_price'],
                    ]
                );
            } else {
                return response_helper(
                    labels(SERVICES_NOT_FOUND, 'services not found'),
                    false,
                    [],
                    200,
                    [
                        'total' => $data['new_total'] ?? '0',
                        'min_price' => $data['new_min_price'] ?? '0',
                        'max_price' => $data['new_max_price'] ?? '0',
                        'min_discount_price' => $data['new_min_discount_price'] ?? '0',
                        'max_discount_price' => $data['new_max_discount_price'] ?? '0',
                    ]
                );
            }
        } catch (\Exception $th) {

            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - get_services()');
            return $this->response->setJSON($response);
        }
    }

    public function manage_service()
    {
        try {
            $this->validation =  \Config\Services::validation();

            // Get the default language from database
            $defaultLanguage = 'en'; // fallback
            $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], "", '0', 'id', 'ASC');
            foreach ($languages as $language) {
                if ($language['is_default'] == 1) {
                    $defaultLanguage = $language['code'];
                    break;
                }
            }

            // Validate translated_fields format
            $postData = $this->request->getPost();
            $validationErrors = [];

            // print_r($postData);
            // die;

            // Check if translated_fields is provided and handle JSON string format
            $translatedFields = $postData['translated_fields'] ?? null;

            // If translated_fields is provided as JSON string, decode it
            if (is_string($translatedFields)) {
                $translatedFields = json_decode($translatedFields, true);

                // Check if JSON decoding was successful
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $validationErrors[] = "translated_fields contains invalid JSON format: " . json_last_error_msg();
                } else {
                    // Update postData with decoded value for further processing
                    $postData['translated_fields'] = $translatedFields;
                }
            }

            // Check if translated_fields is provided and is an array
            if (!isset($postData['translated_fields']) || !is_array($postData['translated_fields'])) {
                $validationErrors[] = "translated_fields is required and must be an object";
            } else {
                $translatedFields = $postData['translated_fields'];
                $requiredFields = ['title', 'description', 'long_description', 'tags'];

                // Check if all required fields are present
                foreach ($requiredFields as $field) {
                    if (!isset($translatedFields[$field]) || !is_array($translatedFields[$field])) {
                        $validationErrors[] = "translated_fields.{$field} is required and must be an object";
                    } else {
                        // Check if default language is provided for required fields
                        if (!isset($translatedFields[$field][$defaultLanguage]) || empty($translatedFields[$field][$defaultLanguage])) {
                            $validationErrors[] = "translated_fields.{$field}.{$defaultLanguage} is required";
                        }
                    }
                }

                // Validate FAQ format if provided
                if (isset($translatedFields['faqs']) && is_array($translatedFields['faqs'])) {
                    foreach ($translatedFields['faqs'] as $languageCode => $faqs) {
                        if (!is_array($faqs)) {
                            $validationErrors[] = "translated_fields.faqs.{$languageCode} must be an array";
                        } else {
                            foreach ($faqs as $index => $faq) {
                                if (!is_array($faq) || !isset($faq['question']) || !isset($faq['answer'])) {
                                    $validationErrors[] = "translated_fields.faqs.{$languageCode}[{$index}] must have 'question' and 'answer' properties";
                                }
                            }
                        }
                    }
                }
            }

            if (!empty($validationErrors)) {
                $response = [
                    'error' => true,
                    'message' => $validationErrors,
                    'data' => []
                ];
                return $this->response->setJSON($response);
            }

            // Set validation rules for basic fields (translatable fields are handled in translation validation)
            $this->validation->setRules(
                [
                    'price' => 'required|numeric|greater_than[0]',
                    'duration' => 'required|numeric',
                    'max_qty' => 'required|numeric|greater_than[0]',
                    'members' => 'required|numeric|greater_than_equal_to[1]',
                    'categories' => 'required',
                    'discounted_price' => "permit_empty|numeric",
                    'is_cancelable' => 'numeric',
                    'at_store' => 'required',
                    'at_doorstep' => 'required',
                ],
            );

            if (!$this->validation->withRequest($this->request)->run()) {
                $errors = $this->validation->getErrors();
                $response = [
                    'error' => true,
                    'message' => $errors,
                    'data' => []
                ];
                return $this->response->setJSON($response);
            } else {
                $request = \Config\Services::request();
                $imagesToDelete = $this->getImagesToDeleteFromRequest($request, 'images_to_delete');
                $filesToDelete = $this->getImagesToDeleteFromRequest($request, 'files_to_delete'); // Add files deletion support
                $fileService = service('fileService');

                // Get current other_images from database before deletion (for existing services)
                $currentOtherImages = [];
                // Get current files from database before deletion (for existing services)
                $currentFiles = [];
                if (isset($_POST['service_id']) && !empty($_POST['service_id'])) {
                    $service_id = $_POST['service_id'];
                    $currentServiceData = fetch_details('services', ['id' => $service_id], ['other_images', 'files']);
                    if (!empty($currentServiceData[0]['other_images'])) {
                        $currentOtherImages = json_decode($currentServiceData[0]['other_images'], true) ?? [];
                    }
                    if (!empty($currentServiceData[0]['files'])) {
                        $currentFiles = json_decode($currentServiceData[0]['files'], true) ?? [];
                    }
                }

                // Delete specified service "other images" before processing uploads
                if (!empty($imagesToDelete)) {
                    $deletionResults = $this->processServiceImageDeletion($imagesToDelete, $fileService);


                    // Remove deleted images from database array
                    foreach ($imagesToDelete as $imageToDelete) {
                        $parsedInfo = $this->parseServiceImageUrl($imageToDelete);
                        if ($parsedInfo['filename']) {
                            $currentOtherImages = array_filter($currentOtherImages, function ($img) use ($parsedInfo) {
                                return !str_contains($img, $parsedInfo['filename']);
                            });
                        }
                    }
                    // Re-index array to avoid gaps
                    $currentOtherImages = array_values($currentOtherImages);
                }

                // Tags validation is now handled in the translation processing
                // No need to process tags here as they will be stored in translations table only
            }
            // Get default language values for slug generation only (not for main table storage)
            $title = '';
            $description = '';
            $longDescription = '';

            // Extract values from translated_fields for slug generation
            $translatedFields = $postData['translated_fields'];
            $title = $this->removeScript($translatedFields['title'][$defaultLanguage] ?? '');
            $description = $this->removeScript($translatedFields['description'][$defaultLanguage] ?? '');
            $longDescription = $this->removeScript($translatedFields['long_description'][$defaultLanguage] ?? '');
            $fileService = service('fileService');
            // Fetch existing service row when updating (includes slug for SlugService resolve/update)
            if (isset($_POST['service_id']) && !empty($_POST['service_id'])) {
                $service_id = $_POST['service_id'];
                $existingServiceRow = fetch_details('services', ['id' => $service_id], ['image', 'files', 'other_images', 'slug']);
                $existingRow = $existingServiceRow[0] ?? [];
                $old_icon = $existingRow['image'] ?? '';
                $old_files = $existingRow['files'] ?? '';
                $old_other_images = $existingRow['other_images'] ?? '';
                $existingSlug = $existingRow['slug'] ?? '';
            } else {
                $service_id = "";
                $old_icon = "";
                $old_files = "";
                $old_other_images = "";
                $existingSlug = '';
            }
            $image_name = $old_icon;
            if (!empty($_FILES['image']) && isset($_FILES['image'])) {
                $file = $this->request->getFile('image');
                $result = $fileService->replace(!empty($old_icon) ? $old_icon : null, $file, 'services');
                if ($result['error']) {
                    return ErrorResponse($result['message'] ?? labels(SOMETHING_WENT_WRONG, 'Something went wrong'), true, [], [], 200, csrf_token(), csrf_hash());
                }
                $image_name = $result['path'];
            }
            if (isset($_POST['sub_category']) && !empty($_POST['sub_category'])) {
                $category_id = $_POST['sub_category'];
            } else {
                $category_id = $_POST['categories'];
            }
            $discounted_price = $this->request->getPost('discounted_price');
            $price = $this->request->getPost('price');
            if ($discounted_price > $price) {
                $response = [
                    'error' => true,
                    'message' => labels(DISCOUNTED_PRICE_CAN_NOT_BE_HIGHER_THAN_THE_PRICE, 'discounted price can not be higher than the price'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                    'data' => []
                ];
                return $this->response->setJSON($response);
            }
            if ($discounted_price == $price) {
                $response = [
                    'error' => true,
                    'message' => labels(DISCOUNTED_PRICE_CAN_NOT_EQUAL_TO_THE_PRICE, 'discounted price can not equal to the price'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                    'data' => []
                ];
                return $this->response->setJSON($response);
            }
            $user_id = $this->user_details['id'];

            // Process files uploads - preserving order as uploaded
            $uploaded_images = $this->request->getFiles('files');

            // Start with current files (after deletions) - similar to other_images approach
            $finalFiles = $currentFiles;

            if (isset($uploaded_images['files'])) {
                // If new files are uploaded, replace all existing files (original behavior)
                $image_names['name'] = [];

                // Delete old files only if we're uploading new ones
                if (!empty($old_files) && empty($finalFiles)) {
                    $old_files_images_array = json_decode($old_files, true);
                    foreach ($old_files_images_array as $old) {
                        $fileService->delete('services', $old);
                    }
                }

                // Process files in order to preserve upload sequence
                foreach ($uploaded_images['files'] as $index => $images) {
                    $validate_image = valid_image($images);
                    if ($validate_image == true) {
                        return response_helper(labels(INVALID_IMAGE, 'Invalid Image'), true, []);
                    }
                    $result = $fileService->upload($images, 'services');
                    if ($result['error']) {
                        return ErrorResponse($result['message'] ?? labels(SOMETHING_WENT_WRONG, 'Something went wrong'), true, [], [], 200, csrf_token(), csrf_hash());
                    }
                    // Preserve order by using array index
                    $image_names['name'][$index] = $result['path'];
                }

                // Re-index array to maintain order and remove any gaps
                $image_names['name'] = array_values($image_names['name']);

                // Use newly uploaded files
                $files_names = json_encode($image_names['name']);
            } else {
                // No new files uploaded, use current files (after any deletions)
                $files_names = !empty($finalFiles) ? json_encode($finalFiles) : $old_files;
            }
            // Process other_images uploads - preserving order as uploaded
            $uploaded_other_images = $this->request->getFiles('other_images');

            // Start with current images (after deletions)
            $finalOtherImages = $currentOtherImages;

            if (!empty($uploaded_other_images)) {
                // Process other_images in order to preserve upload sequence
                $ordered_other_images = [];

                // Check if we have the nested array structure for multiple files
                if (isset($uploaded_other_images['other_images'])) {
                    // Handle multiple file uploads with same name
                    foreach ($uploaded_other_images['other_images'] as $index => $imageFile) {
                        if ($imageFile->isValid() && !$imageFile->hasMoved()) {
                            $validate_image = valid_image($imageFile);
                            if ($validate_image == true) {
                                return response_helper(labels(INVALID_IMAGE, 'Invalid Image'), true, []);
                            }

                            $result = $fileService->upload($imageFile, 'services');
                            if ($result['error']) {
                                return ErrorResponse($result['message'] ?? labels(SOMETHING_WENT_WRONG, 'Something went wrong'), true, [], [], 200, csrf_token(), csrf_hash());
                            }
                            // Preserve order by using array index
                            $ordered_other_images[$index] = $result['path'];
                        }
                    }
                } else {
                    // Handle single file or direct array structure
                    foreach ($uploaded_other_images as $index => $imageFile) {
                        if ($imageFile->isValid() && !$imageFile->hasMoved()) {
                            $validate_image = valid_image($imageFile);
                            if ($validate_image == true) {
                                return response_helper(labels(INVALID_IMAGE, 'Invalid Image'), true, []);
                            }

                            $result = $fileService->upload($imageFile, 'services');
                            if ($result['error']) {
                                return ErrorResponse($result['message'] ?? labels(SOMETHING_WENT_WRONG, 'Something went wrong'), true, [], [], 200, csrf_token(), csrf_hash());
                            }
                            // Preserve order by using array index
                            $ordered_other_images[$index] = $result['path'];
                        }
                    }
                }

                // Re-index array to maintain order and remove any gaps
                $ordered_other_images = array_values($ordered_other_images);

                // Add ordered images to final array
                $finalOtherImages = array_merge($finalOtherImages, $ordered_other_images);
            }

            // Set final other_images (existing after deletions + new uploads)
            $other_images = json_encode($finalOtherImages);
            $faqs = $this->request->getVar('faqs');
            if (isset($faqs)) {
                $array = json_decode(json_encode($faqs), true);
                $convertedArray = array_map(function ($item) {
                    return [$item['question'], $item['answer']];
                }, $array);
            }
            $partner_details = fetch_details('partner_details', ['partner_id' => $user_id]);
            $check_payment_gateway = get_settings('payment_gateways_settings', true);
            $cod_setting =  $check_payment_gateway['cod_setting'];
            if ($cod_setting == 1) {
                $is_pay_later_allowed = ($this->request->getPost('pay_later') == "1") ? 1 : 0;
            } else {
                $is_pay_later_allowed = 0;
            }

            $service_id_tmp = (empty($service_id) || $service_id == "") ? null : $service_id;
            // Slug: use SlugService (same as admin Partners bulk upload/update) for create and update
            $slugTitle = $title;
            if (empty($slugTitle)) {
                $slugTitle = 'service-' . time();
            }
            if (!empty($service_id)) {
                // Update: resolve slug (keep existing, or use POST slug / title); regenerate if legacy slug
                $resolvedSlug = $this->slugService->resolve(
                    currentSlug: $existingSlug,
                    inputSlug: trim($this->request->getPost('slug') ?? ''),
                    fallbackName: $slugTitle,
                    table: 'services',
                    excludeId: (int) $service_id
                );
                if ($this->slugService->isLegacySlug($existingSlug)) {
                    $resolvedSlug = $this->slugService->generate(
                        $slugTitle,
                        $slugTitle,
                        'services',
                        (int) $service_id
                    );
                }
            } else {
                // Create: resolve slug from optional POST slug or fallback to default-language title
                $resolvedSlug = $this->slugService->resolve(
                    currentSlug: null,
                    inputSlug: trim($this->request->getPost('slug') ?? ''),
                    fallbackName: $slugTitle,
                    table: 'services',
                    excludeId: null
                );
            }

            // Extract default language data from translated fields for main table storage
            $defaultLanguageData = $this->extractDefaultLanguageData($postData, $defaultLanguage);

            // Service data - now includes default language translatable fields in main table
            // while still maintaining translations in the translations table
            $service = [
                'id' => $service_id,
                'user_id' => $user_id,
                'category_id' => $category_id,
                'tax_type' => ($this->request->getPost('tax_type') != '') ? $this->request->getPost('tax_type') : 'GST',
                'tax_id' => ($this->request->getVar('tax_id') != '') ? $this->request->getVar('tax_id') : '0',
                'slug' => $resolvedSlug,
                'price' => $price,
                'discounted_price' => ($discounted_price != '') ? $discounted_price : '00',
                'image' => $image_name,
                'number_of_members_required' => $this->request->getVar('members'),
                'duration' => $this->request->getVar('duration'),
                'rating' => 0,
                'number_of_ratings' => 0,
                'on_site_allowed' => ($this->request->getPost('on_site') == "on") ? 1 : 0,
                'is_pay_later_allowed' => $is_pay_later_allowed,
                'is_cancelable' => ($this->request->getPost('is_cancelable') == 1) ? 1 : 0,
                'cancelable_till' => ($this->request->getVar('cancelable_till') != "") ? $this->request->getVar('cancelable_till') : '00',
                'max_quantity_allowed' => $this->request->getPost('max_qty'),
                'files' => isset($files_names) ? $files_names : "",
                'other_images' => $other_images,
                'at_doorstep' => ($this->request->getPost('at_doorstep') == 1) ? 1 : 0,
                'at_store' => ($this->request->getPost('at_store') == 1) ? 1 : 0,
                'status' => ($this->request->getPost('status') == "active") ? 1 : 0,
                // Add default language translatable fields to main table
                'title' => $defaultLanguageData['title'] ?? '',
                'description' => $defaultLanguageData['description'] ?? '',
                'long_description' => $defaultLanguageData['long_description'] ?? '',
                'tags' => $defaultLanguageData['tags'] ?? '',
                'faqs' => $defaultLanguageData['faqs'] ?? '',
            ];
            if ($service_id == '') {
                if ($partner_details[0]['need_approval_for_the_service'] == 1) {
                    $approved_by_admin = 0;
                } else {
                    $approved_by_admin = 1;
                }
                $service['approved_by_admin'] = $approved_by_admin;
            }
            $service_model = new Service_model;
            $db      = \Config\Database::connect();
            $fileService = service('fileService');
            if ($service_model->save($service)) {
                // Determine the correct service ID for both new and existing services
                if (empty($service_id) || $service_id == "") {
                    // This is a new service, use insertID
                    $actualServiceId = $service_model->insertID();
                } else {
                    // This is an existing service being updated, use the service_id from POST
                    $actualServiceId = $service_id;
                }

                try {
                    $this->saveServiceSeoSettings($actualServiceId); // Save SEO settings
                } catch (\Throwable $th) {
                    log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - manage_service() - SEO settings');
                    $this->response->setJSON([
                        'error' => true,
                        'message' => labels(FAILED_TO_SAVE_SEO_SETTINGS, 'Failed to save SEO settings') . ': ' . $th->getMessage(),
                        'csrfName' => csrf_token(),
                        'csrfHash' => csrf_hash(),
                        'data' => []
                    ]);
                }

                // Process service translations with enhanced validation
                try {
                    $this->processServiceTranslationsEnhanced($actualServiceId, $postData, $defaultLanguage);
                } catch (\Throwable $th) {
                    log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - manage_service() - Service translations');
                    // Don't fail the entire request for translation errors, just log them
                }

                if (!empty($actualServiceId)) {
                    $data = fetch_details('services', ['id' => $actualServiceId]);
                    $data[0]['image'] = !empty($data[0]['image']) ? $fileService->url($data[0]['image'], 'services') : "";

                    if (!empty($faqs) && is_string($faqs)) {
                        $faqs = json_decode($faqs, true);
                    }
                    if (empty($faqs) || !is_array($faqs)) {
                        $data[0]['faqs'] = [];
                    } else {
                        $data[0]['faqs'] =  ($faqs);
                    }
                    if (is_string($other_images)) {
                        $other_images = json_decode($other_images, true);
                    }
                    if (empty($other_images) || !is_array($other_images)) {
                        $data[0]['other_images'] = [];
                    } else {
                        // Add base URL to each image in other_images array
                        $formatted_other_images = [];
                        foreach ($other_images as $image) {
                            $formatted_other_images[] = !empty($image) ? $fileService->url($image, 'services') : $image;
                        }
                        $data[0]['other_images'] = $formatted_other_images;
                    }
                    if (is_string($files_names)) {
                        $files_names = json_decode($files_names, true);
                    }
                    if (empty($files_names) || !is_array($files_names)) {
                        $data[0]['files'] = [];
                    } else {
                        // Add base URL to each file in files array
                        $formatted_files = [];
                        foreach ($files_names as $file) {
                            $formatted_files[] = !empty($file) ? $fileService->url($file, 'services') : $file;
                        }
                        $data[0]['files'] = $formatted_files;
                    }

                    $data[0]['status'] = (!empty($data[0]['status']) && isset($data[0]['status']) && $data[0]['status'] == 1) ? "active" : "deactive";
                    $data[0]['image_of_the_service'] = (!empty($data[0]['image']) && isset($data[0]['image'])) ? $data[0]['image'] : "";


                    // Get translated category data for API response
                    $categoryData = fetch_details('categories', ['id' => $category_id]);
                    $translatedCategoryData = get_translated_category_data_for_api($category_id, $categoryData[0]);

                    $data[0]['category_name'] = $translatedCategoryData['name'];
                    $data[0]['category_translated_name'] = $translatedCategoryData['translated_name'];

                    // Add tax_title, tax_value, tax_percentage, translated_tax_title (same as get_services)
                    $data[0] = array_merge($data[0], $this->getServiceTaxFieldsForResponse($data[0]));

                    // Get service details for translation fallback
                    $serviceFallbackData = [
                        'title' => $data[0]['title'] ?? '',
                        'description' => $data[0]['description'] ?? '',
                        'long_description' => $data[0]['long_description'] ?? '',
                        'tags' => $data[0]['tags'] ?? '',
                        'faqs' => $data[0]['faqs'] ?? ''
                    ];

                    // Get translated data for this service based on Content-Language header
                    $translatedServiceData = $this->getServiceTranslatedFields($actualServiceId, $serviceFallbackData);


                    // Merge translated data with the response
                    if (!empty($translatedServiceData)) {
                        $data[0] = array_merge($data[0], $translatedServiceData);

                        // Update original fields with default language values for backward compatibility
                        if (isset($translatedServiceData['translated_fields'])) {
                            $translatedFields = $translatedServiceData['translated_fields'];

                            // Get default language values and update original fields
                            if (isset($translatedFields['title'][$defaultLanguage])) {
                                $data[0]['title'] = $translatedFields['title'][$defaultLanguage];
                            }
                            if (isset($translatedFields['description'][$defaultLanguage])) {
                                $data[0]['description'] = $translatedFields['description'][$defaultLanguage];
                            }
                            if (isset($translatedFields['long_description'][$defaultLanguage])) {
                                $data[0]['long_description'] = $translatedFields['long_description'][$defaultLanguage];
                            }
                            if (isset($translatedFields['tags'][$defaultLanguage])) {
                                $data[0]['tags'] = $translatedFields['tags'][$defaultLanguage];
                            }
                        }
                    }
                    $this->seoModel->setTableContext('services');
                    $seo_settings = $this->seoModel->getSeoSettingsByReferenceId($actualServiceId, 'meta');

                    $formatted_seo_settings = [];
                    if (!empty($seo_settings)) {
                        $formatted_seo_settings['seo_title'] = $seo_settings['title'] ?? $translatedServiceData['translated_fields']['title'][$defaultLanguage];
                        $formatted_seo_settings['seo_description'] = $seo_settings['description'] ?? $translatedServiceData['translated_fields']['description'][$defaultLanguage];
                        $formatted_seo_settings['seo_keywords'] = $seo_settings['keywords'] ?? '';
                        $formatted_seo_settings['seo_og_image'] = $seo_settings['image'] ?? ''; // Already formatted with proper URL
                        $formatted_seo_settings['seo_schema_markup'] = $seo_settings['schema_markup'] ?? '';
                    } else {
                        $formatted_seo_settings['seo_title'] = $translatedServiceData['translated_fields']['title'][$defaultLanguage];
                        $formatted_seo_settings['seo_description'] = $translatedServiceData['translated_fields']['description'][$defaultLanguage];
                        $formatted_seo_settings['seo_keywords'] = '';
                        $formatted_seo_settings['seo_og_image'] = '';
                        $formatted_seo_settings['seo_schema_markup'] = '';
                    }

                    // Use helper to assemble multilingual SEO for response (keeps existing fallbacks intact)
                    try {
                        helper('seo_translations');
                        $serviceTf = $translatedServiceData['translated_fields'] ?? [];
                        $seoBuild = getServiceSeoForManageServiceResponse($actualServiceId, $seo_settings ?: [], $serviceTf, $defaultLanguage);
                        // Merge translated_fields patch
                        if (!empty($seoBuild['translated_fields_patch'])) {
                            foreach ($seoBuild['translated_fields_patch'] as $k => $v) {
                                $data[0]['translated_fields'][$k] = ($data[0]['translated_fields'][$k] ?? []) + $v;
                            }
                        }
                        // Apply legacy overrides only if base SEO exists
                        if (!empty($seo_settings) && !empty($seoBuild['legacy_override'])) {
                            foreach ($seoBuild['legacy_override'] as $lk => $lv) {
                                if ($lk !== 'seo_og_image') {
                                    $formatted_seo_settings[$lk] = $lv;
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        log_message('error', 'Failed to assemble multilingual SEO for service ' . $actualServiceId . ': ' . $e->getMessage());
                    }

                    $data[0] = array_merge($data[0], $formatted_seo_settings);

                    $data[0]['translated_status'] = getTranslatedValue($data[0]['status'], 'panel');

                    $response = [
                        'error' => false,
                        'message' => labels(SERVICE_SAVED_SUCCESSFULLY, 'Service saved successfully!'),
                        'csrfName' => csrf_token(),
                        'csrfHash' => csrf_hash(),
                        'data' => $data
                    ];
                } else {
                    // Update Service
                    $data = fetch_details('services', ['id' => $actualServiceId]);
                    $data[0]['image'] = !empty($data[0]['image']) ? $fileService->url($data[0]['image'], 'services') : "";

                    if (!empty($faqs) && is_string($faqs)) {
                        $faqs = json_decode($faqs, true);
                    }
                    if (empty($faqs) || !is_array($faqs)) {
                        $data[0]['faqs'] = [];
                    } else {
                        $data[0]['faqs'] =  ($faqs);
                    }
                    if (is_string($other_images)) {
                        $other_images = json_decode($other_images, true);
                    }
                    if (empty($other_images) || !is_array($other_images)) {
                        $data[0]['other_images'] = [];
                    } else {
                        // Add base URL to each image in other_images array
                        $formatted_other_images = [];
                        foreach ($other_images as $image) {
                            $formatted_other_images[] = !empty($image) ? $fileService->url($image, 'services') : $image;
                        }
                        $data[0]['other_images'] = $formatted_other_images;
                    }
                    if (is_string($files_names)) {
                        $files_names = json_decode($files_names, true);
                    }
                    if (empty($files_names) || !is_array($files_names)) {
                        $data[0]['files'] = [];
                    } else {
                        // Add base URL to each file in files array
                        $formatted_files = [];
                        foreach ($files_names as $file) {
                            $formatted_files[] = !empty($file) ? $fileService->url($file, 'services') : $file;
                        }
                        $data[0]['files'] = $formatted_files;
                    }

                    $data[0]['status'] = ($data[0]['status'] == 1) ? "active" : "deactive";
                    $data[0]['image_of_the_service'] = $data[0]['image'];

                    // Get translated category data for API response
                    $categoryData = fetch_details('categories', ['id' => $category_id]);
                    $translatedCategoryData = get_translated_category_data_for_api($category_id, $categoryData[0]);

                    $data[0]['category_name'] = $translatedCategoryData['name'];
                    $data[0]['category_translated_name'] = $translatedCategoryData['translated_name'];

                    // Add tax_title, tax_value, tax_percentage, translated_tax_title (same as get_services)
                    $data[0] = array_merge($data[0], $this->getServiceTaxFieldsForResponse($data[0]));

                    // Get service details for translation fallback
                    $serviceFallbackData = [
                        'title' => $data[0]['title'] ?? '',
                        'description' => $data[0]['description'] ?? '',
                        'long_description' => $data[0]['long_description'] ?? '',
                        'tags' => $data[0]['tags'] ?? '',
                        'faqs' => $data[0]['faqs'] ?? ''
                    ];
                    // Get translated data for this service based on Content-Language header
                    $translatedServiceData = $this->getServiceTranslatedFields($actualServiceId, $serviceFallbackData);

                    // Merge translated data with the response
                    if (!empty($translatedServiceData)) {

                        $data[0] = array_merge($data[0], $translatedServiceData);

                        // Update original fields with default language values for backward compatibility
                        if (isset($translatedServiceData['translated_fields'])) {
                            $translatedFields = $translatedServiceData['translated_fields'];

                            // Get default language values and update original fields
                            if (isset($translatedFields['title'][$defaultLanguage])) {
                                $data[0]['title'] = $translatedFields['title'][$defaultLanguage];
                            }
                            if (isset($translatedFields['description'][$defaultLanguage])) {
                                $data[0]['description'] = $translatedFields['description'][$defaultLanguage];
                            }
                            if (isset($translatedFields['long_description'][$defaultLanguage])) {
                                $data[0]['long_description'] = $translatedFields['long_description'][$defaultLanguage];
                            }
                            if (isset($translatedFields['tags'][$defaultLanguage])) {
                                $data[0]['tags'] = $translatedFields['tags'][$defaultLanguage];
                            }
                        }
                    }

                    $this->seoModel->setTableContext('services');
                    $seo_settings = $this->seoModel->getSeoSettingsByReferenceId($actualServiceId, 'full');

                    $formatted_seo_settings = [];
                    if (!empty($seo_settings)) {
                        $formatted_seo_settings['seo_title'] = $seo_settings['title'] ?? $translatedServiceData['translated_fields']['title'][$defaultLanguage];
                        $formatted_seo_settings['seo_description'] = $seo_settings['description'] ?? $translatedServiceData['translated_fields']['description'][$defaultLanguage];
                        $formatted_seo_settings['seo_keywords'] = $seo_settings['keywords'] ?? '';
                        $formatted_seo_settings['seo_og_image'] = $seo_settings['image'] ?? ''; // Already formatted with proper URL
                        $formatted_seo_settings['seo_schema_markup'] = $seo_settings['schema_markup'] ?? '';
                    } else {
                        $formatted_seo_settings['seo_title'] = $translatedServiceData['translated_fields']['title'][$defaultLanguage];
                        $formatted_seo_settings['seo_description'] = $translatedServiceData['translated_fields']['description'][$defaultLanguage];
                        $formatted_seo_settings['seo_keywords'] = '';
                        $formatted_seo_settings['seo_og_image'] = '';
                        $formatted_seo_settings['seo_schema_markup'] = '';
                    }
                    // Augment response with multilingual SEO in translated_fields and legacy fallbacks
                    try {
                        helper('seo_translations');
                        $serviceTf = $translatedServiceData['translated_fields'] ?? [];
                        $seoBuild = getServiceSeoForManageServiceResponse($actualServiceId, $seo_settings ?: [], $serviceTf, $defaultLanguage);
                        // Merge translated_fields patch
                        if (!empty($seoBuild['translated_fields_patch'])) {
                            foreach ($seoBuild['translated_fields_patch'] as $k => $v) {
                                $data[0]['translated_fields'][$k] = ($data[0]['translated_fields'][$k] ?? []) + $v;
                            }
                        }
                        // Apply legacy overrides only if base SEO exists
                        if (!empty($seo_settings) && !empty($seoBuild['legacy_override'])) {
                            foreach ($seoBuild['legacy_override'] as $lk => $lv) {
                                if ($lk !== 'seo_og_image') {
                                    $formatted_seo_settings[$lk] = $lv;
                                }
                            }
                        }
                    } catch (\Throwable $e) {
                        log_message('error', 'Failed to assemble multilingual SEO for service ' . $actualServiceId . ': ' . $e->getMessage());
                    }

                    $data[0] = array_merge($data[0], $formatted_seo_settings);



                    $data[0]['translated_status'] = getTranslatedValue($data[0]['status'], 'panel');


                    $response = [
                        'error' => false,
                        'message' => labels(SERVICE_UPDATED_SUCCESSFULLY, 'Service updated successfully!'),
                        'csrfName' => csrf_token(),
                        'csrfHash' => csrf_hash(),
                        'data' => $data
                    ];
                }


                $response = [
                    'error' => false,
                    'message' => labels(SERVICE_SAVED_SUCCESSFULLY, 'Service saved successfully!'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                    'data' => $data
                ];
                return $this->response->setJSON($response);
            } else {
                $response = [
                    'error' => true,
                    'message' => labels(SERVICE_CAN_NOT_BE_SAVED, 'Service can not be Saved!'),
                    'csrfName' => csrf_token(),
                    'csrfHash' => csrf_hash(),
                    'data' => []
                ];
                return $this->response->setJSON($response);
            }
        } catch (\Exception $th) {
            throw $th;
            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - manage_service()');
            return $this->response->setJSON($response);
        }
    }

    public function delete_service()
    {
        try {
            $validation =  \Config\Services::validation();
            $validation->setRules(
                [
                    'service_id' => 'required|numeric',
                ]
            );
            if (!$validation->withRequest($this->request)->run()) {
                $errors = $validation->getErrors();
                $response = [
                    'error' => true,
                    'message' => $errors,
                    'data' => []
                ];
                return $this->response->setJSON($response);
            }
            $service_id = $this->request->getPost('service_id');
            $exist_service = fetch_details('services', ['id' => $service_id, 'user_id' => $this->user_details['id']], ['id']);
            $fileService = service('fileService');
            if (!empty($exist_service)) {
                $db      = \Config\Database::connect();
                $builder2 = $db->table('cart')->delete(['service_id' => $service_id]);
                $old_data = fetch_details('services', ['id' => $service_id]);
                if ($old_data[0]['image'] != NULL &&  !empty($old_data[0]['image'])) {
                    $fileService->delete('services', $old_data[0]['image']);
                }
                if ($old_data[0]['other_images'] != NULL &&  !empty($old_data[0]['other_images'])) {
                    $other_images = json_decode($old_data[0]['other_images'], true);
                    foreach ($other_images as $oi) {
                        $fileService->delete('services', $oi);
                    }
                }
                if ($old_data[0]['files'] != NULL &&  !empty($old_data[0]['files'])) {
                    $files = json_decode($old_data[0]['files'], true);
                    foreach ($files as $oi) {
                        $fileService->delete('services', $oi);
                    }
                }

                // Clean up SEO settings and images before deleting service
                $this->seoModel->cleanupSeoData($service_id, 'services');

                $builder = $db->table('services')->delete(['id' => $service_id, 'user_id' => $this->user_details['id']]);
                $builder3 = $db->table('services_ratings')->delete(['service_id' => $service_id]);
                if ($builder) {
                    $response = [
                        'error' => false,
                        'message' => labels(SERVICE_DELETED_SUCCESSFULLY, 'Service deleted successfully!'),
                        'data' => []
                    ];
                    return $this->response->setJSON($response);
                } else {
                    $response = [
                        'error' => true,
                        'message' => labels(SERVICE_DOES_NOT_EXIST, 'Service does not exist!'),
                        'data' => []
                    ];
                    return $this->response->setJSON($response);
                }
            } else {
                $response = [
                    'error' => true,
                    'message' => labels(SERVICE_DOES_NOT_EXIST, 'Service does not exist!'),
                    'data' => []
                ];
                return $this->response->setJSON($response);
            }
        } catch (\Exception $th) {

            $response['error'] = true;
            $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
            log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - delete_service()');
            return $this->response->setJSON($response);
        }
    }

    // Private helper methods

    /**
     * Get translated service data in the translated_fields format
     * 
     * Returns translations in the same format as manage_service API expects:
     * {
     *   "translated_fields": {
     *     "title": {
     *       "en": "Service Title in English",
     *       "hi": "Service Title in Hindi",
     *       "ar": "Service Title in Arabic"
     *     },
     *     "description": {
     *       "en": "Description in English", 
     *       "hi": "Description in Hindi",
     *       "ar": "Description in Arabic"
     *     },
     *     "long_description": {
     *       "en": "Long description in English",
     *       "hi": "Long description in Hindi", 
     *       "ar": "Long description in Arabic"
     *     },
     *     "tags": {
     *       "en": "Tags in English",
     *       "hi": "Tags in Hindi",
     *       "ar": "Tags in Arabic"
     *     },
     *     "faqs": {
     *       "en": [...],
     *       "hi": [...],
     *       "ar": [...]
     *     }
     *   }
     * }
     * 
     * @param int $serviceId Service ID
     * @param array $fallbackData Fallback data from main table
     * @return array Translated data in translated_fields format
     */
    private function getServiceTranslatedFields(int $serviceId, array $fallbackData = []): array
    {
        try {
            // Get all available languages from database
            $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], "", '0', 'id', 'ASC');

            // Get default language
            $defaultLanguage = get_default_language();

            // Initialize the translated_fields structure
            $translatedFields = [
                'title' => [],
                'description' => [],
                'long_description' => [],
                'tags' => [],
                'faqs' => []
            ];

            // Define translatable fields and their fallback values
            $translatableFields = [
                'title' => $fallbackData['title'] ?? '',
                'description' => $fallbackData['description'] ?? '',
                'long_description' => $fallbackData['long_description'] ?? '',
                'tags' => $fallbackData['tags'] ?? '',
                'faqs' => $fallbackData['faqs'] ?? ''
            ];

            // Process each language
            foreach ($languages as $language) {
                $languageCode = $language['code'];

                // Get translations for this language
                $translations = get_service_translations($serviceId, $languageCode);


                // Process each translatable field
                foreach ($translatableFields as $fieldName => $fallbackValue) {
                    // Get translated value for this field and language
                    $translatedValue = $translations[$fieldName] ?? $fallbackValue;

                    // Handle special case for FAQs - decode JSON if it's a string
                    if ($fieldName === 'faqs' && is_string($translatedValue)) {
                        $translatedValue = json_decode($translatedValue, true) ?? [];
                    }

                    // Handle special case for tags - keep as string, don't decode as JSON
                    // Tags are stored as comma-separated strings, not JSON arrays
                    // No special processing needed for tags - they should remain as strings

                    // Set the translated value for this language
                    $translatedFields[$fieldName][$languageCode] = $translatedValue;
                }
            }

            return [
                'translated_fields' => $translatedFields
            ];
        } catch (\Exception $e) {
            // Log error but don't break the function
            log_message('error', 'Translation processing failed in getServiceTranslatedFields: ' . $e->getMessage());

            // Return fallback structure with default language only
            return [
                'translated_fields' => [
                    'title' => ['en' => $fallbackData['title'] ?? ''],
                    'description' => ['en' => $fallbackData['description'] ?? ''],
                    'long_description' => ['en' => $fallbackData['long_description'] ?? ''],
                    'tags' => ['en' => $fallbackData['tags'] ?? ''],
                    'faqs' => ['en' => $fallbackData['faqs'] ?? '']
                ]
            ];
        }
    }

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

    private function processServiceImageDeletion($imageUrls, $fileService)
    {
        $deletionResults = [];

        if (empty($imageUrls) || !is_array($imageUrls)) {
            return $deletionResults;
        }

        foreach ($imageUrls as $imageUrl) {
            if (empty($imageUrl)) {
                continue;
            }

            // Extract folder and filename from URL for services
            $parsedInfo = $this->parseServiceImageUrl($imageUrl);

            if ($parsedInfo['folder'] && $parsedInfo['filename']) {
                $result = $fileService->delete(
                    $parsedInfo['folder'],
                    $parsedInfo['filename']
                );

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

    private function parseServiceImageUrl($imageUrl)
    {
        $folder = '';
        $filename = '';

        // ONLY allow deletion of service "other images" (services folder)
        if (strpos($imageUrl, 'public/uploads/services/') !== false) {
            $folder = 'services';
            $filename = basename($imageUrl);
        } else {
            // Try to extract from URL pattern for services folder only
            $urlParts = parse_url($imageUrl);
            if (isset($urlParts['path'])) {
                $pathParts = explode('/', trim($urlParts['path'], '/'));
                $filename = end($pathParts);

                // Only allow services folder
                if (in_array('services', $pathParts)) {
                    $folder = 'services';
                }
            }
        }

        return [
            'folder' => $folder,
            'filename' => $filename
        ];
    }

    /**
     * Extract default language data from translated fields for main table storage
     * 
     * This method extracts the default language values from the translated_fields
     * and returns them in a format suitable for storing in the main services table.
     * 
     * @param array $postData POST data containing translated_fields
     * @param string $defaultLanguage Default language code (e.g., 'en')
     * @return array Default language data for main table
     */
    private function extractDefaultLanguageData(array $postData, string $defaultLanguage): array
    {
        $defaultData = [
            'title' => '',
            'description' => '',
            'long_description' => '',
            'tags' => '',
            'faqs' => ''
        ];

        // Check if translated_fields is provided
        $translatedFields = $postData['translated_fields'] ?? null;

        // Handle case where translated_fields might be a JSON string
        if (is_string($translatedFields)) {
            $translatedFields = json_decode($translatedFields, true);
        }

        if (isset($translatedFields) && is_array($translatedFields)) {
            // Extract default language data for each translatable field
            $translatableFields = ['title', 'description', 'long_description', 'tags', 'faqs'];

            foreach ($translatableFields as $field) {
                if (isset($translatedFields[$field]) && is_array($translatedFields[$field])) {
                    $fieldData = $translatedFields[$field];

                    // Get the default language value for this field
                    if (isset($fieldData[$defaultLanguage])) {
                        if ($field === 'faqs') {
                            // Handle FAQs - convert to JSON string for main table storage
                            $faqsData = $fieldData[$defaultLanguage];
                            if (is_array($faqsData)) {
                                $defaultData['faqs'] = json_encode($faqsData, JSON_UNESCAPED_UNICODE);
                            } else {
                                $defaultData['faqs'] = $faqsData;
                            }
                        } else {
                            // For other fields, just get the value
                            $defaultData[$field] = trim($fieldData[$defaultLanguage]);
                        }
                    }
                }
            }
        }

        return $defaultData;
    }

    private function saveServiceSeoSettings(int $serviceId): void
    {
        try {
            // Get default language for base SEO data
            $defaultLanguage = get_default_language();

            // Get translated fields from POST data
            $translatedFields = $this->request->getPost('translated_fields');

            // If translated fields are provided as JSON string, decode it
            if (is_string($translatedFields)) {
                $translatedFields = json_decode($translatedFields, true);
            }

            // Extract default language SEO data for base SEO settings
            $defaultSeoTitle = '';
            $defaultSeoDescription = '';
            $defaultSeoKeywords = '';
            $defaultSeoSchema = '';

            // Try to get SEO data from translated_fields first (multilingual approach)
            if (!empty($translatedFields['seo_title'][$defaultLanguage])) {
                $defaultSeoTitle = trim($translatedFields['seo_title'][$defaultLanguage]);
            } elseif ($this->request->getPost('seo_title')) {
                // Fallback to single-language field
                $defaultSeoTitle = trim((string) $this->request->getPost('seo_title'));
            }

            if (!empty($translatedFields['seo_description'][$defaultLanguage])) {
                $defaultSeoDescription = trim($translatedFields['seo_description'][$defaultLanguage]);
            } elseif ($this->request->getPost('seo_description')) {
                // Fallback to single-language field
                $defaultSeoDescription = trim((string) $this->request->getPost('seo_description'));
            }

            if (!empty($translatedFields['seo_keywords'][$defaultLanguage])) {
                $keywordValue = $translatedFields['seo_keywords'][$defaultLanguage];
                $defaultSeoKeywords = $this->parseKeywords($keywordValue);
            } elseif ($this->request->getPost('seo_keywords')) {
                // Fallback to single-language field
                $metaKeywords = $this->request->getPost('seo_keywords');
                $defaultSeoKeywords = $this->parseKeywords($metaKeywords);
            }

            if (!empty($translatedFields['seo_schema_markup'][$defaultLanguage])) {
                $defaultSeoSchema = trim($translatedFields['seo_schema_markup'][$defaultLanguage]);
            } elseif ($this->request->getPost('seo_schema_markup')) {
                // Fallback to single-language field
                $defaultSeoSchema = trim((string) $this->request->getPost('seo_schema_markup'));
            }

            // Build SEO data array for base settings
            $seoData = [
                'title'         => $defaultSeoTitle,
                'description'   => $defaultSeoDescription,
                'keywords'      => $defaultSeoKeywords,
                'schema_markup' => $defaultSeoSchema,
                'service_id'    => $serviceId,
            ];

            // Check if any SEO field is filled (excluding service_id)
            $hasSeoData = array_filter($seoData, fn($v) => !empty($v) && $v !== $serviceId);

            // Check if all SEO fields are intentionally cleared
            $allFieldsCleared = empty($seoData['title']) &&
                empty($seoData['description']) &&
                empty($seoData['keywords']) &&
                empty($seoData['schema_markup']);

            // Handle SEO image upload
            $seoImage = $this->request->getFile('seo_og_image');
            $hasImage = $seoImage && $seoImage->isValid();

            // Use Seo_model for service context
            $this->seoModel->setTableContext('services');
            $existingSettings = $this->seoModel->getSeoSettingsByReferenceId($serviceId);

            $fileService = service('fileService');
            $newSeoData = $seoData;
            if ($hasImage) {
                $uploadResult = $fileService->upload($seoImage, 'service_seo_settings');
                if ($uploadResult['error']) {
                    throw new \Exception(labels(SEO_IMAGE_UPLOAD_FAILED, 'SEO image upload failed') . ': ' . $uploadResult['message']);
                }
                $newSeoData['image'] = $uploadResult['path'];
            } else {
                $newSeoData['image'] = $existingSettings['image'] ?? '';
            }

            // If no existing settings, create new if data or image exists
            if (!$existingSettings) {
                if ($hasSeoData || $hasImage) {
                    $result = $this->seoModel->createSeoSettings($newSeoData);
                    if (!empty($result['error'])) {
                        $errors = $result['validation_errors'] ?? [];
                        throw new \Exception($result['message'] . (!empty($errors) ? ': ' . json_encode($errors) : ''));
                    }
                }

                // Process SEO translations after creating base SEO settings
                $this->processSeoTranslations($serviceId, $translatedFields);
                return;
            }

            // If existing settings exist and all fields are cleared (and no new image), delete the record
            if ($existingSettings && $allFieldsCleared && !$hasImage && empty($existingSettings['image'])) {
                $result = $this->seoModel->delete($existingSettings['id']);
                if ($result) {
                    // Clean up old image if it exists
                    if (!empty($existingSettings['image'])) {
                        $fileService->delete('service_seo_settings', $existingSettings['image']);
                    }
                }
                // Also clean up SEO translations when deleting base SEO settings
                $this->cleanupSeoTranslations($serviceId);
                return;
            }

            // Compare existing and new settings
            $settingsChanged = false;
            foreach ($newSeoData as $key => $value) {
                $existingValue = $existingSettings[$key] ?? '';
                $newValue = $value ?? '';
                if ($existingValue !== $newValue) {
                    $settingsChanged = true;
                    break;
                }
            }

            // Also check if a new image was uploaded (this forces an update)
            if (!$settingsChanged && $hasImage) {
                $settingsChanged = true;
            }

            if (!$settingsChanged) {
                // Even if base SEO settings haven't changed, process translations
                $this->processSeoTranslations($serviceId, $translatedFields);
                return;
            }

            // Update existing settings with new data
            $result = $this->seoModel->updateSeoSettings($existingSettings['id'], $newSeoData);
            if (!empty($result['error'])) {
                $errors = $result['validation_errors'] ?? [];
                throw new \Exception($result['message'] . (!empty($errors) ? ': ' . json_encode($errors) : ''));
            }

            // Process SEO translations after updating base SEO settings
            $this->processSeoTranslations($serviceId, $translatedFields);
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/api/V1.php - saveServiceSeoSettings()');
            throw $th; // Re-throw to handle in manage_service
        }
    }

    private function parseKeywords($input): string
    {
        // If input is empty, return empty string
        if (empty($input)) {
            return '';
        }

        // If input is a string, it might be JSON or comma-separated
        if (is_string($input)) {
            // Check if it's a JSON string
            if (json_decode($input, true) !== null) {
                $decoded = json_decode($input, true);
                if (is_array($decoded)) {
                    // Handle array of objects (e.g., [{value: "tag1"}, {value: "tag2"}])
                    $tags = array_map(function ($item) {
                        return is_array($item) && isset($item['value']) ? trim($item['value']) : trim($item);
                    }, $decoded);
                    return implode(',', $tags);
                }
            }
            // Treat as comma-separated string
            return trim($input);
        }

        // If input is an array
        if (is_array($input)) {
            // Handle case where array contains a single JSON string (e.g., ['[{value: "tag1"}, {value: "tag2"}]'])
            if (count($input) === 1 && is_string($input[0]) && json_decode($input[0], true) !== null) {
                $decoded = json_decode($input[0], true);
                if (is_array($decoded)) {
                    $tags = array_map(function ($item) {
                        return is_array($item) && isset($item['value']) ? trim($item['value']) : trim($item);
                    }, $decoded);
                    return implode(',', $tags);
                }
            }
            // Handle array of objects (e.g., [{value: "tag1"}, {value: "tag2"}])
            $tags = array_map(function ($item) {
                return is_array($item) && isset($item['value']) ? trim($item['value']) : trim($item);
            }, $input);
            return implode(',', $tags);
        }

        // Fallback: return empty string for unexpected input
        return '';
    }

    /**
     * Process SEO translations from translated_fields data
     * 
     * Extracts SEO fields from translated_fields and stores them
     * in the translated_service_seo_settings table.
     * 
     * @param int $serviceId Service ID
     * @param array|null $translatedFields Translated fields data from POST
     * @return void
     */
    private function processSeoTranslations(int $serviceId, ?array $translatedFields = null): void
    {
        try {
            // Use provided translated fields or get from POST request (fallback)
            if ($translatedFields === null) {
                $translatedFields = $this->request->getPost('translated_fields');

                // If translated fields are provided as JSON string, decode it
                if (is_string($translatedFields)) {
                    $translatedFields = json_decode($translatedFields, true);
                }
            }

            // Process SEO translations if data is provided
            if (!empty($translatedFields) && is_array($translatedFields)) {
                // Load the SEO translation model
                $seoTranslationModel = model('TranslatedServiceSeoSettings_model');

                // Restructure data for the model (convert field[lang] to lang[field] format)
                $restructuredData = $this->restructureTranslatedFieldsForSeoModel($translatedFields);

                // Process and store the SEO translations
                $seoTranslationResult = $seoTranslationModel->processSeoTranslations($serviceId, $restructuredData);

                // Check if SEO translation processing was successful
                if (!$seoTranslationResult['success']) {
                    throw new \Exception('SEO Translation processing failed: ' . json_encode($seoTranslationResult['errors']));
                }
            }
        } catch (\Exception $e) {
            // Log error but don't fail the entire operation
            log_message('error', 'Exception in processSeoTranslations for service ' . $serviceId . ': ' . $e->getMessage());
            // Re-throw for critical errors
            throw new \Exception('Exception in processSeoTranslations for service ' . $serviceId . ': ' . $e->getMessage());
        }
    }

    /**
     * Restructure translated fields for SEO model
     * Convert from field[lang] format to lang[field] format
     * 
     * Input format:  field[lang] - e.g., seo_title['en'], seo_title['ar']
     * Output format: lang[field] - e.g., en[seo_title], ar[seo_title]
     * 
     * @param array $translatedFields Translated fields in field[lang] format
     * @return array Restructured data in lang[field] format
     */
    private function restructureTranslatedFieldsForSeoModel(array $translatedFields): array
    {
        $restructured = [];

        // SEO fields we want to process
        $seoFields = ['seo_title', 'seo_description', 'seo_keywords', 'seo_schema_markup'];

        // Get all available languages from the translated fields
        $languages = [];
        foreach ($seoFields as $field) {
            if (isset($translatedFields[$field]) && is_array($translatedFields[$field])) {
                $fieldLanguages = array_keys($translatedFields[$field]);
                $languages = array_merge($languages, $fieldLanguages);
            }
        }
        $languages = array_unique($languages);

        // Restructure data: from field[lang] to lang[field]
        foreach ($languages as $languageCode) {
            $restructured[$languageCode] = [];

            foreach ($seoFields as $field) {
                if (isset($translatedFields[$field][$languageCode]) && !empty($translatedFields[$field][$languageCode])) {
                    $value = $translatedFields[$field][$languageCode];

                    // Special handling for keywords - parse them using parseKeywords function
                    if ($field === 'seo_keywords') {
                        $parsedKeywords = $this->parseKeywords($value);
                        $restructured[$languageCode][$field] = $parsedKeywords;
                    } else {
                        $restructured[$languageCode][$field] = $value;
                    }
                }
            }

            // Only keep languages that have at least one SEO field
            if (empty($restructured[$languageCode])) {
                unset($restructured[$languageCode]);
            }
        }

        return $restructured;
    }

    /**
     * Clean up SEO translations when base SEO settings are deleted
     * 
     * Removes all translations from translated_service_seo_settings
     * for the given service ID.
     * 
     * @param int $serviceId The service ID
     * @return void
     */
    private function cleanupSeoTranslations(int $serviceId): void
    {
        try {
            // Load the SEO translation model
            $seoTranslationModel = model('TranslatedServiceSeoSettings_model');

            // Delete all SEO translations for this service
            $seoTranslationModel->deleteServiceSeoTranslations($serviceId);

            log_message('info', 'Cleaned up SEO translations for service ' . $serviceId);
        } catch (\Exception $e) {
            // Log any exceptions but don't fail the operation
            log_message('error', 'Exception in cleanupSeoTranslations for service ' . $serviceId . ': ' . $e->getMessage());
        }
    }

    /**
     * Enhanced service translation processing with proper validation and handling
     * 
     * Expected API format for translatable fields:
     * {
     *   "translated_fields": {
     *     "title": {
     *       "en": "Service Title in English",
     *       "hi": "Service Title in Hindi",
     *       "ar": "Service Title in Arabic"
     *     },
     *     "description": {
     *       "en": "Service description in English",
     *       "hi": "Service description in Hindi",
     *       "ar": "Service description in Arabic"
     *     },
     *     "long_description": {
     *       "en": "Detailed description in English",
     *       "hi": "Detailed description in Hindi",
     *       "ar": "Detailed description in Arabic"
     *     },
     *     "tags": {
     *       "en": "tag1, tag2, tag3",
     *       "hi": "टैग1, टैग2, टैग3",
     *       "ar": "وسم1, وسم2, وسم3"
     *     },
     *     "faqs": {
     *       "en": [
     *         {"question": "What is the response time?", "answer": "We usually respond within 24 hours."},
     *         {"question": "Do you offer free trials?", "answer": "Yes, we offer a 7-day free trial."}
     *       ],
     *       "hi": [
     *         {"question": "प्रतिक्रिया समय क्या है?", "answer": "हम आमतौर पर 24 घंटे के भीतर जवाब देते हैं।"}
     *       ],
     *       "ar": [
     *         {"question": "هل يمكنني الإلغاء في أي وقت؟", "answer": "نعم، يمكنك إلغاء اشتراكك في أي وقت تريده."}
     *       ]
     *     }
     *   }
     * }
     * 
     * @param int $serviceId Service ID
     * @param array $postData POST data from the form
     * @param string $defaultLanguage Default language code
     */
    private function processServiceTranslationsEnhanced(int $serviceId, array $postData, string $defaultLanguage): void
    {
        try {
            // Transform form data to translated_fields structure
            $translatedFields = $this->transformFormDataToTranslatedFields($postData, $defaultLanguage, $serviceId);

            // Process translations if data is provided
            if (!empty($translatedFields) && is_array($translatedFields)) {
                // Validate the translated fields structure
                $validationResult = $this->validateServiceTranslatedFields($translatedFields);

                if (!$validationResult['valid']) {
                    // Log validation errors but don't fail the service creation
                    log_message('error', 'Service translation validation failed: ' . json_encode($validationResult['errors']));
                    return;
                }

                // Process and store the translations
                $translationResult = $this->processServiceTranslationsData($serviceId, $translatedFields);

                // Check if translation processing was successful
                if (!$translationResult['success']) {
                    // Log the errors but don't fail the entire service creation
                    log_message('error', 'Service translation processing failed: ' . json_encode($translationResult['errors']));
                }

                // Log successful translations for debugging
                if (!empty($translationResult['processed_languages'])) {
                    log_message('info', 'Successfully processed service translations for service ' . $serviceId . ': ' . json_encode($translationResult['processed_languages']));
                }
            }
        } catch (\Exception $e) {
            // Log any exceptions but don't fail the service creation
            log_message('error', 'Exception in processServiceTranslationsEnhanced for service ' . $serviceId . ': ' . $e->getMessage());
        }
    }
         
    /**
     * Validate service translated fields structure
     * 
     * @param array $translatedFields The translated fields data
     * @return array Validation result with success status and errors
     */
    private function validateServiceTranslatedFields(array $translatedFields): array
    {
        $result = [
            'valid' => true,
            'errors' => []
        ];

        // Check if translated fields is an array
        if (!is_array($translatedFields)) {
            $result['valid'] = false;
            $result['errors'][] = 'Translated fields must be an array';
            return $result;
        }

        // Define allowed fields for services
        $allowedFields = ['title', 'description', 'long_description', 'tags', 'faqs'];

        // Check each field
        foreach ($translatedFields as $fieldName => $languageData) {
            if (!in_array($fieldName, $allowedFields)) {
                $result['valid'] = false;
                $result['errors'][] = "Field '{$fieldName}' is not allowed for service translations";
                continue;
            }

            if (!is_array($languageData)) {
                $result['valid'] = false;
                $result['errors'][] = "Language data for field '{$fieldName}' must be an array";
                continue;
            }

            // Check each language
            foreach ($languageData as $languageCode => $translatedText) {
                if (!is_string($languageCode) || strlen($languageCode) > 10) {
                    $result['valid'] = false;
                    $result['errors'][] = "Invalid language code for field '{$fieldName}': {$languageCode}";
                    continue;
                }

                if (!is_string($translatedText)) {
                    $result['valid'] = false;
                    $result['errors'][] = "Translated text for field '{$fieldName}' in language '{$languageCode}' must be a string";
                    continue;
                }

                // Check field-specific validations
                if ($fieldName === 'title' && strlen($translatedText) > 255) {
                    $result['valid'] = false;
                    $result['errors'][] = "Title translation for language '{$languageCode}' exceeds 255 characters";
                }
            }
        }

        return $result;
    }

    /**
     * Process service translations data
     * 
     * @param int $serviceId Service ID
     * @param array $translatedFields Translated fields data
     * @return array Result with success status and processed languages
     */
    private function processServiceTranslationsData(int $serviceId, array $translatedFields): array
    {
        $result = [
            'success' => true,
            'errors' => [],
            'processed_languages' => []
        ];

        try {
            // Initialize translation model
            $translationModel = new \App\Models\TranslatedServiceDetails_model();

            // Process each field
            foreach ($translatedFields as $fieldName => $languageData) {

                foreach ($languageData as $languageCode => $translatedText) {
                    try {


                        // Prepare data for this specific field and language
                        $translationData = [
                            $fieldName => $translatedText
                        ];

                        $saveResult = $translationModel->saveTranslatedDetails(
                            $serviceId,
                            $languageCode,
                            $translationData
                        );

                        if ($saveResult) {
                            $result['processed_languages'][] = [
                                'field' => $fieldName,
                                'language' => $languageCode,
                                'status' => 'saved'
                            ];
                        } else {
                            $result['errors'][] = "Failed to save translation for field '{$fieldName}' in language '{$languageCode}'";
                        }
                    } catch (\Exception $e) {
                        $result['errors'][] = "Exception while saving translation for field '{$fieldName}' in language '{$languageCode}': " . $e->getMessage();
                    }
                }
            }

            // If there are any errors, mark as not fully successful
            if (!empty($result['errors'])) {
                $result['success'] = false;
            }
        } catch (\Exception $e) {
            $result['success'] = false;
            $result['errors'][] = 'Exception in processServiceTranslationsData: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * Transform form data to translated fields structure with clean FAQ handling
     * 
     * @param array $postData The form post data
     * @param string $defaultLanguage The default language code
     * @param int|null $serviceId The service ID for updates
     * @return array Transformed fields for translation
     */
    private function transformFormDataToTranslatedFields(array $postData, string $defaultLanguage, ?int $serviceId = null): array
    {
        $translatedFields = [];
        $translatableFields = ['title', 'description', 'long_description', 'tags', 'faqs'];

        // Check if translated_fields is provided in the expected format
        $translatedFieldsData = $postData['translated_fields'] ?? null;

        // Handle case where translated_fields might still be a JSON string
        if (is_string($translatedFieldsData)) {
            $translatedFieldsData = json_decode($translatedFieldsData, true);
        }

        if (isset($translatedFieldsData) && is_array($translatedFieldsData)) {
            $apiTranslatedFields = $translatedFieldsData;

            // Process each translatable field from the API format
            foreach ($translatableFields as $field) {
                if (isset($apiTranslatedFields[$field]) && is_array($apiTranslatedFields[$field])) {
                    $fieldData = $apiTranslatedFields[$field];

                    if ($field === 'faqs') {
                        // Handle FAQs in the new API format
                        $translatedFields[$field] = $this->processApiFAQData($fieldData);
                    } else {
                        // Handle other fields (title, description, long_description, tags)
                        foreach ($fieldData as $languageCode => $value) {
                            if (!empty($languageCode) && $languageCode !== '0') {
                                if ($field === 'tags') {

                                    // Process tags to comma-separated string
                                    $processedTags = $this->processTagsValue($value);
                                    $translatedFields[$field][$languageCode] = $processedTags;
                                } else {
                                    // For other fields, just trim the value
                                    $translatedFields[$field][$languageCode] = trim($value);
                                }
                            }
                        }
                    }
                }
            }
        } else {
            // No translated_fields provided - return error
            throw new \Exception('translated_fields is required for service creation/update');
        }

        return $translatedFields;
    }

    /**
     * Process API FAQ data structure
     * 
     * @param array $faqData The FAQ data in API format: {"en": {"question": "q1", "answer": "a1"}, "hi": {"question": "q2", "answer": "a2"}}
     * @return array Processed FAQ data for translation fields
     */
    private function processApiFAQData(array $faqData): array
    {
        $translatedFields = [];

        // Process FAQs for each language
        foreach ($faqData as $languageCode => $languageFaqs) {
            // Skip invalid language codes
            if (empty($languageCode) || !is_array($languageFaqs)) {
                continue;
            }

            $processedFaqs = [];

            // Process each FAQ in the language
            foreach ($languageFaqs as $faq) {
                // Check if FAQ has question and answer
                if (is_array($faq) && isset($faq['question']) && isset($faq['answer'])) {
                    $question = trim($faq['question'] ?? '');
                    $answer = trim($faq['answer'] ?? '');

                    // Only add FAQ if either question or answer is not empty
                    if (!empty($question) || !empty($answer)) {
                        $processedFaqs[] = [
                            'question' => $question,
                            'answer' => $answer
                        ];
                    }
                }
            }

            // Store processed FAQs for this language
            if (!empty($processedFaqs)) {
                $translatedFields[$languageCode] = json_encode($processedFaqs, JSON_UNESCAPED_UNICODE);
            } else {
                // Store empty array for languages with no FAQs
                $translatedFields[$languageCode] = json_encode([], JSON_UNESCAPED_UNICODE);
            }
        }

        // Ensure all languages have FAQ entries (even if empty)
        $languages = fetch_details('languages', [], ['code']);
        foreach ($languages as $language) {
            $languageCode = $language['code'];
            if (!isset($translatedFields[$languageCode])) {
                $translatedFields[$languageCode] = json_encode([], JSON_UNESCAPED_UNICODE);
            }
        }

        return $translatedFields;
    }

    /**
     * Process tags value and convert to comma-separated string
     * 
     * @param mixed $tagsValue The tags value from form data
     * @return string Comma-separated string of tag values
     */
    private function processTagsValue($tagsValue): string
    {
        if (empty($tagsValue)) {
            return '';
        }

        // If it's already a string, return it trimmed
        if (is_string($tagsValue)) {
            $result = trim($tagsValue);
            return $result;
        }

        // If it's an array, process it
        if (is_array($tagsValue)) {
            $tagValues = [];

            foreach ($tagsValue as $index => $tag) {
                if (is_string($tag)) {
                    // Check if it's a JSON string
                    $decoded = json_decode($tag, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        // It's a JSON string, process the decoded array
                        foreach ($decoded as $item) {
                            if (is_array($item) && isset($item['value'])) {
                                $tagValues[] = trim($item['value']);
                            }
                        }
                    } else {
                        // Direct string value
                        $tagValues[] = trim($tag);
                    }
                } elseif (is_array($tag)) {
                    // Check if it's a direct object format like {"value":"Test"}
                    if (isset($tag['value'])) {
                        $tagValues[] = trim($tag['value']);
                    } else {
                        // Handle case where $tag is an array like [{"value":"Test"}]
                        foreach ($tag as $tagItem) {
                            if (is_array($tagItem) && isset($tagItem['value'])) {
                                $tagValues[] = trim($tagItem['value']);
                            } elseif (is_string($tagItem)) {
                                $tagValues[] = trim($tagItem);
                            }
                        }
                    }
                }
            }

            // Remove empty values and return as comma-separated string
            $filteredValues = array_filter($tagValues, function ($value) {
                return !empty(trim($value));
            });

            $result = implode(', ', $filteredValues);
            return $result;
        }

        // Fallback: convert to string
        return trim((string)$tagsValue);
    }

    /**
     * Returns tax fields for a service for API response (tax_title, tax_value, tax_percentage, translated_tax_title).
     * Matches the same logic as Service_model so get_services and manage_service return consistent tax data.
     *
     * @param array $serviceRow Single service row (must have tax_id, tax_type, price, discounted_price)
     * @return array Keys: tax_title, tax_value, tax_percentage, translated_tax_title
     */
    private function getServiceTaxFieldsForResponse(array $serviceRow): array
    {
        $tax_id = isset($serviceRow['tax_id']) ? $serviceRow['tax_id'] : 0;
        $tax_title = "";
        $tax_percentage = "";
        $tax_value = "";
        $translated_tax_title = "";

        if (!empty($tax_id) && (int) $tax_id > 0) {
            $tax_data = fetch_details('taxes', ['id' => $tax_id], ['title', 'percentage']);
            if (!empty($tax_data)) {
                $tax_title = $tax_data[0]['title'];
                $tax_percentage = $tax_data[0]['percentage'];
            }
            // Translated tax title for current language (same as get_services / Service_model)
            if (function_exists('get_taxes_with_translated_names')) {
                $translatedTaxList = get_taxes_with_translated_names(['id' => $tax_id], ['id', 'title', 'percentage']);
                $translated_tax_title = (!empty($translatedTaxList) && isset($translatedTaxList[0]['title'])) ? $translatedTaxList[0]['title'] : $tax_title;
            } else {
                $translated_tax_title = $tax_title;
            }

            // Compute tax_value from price/discounted_price and tax_type (same logic as Service_model::calculateTaxValues)
            $price = isset($serviceRow['price']) ? floatval($serviceRow['price']) : 0;
            $discounted_price = isset($serviceRow['discounted_price']) ? $serviceRow['discounted_price'] : "0";
            $tax_type = isset($serviceRow['tax_type']) ? $serviceRow['tax_type'] : 'included';
            $pct = !empty($tax_percentage) ? floatval($tax_percentage) : 0;

            if ($discounted_price == "0" || $discounted_price === 0) {
                $tax_value = ($tax_type === 'excluded' && $pct > 0) ? number_format((intval(($price * $pct / 100))), 2) : "";
            } else {
                $discPrice = floatval($discounted_price);
                $tax_value = ($tax_type === 'excluded' && $pct > 0) ? number_format((intval(($discPrice * $pct / 100))), 2) : "";
            }
        }

        return [
            'tax_title' => $tax_title,
            'tax_value' => $tax_value,
            'tax_percentage' => $tax_percentage,
            'translated_tax_title' => $translated_tax_title,
        ];
    }
}
