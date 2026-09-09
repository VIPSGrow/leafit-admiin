<?php

namespace App\Services\Service;

use App\Models\Language_model;
use App\Models\Partners_model;
use App\Models\Service_model;
use App\Services\ServicesService;
use App\Services\Service\ServiceSeoService;
use App\Services\Service\ServiceTranslationHelper;
use App\Services\Service\ServiceValidationRules;
use App\Services\utility\FileService;
use App\Services\utility\SlugService;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\Validation\Validation;

/**
 * Owns the service update workflow (admin panel).
 *
 * Extracted from Admin\Services::update_service(). No behaviour change.
 */
class ServiceUpdateService
{
    private Service_model   $service;
    private ServicesService $serviceService;
    private SlugService     $slugService;
    private ServiceSeoService $seoService;
    private FileService     $fileService;
    private Validation      $validation;
    private Language_model  $language;
    private Partners_model  $partner;
    private \CodeIgniter\Database\BaseConnection $db;

    public function __construct()
    {
        helper('ResponceServices');
        $this->service        = new Service_model();
        $this->serviceService = new ServicesService();
        $this->slugService    = new SlugService();
        $this->seoService     = new ServiceSeoService();
        $this->fileService    = new FileService();
        $this->language       = new Language_model();
        $this->partner        = new Partners_model();
        $this->validation     = \Config\Services::validation();
        $this->db             = \Config\Database::connect();
    }

    /**
     * Update a service from a submitted request.
     *
     * @param int      $userId          Override user_id (pass logged-in partner ID from partner panel; 0 = read from POST 'partner')
     * @param int|null $approvedByAdmin Override approval status (partner panel derives from DB; null = read from POST 'approve_service_value')
     * @return array{error: bool, message?: string|array, redirect_url?: string}
     */
    public function update(IncomingRequest $request, int $creatorId, int $userId = 0, ?int $approvedByAdmin = null): array
    {
        $price = $request->getPost('price');

        // Resolve default language
        $defaultLanguage = 'en';
        $languages = $this->language->select('id, language,code,is_default')->findAll();
        foreach ($languages as $language) {
            if ($language['is_default'] == 1) {
                $defaultLanguage = $language['code'];
                break;
            }
        }

        $postData = $request->getPost();

        // Validate required default-language translatable fields
        [$defaultLanguageErrors, $titleValue, $descriptionValue, $longDescriptionValue, $tagsValue] =
            $this->validateAndExtractDefaultLanguageFields($postData, $defaultLanguage);

        if (!empty($defaultLanguageErrors)) {
            return ['error' => true, 'message' => $defaultLanguageErrors];
        }

        // SEO: if any non-default language has SEO data, default language must also have SEO
        $seoErrors = ServiceSeoService::validateDefaultLanguageSeo($postData, $defaultLanguage);
        if (!empty($seoErrors)) {
            return ['error' => true, 'message' => $seoErrors];
        }

        // CI4 validation
        $this->validation->reset();
        $this->validation->setRules(ServiceValidationRules::forUpdate((float) $price, $userId > 0));
        if (!$this->validation->withRequest($request)->run()) {
            return ['error' => true, 'message' => $this->validation->getErrors()];
        }

        $Service_id                 = $request->getPost('service_id');
        $old_images_and_documents   = $this->service->where('id', $Service_id)->first();

        // Default-language tags (post-validation processing)
        $defaultTags = $request->getPost('tags[' . $defaultLanguage . ']') ?: $request->getPost('tags');
        if (is_array($defaultTags)) {
            $tags = [];
            foreach ($defaultTags as $tag) {
                if (is_string($tag)) {
                    $tags[] = trim($tag);
                } elseif (is_array($tag) && isset($tag['value'])) {
                    $tags[] = trim($tag['value']);
                }
            }
        } else {
            $tags = array_filter(array_map('trim', explode(',', $defaultTags)));
        }
        if (empty($tags)) {
            return ['error' => true, 'message' => labels('service_tags_in_default_language_are_required','Service tags in default language are required!')];
        }

        // Upload main image (optional on update)
        $imageFile  = $request->getFile('service_image_selector_edit');
        $image_name = $old_images_and_documents['image'];
        if ($imageFile && $imageFile->isValid()) {
            $result = $this->fileService->replace($image_name, $imageFile, 'services');
            if ($result['error']) {
                return ['error' => true, 'message' => $result['message']];
            }
            $image_name = $result['path'];
        }

        // Other images
        $other_images_result = $this->processOtherImagesForUpdate($request, $old_images_and_documents);
        if (isset($other_images_result['error'])) {
            return $other_images_result;
        }
        $other_images = $other_images_result['other_images'];

        // Files/documents
        $files_result = $this->processFilesForUpdate($request, $old_images_and_documents);
        if (isset($files_result['error'])) {
            return $files_result;
        }
        $files = $files_result['files'];

        // Business rule checks
        $discounted_price = $request->getPost('discounted_price');
        if ($discounted_price >= $price) {
            return ['error' => true, 'message' => labels('discounted_price_cannot_be_higher_than_or_equal_to_the_price', 'Discounted price cannot be higher than or equal to the price')];
        }

        $is_cancelable = (isset($_POST['is_cancelable']) && $_POST['is_cancelable'] == 'on') ? '1' : '0';
        if ($is_cancelable == '1' && $request->getVar('cancelable_till') == '') {
            return ['error' => true, 'message' => labels('please_add_minutes', 'Please add minutes')];
        }

        $check_payment_gateway = get_settings('payment_gateways_settings', true);
        $is_pay_later_allowed  = ($check_payment_gateway['cod_setting'] == 1 && $request->getPost('pay_later') == 'on') ? 1 : 0;

        // Default language field values
        $defaultTitle           = $titleValue ?? '';
        $defaultDescription     = $descriptionValue ?? '';
        $defaultLongDescription = $longDescriptionValue ?? '';
        $defaultTagsStr         = ServiceTranslationHelper::processTags($tagsValue) ?? '';
        $defaultFaqs            = $this->extractDefaultFaqs($postData, $defaultLanguage);

        // Slug
        $existingService = $old_images_and_documents[0] ?? [];
        $existingSlug    = $existingService['slug'] ?? '';
        $resolvedSlug    = $this->slugService->resolve(
            currentSlug:  $existingSlug,
            inputSlug:    trim($request->getPost('service_slug') ?? ''),
            fallbackName: $defaultTitle,
            table:        'services',
            excludeId:    $Service_id
        );
        if ($this->slugService->isLegacySlug($existingSlug)) {
            $resolvedSlug = $this->slugService->generate($defaultTitle, $defaultTitle, 'services', $Service_id);
        }

        $resolvedUserId = $userId ?: (int) $request->getPost('partner');

        $partner_data = $this->partner->where('partner_id', $resolvedUserId)->first();
        if (!empty($partner_data)) {
            if ((int) ($partner_data['type'] ?? -1) === 0 && (int) $request->getPost('members') > 1) {
                return ['error' => true, 'message' => labels('individual_provider_members_must_be_one', 'An individual provider can have only 1 member')];
            }
            if ((int) $request->getPost('members') > (int) $partner_data['number_of_members']) {
                return ['error' => true, 'message' => labels('number_of_members_cannot_be_greater_than', 'Number Of member could not greater than') . ' ' . $partner_data['number_of_members']];
            }
        }

        $resolvedApproval = $approvedByAdmin ?? $request->getPost('approve_service_value');

        $serviceData = [
            'user_id'                  => $resolvedUserId,
            'category_id'              => $_POST['categories'],
            'tax_type'                 => $request->getPost('tax_type'),
            'tax_id'                   => $request->getPost('tax_id'),
            'tax'                      => $request->getPost('tax') ?? 0,
            'price'                    => $price,
            'discounted_price'         => $discounted_price,
            'image'                    => $image_name,
            'number_of_members_required' => $request->getPost('members'),
            'duration'                 => $request->getPost('duration'),
            'max_quantity_allowed'     => $request->getPost('max_qty'),
            'status'                   => ($request->getPost('status') == 'on') ? 1 : 0,
            'other_images'             => $other_images,
            'files'                    => $files,
            'is_pay_later_allowed'     => $is_pay_later_allowed,
            'is_cancelable'            => $is_cancelable,
            'cancelable_till'          => ($is_cancelable == '1') ? $request->getVar('cancelable_till') : '',
            'at_store'                 => ($request->getPost('at_store') == 'on') ? 1 : 0,
            'at_doorstep'              => ($request->getPost('at_doorstep') == 'on') ? 1 : 0,
            'approved_by_admin'        => $resolvedApproval,
            'title'                    => $defaultTitle,
            'description'              => $defaultDescription,
            'long_description'         => $defaultLongDescription,
            'tags'                     => $defaultTagsStr,
            'faqs'                     => $defaultFaqs,
        ];

        if ($resolvedSlug !== null) {
            $serviceData['slug'] = $resolvedSlug;
        }

        $this->db->transStart();

        if (!$this->service->update($Service_id, $serviceData)) {
            $this->db->transRollback();
            return ['error' => true, 'message' => 'Service cannot be saved!'];
        }

        // Translations
        $postData = $request->getPost();
        $existingTranslationsResult = $this->serviceService->getServiceWithTranslations($Service_id);
        $existingTranslations       = $existingTranslationsResult['translated_data'] ?? [];

        $translatedFields                = ServiceTranslationHelper::transformFormDataToTranslatedFields($postData, $defaultLanguage, $Service_id, $existingTranslations);
        $postData['translated_fields']   = $translatedFields;

        $this->serviceService->handleServiceUpdateWithTranslations($postData, $serviceData, $Service_id, $defaultLanguage);

        // SEO
        try {
            $this->seoService->saveForService($Service_id, $request);
        } catch (\Throwable $th) {
            $this->db->transRollback();
            log_the_responce($th, date('Y-m-d H:i:s') . '--> ServiceUpdateService::update() - SEO settings');
            return ['error' => true, 'message' => 'Failed to save SEO settings: ' . $th->getMessage()];
        }

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            return ['error' => true, 'message' => labels('error_occured', 'Error Occurred')];
        }

        return ['error' => false, 'message' => labels('data_updated_successfully', 'Data updated successfully!'), 'redirect_url' => base_url('admin/services')];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function validateAndExtractDefaultLanguageFields(array $postData, string $defaultLanguage): array
    {
        $errors          = [];
        $titleValue      = null;
        $descriptionValue      = null;
        $longDescriptionValue  = null;
        $tagsValue       = null;

        if (isset($postData['title']) && is_array($postData['title'])) {
            $titleValue             = $postData['title'][$defaultLanguage] ?? null;
            $descriptionValue       = $postData['description'][$defaultLanguage] ?? null;
            $longDescriptionValue   = $postData['long_description'][$defaultLanguage] ?? null;
            $tagsValue              = $postData['tags'][$defaultLanguage] ?? null;

            if (empty($titleValue))             { $errors[] = labels(SERVICE_TITLE_IN_DEFAULT_LANGUAGE_IS_REQUIRED, 'Service title in default language is required!'); }
            if (empty($descriptionValue))       { $errors[] = labels(SERVICE_DESCRIPTION_IN_DEFAULT_LANGUAGE_IS_REQUIRED, 'Service description in default language is required!'); }
            if (empty($longDescriptionValue))   { $errors[] = labels(SERVICE_LONG_DESCRIPTION_IN_DEFAULT_LANGUAGE_IS_REQUIRED, 'Service long description in default language is required!'); }
            if (empty($tagsValue))              { $errors[] = labels(SERVICE_TAGS_IN_DEFAULT_LANGUAGE_ARE_REQUIRED, 'Service tags in default language are required!'); }
        } else {
            $titleValue             = $postData['title[' . $defaultLanguage . ']'] ?? null;
            $descriptionValue       = $postData['description[' . $defaultLanguage . ']'] ?? null;
            $longDescriptionValue   = $postData['long_description[' . $defaultLanguage . ']'] ?? null;
            $tagsValue              = $postData['tags[' . $defaultLanguage . ']'] ?? null;

            if (empty($titleValue))             { $errors[] = labels(SERVICE_TITLE_IN_DEFAULT_LANGUAGE_IS_REQUIRED, 'Service title in default language is required!'); }
            if (empty($descriptionValue))       { $errors[] = labels(SERVICE_DESCRIPTION_IN_DEFAULT_LANGUAGE_IS_REQUIRED, 'Service description in default language is required!'); }
            if (empty($longDescriptionValue))   { $errors[] = labels(SERVICE_LONG_DESCRIPTION_IN_DEFAULT_LANGUAGE_IS_REQUIRED, 'Service long description in default language is required!'); }
            if (empty($tagsValue))              { $errors[] = labels(SERVICE_TAGS_IN_DEFAULT_LANGUAGE_ARE_REQUIRED, 'Service tags in default language are required!'); }
        }

        return [$errors, $titleValue, $descriptionValue, $longDescriptionValue, $tagsValue];
    }

    private function processOtherImagesForUpdate(IncomingRequest $request, array $old_data): array
    {
        $updated_images = [];
        $existing_images = $request->getPost('existing_other_images');
        $remove_flags    = $request->getPost('remove_other_images');

        if (!empty($existing_images)) {
            $base_url = base_url();
            foreach ($existing_images as $key => $image_path) {
                if (strpos($image_path, $base_url) === 0) {
                    $existing_images[$key] = substr($image_path, strlen($base_url));
                }
            }
            foreach ($existing_images as $index => $image) {
                if (isset($remove_flags[$index]) && $remove_flags[$index] === '1') {
                    $this->fileService->delete('services', $image);
                } else {
                    $updated_images[] = $image;
                }
            }
        } elseif (!empty($old_data[0]['other_images']) && !$request->getPost('remove_other_images')) {
            $old_other_images = json_decode($old_data[0]['other_images'], true);
            if (!empty($old_other_images) && is_array($old_other_images)) {
                $updated_images = $old_other_images;
            }
        }

        $multipleFiles = $request->getFiles();
        if (isset($multipleFiles['other_service_image_selector_edit'])) {
            foreach ($multipleFiles['other_service_image_selector_edit'] as $file) {
                if ($file->isValid()) {
                    $result = $this->fileService->upload($file, 'services');
                    if ($result['error']) {
                        return ['error' => true, 'message' => $result['message']];
                    }
                    $updated_images[] = $result['path'];
                }
            }
        }

        return ['other_images' => !empty($updated_images) ? json_encode($updated_images) : '[]'];
    }

    private function processFilesForUpdate(IncomingRequest $request, array $old_data): array
    {
        $updated_files  = [];
        $existing_files = $request->getPost('existing_files');
        $remove_files_flags = $request->getPost('remove_files');

        if (!empty($existing_files)) {
            $base_url = base_url();
            foreach ($existing_files as $key => $file_path) {
                if (strpos($file_path, $base_url) === 0) {
                    $existing_files[$key] = substr($file_path, strlen($base_url));
                }
            }
            foreach ($existing_files as $index => $file) {
                if (isset($remove_files_flags[$index]) && $remove_files_flags[$index] === '1') {
                    $this->fileService->delete('services', $file);
                } else {
                    $updated_files[] = $file;
                }
            }
        } elseif (!empty($old_data[0]['files']) && !$request->getPost('remove_files')) {
            $old_files = json_decode($old_data[0]['files'], true);
            if (!empty($old_files) && is_array($old_files)) {
                $updated_files = $old_files;
            }
        }

        $multipleFiles = $request->getFiles();
        if (isset($multipleFiles['files_edit'])) {
            foreach ($multipleFiles['files_edit'] as $file) {
                if ($file->isValid()) {
                    $result = $this->fileService->upload($file, 'services');
                    if ($result['error']) {
                        return ['error' => true, 'message' => $result['message']];
                    }
                    $updated_files[] = $result['path'];
                }
            }
        }

        return ['files' => !empty($updated_files) ? json_encode($updated_files) : '[]'];
    }

    private function extractDefaultFaqs(array $postData, string $defaultLanguage): string
    {
        if (!isset($postData['faqs'])) {
            return '';
        }
        if (is_string($postData['faqs'])) {
            $defaultFaqsData = json_decode(htmlspecialchars_decode($postData['faqs'] ?? ''), true);
            if (is_array($defaultFaqsData) && !empty($defaultFaqsData)) {
                $languageFaqs = $defaultFaqsData[$defaultLanguage] ?? [];
                if (!empty($languageFaqs)) {
                    return json_encode($languageFaqs, JSON_UNESCAPED_UNICODE);
                }
            }
        } elseif (is_array($postData['faqs'])) {
            $defaultFaqsData = $postData['faqs'][$defaultLanguage] ?? [];
            if (!empty($defaultFaqsData)) {
                return json_encode($defaultFaqsData, JSON_UNESCAPED_UNICODE);
            }
        }
        return '';
    }
}
