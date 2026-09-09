<?php

namespace App\Controllers\Admin;

use App\Models\Settings;
use App\Models\Language_model;
use App\Services\utility\FileService;
use Config\Database;

class BecomeProviderSettingsController extends Admin
{
    protected $superadmin;
    private Settings $settingsModel;
    private Language_model $languageModel;
    private FileService $fileService;

    public function __construct()
    {
        parent::__construct();
        $this->superadmin = $this->session->get('email');
        $this->settingsModel = new Settings();
        $this->languageModel = new Language_model();
        $this->fileService = new FileService();
        helper('ResponceServices');
        helper('events');
        helper('function');
        helper('form');
    }

    public function become_provider_setting_page()
    {
        if (!$this->isLoggedIn || !$this->userIsAdmin) {
            return redirect('admin/login');
        }

        $row = $this->settingsModel->where('variable', 'become_provider_page_settings')->first();
        if ($row) {
            $settings1 = json_decode($row['value'] ?? '{}', true);
            if (is_array($settings1)) {
                $this->data = array_merge($this->data, $settings1);
            }
        }

        $db = Database::connect();
        $builder = $db->table('services_ratings sr');
        $builder->select('sr.*,u.image as profile_image,u.username')
            ->join('users u', 'sr.user_id = u.id')
            ->orderBy('id', 'DESC');
        $services_ratings = $builder->get()->getResultArray();
        foreach ($services_ratings as $key => $row) {
            $services_ratings[$key]['profile_image'] = isset($row['profile_image']) ? base_url($row['profile_image']) : 'public/uploads/users/default.png';
        }
        $this->data['services_ratings'] = $services_ratings;
        $this->data['categories_name'] = get_categories_with_translated_names();

        $languages = $this->languageModel->select(['id', 'language', 'is_default', 'code'])->orderBy('id', 'ASC')->findAll();
        $this->data['languages'] = $languages;

        if (!empty($languages)) {
            $sectionKeys = ['hero_section', 'how_it_work_section', 'category_section', 'subscription_section', 'top_providers_section', 'review_section', 'faq_section', 'feature_section'];
            foreach ($sectionKeys as $sectionKey) {
                if (isset($this->data[$sectionKey]) && is_array($this->data[$sectionKey])) {
                    foreach (['short_headline', 'title', 'description'] as $field) {
                        if (isset($this->data[$sectionKey][$field]) && is_array($this->data[$sectionKey][$field])) {
                            $this->data[$sectionKey][$field] = $this->ensureMultiLangFallbacks($this->data[$sectionKey][$field], $languages);
                        }
                    }
                }
            }
            if (isset($this->data['how_it_work_section']['steps']) && is_array($this->data['how_it_work_section']['steps'])) {
                $this->data['how_it_work_section']['steps'] = $this->applyFallbacksToNestedItems($this->data['how_it_work_section']['steps'], $languages, ['title', 'description']);
            }
            if (isset($this->data['feature_section']['features']) && is_array($this->data['feature_section']['features'])) {
                $this->data['feature_section']['features'] = $this->applyFallbacksToNestedItems($this->data['feature_section']['features'], $languages, ['short_headline', 'title', 'description']);
            }
            if (isset($this->data['faq_section']['faqs']) && is_array($this->data['faq_section']['faqs'])) {
                $this->data['faq_section']['faqs'] = $this->applyFallbacksToNestedItems($this->data['faq_section']['faqs'], $languages, ['question', 'answer']);
            }
        }

        setPageInfo($this->data, labels('Become Provider Settings', 'Become Provider Settings') . ' | ' . labels('admin_panel', 'Admin Panel'), 'become_provider_page_settings');
        return view('backend/admin/template', $this->data);
    }

    public function become_provider_setting_page_update()
    {
        if ($this->superadmin == "superadmin@gmail.com") {
            defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 1;
        } else {
            if (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 0) {
                return JsonError(labels(DEMO_MODE_ERROR, DEMO_MODE_ERROR));
            }
        }
        try {
            if (!$this->isLoggedIn || !$this->userIsAdmin) {
                return redirect('admin/login');
            }

            $request = $this->request->getPost();
            $uploadedFiles = $this->request->getFiles();

            $sections = ['hero_section', 'how_it_work_section', 'category_section', 'subscription_section', 'top_providers_section', 'review_section', 'faq_section'];
            $errors = [];

            $languages = $this->languageModel->select(['id', 'language', 'is_default', 'code'])->orderBy('id', 'ASC')->findAll();
            $default_language = '';
            foreach ($languages as $language) {
                if ($language['is_default'] == 1) {
                    $default_language = $language['code'];
                    break;
                }
            }

            foreach ($sections as $section) {
                if (isset($request["{$section}_status"]) && (($request["{$section}_status"] == "on") || $request["{$section}_status"] == "1")) {
                    $short_headline_array = $request["{$section}_short_headline"] ?? [];
                    $title_array = $request["{$section}_title"] ?? [];
                    $description_array = $request["{$section}_description"] ?? [];
                    if (empty($short_headline_array[$default_language])) {
                        $errors["{$section}_short_headline"] = labels("Please enter {$section} short headline for the default language", "Please enter {$section} short headline for the default language");
                    }
                    if (empty($title_array[$default_language])) {
                        $errors["{$section}_title"] = labels("Please enter {$section} title for the default language", "Please enter {$section} title for the default language");
                    }
                    if (empty($description_array[$default_language])) {
                        $errors["{$section}_description"] = labels("Please enter {$section} description for the default language", "Please enter {$section} description for the default language");
                    }
                    if ($section == 'category_section' && empty($request['category_section_category_ids'])) {
                        $errors['category_section_category_ids'] = labels("Please select categories for category section", "Please select categories for category section");
                    }
                }
            }

            if (isset($request['how_it_work_section_status']) && (($request['how_it_work_section_status'] == "on") || $request['how_it_work_section_status'] == "1")) {
                $steps_data = $request['how_it_work_section_steps'] ?? [];
                if (isset($steps_data[$default_language])) {
                    foreach ($steps_data[$default_language] as $index => $step) {
                        if (empty($step['title']) || empty($step['description'])) {
                            $errors["how_it_work_section_steps.$index"] = labels("Please enter how it works section steps title and description for the default language", "Please enter how it works section steps title and description for the default language");
                        }
                    }
                }
            }

            if (isset($request['faq_section_status']) && (($request['faq_section_status'] == "on") || $request['faq_section_status'] == "1")) {
                $faqs_data = $request['faqs'] ?? [];
                if (empty($faqs_data)) {
                    $errors["faq_section_faqs"] = labels("Please add at least one FAQ", "Please add at least one FAQ");
                }
            }

            if (isset($request['feature_section_status']) && (($request['feature_section_status'] == "on") || $request['feature_section_status'] == "1")) {
                $features_data = $request['feature_section_feature'] ?? [];
                if (isset($features_data[$default_language]) && is_array($features_data[$default_language])) {
                    foreach ($features_data[$default_language] as $index => $feature) {
                        if (!is_numeric($index))
                            continue;
                        if (empty($feature['title']) || empty($feature['description'])) {
                            $errors["feature_section_feature.$index"] = labels("Please enter feature title and description for the default language", "Please enter feature title and description for the default language");
                        }
                    }
                }
            }

            if (!empty($errors)) {
                return JsonError(implode('<br>', array_values($errors)));
            }

            $settings = [];

            foreach ($sections as $section) {
                $section_data = [
                    'status' => ((isset($request["{$section}_status"])) && ($request["{$section}_status"] == "on")) ? 1 : 0,
                    'short_headline' => $request["{$section}_short_headline"] ?? [],
                    'title' => $request["{$section}_title"] ?? [],
                    'description' => $request["{$section}_description"] ?? [],
                ];

                if ($section == 'how_it_work_section') {
                    $section_data['steps'] = $request['how_it_work_section_steps'] ?? [];
                } elseif ($section == 'hero_section') {
                    $hero_section_images_selector = [];
                    $existing_images = $request['hero_section_images_existing'] ?? [];
                    if (!empty($existing_images)) {
                        foreach ($existing_images as $existing_image) {
                            if (isset($existing_image['remove']) && $existing_image['remove'] == '1') {
                                if (!empty($existing_image['image'])) {
                                    $this->fileService->delete('become_provider', $existing_image['image']);
                                }
                                continue;
                            }
                            $hero_section_images_selector[] = ['image' => $existing_image['image']];
                        }
                    }
                    if (isset($uploadedFiles['hero_section_images'])) {
                        foreach ($uploadedFiles['hero_section_images'] as $img) {
                            if ($img->isValid()) {
                                $result = $this->fileService->upload($img, 'become_provider');
                                if (!$result['error']) {
                                    $hero_section_images_selector[] = ['image' => basename($result['path'])];
                                } else {
                                    return JsonError($result['message']);
                                }
                            }
                        }
                    }
                    $section_data['images'] = $hero_section_images_selector;
                } elseif ($section == 'review_section') {
                    $rating_ids_raw = $request['new_rating_ids'] ?? '';
                    if (is_string($rating_ids_raw) && !empty($rating_ids_raw)) {
                        $rating_ids = array_filter(array_map('trim', explode(',', $rating_ids_raw)));
                    } elseif (is_array($rating_ids_raw) && isset($rating_ids_raw[0])) {
                        $rating_ids = array_filter(array_map('trim', is_array($rating_ids_raw[0]) ? $rating_ids_raw[0] : explode(',', $rating_ids_raw[0])));
                    } elseif (is_array($rating_ids_raw)) {
                        $rating_ids = array_filter(array_map('trim', $rating_ids_raw));
                    } else {
                        $rating_ids = [];
                    }
                    $section_data['rating_ids'] = !empty($rating_ids) ? implode(',', $rating_ids) : '';
                } elseif ($section == 'category_section') {
                    $category_ids_raw = $request['category_section_category_ids'] ?? [];
                    if (is_array($category_ids_raw) && !empty($category_ids_raw)) {
                        $category_ids = array_filter(array_map('trim', $category_ids_raw));
                        $section_data['category_ids'] = implode(',', $category_ids);
                    } elseif (is_string($category_ids_raw) && !empty($category_ids_raw)) {
                        $category_ids = array_filter(array_map('trim', explode(',', $category_ids_raw)));
                        $section_data['category_ids'] = implode(',', $category_ids);
                    } else {
                        $section_data['category_ids'] = '';
                    }
                } elseif ($section == 'faq_section') {
                    $section_data['faqs'] = $request['faqs'] ?? [];
                }

                $settings[$section] = $section_data;
            }

            if (isset($request['feature_section_status']) && (($request['feature_section_status'] == "on") || $request['feature_section_status'] == "1")) {
                if (isset($request['feature_section_feature']) && $request['feature_section_feature']) {
                    $updatedFeatures = [];
                    $request_obj = \Config\Services::request();
                    $existing_settings = get_settings('become_provider_page_settings', true);
                    $existing_features = $existing_settings['feature_section']['features'] ?? [];
                    $features_by_index = $request['feature_section_feature'];
                    ksort($features_by_index);

                    foreach ($features_by_index as $i => $feature_data) {
                        if (!is_numeric($i))
                            continue;

                        $exist_image = $feature_data['exist_image'] ?? ($request_obj->getPost("feature_section_feature.{$i}.exist_image") ?? 'new');
                        $exist_disk = $feature_data['exist_disk'] ?? ($request_obj->getPost("feature_section_feature.{$i}.exist_disk") ?? '');
                        $remove_image = $feature_data['remove'] ?? ($request_obj->getPost("feature_section_feature.{$i}.remove") ?? '0');
                        $existing_image = $existing_features[$i]['image'] ?? '';

                        $multilang_short_headline = [];
                        $multilang_title = [];
                        $multilang_description = [];
                        foreach ($feature_data as $key => $value) {
                            if (is_array($value) && !in_array($key, ['position', 'image', 'exist_image', 'exist_disk', 'remove'])) {
                                $multilang_short_headline[$key] = trim($value['short_headline'] ?? '');
                                $multilang_title[$key] = trim($value['title'] ?? '');
                                $multilang_description[$key] = trim($value['description'] ?? '');
                            }
                        }

                        $position = $feature_data['position'] ?? ($request_obj->getPost("feature_section_feature.{$i}.position") ?? 'right');
                        $has_content = false;
                        foreach ($multilang_title as $title) {
                            if (!empty(trim($title))) {
                                $has_content = true;
                                break;
                            }
                        }
                        if (!$has_content)
                            continue;

                        $is_new_feature = ($exist_image == 'new' && empty($existing_image));

                        if ($is_new_feature) {
                            $file = $request_obj->getFile("feature_section_feature[{$i}][image]") ?? $request_obj->getFile("feature_section_feature.{$i}.image");
                            if ($file && $file->isValid()) {
                                $result = $this->fileService->upload($file, 'become_provider');
                                if (!$result['error']) {
                                    $updatedFeatures[] = ['short_headline' => $multilang_short_headline, 'title' => $multilang_title, 'description' => $multilang_description, 'position' => $position, 'image' => basename($result['path'])];
                                } else {
                                    return JsonError($result['message']);
                                }
                            } else {
                                $updatedFeatures[] = ['short_headline' => $multilang_short_headline, 'title' => $multilang_title, 'description' => $multilang_description, 'position' => $position, 'image' => ''];
                            }
                        } else {
                            if ($remove_image == '1') {
                                if (!empty($exist_image)) {
                                    $this->fileService->delete('become_provider', $exist_image);
                                }
                                $exist_image = '';
                            }
                            $updatedData = ['short_headline' => $multilang_short_headline, 'title' => $multilang_title, 'description' => $multilang_description, 'position' => $position];
                            $file = $request_obj->getFile("feature_section_feature[{$i}][image]") ?? $request_obj->getFile("feature_section_feature.{$i}.image");
                            if ($file && $file->isValid()) {
                                $result = $this->fileService->upload($file, 'become_provider');
                                if (!$result['error']) {
                                    $updatedData['image'] = basename($result['path']);
                                    if (!empty($exist_image) && $exist_image != 'new') {
                                        $this->fileService->delete('become_provider', $exist_image);
                                    }
                                } else {
                                    return JsonError($result['message']);
                                }
                            } else {
                                $final_image = (!empty($exist_image) && $exist_image != 'new') ? $exist_image : $existing_image;
                                $updatedData['image'] = $final_image;
                            }
                            $updatedFeatures[] = $updatedData;
                        }
                    }

                    $settings['feature_section'] = [
                        'status' => ($request_obj->getPost("feature_section_status") === "on") ? 1 : 0,
                        'features' => $updatedFeatures,
                    ];
                }
            }

            $json_string = json_encode($settings, JSON_UNESCAPED_UNICODE);
            if ($this->settingsModel->upsert('become_provider_page_settings', $json_string)) {
                return JsonSuccess(labels('Become Provider Page settings has been successfuly updated', 'Become Provider Page settings has been successfuly updated'));
            } else {
                return JsonError(labels('Unable to update the Become Provider Page settings', 'Unable to update the Become Provider Page settings'));
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/Admin/BecomeProviderSettingsController.php - become_provider_setting_page_update()');
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }

    public function review_list()
    {
        $db = Database::connect();
        $limit = $_GET['limit'] ?? 10;
        $offset = $_GET['offset'] ?? 0;
        $search = $_GET['search'] ?? '';
        $sort = $_GET['sort'] ?? 'sr.id';
        $order = $_GET['order'] ?? 'DESC';

        $currentLang = get_current_language();
        $defaultLang = get_default_language();

        $totalBuilder = $db->table('services_ratings sr');
        $totalBuilder->join('users u', 'u.id = sr.user_id');
        $totalBuilder->join('services s', 's.id = sr.service_id');
        $totalBuilder->select('COUNT(sr.id) as total');
        if (!empty($search)) {
            $totalBuilder->groupStart()->like('sr.comment', $search)->orLike('u.username', $search)->orLike('s.title', $search)->groupEnd();
        }
        if (!empty($_GET['rating_star_filter'])) {
            $totalBuilder->where('sr.rating', $_GET['rating_star_filter']);
        }
        $total = $totalBuilder->get()->getRowArray()['total'] ?? 0;

        $builder = $db->table('services_ratings sr');
        $builder->join('users u', 'u.id = sr.user_id');
        $builder->join('services s', 's.id = sr.service_id');
        $builder->select('sr.id, sr.rating, sr.comment, sr.created_at as rated_on, u.image as profile_image, u.username as user_name, s.title as service_name, s.id as service_id, s.user_id as partner_id');
        if (!empty($search)) {
            $builder->groupStart()->like('sr.comment', $search)->orLike('u.username', $search)->orLike('s.title', $search)->groupEnd();
        }
        if (!empty($_GET['rating_star_filter'])) {
            $builder->where('sr.rating', $_GET['rating_star_filter']);
        }
        $builder->orderBy($sort, $order);
        $builder->limit((int) $limit, (int) $offset);
        $rating_records = $builder->get()->getResultArray();

        $serviceIds = array_unique(array_column($rating_records, 'service_id'));
        $partnerIds = array_unique(array_column($rating_records, 'partner_id'));
        $translations = get_batch_translated_names($serviceIds, $partnerIds, $currentLang, $defaultLang);

        $rows = [];
        foreach ($rating_records as $row) {
            $serviceName = $row['service_name'];
            if (isset($translations['services'][$row['service_id']]) && !empty($translations['services'][$row['service_id']])) {
                $serviceName = $translations['services'][$row['service_id']];
            }
            $partnerName = '';
            if (isset($translations['partners'][$row['partner_id']]) && !empty($translations['partners'][$row['partner_id']])) {
                $partnerName = $translations['partners'][$row['partner_id']];
            } else {
                $partnerRow = $db->table('users')->select('username')->where('id', $row['partner_id'])->get()->getRowArray();
                $partnerName = $partnerRow['username'] ?? '';
            }
            $rows[] = [
                'id' => $row['id'],
                'comment' => $row['comment'],
                'user_name' => $row['user_name'],
                'service_name' => $serviceName,
                'rated_on' => $row['rated_on'],
                'stars' => '<i class="fa-solid fa-star text-warning"></i> ' . $row['rating'],
                'partner_name' => $partnerName,
            ];
        }

        return $this->response->setJSON(['total' => $total, 'rows' => $rows]);
    }

    private function ensureMultiLangFallbacks(array $translations, array $allLanguages): array
    {
        if (empty($translations))
            return [];

        $defaultLang = 'en';
        foreach ($allLanguages as $lang) {
            if ($lang['is_default'] == 1) {
                $defaultLang = $lang['code'];
                break;
            }
        }

        $fallbackValue = resolve_translation_fallback($translations, ['en']);

        if (empty($translations[$defaultLang]) && !empty($fallbackValue)) {
            $translations[$defaultLang] = $fallbackValue;
        }

        foreach ($allLanguages as $lang) {
            $langCode = $lang['code'];
            if (empty($translations[$langCode]) && !empty($fallbackValue)) {
                $translations[$langCode] = $fallbackValue;
            }
        }

        return $translations;
    }

    private function applyFallbacksToFields(array $data, array $fields, array $languages): array
    {
        foreach ($fields as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = $this->ensureMultiLangFallbacks($data[$field], $languages);
            }
        }
        return $data;
    }

    private function applyFallbacksToNestedItems(array $items, array $languages, array $fields): array
    {
        foreach ($items as $index => $item) {
            if (is_array($item)) {
                $items[$index] = $this->applyFallbacksToFields($item, $fields, $languages);
            }
        }
        return $items;
    }
}
