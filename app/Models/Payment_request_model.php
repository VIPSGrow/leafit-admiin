<?php

namespace App\Models;

use CodeIgniter\Model;
use DateTime;
use DateTimeZone;

class Payment_request_model extends Model
{
    protected $table = 'payment_request';
    protected $primaryKey = 'id';
    // payment_address stores bank snapshot as JSON when set at withdrawal time (see Withdrawal_requests / API).
    // created_at is allowed so callers can pin it to an explicit UTC value
    // instead of relying on the DB column default (which uses server timezone).
    protected $allowedFields = ['user_id', 'user_type', 'payment_address', 'amount', 'remarks', 'status', 'created_at'];
    public function list($from_app = false, $search = '', $limit = 20, $offset = 0, $sort = 'id', $order = 'DESC', $where = [])
    {
        $ionAuth = new \App\Libraries\CustomIonAuth();
        $db      = \Config\Database::connect();
        $builder = $db->table('payment_request p');
        $multipleWhere = [];
        $condition = $bulkData = $rows = $tempRow = [];

        // Get current language for translation support
        // This allows searching in translated provider names
        $currentLang = get_current_language();
        $defaultLang = get_default_language();

        if (isset($_GET['offset']))
            $offset = $_GET['offset'];

        // Handle search functionality
        // Check if search should be restricted to payment_address only (for partner views)
        $searchPaymentAddressOnly = (isset($_GET['search_payment_address_only']) && $_GET['search_payment_address_only'] == true);

        // Search should work for provider name (including translations) and payment address
        // Unless restricted to payment_address only (for partner withdrawal requests)
        if ((isset($search) && !empty($search) && $search != "") || (isset($_GET['search']) && $_GET['search'] != '')) {
            $search = (isset($_GET['search']) && $_GET['search'] != '') ? $_GET['search'] : $search;

            // Build search conditions
            // If search_payment_address_only flag is set, only search in payment_address
            // Otherwise, search in both payment_address and provider name (username)
            if ($searchPaymentAddressOnly) {
                // Partner view: search only in payment_address field
                $multipleWhere = [
                    'p.payment_address' => $search, // Search only in payment address
                ];
            } else {
                // Admin view: search in payment_address and provider name (with translations)
                // Add translated fields join for search if not English
                // This allows searching in translated provider names (username)
                if ($currentLang !== 'en') {
                    $builder->join('translated_partner_details tpd_search', "tpd_search.partner_id = p.user_id AND tpd_search.language_code = '$currentLang'", 'left');
                }

                // Build search conditions for admin view
                $multipleWhere = [
                    'p.payment_address' => $search, // Search in payment address
                    'u.username' => $search, // Search in provider name (username)
                ];

                // Add translated provider name to search if not English
                // This allows searching in translated provider names
                if ($currentLang !== 'en') {
                    $multipleWhere['tpd_search.username'] = $search; // Search in translated provider name
                }
            }
        }
        if (isset($_GET['limit'])) {
            $limit = $_GET['limit'];
        }
        if (isset($_GET['sort'])) {
            if ($_GET['sort'] == 'p.id') {
                $sort = "p.id";
            } else {
                $sort = $_GET['sort'];
            }
        }
        if (isset($_GET['order'])) {
            $order = $_GET['order'];
        }
        if (isset($_POST['order'])) {
            $order = $_POST['order'];
        }
        if (isset($where) && !empty($where)) {
            $builder->where($where);
        }

        // Count query - build with necessary joins for search
        $countBuilder = $db->table('payment_request p');

        // Determine if we have a search term
        $hasSearch = (isset($search) && !empty($search) && $search != "") || (isset($_GET['search']) && $_GET['search'] != '');

        // Add translated fields join for count query if searching in non-English
        // Only add join if not restricted to payment_address only (admin view)
        if ($hasSearch && $currentLang !== 'en' && !$searchPaymentAddressOnly) {
            $countBuilder->join('translated_partner_details tpd_search', "tpd_search.partner_id = p.user_id AND tpd_search.language_code = '$currentLang'", 'left');
        }

        // Use COUNT(DISTINCT p.id) so joins (e.g. partner_details, translated_partner_details)
        // cannot duplicate rows and inflate the total; otherwise pagination shows empty page 2.
        $countBuilder->select('count(DISTINCT p.id) as total')
            ->join('users u', 'u.id=p.user_id')
            ->join('partner_details pd', 'pd.partner_id=u.id', 'left');

        // Apply WHERE conditions to count query (e.g., user_id filter)
        // This ensures the count matches the filtered data results
        if (isset($where) && !empty($where)) {
            $countBuilder->where($where);
        }

        // Apply search conditions to count query
        if (isset($multipleWhere) && !empty($multipleWhere)) {
            $countBuilder->groupStart();
            $countBuilder->orLike($multipleWhere);
            $countBuilder->groupEnd();
        }

        if (isset($_GET['withdraw_request_filter']) && $_GET['withdraw_request_filter'] != '') {
            $countBuilder->where('p.status', $_GET['withdraw_request_filter']);
        }

        $count = $countBuilder->get()->getResultArray();
        // Ensure we have a valid total (count query always returns one row; fallback for safety).
        $total = (!empty($count) && isset($count[0]['total'])) ? (int) $count[0]['total'] : 0;
        // Data query - build with necessary joins.
        // p.payment_address may contain JSON bank snapshot; we parse it in the loop for display.
        $builder->select('p.*,p.id as payment_request_id,p.created_at as payment_created_at,pd.*,u.username as partner_name,u.image as partner_image, u.phone as partner_phone, u.email as partner_email')
            ->join('users u', 'u.id=p.user_id', 'left')
            ->join('partner_details pd', 'pd.partner_id=u.id', 'left');

        // Determine if we have a search term (reuse the same check)
        $hasSearch = (isset($search) && !empty($search) && $search != "") || (isset($_GET['search']) && $_GET['search'] != '');

        // Add translated fields join for data query if searching in non-English
        // Only add join if not restricted to payment_address only (admin view)
        // This ensures we can search in translated provider names for admin view
        if ($hasSearch && $currentLang !== 'en' && !$searchPaymentAddressOnly) {
            $builder->join('translated_partner_details tpd_search', "tpd_search.partner_id = p.user_id AND tpd_search.language_code = '$currentLang'", 'left');
        }

        if (isset($where) && !empty($where)) {
            $builder->where($where);
        }

        // Apply search conditions to data query
        if (isset($multipleWhere) && !empty($multipleWhere)) {
            $builder->groupStart();
            $builder->orLike($multipleWhere);
            $builder->groupEnd();
        }

        if (isset($_GET['withdraw_request_filter']) && $_GET['withdraw_request_filter'] != '') {
            $builder->where('p.status', $_GET['withdraw_request_filter']);
        }

        $record = $builder->orderBy($sort, $order)->limit($limit, $offset)->get()->getResultArray();

        $allTranslations = [];
        if (!empty($record)) {
            $partnerIds = array_column($record, 'partner_id');
            $translatedPartnerDetailsModel = new \App\Models\TranslatedPartnerDetails_model();
            // Get all translations for all partners (not just current language)
            $allTranslations = $translatedPartnerDetailsModel->getAllTranslationsForPartners($partnerIds);
        }

        $bulkData = array();
        $bulkData['total'] = $total;
        $rows = array();
        $tempRow = array();
        foreach ($record as $row) {
            $partner_details = fetch_details('partner_details', ['partner_id' => $row['partner_id']]);

            // Get translations for this specific partner
            $partnerTranslations = $allTranslations[$row['partner_id']] ?? [];

            // Re-index translations by language_code for easier access
            $translationsByLang = [];
            if (!empty($partnerTranslations)) {
                foreach ($partnerTranslations as $langCode => $translation) {
                    $translationsByLang[$langCode] = $translation;
                }
            }

            // Get translated company name with fallback logic
            // Priority: current language → default language → base table
            $companyName = $partner_details[0]['company_name'] ?? ''; // fallback to base table
            $partner_name = $row['partner_name'] ?? ''; // fallback to base table
            if (!empty($translationsByLang)) {
                // Try current language first
                if (isset($translationsByLang[$currentLang]) && !empty($translationsByLang[$currentLang]['company_name'])) {
                    $companyName = $translationsByLang[$currentLang]['company_name'];
                }
                // Fallback to default language
                elseif (isset($translationsByLang[$defaultLang]) && !empty($translationsByLang[$defaultLang]['company_name'])) {
                    $companyName = $translationsByLang[$defaultLang]['company_name'];
                }

                if (isset($translationsByLang[$currentLang]) && !empty($translationsByLang[$currentLang]['username'])) {
                    $partner_name = $translationsByLang[$currentLang]['username'];
                } elseif (isset($translationsByLang[$defaultLang]) && !empty($translationsByLang[$defaultLang]['username'])) {
                    $partner_name = $translationsByLang[$defaultLang]['username'];
                }
            }

            $operations = "";
            if ($row['status'] == 0) {
                if (isset($where['user_id']) && !empty($where['user_id'])) {
                    $operations .= '<button class="btn btn-success btn-sm edit" data-id="' . $row['id'] . '" data-toggle="modal" data-target="#update_modal"><i class="fa fa-pen" aria-hidden="true"></i> </button> ';
                }
                if ($ionAuth->isAdmin()) {
                    $operations .= '<button class="btn btn-success btn-sm edit_request" data-id="' . $row['id'] . '" data-toggle="modal" data-target="#edit_modal"><i class="fa fa-check" aria-hidden="true"></i> </button> ';
                }
            }
            if ($row['status'] == 1) {
                if (isset($where['user_id']) && !empty($where['user_id'])) {
                    $operations .= '<button   class="btn btn-success btn-sm set_settlement_status" 164id="' . $row['payment_request_id'] . '" value="' . $row['payment_request_id'] . '" >' . labels('settle', 'Settle') . '</button> ';
                }
                if ($ionAuth->isAdmin()) {
                    $operations .= '<button   class="btn btn-success btn-sm set_settlement_status" id="' . $row['payment_request_id'] . '" value="' . $row['payment_request_id'] . '" >' . labels('settle', 'Settle') . '</button> ';
                }
            }
            if ($from_app) {
                if ($row['status'] == 0) {
                    $status = "Pending";
                } elseif ($row['status'] == 1) {
                    $status = "Approved";
                } else if ($row['status'] == 2) {
                    $status = "Rejected";
                } else if ($row['status'] == 3) {
                    $status = "Settled";
                }
            } else {

                if ($row['status'] == 0) {
                    $status =   "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-grey text-emerald-grey dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3 mx-5'>" . labels('pending', 'Pending') .
                        "</div>";
                } elseif ($row['status'] == 1) {
                    $status =    "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-success text-emerald-success dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3 mx-5'>" . labels('approved', 'Approved') .
                        "</div>";
                } elseif ($row['status'] == 2) {
                    $status =    "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-danger text-emerald-danger dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3 mx-5'>" . labels('rejected', 'Rejected') .
                        "</div>";
                } else if ($row['status'] == 3) {
                    $status =    "<div class='tag border-0 rounded-md ltr:ml-2 rtl:mr-2 bg-emerald-warning text-emerald-warning dark:bg-emerald-500/20 dark:text-emerald-100 ml-3 mr-3 mx-5'>" . labels('settled', 'Settled') .
                        "</div>";
                }
            }

            $disk = fetch_current_file_manager();
            // Handle profile image with consistent fallback logic
            $imageSrc = get_file_url($disk, $row['partner_image'], 'public/backend/assets/default.png', 'profile');
            $profile = '<div class="o-media o-media--middle">
                        <a href="' . $imageSrc . '" data-lightbox="image-1">
                            <img class="o-media__img images_in_card" src="' . $imageSrc . '" alt="' . $partner_name . '">
                        </a>';
            $profile .= '<a href="' . base_url('/admin/partners/general_outlook/' . $row['partner_id']) . '"><div class="o-media__body">
                <div class="provider_name_table" >' . $partner_name . '</span></div>
                <div class="provider_email_table">' . $companyName . '</div>
                <div class="provider_email_table">' . $row['partner_email'] . '(' . $row['partner_phone'] . ')</div>
                </div>
                </div></a>';

            // Parse bank details from payment_address JSON snapshot.
            // Supports two formats:
            //   - New (custom fields): array of {custom_field_id, label, value} objects
            //   - Legacy: flat {bank_name, account_number, ...} object
            $snapshot = null;
            if (!empty($row['payment_address']) && is_string($row['payment_address'])) {
                $decoded = json_decode($row['payment_address'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $snapshot = $decoded;
                }
            }

            $bank_details = '';
            $paymentAddressDisplay = '';

            if ($snapshot !== null && is_array($snapshot)) {
                // Detect format: new format is a sequential array of objects with custom field data.
                $isNewFormat = isset($snapshot[0]) && isset($snapshot[0]['custom_field_id']);

                if ($isNewFormat) {
                    $lines = [];
                    $bank_details = '<div class="small o-media__body" style="width: 200px;">';
                    foreach ($snapshot as $field) {
                        $label = htmlspecialchars((string) ($field['label'] ?? $field['custom_field_id'] ?? ''));
                        $value = htmlspecialchars((string) ($field['value'] ?? ''));
                        if ($value !== '') {
                            $bank_details .= '<div><span class="fw-medium">' . $label . ':</span> ' . $value . '</div>';
                            $lines[] = ($field['label'] ?? $field['custom_field_id'] ?? '') . ': ' . ($field['value'] ?? '');
                        }
                    }
                    $bank_details .= '</div>';
                    $paymentAddressDisplay = implode(' | ', $lines);
                } else {
                    // Legacy flat object format (backward compatibility).
                    $displayBankName      = $snapshot['bank_name'] ?? '';
                    $displayAccountNumber = $snapshot['account_number'] ?? '';
                    $displayAccountName   = $snapshot['account_name'] ?? '';
                    $displayBankCode      = $snapshot['bank_code'] ?? '';
                    $displaySwiftCode     = $snapshot['swift_code'] ?? '';

                    $bank_details = '<div class="small o-media__body" style="width: 200px;">'
                        . '<div class="font-weight-bold">' . htmlspecialchars($displayBankName) . '</div>'
                        . '<div><span class="fw-medium">A/c No:</span> ' . htmlspecialchars($displayAccountNumber)
                        . ' | <span class="fw-medium">Holder:</span> ' . htmlspecialchars($displayAccountName) . '</div>'
                        . '<div><span class="fw-medium">Code:</span> ' . htmlspecialchars($displayBankCode)
                        . ' | <span class="fw-medium">SWIFT:</span> ' . htmlspecialchars($displaySwiftCode) . '</div>'
                        . '</div>';
                    $paymentAddressDisplay = trim($displayBankName . ' - ' . $displayAccountNumber);
                }
            } else {
                // No JSON snapshot: old raw format or empty.
                $raw = trim((string) ($row['payment_address'] ?? ''));
                if ($raw !== '') {
                    $bank_details = '<div class="small o-media__body" style="width: 200px; white-space: pre-wrap;">'
                        . htmlspecialchars($raw) . '</div>';
                    $paymentAddressDisplay = $raw;
                } else {
                    $bank_details = '<div class="small o-media__body" style="width: 200px;">—</div>';
                    $paymentAddressDisplay = '';
                }
            }

            $tempRow['id'] = $row['payment_request_id'];
            $tempRow['user_id'] = $row['user_id'];
            $tempRow['profile'] = $profile;
            $tempRow['user_type'] = labels($row['user_type'], ucfirst($row['user_type']));
            $tempRow['payment_address'] = $paymentAddressDisplay;
            $tempRow['bank_details'] = $bank_details;
            $tempRow['amount'] = $row['amount'];
            $tempRow['remarks'] = $row['remarks'];
            $tempRow['status'] = $status;
            $tempRow['translated_status'] = getTranslatedValue($status, 'panel');


            // We assume DB timestamps are stored in UTC and convert them to the current PHP timezone.
            if (!$from_app) {
                try {
                    $utc = new DateTime($row['payment_created_at'], new DateTimeZone('UTC'));
                    // Emit an unambiguous UTC ISO-8601 string so the client can convert
                    // to the viewer's browser-local timezone (used by the admin panel).
                    $tempRow['created_at_utc'] = $utc->format('Y-m-d\TH:i:s\Z');
                    $localTz = new DateTimeZone(date_default_timezone_get());
                    $utc->setTimezone($localTz);
                    $tempRow['created_at'] = $utc->format('d-M-Y h:i A');
                } catch (\Exception $e) {
                    // Fallback to original value if anything goes wrong to avoid breaking the table.
                    $tempRow['created_at_utc'] = $row['payment_created_at'];
                    $tempRow['created_at'] = $row['payment_created_at'];
                }
            } else {
                // For app responses we keep the raw DB timestamp so mobile clients can handle timezone themselves.
                $tempRow['created_at'] = $row['payment_created_at'];
            }
            $tempRow['operations'] =  $operations;
            if ($from_app == true) {
                $tempRow['status'] =  strip_tags($status);
            } else {
                $tempRow['status'] =  $status;
            }
            $rows[] = $tempRow;
        }
        $bulkData['rows'] = $rows;
        if ($from_app) {
            $data['total'] = $total;
            $data['data'] = $rows;
            return $data;
        } else {
            return json_encode($bulkData);
        }
    }
}
