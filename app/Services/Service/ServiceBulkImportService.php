<?php

namespace App\Services\Service;

use App\Models\Language_model;
use App\Models\Service_model;
use App\Models\TranslatedServiceDetails_model;
use App\Services\ServicesService;
use App\Services\Service\ServiceSeoService;
use App\Services\Service\ServiceTranslationHelper;
use App\Services\utility\SlugService;
use CodeIgniter\HTTP\IncomingRequest;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Owns the bulk service import/export workflow.
 *
 * Extracted from Admin\Services::bulk_import_service_upload(),
 * downloadSampleForInsert(), downloadSampleForUpdate(). No behaviour change.
 */
class ServiceBulkImportService
{
    private ServicesService $serviceService;
    private ServiceSeoService $seoService;
    private SlugService $slugService;

    public function __construct()
    {
        helper('ResponceServices');
        $this->serviceService = new ServicesService();
        $this->seoService     = new ServiceSeoService();
        $this->slugService    = new SlugService();
    }

    /**
     * Process a bulk upload file (insert or update depending on whether ID column exists).
     *
     * @return array{error: bool, message: string}
     */
    public function upload(IncomingRequest $request, int $creatorId): array
    {
        $file     = $request->getFile('file');
        $filePath = FCPATH . 'public/uploads/service_bulk_upload/';
        if (!is_dir($filePath)) {
            if (!mkdir($filePath, 0775, true)) {
                return ['error' => true, 'message' => labels(FAILED_TO_CREATE_FOLDERS, 'Failed to create folders')];
            }
        }

        $newName  = $file->getRandomName();
        $file->move($filePath, $newName);
        $fullPath    = $filePath . $newName;
        $spreadsheet = IOFactory::load($fullPath);
        $sheet       = $spreadsheet->getActiveSheet();

        $headerRowData = $sheet->rangeToArray('A1:' . $sheet->getHighestColumn() . '1', null, true, false, false);
        $headers       = array_values(array_map(fn($h) => trim($h, ' "'), $headerRowData[0]));

        $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], '', '0', 'id', 'ASC');
        $defaultLanguage = 'en';
        foreach ($languages as $language) {
            if ($language['is_default'] == 1) {
                $defaultLanguage = $language['code'];
                break;
            }
        }

        $headerRow    = $sheet->getRowIterator(1)->current();
        $cellIterator = $headerRow->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);

        [$languageHeaders, $other_image_Headers, $FilesHeaders] = $this->parseHeaders($cellIterator);

        if (!in_array('ID', $headers)) {
            return $this->processInsert($request, $sheet, $headers, $languages, $defaultLanguage, $languageHeaders, $other_image_Headers, $FilesHeaders, $creatorId);
        } else {
            return $this->processUpdate($request, $sheet, $headers, $languages, $defaultLanguage, $languageHeaders, $other_image_Headers, $FilesHeaders, $creatorId);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Insert flow
    // ──────────────────────────────────────────────────────────────────────────

    private function processInsert(
        IncomingRequest $request,
        $sheet,
        array $headers,
        array $languages,
        string $defaultLanguage,
        array $languageHeaders,
        array $other_image_Headers,
        array $FilesHeaders,
        int $creatorId
    ): array {
        if (!is_permitted($creatorId, 'create', 'services')) {
            return ['error' => true, 'message' => labels('NO_PERMISSION_TO_TAKE_THIS_ACTION', 'Sorry! You are not permitted to create services')];
        }

        foreach (['title', 'description'] as $field) {
            if (!isset($languageHeaders[$field][$defaultLanguage])) {
                return ['error' => true, 'message' => labels('MULTILANGUAGE_MISSING_REQUIRED_FIELD', ucfirst($field) . " is required for default language ($defaultLanguage) in CSV headers")];
            }
        }

        $data = array_values(array_filter($sheet->toArray(), fn($row) => !empty(array_filter($row))));
        array_shift($data);
        $data = array_values($data);

        $services            = [];
        $serviceTranslations = [];
        $serviceSeoTranslations = [];

        foreach ($data as $rowIndex => $row) {
            $row = array_values($row);

            $provider = fetch_details('partner_details', ['partner_id' => $row[0]]);
            if (empty($provider)) {
                return ['error' => true, 'message' => labels('Provider ID', 'Provider ID') . " :: " . $row[0] . " " . labels('not found', 'not found')];
            }
            $category = fetch_details('categories', ['id' => $row[1]]);
            if (empty($category)) {
                return ['error' => true, 'message' => labels('Category ID', 'Category ID') . " :: " . $row[1] . " " . labels('not found', 'not found')];
            }
            $tax = fetch_details('taxes', ['id' => $row[6]]);
            if (empty($tax)) {
                return ['error' => true, 'message' => labels('Tax ID', 'Tax ID') . " :: " . $row[6] . " " . labels('not found', 'not found')];
            }

            $translatedFields = ServiceTranslationHelper::extractTranslatedFieldsFromBulkRow($row, $languageHeaders, $defaultLanguage);
            $seoTranslations  = ServiceTranslationHelper::extractSeoTranslationsFromRow($row, $headers, $languages);
            $serviceSeoTranslations[$rowIndex] = $seoTranslations;

            if (empty($translatedFields['title'][$defaultLanguage])) {
                return ['error' => true, 'message' => labels('MULTILANGUAGE_MISSING_TITLE', "Title is required for default language ($defaultLanguage) at row " . ($rowIndex + 2))];
            }
            if (empty($translatedFields['description'][$defaultLanguage])) {
                return ['error' => true, 'message' => labels('MULTILANGUAGE_MISSING_DESCRIPTION', "Description is required for default language ($defaultLanguage) at row " . ($rowIndex + 2))];
            }

            $other_images = $this->processOtherImagesFromRow($row, $other_image_Headers);
            $files        = $this->processFilesFromRow($row, $FilesHeaders);
            $image        = !empty($row[16]) ? copy_image($row[16], '/public/uploads/services/') : '';

            $defaultTitle = $translatedFields['title'][$defaultLanguage] ?? '';
            $resolvedSlug = $this->slugService->generate(raw: $defaultTitle, fallback: $defaultTitle, table: 'services');

            $serviceData = [
                'user_id'                  => $row[0],
                'category_id'              => $row[1],
                'title'                    => $defaultTitle,
                'description'              => $translatedFields['description'][$defaultLanguage] ?? '',
                'long_description'         => $translatedFields['long_description'][$defaultLanguage] ?? '',
                'tags'                     => $translatedFields['tags'][$defaultLanguage] ?? '',
                'faqs'                     => $translatedFields['faqs'][$defaultLanguage] ?? json_encode([], JSON_UNESCAPED_UNICODE),
                'slug'                     => $resolvedSlug,
                'duration'                 => $row[2],
                'number_of_members_required' => $row[3],
                'max_quantity_allowed'     => $row[4],
                'tax_type'                 => $row[5],
                'tax_id'                   => $row[6],
                'price'                    => $row[7],
                'discounted_price'         => $row[8],
                'is_cancelable'            => $row[9],
                'cancelable_till'          => ($row[9] == 1) ? $row[10] : '',
                'is_pay_later_allowed'     => $row[11],
                'at_store'                 => $row[12],
                'at_doorstep'              => $row[13],
                'status'                   => $row[14],
                'approved_by_admin'        => ($provider[0]['need_approval_for_the_service'] == '1') ? '1' : '0',
                'other_images'             => json_encode($other_images),
                'image'                    => $image,
                'files'                    => json_encode($files),
            ];

            $services[]            = $serviceData;
            $serviceTranslations[] = $translatedFields;
        }

        return $this->runInsertTransaction($services, $serviceTranslations, $serviceSeoTranslations, $languages, 'insert');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Update flow
    // ──────────────────────────────────────────────────────────────────────────

    private function processUpdate(
        IncomingRequest $request,
        $sheet,
        array $headers,
        array $languages,
        string $defaultLanguage,
        array $languageHeaders,
        array $other_image_Headers,
        array $FilesHeaders,
        int $creatorId
    ): array {
        if (!is_permitted($creatorId, 'update', 'services')) {
            return ['error' => true, 'message' => labels('NO_PERMISSION_TO_TAKE_THIS_ACTION', 'Sorry! You are not permitted to update services')];
        }

        foreach (['title', 'description'] as $field) {
            if (!isset($languageHeaders[$field][$defaultLanguage])) {
                return ['error' => true, 'message' => labels('MULTILANGUAGE_MISSING_REQUIRED_FIELD', ucfirst($field) . " is required for default language ($defaultLanguage) in CSV headers")];
            }
        }

        $data = array_values(array_filter($sheet->toArray(), fn($row) => !empty(array_filter($row))));
        array_shift($data);
        $data = array_values($data);

        $services            = [];
        $serviceTranslations = [];
        $serviceSeoTranslations = [];

        foreach ($data as $rowIndex => $row) {
            $row = array_values($row);

            $fetch_service_data = fetch_details('services', ['id' => $row[0]], ['image', 'other_images', 'files']);
            if (empty($fetch_service_data)) {
                return ['error' => true, 'message' => labels('Service ID', 'Service ID') . " :: " . $row[0] . " " . labels('not found', 'not found')];
            }

            $old_other_images = [];
            if (!empty($fetch_service_data[0]['other_images'])) {
                $decoded = json_decode($fetch_service_data[0]['other_images'], true);
                $old_other_images = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [];
            }

            $old_files = [];
            if (!empty($fetch_service_data[0]['files'])) {
                $decoded   = json_decode($fetch_service_data[0]['files'], true);
                $old_files = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [];
            }

            $provider = fetch_details('partner_details', ['partner_id' => $row[1]]);
            if (empty($provider)) {
                return ['error' => true, 'message' => labels('Provider ID', 'Provider ID') . " :: " . $row[1] . " " . labels('not found', 'not found')];
            }
            $category = fetch_details('categories', ['id' => $row[2]]);
            if (empty($category)) {
                return ['error' => true, 'message' => labels('Category ID', 'Category ID') . " :: " . $row[2] . " " . labels('not found', 'not found')];
            }
            $tax = fetch_details('taxes', ['id' => $row[7]]);
            if (empty($tax)) {
                return ['error' => true, 'message' => labels('Tax ID', 'Tax ID') . " :: " . $row[7] . " " . labels('not found', 'not found')];
            }

            $translatedFields = ServiceTranslationHelper::extractTranslatedFieldsFromBulkRow($row, $languageHeaders, $defaultLanguage);
            $seoTranslations  = ServiceTranslationHelper::extractSeoTranslationsFromRow($row, $headers, $languages);
            $serviceSeoTranslations[$rowIndex] = $seoTranslations;

            if (empty($translatedFields['title'][$defaultLanguage])) {
                return ['error' => true, 'message' => labels('MULTILANGUAGE_MISSING_TITLE', "Title is required for default language ($defaultLanguage) at row " . ($rowIndex + 2))];
            }
            if (empty($translatedFields['description'][$defaultLanguage])) {
                return ['error' => true, 'message' => labels('MULTILANGUAGE_MISSING_DESCRIPTION', "Description is required for default language ($defaultLanguage) at row " . ($rowIndex + 2))];
            }

            $other_images = $this->processOtherImagesFromRowUpdate($row, $other_image_Headers, $old_other_images);
            $files        = $this->processFilesFromRowUpdate($row, $FilesHeaders, $old_files);

            $image    = !empty($row[17]) ? copy_image($row[17], '/public/uploads/services/') : '';
            if (empty($image)) {
                $image = $fetch_service_data[0]['image'];
            }

            $serviceId    = $row[0];
            $defaultTitle = $translatedFields['title'][$defaultLanguage] ?? '';
            $existingSlugRow = fetch_details('services', ['id' => $serviceId], ['slug']);
            $currentSlug     = $existingSlugRow[0]['slug'] ?? null;
            $baseName        = !empty($defaultTitle) ? $defaultTitle : '';

            $slug = $this->slugService->resolve($currentSlug, null, $baseName, 'services', $serviceId);
            if ($this->slugService->isLegacySlug($currentSlug)) {
                $slug = $this->slugService->generate($baseName, $baseName, 'services', $serviceId);
            }

            $serviceData = [
                'id'                       => $serviceId,
                'user_id'                  => $row[1],
                'category_id'              => $row[2],
                'title'                    => $defaultTitle,
                'description'              => $translatedFields['description'][$defaultLanguage] ?? '',
                'long_description'         => $translatedFields['long_description'][$defaultLanguage] ?? '',
                'tags'                     => $translatedFields['tags'][$defaultLanguage] ?? '',
                'faqs'                     => $translatedFields['faqs'][$defaultLanguage] ?? json_encode([], JSON_UNESCAPED_UNICODE),
                'slug'                     => $slug,
                'duration'                 => $row[3],
                'number_of_members_required' => $row[4],
                'max_quantity_allowed'     => $row[5],
                'tax_type'                 => $row[6],
                'tax_id'                   => $row[7],
                'price'                    => $row[8],
                'discounted_price'         => $row[9],
                'is_cancelable'            => $row[10],
                'cancelable_till'          => ($row[10] == 1) ? $row[11] : '',
                'is_pay_later_allowed'     => $row[12],
                'at_store'                 => $row[13],
                'at_doorstep'              => $row[14],
                'status'                   => $row[15],
                'approved_by_admin'        => ($provider[0]['need_approval_for_the_service'] == '1') ? '0' : '1',
                'other_images'             => json_encode($other_images),
                'image'                    => $image,
                'files'                    => json_encode($files),
            ];

            $services[]            = $serviceData;
            $serviceTranslations[] = $translatedFields;
        }

        return $this->runInsertTransaction($services, $serviceTranslations, $serviceSeoTranslations, $languages, 'update');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Shared transaction runner
    // ──────────────────────────────────────────────────────────────────────────

    private function runInsertTransaction(
        array  $services,
        array  $serviceTranslations,
        array  $serviceSeoTranslations,
        array  $languages,
        string $mode
    ): array {
        $serviceModel = new Service_model();
        $db           = \Config\Database::connect();
        $db->transStart();

        try {
            foreach ($services as $index => $service) {
                if ($mode === 'update') {
                    $serviceId = $service['id'];
                    unset($service['id']);
                    if (!$serviceModel->update($serviceId, $service)) {
                        throw new \Exception("Failed to update service ID $serviceId at row " . ($index + 2));
                    }
                } else {
                    if (!$serviceModel->insert($service)) {
                        throw new \Exception("Failed to add service at row " . ($index + 2));
                    }
                    $serviceId = $serviceModel->insertID();
                }

                $translationData = $serviceTranslations[$index];
                $translationData = $this->normalizeFaqsForService($translationData);

                $translationResult = $this->serviceService->saveTranslatedFields($serviceId, $translationData);
                if (!$translationResult['success']) {
                    throw new \Exception("Failed to save translations for service ID $serviceId: " . implode(', ', $translationResult['errors']));
                }

                if (isset($serviceSeoTranslations[$index])) {
                    $seoResult = $this->seoService->saveForBulk($serviceId, $serviceSeoTranslations[$index], $languages);
                    if (!$seoResult) {
                        log_message('error', "Row {$index}: Failed to save SEO settings for service {$serviceId}");
                    }
                }
            }

            $db->transComplete();
            if ($db->transStatus() === false) {
                throw new \Exception('Transaction failed');
            }

            $message = ($mode === 'update') ? 'Services updated successfully' : 'Services added successfully';
            return ['error' => false, 'message' => $message];
        } catch (\Exception $e) {
            $db->transRollback();
            log_message('error', 'Bulk ' . $mode . ' service error: ' . $e->getMessage());
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function parseHeaders(\PhpOffice\PhpSpreadsheet\Worksheet\RowCellIterator $cellIterator): array
    {
        $languageHeaders     = [];
        $other_image_Headers = [];
        $FilesHeaders        = [];
        $columnIndex = 0;

        foreach ($cellIterator as $cell) {
            $header = $cell->getValue();

            if (preg_match('/^(Title|Description|Long Description|Tags)\s*\[([a-z]{2,})\]\s*$/i', $header, $matches)) {
                $fieldName = strtolower(str_replace(' ', '_', $matches[1]));
                $langCode  = strtolower(trim($matches[2]));
                $languageHeaders[$fieldName][$langCode] = $columnIndex;
            } elseif (preg_match('/^faq\s*\[([a-z]{2,})\]\s*\[(question|answer)\]\s*\[(\d+)\]\s*$/i', $header, $matches)) {
                $langCode  = strtolower(trim($matches[1]));
                $type      = $matches[2];
                $faqNumber = $matches[3];
                $languageHeaders['faqs'][$langCode][$faqNumber][$type] = $columnIndex;
            } elseif (preg_match('/^Other Image\[(\d+)\]$/', $header, $matches)) {
                $other_image_Headers[$matches[1]] = $columnIndex;
            } elseif (preg_match('/^Files\[(\d+)\]$/', $header, $matches)) {
                $FilesHeaders[$matches[1]] = $columnIndex;
            }

            $columnIndex++;
        }

        return [$languageHeaders, $other_image_Headers, $FilesHeaders];
    }

    private function processOtherImagesFromRow(array $row, array $other_image_Headers): array
    {
        $other_images = [];
        foreach ($other_image_Headers as $indexes) {
            $other_image = isset($row[$indexes]) ? trim($row[$indexes]) : '';
            if (!empty($other_image)) {
                copy_image($row[$indexes], '/public/uploads/services/');
                $other_images[] = $other_image;
            }
        }
        return $other_images;
    }

    private function processOtherImagesFromRowUpdate(array $row, array $other_image_Headers, array $old_other_images): array
    {
        $other_images = [];
        foreach ($other_image_Headers as $indexes) {
            $other_image = isset($row[$indexes]) ? trim($row[$indexes]) : '';
            if (!empty($other_image) && !in_array($other_image, $old_other_images)) {
                $oi = copy_image($row[$indexes], '/public/uploads/services/');
                if (!empty($oi)) {
                    $other_images[] = $oi;
                }
            }
        }
        return !empty($other_images) ? $other_images : $old_other_images;
    }

    private function processFilesFromRow(array $row, array $FilesHeaders): array
    {
        $files = [];
        foreach ($FilesHeaders as $indexes) {
            $file = isset($row[$indexes]) ? trim($row[$indexes]) : '';
            if (!empty($file)) {
                copy_image($row[$indexes], '/public/uploads/services/');
                $files[] = $file;
            }
        }
        return $files;
    }

    private function processFilesFromRowUpdate(array $row, array $FilesHeaders, array $old_files): array
    {
        $files = [];
        foreach ($FilesHeaders as $indexes) {
            $file = isset($row[$indexes]) ? trim($row[$indexes]) : '';
            if (!empty($file) && !in_array($file, $old_files)) {
                $uploaded = copy_image($row[$indexes], '/public/uploads/services/');
                if (!empty($uploaded)) {
                    $files[] = $uploaded;
                }
            }
        }
        return !empty($files) ? $files : $old_files;
    }

    private function normalizeFaqsForService(array $translationData): array
    {
        if (empty($translationData['faqs']) && !empty($translationData['faqs_by_number'])) {
            $faqsByLanguage = [];
            foreach ($translationData['faqs_by_number'] as $faqNumber => $faqByLanguage) {
                foreach ($faqByLanguage as $langCode => $faqData) {
                    $faqsByLanguage[$langCode][] = $faqData;
                }
            }
            foreach ($faqsByLanguage as $langCode => $languageFaqs) {
                $translationData['faqs'][$langCode] = json_encode($languageFaqs, JSON_UNESCAPED_UNICODE);
            }
        }
        unset($translationData['faqs_by_number']);
        return $translationData;
    }
}
