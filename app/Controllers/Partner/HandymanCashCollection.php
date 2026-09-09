<?php

namespace App\Controllers\Partner;

use App\Models\HandymanCashCollectionModel;
use App\Services\Provider\HandymanCashCollectionService;

class HandymanCashCollection extends Partner
{
    public function __construct()
    {
        helper('ResponceServices');
        parent::__construct();
    }

    public function index()
    {
        if (!exists(['partner_id' => $this->userId, 'is_approved' => 1], 'partner_details')) {
            return redirect('partner/profile');
        }
        setPageInfo($this->data, labels('handymen_cash_collections', 'Handymen Cash Collections') . ' | ' . labels('provider_panel', 'Provider Panel'), 'handyman_cash_collection');

        $this->data['currency'] = get_settings('general_settings', true)['currency'];

        return view('backend/partner/template', $this->data);
    }

    public function list_data()
    {
        if (!exists(['partner_id' => $this->userId, 'is_approved' => 1], 'partner_details')) {
            return redirect('partner/profile');
        }

        $search = trim((string) ($this->request->getGet('search') ?: ''));

        $model = model(HandymanCashCollectionModel::class);
        $outstanding = $model->getOutstandingByHandyman((int) $this->userId, $search);

        $fileService = service('fileService');
        $handymen = [];
        foreach ($outstanding as $h) {
            $handymen[(int) $h['handyman_id']] = [
                'handyman_name' => $h['handyman_name'],
                'handyman_image' => $fileService->url($h['handyman_image'] ?? '', 'profile', 'public/backend/assets/default.png'),
                'handyman_email' => $h['handyman_email'] ?? '',
                'handyman_phone' => trim((string) ($h['handyman_country_code'] ?? '')) !== ''
                    ? '(' . $h['handyman_country_code'] . ') ' . $h['handyman_phone']
                    : (string) ($h['handyman_phone'] ?? ''),
                'outstanding_amount' => number_format((float) $h['outstanding_amount'], 2),
            ];
        }

        $rows = $model->listOutstandingCollections((int) $this->userId, array_keys($handymen));

        foreach ($rows as &$row) {
            $handymanId = (int) $row['handyman_id'];
            $row = array_merge($row, $handymen[$handymanId] ?? []);
            $row['amount_display'] = number_format((float) $row['amount'], 2);
            $row['collected_date'] = !empty($row['created_at']) ? date('M d, Y', strtotime($row['created_at'])) : '-';
        }
        unset($row);

        return $this->response->setJSON(['rows' => $rows]);
    }

    public function collect()
    {
        try {
            $handymanId = (int) $this->request->getPost('handyman_id');
            $amount     = (float) $this->request->getPost('amount');
            $message    = $this->request->getPost('message');

            $result = (new HandymanCashCollectionService())->collectFromHandyman(
                (int) $this->userId,
                $handymanId,
                $amount,
                $message
            );

            if ($result['error']) {
                return JsonError($result['message']);
            }
            return JsonSuccess($result['message']);
        } catch (\Throwable $th) {
            log_message('error', date('Y-m-d H:i:s') . ' --> app/Controllers/Partner/HandymanCashCollection.php - collect()' . "\nAt Line : " . $th->getLine() . "\nMessage : " . $th->getMessage());
            return JsonError(labels(SOMETHING_WENT_WRONG, 'Something Went Wrong'));
        }
    }
}
