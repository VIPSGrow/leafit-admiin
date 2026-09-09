<?php

namespace App\Controllers\Apis\Provider;

use App\Controllers\BaseController;
use App\Libraries\JWT;
use App\Models\Notification_model;
use App\Services\NotificationTemplateService;
use App\Models\TranslatedCategoryDetails_model;
use App\Models\TranslatedPartnerDetails_model;
use CodeIgniter\I18n\Time;

class NotificationApiController extends BaseController
{
    protected $request, $trans, $db, $data;
    protected JWT $JWT;
    protected $toDateTime;
    protected $user_details = [];
    protected $excluded_routes =
        [
            "api/v1/index",
            "api/v1"
        ];
    public function __construct()
    {
        helper('api');
        helper("function");
        helper('ResponceServices');
        $this->request = \Config\Services::request();
        $this->JWT = new JWT();
        $current_uri = uri_string();
        $token = verify_app_request();
        if (!$token['error'] && isset($token['data']) && !empty($token['data'])) {
            $this->user_details = $token['data'];
        } else if (!in_array($current_uri, $this->excluded_routes)) {
            header('Content-Type: application/json');
            http_response_code($token['status']);
            print_r(json_encode($token));
            die();
        }
    }

    public function index()
    {
        $response = \Config\Services::response();
        helper("filesystem");
        $response->setHeader('content-type', 'Text');
        return $response->setBody(file_get_contents(base_url('apidocs.txt')));
    }

    // public function manage_notification()
    // {
    //     try {
    //         $validation = \Config\Services::validation();
    //         $validation->setRules(
    //             [
    //                 'notification_id' => 'required',
    //                 'is_readed' => 'permit_empty|numeric',
    //             ]
    //         );
    //         if (!$validation->withRequest($this->request)->run()) {
    //             $errors = $validation->getErrors();
    //             $response = [
    //                 'error' => true,
    //                 'message' => $errors,
    //                 'data' => [],
    //             ];
    //             return $this->response->setJSON($response);
    //         }
    //         $nfcs = fetch_details('notifications', ['id' => $this->request->getPost('notification_id')]);
    //         if (empty($nfcs)) {
    //             return response_helper(labels(NOTIFICATION_NOT_FOUND, 'notification not found!'));
    //         }
    //         if ($this->request->getPost('delete_notification') && $this->request->getPost('delete_notification') == 1) {
    //             $data = ['id' => $this->request->getPost('notification_id'), 'user_id' => $this->user_details['id']];
    //             if (exists(['id' => $this->request->getPost('notification_id'), 'notification_type' => 'general'], 'notifications')) {
    //                 if (exists(['notification_id' => $this->request->getPost('notification_id'), 'user_id' => $this->user_details['id']], 'delete_general_notification')) {
    //                     update_details(['is_deleted' => 1], ['notification_id' => $this->request->getPost('notification_id'), 'user_id' => $this->user_details['id']], 'delete_general_notification');
    //                     return response_helper(labels(NOTIFICATION_DELETED_SUCCESSFULLY, 'Notification deleted successfully'), false);
    //                 } else {
    //                     insert_details(['is_deleted' => 1, 'notification_id' => $this->request->getPost('notification_id'), 'user_id' => $this->user_details['id']], 'delete_general_notification');
    //                     return response_helper(labels(NOTIFICATION_DELETED_SUCCESSFULLY, 'Notification deleted successfully'), false);
    //                 }
    //             }
    //             if (!exists($data, 'notifications')) {
    //                 return response_helper(labels(NOTIFICATION_NOT_FOUND, 'notification not found'));
    //             }
    //             if (delete_details($data, 'notifications')) {
    //                 return response_helper(labels(NOTIFICATION_DELETED_SUCCESSFULLY, 'Notification deleted successfully'), false);
    //             } else {
    //                 return response_helper(labels(SOMETHING_WENT_WRONG, 'Something went wrong'));
    //             }
    //         }
    //         $data = ['id' => $this->request->getPost('notification_id'), 'user_id' => $this->user_details['id']];
    //         if (!exists($data, 'notifications')) {
    //             return response_helper(labels(NOTIFICATION_NOT_FOUND, 'notification not found..'));
    //         }
    //         if (exists(['id' => $this->request->getPost('notification_id'), 'notification_type' => 'general'], 'notifications')) {
    //             if (exists(['notification_id' => $this->request->getPost('notification_id'), 'user_id' => $this->user_details['id']], 'delete_general_notification')) {
    //                 update_details(['is_deleted' => !empty($this->request->getPost('is_readed')) ? 1 : 0], ['notification_id' => $this->request->getPost('notification_id'), 'user_id' => $this->user_details['id']], 'delete_general_notification');
    //                 return response_helper(labels(NOTIFICATION_UPDATED_SUCCESSFULLY, 'Notification updated successfully'), false);
    //             } else {
    //                 $set = [
    //                     'is_readed' => $this->request->getPost('is_readed') != '' ? 1 : 0,
    //                     'notification_id' => $this->request->getPost('notification_id'),
    //                     'user_id' => $this->user_details['id'],
    //                 ];
    //                 insert_details($set, 'delete_general_notification');
    //                 return response_helper(labels(NOTIFICATION_UPDATED_SUCCESSFULLY, 'Notification updated successfully'), false);
    //             }
    //         }
    //         $update_notifications = update_details(
    //             ['is_readed' => $this->request->getPost('is_readed') != '' ? 1 : 0],
    //             ['id' => $this->request->getPost('notification_id'), 'user_id' => $this->user_details['id']],
    //             'notifications'
    //         );
    //         if ($update_notifications == true) {
    //             $res = $this->get_notifications($this->request->getPost('notification_id'));
    //             $notifcations = json_decode($res->getBody(), true);
    //             if (!empty($notifcations)) {
    //                 $error = false;
    //                 $message = labels(NOTIFICATION_UPDATED_SUCCESSFULLY, 'notification updated successfully');
    //             } else {
    //                 $error = true;
    //                 $message = labels(NOTIFICATION_NOT_FOUND, 'notification not found');
    //             }
    //             return response_helper($message, $error, remove_null_values($notifcations));
    //         } else {
    //             return response_helper(labels(SOMETHING_WENT_WRONG, 'Something went wrong'));
    //         }
    //     } catch (\Exception $th) {
    //         $response['error'] = true;
    //         $response['message'] = labels(SOMETHING_WENT_WRONG, 'Something went wrong');
    //         log_the_responce($this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . " Issue => " . $th, date("Y-m-d H:i:s") . '--> app/Controllers/api/V1.php - manage_notification()');
    //         return $this->response->setJSON($response);
    //     }
    // }

    public function get_notifications()
    {
        try {
            helper('function');

            $userId = $this->user_details['id'];
            $limit = max((int) ($this->request->getPost('limit') ?? 10), 1);
            $offset = max((int) ($this->request->getPost('offset') ?? 0), 0);

            $model = new Notification_model();

            // IMPORTANT:
            // Providers must NOT receive customer-targeted broadcasts.
            // We reuse the same model logic as customer API, but with audienceTarget = 'provider'.
            // Handymen share this JWT/endpoint with providers — the `User-type` header (set by the
            // handyman app) tells us to fetch that handyman's own notifications instead.
            $audienceTarget = strtolower($this->request->getHeaderLine('User-type')) === 'handyman' ? 'handyman' : 'provider';

            $total = $model->countForAudience($userId, $audienceTarget);
            $rows = $model->fetchForAudience($userId, $audienceTarget, $limit, $offset);

            if (empty($rows)) {
                return response_helper(labels(NOTIFICATION_NOT_FOUND, 'Notification Not Found'), false, [], 200);
            }

            // mark read in one go
            $model->markAsRead(array_column($rows, 'id'));

            $templateService = new NotificationTemplateService();
            $defaultLang = get_default_language();
            $requestedLang = get_current_language_from_request();
            $catModel = new TranslatedCategoryDetails_model();
            $partnerModel = new TranslatedPartnerDetails_model();

            foreach ($rows as &$n) {
                if (($n['event_type'] ?? '') === 'system_generated') {
                    $n = $this->renderSystemGenerated($n, $userId, $templateService);
                }

                if ($n['type'] === 'provider') {
                    $n = $this->attachProvider($n, $defaultLang, $requestedLang, $partnerModel);
                } elseif ($n['type'] === 'category') {
                    $n = $this->attachCategory($n, $defaultLang, $requestedLang, $catModel);
                }

                foreach (['created_at', 'date_sent', 'updated_at'] as $field) {
                    if (!empty($n[$field])) {
                        $n[$field] = Time::parse($n[$field])->setTimezone('UTC')->format('Y-m-d\TH:i:s.v\Z');
                    }
                }

                $n['duration'] = $this->duration($n['date_sent'] ?? null);
            }

            return response_helper(
                labels(NOTIFICATIONS_FETCHED_SUCCESSFULLY, 'Notifications fetched successfully'),
                false,
                remove_null_values($rows),
                200,
                ['total' => $total]
            );
        } catch (\Throwable $th) {
            log_the_responce(
                $this->request->header('Authorization') . ' Params: ' . json_encode($_POST) .
                " Issue: " . $th,
                date("Y-m-d H:i:s") . '--> get_notifications()'
            );

            return $this->response->setJSON([
                'error' => true,
                'message' => labels(SOMETHING_WENT_WRONG, 'Something went wrong'),
            ]);
        }
    }

    private function renderSystemGenerated($n, $userId, $templateService)
    {
        $eventKey = $n['type'] ?? '';
        $context = json_decode($n['context_data'] ?? '{}', true) ?: [];
        $context['user_id'] = $context['user_id'] ?? $userId;

        if (!$eventKey) {
            return $n;
        }

        $tpl = $templateService->getFcmTemplate($eventKey);
        if (!$tpl) {
            $n['event_key'] = $eventKey;
            return $n;
        }

        $vars = $templateService->extractVariablesFromContext($eventKey, $context);

        $n['title'] = $templateService->replaceVariables($tpl['title'] ?? '', $vars);
        $n['message'] = $templateService->replaceVariables($tpl['body'] ?? '', $vars);
        $n['event_key'] = $eventKey;
        $n['order_id'] = (isset($context['order_id']) ? $context['order_id'] : (isset($context['booking_id']) ? (string) $context['booking_id'] : null));
        $n['custom_job_request_id'] = (isset($context['custom_job_request_id']) ? $context['custom_job_request_id'] : null);

        return $n;
    }

    private function attachProvider($n, $defaultLang, $reqLang, $partnerModel)
    {
        $id = (int) ($n['type_id'] ?? 0);
        if ($id <= 0)
            return $n;

        $base = fetch_details('partner_details', ['partner_id' => $id, 'is_approved' => 1])[0] ?? [];
        $slug = $base['slug'] ?? '';
        $fallback = trim($base['company_name'] ?? '');

        $default = $partnerModel->getTranslatedDetails($id, $defaultLang);
        $req = $reqLang !== $defaultLang
            ? $partnerModel->getTranslatedDetails($id, $reqLang)
            : $default;

        $name = $default['company_name'] ?? $default['username'] ?? $fallback;
        $translated = $req['company_name'] ?? $req['username'] ?? $name;

        $n['provider_name'] = trim($name);
        $n['translated_provider_name'] = trim($translated);
        $n['provider_slug'] = $slug;

        return $n;
    }

    private function attachCategory($n, $defaultLang, $reqLang, $catModel)
    {
        $id = (int) ($n['type_id'] ?? 0);
        if ($id <= 0)
            return $n;

        $base = fetch_details('categories', ['id' => $id])[0] ?? [];
        $slug = $base['slug'] ?? '';
        $fallback = trim($base['name'] ?? '');

        $default = $catModel->getTranslatedDetails($id, $defaultLang);
        $req = $reqLang !== $defaultLang
            ? $catModel->getTranslatedDetails($id, $reqLang)
            : $default;

        $name = $default['name'] ?? $fallback;
        $translated = $req['name'] ?? $name;

        $n['category_name'] = trim($name);
        $n['translated_category_name'] = trim($translated);
        $n['category_slug'] = $slug;

        return $n;
    }

    private function duration(?string $date)
    {
        if (!$date)
            return '';

        try {
            $utcDate = new Time($date, 'UTC');
            $now = Time::now('UTC');
            $diff = $now->getTimestamp() - $utcDate->getTimestamp();

            if ($diff < 0)
                return 'just now';

            $units = [
                31536000 => 'year',
                2592000 => 'month',
                604800 => 'week',
                86400 => 'day',
                3600 => 'hour',
                60 => 'minute',
                1 => 'second',
            ];

            foreach ($units as $seconds => $name) {
                $value = floor($diff / $seconds);
                if ($value >= 1) {
                    return $value . ' ' . $name . ($value > 1 ? 's' : '') . ' ago';
                }
            }

            return 'just now';
        } catch (\Exception $e) {
            return '';
        }
    }
}
