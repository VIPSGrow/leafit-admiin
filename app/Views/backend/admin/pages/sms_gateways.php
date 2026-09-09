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
<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('sms_gateways', "SMS Gateways") ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active">
                    <a href="<?= base_url('/admin/dashboard') ?>">
                        <i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?>
                    </a>
                </div>
                <div class="breadcrumb-item">
                    <a href="<?= base_url('/admin/settings/system-settings') ?>">
                        <?= labels('system_settings', "System Settings") ?>
                    </a>
                </div>
                <div class="breadcrumb-item"><?= labels('sms_gateways', "SMS Gateways") ?></div>
            </div>
        </div>
        <?php
        $settings = get_settings('system_settings', true);
        $sms_gateway_setting = get_settings('sms_gateway_setting');
        $sms_gateway_data = is_string($sms_gateway_setting) ? json_decode($sms_gateway_setting, true) : [];
        ?>
        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-link active" id="twilio-tab" data-toggle="tab" href="#twilio" role="tab"><?= labels('sms_gateways_configuration', "SMS Gateways Configuration") ?></a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="sms-template-tab" data-toggle="tab" href="#sms_template" role="tab"><?= labels('sms_templates', "SMS Templates") ?></a>
            </li>
        </ul>
        <div class="tab-content">
            <div class="tab-pane fade show active" id="twilio" role="tabpanel">
                <input type="hidden" id="sms_gateway_data" value='<?= json_encode($sms_gateway_data) ?>' />
                <?php
                // Use current_sms_gateway as source of truth; fallback to legacy status flags for old data
                $twilio = $twilio ?? [];
                $fast2sms = $fast2sms ?? [];
                $msg91 = $msg91 ?? [];
                $twofactor = $twofactor ?? [];
                $combirds = $combirds ?? [];
                $current_sms_gateway = $current_sms_gateway ?? '';

                if ($current_sms_gateway === '' && !empty($twilio['twilio_status']) && $twilio['twilio_status'] == '1') {
                    $current_sms_gateway = 'twilio';
                } elseif ($current_sms_gateway === '' && !empty($fast2sms['fast2sms_status']) && $fast2sms['fast2sms_status'] == '1') {
                    $current_sms_gateway = 'fast2sms';
                } elseif ($current_sms_gateway === '' && !empty($msg91['msg91_status']) && $msg91['msg91_status'] == '1') {
                    $current_sms_gateway = 'msg91';
                } elseif ($current_sms_gateway === '' && !empty($twofactor['2factor_status']) && $twofactor['2factor_status'] == '1') {
                    $current_sms_gateway = '2factor';
                } elseif ($current_sms_gateway === '' && !empty($combirds['combirds_status']) && $combirds['combirds_status'] == '1') {
                    $current_sms_gateway = 'combirds';
                }
                ?>
                <form id="sms-gateway-form" method="POST" action="<?= base_url('admin/settings/sms-gateway-settings') ?>">
                    <input type="hidden" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>">
                    <!-- Top row: Fast2SMS and 2Factor -->
                    <div class="row mb-4">
                        <!-- Fast2SMS card -->
                        <div class="col-md-6">
                            <div class="card px-3 h-100">
                                <div class="row border_bottom_for_cards mb-3">
                                    <div class="col ">
                                        <div class='toggleButttonPostition'><?= labels('fast2sms', 'Fast2SMS') ?></div>
                                    </div>
                                    <div class="col d-flex justify-content-end mt-4">
                                        <div class="custom-control custom-switch">
                                            <?php $fast2sms_status = ($current_sms_gateway === 'fast2sms') ? 1 : 0; ?>
                                            <input id="fast2sms_status" class="custom-control-input toggle-switch" type="checkbox" name="fast2sms_status" <?= ($fast2sms_status) == 1 ? 'checked' : '' ?>>
                                            <label for="fast2sms_status" class="custom-control-label"><?= labels('fast2sms', 'Fast2SMS') ?> <?= labels('status', 'Status') ?></label>
                                        </div>
                                    </div>
                                </div>
                                <div class="alert alert-light border mb-3 py-2 px-3" role="region" aria-label="Fast2SMS setup guide">
                                    <small class="text-body d-block mb-1">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        <?= labels('fast2sms_guide_intro', 'Get these credentials from your') ?>
                                        <a href="https://fast2sms.com/dashboard/dev-api" target="_blank" rel="noopener noreferrer" class="alert-link"><strong>Fast2SMS Dev API</strong></a> <?= labels('fast2sms_guide_dashboard', 'dashboard') ?>.
                                    </small>
                                </div>
                                <div class="card-body p-0">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="fast2sms_api_key"><?= labels('authorization_key', 'Authorization Key') ?></label>
                                                <input type="text" value="<?= isset($fast2sms['fast2sms_api_key']) ? (ALLOW_VIEW_KEYS == 0 ? "asc****************adaca" : $fast2sms['fast2sms_api_key']) : '' ?>" name='fast2sms_api_key' id='fast2sms_api_key' placeholder='<?= labels('enter_authorization_key', 'Enter Authorization Key') ?>' class="form-control" />
                                                <small class="form-text text-muted"><?= labels('fast2sms_api_key_help', 'API key from Dev API section. Keep it secret.') ?></small>
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="fast2sms_sender_id"><?= labels('sender_id', 'Sender ID') ?></label>
                                                <input type="text" value="<?= isset($fast2sms['fast2sms_sender_id']) ? (ALLOW_VIEW_KEYS == 0 ? "asc****************adaca" : $fast2sms['fast2sms_sender_id']) : '' ?>" name='fast2sms_sender_id' id='fast2sms_sender_id' placeholder='<?= labels('enter_sender_id', 'Enter Sender ID') ?>' class="form-control" />
                                                <small class="form-text text-muted"><?= labels('fast2sms_sender_id_help', 'DLT-approved 3–6 letter Sender ID, e.g. FSTSMS') ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- 2Factor card -->
                        <div class="col-md-6">
                            <div class="card px-3 h-100">
                                <div class="row border_bottom_for_cards mb-3">
                                    <div class="col ">
                                        <div class='toggleButttonPostition'><?= labels('2factor', '2Factor') ?></div>
                                    </div>
                                    <div class="col d-flex justify-content-end mt-4">
                                        <div class="custom-control custom-switch">
                                            <?php $twofactor_status = ($current_sms_gateway === '2factor') ? 1 : 0; ?>
                                            <input id="twofactor_status" class="custom-control-input toggle-switch" type="checkbox" name="2factor_status" <?= ($twofactor_status) == 1 ? 'checked' : '' ?>>
                                            <label for="twofactor_status" class="custom-control-label"><?= labels('2factor', '2Factor') ?> <?= labels('status', 'Status') ?></label>
                                        </div>
                                    </div>
                                </div>
                                <div class="alert alert-light border mb-3 py-2 px-3" role="region" aria-label="2Factor setup guide">
                                    <small class="text-body d-block mb-1">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        <?= labels('2factor_guide_intro', 'Get these credentials from your') ?>
                                        <a href="https://2factor.in" target="_blank" rel="noopener noreferrer" class="alert-link"><strong>2Factor.in</strong></a> <?= labels('2factor_guide_dashboard', 'dashboard') ?>.
                                    </small>
                                </div>
                                <div class="card-body p-0">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="2factor_api_key"><?= labels('api_key', 'API Key') ?></label>
                                                <input type="text" value="<?= isset($twofactor['2factor_api_key']) ? (ALLOW_VIEW_KEYS == 0 ? "asc****************adaca" : $twofactor['2factor_api_key']) : '' ?>" name='2factor_api_key' id='2factor_api_key' placeholder='<?= labels('enter_api_key', 'Enter API Key') ?>' class="form-control" />
                                                <small class="form-text text-muted"><?= labels('2factor_api_key_help', 'API key from 2Factor dashboard. Keep it secret.') ?></small>
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="2factor_sender_id"><?= labels('sender_id', 'Sender ID') ?></label>
                                                <input type="text" value="<?= isset($twofactor['2factor_sender_id']) ? (ALLOW_VIEW_KEYS == 0 ? "asc****************adaca" : $twofactor['2factor_sender_id']) : '' ?>" name='2factor_sender_id' id='2factor_sender_id' placeholder='<?= labels('enter_sender_id', 'Enter Sender ID') ?>' class="form-control" />
                                                <small class="form-text text-muted"><?= labels('2factor_sender_id_help', 'Sender ID shown to recipient (e.g. 2FACTOR)') ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Middle row: Twilio and MSG91 -->
                    <div class="row mb-4">
                        <!-- Twilio card -->
                        <div class="col-md-6">
                            <div class="card px-3 h-100">
                                <div class="row border_bottom_for_cards mb-3">
                                    <div class="col ">
                                        <div class='toggleButttonPostition'><?= labels('twilio', 'Twilio') ?></div>
                                    </div>
                                    <div class="col d-flex justify-content-end mt-4">
                                        <div class="custom-control custom-switch">
                                            <?php $twilio_status = ($current_sms_gateway === 'twilio') ? 1 : 0; ?>
                                            <input id="twilio_status" class="custom-control-input toggle-switch" type="checkbox" name="twilio_status" <?= ($twilio_status) == 1 ? 'checked' : '' ?>>
                                            <label for="twilio_status" class="custom-control-label"><?= labels('twilio', 'Twilio') ?> <?= labels('status', 'Status') ?></label>
                                        </div>
                                    </div>
                                </div>
                                <div class="alert alert-light border mb-3 py-2 px-3" role="region" aria-label="Twilio setup guide">
                                    <small class="text-body d-block mb-1">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        <?= labels('twilio_guide_intro', 'Get these credentials from your') ?>
                                        <a href="https://console.twilio.com" target="_blank" rel="noopener noreferrer" class="alert-link"><strong>Twilio Console</strong></a>.
                                    </small>
                                </div>
                                <div class="card-body p-0">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="twilio_account_sid"><?= labels('account_sid', 'Account SID') ?></label>
                                                <input type="text" value="<?= isset($twilio['twilio_account_sid']) ? (ALLOW_VIEW_KEYS == 0 ? "asc****************adaca" : $twilio['twilio_account_sid']) : '' ?>" name='twilio_account_sid' id='twilio_account_sid' placeholder='<?= labels('enter_account_sid', 'Enter Account SID') ?>' class="form-control" />
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="twilio_auth_token"><?= labels('auth_token', 'Auth Token') ?></label>
                                                <input type="text" value="<?= isset($twilio['twilio_auth_token']) ? (ALLOW_VIEW_KEYS == 0 ? "asc****************adaca" : $twilio['twilio_auth_token']) : '' ?>" name='twilio_auth_token' id='twilio_auth_token' placeholder='<?= labels('enter_auth_token', 'Enter Auth Token') ?>' class="form-control" />
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="twilio_from"><?= labels('from', 'From') ?></label>
                                                <input type="text" value="<?= isset($twilio['twilio_from']) ? (ALLOW_VIEW_KEYS == 0 ? "asc****************adaca" : $twilio['twilio_from']) : '' ?>" name='twilio_from' id='twilio_from' placeholder='<?= labels('enter_from', 'Enter From') ?>' class="form-control" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- MSG91 card -->
                        <div class="col-md-6">
                            <div class="card px-3 h-100">
                                <div class="row border_bottom_for_cards mb-3">
                                    <div class="col ">
                                        <div class='toggleButttonPostition'><?= labels('msg91', 'MSG91') ?></div>
                                    </div>
                                    <div class="col d-flex justify-content-end mt-4">
                                        <div class="custom-control custom-switch">
                                            <?php $msg91_status = ($current_sms_gateway === 'msg91') ? 1 : 0; ?>
                                            <input id="msg91_status" class="custom-control-input toggle-switch" type="checkbox" name="msg91_status" <?= ($msg91_status) == 1 ? 'checked' : '' ?>>
                                            <label for="msg91_status" class="custom-control-label"><?= labels('msg91', 'MSG91') ?> <?= labels('status', 'Status') ?></label>
                                        </div>
                                    </div>
                                </div>
                                <div class="alert alert-light border mb-3 py-2 px-3" role="region" aria-label="MSG91 setup guide">
                                    <small class="text-body d-block mb-1">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        <?= labels('msg91_guide_intro', 'Get your authkey from the') ?>
                                        <a href="https://control.msg91.com" target="_blank" rel="noopener noreferrer" class="alert-link"><strong>MSG91 Control Panel</strong></a>.
                                    </small>
                                </div>
                                <div class="card-body p-0">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="msg91_authkey"><?= labels('authkey', 'Auth Key') ?></label>
                                                <input type="text" value="<?= isset($msg91['msg91_authkey']) ? (ALLOW_VIEW_KEYS == 0 ? "asc****************adaca" : $msg91['msg91_authkey']) : '' ?>" name='msg91_authkey' id='msg91_authkey' placeholder='<?= labels('enter_authkey', 'Enter Auth Key') ?>' class="form-control" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom row: Combirds Gateway -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card px-3 h-100">
                                <div class="row border_bottom_for_cards mb-3">
                                    <div class="col">
                                        <div class='toggleButttonPostition'>Combirds</div>
                                    </div>
                                    <div class="col d-flex justify-content-end mt-4">
                                        <div class="custom-control custom-switch">
                                            <?php $combirds_status = ($current_sms_gateway === 'combirds') ? 1 : 0; ?>
                                            <input id="combirds_status" class="custom-control-input toggle-switch" type="checkbox" name="combirds_status" <?= ($combirds_status) == 1 ? 'checked' : '' ?>>
                                            <label for="combirds_status" class="custom-control-label">Combirds Status</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="alert alert-light border mb-3 py-2 px-3">
                                    <small class="text-body d-block mb-1">
                                        <i class="fas fa-info-circle mr-1"></i> Get credentials from your <a href="https://smsapi.edumarcsms.com" target="_blank" class="alert-link"><strong>Combirds / Edumarc</strong></a> portal.
                                    </small>
                                </div>
                                <div class="card-body p-0">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="combirds_api_key">API Key</label>
                                                <input type="text" value="<?= isset($combirds['combirds_api_key']) ? (ALLOW_VIEW_KEYS == 0 ? "asc****************adaca" : $combirds['combirds_api_key']) : '' ?>" name="combirds_api_key" id="combirds_api_key" placeholder="Enter API Key" class="form-control" />
                                            </div>
                                        </div>
                                      	<div class="col-md-12">
                                            <div class="form-group">
                                                <label for="combirds_t_api_key">API Key ( Transaction )</label>
                                                <input type="text" value="<?= isset($combirds['combirds_t_api_key']) ? (ALLOW_VIEW_KEYS == 0 ? "asc****************adaca" : $combirds['combirds_t_api_key']) : '' ?>" name="combirds_t_api_key" id="combirds_t_api_key" placeholder="Enter API Key" class="form-control" />
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="combirds_sender_id">Sender ID</label>
                                                <input type="text" value="<?= isset($combirds['combirds_sender_id']) ? (ALLOW_VIEW_KEYS == 0 ? "asc****************adaca" : $combirds['combirds_sender_id']) : '' ?>" name="combirds_sender_id" id="combirds_sender_id" placeholder="Enter Sender ID" class="form-control" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <?php if ($permissions['update']['settings'] == 1) : ?>
                            <div class="col-md d-flex justify-content-lg-end mr-1">
                                <div class="form-group">
                                    <input type='submit' name='update' id='update' value='<?= labels('save_changes', "Save Changes") ?>' class='btn btn-primary' />
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <?php if ($permissions['read']['settings'] == 1) : ?>
                <div class="tab-pane fade show " id="sms_template" role="tabpanel">
                    <div class="card">
                        <div class="col mb-3" style="border-bottom: solid 1px #e5e6e9;">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="toggleButttonPostition"><?= labels('sms_templates', "SMS Templates") ?></div>
                                <div class="dropdown d-inline ml-2">
                                    <button class="btn export_download dropdown-toggle" type="button" id="smsTemplatesDownloadDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <?= labels('download', 'Download') ?>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-right" aria-labelledby="smsTemplatesDownloadDropdown">
                                        <a class="dropdown-item sms-templates-export" href="#" data-export-type="pdf"><?= labels('pdf', 'PDF') ?></a>
                                        <a class="dropdown-item sms-templates-export" href="#" data-export-type="excel"><?= labels('excel', 'Excel') ?></a>
                                        <a class="dropdown-item sms-templates-export" href="#" data-export-type="csv"><?= labels('csv', 'CSV') ?></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="col-md-12">
                                <table class="table " data-fixed-columns="true" id="user_list" data-pagination-successively-size="2" data-detail-formatter="user_formater" data-auto-refresh="true" data-toggle="table" data-url="<?= base_url("admin/settings/sms-templates-list") ?>" data-side-pagination="server" data-pagination="true" data-page-list="[5, 10, 25, 50, 100, 200, All]" data-search="false" data-show-columns="false" data-show-columns-search="true" data-show-refresh="false" data-sort-name="id" data-sort-order="desc" data-query-params="sms_query_params">
                                    <thead>
                                        <tr>
                                            <th data-field="id" class="text-center" data-visible="true" data-sortable="true"><?= labels('id', 'ID') ?></th>
                                            <th data-field="title" class="text-center"><?= labels('title', 'Title') ?></th>
                                            <th data-field="type" class="text-center"><?= labels('type', 'Type') ?></th>
                                            <th data-field="truncatedtemplate" class="text-center" data-visible="true"><?= labels('template', 'template') ?></th>
                                            <th data-field="parameters" class="text-center" data-visible="true"><?= labels('parameters', 'Parameters') ?></th>
                                            <th data-field="operations" class="text-center" data-events="sms_gateway_events"><?= labels('operations', 'Operations') ?></th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>
</div>

<script>
    $(document).on('submit', '#sms-gateway-form', function(e) {
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
                $('[name="<?= csrf_token() ?>"]').val(res.csrfHash ?? res.csrf_hash ?? '');
            },
            error: function() {
                iziToast.error({ title: '', message: '<?= labels("something_went_wrong", "Something went wrong") ?>', position: 'topRight' });
            },
            complete: function() {
                $btn.prop('disabled', false).val(originalVal);
            }
        });
    });

    document.addEventListener('DOMContentLoaded', function() {
        const switches = document.querySelectorAll('.toggle-switch');

        switches.forEach(switchInput => {
            switchInput.value = switchInput.checked ? 'true' : 'false';

            switchInput.addEventListener('change', function() {
                this.value = this.checked ? 'true' : 'false';

                if (this.checked) {
                    switches.forEach(input => {
                        if (input !== this) {
                            input.checked = false;
                            input.value = 'false';
                        }
                    });
                }
            });
        });
    });
</script>