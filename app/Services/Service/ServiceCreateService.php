<?php

namespace App\Services\Service;

use App\Models\Language_model;
use App\Models\Partners_model;
use App\Models\Service_model;
use App\Models\Seo_model;
use App\Services\ServicesService;
use App\Services\Service\ServiceSeoService;
use App\Services\Service\ServiceTranslationHelper;
use App\Services\Service\ServiceValidationRules;
use App\Services\utility\FileService;
use App\Services\utility\SlugService;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\Validation\Validation;

/**
 * Owns the service creation workflow (admin panel).
 *
 * Extracted from Admin\Services::add_service(). The controller validates,
 * then delegates to this service. No behaviour change.
 */
class ServiceCreateService
{
    private Service_model   $service;
    private Seo_model       $seoModel;
    private ServicesService $serviceService;
    private SlugService     $slugService;
    private ServiceSeoService $seoService;
    private FileService     $fileService;
    private Validation      $validation;
    private Language_model $language;
    private Partners_model $partner;
    private \CodeIgniter\Database\BaseConnection $db;

    public function __construct()
    {
        helper('ResponceServices');
        $this->service        = new Service_model();
        $this->seoModel       = new Seo_model();
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
     * Create a service from a submitted request.
     *
     * @param int      $userId          Override user_id (pass logged-in partner ID from partner panel; 0 = read from POST 'partner')
     * @param int|null $approvedByAdmin Override approval status (partner panel derives from DB; null = read from POST 'approve_service_value')
     * @return array{error: bool, message?: string|array, service_id?: int, redirect_url?: string}
     */
    public function create(IncomingRequest $request, int $creatorId, int $userId = 0, ?int $approvedByAdmin = null): array
    {
        $price = $request->getPost('price');

        // Resolve default language
        $defaultLanguage = 'en';
        $languages = $this->language->select('id, language, code, is_default')->findAll();
        foreach ($languages as $language) {
            if ($language['is_default'] == 1) {
                $defaultLanguage = $language['code'];
                break;
            }
        }

        $postData = $request->getPost();

        // Validate required default-language translatable fields
        $defaultLanguageErrors = $this->validateDefaultLanguageFields($postData, $defaultLanguage);
        if (!empty($defaultLanguageErrors)) {
            return ['error' => true, 'message' => $defaultLanguageErrors];
        }

        // SEO: if any non-default language has SEO data, default language must also have SEO
        $seoErrors = ServiceSeoService::validateDefaultLanguageSeo($postData, $defaultLanguage);
        if (!empty($seoErrors)) {
            return ['error' => true, 'message' => $seoErrors];
        }

        // Extract the default-language values validated above (needed later)
        [$titleValue, $descriptionValue, $longDescriptionValue, $tagsValue] =
            $this->extractDefaultLanguageValues($postData, $defaultLanguage);

        // Cloning detection
        $cloning          = false;
        $original_service = null;
        $source_service_id = $request->getVar('service_id');
        if ($source_service_id) {
            $original_service = $this->service->select('image, other_images, files')->where('id', $source_service_id)->first();
            if (!empty($original_service)) {
                $cloning = true;
            }
        }

        // CI4 validation
        $this->validation->reset();
        $this->validation->setRules(ServiceValidationRules::forCreate((float) $price, $cloning, $userId > 0));
        if (!$this->validation->withRequest($request)->run()) {
            return ['error' => true, 'message' => $this->validation->getErrors()];
        }

        // Additional image guard when not cloning
        $imageFile = $request->getFile('service_image_selector');
        if (!$cloning && (!$imageFile || !$imageFile->isValid() || $imageFile->getSize() == 0)) {
            return ['error' => true, 'message' => labels(PLEASE_UPLOAD_AN_IMAGE_FILE, 'Please upload an image file')];
        }

        // Upload main image
        $uploadedImagePath = null;
        if ($imageFile && $imageFile->isValid()) {
            $result = $this->fileService->upload($imageFile, 'services');
            if ($result['error']) {
                return ['error' => true, 'message' => $result['message']];
            }
            $uploadedImagePath = $result['path'];
        }

        // Upload other images
        $allFiles             = $request->getFiles();
        $uploadedOtherImages  = $this->processOtherImages($request, $allFiles, $cloning, $original_service);
        if (isset($uploadedOtherImages['error'])) {
            return $uploadedOtherImages;
        }

        // Upload files/documents
        $uploadedFilesDocuments = $this->processFileDocuments($request, $allFiles, $cloning, $original_service);
        if (isset($uploadedFilesDocuments['error'])) {
            return $uploadedFilesDocuments;
        }

        $other_images = !empty($uploadedOtherImages) ? json_encode($uploadedOtherImages) : '[]';
        $files        = !empty($uploadedFilesDocuments) ? json_encode($uploadedFilesDocuments) : '[]';

        // Business rule checks
        $category_id       = $request->getPost('categories');
        $discounted_price  = $request->getPost('discounted_price');
        if ($discounted_price >= $price && $discounted_price == $price) {
            return ['error' => true, 'message' => 'discounted price can not be higher than or equal to the price!'];
        }

        $user_id      = $userId ?: (int) $request->getPost('partner');
        $partner_data = $this->partner->where('partner_id', $user_id)->first();
        if ((int) ($partner_data['type'] ?? -1) === 0 && (int) $request->getVar('members') > 1) {
            return ['error' => true, 'message' => labels('individual_provider_members_must_be_one', 'An individual provider can have only 1 member')];
        }
        if ($request->getVar('members') > $partner_data['number_of_members']) {
            return ['error' => true, 'message' => 'Number Of member could not greater than ' . $partner_data['number_of_members']];
        }

        $check_payment_gateway = get_settings('payment_gateways_settings', true);
        $is_pay_later_allowed  = ($check_payment_gateway['cod_setting'] == 1 && $request->getPost('pay_later') == 'on') ? 1 : 0;
        $is_cancelable         = isset($_POST['is_cancelable']) ? 1 : 0;

        // Resolve main image path
        if ($uploadedImagePath !== null) {
            $image = $uploadedImagePath;
        } elseif ($cloning && !empty($original_service['image'])) {
            $image = $this->fileService->copy('services', $original_service['image']);
        } else {
            $image = '';
        }

        // Default language field values
        $defaultTitle            = $titleValue ?? '';
        $defaultDescription      = $descriptionValue ?? '';
        $defaultLongDescription  = $longDescriptionValue ?? '';
        $defaultTags             = ServiceTranslationHelper::processTags($tagsValue) ?? '';
        $defaultFaqs             = $this->extractDefaultFaqs($postData, $defaultLanguage);

        // Slug
        $resolvedSlug = $this->slugService->resolve(
            currentSlug: null,
            inputSlug:   $request->getPost('service_slug'),
            fallbackName: $defaultTitle,
            table:       'services'
        );

        $serviceData = [
            'user_id'                  => $user_id,
            'category_id'              => $category_id,
            'tax_type'                 => $request->getVar('tax_type'),
            'tax_id'                   => $request->getVar('tax_id'),
            'tax'                      => $request->getPost('tax') ?? 0,
            'price'                    => $price,
            'discounted_price'         => $discounted_price,
            'image'                    => $image,
            'other_images'             => $other_images,
            'number_of_members_required' => $request->getVar('members'),
            'duration'                 => $request->getVar('duration'),
            'rating'                   => 0,
            'number_of_ratings'        => 0,
            'on_site_allowed'          => ($request->getPost('on_site') == 'on') ? 1 : 0,
            'is_pay_later_allowed'     => $is_pay_later_allowed,
            'is_cancelable'            => $is_cancelable,
            'cancelable_till'          => $request->getVar('cancelable_till'),
            'max_quantity_allowed'     => $request->getPost('max_qty'),
            'status'                   => isset($_POST['status']) ? 1 : 0,
            'files'                    => $files,
            'at_store'                 => isset($_POST['at_store']) ? 1 : 0,
            'at_doorstep'              => isset($_POST['at_doorstep']) ? 1 : 0,
            'approved_by_admin'        => $approvedByAdmin ?? ($_POST['approve_service_value'] ?? '1'),
            'slug'                     => $resolvedSlug,
            'title'                    => $defaultTitle,
            'description'              => $defaultDescription,
            'long_description'         => $defaultLongDescription,
            'tags'                     => $defaultTags,
            'faqs'                     => $defaultFaqs,
        ];

        $this->db->transStart();

        if (!$this->service->save($serviceData)) {
            $this->db->transRollback();
            $this->cleanupUploadedFiles($image, $uploadedOtherImages, $uploadedFilesDocuments);
            return ['error' => true, 'message' => labels('error_occured', 'Error Occured')];
        }

        $serviceId        = $this->service->insertID();
        $postData['translated_fields'] = ServiceTranslationHelper::transformFormDataToTranslatedFields($postData, $defaultLanguage, null);

        $translationResult = $this->serviceService->handleServiceCreationWithTranslations($postData, $serviceData, $serviceId, $defaultLanguage);
        if (!$translationResult['success']) {
            log_message('error', 'Failed to save service translations: ' . implode(', ', $translationResult['errors']));
        }

        try {
            $this->seoService->saveForService($serviceId, $request);
        } catch (\Throwable $th) {
            $this->db->transRollback();
            $this->cleanupUploadedFiles($image, $uploadedOtherImages, $uploadedFilesDocuments);
            log_the_responce($th, date('Y-m-d H:i:s') . '--> ServiceCreateService::create() - SEO settings');
            return ['error' => true, 'message' => labels('error_occured', 'Error Occured')];
        }

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            $this->cleanupUploadedFiles($image, $uploadedOtherImages, $uploadedFilesDocuments);
            return ['error' => true, 'message' => labels('error_occured', 'Error Occured')];
        }

        $message = $cloning
            ? labels('service_cloned_successfully', 'Service cloned successfully')
            : labels('data_saved_successfully', 'Data Saved Successfully');

        return ['error' => false, 'message' => $message, 'service_id' => $serviceId, 'redirect_url' => base_url('admin/services')];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function validateDefaultLanguageFields(array $postData, string $defaultLanguage): array
    {
        $errors = [];

        if (isset($postData['title']) && is_array($postData['title'])) {
            if (empty($postData['title'][$defaultLanguage] ?? null)) {
                $errors[] = labels('service_title_in_default_language_is_required', 'Service title is required for default language');
            }
            if (empty($postData['description'][$defaultLanguage] ?? null)) {
                $errors[] = labels('service_description_in_default_language_is_required', 'Service description is required for default language');
            }
            if (empty($postData['long_description'][$defaultLanguage] ?? null)) {
                $errors[] = labels('service_long_description_in_default_language_is_required', 'Service long description is required for default language');
            }
            $tagsValue = ServiceTranslationHelper::processTags($postData['tags'][$defaultLanguage] ?? null);
            if (empty($tagsValue)) {
                $errors[] = labels('service_tags_in_default_language_are_required', 'Service tags are required for default language');
            }
        } else {
            if (empty($postData['title[' . $defaultLanguage . ']'] ?? null)) {
                $errors[] = labels('service_title_in_default_language_is_required', 'Service title is required for default language');
            }
            if (empty($postData['description[' . $defaultLanguage . ']'] ?? null)) {
                $errors[] = labels('service_description_in_default_language_is_required', 'Service description is required for default language');
            }
            if (empty($postData['long_description[' . $defaultLanguage . ']'] ?? null)) {
                $errors[] = labels('service_long_description_in_default_language_is_required', 'Service long description is required for default language');
            }
            $tagsValue = ServiceTranslationHelper::processTags($postData['tags[' . $defaultLanguage . ']'] ?? null);
            if (empty($tagsValue)) {
                $errors[] = labels('service_tags_in_default_language_are_required', 'Service tags are required for default language');
            }
        }

        return $errors;
    }

    private function extractDefaultLanguageValues(array $postData, string $defaultLanguage): array
    {
        if (isset($postData['title']) && is_array($postData['title'])) {
            return [
                $postData['title'][$defaultLanguage] ?? null,
                $postData['description'][$defaultLanguage] ?? null,
                $postData['long_description'][$defaultLanguage] ?? null,
                $postData['tags'][$defaultLanguage] ?? null,
            ];
        }
        return [
            $postData['title[' . $defaultLanguage . ']'] ?? null,
            $postData['description[' . $defaultLanguage . ']'] ?? null,
            $postData['long_description[' . $defaultLanguage . ']'] ?? null,
            $postData['tags[' . $defaultLanguage . ']'] ?? null,
        ];
    }

    private function processOtherImages(IncomingRequest $request, array $multipleFiles, bool $cloning, ?array $originalService): array
    {
        $uploadedOtherImages = [];

        if (isset($multipleFiles['other_service_image_selector'])) {
            $files = $multipleFiles['other_service_image_selector'];
            foreach ($files as $file) {
                if (!empty($files[0]) && $files[0]->getSize() > 0) {
                    if ($file->isValid()) {
                        $result = $this->fileService->upload($file, 'services');
                        if ($result['error']) {
                            return ['error' => true, 'message' => $result['message']];
                        }
                        $uploadedOtherImages[] = $result['path'];
                    }
                } elseif ($cloning && empty($uploadedOtherImages) && !empty($originalService['other_images'])) {
                    $other_images_data = json_decode($originalService['other_images'], true);
                    if (is_array($other_images_data)) {
                        foreach ($other_images_data as $p) {
                            $uploadedOtherImages[] = $this->fileService->copy('services', $p);
                        }
                    }
                    break;
                }
            }
        } elseif ($cloning && !empty($originalService['other_images'])) {
            $other_images_data = json_decode($originalService['other_images'], true);
            if (is_array($other_images_data)) {
                foreach ($other_images_data as $p) {
                    $uploadedOtherImages[] = $this->fileService->copy('services', $p);
                }
            }
        }

        // Handle existing images with remove flags — copy kept images to new files
        $existing_other_images     = $request->getPost('existing_other_images');
        $remove_other_images_flags = $request->getPost('remove_other_images');
        if ($cloning && !empty($existing_other_images) && !empty($remove_other_images_flags)) {
            $filtered = [];
            $base_url = base_url();
            foreach ($existing_other_images as $index => $image_path) {
                if (isset($remove_other_images_flags[$index]) && $remove_other_images_flags[$index] === '1') {
                    continue;
                }
                if (strpos($image_path, $base_url) === 0) {
                    $image_path = substr($image_path, strlen($base_url));
                }
                $filtered[] = $this->fileService->copy('services', $image_path);
            }
            $uploadedOtherImages = $filtered;
        }

        return $uploadedOtherImages;
    }

    private function processFileDocuments(IncomingRequest $request, array $multipleFiles, bool $cloning, ?array $originalService): array
    {
        $uploadedFilesDocuments = [];

        if (isset($multipleFiles['files'])) {
            $files = $multipleFiles['files'];
            if (!empty($files[0]) && $files[0]->getSize() > 0) {
                foreach ($files as $file) {
                    if ($file->isValid()) {
                        $result = $this->fileService->upload($file, 'services');
                        if ($result['error']) {
                            return ['error' => true, 'message' => $result['message']];
                        }
                        $uploadedFilesDocuments[] = $result['path'];
                    }
                }
            } elseif ($cloning && empty($uploadedFilesDocuments) && !empty($originalService['files'])) {
                $files_data = json_decode($originalService['files'], true);
                if (is_array($files_data)) {
                    foreach ($files_data as $p) {
                        $uploadedFilesDocuments[] = $this->fileService->copy('services', $p);
                    }
                }
            }
        } elseif ($cloning && !empty($originalService['files'])) {
            $files_data = json_decode($originalService['files'], true);
            if (is_array($files_data)) {
                foreach ($files_data as $p) {
                    $uploadedFilesDocuments[] = $this->fileService->copy('services', $p);
                }
            }
        }

        $existing_files     = $request->getPost('existing_files');
        $remove_files_flags = $request->getPost('remove_files');
        if ($cloning && !empty($existing_files) && !empty($remove_files_flags)) {
            $filtered = [];
            $base_url = base_url();
            foreach ($existing_files as $index => $file_path) {
                if (isset($remove_files_flags[$index]) && $remove_files_flags[$index] === '1') {
                    continue;
                }
                if (strpos($file_path, $base_url) === 0) {
                    $file_path = substr($file_path, strlen($base_url));
                }
                $filtered[] = $this->fileService->copy('services', $file_path);
            }
            $uploadedFilesDocuments = $filtered;
        }

        return $uploadedFilesDocuments;
    }

    private function cleanupUploadedFiles(string $image, array $otherImages, array $fileDocuments): void
    {
        if (!empty($image)) {
            $this->fileService->delete('services', $image);
        }
        foreach ($otherImages as $path) {
            $this->fileService->delete('services', $path);
        }
        foreach ($fileDocuments as $path) {
            $this->fileService->delete('services', $path);
        }
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
