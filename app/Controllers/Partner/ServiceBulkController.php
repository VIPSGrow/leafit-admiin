<?php

namespace App\Controllers\Partner;

use App\Models\Language_model;
use App\Models\Seo_model;
use App\Models\Service_model;
use App\Models\TranslatedServiceDetails_model;
use App\Services\ServicesService;
use App\Services\Service\ServiceSeoService;
use App\Services\Service\ServiceTranslationHelper;
use App\Services\utility\FileService;
use App\Services\utility\SlugService;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ServiceBulkController extends Partner
{
    protected ServicesService $serviceService;
    protected ServiceSeoService $seoService;
    protected SlugService $slugService;
    protected FileService $fileService;
    protected Seo_model $seoModel;

    public function __construct()
    {
        parent::__construct();
        $this->serviceService = new ServicesService();
        $this->seoService     = new ServiceSeoService();
        $this->slugService    = new SlugService();
        $this->fileService    = new FileService();
        $this->seoModel       = new Seo_model();
        helper('ResponceServices');
    }

    public function bulk_import_services()
    {
        if ($this->isLoggedIn) {
            setPageInfo($this->data, labels('services', 'Services') . ' | ' . labels('provider_panel', 'Provider Panel'), 'bulk_import_services');
            return view('backend/partner/template', $this->data);
        } else {
            return redirect('partner/login');
        }
    }

    public function bulk_import_service_upload()
    {
        $file = $this->request->getFile('file');
        $filePath = FCPATH . 'public/uploads/service_bulk_upload/';
        if (!is_dir($filePath)) {
            if (!mkdir($filePath, 0775, true)) {
                return $this->response->setJSON([
                    'status' => 'error',
                    'message' => labels(FAILED_TO_CREATE_FOLDERS, "Failed to create folders")
                ]);
            }
        }
        $newName = $file->getRandomName();
        $file->move($filePath, $newName);
        $fullPath = $filePath . $newName;
        $spreadsheet = IOFactory::load($fullPath);
        $sheet = $spreadsheet->getActiveSheet();
        $headerRow = $sheet->getRowIterator(1)->current();
        $cellIterator = $headerRow->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);

        // Parse multilanguage headers for translatable fields
        // Translatable fields: title, description, long_description, tags, faqs
        $languageHeaders = [];
        $other_image_Headers = [];
        $FilesHeaders = [];
        $columnIndex = 0;
        $OtherImagecolumnIndex = 0;
        $FilescolumnIndex = 0;

        // Get headers correctly - each header is in a separate cell
        $headerRowData = $sheet->rangeToArray('A1:' . $sheet->getHighestColumn() . '1', NULL, TRUE, FALSE, FALSE);
        $headers = $headerRowData[0]; // First row contains headers

        // Clean up headers - trim whitespace and quotes
        $headers = array_map(function ($header) {
            return trim($header, ' "');
        }, $headers);

        // Ensure headers array has sequential numeric indices (0, 1, 2, ...) to match row array indices
        $headers = array_values($headers);

        // Get available languages from database for validation
        $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], "", '0', 'id', 'ASC');
        $defaultLanguage = 'en'; // fallback
        foreach ($languages as $language) {
            if ($language['is_default'] == 1) {
                $defaultLanguage = $language['code'];
                break;
            }
        }

        if (!in_array('ID', $headers)) {
            //insert
            // Parse headers for translatable fields with language codes
            // Format: Title[en], Description[es], faq[en][question][1], etc.
            foreach ($cellIterator as $cell) {
                $header = $cell->getValue();

                // Match multilanguage translatable fields: Title[en], Description[en], etc.
                // Be lenient with header formatting (extra spaces / uppercase codes) so providers do not lose translations.
                // Allow optional whitespace + mixed-case codes so repeated bulk updates never drop a language column.
                if (preg_match('/^(Title|Description|Long Description|Tags)\s*\[([a-z]{2,})\]\s*$/i', $header, $matches)) {
                    $fieldName = strtolower(str_replace(' ', '_', $matches[1]));
                    $langCode = strtolower(trim($matches[2]));
                    if (!isset($languageHeaders[$fieldName])) {
                        $languageHeaders[$fieldName] = [];
                    }
                    $languageHeaders[$fieldName][$langCode] = $columnIndex;
                }
                // Match multilanguage FAQs: faq[en][question][1], faq[es][answer][1]
                // Keep the same relaxed parsing for FAQ columns to retain every language block from the CSV.
                // Match FAQ headers with the same relaxed rules to keep Hindi / other locales intact.
                elseif (preg_match('/^faq\s*\[([a-z]{2,})\]\s*\[(question|answer)\]\s*\[(\d+)\]\s*$/i', $header, $matches)) {
                    $langCode = strtolower(trim($matches[1]));
                    $type = $matches[2];
                    $faqNumber = $matches[3];
                    if (!isset($languageHeaders['faqs'])) {
                        $languageHeaders['faqs'] = [];
                    }
                    if (!isset($languageHeaders['faqs'][$langCode])) {
                        $languageHeaders['faqs'][$langCode] = [];
                    }
                    if (!isset($languageHeaders['faqs'][$langCode][$faqNumber])) {
                        $languageHeaders['faqs'][$langCode][$faqNumber] = [];
                    }
                    $languageHeaders['faqs'][$langCode][$faqNumber][$type] = $columnIndex;
                }
                // Match other images
                elseif (preg_match('/^Other Image\[(\d+)\]$/', $header, $matches)) {
                    $other_image_number = $matches[1];
                    $other_image_Headers[$other_image_number] = $OtherImagecolumnIndex;
                }
                // Match files
                elseif (preg_match('/^Files\[(\d+)\]$/', $header, $matches)) {
                    $fileNumber = $matches[1];
                    $FilesHeaders[$fileNumber] = $FilescolumnIndex;
                }

                $columnIndex++;
                $OtherImagecolumnIndex++;
                $FilescolumnIndex++;
            }
            $data = $sheet->toArray();
            array_shift($data);
            $data = array_filter($data, function ($row) {
                return !empty(array_filter($row));
            });
            // Reindex data array to ensure sequential numeric indices match header indices
            $data = array_values($data);

            // Validate default language has required fields
            $requiredFields = ['title', 'description'];
            foreach ($requiredFields as $field) {
                if (!isset($languageHeaders[$field][$defaultLanguage])) {
                    return ErrorResponse(
                        labels('multilanguage_missing_required_field', ucfirst($field) . " is required for default language ($defaultLanguage) in CSV headers"),
                        true,
                        [],
                        [],
                        200,
                        csrf_token(),
                        csrf_hash()
                    );
                }
            }

            $services = [];
            $serviceTranslations = [];
            $serviceSeoTranslations = []; // Store SEO translations for each service

            foreach ($data as $rowIndex => $row) {
                // Ensure row has sequential numeric indices to match header indices
                $row = array_values($row);

                // NEW CSV Structure (after multilanguage support):
                // Index 0: Provider ID
                // Index 1: Category ID
                // Index 2: Duration
                // Index 3: Members Required
                // Index 4: Max Quantity
                // Index 5: Price Type
                // Index 6: Tax ID
                // Index 7: Price
                // Index 8: Discounted Price
                // Index 9: Is Cancelable
                // Index 10: Cancelable before
                // Index 11: Pay Later Allowed
                // Index 12: At Store
                // Index 13: At Doorstep
                // Index 14: Status
                // Index 15: Approve Service
                // Index 16: Image
                // Then: Dynamic language columns...

                // Validate references
                $provider = fetch_details('partner_details', ['partner_id' => $row[0]]);
                if (empty($provider)) {
                    return ErrorResponse(labels(PROVIDER_ID, "Provider ID") . " :: " . $row[0] . " " . labels(NOT_FOUND, "not found"), true, [], [], 200, csrf_token(), csrf_hash());
                } else if ($row[0] != $this->userId) {
                    return ErrorResponse("Provider ID must be logged in user id", true, [], [], 200, csrf_token(), csrf_hash());
                }
                $category = fetch_details('categories', ['id' => $row[1]]);
                if (empty($category)) {
                    return ErrorResponse(labels(CATEGORY_ID, "Category ID") . " :: " . $row[1] . " " . labels(NOT_FOUND, "not found"), true, [], [], 200, csrf_token(), csrf_hash());
                }
                $tax = fetch_details('taxes', ['id' => $row[6]]); // FIXED: was $row[10], now $row[6]
                if (empty($tax)) {
                    return ErrorResponse(labels(TAX_ID, "Tax ID") . " :: " . $row[6] . " " . labels(NOT_FOUND, "not found"), true, [], [], 200, csrf_token(), csrf_hash());
                }

                // Extract translated fields per language
                $translatedFields = ServiceTranslationHelper::extractTranslatedFieldsFromBulkRow($row, $languageHeaders, $defaultLanguage);

                // Extract SEO translations from this row
                $seoTranslations = ServiceTranslationHelper::extractSeoTranslationsFromRow($row, $headers, $languages);

                // Store SEO translations for later saving (after service is created)
                $serviceSeoTranslations[$rowIndex] = $seoTranslations;

                // Validate default language required fields have values
                if (empty($translatedFields['title'][$defaultLanguage])) {
                    return ErrorResponse(
                        labels("title_is_required_for_default_language", "Title is required for default language") . " ($defaultLanguage) " . labels('at row', 'at row') . " " . ($rowIndex + 2),
                        true,
                        [],
                        [],
                        200,
                        csrf_token(),
                        csrf_hash()
                    );
                }
                if (empty($translatedFields['description'][$defaultLanguage])) {
                    return ErrorResponse(
                        labels("description_is_required_for_default_language", "Description is required for default language") . " ($defaultLanguage) " . labels('at row', 'at row') . " " . ($rowIndex + 2),
                        true,
                        [],
                        [],
                        200,
                        csrf_token(),
                        csrf_hash()
                    );
                }

                // Process other images
                $other_images = [];
                foreach ($other_image_Headers as $indexes) {
                    $other_image = isset($row[$indexes]) ? trim($row[$indexes]) : '';
                    if (!empty($other_image)) {
                        copy_image($row[$indexes], '/public/uploads/services/');
                        if (!empty($other_image)) {
                            $other_images[] = $other_image;
                        }
                    }
                }

                // Process files
                $files = [];
                foreach ($FilesHeaders as $indexes) {
                    $file = isset($row[$indexes]) ? trim($row[$indexes]) : '';
                    if (!empty($file)) {
                        copy_image($row[$indexes], '/public/uploads/services/');
                        if (!empty($file)) {
                            $files[] = $file;
                        }
                    }
                }

                // Process main service image (FIXED: was $row[21], now $row[16])
                $image = !empty($row[16]) ? copy_image($row[16], '/public/uploads/services/') : "";

                // Generate slug from default language title (same logic as admin bulk upload)
                // Uses SlugService for consistent, unique slugs across admin and partner panels
                $defaultTitle = $translatedFields['title'][$defaultLanguage] ?? '';
                $resolvedSlug = $this->slugService->generate(
                    $defaultTitle,
                    $defaultTitle,
                    'services'
                );

                // Prepare service data INCLUDING default language values for fallback
                // Store default language translatable fields in main table as fallback
                // These same values will also be stored in translated_service_details table
                $serviceData = [
                    'user_id' => $row[0], // Index 0: Provider ID
                    'category_id' => $row[1], // Index 1: Category ID
                    // ✅ Include default language translatable fields as fallback
                    'title' => $defaultTitle,
                    'description' => $translatedFields['description'][$defaultLanguage] ?? '',
                    'long_description' => $translatedFields['long_description'][$defaultLanguage] ?? '',
                    'tags' => $translatedFields['tags'][$defaultLanguage] ?? '',
                    'faqs' => $translatedFields['faqs'][$defaultLanguage] ?? json_encode([], JSON_UNESCAPED_UNICODE),
                    // Auto-generate slug from default language title (SlugService)
                    'slug' => $resolvedSlug,
                    // Non-translatable fields (FIXED: all indexes corrected)
                    'duration' => $row[2], // FIXED: was $row[5], now $row[2]
                    'number_of_members_required' => $row[3], // FIXED: was $row[6], now $row[3]
                    'max_quantity_allowed' => $row[4], // FIXED: was $row[7], now $row[4]
                    'tax_type' => $row[5], // FIXED: was $row[9], now $row[5]
                    'tax_id' => $row[6], // FIXED: was $row[10], now $row[6]
                    'price' => $row[7], // FIXED: was $row[11], now $row[7]
                    'discounted_price' => $row[8], // FIXED: was $row[12], now $row[8]
                    'is_cancelable' => $row[9], // FIXED: was $row[13], now $row[9]
                    'cancelable_till' => ($row[9] == 1) ? $row[10] : "", // FIXED: indexes corrected
                    'is_pay_later_allowed' => $row[11], // FIXED: was $row[15], now $row[11]
                    'at_store' => $row[12], // FIXED: was $row[16], now $row[12]
                    'at_doorstep' => $row[13], // FIXED: was $row[17], now $row[13]
                    'status' => $row[14], // FIXED: was $row[18], now $row[14]
                    'approved_by_admin' => ($provider[0]['need_approval_for_the_service'] == "1") ? "0" : "1",
                    'other_images' => json_encode($other_images),
                    'image' => $image,
                    'files' => json_encode($files),
                ];

                $services[] = $serviceData;
                $serviceTranslations[] = $translatedFields;
            }

            // Insert services and their translations
            $serviceModel = new Service_model();
            $db = \Config\Database::connect();
            $db->transStart();

            try {
                foreach ($services as $index => $service) {
                    // Insert service with default language values as fallback
                    if (!$serviceModel->insert($service)) {
                        throw new \Exception(labels("failed_to_add_service", "Failed to add service") . " " . labels('at row', 'at row') . " " . ($index + 2));
                    }

                    $serviceId = $serviceModel->insertID();

                    // Save ALL language translations (including default) in translated_service_details table
                    // This provides:
                    // 1. Main table: Has default language values for quick access and fallback
                    // 2. Translations table: Has ALL languages including default for consistency

                    // Prepare translation data for ServicesService
                    $translationData = $serviceTranslations[$index];

                    // Convert FAQ format for ServicesService if needed
                    // ServicesService expects FAQs grouped by language: ['en' => [...], 'es' => [...]]
                    // The extractTranslatedFieldsFromBulkRow already creates 'faqs' in language-wise format
                    // If 'faqs' is missing, convert 'faqs_by_number' to language-wise format
                    if (empty($translationData['faqs']) && isset($translationData['faqs_by_number']) && !empty($translationData['faqs_by_number'])) {
                        // Convert faqs_by_number (grouped by FAQ number) to language-wise format
                        // Structure: [1 => ['en' => [...], 'es' => [...]], 2 => ['en' => [...], 'es' => [...]]]
                        // Convert to: ['en' => [...], 'es' => [...]]
                        $faqsByLanguage = [];
                        foreach ($translationData['faqs_by_number'] as $faqNumber => $faqByLanguage) {
                            foreach ($faqByLanguage as $langCode => $faqData) {
                                if (!isset($faqsByLanguage[$langCode])) {
                                    $faqsByLanguage[$langCode] = [];
                                }
                                $faqsByLanguage[$langCode][] = $faqData;
                            }
                        }
                        // Convert arrays to JSON strings to match the expected format
                        foreach ($faqsByLanguage as $langCode => $languageFaqs) {
                            $translationData['faqs'][$langCode] = json_encode($languageFaqs, JSON_UNESCAPED_UNICODE);
                        }
                        unset($translationData['faqs_by_number']);
                    } else if (isset($translationData['faqs_by_number'])) {
                        // Remove faqs_by_number if faqs already exists (faqs is the correct format)
                        unset($translationData['faqs_by_number']);
                    }

                    $translationResult = $this->serviceService->saveTranslatedFields(
                        $serviceId,
                        $translationData
                    );

                    if (!$translationResult['success']) {
                        $errors = implode(', ', $translationResult['errors']);
                        throw new \Exception(labels("failed_to_save_translations_for_service_id", "Failed to save translations for service ID") . " $serviceId: " . $errors);
                    }

                    // Save SEO settings for this service (at the end, after translations)
                    if (isset($serviceSeoTranslations[$index])) {
                        $seoResult = $this->seoService->saveForBulk($serviceId, $serviceSeoTranslations[$index], $languages);
                        if (!$seoResult) {
                            log_message('error', "Row {$index}: Failed to save SEO settings for service {$serviceId}");
                        }
                    }
                }

                $db->transComplete();

                if ($db->transStatus() === false) {
                    throw new \Exception(labels(TRANSACTION_FAILED, 'Transaction failed'));
                }

                return successResponse(labels(DATA_SAVED_SUCCESSFULLY, "Services added successfully"), false, [], [], 200, csrf_token(), csrf_hash());
            } catch (\Exception $e) {
                $db->transRollback();
                log_message('error', 'Partner bulk import service error: ' . $e->getMessage());
                return ErrorResponse(labels(ERROR_OCCURED, "Error occurred"), true, [], [], 200, csrf_token(), csrf_hash());
            }
        } else {
            //update
            // Parse headers for translatable fields with language codes (same as INSERT)
            // Format: Title[en], Description[es], faq[en][question][1], etc.
            foreach ($cellIterator as $cell) {
                $header = $cell->getValue();

                // Match multilanguage translatable fields: Title[en], Description[en], etc.
                if (preg_match('/^(Title|Description|Long Description|Tags)\s*\[([a-z]{2,})\]\s*$/i', $header, $matches)) {
                    $fieldName = strtolower(str_replace(' ', '_', $matches[1]));
                    $langCode = strtolower(trim($matches[2]));
                    if (!isset($languageHeaders[$fieldName])) {
                        $languageHeaders[$fieldName] = [];
                    }
                    $languageHeaders[$fieldName][$langCode] = $columnIndex;
                }
                // Match multilanguage FAQs: faq[en][question][1], faq[es][answer][1]
                elseif (preg_match('/^faq\s*\[([a-z]{2,})\]\s*\[(question|answer)\]\s*\[(\d+)\]\s*$/i', $header, $matches)) {
                    $langCode = strtolower(trim($matches[1]));
                    $type = $matches[2];
                    $faqNumber = $matches[3];
                    if (!isset($languageHeaders['faqs'])) {
                        $languageHeaders['faqs'] = [];
                    }
                    if (!isset($languageHeaders['faqs'][$langCode])) {
                        $languageHeaders['faqs'][$langCode] = [];
                    }
                    if (!isset($languageHeaders['faqs'][$langCode][$faqNumber])) {
                        $languageHeaders['faqs'][$langCode][$faqNumber] = [];
                    }
                    $languageHeaders['faqs'][$langCode][$faqNumber][$type] = $columnIndex;
                }
                // Match other images
                elseif (preg_match('/^Other Image\[(\d+)\]$/', $header, $matches)) {
                    $other_image_number = $matches[1];
                    $other_image_Headers[$other_image_number] = $OtherImagecolumnIndex;
                }
                // Match files
                elseif (preg_match('/^Files\[(\d+)\]$/', $header, $matches)) {
                    $fileNumber = $matches[1];
                    $FilesHeaders[$fileNumber] = $FilescolumnIndex;
                }

                $columnIndex++;
                $OtherImagecolumnIndex++;
                $FilescolumnIndex++;
            }
            // Validate default language has required fields
            $requiredFields = ['title', 'description'];
            foreach ($requiredFields as $field) {
                if (!isset($languageHeaders[$field][$defaultLanguage])) {
                    return ErrorResponse(
                        labels('multilanguage_missing_required_field', ucfirst($field) . " is required for default language ($defaultLanguage) in CSV headers"),
                        true,
                        [],
                        [],
                        200,
                        csrf_token(),
                        csrf_hash()
                    );
                }
            }

            $data = $sheet->toArray();
            array_shift($data);
            $data = array_filter($data, function ($row) {
                return !empty(array_filter($row));
            });

            $services = [];
            $serviceTranslations = [];
            $serviceSeoTranslations = []; // Store SEO translations for each service

            foreach ($data as $rowIndex => $row) {
                // Ensure row has sequential numeric indices to match header indices
                $row = array_values($row);

                // NEW CSV Structure for UPDATE (same as INSERT but with ID at index 0):
                // Index 0: ID (SERVICE ID - required for update)
                // Index 1: Provider ID
                // Index 2: Category ID
                // Index 3: Duration
                // Index 4: Members Required
                // Index 5: Max Quantity
                // Index 6: Price Type
                // Index 7: Tax ID
                // Index 8: Price
                // Index 9: Discounted Price
                // Index 10: Is Cancelable
                // Index 11: Cancelable before
                // Index 12: Pay Later Allowed
                // Index 13: At Store
                // Index 14: At Doorstep
                // Index 15: Status
                // Index 16: Approve Service
                // Index 17: Image
                // Then: Dynamic language columns...

                // Include slug so we can resolve/update it when title changes (same as admin bulk update)
                $fetch_service_data = fetch_details('services', ['id' => $row[0]], ['image', 'other_images', 'files', 'slug']);
                if (empty($fetch_service_data)) {
                    return ErrorResponse(labels('service_id', 'Service ID') . " :: " . $row[0] . " " . labels('not found', 'not found'), true, [], [], 200, csrf_token(), csrf_hash());
                }

                $old_other_images = [];
                if (!empty($fetch_service_data)) {
                    $other_images = $fetch_service_data[0]['other_images'];
                    $old_other_images = is_string($other_images) ? json_decode($other_images, true) : $other_images;
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $old_other_images = [];
                    }
                }

                $old_files = [];
                if (!empty($fetch_service_data)) {
                    $old_files = $fetch_service_data[0]['files'];
                    $old_files = is_string($old_files) ? json_decode($old_files, true) : $old_files;
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $old_files = [];
                    }
                }

                // Validate references - FIXED: Provider ID is at index 1 for UPDATE operations
                $provider = fetch_details('partner_details', ['partner_id' => $row[1]]);
                if (empty($provider)) {
                    return ErrorResponse(labels(PROVIDER_ID, "Provider ID") . " :: " . $row[1] . " " . labels(NOT_FOUND, "not found"), true, [], [], 200, csrf_token(), csrf_hash());
                } else if ($row[1] != $this->userId) {
                    return ErrorResponse(labels(THE_PROVIDER_ID_MUST_MATCH_THE_LOGGED_IN_USER_ID, "The provider ID must match the logged-in user ID."), true, [], [], 200, csrf_token(), csrf_hash());
                }
                $category = fetch_details('categories', ['id' => $row[2]]);
                if (empty($category)) {
                    return ErrorResponse(labels(CATEGORY_ID, "Category ID") . " :: " . $row[2] . " " . labels(NOT_FOUND, "not found"), true, [], [], 200, csrf_token(), csrf_hash());
                }
                $tax = fetch_details('taxes', ['id' => $row[7]]);
                if (empty($tax)) {
                    return ErrorResponse(labels(TAX_ID, "Tax ID") . " :: " . $row[7] . " " . labels(NOT_FOUND, "not found"), true, [], [], 200, csrf_token(), csrf_hash());
                }

                // Extract translated fields per language
                $translatedFields = ServiceTranslationHelper::extractTranslatedFieldsFromBulkRow($row, $languageHeaders, $defaultLanguage);

                // Extract SEO translations from this row
                $seoTranslations = ServiceTranslationHelper::extractSeoTranslationsFromRow($row, $headers, $languages);

                // Get service ID for storing SEO translations
                $serviceId = $row[0];

                // Store SEO translations for later saving (after service is updated)
                $serviceSeoTranslations[$serviceId] = $seoTranslations;

                // Validate default language required fields have values
                if (empty($translatedFields['title'][$defaultLanguage])) {
                    return ErrorResponse(
                        labels('title_is_required_for_default_language', "Title is required for default language") . ($defaultLanguage) . " " . labels('at row', 'at row') . " " . ($rowIndex + 2),
                        true,
                        [],
                        [],
                        200,
                        csrf_token(),
                        csrf_hash()
                    );
                }
                if (empty($translatedFields['description'][$defaultLanguage])) {
                    return ErrorResponse(
                        labels('description_is_required_for_default_language', "Description is required for default language") . ($defaultLanguage) . " " . labels('at row', 'at row') . " " . ($rowIndex + 2),
                        true,
                        [],
                        [],
                        200,
                        csrf_token(),
                        csrf_hash()
                    );
                }
                $other_images = [];
                foreach ($other_image_Headers as $indexes) {
                    $other_image = isset($row[$indexes]) ? trim($row[$indexes]) : '';
                    if (!empty($other_image) && !in_array($other_image, $old_other_images)) {
                        $oi = copy_image($row[$indexes], '/public/uploads/services/');
                        if (!empty($other_image)) {
                            $other_images[] = $oi;
                        }
                    } else if (!empty($old_other_images)) {
                        $other_images = $old_other_images;
                    } else {
                        $other_images = [];
                    }
                }
                $files = [];
                foreach ($FilesHeaders as $indexes) {
                    $file = isset($row[$indexes]) ? trim($row[$indexes]) : '';
                    if (!empty($file) && !in_array($file, $old_files)) {
                        $oi = copy_image($row[$indexes], '/public/uploads/services/');
                        if (!empty($file)) {
                            $files[] = $oi;
                        }
                    } else if (!empty($old_files)) {
                        $files = $old_files;
                    } else {
                        $files = [];
                    }
                }
                // FIXED: Use processed FAQs from translated fields instead of empty array
                $faqs = $translatedFields['faqs'][$defaultLanguage] ?? json_encode([], JSON_UNESCAPED_UNICODE);
                // FIXED: Use correct row indices for UPDATE CSV structure
                // Index 0: Service ID, Index 1: Provider ID, Index 2: Category ID, etc.
                $image = !empty($row[17]) ? copy_image($row[17], '/public/uploads/services/') : "";

                // Resolve slug from default language title (same logic as admin bulk update)
                // Keeps existing slug if title unchanged; regenerates for legacy slugs; excludes current service ID for uniqueness
                $serviceId = $row[0];
                $defaultTitle = $translatedFields['title'][$defaultLanguage] ?? '';
                $currentSlug = $fetch_service_data[0]['slug'] ?? null;
                $baseName = !empty($translatedFields['title'][$defaultLanguage])
                    ? $translatedFields['title'][$defaultLanguage]
                    : $defaultTitle;
                $slug = $this->slugService->resolve(
                    $currentSlug,
                    null,
                    $baseName,
                    'services',
                    $serviceId
                );
                if ($currentSlug !== null && $this->slugService->isLegacySlug($currentSlug)) {
                    $slug = $this->slugService->generate(
                        $baseName,
                        $baseName,
                        'services',
                        $serviceId
                    );
                }

                $services[] = [
                    'id' => $serviceId, // Service ID
                    'user_id' => $row[1], // Provider ID
                    'category_id' => $row[2], // Category ID
                    'title' => $defaultTitle, // Title from translations
                    'tags' => $translatedFields['tags'][$defaultLanguage] ?? '', // Tags from translations
                    'description' => $translatedFields['description'][$defaultLanguage] ?? '', // Description from translations
                    // Auto-generate slug from default language title
                    'slug' => $slug,
                    'duration' => $row[3], // Duration
                    'number_of_members_required' => $row[4], // Members Required
                    'max_quantity_allowed' => $row[5], // Max Quantity
                    'long_description' => $translatedFields['long_description'][$defaultLanguage] ?? '', // Long Description from translations
                    'tax_type' => $row[6], // Price Type
                    'tax_id' => $row[7], // Tax ID
                    'price' => $row[8], // Price
                    'discounted_price' => $row[9], // Discounted Price
                    'is_cancelable' => $row[10], // Is Cancelable
                    'cancelable_till' => ($row[10] == 1) ? $row[11] : "", // Cancelable before
                    'is_pay_later_allowed' => $row[12], // Pay Later Allowed
                    'at_store' => $row[13], // At Store
                    'at_doorstep' => $row[14], // At Doorstep
                    'status' => $row[15], // Status
                    'image' => $image,
                    'approved_by_admin' => (!empty($provider) && $provider[0]['need_approval_for_the_service'] == "1") ? "0" : "1",
                    'faqs' => json_encode($faqs, JSON_UNESCAPED_UNICODE),
                    'other_images' => json_encode($other_images),
                    'files' => json_encode($files),
                ];
            }

            // Update services and their translations
            $serviceModel = new Service_model();
            $db = \Config\Database::connect();
            $db->transStart();

            try {
                foreach ($services as $index => $service) {
                    $serviceId = $service['id'];
                    unset($service['id']);

                    // Update service with default language values as fallback
                    if (!$serviceModel->update($serviceId, $service)) {
                        throw new \Exception(labels(FAILED_TO_UPDATE_SERVICE, 'Failed to update service') . " " . labels('at row', 'at row') . " " . ($index + 2));
                    }

                    // Save ALL language translations (including default) in translated_service_details table
                    // This provides:
                    // 1. Main table: Has default language values for quick access and fallback
                    // 2. Translations table: Has ALL languages including default for consistency

                    // Get the translated fields for this service
                    $translatedFields = ServiceTranslationHelper::extractTranslatedFieldsFromBulkRow($data[$index], $languageHeaders, $defaultLanguage);

                    // Prepare translation data for ServicesService
                    $translationData = $translatedFields;

                    // Convert FAQ format for ServicesService if needed
                    // ServicesService expects FAQs grouped by language: ['en' => [...], 'es' => [...]]
                    // The extractTranslatedFieldsFromBulkRow already creates 'faqs' in language-wise format
                    // If 'faqs' is missing, convert 'faqs_by_number' to language-wise format
                    if (empty($translationData['faqs']) && isset($translationData['faqs_by_number']) && !empty($translationData['faqs_by_number'])) {
                        // Convert faqs_by_number (grouped by FAQ number) to language-wise format
                        // Structure: [1 => ['en' => [...], 'es' => [...]], 2 => ['en' => [...], 'es' => [...]]]
                        // Convert to: ['en' => [...], 'es' => [...]]
                        $faqsByLanguage = [];
                        foreach ($translationData['faqs_by_number'] as $faqNumber => $faqByLanguage) {
                            foreach ($faqByLanguage as $langCode => $faqData) {
                                if (!isset($faqsByLanguage[$langCode])) {
                                    $faqsByLanguage[$langCode] = [];
                                }
                                $faqsByLanguage[$langCode][] = $faqData;
                            }
                        }
                        // Convert arrays to JSON strings to match the expected format
                        foreach ($faqsByLanguage as $langCode => $languageFaqs) {
                            $translationData['faqs'][$langCode] = json_encode($languageFaqs, JSON_UNESCAPED_UNICODE);
                        }
                        unset($translationData['faqs_by_number']);
                    } else if (isset($translationData['faqs_by_number'])) {
                        // Remove faqs_by_number if faqs already exists (faqs is the correct format)
                        unset($translationData['faqs_by_number']);
                    }

                    $translationResult = $this->serviceService->saveTranslatedFields(
                        $serviceId,
                        $translationData
                    );

                    if (!$translationResult['success']) {
                        $errors = implode(', ', $translationResult['errors']);
                        throw new \Exception(labels(FAILED_TO_SAVE_TRANSLATIONS_FOR_SERVICE_ID, 'Failed to save translations for service ID') . " $serviceId: " . $errors);
                    }

                    // Save SEO settings for this service (at the end, after translations)
                    if (isset($serviceSeoTranslations[$serviceId])) {
                        $seoResult = $this->seoService->saveForBulk($serviceId, $serviceSeoTranslations[$serviceId], $languages);
                        if (!$seoResult) {
                            log_message('error', "Failed to save SEO settings for service {$serviceId}");
                        }
                    }
                }

                $db->transComplete();

                if ($db->transStatus() === false) {
                    throw new \Exception(labels(TRANSACTION_FAILED, 'Transaction failed'));
                }

                return successResponse(labels(SERVICES_UPDATED_SUCCESSFULLY, 'Services updated successfully'), false, [], [], 200, csrf_token(), csrf_hash());
            } catch (\Exception $e) {
                $db->transRollback();
                log_message('error', 'Partner bulk update service error: ' . $e->getMessage());
                return ErrorResponse($e->getMessage(), true, [], [], 200, csrf_token(), csrf_hash());
            }
        }
    }

    public function downloadSampleForInsert()
    {
        try {
            // Get available languages from database
            $languages = fetch_details('languages', [], ['code', 'language', 'is_default'], "", '0', 'id', 'ASC');

            // Build headers with non-translatable fields first
            $headers = [
                'Provider ID',
                'Category ID',
                // ❌ Removed: Title, Tags, Short Description, Description (these are translatable)
                'Duration to perform task',
                'Members Required to Perform Task',
                'Max Quantity allowed for services',
                'Price Type',
                'Tax ID',
                'Price',
                'Discounted Price',
                'Is Cancelable',
                'Cancelable before',
                'Pay Later Allowed',
                'At Store',
                'At Doorstep',
                'Status',
                'Approve Service',
                'Image',
            ];

            // Add translatable fields for each language
            // Format: Title[en], Title[es], Description[en], Description[es], etc.
            foreach ($languages as $language) {
                $langCode = $language['code'];
                $langName = $language['language'];

                // Add headers for each translatable field per language
                $headers[] = "Title[$langCode]"; // translatable
                $headers[] = "Description[$langCode]"; // translatable (short description)
                $headers[] = "Long Description[$langCode]"; // translatable
                $headers[] = "Tags[$langCode]"; // translatable

                // Add FAQ headers for this language (2 FAQs as example)
                $headers[] = "faq[$langCode][question][1]";
                $headers[] = "faq[$langCode][answer][1]";
                $headers[] = "faq[$langCode][question][2]";
                $headers[] = "faq[$langCode][answer][2]";
            }

            // Add non-translatable fields at the end
            $headers[] = 'Other Image[1]';
            $headers[] = 'Other Image[2]';
            $headers[] = 'Files[1]';
            $headers[] = 'Files[2]';

            // Add SEO language headers at the very end, after all other columns
            // Format: "SEO Title (en)", "SEO Description (en)", etc.
            foreach ($languages as $language) {
                $langCode = $language['code'];
                $headers[] = "SEO Title ($langCode)";
                $headers[] = "SEO Description ($langCode)";
                $headers[] = "SEO Keywords ($langCode)";
                $headers[] = "SEO Schema Markup ($langCode)";
            }

            // Build sample data row
            $sampleRow = [
                $this->userId, // Provider ID (logged in user)
                '2', // Category ID
                '60', // Duration to perform task
                '1', // Members Required to Perform Task
                '5', // Max Quantity allowed for services
                'included', // Price Type
                '1', // Tax ID
                '100', // Price
                '80', // Discounted Price
                '1', // Is Cancelable
                '24', // Cancelable before (hours)
                '1', // Pay Later Allowed
                '1', // At Store
                '1', // At Doorstep
                '1', // Status (1=active)
                '1', // Approve Service
                'public/uploads/services/sample.jpg', // Image
            ];

            // Add sample translatable fields for each language
            foreach ($languages as $language) {
                $langCode = $language['code'];
                $langName = $language['language'];

                // Sample data varies by language for demonstration
                if ($langCode === 'en') {
                    $sampleRow[] = 'House Cleaning Service'; // Title[en]
                    $sampleRow[] = 'Professional house cleaning service'; // Description[en]
                    $sampleRow[] = 'We provide thorough cleaning of your home including all rooms, kitchen, and bathrooms'; // Long Description[en]
                    $sampleRow[] = 'cleaning,house,professional'; // Tags[en]
                    $sampleRow[] = 'What areas do you clean?'; // faq[en][question][1]
                    $sampleRow[] = 'We clean all rooms, kitchen, bathrooms, and common areas'; // faq[en][answer][1]
                    $sampleRow[] = 'How long does it take?'; // faq[en][question][2]
                    $sampleRow[] = 'Typically 2-3 hours depending on home size'; // faq[en][answer][2]
                } elseif ($langCode === 'es') {
                    $sampleRow[] = 'Servicio de Limpieza de Casa'; // Title[es]
                    $sampleRow[] = 'Servicio profesional de limpieza de casa'; // Description[es]
                    $sampleRow[] = 'Proporcionamos limpieza completa de su hogar incluyendo todas las habitaciones, cocina y baños'; // Long Description[es]
                    $sampleRow[] = 'limpieza,casa,profesional'; // Tags[es]
                    $sampleRow[] = '¿Qué áreas limpian?'; // faq[es][question][1]
                    $sampleRow[] = 'Limpiamos todas las habitaciones, cocina, baños y áreas comunes'; // faq[es][answer][1]
                    $sampleRow[] = '¿Cuánto tiempo toma?'; // faq[es][question][2]
                    $sampleRow[] = 'Típicamente 2-3 horas dependiendo del tamaño de la casa'; // faq[es][answer][2]
                } elseif ($langCode === 'ar') {
                    $sampleRow[] = 'خدمة تنظيف المنزل'; // Title[ar]
                    $sampleRow[] = 'خدمة تنظيف منزلية احترافية'; // Description[ar]
                    $sampleRow[] = 'نوفر تنظيفًا شاملاً لمنزلك بما في ذلك جميع الغرف والمطبخ والحمامات'; // Long Description[ar]
                    $sampleRow[] = 'تنظيف,منزل,احترافي'; // Tags[ar]
                    $sampleRow[] = 'ما هي المناطق التي تنظفونها؟'; // faq[ar][question][1]
                    $sampleRow[] = 'ننظف جميع الغرف والمطبخ والحمامات والمناطق المشتركة'; // faq[ar][answer][1]
                    $sampleRow[] = 'كم يستغرق الوقت؟'; // faq[ar][question][2]
                    $sampleRow[] = 'عادة 2-3 ساعات حسب حجم المنزل'; // faq[ar][answer][2]
                } else {
                    // For other languages, leave empty (optional)
                    $sampleRow[] = ''; // Title
                    $sampleRow[] = ''; // Description
                    $sampleRow[] = ''; // Long Description
                    $sampleRow[] = ''; // Tags
                    $sampleRow[] = ''; // faq[question][1]
                    $sampleRow[] = ''; // faq[answer][1]
                    $sampleRow[] = ''; // faq[question][2]
                    $sampleRow[] = ''; // faq[answer][2]
                }
            }

            // Add sample other images and files
            $sampleRow[] = 'public/uploads/services/image1.jpg'; // Other Image[1]
            $sampleRow[] = 'public/uploads/services/image2.jpg'; // Other Image[2]
            $sampleRow[] = 'public/uploads/services/document1.pdf'; // Files[1]
            $sampleRow[] = 'public/uploads/services/document2.pdf'; // Files[2]

            // Add SEO sample data for each language at the end
            // IMPORTANT: Order must match headers - group by language (Title, Description, Keywords, Schema per language)
            foreach ($languages as $language) {
                $langCode = $language['code'];
                // Sample SEO Title for this language
                $sampleRow[] = 'Sample SEO Title (' . $langCode . ')';
                // Sample SEO Description for this language
                $sampleRow[] = 'Sample SEO Description (' . $langCode . ')';
                // Sample SEO Keywords for this language
                $sampleRow[] = 'keyword1, keyword2, keyword3 (' . $langCode . ')';
                // Sample SEO Schema Markup for this language
                $sampleRow[] = '{"@type":"Service"} (' . $langCode . ')';
            }

            $output = fopen('php://output', 'w');
            if ($output === false) {
                throw new \Exception(labels(FAILED_TO_OPEN_OUTPUT_STREAM, "Failed to open output stream."));
            }
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="service_sample_without_data_multilanguage.csv"');
            fputcsv($output, $headers);
            fputcsv($output, $sampleRow); // Add sample data row
            fclose($output);
            exit;
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/ServiceBulkController.php - downloadSampleForInsert()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function downloadSampleForUpdate()
    {
        try {
            // Get active languages for multilanguage support
            $languageModel = new Language_model();
            $languages = $languageModel->findAll();
            $defaultLanguage = get_settings('default_language', true);

            // Build headers - simple string array
            $headers = [];
            $headers[] = 'ID';
            $headers[] = 'Provider ID';
            $headers[] = 'Category ID';
            $headers[] = 'Duration to perform task';
            $headers[] = 'Members Required to Perform Task';
            $headers[] = 'Max Quantity allowed for services';
            $headers[] = 'Price Type';
            $headers[] = 'Tax ID';
            $headers[] = 'Price';
            $headers[] = 'Discounted Price';
            $headers[] = 'Is Cancelable';
            $headers[] = 'Cancelable before';
            $headers[] = 'Pay Later Allowed';
            $headers[] = 'At Store';
            $headers[] = 'At Doorstep';
            $headers[] = 'Status';
            $headers[] = 'Approve Service';
            $headers[] = 'Image';

            // Add language-specific headers
            foreach ($languages as $language) {
                $langCode = $language['code'];
                $headers[] = "Title[$langCode]";
                $headers[] = "Description[$langCode]";
                $headers[] = "Long Description[$langCode]";
                $headers[] = "Tags[$langCode]";
                $headers[] = "faq[$langCode][question][1]";
                $headers[] = "faq[$langCode][answer][1]";
                $headers[] = "faq[$langCode][question][2]";
                $headers[] = "faq[$langCode][answer][2]";
            }

            $headers[] = 'Other Image[1]';
            $headers[] = 'Other Image[2]';
            $headers[] = 'Files[1]';
            $headers[] = 'Files[2]';

            // Add SEO language headers at the very end, after all other columns
            // Format: "SEO Title (en)", "SEO Description (en)", etc.
            foreach ($languages as $language) {
                $langCode = $language['code'];
                $headers[] = "SEO Title ($langCode)";
                $headers[] = "SEO Description ($langCode)";
                $headers[] = "SEO Keywords ($langCode)";
                $headers[] = "SEO Schema Markup ($langCode)";
            }

            // Fetch only the logged-in partner's services
            $services = fetch_details('services', ['user_id' => $this->userId]);

            // Prepare data
            $all_data = [];
            $translationModel = new TranslatedServiceDetails_model();

            foreach ($services as $service) {
                $row = [];

                // Add basic service fields - force to string immediately
                $row[] = strval($service['id'] ?? '');
                $row[] = strval($service['user_id'] ?? '');
                $row[] = strval($service['category_id'] ?? '');
                $row[] = strval($service['duration'] ?? '');
                $row[] = strval($service['number_of_members_required'] ?? '');
                $row[] = strval($service['max_quantity_allowed'] ?? '');
                $row[] = strval($service['tax_type'] ?? '');
                $row[] = strval($service['tax_id'] ?? '');
                $row[] = strval($service['price'] ?? '');
                $row[] = strval($service['discounted_price'] ?? '');
                $row[] = strval($service['is_cancelable'] ?? '');
                $row[] = strval($service['cancelable_till'] ?? '');
                $row[] = strval($service['is_pay_later_allowed'] ?? '');
                $row[] = strval($service['at_store'] ?? '');
                $row[] = strval($service['at_doorstep'] ?? '');
                $row[] = strval($service['status'] ?? '');
                $row[] = strval($service['approved_by_admin'] ?? '');
                $row[] = strval($service['image'] ?? '');

                // Get translations
                $translations = $translationModel->where('service_id', $service['id'])->findAll();

                $translationsByLang = [];
                foreach ($translations as $trans) {
                    $translationsByLang[$trans['language_code']] = $trans;
                }

                // Find default language code for fallback
                $defaultLangCode = null;
                foreach ($languages as $lang) {
                    if (isset($lang['is_default']) && $lang['is_default'] == 1) {
                        $defaultLangCode = $lang['code'];
                        break;
                    }
                }

                // Add language-specific data
                foreach ($languages as $language) {
                    $langCode = $language['code'];
                    $isDefault = isset($language['is_default']) && $language['is_default'] == 1;

                    if (isset($translationsByLang[$langCode])) {
                        // Translation exists for this language - use it
                        $trans = $translationsByLang[$langCode];

                        // Add translatable text fields
                        $row[] = strval($trans['title'] ?? '');
                        $row[] = strval($trans['description'] ?? '');
                        $row[] = strval(strip_tags(htmlspecialchars_decode(stripslashes($trans['long_description'] ?? ''))));
                        $row[] = strval($trans['tags'] ?? '');

                        // Handle FAQs
                        $faqsJson = $trans['faqs'] ?? '[]';
                        $faqs = @json_decode($faqsJson, true);

                        if (!is_array($faqs)) {
                            $faqs = [];
                        }

                        // Add first FAQ
                        if (isset($faqs[0]) && is_array($faqs[0])) {
                            $row[] = strval($faqs[0]['question'] ?? '');
                            $row[] = strval($faqs[0]['answer'] ?? '');
                        } else {
                            $row[] = '';
                            $row[] = '';
                        }

                        // Add second FAQ
                        if (isset($faqs[1]) && is_array($faqs[1])) {
                            $row[] = strval($faqs[1]['question'] ?? '');
                            $row[] = strval($faqs[1]['answer'] ?? '');
                        } else {
                            $row[] = '';
                            $row[] = '';
                        }
                    } else {
                        // No translation exists for this language

                        if ($isDefault) {
                            // Default language - use fallback from base table or default language translation
                            $fallbackTrans = null;

                            if ($defaultLangCode && isset($translationsByLang[$defaultLangCode])) {
                                $fallbackTrans = $translationsByLang[$defaultLangCode];
                            }

                            // Use fallback translation if available, otherwise use base service table data
                            // Base service table stores default language data as fallback
                            $title = $fallbackTrans['title'] ?? $service['title'] ?? '';
                            $description = $fallbackTrans['description'] ?? $service['description'] ?? '';
                            $longDescription = $fallbackTrans['long_description'] ?? $service['long_description'] ?? '';
                            $tags = $fallbackTrans['tags'] ?? $service['tags'] ?? '';
                            $faqsJson = $fallbackTrans['faqs'] ?? $service['faqs'] ?? '[]';
                        } else {
                            // Non-default language → NO fallback
                            $title = '';
                            $description = '';
                            $longDescription = '';
                            $tags = '';
                            $faqsJson = '[]';
                        }

                        // Push values
                        $row[] = strval($title);
                        $row[] = strval($description);
                        $row[] = strval(strip_tags(htmlspecialchars_decode(stripslashes($longDescription))));
                        $row[] = strval($tags);

                        // FAQs
                        $faqs = @json_decode($faqsJson, true);
                        if (!is_array($faqs)) {
                            $faqs = [];
                        }

                        // FAQ 1
                        $row[] = isset($faqs[0]['question']) ? strval($faqs[0]['question']) : '';
                        $row[] = isset($faqs[0]['answer']) ? strval($faqs[0]['answer']) : '';

                        // FAQ 2
                        $row[] = isset($faqs[1]['question']) ? strval($faqs[1]['question']) : '';
                        $row[] = isset($faqs[1]['answer']) ? strval($faqs[1]['answer']) : '';
                    }
                }

                // Handle other_images
                $otherImagesJson = $service['other_images'] ?? '[]';
                $otherImages = @json_decode($otherImagesJson, true);

                if (!is_array($otherImages)) {
                    $otherImages = [];
                }

                $row[] = isset($otherImages[0]) && is_string($otherImages[0]) ? $otherImages[0] : '';
                $row[] = isset($otherImages[1]) && is_string($otherImages[1]) ? $otherImages[1] : '';

                // Handle files
                $filesJson = $service['files'] ?? '[]';
                $files = @json_decode($filesJson, true);
                if (!is_array($files)) {
                    $files = [];
                }
                $row[] = isset($files[0]) && is_string($files[0]) ? $files[0] : '';
                $row[] = isset($files[1]) && is_string($files[1]) ? $files[1] : '';

                // Get SEO settings for this service
                $this->seoModel->setTableContext('services');
                $baseSeoSettings = $this->seoModel->getSeoSettingsByReferenceId($service['id']);

                // Get SEO translations
                $seoTranslationModel = model('TranslatedServiceSeoSettings_model');
                $seoTranslations = $seoTranslationModel->where('service_id', $service['id'])->findAll();

                $seoTranslationsByLang = [];
                foreach ($seoTranslations as $seoTrans) {
                    $seoTranslationsByLang[$seoTrans['language_code']] = $seoTrans;
                }

                // Add SEO data for each language
                foreach ($languages as $language) {
                    $langCode = $language['code'];
                    $isDefault = $language['is_default'] == 1;

                    // Find SEO translation for this language
                    $seoTranslation = null;
                    if (!empty($seoTranslationsByLang[$langCode])) {
                        $seoTranslation = $seoTranslationsByLang[$langCode];
                    }

                    // Use translation if available, otherwise use base settings for default language
                    $row[] = strval($seoTranslation['seo_title'] ?? ($isDefault ? ($baseSeoSettings['title'] ?? '') : ''));
                    $row[] = strval($seoTranslation['seo_description'] ?? ($isDefault ? ($baseSeoSettings['description'] ?? '') : ''));
                    $row[] = strval($seoTranslation['seo_keywords'] ?? ($isDefault ? ($baseSeoSettings['keywords'] ?? '') : ''));
                    $row[] = strval($seoTranslation['seo_schema_markup'] ?? ($isDefault ? ($baseSeoSettings['schema_markup'] ?? '') : ''));
                }

                $all_data[] = $row;
            }

            // Output CSV
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="service_update_sample_with_data_multilanguage.csv"');

            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);

            foreach ($all_data as $row) {
                fputcsv($output, $row);
            }

            fclose($output);
            exit;
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/ServiceBulkController.php - downloadSampleForUpdate()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, "Something Went Wrong"), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function ServiceAddInstructions()
    {
        try {
            $filePath = (FCPATH . '/public/uploads/site/Service-Add-Instructions.pdf');
            $fileName = 'Service-Add-Instructions.pdf';
            if (file_exists($filePath)) {
                return $this->response->download($filePath, null)->setFileName($fileName);
            } else {
                $_SESSION['toastMessage'] = labels(CANNOT_DOWNLOAD, 'Cannot download');
                $_SESSION['toastMessageType'] = 'error';
                $this->session->markAsFlashdata('toastMessage');
                $this->session->markAsFlashdata('toastMessageType');
                return redirect()->to('admin/services')->withCookies();
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/ServiceBulkController.php - ServiceAddInstructions()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }

    public function ServiceUpdateInstructions()
    {
        try {
            $filePath = (FCPATH . '/public/uploads/site/Service-Update-Instructions.pdf');
            $fileName = 'Service-Update-Instructions.pdf';
            if (file_exists($filePath)) {
                return $this->response->download($filePath, null)->setFileName($fileName);
            } else {
                $_SESSION['toastMessage'] = labels(CANNOT_DOWNLOAD, 'Cannot download');
                $_SESSION['toastMessageType'] = 'error';
                $this->session->markAsFlashdata('toastMessage');
                $this->session->markAsFlashdata('toastMessageType');
                return redirect()->to('admin/services')->withCookies();
            }
        } catch (\Throwable $th) {
            log_the_responce($th, date("Y-m-d H:i:s") . '--> app/Controllers/partner/ServiceBulkController.php - ServiceUpdateInstructions()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
}
