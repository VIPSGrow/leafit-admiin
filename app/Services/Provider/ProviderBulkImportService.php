<?php

namespace App\Services\provider;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Config\Database;
use App\Services\utility\SlugService;
use App\Models\Seo_model;

class ProviderBulkImportService
{
    protected $db;
    protected Seo_model $seoModel;
    protected SlugService $slugService;
    protected SlotSettingsService $slotSettingsService;

    /**
     * Header name (spreadsheet) → POST-style key consumed by
     * SlotSettingsService::extractFromPost(). 'Advance Booking Days'
     * is intentionally not listed — it is reused for max_advance_booking_days.
     */
    private const SLOT_SETTINGS_HEADERS = [
        'Slot Interval'            => 'slot_interval',
        'Allow Multiple Bookings'  => 'allow_multiple_bookings',
        'Slot Capacity'            => 'slot_capacity',
        'Min Advance Booking'      => 'min_advance_booking',
        'Min Advance Booking Unit' => 'min_advance_booking_unit',
        'Same Day Booking'         => 'same_day_booking',
        'Buffer Before'            => 'buffer_before',
        'Buffer After'             => 'buffer_after',
    ];

    protected int $chunkSize = 50;
    protected bool $debug = true;

    /**
     * Header name (spreadsheet) → DB column for partner_details table.
     * Only fields present in the uploaded file will be included in the payload.
     */
    private const PROVIDER_FIELD_MAP = [
        'Type'                      => 'type',
        'Visiting Charge'           => 'visiting_charges',
        'Advance Booking Days'      => 'advance_booking_days',
        'Number of Members'         => 'number_of_members',
        'At Store'                  => 'at_store',
        'At Doorstep'               => 'at_doorstep',
        'Need Approval for Service' => 'need_approval_for_the_service',
        'Address'                   => 'address',
        'Is Approved'               => 'is_approved',
        'Max Serviceable Distance'  => 'max_serviceable_distance',
        'Banner Image'              => 'banner',
    ];

    /**
     * Header name (spreadsheet) → DB column for users table.
     */
    private const USER_FIELD_MAP = [
        'Email'        => 'email',
        'Phone'        => 'phone',
        'Country Code' => 'country_code',
        'Password'     => 'password',
        'City'         => 'city',
        'Latitude'     => 'latitude',
        'Longitude'    => 'longitude',
        'Image'        => 'image',
        'Login Type'   => 'loginType',
    ];

    /**
     * Headers that must be present for insert mode.
     */
    private const REQUIRED_HEADERS_INSERT = [
        'Type',
        'Email',
        'Phone',
        'Country Code',
        'Password',
        'Login Type',
    ];

    /**
     * Headers that must be present for update mode.
     */
    private const REQUIRED_HEADERS_UPDATE = [
        'User ID',
    ];

    public function __construct()
    {
        $this->db = Database::connect();
        $this->slugService = new SlugService();
        $this->seoModel = new Seo_model();
        $this->slotSettingsService = new SlotSettingsService();
    }

    /* ============================================================
     | ENTRY POINT
     ============================================================ */

    public function handle($file, string $mode)
    {
        // Copy to a temp file with .xlsx extension so the Xlsx reader can open it as a zip
        $tmpPath = tempnam(sys_get_temp_dir(), 'bulk_') . '.xlsx';
        copy($file->getTempName(), $tmpPath);

        $reader = IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load($tmpPath);
        $sheet = $spreadsheet->getActiveSheet();
        [$headers, $rows] = $this->parseCsv($sheet);
        $spreadsheet->disconnectWorksheets();
        @unlink($tmpPath);

        $missing = $this->validateRequiredHeaders($headers, $mode);
        if (!empty($missing)) {
            $this->log('Missing required columns: ' . implode(', ', $missing));
            return ErrorResponse(
                'Missing required columns: ' . implode(', ', $missing),
                true, [], [], 200, csrf_token(), csrf_hash()
            );
        }

        $languages = fetch_details('languages', [], ['id', 'code', 'is_default']);
        $defaultLang = array_filter($languages, fn($lang) => $lang['is_default'] == 1)[0]['code'] ?? 'en';

        // Fetch visible custom field definitions once for all chunks
        $customFields = $this->loadCustomFieldDefinitions();

        foreach (array_chunk($rows, $this->chunkSize) as $index => $chunk) {
            $result = $this->processChunk(
                $chunk,
                $headers,
                $languages,
                $defaultLang,
                $mode,
                $customFields
            );

            // processChunk returns an ErrorResponse on a handled failure
            if ($result !== null) {
                $this->log('Chunk #' . ($index + 1) . ' aborted with handled error');
                return $result;
            }
        }

        return successResponse("Providers imported successfully");
    }

    /* ============================================================
     | CORE CHUNK HANDLER
     ============================================================ */

    private function processChunk(array $rows, array $headers, array $languages, string $defaultLang, string $mode, array $customFields)
    {
        $this->db->transStart();

        $rowIndex = 0;
        foreach ($rows as $row) {
            $rowIndex++;
            $rowTag = 'row#' . $rowIndex
                . ' UserID=' . ($this->getValue($row, $headers, 'User ID') ?: '-')
                . ' Email=' . ($this->getValue($row, $headers, 'Email') ?: '-');

          try {
            $translations = $this->extractTranslationsFromRow($row, $headers, $languages);
            $seoTranslations = $this->extractSeoTranslationsFromRow($row, $headers, $languages);

            $partnerIdRaw = $this->getValue($row, $headers, 'User ID');
            $partnerId = ($partnerIdRaw !== '' && $partnerIdRaw !== null) ? (int) $partnerIdRaw : null;

            $slug = $this->slugService->resolve(
                $partnerId ? $this->getExistingSlug($partnerId) : null,
                null,
                $translations[$defaultLang]['company_name'] ?? 'provider',
                'partner_details',
                $partnerId
            );

            $provider = $this->buildProviderPayload($row, $headers, $translations, $defaultLang, $slug);
            $user = $this->buildUserPayload($row, $headers, $translations, $defaultLang);

            // --- Validations ---

            // Latitude/Longitude range validation
            if (isset($user['latitude'])) {
                $lat = (float) $user['latitude'];
                if (!is_numeric($user['latitude']) || $lat < -90 || $lat > 90) {
                    $this->db->transRollback();
                    return ErrorResponse(labels(PLEASE_ENTER_VALID_LATITUDE, "Please enter valid latitude"), true, [], [], 200, csrf_token(), csrf_hash());
                }
            }
            if (isset($user['longitude'])) {
                $lng = (float) $user['longitude'];
                if (!is_numeric($user['longitude']) || $lng < -180 || $lng > 180) {
                    $this->db->transRollback();
                    return ErrorResponse(labels(PLEASE_ENTER_VALID_LONGITUDE, "Please enter valid longitude"), true, [], [], 200, csrf_token(), csrf_hash());
                }
            }

            // Max serviceable distance validation (numeric, >= 0 when present)
            if (isset($provider['max_serviceable_distance'])
                && $provider['max_serviceable_distance'] !== null
                && ((float) $provider['max_serviceable_distance']) < 0
            ) {
                $this->db->transRollback();
                return ErrorResponse(labels('max_serviceable_distance_must_be_0_or_greater', 'Max serviceable distance must be 0 or greater'), true, [], [], 200, csrf_token(), csrf_hash());
            }

            // Password strength validation (insert only)
            if ($mode === 'insert' && isset($user['password'])) {
                $passwordErrors = validate_password_strength($user['password']);
                if (!empty($passwordErrors)) {
                    $this->db->transRollback();
                    return ErrorResponse(implode(', ', $passwordErrors), true, [], [], 200, csrf_token(), csrf_hash());
                }
            }

            // Login type uniqueness check (insert only)
            if ($mode === 'insert') {
                $loginType = $user['loginType'] ?? 'phone';
                $uniqueError = $this->checkLoginTypeUniqueness($user, $loginType);
                if ($uniqueError) {
                    $this->db->transRollback();
                    return ErrorResponse($uniqueError, true, [], [], 200, csrf_token(), csrf_hash());
                }
            }

            // Shift timing validation (mirrors add_partner working-hours rules)
            if ($this->hasTimingHeaders($headers)) {
                $shiftError = $this->validateShiftTimings($row, $headers);
                if ($shiftError !== null) {
                    $this->db->transRollback();
                    return ErrorResponse($shiftError, true, [], [], 200, csrf_token(), csrf_hash());
                }
            }

            // Concurrent-booking validation: slot_capacity only required/used
            // when allow_multiple_bookings is enabled (same conditional as the
            // add_partner Scheduling Configuration step).
            if ($this->hasSlotSettingsHeaders($headers)) {
                $capacityError = $this->validateConcurrentBooking($row, $headers);
                if ($capacityError !== null) {
                    $this->db->transRollback();
                    return ErrorResponse($capacityError, true, [], [], 200, csrf_token(), csrf_hash());
                }
            }

            // Collect other images from any Other Image[N] columns
            $otherImages = $this->collectOtherImages($row, $headers);
            if (!empty($otherImages)) {
                $provider['other_images'] = json_encode($otherImages);
            }

            // $this->debug('Prepared payload', compact('provider', 'user'));

            if ($mode === 'insert') {
                $partnerId = $this->insertBatch($user, $provider);
            } else {
                $this->updateBatch($partnerId, $user, $provider);
            }

            // Save partner timings only if any day columns are present
            if ($this->hasTimingHeaders($headers)) {
                $timings = $this->buildTimingsPayload($row, $headers, $partnerId);
                $this->savePartnerTimings($timings, $partnerId, $mode);
            }

            // Save provider slot settings (scheduling configuration).
            // Always create a row on insert (mirrors the add_partner form);
            // on update only touch it when slot columns are present so a
            // partial update never resets settings to defaults.
            if ($mode === 'insert' || $this->hasSlotSettingsHeaders($headers)) {
                $slotInput = $this->buildSlotSettingsInput($row, $headers);
                $this->slotSettingsService->save(
                    (int) $partnerId,
                    $this->slotSettingsService->extractFromPost($slotInput)
                );
            }

            // Save custom field values (bank_details + documents)
            $customFieldValues = $this->collectCustomFieldValues($row, $headers, $customFields);
            if (!empty($customFieldValues)) {
                $this->upsertPartnerCustomFields((int) $partnerId, $customFieldValues);
            }

            $this->saveBulkPartnerTranslations($partnerId, $translations);

            // SEO Meta Image is a single non-translated field
            $seoMetaImage = $this->hasHeader($headers, 'SEO Meta Image')
                ? trim((string) $this->getValue($row, $headers, 'SEO Meta Image'))
                : null;

            $this->saveBulkPartnerSeoSettings($partnerId, $seoTranslations, $languages, $seoMetaImage);

            // Surface silent DB failures (transStart suppresses query
            // exceptions when DBDebug is off — without this the import
            // looks "successful" while the transaction is doomed).
            $dbErr = $this->db->error();
            if (!empty($dbErr['code'])) {
                $this->log("DB error after {$rowTag}: " . json_encode($dbErr)
                    . ' lastQuery=' . (string) $this->db->getLastQuery());
            }
          } catch (\Throwable $e) {
              $this->log("EXCEPTION on {$rowTag}: " . $e->getMessage()
                  . ' @ ' . $e->getFile() . ':' . $e->getLine());
              $this->db->transRollback();
              throw $e;
          }
        }

        $this->db->transComplete();

        $status = $this->db->transStatus();
      
        if (!$status) {
            return ErrorResponse(
                labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'),
                true, [], [], 200, csrf_token(), csrf_hash()
            );
        }

        return null;
    }

    /* ============================================================
     | INSERT / UPDATE
     ============================================================ */

    private function insertBatch(array $user, array $provider): int
    {
        $user['password'] = (new \IonAuth\Models\IonAuthModel())
            ->hashPassword($user['password']);
        $user['ip_address'] = \Config\Services::request()->getIPAddress();
        $user['created_on'] = time();
        $user['created_at'] = date('Y-m-d H:i:s');
        $user['updated_at'] = date('Y-m-d H:i:s');

        $this->db->table('users')->insert($user);
        $userId = $this->db->insertID();
      
        if (!$userId) {
            throw new \RuntimeException('users insert failed (insertID=0). DB: '
                . json_encode($this->db->error())
                . ' lastQuery=' . (string) $this->db->getLastQuery());
        }

        $this->db->table('users_groups')->insert([
            'user_id' => $userId,
            'group_id' => 3
        ]);

        $provider['partner_id'] = $userId;
        $provider['created_at'] = date('Y-m-d H:i:s');
        $provider['updated_at'] = date('Y-m-d H:i:s');
        $this->db->table('partner_details')->insert($provider);

        return $userId;
    }

    private function updateBatch(int $partnerId, array $user, array $provider)
    {
        // Password is not updated during bulk update
        unset($user['password']);

        // Only run updates if there are fields to update beyond the always-set keys
        if (count($user) > 1) { // more than just 'active'
            $user['updated_at'] = date('Y-m-d H:i:s');
            $this->db->table('users')->update($user, ['id' => $partnerId]);
        }
        if (count($provider) > 1) { // more than just 'slug'
            $provider['updated_at'] = date('Y-m-d H:i:s');
            $this->db->table('partner_details')->update($provider, ['partner_id' => $partnerId]);
        }
    }

    /**
     * Check login type uniqueness for a new provider.
     * Email login: email must be unique among providers.
     * Phone login: phone + country_code must be unique among providers.
     */
    private function checkLoginTypeUniqueness(array $user, string $loginType): ?string
    {
        $builder = $this->db->table('users u')
            ->select('u.id')
            ->join('users_groups ug', 'ug.user_id = u.id')
            ->where('ug.group_id', 3);

        if ($loginType === 'email') {
            $email = $user['email'] ?? '';
            if ($email === '') {
                return labels('please_enter_email', 'Email is required for email login type');
            }
            $builder->where('u.email', $email)
                    ->where('u.loginType', 'email');
            $exists = $builder->countAllResults() > 0;
            if ($exists) {
                return $email . ' - ' . labels('email_already_exists', 'Email already exists for a provider');
            }
        } else {
            $phone = $user['phone'] ?? '';
            $countryCode = $user['country_code'] ?? '';
            if ($phone === '') {
                return labels('please_enter_phone', 'Phone is required for phone login type');
            }
            $builder->where('u.phone', $phone)
                    ->where('u.country_code', $countryCode)
                    ->where('u.loginType', 'phone');
            $exists = $builder->countAllResults() > 0;
            if ($exists) {
                return $phone . ' - ' . labels(PHONE_NUMBER_ALREADY_EXISTS_PLEASE_USE_ANOTHER_ONE, 'Phone number already exists please use another one');
            }
        }

        return null;
    }

    /* ============================================================
     | PAYLOAD BUILDERS
     ============================================================ */

    private function buildProviderPayload($row, $headers, $translations, $defaultLang, $slug)
    {
        $payload = ['slug' => $slug];

        // Map only the columns that exist in the uploaded file
        foreach (self::PROVIDER_FIELD_MAP as $headerName => $dbColumn) {
            if (!$this->hasHeader($headers, $headerName)) {
                continue;
            }

            $value = $this->getValue($row, $headers, $headerName);

            // Special handling per field
            if ($dbColumn === 'is_approved') {
                $value = in_array($value, ['0', '1', 0, 1], true) ? $value : 0;
            }
            if ($dbColumn === 'number_of_members') {
                $type = (int) $this->getValue($row, $headers, 'Type');
                $value = ($type === 1) ? $value : '1';
            }
            if ($dbColumn === 'max_serviceable_distance') {
                $value = ($value !== '' && is_numeric($value)) ? $value : null;
            }

            $payload[$dbColumn] = $value;
        }

        // Translation-derived fields: include only when translation data exists
        $defaultTranslation = $translations[$defaultLang] ?? [];
        if (!empty($defaultTranslation['company_name'])) {
            $payload['company_name'] = $defaultTranslation['company_name'];
        }
        if (!empty($defaultTranslation['about'])) {
            $payload['about'] = $defaultTranslation['about'];
        }
        if (!empty($defaultTranslation['long_description'])) {
            $payload['long_description'] = $defaultTranslation['long_description'];
        }

        return $payload;
    }

    private function buildUserPayload($row, $headers, $translations, $defaultLang)
    {
        $payload = ['active' => 1];

        // Map only the columns that exist in the uploaded file
        foreach (self::USER_FIELD_MAP as $headerName => $dbColumn) {
            if (!$this->hasHeader($headers, $headerName)) {
                continue;
            }

            $value = $this->getValue($row, $headers, $headerName);

            // Special handling per field
            if ($dbColumn === 'email') {
                $value = strtolower(trim($value));
            }
            if ($dbColumn === 'country_code') {
                $value = strpos($value, '+') === 0 ? $value : '+' . $value;
            }
            if ($dbColumn === 'latitude' || $dbColumn === 'longitude') {
                $value = is_numeric($value) ? number_format((float) $value, 7, '.', '') : $value;
            }
            if ($dbColumn === 'loginType') {
                $value = strtolower(trim($value));
                $value = in_array($value, ['email', 'phone']) ? $value : 'phone';
            }

            $payload[$dbColumn] = $value;
        }

        // Username from translations (default language)
        $defaultTranslation = $translations[$defaultLang] ?? [];
        if (!empty($defaultTranslation['username'])) {
            $payload['username'] = $defaultTranslation['username'];
        }

        return $payload;
    }

    /* ============================================================
     | OTHER IMAGES
     ============================================================ */

    /**
     * Collect other image paths from any Other Image[N] columns present in the file.
     */
    private function collectOtherImages(array $row, array $headers): array
    {
        $images = [];
        foreach ($headers as $headerName => $colIndex) {
            if (preg_match('/^other image\[(\d+)\]$/i', $headerName, $matches)) {
                $value = isset($row[$colIndex]) ? trim((string) $row[$colIndex]) : '';
                if ($value !== '') {
                    $images[(int) $matches[1]] = $value;
                }
            }
        }
        ksort($images);
        return array_values($images);
    }

    /* ============================================================
     | PARTNER TIMINGS
     ============================================================ */

    /**
     * Spreadsheet day label → provider_shifts.day enum value.
     */
    private const TIMING_DAYS = [
        'Monday'    => 'monday',
        'Tuesday'   => 'tuesday',
        'Wednesday' => 'wednesday',
        'Thursday'  => 'thursday',
        'Friday'    => 'friday',
        'Saturday'  => 'saturday',
        'Sunday'    => 'sunday',
    ];

    /**
     * Ordered timing header names for the given shift count.
     * Shift 1 keeps the legacy un-numbered columns for backward
     * compatibility; shifts 2..N use "<Day> Shift <n> Start/End Time".
     */
    private function dayTimingHeaders(int $shiftCount): array
    {
        $shiftCount = max(1, $shiftCount);
        $headers = [];
        foreach (array_keys(self::TIMING_DAYS) as $day) {
            $headers[] = "$day Start Time";
            $headers[] = "$day End Time";
            for ($n = 2; $n <= $shiftCount; $n++) {
                $headers[] = "$day Shift $n Start Time";
                $headers[] = "$day Shift $n End Time";
            }
            $headers[] = "$day Is Open";
        }
        return $headers;
    }

    /**
     * Parallel sample values for dayTimingHeaders() (split-shift demo).
     */
    private function dayTimingSampleValues(int $shiftCount): array
    {
        $shiftCount = max(1, $shiftCount);
        $vals = [];
        foreach (array_keys(self::TIMING_DAYS) as $day) {
            $vals[] = '09:00:00';
            $vals[] = '13:00:00';
            for ($n = 2; $n <= $shiftCount; $n++) {
                $vals[] = '14:00:00';
                $vals[] = '18:00:00';
            }
            $vals[] = '1';
        }
        return $vals;
    }

    /**
     * True if the sheet carries any timing column for $dayName
     * (legacy Start/End/Is Open or any numbered shift column).
     * $headers keys are lowercased by parseCsv().
     */
    private function dayHasTimingHeaders(array $headers, string $dayName): bool
    {
        if ($this->hasHeader($headers, "$dayName Start Time")
            || $this->hasHeader($headers, "$dayName End Time")
            || $this->hasHeader($headers, "$dayName Is Open")
        ) {
            return true;
        }
        $prefix = strtolower($dayName) . ' shift ';
        foreach (array_keys($headers) as $h) {
            if (strpos((string) $h, $prefix) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Collect complete shift pairs for a day, ordered and compactly
     * renumbered from 1. Shift 1 = legacy "<Day> Start/End Time";
     * shifts 2..N = "<Day> Shift <n> Start/End Time". Incomplete or
     * blank pairs are dropped.
     *
     * @return array<int, array{start:string, end:string}>
     */
    private function collectDayShifts(array $row, array $headers, string $dayName): array
    {
        $pairs = [
            1 => [
                'start' => trim((string) $this->getValue($row, $headers, "$dayName Start Time")),
                'end'   => trim((string) $this->getValue($row, $headers, "$dayName End Time")),
            ],
        ];

        $startRe = '/^' . preg_quote(strtolower($dayName), '/') . ' shift (\d+) start time$/';
        $endRe   = '/^' . preg_quote(strtolower($dayName), '/') . ' shift (\d+) end time$/';

        foreach ($headers as $name => $col) {
            $cell = isset($row[$col]) ? trim((string) $row[$col]) : '';
            if (preg_match($startRe, (string) $name, $m)) {
                $pairs[(int) $m[1]]['start'] = $cell;
            } elseif (preg_match($endRe, (string) $name, $m)) {
                $pairs[(int) $m[1]]['end'] = $cell;
            }
        }

        ksort($pairs);

        $shifts = [];
        foreach ($pairs as $p) {
            $start = $p['start'] ?? '';
            $end   = $p['end'] ?? '';
            if ($start !== '' && $end !== '') {
                // Store canonical HH:MM:SS (input may be 12h "9:00 AM",
                // 24h, or an Excel time fraction). Validation has already
                // rejected unparseable values before save.
                $shifts[] = [
                    'start' => $this->normalizeTime($start) ?? $start,
                    'end'   => $this->normalizeTime($end) ?? $end,
                ];
            }
        }
        return $shifts;
    }

    /**
     * One record per present day:
     *   day, shifts (complete pairs), is_open_raw (null when not supplied).
     */
    private function buildTimingsPayload(array $row, array $headers, int $partnerId): array
    {
        $records = [];
        foreach (self::TIMING_DAYS as $dayName => $dayValue) {
            if (!$this->dayHasTimingHeaders($headers, $dayName)) {
                continue;
            }

            $hasOpenCol = $this->hasHeader($headers, "$dayName Is Open");
            $openRaw    = $hasOpenCol
                ? trim((string) $this->getValue($row, $headers, "$dayName Is Open"))
                : '';

            $records[] = [
                'partner_id'  => $partnerId,
                'day'         => $dayValue,
                'shifts'      => $this->collectDayShifts($row, $headers, $dayName),
                'is_open_raw' => ($openRaw === '') ? null : $openRaw,
            ];
        }
        return $records;
    }

    /**
     * Persist multi-shift schedule.
     *  - insert: write all provided shifts (default-hours fallback when a
     *    present day has no complete pair).
     *  - update: replace the whole day when shift times are supplied;
     *    when only Is Open is supplied, just flip the flag (never wipe an
     *    existing schedule on blank timing cells).
     */
    private function savePartnerTimings(array $records, int $partnerId, string $mode): void
    {
        foreach ($records as $rec) {
            $day       = $rec['day'];
            $shifts    = $rec['shifts'];
            $isOpenRaw = $rec['is_open_raw'];
            $hasShifts = !empty($shifts);

            if ($mode === 'update' && !$hasShifts) {
                if ($isOpenRaw !== null) {
                    $this->db->table('provider_shifts')
                        ->where('partner_id', $partnerId)
                        ->where('day', $day)
                        ->update(['is_open' => $this->toIntFlag($isOpenRaw)]);
                }
                continue;
            }

            if ($mode === 'update') {
                $this->db->table('provider_shifts')
                    ->where('partner_id', $partnerId)
                    ->where('day', $day)
                    ->delete();
            }

            $isOpen = $isOpenRaw !== null ? $this->toIntFlag($isOpenRaw) : 1;

            // Present day with no usable times (e.g. closed): keep one
            // default-hours row so the day still exists in the schedule.
            if (!$hasShifts) {
                $shifts = [['start' => '09:00:00', 'end' => '18:00:00']];
            }

            $shiftNumber = 1;
            foreach ($shifts as $s) {
                $this->db->table('provider_shifts')->insert([
                    'partner_id'   => $partnerId,
                    'day'          => $day,
                    'shift_number' => $shiftNumber++,
                    'opening_time' => $s['start'],
                    'closing_time' => $s['end'],
                    'is_open'      => $isOpen,
                ]);
            }
        }
    }

    private function toIntFlag($v): int
    {
        return in_array(strtolower(trim((string) $v)), ['1', 'on', 'true', 'yes'], true) ? 1 : 0;
    }

    /* ============================================================
     | TIMING / SLOT VALIDATION (mirrors add_partner rules)
     ============================================================ */

    /**
     * Validate working-hours shifts for one row. Returns the first
     * human-readable error, or null when valid. Each provided shift needs
     * a valid start AND end, end strictly after start, and shifts within a
     * day must not overlap — same intent as the add_partner working-hours
     * step. Fully blank shift = "not provided" (allowed).
     */
    private function validateShiftTimings(array $row, array $headers): ?string
    {
        foreach (self::TIMING_DAYS as $dayName => $dayValue) {
            if (!$this->dayHasTimingHeaders($headers, $dayName)) {
                continue;
            }

            $pairs = [
                1 => [
                    'start' => trim((string) $this->getValue($row, $headers, "$dayName Start Time")),
                    'end'   => trim((string) $this->getValue($row, $headers, "$dayName End Time")),
                ],
            ];
            $startRe = '/^' . preg_quote(strtolower($dayName), '/') . ' shift (\d+) start time$/';
            $endRe   = '/^' . preg_quote(strtolower($dayName), '/') . ' shift (\d+) end time$/';
            foreach ($headers as $name => $col) {
                $cell = isset($row[$col]) ? trim((string) $row[$col]) : '';
                if (preg_match($startRe, (string) $name, $m)) {
                    $pairs[(int) $m[1]]['start'] = $cell;
                } elseif (preg_match($endRe, (string) $name, $m)) {
                    $pairs[(int) $m[1]]['end'] = $cell;
                }
            }
            ksort($pairs);

            $ranges = [];
            foreach ($pairs as $num => $p) {
                $start = $p['start'] ?? '';
                $end   = $p['end'] ?? '';

                if ($start === '' && $end === '') {
                    continue; // shift not provided
                }
                $where = ' - ' . labels($dayValue, $dayName) . ' ' . labels('shift', 'Shift') . ' ' . $num;

                if ($start === '' || $end === '') {
                    return labels('incomplete_shift_time', 'Incomplete working hours') . $where;
                }
                $s = $this->timeToSeconds($start);
                $e = $this->timeToSeconds($end);
                if ($s === null || $e === null) {
                    return labels('invalid_shift_time', 'Invalid time format (use HH:MM or HH:MM:SS)') . $where;
                }
                if ($e <= $s) {
                    return labels('shift_end_after_start', 'End time must be after start time') . $where;
                }
                $ranges[] = ['s' => $s, 'e' => $e];
            }

            usort($ranges, fn($a, $b) => $a['s'] <=> $b['s']);
            for ($i = 1, $cnt = count($ranges); $i < $cnt; $i++) {
                if ($ranges[$i]['s'] < $ranges[$i - 1]['e']) {
                    return labels('shift_times_overlap', 'Shift timings overlap')
                        . ' - ' . labels($dayValue, $dayName);
                }
            }
        }

        return null;
    }

    /**
     * slot_capacity is only meaningful when allow_multiple_bookings is on
     * (same conditional as the add_partner Scheduling Configuration step).
     * Enabled → capacity must be an integer 1-100. Disabled → ignored.
     */
    private function validateConcurrentBooking(array $row, array $headers): ?string
    {
        if (!$this->hasHeader($headers, 'Allow Multiple Bookings')) {
            return null;
        }
        if ($this->toIntFlag($this->getValue($row, $headers, 'Allow Multiple Bookings')) !== 1) {
            return null; // conditional: capacity not required when toggle off
        }

        if (!$this->hasHeader($headers, 'Slot Capacity')) {
            return labels('concurrent_capacity_required', 'Concurrent booking capacity is required when multiple bookings are allowed');
        }
        $capacity = trim((string) $this->getValue($row, $headers, 'Slot Capacity'));
        if ($capacity === '' || !ctype_digit($capacity) || (int) $capacity < 1 || (int) $capacity > 100) {
            return labels('concurrent_capacity_required', 'Concurrent booking capacity is required when multiple bookings are allowed');
        }
        return null;
    }

    private function timeToSeconds(string $value): ?int
    {
        $norm = $this->normalizeTime($value);
        if ($norm === null) {
            return null;
        }
        [$h, $m, $s] = array_map('intval', explode(':', $norm));
        return $h * 3600 + $m * 60 + $s;
    }

    /**
     * Normalize a spreadsheet time cell to canonical 24h "HH:MM:SS".
     * Accepts:
     *   - 12-hour with meridiem: "9:00 AM", "1:00 PM", "01:30:15 pm"
     *   - 24-hour: "13:00", "09:00:00", "9:5"
     *   - Excel time serial (fraction of a day): "0.375" → 09:00:00
     *   - Bare hour: "9" → 09:00:00
     * Returns null when the value cannot be parsed (caller flags it).
     */
    private function normalizeTime($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $v = trim((string) $value);
        if ($v === '') {
            return null;
        }

        // Excel stores times as a fraction of a 24h day.
        if (is_numeric($v)) {
            $f = (float) $v;
            if ($f >= 0 && $f < 1) {
                return gmdate('H:i:s', (int) round($f * 86400));
            }
            if ($f >= 0 && $f <= 23 && floor($f) == $f) {
                return sprintf('%02d:00:00', (int) $f);
            }
            return null;
        }

        // Normalize spacing/case so "9:00am", "9:00 AM", "9:00\u{00A0}AM" all parse.
        $clean = strtoupper(preg_replace('/\s+/u', ' ', str_replace("\xC2\xA0", ' ', $v)));

        foreach (['g:i A', 'g:i:s A', 'G:i', 'G:i:s', 'H:i', 'H:i:s'] as $fmt) {
            $dt = \DateTime::createFromFormat('!' . $fmt, $clean);
            if ($dt instanceof \DateTime) {
                // PHP 8.2 returns false when there are no errors/warnings.
                $errs = \DateTime::getLastErrors();
                if ($errs === false
                    || (empty($errs['warning_count']) && empty($errs['error_count']))
                ) {
                    return $dt->format('H:i:s');
                }
            }
        }

        // Last resort: let strtotime handle odd-but-valid inputs.
        $ts = strtotime($clean);
        return $ts !== false ? date('H:i:s', $ts) : null;
    }

    /* ============================================================
     | CUSTOM FIELDS
     ============================================================ */

    /**
     * Fetch visible custom field definitions for bank_details and documents groups.
     */
    private function loadCustomFieldDefinitions(): array
    {
        return $this->db->table('custom_fields')
            ->select(['id', 'field_label', 'field_type', 'field_group'])
            ->whereIn('field_group', ['bank_details', 'documents'])
            ->where('visible', 1)
            ->orderBy('field_group', 'ASC')
            ->orderBy('sort_order', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function collectCustomFieldValues(array $row, array $headers, array $customFields): array
    {
        // Build a lookup of custom field IDs that exist in the DB
        $validIds = [];
        foreach ($customFields as $cf) {
            $validIds[(int) $cf['id']] = true;
        }

        // Scan headers for CF_{id} prefixed columns
        $values = [];
        foreach ($headers as $headerName => $colIndex) {
            if (preg_match('/^cf_(\d+)\s*:/i', $headerName, $matches)) {
                $cfId = (int) $matches[1];
                if (isset($validIds[$cfId]) && isset($row[$colIndex])) {
                    $values[$cfId] = trim((string) $row[$colIndex]);
                }
            }
        }
        return $values;
    }

    private function upsertPartnerCustomFields(int $partnerId, array $valuesById): void
    {
        if ($partnerId <= 0 || empty($valuesById)) {
            return;
        }

        $valuesSql = [];
        $deletionIds = [];

        foreach ($valuesById as $customFieldId => $rawValue) {
            $customFieldId = (int) $customFieldId;
            if ($customFieldId <= 0) {
                continue;
            }

            if (is_array($rawValue)) {
                $rawValue = json_encode($rawValue, JSON_UNESCAPED_SLASHES);
            }

            if ($rawValue === '' || $rawValue === null) {
                $deletionIds[] = $customFieldId;
                continue;
            }

            $valuesSql[] = '(' . (int) $partnerId . ',' . (int) $customFieldId . ',' . $this->db->escape((string) $rawValue) . ')';
        }

        if (!empty($deletionIds)) {
            $this->db->table('partner_custom_fields')
                ->where('partner_id', $partnerId)
                ->whereIn('custom_field_id', $deletionIds)
                ->delete();
        }

        if (!empty($valuesSql)) {
            $sql = 'INSERT INTO `partner_custom_fields` (`partner_id`, `custom_field_id`, `value`) VALUES '
                . implode(',', $valuesSql)
                . ' ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)';

            $this->db->query($sql);
        }
    }

    /* ============================================================
     | SAMPLE FILE GENERATION
     ============================================================ */

    /**
     * Generate headers and a single sample row for bulk insert.
     *
     * @return array{headers: array, rows: array<array>}
     */
    public function generateInsertSample(): array
    {
        $langData      = $this->getLanguageHeaders();
        $languages     = $langData['languages'];
        $customFields  = $this->loadCustomFieldDefinitions();

        // Core headers (language headers will be prepended, SEO appended)
        $headers = [
            'Type', 'Visiting Charge', 'Advance Booking Days', 'Number of Members',
            'At Store', 'At Doorstep', 'Need Approval for Service', 'Address', 'Is Approved',
            'Max Serviceable Distance',
            'Email', 'Phone', 'Country Code', 'Password', 'Login Type',
            'City', 'Latitude', 'Longitude',
            'Slot Interval', 'Allow Multiple Bookings', 'Slot Capacity',
            'Min Advance Booking', 'Min Advance Booking Unit', 'Same Day Booking',
            'Buffer Before', 'Buffer After',
            'Image', 'Banner Image',
            'Other Image[1]', 'Other Image[2]',
        ];

        // Day timing headers (sample demonstrates 2 shifts per day).
        $insertSampleShifts = 2;
        array_splice(
            $headers,
            array_search('Slot Interval', $headers, true),
            0,
            $this->dayTimingHeaders($insertSampleShifts)
        );

        // Dynamic custom field headers
        foreach ($customFields as $cf) {
            $headers[] = 'CF_' . $cf['id'] . ': ' . ($cf['field_label'] ?? 'Custom Field');
        }

        // Prepend language headers, append SEO Meta Image + per-language SEO headers
        array_splice($headers, 0, 0, $langData['headers']);
        $headers[] = 'SEO Meta Image';
        $headers = array_merge($headers, $langData['seo_headers']);

        // --- Sample row ---
        $row = [];

        // Translation sample values (grouped by field, then by language)
        $fieldSamples = ['SampleUser', 'Sample Company', 'About this provider', 'Detailed description of the provider'];
        foreach ($fieldSamples as $sample) {
            foreach ($languages as $lang) {
                $row[] = $sample . ' (' . $lang['code'] . ')';
            }
        }

        // Core fields (timing values injected between Longitude and the
        // Slot block to stay aligned with the spliced day headers)
        $row = array_merge(
            $row,
            [
                '1', '60', '365', '3', '1', '1', '0',
                'test123 , near test', '1',
                '100',
                'sample_company@gmail.com', '4848945845', '91', '12345678', 'phone',
                'Test', '28.743580', '45.623705',
            ],
            $this->dayTimingSampleValues($insertSampleShifts),
            [
                '30', '0', '1',
                '1', 'hours', '1',
                '0', '0',
                'public/uploads/profile/test.png',
                'public/uploads/banner/test.png',
                'public/uploads/partner/test1.png',
                'public/uploads/partner/test2.png',
            ]
        );

        // Custom field sample values
        foreach ($customFields as $cf) {
            $row[] = $cf['field_type'] === 'file'
                ? 'public/uploads/sample_file.png'
                : 'Sample ' . $cf['field_label'];
        }

        // SEO Meta Image (non-translated, single column)
        $row[] = 'public/uploads/seo_settings/sample_image.png';

        // Per-language SEO sample values
        $seoSamples = ['Sample SEO Title', 'Sample SEO Description', 'keyword1, keyword2, keyword3', '{"@type":"LocalBusiness"}'];
        foreach ($seoSamples as $sample) {
            foreach ($languages as $lang) {
                $row[] = $sample . ' (' . $lang['code'] . ')';
            }
        }

        return ['headers' => $headers, 'rows' => [$row]];
    }

    /**
     * Generate headers and data rows for bulk update (pre-filled with existing provider data).
     *
     * @return array{headers: array, rows: array<array>}
     */
    public function generateUpdateSample(): array
    {
        $langData     = $this->getLanguageHeaders();
        $languages    = $langData['languages'];
        $customFields = $this->loadCustomFieldDefinitions();

        // Core headers
        $headers = [
            'ID', 'User ID',
            'Type', 'Visiting Charge', 'Advance Booking Days', 'Number of Members',
            'At Store', 'At Doorstep', 'Need Approval for Service', 'Address', 'Is Approved',
            'Max Serviceable Distance',
            'Email', 'Phone', 'Country Code', 'Login Type',
            'City', 'Latitude', 'Longitude',
            'Slot Interval', 'Allow Multiple Bookings', 'Slot Capacity',
            'Min Advance Booking', 'Min Advance Booking Unit', 'Same Day Booking',
            'Buffer Before', 'Buffer After',
            'Image', 'Banner Image',
        ];

        // Widen timing columns to the max shift count present across all
        // providers (same growth strategy as Other Image[N]).
        $maxShiftRow = $this->db->table('provider_shifts')
            ->selectMax('shift_number', 'm')
            ->get()
            ->getRowArray();
        $maxShifts = max(1, (int) ($maxShiftRow['m'] ?? 1));
        array_splice(
            $headers,
            array_search('Slot Interval', $headers, true),
            0,
            $this->dayTimingHeaders($maxShifts)
        );

        // Dynamic custom field headers
        foreach ($customFields as $cf) {
            $headers[] = 'CF_' . $cf['id'] . ': ' . ($cf['field_label'] ?? 'Custom Field');
        }

        // Insert language headers after 'User ID' (position 2)
        array_splice($headers, 2, 0, $langData['headers']);

        // Fetch all providers to determine max other images
        $providers = fetch_details('partner_details', [], [], '', 0, 'id', 'ASC');
        if (empty($providers)) {
            return ['headers' => $headers, 'rows' => []];
        }

        $maxOtherImages = 0;
        foreach ($providers as $p) {
            $imgs = json_decode($p['other_images'] ?? '', true);
            if (is_array($imgs)) {
                $maxOtherImages = max($maxOtherImages, count($imgs));
            }
        }
        for ($i = 1; $i <= $maxOtherImages; $i++) {
            $headers[] = "Other Image[$i]";
        }

        // Append SEO Meta Image (non-translated) then per-language SEO headers
        $headers[] = 'SEO Meta Image';
        $headers = array_merge($headers, $langData['seo_headers']);

        // Find default language
        $defaultLangCode = null;
        foreach ($languages as $lang) {
            if (isset($lang['is_default']) && $lang['is_default'] == 1) {
                $defaultLangCode = $lang['code'];
                break;
            }
        }

        $cfIds = array_map('intval', array_column($customFields, 'id'));
        $translationModel    = new \App\Models\TranslatedPartnerDetails_model();
        $seoTranslationModel = model('TranslatedPartnerSeoSettings_model');

        $allRows = [];

        foreach ($providers as $provider) {
            $partnerId   = (int) $provider['partner_id'];
            $otherImages = json_decode($provider['other_images'] ?? '', true);
            $user        = fetch_details('users', ['id' => $partnerId], ['username', 'email', 'phone', 'country_code', 'loginType', 'city', 'latitude', 'longitude', 'image']);
            $timings     = $this->db->table('provider_shifts')
                ->where('partner_id', $partnerId)
                ->orderBy('shift_number', 'ASC')
                ->get()
                ->getResultArray();
            $shiftsByDay = [];
            foreach ($timings as $sh) {
                $shiftsByDay[$sh['day']][] = $sh;
            }
            $slotSettings = $this->slotSettingsService->find($partnerId);

            if (empty($user) || empty($timings)) {
                continue;
            }
            $user = $user[0];

            // Translations
            $dbTranslations  = $translationModel->where('partner_id', $partnerId)->findAll();
            $translationsByLang = [];
            foreach ($dbTranslations as $t) {
                $translationsByLang[$t['language_code']] = $t;
            }

            // SEO data
            $this->seoModel->setTableContext('providers');
            $baseSeo         = $this->seoModel->getSeoSettingsByReferenceId($partnerId);
            $seoTranslations = $seoTranslationModel->getAllTranslationsForPartner($partnerId);

            // Base table fallback
            $base = [
                'username'         => $user['username'] ?? '',
                'company_name'     => $provider['company_name'] ?? '',
                'about'            => $provider['about'] ?? '',
                'long_description' => $provider['long_description'] ?? '',
            ];

            $rowData = [
                'ID'      => $provider['id'],
                'User ID' => $partnerId,
            ];

            // Translation fields with fallback
            $translationFields = [
                'Username'     => 'username',
                'Company Name' => 'company_name',
                'About Provider' => 'about',
                'Description'  => 'long_description',
            ];
            foreach ($translationFields as $headerLabel => $dbField) {
                foreach ($languages as $language) {
                    $code      = $language['code'];
                    $isDefault = ($language['is_default'] ?? 0) == 1;
                    $value     = '';

                    if (isset($translationsByLang[$code]) && !empty($translationsByLang[$code][$dbField])) {
                        $value = $translationsByLang[$code][$dbField];
                    } elseif ($isDefault) {
                        $value = $base[$dbField];
                    }

                    if ($dbField === 'long_description' && $value !== '') {
                        $value = strip_tags(htmlspecialchars_decode(stripslashes($value)), '<p><br>');
                    }

                    $rowData[$headerLabel . ' (' . $code . ')'] = $value;
                }
            }

            // Core provider/user fields
            $rowData['Type']                      = $provider['type'];
            $rowData['Visiting Charge']           = $provider['visiting_charges'];
            $rowData['Advance Booking Days']      = $provider['advance_booking_days'];
            $rowData['Number of Members']         = $provider['number_of_members'];
            $rowData['At Store']                  = $provider['at_store'];
            $rowData['At Doorstep']               = $provider['at_doorstep'];
            $rowData['Need Approval for Service'] = $provider['need_approval_for_the_service'];
            $rowData['Address']                   = $provider['address'];
            $rowData['Is Approved']               = $provider['is_approved'];
            $rowData['Max Serviceable Distance']  = $provider['max_serviceable_distance'];
            $rowData['Email']                     = $user['email'];
            $rowData['Phone']                     = $user['phone'];
            $rowData['Country Code']              = $user['country_code'];
            $rowData['Login Type']                = $user['loginType'] ?? 'phone';
            $rowData['City']                      = $user['city'];
            $rowData['Latitude']                  = $user['latitude'];
            $rowData['Longitude']                 = $user['longitude'];

            // Timings — emit in the exact header order produced by
            // dayTimingHeaders($maxShifts); blanks for absent shifts.
            foreach (self::TIMING_DAYS as $dayName => $dayValue) {
                $dayShifts = $shiftsByDay[$dayValue] ?? [];
                $first     = $dayShifts[0] ?? null;

                $rowData["$dayName Start Time"] = $first['opening_time'] ?? '';
                $rowData["$dayName End Time"]   = $first['closing_time'] ?? '';
                for ($n = 2; $n <= $maxShifts; $n++) {
                    $sh = $dayShifts[$n - 1] ?? null;
                    $rowData["$dayName Shift $n Start Time"] = $sh['opening_time'] ?? '';
                    $rowData["$dayName Shift $n End Time"]   = $sh['closing_time'] ?? '';
                }
                $rowData["$dayName Is Open"] = $first['is_open'] ?? 0;
            }

            // Scheduling configuration (provider_slot_settings).
            // 'Advance Booking Days' above already carries max_advance_booking_days.
            $rowData['Slot Interval']            = $slotSettings['slot_interval'];
            $rowData['Allow Multiple Bookings']  = $slotSettings['allow_multiple_bookings'];
            $rowData['Slot Capacity']            = $slotSettings['slot_capacity'];
            $rowData['Min Advance Booking']      = $slotSettings['min_advance_booking_value'];
            $rowData['Min Advance Booking Unit'] = $slotSettings['min_advance_booking_unit'];
            $rowData['Same Day Booking']         = $slotSettings['same_day_booking'];
            $rowData['Buffer Before']            = $slotSettings['buffer_before'];
            $rowData['Buffer After']             = $slotSettings['buffer_after'];

            $rowData['Image']        = $user['image'] ?? '';
            $rowData['Banner Image'] = $provider['banner'] ?? '';

            // Custom field values
            $cfValues = $this->getPartnerCustomFieldValuesById($partnerId, $cfIds);
            foreach ($customFields as $cf) {
                $headerLabel = 'CF_' . $cf['id'] . ': ' . ($cf['field_label'] ?? 'Custom Field');
                $rowData[$headerLabel] = $cfValues[(int) $cf['id']] ?? '';
            }

            // Other images
            if (is_array($otherImages)) {
                foreach ($otherImages as $idx => $img) {
                    $rowData['Other Image[' . ($idx + 1) . ']'] = $img ?? '';
                }
            }
            for ($i = count($otherImages ?? []); $i < $maxOtherImages; $i++) {
                $rowData['Other Image[' . ($i + 1) . ']'] = '';
            }

            // SEO Meta Image (non-translated)
            $rowData['SEO Meta Image'] = $baseSeo['image'] ?? '';

            // SEO translation data
            foreach ($languages as $language) {
                $code      = $language['code'];
                $isDefault = ($language['is_default'] ?? 0) == 1;

                $seoTrans = null;
                foreach ($seoTranslations as $st) {
                    if (($st['language_code'] ?? '') === $code) {
                        $seoTrans = $st;
                        break;
                    }
                }

                $rowData['SEO Title (' . $code . ')']         = $seoTrans['seo_title'] ?? ($isDefault ? ($baseSeo['title'] ?? '') : '');
                $rowData['SEO Description (' . $code . ')']   = $seoTrans['seo_description'] ?? ($isDefault ? ($baseSeo['description'] ?? '') : '');
                $rowData['SEO Keywords (' . $code . ')']      = $seoTrans['seo_keywords'] ?? ($isDefault ? ($baseSeo['keywords'] ?? '') : '');
                $rowData['SEO Schema Markup (' . $code . ')'] = $seoTrans['seo_schema_markup'] ?? ($isDefault ? ($baseSeo['schema_markup'] ?? '') : '');
            }

            $allRows[] = $rowData;
        }

        return ['headers' => $headers, 'rows' => $allRows];
    }

    /**
     * Fetch language headers for multi-language support in bulk import/export.
     */
    private function getLanguageHeaders(): array
    {
        $languages = fetch_details('languages', [], ['id', 'language', 'code', 'is_default'], '', '0', 'id', 'ASC');

        $translatableFields    = ['Username', 'Company Name', 'About Provider', 'Description'];
        $seoTranslatableFields = ['SEO Title', 'SEO Description', 'SEO Keywords', 'SEO Schema Markup'];

        $languageHeaders    = [];
        $seoLanguageHeaders = [];

        foreach ($translatableFields as $field) {
            foreach ($languages as $lang) {
                $languageHeaders[] = $field . ' (' . $lang['code'] . ')';
            }
        }
        foreach ($seoTranslatableFields as $field) {
            foreach ($languages as $lang) {
                $seoLanguageHeaders[] = $field . ' (' . $lang['code'] . ')';
            }
        }

        return [
            'headers'                  => $languageHeaders,
            'seo_headers'              => $seoLanguageHeaders,
            'languages'                => $languages,
            'translatable_fields'      => $translatableFields,
            'seo_translatable_fields'  => $seoTranslatableFields,
        ];
    }

    /**
     * Fetch custom field values for a partner by field IDs.
     */
    private function getPartnerCustomFieldValuesById(int $partnerId, array $fieldIds): array
    {
        if ($partnerId <= 0 || empty($fieldIds)) {
            return [];
        }

        $fieldIds = array_values(array_filter(array_map('intval', $fieldIds), fn($id) => $id > 0));
        if (empty($fieldIds)) {
            return [];
        }

        $rows = $this->db->table('partner_custom_fields')
            ->select(['custom_field_id', 'value'])
            ->where('partner_id', $partnerId)
            ->whereIn('custom_field_id', $fieldIds)
            ->get()
            ->getResultArray();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['custom_field_id']] = $row['value'] ?? null;
        }
        return $out;
    }

    /* ============================================================
     | EXCEL WRITER (DATA + INSTRUCTIONS)
     ============================================================ */

    /**
     * Write sample data and auto-generated instructions to an Excel file.
     *
     * @param array  $headers  Column headers
     * @param array  $rows     Data rows (each row is an indexed array matching headers)
     * @param string $mode     'insert' or 'update'
     * @return string           Temp file path to the generated .xlsx
     */
    public function writeSampleExcel(array $headers, array $rows, string $mode): string
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

        // --- Sheet 1: Data ---
        $dataSheet = $spreadsheet->getActiveSheet();
        $dataSheet->setTitle('Data');

        // Write headers
        foreach ($headers as $col => $header) {
            $coord = Coordinate::stringFromColumnIndex($col + 1) . '1';
            $dataSheet->setCellValue($coord, $header);
        }
        // Bold header row
        $lastCol = Coordinate::stringFromColumnIndex(count($headers));
        $dataSheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true);

        // Write data rows
        foreach ($rows as $rowIdx => $row) {
            $values = is_array($row) ? array_values($row) : [$row];
            foreach ($values as $col => $value) {
                $coord = Coordinate::stringFromColumnIndex($col + 1) . ($rowIdx + 2);
                $dataSheet->setCellValue($coord, $value);
            }
        }

        // --- Sheet 2: Instructions ---
        $instrSheet = $spreadsheet->createSheet();
        $instrSheet->setTitle('Instructions');
        $instructions = $this->buildInstructionsData($headers, $mode);

        $instrSheet->setCellValue('A1', 'Column');
        $instrSheet->setCellValue('B1', 'Required');
        $instrSheet->setCellValue('C1', 'Description');
        $instrSheet->setCellValue('D1', 'Example / Notes');
        $instrSheet->getStyle('A1:D1')->getFont()->setBold(true);

        foreach ($instructions as $i => $instr) {
            $r = $i + 2;
            $instrSheet->setCellValue("A{$r}", $instr['column']);
            $instrSheet->setCellValue("B{$r}", $instr['required']);
            $instrSheet->setCellValue("C{$r}", $instr['description']);
            $instrSheet->setCellValue("D{$r}", $instr['example']);
        }

        // Auto-size instruction columns
        foreach (['A', 'B', 'C', 'D'] as $col) {
            $instrSheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Protect the instructions sheet (read-only, no password required)
        $instrSheet->getProtection()->setSheet(true);

        // Set Data sheet as active
        $spreadsheet->setActiveSheetIndex(0);

        $tmpPath = tempnam(sys_get_temp_dir(), 'bulk_') . '.xlsx';
        $writer  = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($tmpPath);
        $spreadsheet->disconnectWorksheets();

        return $tmpPath;
    }

    /**
     * Build instruction rows dynamically from the actual headers.
     */
    private function buildInstructionsData(array $headers, string $mode): array
    {
        $isInsert = $mode === 'insert';

        // Known field descriptions
        $fieldInfo = [
            'ID'                          => ['No',  'Auto-populated for update reference', 'Do not change this value'],
            'User ID'                     => ['Yes', 'The provider\'s user ID (for update matching)', 'Do not change this value'],
            'Type'                        => [$isInsert ? 'Yes' : 'No', 'Provider type: 1 = Company, 0 = Individual', '1'],
            'Visiting Charge'             => ['No',  'Charge for visiting customers', '60'],
            'Advance Booking Days'        => ['No',  'Maximum future booking horizon in days (1-3650). Also used as the scheduling max advance booking days', '30'],
            'Number of Members'           => ['No',  'Team size (only for Company type=1, otherwise set 0)', '3'],
            'At Store'                    => ['No',  '1 = Provides service at store, 0 = No', '1'],
            'At Doorstep'                 => ['No',  '1 = Provides doorstep service, 0 = No', '1'],
            'Need Approval for Service'   => ['No',  '1 = Services need admin approval, 0 = No', '0'],
            'Address'                     => ['No',  'Provider\'s address', 'test123, near test'],
            'Is Approved'                 => ['No',  '0 = Disapproved, 1 = Approved (do not use 7)', '1'],
            'Max Serviceable Distance'    => ['No',  'Max distance (KM) within which this provider is served to customers. Stored regardless of the global/provider-wise setting', '100'],
            'Email'                       => [$isInsert ? 'Yes' : 'No', 'Provider\'s email address (must be unique)', 'provider@example.com'],
            'Phone'                       => [$isInsert ? 'Yes' : 'No', 'Phone number (must be unique)', '4848945845'],
            'Country Code'                => [$isInsert ? 'Yes' : 'No', 'Phone country code (without +)', '91'],
            'Password'                    => [$isInsert ? 'Yes' : 'No', 'Plain text password (hashed on import, must meet password strength rules)', 'Test@123'],
            'Login Type'                  => [$isInsert ? 'Yes' : 'No', 'Login method: "email" or "phone". Email login: email must be unique. Phone login: phone+country code must be unique.', 'phone'],
            'City'                        => ['No',  'Provider\'s city', 'Mumbai'],
            'Latitude'                    => ['No',  'Latitude (-90 to 90, trimmed to 7 decimal places)', '28.7435800'],
            'Longitude'                   => ['No',  'Longitude (-180 to 180, trimmed to 7 decimal places)', '45.6237050'],
            'Image'                       => ['No',  'Profile image path relative to project root', 'public/uploads/profile/test.png'],
            'Banner Image'                => ['No',  'Banner image path relative to project root', 'public/uploads/banner/test.png'],
            'SEO Meta Image'              => ['No',  'SEO meta image path relative to project root', 'public/uploads/seo_settings/sample_image.png'],
            'Slot Interval'               => [$isInsert ? 'Yes' : 'No', 'Minutes between booking start times. Allowed: 5, 10, 15, 20, 30, 45, 60 (defaults to 30 if blank/invalid)', '30'],
            'Allow Multiple Bookings'     => ['No',  '1 = allow concurrent bookings in the same slot, 0 = single booking per slot', '0'],
            'Slot Capacity'               => ['No',  'Max parallel bookings per slot (1-100). Forced to 1 when Allow Multiple Bookings is 0', '1'],
            'Min Advance Booking'         => [$isInsert ? 'Yes' : 'No', 'Minimum lead time before a booking can start (numeric value, pair with Min Advance Booking Unit)', '1'],
            'Min Advance Booking Unit'    => [$isInsert ? 'Yes' : 'No', 'Unit for Min Advance Booking: minutes, hours, or days (defaults to hours)', 'hours'],
            'Same Day Booking'            => ['No',  '1 = allow bookings for today, 0 = no same-day bookings', '1'],
            'Buffer Before'               => ['No',  'Minutes blocked before each booking (0-1440)', '0'],
            'Buffer After'                => ['No',  'Minutes blocked after each booking (0-1440)', '0'],
        ];

        // Day timing descriptions
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        foreach ($days as $day) {
            $fieldInfo[$day . ' Start Time'] = ['No', "Shift 1 opening time for $day (HH:MM:SS)", '09:00:00'];
            $fieldInfo[$day . ' End Time']   = ['No', "Shift 1 closing time for $day (HH:MM:SS)", '13:00:00'];
            $fieldInfo[$day . ' Is Open']    = ['No', "1 = Open on $day, 0 = Closed (applies to every shift of the day)", '1'];
        }

        $instructions = [];

        foreach ($headers as $header) {
            // Known static field
            if (isset($fieldInfo[$header])) {
                $info = $fieldInfo[$header];
                $instructions[] = [
                    'column'      => $header,
                    'required'    => $info[0],
                    'description' => $info[1],
                    'example'     => $info[2],
                ];
                continue;
            }

            // Language translation column: "Field Name (code)"
            if (preg_match('/^(Username|Company Name|About Provider|Description)\s+\((\w+)\)$/', $header, $m)) {
                $instructions[] = [
                    'column'      => $header,
                    'required'    => $m[1] === 'Company Name' ? 'Recommended' : 'No',
                    'description' => $m[1] . ' in ' . strtoupper($m[2]) . ' language',
                    'example'     => 'Sample ' . $m[1] . ' (' . $m[2] . ')',
                ];
                continue;
            }

            // SEO column: "SEO Field (code)"
            if (preg_match('/^(SEO .+)\s+\((\w+)\)$/', $header, $m)) {
                $instructions[] = [
                    'column'      => $header,
                    'required'    => 'No',
                    'description' => $m[1] . ' in ' . strtoupper($m[2]) . ' language',
                    'example'     => 'Sample value for ' . $m[1],
                ];
                continue;
            }

            // Custom field: "CF_123: Label"
            if (preg_match('/^CF_(\d+):\s*(.+)$/', $header, $m)) {
                $instructions[] = [
                    'column'      => $header,
                    'required'    => 'No',
                    'description' => 'Custom field "' . trim($m[2]) . '" (ID: ' . $m[1] . '). The CF_ prefix with the ID is used for mapping — do not change it.',
                    'example'     => 'Value or file path',
                ];
                continue;
            }

            // Numbered shift: "<Day> Shift <n> Start/End Time"
            if (preg_match('/^(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday) Shift (\d+) (Start|End) Time$/', $header, $m)) {
                $instructions[] = [
                    'column'      => $header,
                    'required'    => 'No',
                    'description' => 'Shift ' . $m[2] . ' ' . strtolower($m[3]) . ' time for ' . $m[1]
                        . ' (HH:MM:SS). Add "<Day> Shift N Start/End Time" pairs for extra shifts; '
                        . 'leave both blank to skip a shift. Shifts are renumbered in order.',
                    'example'     => $m[3] === 'Start' ? '14:00:00' : '18:00:00',
                ];
                continue;
            }

            // Other Image[N]
            if (preg_match('/^Other Image\[(\d+)\]$/', $header, $m)) {
                $instructions[] = [
                    'column'      => $header,
                    'required'    => 'No',
                    'description' => 'Additional gallery image #' . $m[1] . '. You may add more Other Image[N] columns as needed.',
                    'example'     => 'public/uploads/partner/photo.png',
                ];
                continue;
            }

            // Fallback
            $instructions[] = [
                'column'      => $header,
                'required'    => 'No',
                'description' => '',
                'example'     => '',
            ];
        }

        return $instructions;
    }

    /* ============================================================
     | CSV HELPERS
     ============================================================ */

    private function parseCsv($sheet)
    {
        $headers = [];
        foreach ($sheet->rangeToArray('A1:' . $sheet->getHighestColumn() . '1')[0] as $i => $h) {
            if ($h === null || trim((string) $h) === '') {
                continue;
            }
            $headers[strtolower(trim((string) $h))] = $i;
        }

        $rows = array_filter(array_slice($sheet->toArray(), 1), fn($r) => !empty(array_filter($r)));

        return [$headers, $rows];
    }

    private function hasHeader(array $headers, string $key): bool
    {
        return isset($headers[strtolower(trim($key))]);
    }

    private function hasTimingHeaders(array $headers): bool
    {
        foreach (array_keys(self::TIMING_DAYS) as $day) {
            if ($this->dayHasTimingHeaders($headers, $day)) {
                return true;
            }
        }
        return false;
    }

    private function hasSlotSettingsHeaders(array $headers): bool
    {
        foreach (array_keys(self::SLOT_SETTINGS_HEADERS) as $header) {
            if ($this->hasHeader($headers, $header)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Build a POST-shaped array for SlotSettingsService::extractFromPost().
     * 'Advance Booking Days' doubles as max_advance_booking_days (the
     * add_partner form feeds a single input into both partner_details and
     * provider_slot_settings). Missing/blank cells fall back to defaults
     * inside SlotSettingsService::sanitize().
     */
    private function buildSlotSettingsInput(array $row, array $headers): array
    {
        return [
            'slot_interval'            => $this->getValue($row, $headers, 'Slot Interval'),
            'allow_multiple_bookings'  => $this->getValue($row, $headers, 'Allow Multiple Bookings'),
            'slot_capacity'            => $this->getValue($row, $headers, 'Slot Capacity'),
            'min_advance_booking'      => $this->getValue($row, $headers, 'Min Advance Booking'),
            'min_advance_booking_unit' => $this->getValue($row, $headers, 'Min Advance Booking Unit'),
            'advance_booking_days'     => $this->getValue($row, $headers, 'Advance Booking Days'),
            'same_day_booking'         => $this->getValue($row, $headers, 'Same Day Booking'),
            'buffer_before'            => $this->getValue($row, $headers, 'Buffer Before'),
            'buffer_after'             => $this->getValue($row, $headers, 'Buffer After'),
        ];
    }

    private function validateRequiredHeaders(array $headers, string $mode): array
    {
        $required = ($mode === 'insert')
            ? self::REQUIRED_HEADERS_INSERT
            : self::REQUIRED_HEADERS_UPDATE;

        $missing = [];
        foreach ($required as $header) {
            if (!$this->hasHeader($headers, $header)) {
                $missing[] = $header;
            }
        }
        return $missing;
    }

    private function getValue(array $row, array $headers, string $key)
    {
        $key = strtolower(trim($key));
        return isset($headers[$key]) && isset($row[$headers[$key]])
            ? trim((string)($row[$headers[$key]] ?? ''))
            : '';
    }

    private function getExistingSlug($partnerId)
    {
        $row = fetch_details('partner_details', ['partner_id' => $partnerId], ['slug']);
        return $row[0]['slug'] ?? null;
    }

    /* ============================================================
     | DEBUG
     ============================================================ */

    /**
     * Diagnostic logger for the bulk import flow. Uses the 'error' level so
     * entries are written regardless of the app log threshold. Output lands
     * in writable/logs/log-<date>.log.
     */
    private function log(string $message): void
    {
        if ($this->debug) {
            log_message('error', '[PROVIDER IMPORT] ' . $message);
        }
    }

    /**
     * Extract translation data from Excel row
     * 
     * Processes translation columns from Excel row and organizes them by language and field
     * 
     * @param array $row Excel row data
     * @param array $headers Excel column headers
     * @param array $languages Available languages
     * @return array Translation data organized by language code
     */
    private function extractTranslationsFromRow(array $row, array $headers, array $languages): array
    {
        $translations = [];

        // Initialize translation structure for each language
        foreach ($languages as $language) {
            $translations[$language['code']] = [
                'username' => null,
                'company_name' => null,
                'about' => null,
                'long_description' => null
            ];
        }

        // Map of field names (lowercase) to database columns
        $fieldMapping = [
            'username'       => 'username',
            'company name'   => 'company_name',
            'about provider' => 'about',
            'description'    => 'long_description'
        ];

        // Build a set of valid language codes for quick lookup
        $validLangCodes = array_column($languages, 'code');

        // Headers map is [lowercase_header_name => column_index]
        foreach ($headers as $headerName => $colIndex) {
            // Match translation columns: "company name (en)", "about provider (ar)", etc.
            if (preg_match('/^(.+?)\s*\(([a-z]{2,3})\)$/i', $headerName, $matches)) {
                $fieldName = strtolower(trim($matches[1]));
                $languageCode = strtolower(trim($matches[2]));

                if (isset($fieldMapping[$fieldName]) && in_array($languageCode, $validLangCodes) && isset($row[$colIndex])) {
                    $translations[$languageCode][$fieldMapping[$fieldName]] = $row[$colIndex];
                }
            }
        }

        return $translations;
    }

    /**
     * Extract SEO translations from Excel row
     * 
     * Processes SEO translation columns from Excel row and organizes them by language and field
     * 
     * @param array $row Excel row data
     * @param array $headers Excel column headers
     * @param array $languages Available languages
     * @return array SEO translation data organized by language code
     */
    private function extractSeoTranslationsFromRow(array $row, array $headers, array $languages): array
    {
        $seoTranslations = [];

        // Initialize SEO translation structure for each language
        foreach ($languages as $language) {
            $seoTranslations[$language['code']] = [
                'seo_title' => null,
                'seo_description' => null,
                'seo_keywords' => null,
                'seo_schema_markup' => null
            ];
        }

        // Map of SEO field names (lowercase) to database columns
        $seoFieldMapping = [
            'seo title'         => 'seo_title',
            'seo description'   => 'seo_description',
            'seo keywords'      => 'seo_keywords',
            'seo schema markup' => 'seo_schema_markup'
        ];

        // Build a set of valid language codes for quick lookup
        $validLangCodes = array_column($languages, 'code');

        // Headers map is [lowercase_header_name => column_index]
        foreach ($headers as $headerName => $colIndex) {
            if (empty($headerName)) {
                continue;
            }
            // Match SEO translation columns: "seo title (en)", "seo description (ar)", etc.
            if (preg_match('/^(.+?)\s*\(([a-z]{2,3})\)$/i', $headerName, $matches)) {
                $fieldName = strtolower(trim($matches[1]));
                $languageCode = strtolower(trim($matches[2]));

                if (isset($seoFieldMapping[$fieldName]) && in_array($languageCode, $validLangCodes) && isset($row[$colIndex])) {
                    $dbField = $seoFieldMapping[$fieldName];
                    $value = $row[$colIndex];

                    if ($dbField === 'seo_keywords' && !empty($value)) {
                        $value = $this->parseKeywords($value);
                    }

                    $seoTranslations[$languageCode][$dbField] = !empty($value) ? trim((string)$value) : null;
                }
            }
        }

        return $seoTranslations;
    }

    /**
     * Save partner translations for bulk operations
     * 
     * Saves translation data to the translated_partner_details table
     * 
     * @param int $partnerId Partner ID
     * @param array $translations Translation data organized by language code
     * @return bool Success status
     */
    private function saveBulkPartnerTranslations(int $partnerId, array $translations): bool
    {
        try {
            // Use PartnerService to save translations
            $partnerService = new \App\Services\PartnerService();

            // Process each language
            foreach ($translations as $languageCode => $translationData) {
                // Only save if at least one field has data
                $hasData = false;
                foreach ($translationData as $value) {
                    if (!empty($value)) {
                        $hasData = true;
                        break;
                    }
                }

                if ($hasData) {
                    // Save translations for this language
                    $result = $partnerService->saveTranslations($partnerId, $languageCode, $translationData);

                    if (!$result) {
                        log_message('error', "Failed to save translations for partner {$partnerId} in language {$languageCode}");
                        return false;
                    }
                }
            }

            return true;
        } catch (\Exception $e) {
            log_message('error', 'Exception in saveBulkPartnerTranslations: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Save SEO settings for bulk provider operations
     * 
     * Saves base SEO settings (default language) to partners_seo_settings table
     * and all language SEO translations to translated_partner_seo_settings table
     * 
     * @param int $partnerId Partner ID
     * @param array $seoTranslations SEO translation data organized by language code
     * @param array $languages Available languages
     * @return bool Success status
     */
    private function saveBulkPartnerSeoSettings(int $partnerId, array $seoTranslations, array $languages, ?string $seoMetaImage = null): bool
    {
        try {
            // Get default language
            $defaultLanguage = '';
            foreach ($languages as $language) {
                if ($language['is_default'] == 1) {
                    $defaultLanguage = $language['code'];
                    break;
                }
            }

            if (empty($defaultLanguage)) {
                log_message('error', "No default language found for saving SEO settings for partner {$partnerId}");
                return false;
            }

            // Extract default language SEO data for base SEO settings
            $defaultSeoTitle = '';
            $defaultSeoDescription = '';
            $defaultSeoKeywords = '';
            $defaultSeoSchema = '';

            if (!empty($seoTranslations[$defaultLanguage]['seo_title'])) {
                $defaultSeoTitle = trim($seoTranslations[$defaultLanguage]['seo_title']);
            }

            if (!empty($seoTranslations[$defaultLanguage]['seo_description'])) {
                $defaultSeoDescription = trim($seoTranslations[$defaultLanguage]['seo_description']);
            }

            if (!empty($seoTranslations[$defaultLanguage]['seo_keywords'])) {
                $defaultSeoKeywords = trim($seoTranslations[$defaultLanguage]['seo_keywords']);
            }

            if (!empty($seoTranslations[$defaultLanguage]['seo_schema_markup'])) {
                $defaultSeoSchema = trim($seoTranslations[$defaultLanguage]['seo_schema_markup']);
            }

            // Resolve SEO image: use provided value, fall back to existing
            $this->seoModel->setTableContext('providers');
            $existingSettings = $this->seoModel->getSeoSettingsByReferenceId($partnerId);
            $resolvedImage = ($seoMetaImage !== null && $seoMetaImage !== '')
                ? $seoMetaImage
                : ($existingSettings['image'] ?? '');

            // Check if any SEO field is filled
            $hasSeoData = !empty($defaultSeoTitle) ||
                !empty($defaultSeoDescription) ||
                !empty($defaultSeoKeywords) ||
                !empty($defaultSeoSchema) ||
                !empty($resolvedImage);

            // Only save base SEO settings if there's data
            if ($hasSeoData) {
                // Build SEO data array
                $seoData = [
                    'title'         => $defaultSeoTitle,
                    'description'   => $defaultSeoDescription,
                    'keywords'      => $defaultSeoKeywords,
                    'schema_markup' => $defaultSeoSchema,
                    'partner_id'    => $partnerId,
                    'image'         => $resolvedImage,
                ];

                if ($existingSettings) {
                    // Update existing settings
                    $result = $this->seoModel->updateSeoSettings($existingSettings['id'], $seoData);
                    if (!empty($result['error'])) {
                        log_message('error', "Failed to update SEO settings for partner {$partnerId}: " . $result['message']);
                        return false;
                    }
                } else {
                    // Create new settings
                    $result = $this->seoModel->createSeoSettings($seoData);
                    if (!empty($result['error'])) {
                        log_message('error', "Failed to create SEO settings for partner {$partnerId}: " . $result['message']);
                        return false;
                    }
                }
            }

            // Process SEO translations for all languages
            // Restructure data from lang[field] to field[lang] format for processSeoTranslations
            $restructuredSeoData = [];
            $seoFields = ['seo_title', 'seo_description', 'seo_keywords', 'seo_schema_markup'];

            foreach ($seoFields as $field) {
                $restructuredSeoData[$field] = [];
                foreach ($seoTranslations as $languageCode => $fields) {
                    $restructuredSeoData[$field][$languageCode] = $fields[$field] ?? '';
                }
            }

            // Save SEO translations using the existing processSeoTranslations method
            $this->processSeoTranslations($partnerId, $restructuredSeoData);

            return true;
        } catch (\Exception $e) {
            log_message('error', 'Exception in saveBulkPartnerSeoSettings: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Process SEO translations for partner if provided in the request
     * 
     * @param int $partnerId The partner ID
     * @return void
     */
    private function processSeoTranslations(int $partnerId, ?array $translatedFields = null): void
    {
        try {

            // Process SEO translations if data is provided
            if (!empty($translatedFields) && is_array($translatedFields)) {

                // Load the SEO translation model
                $seoTranslationModel = model('TranslatedPartnerSeoSettings_model');

                // Restructure data for the model (convert field[lang] to lang[field] format)
                $restructuredData = $this->restructureTranslatedFieldsForSeoModel($translatedFields);

                // Process and store the SEO translations
                $seoTranslationResult = $seoTranslationModel->processSeoTranslations($partnerId, $restructuredData);

                // Check if SEO translation processing was successful
                if (!$seoTranslationResult['success']) {
                    throw new \Exception('SEO Translation processing failed: ' . json_encode($seoTranslationResult['errors']));
                }
            }
        } catch (\Exception $e) {
            // Log any exceptions but don't fail the operation
            throw new \Exception('Exception in processSeoTranslations for partner ' . $partnerId . ': ' . $e->getMessage());
        }
    }

    /**
     * Restructure translated fields for SEO model
     * Convert from field[lang] format to lang[field] format
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
                $languages = array_merge($languages, array_keys($translatedFields[$field]));
            }
        }
        $languages = array_unique($languages);

        // Restructure data: from field[lang] to lang[field]
        foreach ($languages as $languageCode) {
            $restructured[$languageCode] = [];

            foreach ($seoFields as $field) {
                $value = $translatedFields[$field][$languageCode] ?? '';

                if ($field === 'seo_keywords') {
                    $restructured[$languageCode][$field] = !empty($value)
                        ? $this->parseKeywords($value)
                        : '';
                } else {
                    $restructured[$languageCode][$field] = $value !== null
                        ? $value
                        : '';
                }
            }
            // Keep the language entry even if every field is empty.
            // This lets the translation model overwrite stale values with blanks.
        }


        return $restructured;
    }

    /**
     * Parses meta keywords input from Tagify.
     */
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
}
