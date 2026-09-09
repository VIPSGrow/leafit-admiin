<?php
$ss              = $slot_settings ?? [];
$ss_interval     = (int) ($ss['slot_interval']             ?? 30);
$ss_allowMulti   = (int) ($ss['allow_multiple_bookings']   ?? 0) === 1;
$ss_capacity     = (int) ($ss['slot_capacity']             ?? 1);
$ss_minValue     = (int) ($ss['min_advance_booking_value'] ?? 1);
$ss_minUnit      = (string) ($ss['min_advance_booking_unit'] ?? 'hours');
$ss_maxDays      = (int) ($ss['max_advance_booking_days']  ?? 30);
$ss_sameDay      = (int) ($ss['same_day_booking']          ?? 1) === 1;
$ss_bufBefore    = (int) ($ss['buffer_before']             ?? 0);
$ss_bufAfter     = (int) ($ss['buffer_after']              ?? 0);
$ss_isIndividual = isset($partner_details['type']) && (int) $partner_details['type'] === 0;
?>
<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('slot_configuration', 'Slot Configuration') ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('partner/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item"><?= labels('slot_configuration', 'Slot Configuration') ?></div>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <div class="mb-3">
                    <h5 class="mb-1"><?= labels('configure_scheduling', 'Configure Scheduling') ?></h5>
                    <p class="text-muted mb-0"><?= labels('configure_scheduling_subtitle', 'Configure slot intervals, booking windows, and buffer rules.') ?></p>
                </div>

                <?= form_open('/partner/slot-configuration/update', ['method' => 'post', 'id' => 'partner_slot_configuration_form', 'novalidate' => 'novalidate']); ?>

                <div class="scheduling-config-section">
                    <div class="stepper-section-divider">
                        <p><?= labels('slot_behavior', 'Slot Behavior') ?></p>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="slot_interval" class="required"><?= labels('slot_interval', 'Slot Interval') ?></label>
                                <div class="input-group">
                                    <input id="slot_interval" class="form-control" type="number"
                                        name="slot_interval" min="5" max="240" step="5"
                                        value="<?= $ss_interval ?>" placeholder="5 - 240"
                                        title="<?= labels('slot_interval_invalid', 'Enter a value in steps of 5, between 5 and 240 minutes.') ?>"
                                        required>
                                    <div class="input-group-append">
                                        <span class="input-group-text"><?= labels('minutes', 'minutes') ?></span>
                                    </div>
                                </div>
                                <div class="invalid-feedback" id="slot_interval_error"></div>
                                <small class="form-text text-muted">
                                    <i class="fas fa-info-circle"></i>
                                    <?= labels('slot_interval_helper', 'Determines how booking start times are generated.') ?>
                                </small>
                            </div>
                        </div>
                        <div class="col-md-4 my-1" id="capacity_settings_wrapper" <?= $ss_isIndividual ? 'style="display:none;"' : '' ?>>
                            <div class="form-group">
                                <div class="d-flex">
                                    <div class="custom-control custom-switch mb-0" style="padding-top:0; align-items: unset;">
                                        <input type="checkbox" class="custom-control-input" id="allow_multiple_bookings" name="allow_multiple_bookings" <?= $ss_allowMulti ? 'checked' : '' ?>>
                                        <label class="custom-control-label" for="allow_multiple_bookings"></label>
                                    </div>
                                    <label for="allow_multiple_bookings" class="mb-0 ml-2">
                                        <?= labels('allow_multiple_bookings', 'Allow Multiple Concurrent Bookings') ?>
                                    </label>
                                </div>
                                <small class="form-text text-muted">
                                    <?= labels('allow_multiple_bookings_description', 'Enable when more than one booking can be served at the same time (e.g. multiple staff or chairs).') ?>
                                </small>
                            </div>
                        </div>
                        <div class="col-md-4" id="slot_capacity_col" style="display: <?= (!$ss_isIndividual && $ss_allowMulti) ? 'block' : 'none' ?>;">
                            <div class="form-group">
                                <label for="slot_capacity" class="required"><?= labels('concurrent_booking_capacity', 'Concurrent Booking Capacity') ?></label>
                                <input id="slot_capacity" class="form-control" type="number" name="slot_capacity"
                                    min="1" max="100" value="<?= $ss_capacity ?>"
                                    oninput="this.value = Math.max(1, Math.min(100, Math.abs(parseInt(this.value) || 1)))"
                                    placeholder="<?= labels('enter', 'Enter') ?> <?= labels('concurrent_booking_capacity', 'Concurrent Booking Capacity') ?>">
                                <small class="form-text text-muted">
                                    <i class="fas fa-info-circle"></i>
                                    <?= labels('concurrent_booking_capacity_helper', 'Maximum simultaneous booking capacity.') ?>
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="stepper-section-divider">
                        <p><?= labels('booking_window_rules', 'Booking Window Rules') ?></p>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="min_advance_booking" class="required"><?= labels('minimum_advance_booking', 'Minimum Advance Booking') ?></label>
                                <div class="input-group w-100">
                                    <input id="min_advance_booking" class="form-control flex-fill" type="number"
                                        name="min_advance_booking" min="0" value="<?= $ss_minValue ?>"
                                        oninput="this.value = Math.abs(parseInt(this.value) || 0)" required>
                                    <div class="input-group-append" style="flex: 0 0 110px;">
                                        <select class="form-control" name="min_advance_booking_unit" id="min_advance_booking_unit" style="width: 110px; padding-right: 1.5rem;">
                                            <option value="minutes" <?= $ss_minUnit === 'minutes' ? 'selected' : '' ?>><?= labels('minutes', 'Minutes') ?></option>
                                            <option value="hours"   <?= $ss_minUnit === 'hours'   ? 'selected' : '' ?>><?= labels('hours', 'Hours') ?></option>
                                            <option value="days"    <?= $ss_minUnit === 'days'    ? 'selected' : '' ?>><?= labels('days', 'Days') ?></option>
                                        </select>
                                    </div>
                                </div>
                                <small class="form-text text-muted">
                                    <i class="fas fa-info-circle"></i>
                                    <?= labels('min_advance_booking_helper', 'Earliest a customer can book before service start.') ?>
                                </small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="advance_booking_days" class="required"><?= labels('maximum_future_booking_days', 'Maximum Future Booking Days') ?></label>
                                <input id="advance_booking_days" class="form-control" type="number"
                                    name="advance_booking_days" min="1" value="<?= $ss_maxDays ?>"
                                    oninput="this.value = Math.abs(parseInt(this.value) || 1)" required>
                                <small class="form-text text-muted">
                                    <i class="fas fa-info-circle"></i>
                                    <?= labels('max_future_booking_helper', 'Customers can book this many days into the future.') ?>
                                </small>
                            </div>
                        </div>
                        <div class="col-md-4 my-1">
                            <div class="form-group">
                                <div class="d-flex">
                                    <div class="custom-control custom-switch mb-0" style="padding-top:0; align-items: unset;">
                                        <input type="checkbox" class="custom-control-input" id="same_day_booking" name="same_day_booking" <?= $ss_sameDay ? 'checked' : '' ?>>
                                        <label class="custom-control-label" for="same_day_booking"></label>
                                    </div>
                                    <label for="same_day_booking" class="mb-0 ml-2">
                                        <?= labels('same_day_booking', 'Same Day Booking') ?>
                                    </label>
                                </div>
                                <small class="form-text text-muted">
                                    <?= labels('same_day_booking_description', 'Allow customers to place bookings for today.') ?>
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="stepper-section-divider">
                        <p><?= labels('default_buffer_rules', 'Default Buffer Rules') ?></p>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="buffer_before"><?= labels('buffer_before_booking', 'Buffer Before Booking') ?></label>
                                <div class="input-group">
                                    <input id="buffer_before" class="form-control" type="number"
                                        name="buffer_before" min="0" value="<?= $ss_bufBefore ?>"
                                        oninput="this.value = Math.abs(parseInt(this.value) || 0)">
                                    <div class="input-group-append">
                                        <span class="input-group-text"><?= labels('minutes', 'minutes') ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="buffer_after"><?= labels('buffer_after_booking', 'Buffer After Booking') ?></label>
                                <div class="input-group">
                                    <input id="buffer_after" class="form-control" type="number"
                                        name="buffer_after" min="0" value="<?= $ss_bufAfter ?>"
                                        oninput="this.value = Math.abs(parseInt(this.value) || 0)">
                                    <div class="input-group-append">
                                        <span class="input-group-text"><?= labels('minutes', 'minutes') ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <small class="form-text text-muted">
                                <i class="fas fa-info-circle"></i>
                                <?= labels('buffer_helper', 'Additional unavailable time added before or after bookings.') ?>
                            </small>
                        </div>
                    </div>
                </div>

                <div class="form-group d-flex justify-content-end mt-3">
                    <button type="submit" class="btn btn-primary submit_btn">
                        <i class="fas fa-check"></i> <?= labels('save', 'Save') ?>
                    </button>
                </div>

                <?= form_close() ?>
            </div>
        </div>
    </section>
</div>

<script>
    // Slot interval: free-form minutes — must be a multiple of 5 within [5, 240].
    function validateSlotInterval() {
        var $input = $('#slot_interval');
        var $error = $('#slot_interval_error');
        var raw    = String($input.val()).trim();
        var n      = parseInt(raw, 10);
        var msg    = '';

        if (raw === '' || isNaN(n) || n < 5 || n > 240 || n % 5 !== 0) {
            msg = '<?= labels('slot_interval_invalid', 'Enter a value in steps of 5, between 5 and 240 minutes.') ?>';
        }

        $input.toggleClass('is-invalid', msg !== '');
        $error.text(msg).css('display', msg !== '' ? 'block' : '');
        return msg === '';
    }
    $(document).on('input blur', '#slot_interval', validateSlotInterval);

    function toggleSlotCapacityField() {
        var on = $('#allow_multiple_bookings').is(':checked');
        $('#slot_capacity_col').toggle(on);
        if (!on) {
            $('#slot_capacity').val(1);
        } else if (parseInt($('#slot_capacity').val(), 10) <= 1) {
            $('#slot_capacity').val(2);
        }
    }
    $(document).on('change', '#allow_multiple_bookings', toggleSlotCapacityField);

    $(function () {
        var $form = $('#partner_slot_configuration_form');
        if (!$form.length) return;
        $form.on('submit', onSlotConfigurationFormSubmit);
    });

    function onSlotConfigurationFormSubmit(e) {
        e.preventDefault();
        e.stopImmediatePropagation();

        if (!validateSlotInterval()) {
            $('#slot_interval').focus();
            return;
        }

        var $form = $(this);
        var $submitBtn = $form.find('.submit_btn');
        var btnHtml = $submitBtn.html();
        var formData = new FormData(this);
        formData.append(csrfName, csrfHash);

        $.ajax({
            type: 'POST',
            url: $form.attr('action'),
            data: formData,
            cache: false,
            contentType: false,
            processData: false,
            dataType: 'json',
            beforeSend: function () {
                $submitBtn.prop('disabled', true);
                $submitBtn.html('<div class="spinner-border text-light spinner-border-sm mx-3" role="status"><span class="visually-hidden"></span></div>');
            },
            success: function (response) {
                if (response && response.csrfName) csrfName = response.csrfName;
                if (response && response.csrfHash) csrfHash = response.csrfHash;

                if (response && response.error === false) {
                    showToastMessage(response.message, 'success');
                } else {
                    var msg = (response && typeof response.message === 'string')
                        ? response.message
                        : '<?= labels('something_went_wrong', 'Something went wrong') ?>';
                    showToastMessage(msg, 'error');
                }
            },
            error: function () {
                showToastMessage('<?= labels('something_went_wrong', 'Something went wrong') ?>', 'error');
            },
            complete: function () {
                $submitBtn.prop('disabled', false);
                $submitBtn.html(btnHtml);
            }
        });
    }
</script>
