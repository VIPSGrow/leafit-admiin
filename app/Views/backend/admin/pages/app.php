<!-- Main Content -->
<?php
$db      = \Config\Database::connect();
$builder = $db->table('users u');
$builder->select('u.*,ug.group_id')
    ->join('users_groups ug', 'ug.user_id = u.id')
    ->where('ug.group_id', 1)
    ->where(['phone' => $_SESSION['identity']]);
$user1 = $builder->get()->getResultArray();
$permissions = get_permission($user1[0]['id']);
?>

<style>
    .toggleButttonPostition {
        margin-left: 10px;
    }
</style>
<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('app_settings', "App settings") ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item "><a href="<?= base_url('/admin/settings/system-settings') ?>"><?= labels('system_settings', "System Settings") ?></a></div>
                <div class="breadcrumb-item"><?= labels('app_settings', "App settings") ?></div>
            </div>
        </div>
        <?= form_open_multipart(base_url('admin/settings/app_settings')) ?>
        <input type="hidden" name="update" value="1">
        <!-- Browser timezone sent so maintenance schedule times are stored in UTC (admin enters in local time, e.g. IST). -->
        <input type="hidden" name="maintenance_schedule_entry_timezone" id="maintenance_schedule_entry_timezone" value="">
        <div class="row mb-3">
            <!-- Customer App urls  -->
            <div class="col-md-4 col-sm-12 col-xl-4">
                <div class="card h-100">
                    <div class="row m-0" style="border-bottom: solid 1px #e5e6e9;">
                        <div class="col ">
                            <div class="toggleButttonPostition"><?= labels('app_url', "App URL") ?></div>
                        </div>
                        <div class="mr-4 mt-4">
                            <input type="" class="btn btn-primary" readonly value="<?= labels('customer_application', "Customer Application") ?>" style="cursor: default;">
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="form-group mb-0">
                                    <label><?= labels('playstore_url', "Playstore URL") ?></label>
                                    <input id="customer_playstore_url" class="form-control" value="<?= isset($customer_playstore_url) ? $customer_playstore_url : "" ?>" type="text" name="customer_playstore_url" placeholder="<?= labels('enter_customer_playstore_url', 'Enter customer playstore application url') ?>">
                                </div>
                            </div>
                            <div class="col-md-12 mt-2">
                                <div class="form-group mb-0">
                                    <label><?= labels('appstore_url', "Appstore URL") ?></label>
                                    <input id="customer_appstore_url" value="<?= isset($customer_appstore_url) ? $customer_appstore_url : "" ?>" class="form-control" type="text" name="customer_appstore_url" placeholder="<?= labels('enter_customer_appstore_url', 'Enter customer appstore application url') ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Provider App urls  -->
            <div class="col-md-6 col-sm-12 col-xl-4">
                <div class="card h-100">
                    <div class="row m-0" style="border-bottom: solid 1px #e5e6e9;">
                        <div class="col ">
                            <div class="toggleButttonPostition"><?= labels('app_url', "App URL") ?></div>
                        </div>
                        <div class="mr-4 mt-4">
                            <input type="" class="btn btn-primary" readonly value="<?= labels('provider_application', "Provider Application") ?>" style="cursor: default;">
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="form-group mb-0">
                                    <label><?= labels('playstore_url', "Playstore URL") ?></label>
                                    <input id="provider_playstore_url" class="form-control" value="<?= isset($provider_playstore_url) ? $provider_playstore_url : "" ?>" type="text" name="provider_playstore_url" placeholder="<?= labels('enter_provider_playstore_app_url', 'Enter provider playstore application url') ?>">
                                </div>
                            </div>
                            <div class="col-md-12 mt-2">
                                <div class="form-group mb-0">
                                    <label><?= labels('appstore_url', "Appstore URL") ?></label>
                                    <input id="provider_appstore_url" class="form-control" type="text" value="<?= isset($provider_appstore_url) ? $provider_appstore_url : "" ?>" name="provider_appstore_url" placeholder="<?= labels('enter_provider_appstore_app_url', 'Enter provider appstore application url') ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Customer Setting -->
            <div class="col-md-6 col-sm-12 col-xl-4">
                <div class="card h-100">
                    <div class="row m-0" style="border-bottom: solid 1px #e5e6e9;">
                        <div class="col ">
                            <div class="toggleButttonPostition"><?= labels('customer_setting', "Customer Setting") ?></div>
                        </div>
                        <div class="mr-4 mt-4">
                            <input type="" readonly class="btn btn-primary" value="<?= labels('customer_application', "Customer Application") ?>" style="cursor: default;">
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label><?= labels('provider_location_in_provider_details', "show provider location on provider details") ?></label>
                                    <br>
                                    <label class="mt-2 " style="padding-top:0">
                                        <?php
                                        $provider_location_in_provider_details = isset($provider_location_in_provider_details) ? $provider_location_in_provider_details : 0;
                                        $isMaintenanceMode = $provider_location_in_provider_details == "1";
                                        $isChecked = isset($provider_location_in_provider_details) && $provider_location_in_provider_details == 1;
                                        $defaultValue = isset($provider_location_in_provider_details) ? $provider_location_in_provider_details : "0";
                                        ?>
                                        <input type="checkbox" class="status-switch" name="provider_location_in_provider_details" id="provider_location_in_provider_details" <?php echo $isChecked ? "checked" : "" ?> value="<?= $defaultValue; ?>">
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mb-3">
            <!-- Customer Version Settings -->
            <div class="col-md-6 col-sm-12 col-xl-6">
                <div class="card h-100">
                    <div class="row m-0" style="border-bottom: solid 1px #e5e6e9;">
                        <div class="col ">
                            <div class="toggleButttonPostition"><?= labels('version_settings', "Version Settings") ?></div>
                        </div>
                        <div class="mr-4 mt-4">
                            <input type="" class="btn btn-primary" readonly value="<?= labels('customer_application', "Customer Application") ?>" style="cursor: default;">
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="customer_current_version_android_app"><?= labels('current_version_of_android_app', "Current Version Of Android App") ?></label>
                                    <input type="tel" class="form-control" name="customer_current_version_android_app" id="customer_current_version_android_app" value="<?= isset($customer_current_version_android_app) ? $customer_current_version_android_app : '' ?>" required />
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="current_version_ios_app"><?= labels('current_version_of_IOS_app', "Current Version Of IOS App") ?></label>
                                    <input type="tel" class="form-control" name="customer_current_version_ios_app" id="customer_current_version_ios_app" value="<?= isset($customer_current_version_ios_app) ? $customer_current_version_ios_app : '' ?>" required />
                                </div>
                            </div>
                            <div class="col-md-12 mt-2">
                                <div class="form-group">
                                    <div class="control-label"><?= labels('compulsory_update_force_update', "Compulsory Update/Force Update") ?></div>
                                    <label class=" mt-2">
                                        <input type="hidden" name="customer_compulsary_update_force_update" value='0' id="customer_compulsary_update_force_update_value">
                                        <!-- <input type="checkbox" name="customer_compulsary_update_force_update" id="customer_compulsary_update_force_update" <?php echo isset($customer_compulsary_update_force_update) && $customer_compulsary_update_force_update == 1 ? "checked" : "" ?> class="custom-switch-input" value=<?= isset($compulsary_update_force_update) ? $compulsary_update_force_update : "0"; ?>>
                                        <span class="custom-switch-indicator"></span> -->
                                        <?php
                                        $customer_compulsary_update_force_update = isset($customer_compulsary_update_force_update) ? $customer_compulsary_update_force_update : 0;
                                        $isMaintenanceMode = $customer_compulsary_update_force_update == "1";
                                        $isChecked = isset($customer_compulsary_update_force_update) && $customer_compulsary_update_force_update == 1;
                                        $defaultValue = isset($compulsary_update_force_update) ? $compulsary_update_force_update : "0";
                                        ?>
                                        <input type="checkbox" class="status-switch" name="customer_compulsary_update_force_update" id="customer_compulsary_update_force_update" <?php echo $isChecked ? "checked" : "" ?> value="<?= $defaultValue; ?>">
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Provider Version Settings -->
            <div class="col-md-6 col-sm-12 col-xl-6">
                <div class="card h-100">
                    <div class="row m-0" style="border-bottom: solid 1px #e5e6e9;">
                        <div class="col ">
                            <div class="toggleButttonPostition"><?= labels('version_settings', "Version Settings") ?></div>
                        </div>
                        <div class="mr-4 mt-4">
                            <input type="" class="btn btn-primary" readonly value="<?= labels('provider_application', "Provider Application") ?> " style="cursor: default;">
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="current_version_android_app"><?= labels('current_version_of_android_app', "Current Version Of Android App") ?></label>
                                    <input type="tel" class="form-control" name="provider_current_version_android_app" id="provider_current_version_android_app" value="<?= isset($provider_current_version_android_app)   ? $provider_current_version_android_app : '' ?>" required />
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="current_version_ios_app"><?= labels('current_version_of_IOS_app', "Current Version Of IOS App") ?></label>
                                    <input type="tel" class="form-control" name="provider_current_version_ios_app" id="current_version_ios_app" value="<?= isset($provider_current_version_ios_app) ? $provider_current_version_ios_app : '' ?>" required />
                                </div>
                            </div>
                            <div class="col-md-12 mt-2">
                                <div class="form-group">
                                    <div class="control-label"><?= labels('compulsory_update_force_update', "Compulsory Update/Force Update") ?></div>
                                    <label class=" mt-2">
                                        <input type="hidden" id="provider_compulsary_update_force_value" name="provider_compulsary_update_force_update" value='0'>
                                        <!-- <input type="checkbox" id="provider_compulsary_update_force_update" <?php echo isset($provider_compulsary_update_force_update) && $provider_compulsary_update_force_update == 1 ? "checked" : "" ?> name="provider_compulsary_update_force_update" class="custom-switch-input" value=<?= isset($compulsary_update_force_update) ? "checked" : "0"; ?>>
                                        <span class="custom-switch-indicator"></span> -->
                                        <?php
                                        $provider_compulsary_update_force_update = isset($provider_compulsary_update_force_update) ? $provider_compulsary_update_force_update : 0;
                                        $isMaintenanceMode = $provider_compulsary_update_force_update == "1";
                                        $isChecked = isset($provider_compulsary_update_force_update) && $provider_compulsary_update_force_update == 1;
                                        $defaultValue = isset($compulsary_update_force_update) ? $compulsary_update_force_update : "0";
                                        ?>
                                        <input type="checkbox" class="status-switch" name="provider_compulsary_update_force_update" id="provider_compulsary_update_force_update" <?php echo $isChecked ? "checked" : "" ?> value="<?= $defaultValue; ?>">
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mb-3">
            <!-- Customer Android Google Admob ads -->
            <div class="col-md-6 col-sm-12 col-xl-6">
                <div class="card h-100">
                    <div class="row m-0" style="border-bottom: solid 1px #e5e6e9;">
                        <div class="col ">
                            <div class="toggleButttonPostition"><?= labels('android_google_admob_ads', "Android Google Admob Ads") ?></div>
                        </div>
                        <div class="mr-4 mt-4">
                            <input type="" class="btn btn-primary" readonly value="<?= labels('customer_application', "Customer Application") ?>" style="cursor: default;">
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="customer_android_google_interstitial_id"><?= labels('google_interstitial_id', "Google Interstitial Id") ?></label>
                                    <input type="text" class="form-control" name="customer_android_google_interstitial_id" id="customer_android_google_interstitial_id" value="<?= isset($customer_android_google_interstitial_id) ? $customer_android_google_interstitial_id : '' ?>" />
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="customer_android_google_banner_id"><?= labels('google_banner_id', "Google Banner Id") ?></label>
                                    <input type="text" class="form-control" name="customer_android_google_banner_id" id="customer_android_google_banner_id" value="<?= isset($customer_android_google_banner_id) ? $customer_android_google_banner_id : '' ?>" />
                                </div>
                            </div>
                            <div class="col-md-12 mt-2">
                                <div class="form-group">
                                    <div class="control-label"><?= labels('android_google_ads_status', "Android Google Ads Status") ?></div>
                                    <label class=" mt-2">
                                        <input type="hidden" name="customer_android_google_ads_status" value='0' id="customer_android_google_ads_status_value">
                                        <?php
                                        $customer_android_google_ads_status = isset($customer_android_google_ads_status) ? $customer_android_google_ads_status : 0;
                                        $isMaintenanceMode = $customer_android_google_ads_status == "1";
                                        $isChecked = isset($customer_android_google_ads_status) && $customer_android_google_ads_status == 1;
                                        $defaultValue = isset($customer_android_google_ads_status) ? $customer_android_google_ads_status : "0";
                                        ?>
                                        <input type="checkbox" class="status-switch" name="customer_android_google_ads_status" id="customer_android_google_ads_status" <?php echo $isChecked ? "checked" : "" ?> value="<?= $defaultValue; ?>">
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Provider Android Google Admob ads -->
            <div class="col-md-6 col-sm-12 col-xl-6">
                <div class="card h-100">
                    <div class="row m-0" style="border-bottom: solid 1px #e5e6e9;">
                        <div class="col ">
                            <div class="toggleButttonPostition"><?= labels('android_google_admob_ads', "Android Google Admob Ads") ?></div>
                        </div>
                        <div class="mr-4 mt-4">
                            <input type="" class="btn btn-primary" readonly value="<?= labels('provider_application', "Provider Application") ?>" style="cursor: default;">
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="provider_android_google_interstitial_id"><?= labels('google_interstitial_id', "Google Interstitial Id") ?></label>
                                    <input type="text" class="form-control" name="provider_android_google_interstitial_id" id="provider_android_google_interstitial_id" value="<?= isset($provider_android_google_interstitial_id) ? $provider_android_google_interstitial_id : '' ?>" />
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="provider_android_google_banner_id"><?= labels('google_banner_id', "Google Banner Id") ?></label>
                                    <input type="text" class="form-control" name="provider_android_google_banner_id" id="provider_android_google_banner_id" value="<?= isset($provider_android_google_banner_id) ? $provider_android_google_banner_id : '' ?>" />
                                </div>
                            </div>
                            <div class="col-md-12 mt-2">
                                <div class="form-group">
                                    <div class="control-label"><?= labels('android_google_ads_status', "Android Google Ads Status") ?></div>
                                    <label class=" mt-2">
                                        <input type="hidden" name="provider_android_google_ads_status" value='0' id="provider_android_google_ads_status_value">
                                        <?php
                                        $provider_android_google_ads_status = isset($provider_android_google_ads_status) ? $provider_android_google_ads_status : 0;
                                        $isMaintenanceMode = $provider_android_google_ads_status == "1";
                                        $isChecked = isset($provider_android_google_ads_status) && $provider_android_google_ads_status == 1;
                                        $defaultValue = isset($provider_android_google_ads_status) ? $provider_android_google_ads_status : "0";
                                        ?>
                                        <input type="checkbox" class="status-switch" name="provider_android_google_ads_status" id="provider_android_google_ads_status" <?php echo $isChecked ? "checked" : "" ?> value="<?= $defaultValue; ?>">
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mb-3">
            <!-- Customer IOS Google Admob ads -->
            <div class="col-md-6 col-sm-12 col-xl-6">
                <div class="card h-100">
                    <div class="row m-0" style="border-bottom: solid 1px #e5e6e9;">
                        <div class="col ">
                            <div class="toggleButttonPostition"><?= labels('ios_google_admob_ads', "IOS Google Admob Ads") ?></div>
                        </div>
                        <div class="mr-4 mt-4">
                            <input type="" class="btn btn-primary" readonly value="<?= labels('customer_application', "Customer Application") ?>" style="cursor: default;">
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="customer_ios_google_interstitial_id"><?= labels('google_interstitial_id', "Google Interstitial Id") ?></label>
                                    <input type="text" class="form-control" name="customer_ios_google_interstitial_id" id="customer_ios_google_interstitial_id" value="<?= isset($customer_ios_google_interstitial_id) ? $customer_ios_google_interstitial_id : '' ?>" />
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="customer_ios_google_banner_id"><?= labels('google_banner_id', "Google Banner Id") ?></label>
                                    <input type="text" class="form-control" name="customer_ios_google_banner_id" id="customer_ios_google_banner_id" value="<?= isset($customer_ios_google_banner_id) ? $customer_ios_google_banner_id : '' ?>" />
                                </div>
                            </div>
                            <div class="col-md-12 mt-2">
                                <div class="form-group">
                                    <div class="control-label"><?= labels('ios_google_ads_status', "IOS Google Ads Status") ?></div>
                                    <label class=" mt-2">
                                        <input type="hidden" name="customer_ios_google_ads_status" value='0' id="customer_ios_google_ads_status_value">
                                        <?php
                                        $customer_ios_google_ads_status = isset($customer_ios_google_ads_status) ? $customer_ios_google_ads_status : 0;
                                        $isMaintenanceMode = $customer_ios_google_ads_status == "1";
                                        $isChecked = isset($customer_ios_google_ads_status) && $customer_ios_google_ads_status == 1;
                                        $defaultValue = isset($customer_ios_google_ads_status) ? $customer_ios_google_ads_status : "0";
                                        ?>
                                        <input type="checkbox" class="status-switch" name="customer_ios_google_ads_status" id="customer_ios_google_ads_status" <?php echo $isChecked ? "checked" : "" ?> value="<?= $defaultValue; ?>">
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Provider IOS Google Admob ads -->
            <div class="col-md-6 col-sm-12 col-xl-6">
                <div class="card h-100">
                    <div class="row m-0" style="border-bottom: solid 1px #e5e6e9;">
                        <div class="col ">
                            <div class="toggleButttonPostition"><?= labels('ios_google_admob_ads', "IOS Google Admob Ads") ?></div>
                        </div>
                        <div class="mr-4 mt-4">
                            <input type="" class="btn btn-primary" readonly value="<?= labels('provider_application', "Provider Application") ?>" style="cursor: default;">
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="provider_ios_google_interstitial_id"><?= labels('google_interstitial_id', "Google Interstitial Id") ?></label>
                                    <input type="text" class="form-control" name="provider_ios_google_interstitial_id" id="provider_ios_google_interstitial_id" value="<?= isset($provider_ios_google_interstitial_id) ? $provider_ios_google_interstitial_id : '' ?>" />
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="provider_ios_google_banner_id"><?= labels('google_banner_id', "Google Banner Id") ?></label>
                                    <input type="text" class="form-control" name="provider_ios_google_banner_id" id="provider_ios_google_banner_id" value="<?= isset($provider_ios_google_banner_id) ? $provider_ios_google_banner_id : '' ?>" />
                                </div>
                            </div>
                            <div class="col-md-12 mt-2">
                                <div class="form-group">
                                    <div class="control-label"><?= labels('ios_google_ads_status', "IOS Google Ads Status") ?></div>
                                    <label class=" mt-2">
                                        <input type="hidden" name="provider_ios_google_ads_status" value='0' id="provider_ios_google_ads_status_value">
                                        <?php
                                        $provider_ios_google_ads_status = isset($provider_ios_google_ads_status) ? $provider_ios_google_ads_status : 0;
                                        $isMaintenanceMode = $provider_ios_google_ads_status == "1";
                                        $isChecked = isset($provider_ios_google_ads_status) && $provider_ios_google_ads_status == 1;
                                        $defaultValue = isset($provider_ios_google_ads_status) ? $provider_ios_google_ads_status : "0";
                                        ?>
                                        <input type="checkbox" class="status-switch" name="provider_ios_google_ads_status" id="provider_ios_google_ads_status" <?php echo $isChecked ? "checked" : "" ?> value="<?= $defaultValue; ?>">
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mb-3">
            <!-- Customer Maintenance Mode -->
            <div class="col-md-6 col-sm-12 col-xl-6">
                <div class="card h-100">
                    <div class="row m-0" style="border-bottom: solid 1px #e5e6e9;">
                        <div class="col ">
                            <div class="toggleButttonPostition"><?= labels('maintenance_mode', "Maintenance Mode") ?></div>
                        </div>
                        <div class="mr-4 mt-4">
                            <input type="" class="btn btn-primary" readonly value="<?= labels('customer_application', "Customer Application") ?>" style="cursor: default;">
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label><?= labels('start_and_end_date', "Start And End Date") ?></label>
                                    <input type="text" name="customer_app_maintenance_schedule_date" id="customer_app_maintenance_schedule_date" class="form-control daterange-cus " value="<?php echo $customer_app_maintenance_schedule_date ?? ""  ?>">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="d-flex flex-wrap align-items-center gap-4">
                                    <?php
                                    // Languages are already sorted in controller (default language first)
                                    foreach ($languages as $index => $language) {
                                        if ($language['is_default'] == 1) {
                                            $current_customer_maintenance_language = $language['code'];
                                        }
                                    ?>
                                        <div class="language-customer-maintenance-option mb-3 position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                            id="language-customer-maintenance-<?= $language['code'] ?>"
                                            data-language="<?= $language['code'] ?>"
                                            style="cursor: pointer; padding: 0.5rem 0;">
                                            <span class="language-customer-maintenance-text px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                                style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                                <?= $language['language'] ?><?= $language['is_default'] ? '(Default)' : '' ?>
                                            </span>
                                            <div class="language-customer-maintenance-underline"
                                                style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: #0d6efd; transition: width 0.3s ease; border-radius: 1px;"></div>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <?php
                                // Use the same languages for form fields (already sorted in controller)
                                foreach ($languages as $index => $language) {
                                ?>
                                    <div class="form-group mb-0" id="translationCustomerMaintenanceDiv-<?= $language['code'] ?>" <?= $language['code'] == $current_customer_maintenance_language ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                        <label><?= labels('message_for_customer_application', "Message for Customer Application") . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                        <textarea class="form-control" style="min-height:60px" name="message_for_customer_application[<?= $language['code'] ?>]" style="min-height:60px" rows="1"><?php
                                                                                                                                                                                                    // Handle both new multi-language format and old single string format
                                                                                                                                                                                                    if (isset($message_for_customer_application[$language['code']])) {
                                                                                                                                                                                                        echo $message_for_customer_application[$language['code']];
                                                                                                                                                                                                    } else if (is_string($message_for_customer_application) && $language['is_default'] == 1) {
                                                                                                                                                                                                        echo $message_for_customer_application;
                                                                                                                                                                                                    } else {
                                                                                                                                                                                                        echo "";
                                                                                                                                                                                                    }
                                                                                                                                                                                                    ?></textarea>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="col-md-12  mt-2">
                                <div class="form-group">
                                    <label><?= labels('maintenance_mode', "Maintenance Mode") ?></label>
                                    <br>
                                    <label class=" mt-1 " style="padding-top:0">
                                        <?php
                                        $customer_app_maintenance_mode = isset($customer_app_maintenance_mode) ? $customer_app_maintenance_mode : 0;
                                        $isMaintenanceMode = $customer_app_maintenance_mode == "1";
                                        $isChecked = isset($customer_app_maintenance_mode) && $customer_app_maintenance_mode == 1;
                                        $defaultValue = isset($compulsary_update_force_update) ? $compulsary_update_force_update : "0";
                                        ?>
                                        <input type="checkbox" class="status-switch" name="customer_app_maintenance_mode" id="customer_maintenance_mode" <?php echo $isChecked ? "checked" : "" ?> value="<?= $defaultValue; ?>">
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Provider Maintenance Mode -->
            <div class="col-md-6 col-sm-12 col-xl-6">
                <div class="card h-100">
                    <div class="row m-0" style="border-bottom: solid 1px #e5e6e9;">
                        <div class="col ">
                            <div class="toggleButttonPostition">
                                <?= labels('maintenance_mode', "Maintenance Mode") ?>
                                <small class="text-muted d-block fs-6">
                                    <?= labels(
                                        'provider_maintenance_mode_note',
                                        "This setting will be applied to both the provider app and the provider panel"
                                    ) ?>
                                </small>
                            </div>
                        </div>
                        <div class="mr-4 mt-4">
                            <input type="text" readonly class="btn btn-primary"
                                value="<?= labels('provider_app', "Provider App") ?> & <?= labels('panel', "Panel") ?>"
                                style="cursor: default;">
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label><?= labels('start_and_end_date', "Start And End Date") ?></label>
                                    <input type="text" name="provider_app_maintenance_schedule_date" id="provider_app_maintenance_schedule_date" class="form-control  daterange-cus" value="<?php echo $provider_app_maintenance_schedule_date ?? ""  ?>">
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="d-flex flex-wrap align-items-center gap-4">
                                    <?php
                                    // Languages are already sorted in controller (default language first)
                                    foreach ($languages as $index => $language) {
                                        if ($language['is_default'] == 1) {
                                            $current_provider_maintenance_language = $language['code'];
                                        }
                                    ?>
                                        <div class="language-provider-maintenance-option mb-3 position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                            id="language-provider-maintenance-<?= $language['code'] ?>"
                                            data-language="<?= $language['code'] ?>"
                                            style="cursor: pointer; padding: 0.5rem 0;">
                                            <span class="language-provider-maintenance-text px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                                style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                                <?= $language['language'] ?><?= $language['is_default'] ? '(Default)' : '' ?>
                                            </span>
                                            <div class="language-provider-maintenance-underline"
                                                style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: #0d6efd; transition: width 0.3s ease; border-radius: 1px;"></div>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <?php
                                // Use the same languages for form fields (already sorted in controller)
                                foreach ($languages as $index => $language) {
                                ?>
                                    <div class="form-group mb-0" id="translationProviderMaintenanceDiv-<?= $language['code'] ?>" <?= $language['code'] == $current_provider_maintenance_language ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                        <label><?= labels('message_for_provider_application', "Message for Provider Application") . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                        <textarea class="form-control" style="min-height:60px" name="message_for_provider_application[<?= $language['code'] ?>]" style="min-height:60px" rows="1"><?php
                                                                                                                                                                                                    // Handle both new multi-language format and old single string format
                                                                                                                                                                                                    if (isset($message_for_provider_application[$language['code']])) {
                                                                                                                                                                                                        echo $message_for_provider_application[$language['code']];
                                                                                                                                                                                                    } else if (is_string($message_for_provider_application) && $language['is_default'] == 1) {
                                                                                                                                                                                                        echo $message_for_provider_application;
                                                                                                                                                                                                    } else {
                                                                                                                                                                                                        echo "";
                                                                                                                                                                                                    }
                                                                                                                                                                                                    ?></textarea>
                                    </div>
                                <?php } ?>
                            </div>
                            <div class="col-md-12 mt-2">
                                <div class="form-group">
                                    <label><?= labels('maintenance_mode', "Maintenance Mode") ?></label>
                                    <br>
                                    <label class=" mt-1 " style="padding-top:0">
                                        <input type="hidden" name="provider_app_maintenance_mode" value='0' id="provider_maintenance_mode_value">
                                        <?php
                                        $provider_app_maintenance_mode = isset($provider_app_maintenance_mode) ? $provider_app_maintenance_mode : 0;
                                        $isMaintenanceMode = $provider_app_maintenance_mode == "1";
                                        $isChecked = isset($provider_app_maintenance_mode) && $provider_app_maintenance_mode == 1;
                                        $defaultValue = isset($compulsary_update_force_update) ? $compulsary_update_force_update : "0";
                                        ?>
                                        <input type="checkbox" class="status-switch" name="provider_app_maintenance_mode" id="provider_maintenance_mode" <?php echo $isChecked ? "checked" : "" ?> value="<?= $defaultValue; ?>">
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($permissions['update']['settings'] == 1) : ?>

            <div class="row mb-3">
                <div class="col-md d-flex justify-content-end">
                    <input type='submit' name='update' id='update' value='<?= labels('save_changes', "Save") ?>' class='btn btn-lg bg-new-primary' />
                    <?= form_close() ?>
                </div>
            </div>
        <?php endif; ?>

    </section>
</div>
<script>
    $('#provider_location_in_provider_details').on('change', function() {
        var value = this.checked ? "1" : "0";
        this.value = this.checked ? "1" : "0";
    }).change();
    $('#otp_system').on('change', function() {
        this.value = this.checked ? 1 : 0;
    }).change();
</script>
<script>
    $(function() {
        $('.fa').popover({
            trigger: "hover"
        });
    })
    $('#customer_compulsary_update_force_update').on('change', function() {
        this.value = this.checked ? 1 : 0;
    }).change();
    $('#provider_compulsary_update_force_update').on('change', function() {
        this.value = this.checked ? 1 : 0;
    }).change();
    $('#customer_maintenance_mode').on('change', function() {
        this.value = this.checked ? 1 : 0;
    }).change();
    $('#provider_maintenance_mode').on('change', function() {
        this.value = this.checked ? 1 : 0;
    }).change();
    // Customer Android Google Ads Status handler
    $('#customer_android_google_ads_status').on('change', function() {
        this.value = this.checked ? 1 : 0;
    }).change();
    // Provider Android Google Ads Status handler
    $('#provider_android_google_ads_status').on('change', function() {
        this.value = this.checked ? 1 : 0;
    }).change();
    // Customer iOS Google Ads Status handler
    $('#customer_ios_google_ads_status').on('change', function() {
        this.value = this.checked ? 1 : 0;
    }).change();
    // Provider iOS Google Ads Status handler
    $('#provider_ios_google_ads_status').on('change', function() {
        this.value = this.checked ? 1 : 0;
    }).change();
    $(document).ready(function() {
        var isMaintenanceMode = <?= json_encode($isChecked) ?>;

        function handleSwitchChange(checkbox) {
            var switchery = $(checkbox).siblings('.switchery');
            if (checkbox.checked) {
                switchery.addClass('yes-content').removeClass('no-content');
            } else {
                switchery.addClass('no-content').removeClass('yes-content');
            }
        }
        handleSwitchChange($('#customer_maintenance_mode')[0]);
        handleSwitchChange($('#provider_maintenance_mode')[0]);
        handleSwitchChange($('#customer_compulsary_update_force_update')[0]);
        handleSwitchChange($('#provider_compulsary_update_force_update')[0]);
        handleSwitchChange($('#provider_location_in_provider_details')[0]);
        // Handle customer and provider Android/iOS Google Ads Status switches
        handleSwitchChange($('#customer_android_google_ads_status')[0]);
        handleSwitchChange($('#provider_android_google_ads_status')[0]);
        handleSwitchChange($('#customer_ios_google_ads_status')[0]);
        handleSwitchChange($('#provider_ios_google_ads_status')[0]);
        $('#customer_maintenance_mode').on('change', function() {
            handleSwitchChange(this);
            this.value = this.checked ? 1 : 0;
        });
        $('#provider_maintenance_mode').on('change', function() {
            handleSwitchChange(this);
            this.value = this.checked ? 1 : 0;
        });
        $('#customer_compulsary_update_force_update').on('change', function() {
            handleSwitchChange(this);
            this.value = this.checked ? 1 : 0;
        });
        $('#provider_compulsary_update_force_update').on('change', function() {
            handleSwitchChange(this);
            this.value = this.checked ? 1 : 0;
        });
        $('#provider_location_in_provider_details').on('change', function() {
            handleSwitchChange(this);
            this.value = this.checked ? 1 : 0;
        });
        // Customer Android Google Ads Status change handler
        $('#customer_android_google_ads_status').on('change', function() {
            handleSwitchChange(this);
            this.value = this.checked ? 1 : 0;
        });
        // Provider Android Google Ads Status change handler
        $('#provider_android_google_ads_status').on('change', function() {
            handleSwitchChange(this);
            this.value = this.checked ? 1 : 0;
        });
        // Customer iOS Google Ads Status change handler
        $('#customer_ios_google_ads_status').on('change', function() {
            handleSwitchChange(this);
            this.value = this.checked ? 1 : 0;
        });
        // Provider iOS Google Ads Status change handler
        $('#provider_ios_google_ads_status').on('change', function() {
            handleSwitchChange(this);
            this.value = this.checked ? 1 : 0;
        });

        // Handle customer maintenance language tab switching
        $('.language-customer-maintenance-option').on('click', function() {
            var selectedLanguage = $(this).data('language');

            // Remove active state from all customer maintenance language options
            $('.language-customer-maintenance-option').removeClass('selected');
            $('.language-customer-maintenance-text').removeClass('text-primary fw-medium').addClass('text-muted');
            $('.language-customer-maintenance-underline').css('width', '0');

            // Add active state to clicked option
            $(this).addClass('selected');
            $(this).find('.language-customer-maintenance-text').removeClass('text-muted').addClass('text-primary fw-medium');
            $(this).find('.language-customer-maintenance-underline').css('width', '100%');

            // Hide all customer maintenance translation divs
            $('[id^="translationCustomerMaintenanceDiv-"]').hide();

            // Show the selected language div
            $('#translationCustomerMaintenanceDiv-' + selectedLanguage).show();
        });

        // Handle provider maintenance language tab switching
        $('.language-provider-maintenance-option').on('click', function() {
            var selectedLanguage = $(this).data('language');

            // Remove active state from all provider maintenance language options
            $('.language-provider-maintenance-option').removeClass('selected');
            $('.language-provider-maintenance-text').removeClass('text-primary fw-medium').addClass('text-muted');
            $('.language-provider-maintenance-underline').css('width', '0');

            // Add active state to clicked option
            $(this).addClass('selected');
            $(this).find('.language-provider-maintenance-text').removeClass('text-muted').addClass('text-primary fw-medium');
            $(this).find('.language-provider-maintenance-underline').css('width', '100%');

            // Hide all provider maintenance translation divs
            $('[id^="translationProviderMaintenanceDiv-"]').hide();

            // Show the selected language div
            $('#translationProviderMaintenanceDiv-' + selectedLanguage).show();
        });

        // Initialize default language states on page load
        // This ensures the correct language is selected when page loads
        $('.language-customer-maintenance-option.selected').trigger('click');
        $('.language-provider-maintenance-option.selected').trigger('click');

        // Send browser timezone so backend can convert maintenance schedule from local time to UTC
        var tzEl = document.getElementById('maintenance_schedule_entry_timezone');
        if (tzEl && typeof Intl !== 'undefined' && Intl.DateTimeFormat) {
            tzEl.value = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
        }

        // AJAX form submission
        $('form[action*="app_settings"]').on('submit', function(e) {
            e.preventDefault();
            var $btn = $(this).find('[type="submit"]');
            var originalVal = $btn.val();
            $btn.prop('disabled', true);
            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: $(this).serialize(),
                success: function(res) {
                    if (res.error === false) {
                        iziToast.success({ title: '', message: res.message, position: 'topRight' });
                    } else {
                        iziToast.error({ title: '', message: res.message, position: 'topRight' });
                    }
                },
                error: function() {
                    iziToast.error({ title: '', message: '<?= labels("something_went_wrong", "Something went wrong") ?>', position: 'topRight' });
                },
                complete: function() {
                    $btn.prop('disabled', false).val(originalVal);
                }
            });
        });
    });
</script>