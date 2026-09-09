<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ExtractAppSettingsFromGeneralSettings extends Migration
{
    private const APP_KEYS = [
        'customer_current_version_android_app',
        'customer_current_version_ios_app',
        'customer_compulsary_update_force_update',
        'provider_current_version_android_app',
        'provider_current_version_ios_app',
        'provider_compulsary_update_force_update',
        'message_for_customer_application',
        'customer_app_maintenance_mode',
        'customer_app_maintenance_schedule_date',
        'message_for_provider_application',
        'provider_app_maintenance_mode',
        'provider_app_maintenance_schedule_date',
        'customer_playstore_url',
        'customer_appstore_url',
        'provider_playstore_url',
        'provider_appstore_url',
        'customer_android_google_interstitial_id',
        'customer_android_google_banner_id',
        'customer_android_google_ads_status',
        'provider_android_google_interstitial_id',
        'provider_android_google_banner_id',
        'provider_android_google_ads_status',
        'customer_ios_google_interstitial_id',
        'customer_ios_google_banner_id',
        'customer_ios_google_ads_status',
        'provider_ios_google_interstitial_id',
        'provider_ios_google_banner_id',
        'provider_ios_google_ads_status',
    ];

    public function up()
    {
        $builder = $this->db->table('settings');

        $row = $builder->where('variable', 'general_settings')->get()->getRowArray();
        if (!$row) {
            return;
        }

        $general = json_decode($row['value'], true) ?? [];

        // Normalize old AdMob flat keys into customer/provider split before extracting
        $admobMap = [
            'android_google_interstitial_id' => ['customer_android_google_interstitial_id', 'provider_android_google_interstitial_id'],
            'android_google_banner_id'        => ['customer_android_google_banner_id',        'provider_android_google_banner_id'],
            'ios_google_interstitial_id'      => ['customer_ios_google_interstitial_id',      'provider_ios_google_interstitial_id'],
            'ios_google_banner_id'            => ['customer_ios_google_banner_id',            'provider_ios_google_banner_id'],
        ];
        foreach ($admobMap as $old => $new) {
            if (!empty($general[$old])) {
                foreach ($new as $newKey) {
                    if (empty($general[$newKey])) {
                        $general[$newKey] = $general[$old];
                    }
                }
            }
        }
        foreach (['android_google_ads_status' => ['customer_android_google_ads_status', 'provider_android_google_ads_status'], 'ios_google_ads_status' => ['customer_ios_google_ads_status', 'provider_ios_google_ads_status']] as $old => $new) {
            if (isset($general[$old]) && $general[$old] !== '') {
                foreach ($new as $newKey) {
                    if (!isset($general[$newKey]) || $general[$newKey] === '') {
                        $general[$newKey] = $general[$old];
                    }
                }
            }
        }

        // Extract app keys
        $appData = [];
        foreach (self::APP_KEYS as $key) {
            if (array_key_exists($key, $general)) {
                $appData[$key] = $general[$key];
                unset($general[$key]);
            }
        }

        // Upsert app_settings row (existing keys take precedence — safe re-run)
        $existingApp = $builder->where('variable', 'app_settings')->get()->getRowArray();
        if ($existingApp) {
            $existing = json_decode($existingApp['value'], true) ?? [];
            $appData  = array_merge($appData, $existing);
            $builder->where('variable', 'app_settings')->update(['value' => json_encode($appData)]);
        } else {
            $builder->insert(['variable' => 'app_settings', 'value' => json_encode($appData)]);
        }

        // Write general_settings back with app keys removed
        $builder->where('variable', 'general_settings')->update(['value' => json_encode($general)]);
    }

    public function down()
    {
        $builder = $this->db->table('settings');

        $appRow     = $builder->where('variable', 'app_settings')->get()->getRowArray();
        $generalRow = $builder->where('variable', 'general_settings')->get()->getRowArray();

        if (!$appRow || !$generalRow) {
            return;
        }

        $appData     = json_decode($appRow['value'], true) ?? [];
        $generalData = json_decode($generalRow['value'], true) ?? [];

        foreach (self::APP_KEYS as $key) {
            if (array_key_exists($key, $appData)) {
                $generalData[$key] = $appData[$key];
            }
        }

        $builder->where('variable', 'general_settings')->update(['value' => json_encode($generalData)]);
        $builder->where('variable', 'app_settings')->delete();
    }
}
