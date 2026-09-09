<?php

namespace App\Services\Provider;

use App\Models\Bookmarks_model;
use App\Models\Partner_subscription_model;
use App\Models\Partners_model;
use App\Models\Promo_code_model;
use App\Models\ProviderLeaves_model;
use App\Models\ProviderShifts_model;
use App\Models\ProviderSlotSettings_model;
use App\Models\Seo_model;
use App\Models\SlotLocks_model;
use App\Models\TranslatedPartnerDetails_model;
use App\Models\TranslatedPartnerSeoSettings_model;
use App\Models\Users_model;
use App\Services\utility\FileService;
use Config\Database;

/**
 * Owns the provider-deletion (`delete_partner`) workflow.
 *
 * Phase 3 step 4 of the Provider refactor. Uses CI4 Models + FileService
 * end-to-end; no legacy `function_helper.php` calls.
 */
class ProviderDeleteService
{
    private Users_model $users;
    private Partners_model $partners;
    private ProviderShifts_model $providerShifts;
    private ProviderLeaves_model $providerLeaves;
    private ProviderSlotSettings_model $providerSlotSettings;
    private SlotLocks_model $slotLocks;
    private Partner_subscription_model $partnerSubscriptions;
    private Promo_code_model $promoCodes;
    private Bookmarks_model $bookmarks;
    private TranslatedPartnerDetails_model $translatedPartnerDetails;
    private TranslatedPartnerSeoSettings_model $translatedPartnerSeoSettings;
    private Seo_model $seoModel;
    private FileService $fileService;

    public function __construct()
    {
        $this->users = new Users_model();
        $this->partners = new Partners_model();
        $this->providerShifts = new ProviderShifts_model();
        $this->providerLeaves = new ProviderLeaves_model();
        $this->providerSlotSettings = new ProviderSlotSettings_model();
        $this->slotLocks = new SlotLocks_model();
        $this->partnerSubscriptions = new Partner_subscription_model();
        $this->promoCodes = new Promo_code_model();
        $this->bookmarks = new Bookmarks_model();
        $this->translatedPartnerDetails = new TranslatedPartnerDetails_model();
        $this->translatedPartnerSeoSettings = new TranslatedPartnerSeoSettings_model();
        $this->seoModel = new Seo_model();
        $this->fileService = service('fileService');
    }

    /**
     * Delete a partner and all owned rows/files.
     *
     * @return array{userExisted: bool, userDeleted: bool}
     *
     * @throws \Throwable On unexpected failure (caller logs + returns SOMETHING_WENT_WRONG).
     */
    public function delete(int $partnerId): array
    {
        $user = $this->users->select(['id', 'image'])->find($partnerId);
        $partner = $this->partners
            ->select(['banner', 'other_images'])
            ->where('partner_id', $partnerId)
            ->first();

        $db = Database::connect();
        $db->transException(true);
        $db->transStart();

        try {
            $this->deleteOwnedFiles($partnerId, $user, $partner);

            // Provider-owned tables (have models with deleteByPartner / equivalent).
            $this->providerShifts->deleteByPartner($partnerId);
            $this->providerLeaves->deleteByPartner($partnerId);
            $this->providerSlotSettings->deleteByPartner($partnerId);
            $this->slotLocks->where('partner_id', $partnerId)->delete();
            $this->partnerSubscriptions->where('partner_id', $partnerId)->delete();
            $this->promoCodes->where('partner_id', $partnerId)->delete();
            $this->bookmarks->where('partner_id', $partnerId)->delete();
            $this->translatedPartnerDetails->deletePartnerTranslations($partnerId);
            $this->translatedPartnerSeoSettings->deletePartnerSeoTranslations($partnerId);

            // SEO base row (image already removed inside deleteOwnedFiles via cleanupSeoData).
            $this->seoModel->setTableContext('providers');
            $existingSeo = $this->seoModel->getSeoSettingsByReferenceId($partnerId, 'raw');
            if (!empty($existingSeo['id'])) {
                $this->seoModel->deleteSeoSettings((int) $existingSeo['id']);
            }

            // Tables without dedicated models — use query builder.
            $db->table('services')->where('user_id', $partnerId)->delete();
            $db->table('partner_timings')->where('partner_id', $partnerId)->delete();
            $db->table('partner_custom_fields')->where('partner_id', $partnerId)->delete();
            $db->table('users_groups')->where('user_id', $partnerId)->delete();
            $db->table('users_tokens')->where('user_id', $partnerId)->delete();
            if ($db->tableExists('partner_bids')) {
                $db->table('partner_bids')->where('partner_id', $partnerId)->delete();
            }

            if (!empty($partner)) {
                $this->partners->where('partner_id', $partnerId)->delete();
            }

            $userExisted = !empty($user);
            $userDeleted = false;
            if ($userExisted) {
                $userDeleted = (bool) $this->users->delete($partnerId);
            }

            $db->transComplete();

            return ['userExisted' => $userExisted, 'userDeleted' => $userDeleted];
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    /**
     * Delete every file owned by the partner from configured storage.
     */
    private function deleteOwnedFiles(int $partnerId, ?array $user, ?array $partner): void
    {
        // Profile image lives on `users.image`.
        if (!empty($user['image'])) {
            $this->fileService->delete('profile', $user['image']);
        }

        if (!empty($partner)) {
            if (!empty($partner['banner'])) {
                $this->fileService->delete('banner', $partner['banner']);
            }

            // Gallery (JSON array) on partner_details.other_images.
            if (!empty($partner['other_images'])) {
                $decoded = json_decode($partner['other_images'], true);
                if (is_array($decoded)) {
                    foreach ($decoded as $imagePath) {
                        if (!empty($imagePath)) {
                            $this->fileService->delete('partner', $imagePath);
                        }
                    }
                }
            }

            // Legacy single-file fields (pre custom_fields data model).
            foreach (['address_id', 'passport', 'national_id'] as $legacyField) {
                if (!empty($partner[$legacyField])) {
                    $this->fileService->delete($legacyField, $partner[$legacyField]);
                }
            }
        }

        // KYC documents in partner_custom_fields (file-type rows in `documents` group).
        $this->deleteCustomFieldFiles($partnerId);

        // SEO image (handled by Seo_model; row itself removed later by deleteSeoSettings).
        $this->seoModel->cleanupSeoData($partnerId, 'providers');
    }

    /**
     * Delete all stored files referenced by a partner's file-type custom fields
     * in the `documents` group.
     */
    private function deleteCustomFieldFiles(int $partnerId): void
    {
        $db = Database::connect();
        if (!$db->tableExists('custom_fields') || !$db->tableExists('partner_custom_fields')) {
            return;
        }

        $documentFieldIds = array_map(
            'intval',
            array_column(
                $db->table('custom_fields')
                    ->select('id')
                    ->where('field_group', 'documents')
                    ->where('field_type', 'file')
                    ->get()
                    ->getResultArray(),
                'id'
            )
        );

        if (empty($documentFieldIds)) {
            return;
        }

        $fileRows = $db->table('partner_custom_fields')
            ->select(['value'])
            ->where('partner_id', $partnerId)
            ->whereIn('custom_field_id', $documentFieldIds)
            ->get()
            ->getResultArray();

        foreach ($fileRows as $row) {
            $path = (string) ($row['value'] ?? '');
            if ($path !== '') {
                $this->fileService->delete('custom_fields', $path);
            }
        }
    }
}
