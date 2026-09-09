<?php

namespace App\Notification\Factories;

use App\Notification\Contracts\PushProviderInterface;
use App\Notification\Providers\Push\FcmProvider;
use App\Models\Settings;

/**
 * Factory for creating push notification providers.
 *
 * Reads Firebase / push configuration from the database and returns
 * a fully configured PushProviderInterface implementation.
 *
 * Currently supports:
 *   - 'fcm' (default) → FcmProvider
 *
 * To add a new push provider (e.g. APNs, OneSignal):
 *   1. Create a class in Providers/Push/ implementing PushProviderInterface.
 *   2. Add a case in make() to instantiate it.
 *   3. Store the relevant config in `settings` table under a new variable.
 */
class PushProviderFactory
{
    /**
     * Build and return the configured push provider.
     *
     * Resolves project_id and service-account file path from `settings` table
     * entries ('firebase_settings' and optionally 'json_file').
     *
     * @param  string $provider  Provider identifier. Default: 'fcm'.
     * @return PushProviderInterface
     *
     * @throws \RuntimeException When the requested provider is unknown.
     */
    public function make(string $provider = 'fcm'): PushProviderInterface
    {
        return match ($provider) {
            'fcm'   => $this->makeFcm(),
            default => throw new \RuntimeException("Unknown push provider: {$provider}"),
        };
    }

    // ──────────────────────────────────────────────
    //  Provider-specific builders
    // ──────────────────────────────────────────────

    /**
     * Create a FcmProvider with config resolved from the database.
     *
     * Config resolution mirrors the logic that was in NotificationService:
     *   - project_id  → firebase_settings.projectId  (or firebase_project_id setting)
     *   - json_file   → firebase_settings.json_file  (or standalone json_file setting)
     *   - The JSON file path is resolved relative to the public/ directory.
     *
     * @return FcmProvider
     */
    private function makeFcm(): FcmProvider
    {
        $settingsModel    = new Settings();
        $firebaseSettings = $this->getJsonSetting($settingsModel, 'firebase_settings');

        // ── Resolve project ID ──
        $projectId = $firebaseSettings['projectId'] ?? null;

        if (empty($projectId)) {
            // Fallback: try dedicated firebase_project_id setting
            $record    = $settingsModel->where('variable', 'firebase_project_id')->first();
            $projectId = $record['value'] ?? null;
        }

        // ── Resolve service-account file path ──
        $fileName = $firebaseSettings['json_file'] ?? null;

        if (empty($fileName)) {
            // Fallback: try standalone json_file setting
            $record   = $settingsModel->where('variable', 'json_file')->first();
            $fileName = $record['value'] ?? null;
        }

        // Build absolute path (file lives in public/ directory).
        $serviceFilePath = null;
        if (!empty($fileName)) {
            $serviceFilePath = realpath(APPPATH . '../public/' . $fileName) ?: null;
        }

        return new FcmProvider([
            'project_id'        => $projectId,
            'service_file_path' => $serviceFilePath,
        ]);
    }

    // ──────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────

    /**
     * Read a JSON-encoded setting from the `settings` table.
     *
     * @param  Settings $model    Settings model instance.
     * @param  string   $variable The `variable` column value to look up.
     * @return array  Decoded JSON, or empty array if not found / invalid.
     */
    private function getJsonSetting(Settings $model, string $variable): array
    {
        $record = $model->where('variable', $variable)->first();

        if (empty($record['value'])) {
            return [];
        }

        return json_decode($record['value'], true) ?? [];
    }
}
