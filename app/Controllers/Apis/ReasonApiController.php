<?php

namespace App\Controllers\Apis;

use App\Controllers\BaseController;
use App\Models\ReasonModel;

class ReasonApiController extends BaseController
{
    protected $request, $validation;
    protected $user_details = [];

    public function __construct()
    {
        helper(['api', 'function', 'ResponceServices']);
        $this->request = \Config\Services::request();
        $this->validation = \Config\Services::validation();
        $token = verify_app_request();
        if (!$token['error'] && isset($token['data']) && !empty($token['data'])) {
            $this->user_details = $token['data'];
        } else {
            header('Content-Type: application/json');
            http_response_code($token['status']);
            print_r(json_encode([
                'error'   => true,
                'message' => $token['message'],
                'status'  => 401,
            ]));
            die();
        }
    }

    public function get_reasons()
    {
        try {
            $limit  = (int) ($this->request->getPost('limit')  ?: 10);
            $offset = (int) ($this->request->getPost('offset') ?: 0);
            $sort   = $this->request->getPost('sort')   ?: 'id';
            $order  = $this->request->getPost('order')  ?: 'DESC';
            $type   = $this->request->getPost('type')   ?: '';

            $allowedSort = ['id', 'type', 'reason', 'needs_additional_info', 'created_at'];
            if (!in_array($sort, $allowedSort, true)) {
                $sort = 'id';
            }
            $order = (strtoupper($order) === 'DESC') ? 'DESC' : 'ASC';

            $requestedLang = get_current_language_from_request();
            $defaultLang   = get_default_language();

            $model   = new ReasonModel();
            $db      = \Config\Database::connect();
            $builder = $db->table('reasons');

            $builder->select(['id', 'type', 'reason', 'needs_additional_info', 'created_at']);

            if ($type !== '') {
                $builder->where('type', $type);
            }

            $countBuilder = clone $builder;
            $total        = (int) $countBuilder->countAllResults(false);

            $rows = $builder
                ->orderBy($sort, $order)
                ->limit($limit, $offset)
                ->get()
                ->getResultArray();

            if (!empty($rows) && $model->translatedTableExists()) {
                $reasonIds = array_column($rows, 'id');

                $translations = $db->table('translated_reasons tr')
                    ->select('tr.reason_id, tr.reason as translated_reason, l.code as language_code')
                    ->join('languages l', 'l.id = tr.language_id')
                    ->whereIn('tr.reason_id', $reasonIds)
                    ->get()
                    ->getResultArray();

                $translationsByReasonId = [];
                foreach ($translations as $t) {
                    $translationsByReasonId[$t['reason_id']][$t['language_code']] = $t['translated_reason'];
                }

                foreach ($rows as &$row) {
                    $allTranslations = $translationsByReasonId[$row['id']] ?? [];

                    // Resolve reason with fallback chain:
                    // 1. requested language  2. default language  3. base table value
                    $row['reason'] = $allTranslations[$requestedLang]
                        ?? $allTranslations[$defaultLang]
                        ?? $row['reason'];
                }
                unset($row);
            }

            if (!empty($rows)) {
                return $this->response->setJSON([
                    'error'   => false,
                    'message' => labels('data_fetched_successfully', 'Data fetched successfully'),
                    'data'    => $rows,
                    'total'   => $total,
                ]);
            } else {
                return $this->response->setJSON([
                    'error'   => false,
                    'message' => labels('data_not_found', 'Data not found')
                ]);
            }
        } catch (\Exception $th) {
            log_message('error', $this->request->header('Authorization') . '   Params passed :: ' . json_encode($_POST) . ' Issue => ' . $th . ' --> app/Controllers/Apis/ReasonApiController.php - get_reasons()');
            return $this->response->setJSON([
                'error'   => true,
                'message' => labels('something_went_wrong', 'Something went wrong'),
            ]);
        }
    }
}
