<!-- Main Content -->
<?php

use App\Services\utility\PermissionService;

$db = \Config\Database::connect();
$builder = $db->table('users u');
$builder->select('u.*,ug.group_id')
    ->join('users_groups ug', 'ug.user_id = u.id')
    ->where('ug.group_id', 1)
    ->where(['phone' => $_SESSION['identity']]);
$user1 = $builder->get()->getResultArray();

$userId = !empty($user1) ? $user1[0]['id'] : 0;

$permissionService = new PermissionService();
?>
<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1> <?= labels('api_key_settings', 'API Key Settings') ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/dashboard') ?>"><i
                            class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item "><a
                        href="<?= base_url('/admin/settings/system-settings') ?>"><?= labels('system_settings', "System Settings") ?></a>
                </div>
                <div class="breadcrumb-item"> <?= labels('api_key_settings', 'API Key Settings') ?></div>
            </div>
        </div>
        <form action="<?= base_url('admin/settings/api_key_settings') ?>" method="post">
            <input type="hidden" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>">
            <input type="hidden" name="update" value="1">
            <div class="container-fluid card ">
                <div class="row">
                    <div class="col " style="border-bottom: solid 1px #e5e6e9;">
                        <div class='toggleButttonPostition '><?= labels('api_key_settings', 'Api Key Settings') ?></div>
                    </div>
                </div>
                <div class="row">
                    <div class="col mb-3">
                        <div class='toggleButttonPostition text-new-primary'>
                            <?= labels('client_api_key_settings', ' Client API Keys') ?>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label
                                for="google_map_api"><?= labels('API_link_for_customer_app', 'API link for Customer App') ?>
                            </label>
                            <div class="input-group">
                                <input id="API_link_for_customer_app" class="form-control" type="text"
                                    name="API_link_for_customer_app" value="<?= base_url('api/v1/'); ?>" readonly>
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" type="button"
                                        onclick="copyToClipboard('API_link_for_customer_app')">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                            <small class="text-danger">(
                                <?= labels('use_this_link_as_your_API_link_in_apps_code', 'Use this link as your API link in App\'s code') ?>
                                )</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label
                                for="google_map_api"><?= labels('API_link_for_provider_app', 'API link for Provider App ') ?>
                            </label>
                            <div class="input-group">
                                <input id="API_link_for_provider_app" class="form-control" type="text"
                                    name="API_link_for_provider_app" value="<?= base_url('/partner/api/v1/'); ?>"
                                    readonly>
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" type="button"
                                        onclick="copyToClipboard('API_link_for_provider_app')">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                            <small class="text-danger">(
                                <?= labels('use_this_link_as_your_API_link_in_providers_app_code', 'Use this link as your API link in Provider\'s App code') ?>
                                )</small>
                        </div>
                    </div>
                </div>
                <?php
                // Can edit if user is admin/superadmin AND modification is allowed
                $canEdit = (defined('ALLOW_MODIFICATION') && ALLOW_MODIFICATION == 1) && ($permissionService->isSuperAdmin($userId) || (string) $userId == "1");
                $mapProvider = isset($map_provider) && $map_provider === 'openstreetmap' ? 'openstreetmap' : 'google';
                ?>

                <div class="row">
                    <div class="col mb-3">
                        <div class='toggleButttonPostition text-new-primary'>
                            <?= labels('map_provider_settings', 'Map Provider Settings') ?>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="map_provider"><?= labels('map_provider', 'Map Provider') ?></label>
                            <select id="map_provider" name="map_provider" class="form-control"
                                <?= (!$canEdit) ? 'disabled' : '' ?>>
                                <option value="google" <?= $mapProvider === 'google' ? 'selected' : '' ?>>
                                    <?= labels('google_maps', 'Google Maps') ?>
                                </option>
                                <option value="openstreetmap" <?= $mapProvider === 'openstreetmap' ? 'selected' : '' ?>>
                                    <?= labels('open_street_maps', 'OpenStreetMap') ?>
                                </option>
                            </select>
                            <small class="form-text text-warning">
                                <?= labels('open_street_maps_accuracy_note', 'OpenStreetMap is not as accurate as Google Maps. If higher accuracy and precision is required, please use Google Maps.') ?>
                            </small>
                        </div>
                    </div>
                </div>

                <div class="row" id="google_map_keys_section" style="<?= $mapProvider === 'openstreetmap' ? 'display:none;' : '' ?>">
                    <div class="col-md-6">
                        <div class='toggleButttonPostition text-new-primary'>
                            <?= labels('google_API_key_for_map', 'Google API key for map') ?>
                        </div>
                        <div class="form-group">
                            <label
                                for="google_map_api"><?= labels('google_API_key_for_map', 'Google API key for map') ?></label>
                            <div class="input-group">
                                <input id="google_map_api" class="form-control" type="text" name="google_map_api"
                                    value="<?= (isset($google_map_api) && !empty($google_map_api) ? str_repeat('*', strlen($google_map_api)) : 'Enter API key') ?>"
                                    data-original-value="<?= isset($google_map_api) ? htmlspecialchars($google_map_api) : '' ?>"
                                    data-is-masked="<?= (isset($google_map_api) && !empty($google_map_api)) ? 'true' : 'false' ?>"
                                    <?= (!$canEdit) ? 'readonly' : '' ?> />
                                <?php if ($canEdit): ?>
                                    <div class="input-group-append">
                                        <button class="btn btn-primary" type="button"
                                            onclick="toggleGoogleApiKeyVisibility('google_map_api', 'google-api-key-toggle-icon')"
                                            title="Toggle visibility">
                                            <i class="fas fa-eye" id="google-api-key-toggle-icon"></i>
                                        </button>
                                    </div>
                                <?php endif; ?>
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" type="button"
                                        onclick="copyToClipboard('google_map_api')">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                            <small
                                class="form-text text-muted"><?= ($canEdit) ? labels('click_the_eye_icon_to_toggle_visibility', 'Click the eye icon to toggle visibility') : '' ?></small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class='toggleButttonPostition text-new-primary'>
                            <?= labels('google_api_key_for_places', 'Google API key for Places') ?>
                        </div>
                        <div class="form-group">
                            <label
                                for="google_places_api"><?= labels('google_api_key_for_places', 'Google API key for Places') ?></label>
                            <div class="input-group">
                                <input id="google_places_api" class="form-control" type="text" name="google_places_api"
                                    value="<?= (isset($google_places_api) && !empty($google_places_api) ? str_repeat('*', strlen($google_places_api)) : 'Enter API key') ?>"
                                    data-original-value="<?= isset($google_places_api) ? htmlspecialchars($google_places_api) : '' ?>"
                                    data-is-masked="<?= (isset($google_places_api) && !empty($google_places_api)) ? 'true' : 'false' ?>"
                                    <?= (!$canEdit) ? 'readonly' : '' ?> />
                                <?php if ($canEdit): ?>
                                    <div class="input-group-append">
                                        <button class="btn btn-primary" type="button"
                                            onclick="toggleGoogleApiKeyVisibility('google_places_api', 'google-places-api-key-toggle-icon')"
                                            title="Toggle visibility">
                                            <i class="fas fa-eye" id="google-places-api-key-toggle-icon"></i>
                                        </button>
                                    </div>
                                <?php endif; ?>
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" type="button"
                                        onclick="copyToClipboard('google_places_api')">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                            <small
                                class="form-text text-muted"><?= ($canEdit) ? labels('click_the_eye_icon_to_toggle_visibility', 'Click the eye icon to toggle visibility') : '' ?></small>
                        </div>
                    </div>
                </div>

                <!-- Microsoft Clarity Settings -->
                <div class="row mt-4">
                    <div class="col mb-3">
                        <div class='toggleButttonPostition text-new-primary'>
                            <?= labels('microsoft_clarity_settings', 'Microsoft Clarity Settings') ?>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label
                                for="microsoft_clarity_project_id"><?= labels('microsoft_clarity_project_id', 'Microsoft Clarity Project ID') ?></label>
                            <div class="input-group">
                                <input id="microsoft_clarity_project_id" class="form-control" type="text"
                                    name="microsoft_clarity_project_id"
                                    value="<?= (isset($microsoft_clarity_project_id) && !empty($microsoft_clarity_project_id) ? str_repeat('*', strlen($microsoft_clarity_project_id)) : 'Enter Project ID') ?>"
                                    data-original-value="<?= isset($microsoft_clarity_project_id) ? htmlspecialchars($microsoft_clarity_project_id) : '' ?>"
                                    data-is-masked="<?= (isset($microsoft_clarity_project_id) && !empty($microsoft_clarity_project_id)) ? 'true' : 'false' ?>"
                                    <?= (!$canEdit) ? 'readonly' : '' ?> />
                                <?php if ($canEdit): ?>
                                    <div class="input-group-append">
                                        <button class="btn btn-primary" type="button"
                                            onclick="toggleGoogleApiKeyVisibility('microsoft_clarity_project_id', 'clarity-project-id-toggle-icon')"
                                            title="Toggle visibility">
                                            <i class="fas fa-eye" id="clarity-project-id-toggle-icon"></i>
                                        </button>
                                    </div>
                                <?php endif; ?>
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" type="button"
                                        onclick="copyToClipboard('microsoft_clarity_project_id')">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                            <small
                                class="form-text text-muted"><?= ($canEdit) ? labels('click_the_eye_icon_to_toggle_visibility', 'Click the eye icon to toggle visibility') : '' ?></small>
                            <small
                                class="form-text text-muted"><?= labels('microsoft_clarity_project_id_help', 'Enter your Microsoft Clarity Project ID to enable analytics tracking for provider panel') ?></small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <div class="control-label">
                                <?= labels('microsoft_clarity_enabled', 'Enable Microsoft Clarity') ?>
                            </div>
                            <label class="mt-2">
                                <!-- Hidden input to send '0' when checkbox is unchecked -->
                                <input type="hidden" name="microsoft_clarity_enabled" value='0'
                                    id="microsoft_clarity_enabled_value">
                                <?php
                                // Set up the checkbox state for Switchery switch
                                $microsoft_clarity_enabled = isset($microsoft_clarity_enabled) ? $microsoft_clarity_enabled : 0;
                                $isClarityChecked = isset($microsoft_clarity_enabled) && $microsoft_clarity_enabled == 1;
                                // Checkbox value will be updated by JavaScript to 1 or 0, but initial value should be 1 when checked
                                $clarityCheckboxValue = $isClarityChecked ? "1" : "0";
                                ?>
                                <!-- Switchery switch - initialized automatically by custom.js for .status-switch class -->
                                <!-- JavaScript will add enable-content/disable-content classes to show "Enabled/Disabled" labels -->
                                <input type="checkbox" class="status-switch" name="microsoft_clarity_enabled"
                                    id="microsoft_clarity_enabled" <?php echo $isClarityChecked ? "checked" : "" ?>
                                    value="<?= $clarityCheckboxValue; ?>" <?= (!$canEdit) ? 'disabled' : '' ?>>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="row">
                </div>
                <div class="row mt-3">
                    <?php if ($permissionService->can($userId, 'update', 'settings')): ?>

                        <div class="col-md d-flex justify-content-end">
                            <div class="form-group">
                                <input type='submit' name='update' id='update'
                                    value='<?= labels('save_changes', "Save Changes") ?>' class='btn bg-new-primary' />
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </form>
    </section>
</div>

<script>
    // Google API Key masking functionality - Updated to handle multiple API keys
    function toggleGoogleApiKeyVisibility(inputId, iconId) {
        const apiKeyInput = document.getElementById(inputId);
        const toggleIcon = document.getElementById(iconId);

        if (!apiKeyInput || !toggleIcon) {
            console.error('Required elements not found for Google API key visibility toggle');
            return;
        }

        const originalValue = apiKeyInput.getAttribute('data-original-value');
        const isMasked = apiKeyInput.getAttribute('data-is-masked') === 'true';

        if (!originalValue || originalValue.trim() === '') {
            // No value to toggle
            return;
        }

        if (isMasked) {
            // Show the actual value
            apiKeyInput.value = originalValue;
            apiKeyInput.setAttribute('data-is-masked', 'false');
            toggleIcon.classList.remove('fa-eye');
            toggleIcon.classList.add('fa-eye-slash');
            toggleIcon.parentElement.setAttribute('title', 'Hide API key');
        } else {
            // Mask the value
            apiKeyInput.value = '*'.repeat(originalValue.length);
            apiKeyInput.setAttribute('data-is-masked', 'true');
            toggleIcon.classList.remove('fa-eye-slash');
            toggleIcon.classList.add('fa-eye');
            toggleIcon.parentElement.setAttribute('title', 'Show API key');
        }
    }

    // Toggle Google API key fields based on selected map provider
    $(document).ready(function () {
        function toggleMapProviderFields() {
            var provider = $('#map_provider').val();
            $('#google_map_keys_section').toggle(provider === 'google');
        }
        toggleMapProviderFields();
        $('#map_provider').on('change', toggleMapProviderFields);
    });

    // Initialize Google API key toggle button state for both API keys
    $(document).ready(function () {
        // Initialize Map API key toggle
        const mapApiKeyInput = document.getElementById('google_map_api');
        const mapToggleIcon = document.getElementById('google-api-key-toggle-icon');

        if (mapApiKeyInput && mapToggleIcon) {
            const isMasked = mapApiKeyInput.getAttribute('data-is-masked') === 'true';
            if (isMasked) {
                mapToggleIcon.classList.remove('fa-eye-slash');
                mapToggleIcon.classList.add('fa-eye');
                mapToggleIcon.parentElement.setAttribute('title', 'Show API key');
            } else {
                mapToggleIcon.classList.remove('fa-eye');
                mapToggleIcon.classList.add('fa-eye-slash');
                mapToggleIcon.parentElement.setAttribute('title', 'Hide API key');
            }
        }

        // Initialize Places API key toggle
        const placesApiKeyInput = document.getElementById('google_places_api');
        const placesToggleIcon = document.getElementById('google-places-api-key-toggle-icon');

        if (placesApiKeyInput && placesToggleIcon) {
            const isMasked = placesApiKeyInput.getAttribute('data-is-masked') === 'true';
            if (isMasked) {
                placesToggleIcon.classList.remove('fa-eye-slash');
                placesToggleIcon.classList.add('fa-eye');
                placesToggleIcon.parentElement.setAttribute('title', 'Show API key');
            } else {
                placesToggleIcon.classList.remove('fa-eye');
                placesToggleIcon.classList.add('fa-eye-slash');
                placesToggleIcon.parentElement.setAttribute('title', 'Hide API key');
            }
        }

        // Initialize Microsoft Clarity Project ID toggle
        const clarityProjectIdInput = document.getElementById('microsoft_clarity_project_id');
        const clarityToggleIcon = document.getElementById('clarity-project-id-toggle-icon');

        if (clarityProjectIdInput && clarityToggleIcon) {
            const isMasked = clarityProjectIdInput.getAttribute('data-is-masked') === 'true';
            if (isMasked) {
                clarityToggleIcon.classList.remove('fa-eye-slash');
                clarityToggleIcon.classList.add('fa-eye');
                clarityToggleIcon.parentElement.setAttribute('title', 'Show Project ID');
            } else {
                clarityToggleIcon.classList.remove('fa-eye');
                clarityToggleIcon.classList.add('fa-eye-slash');
                clarityToggleIcon.parentElement.setAttribute('title', 'Hide Project ID');
            }
        }

        // Handle Microsoft Clarity switch change event
        // Update the checkbox value to 1 or 0 when toggled
        // Also update the enable/disable content classes for proper label display
        function handleClaritySwitchChange(checkbox) {
            // Find the Switchery element - it's usually the next sibling
            var switchery = checkbox.nextElementSibling;

            // If nextElementSibling is not a switchery element, look for it
            if (!switchery || !switchery.classList.contains('switchery')) {
                switchery = checkbox.parentNode.querySelector('.switchery');
            }

            if (switchery) {
                if (checkbox.checked) {
                    // Add enable-content class and remove disable-content for "Enabled" label
                    switchery.classList.add('enable-content');
                    switchery.classList.remove('disable-content');
                } else {
                    // Add disable-content class and remove enable-content for "Disabled" label
                    switchery.classList.add('disable-content');
                    switchery.classList.remove('enable-content');
                }
            }
        }

        // Initialize the switch state on page load
        var clarityCheckbox = document.getElementById('microsoft_clarity_enabled');
        if (clarityCheckbox) {
            // Wait a bit for Switchery to initialize, then set the classes
            setTimeout(function () {
                handleClaritySwitchChange(clarityCheckbox);
            }, 100);

            // Handle change event
            $('#microsoft_clarity_enabled').on('change', function () {
                this.value = this.checked ? 1 : 0;
                handleClaritySwitchChange(this);
            });
        }
    });

    // Validate on form submission - Handle both API keys
    $('form').submit(function (event) {
        // Handle Google Map API key masking before form submission
        const mapApiKeyInput = document.getElementById('google_map_api');
        if (mapApiKeyInput) {
            const originalValue = mapApiKeyInput.getAttribute('data-original-value');
            const isMasked = mapApiKeyInput.getAttribute('data-is-masked') === 'true';

            // If the field is masked, use the original value for submission
            if (isMasked && originalValue && originalValue.trim() !== '') {
                mapApiKeyInput.value = originalValue;
            }
        }

        // Handle Google Places API key masking before form submission
        const placesApiKeyInput = document.getElementById('google_places_api');
        if (placesApiKeyInput) {
            const originalValue = placesApiKeyInput.getAttribute('data-original-value');
            const isMasked = placesApiKeyInput.getAttribute('data-is-masked') === 'true';

            // If the field is masked, use the original value for submission
            if (isMasked && originalValue && originalValue.trim() !== '') {
                placesApiKeyInput.value = originalValue;
            }
        }

        // Handle Microsoft Clarity Project ID masking before form submission
        const clarityProjectIdInput = document.getElementById('microsoft_clarity_project_id');
        if (clarityProjectIdInput) {
            const originalValue = clarityProjectIdInput.getAttribute('data-original-value');
            const isMasked = clarityProjectIdInput.getAttribute('data-is-masked') === 'true';

            // If the field is masked, use the original value for submission
            if (isMasked && originalValue && originalValue.trim() !== '') {
                clarityProjectIdInput.value = originalValue;
            }
        }
    });

    function copyToClipboard(elementId) {
        const element = document.getElementById(elementId);
        if (element) {
            element.select();
            element.setSelectionRange(0, 99999); // For mobile devices
            document.execCommand('copy');

            // Show a temporary success message
            const originalValue = element.value;
            element.style.backgroundColor = '#d4edda';
            element.style.borderColor = '#c3e6cb';
            setTimeout(() => {
                element.style.backgroundColor = '';
                element.style.borderColor = '';
            }, 1000);
        }
    }

    $('form[action*="api_key_settings"]').on('submit', function (e) {
        e.preventDefault();
        var $btn = $(this).find('[type="submit"]');
        var originalVal = $btn.val();
        $btn.prop('disabled', true);
        $.ajax({
            url: $(this).attr('action'),
            type: 'POST',
            data: $(this).serialize(),
            success: function (res) {
                if (res.error === false) {
                    showToastMessage(res.message, 'success');
                } else {
                    showToastMessage(res.message, 'error');
                }
            },
            error: function () {
                showToastMessage('<?= labels("something_went_wrong", "Something went wrong") ?>', 'error');
            },
            complete: function () {
                $btn.prop('disabled', false).val(originalVal);
            }
        });
    });
</script>