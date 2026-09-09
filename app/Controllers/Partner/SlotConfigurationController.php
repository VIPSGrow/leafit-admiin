<?php

namespace App\Controllers\Partner;

use App\Services\Provider\SlotSettingsService;

class SlotConfigurationController extends Partner
{
    protected SlotSettingsService $slotSettingsService;

    public function __construct()
    {
        parent::__construct();
        helper('ResponceServices');
        $this->slotSettingsService = new SlotSettingsService();
    }

    public function index()
    {
        if (!$this->isLoggedIn) {
            return redirect('partner/login');
        }

        setPageInfo(
            $this->data,
            labels('slot_configuration', 'Slot Configuration') . ' | ' . labels('provider_panel', 'Provider Panel'),
            'slot_configuration'
        );

        $partner_details = !empty(fetch_details('partner_details', ['partner_id' => $this->userId]))
            ? fetch_details('partner_details', ['partner_id' => $this->userId])[0]
            : [];

        $this->data['partner_details'] = $partner_details;
        $this->data['slot_settings']   = $this->slotSettingsService->find((int) $this->userId);

        return view('backend/partner/template', $this->data);
    }

    public function update()
    {
        try {
            if (!$this->isLoggedIn || !$this->userIsPartner) {
                return ErrorResponse(labels(UNAUTHORIZED_ACCESS, 'Unauthorized access'), true, [], [], 200, csrf_token(), csrf_hash());
            }

            $post = $this->request->getPost();

            $this->slotSettingsService->save(
                (int) $this->userId,
                $this->slotSettingsService->extractFromPost($post)
            );

            // Mirror admin behaviour: keep partner_details.advance_booking_days in sync
            // with provider_slot_settings.max_advance_booking_days so legacy callers
            // (orders, customer APIs) reading from partner_details stay consistent.
            if (isset($post['advance_booking_days']) && $post['advance_booking_days'] !== '') {
                update_details(
                    ['advance_booking_days' => (int) $post['advance_booking_days']],
                    ['partner_id' => $this->userId],
                    'partner_details'
                );
            }

            return successResponse(labels(DATA_UPDATED_SUCCESSFULLY, 'Data updated successfully'), false, [], [], 200, csrf_token(), csrf_hash());
        } catch (\Throwable $th) {
            log_the_responce($th, date('Y-m-d H:i:s') . '--> app/Controllers/partner/SlotConfigurationController.php - update()');
            return ErrorResponse(labels(SOMETHING_WENT_WRONG, 'Something went wrong'), true, [], [], 200, csrf_token(), csrf_hash());
        }
    }
}
