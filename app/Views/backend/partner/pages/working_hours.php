<?php
$days = [
    'monday'    => 'Monday',
    'tuesday'   => 'Tuesday',
    'wednesday' => 'Wednesday',
    'thursday'  => 'Thursday',
    'friday'    => 'Friday',
    'saturday'  => 'Saturday',
    'sunday'    => 'Sunday',
];
?>
<script>
    window.stepperLabels = {
        working_hours_applied_to_all: "<?= labels('working_hours_applied_to_all', 'Working hours applied to all days') ?>",
        shift_end_after_start: "<?= labels('shift_end_after_start', 'End time must be after start time') ?>",
        shift_overlap: "<?= labels('shift_overlap', 'Shift {shift1} overlaps with Shift {shift2} on {day}') ?>"
    };
</script>
<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('working_hours', 'Working Hours') ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('partner/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item"><?= labels('working_hours', 'Working Hours') ?></div>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <div class="mb-3 d-flex justify-content-between align-items-start">
                    <div>
                        <h5 class="mb-1"><?= labels('working_schedule', 'Working Schedule') ?></h5>
                        <p class="text-muted mb-0"><?= labels('working_schedule_subtitle', 'Configure when your services are available to customers.') ?></p>
                    </div>
                    <button type="button" id="apply_to_all_days"
                        class="btn btn-light text-primary font-weight-medium d-inline-flex align-items-center">
                        <i class="far fa-copy mr-2"></i>
                        <?= labels('apply_to_all', 'Apply to all') ?>
                    </button>
                </div>

                <?= form_open('/partner/working-hours/update', ['method' => 'post', 'class' => 'form-submit-event', 'id' => 'partner_working_hours_form', 'novalidate' => 'novalidate']); ?>

                <div class="working-days-list" id="working_days_grid">
                    <?php foreach ($days as $key => $day):
                        $index     = array_search($key, array_keys($days));
                        $dayShifts = $provider_shifts_by_day[$key] ?? [];
                        if (!empty($dayShifts)) {
                            $primaryShift = $dayShifts[0];
                            $opening_time = substr((string) $primaryShift['opening_time'], 0, 5);
                            $closing_time = substr((string) $primaryShift['closing_time'], 0, 5);
                            $is_open      = (int) $primaryShift['is_open'] === 1;
                            $extraShifts  = array_slice($dayShifts, 1);
                        } else {
                            $opening_time = isset($partner_timings[$index]['opening_time']) ? substr((string) $partner_timings[$index]['opening_time'], 0, 5) : '09:00';
                            $closing_time = isset($partner_timings[$index]['closing_time']) ? substr((string) $partner_timings[$index]['closing_time'], 0, 5) : '18:00';
                            $is_open      = isset($partner_timings[$index]['is_open']) && $partner_timings[$index]['is_open'] == '1';
                            $extraShifts  = [];
                        }
                        ?>
                        <div class="schedule-day-card schedule-day-row <?= $is_open ? 'active' : '' ?>"
                            data-day="<?= $key ?>">
                            <div class="schedule-day-head">
                                <div class="custom-control custom-switch mb-0">
                                    <input type="checkbox" class="custom-control-input day-toggle"
                                        id="day_toggle_<?= $key ?>" name="<?= $key ?>"
                                        <?= $is_open ? 'checked' : '' ?>>
                                    <label class="custom-control-label" for="day_toggle_<?= $key ?>"></label>
                                </div>
                                <div class="schedule-day-icon">
                                    <i class="far fa-clock"></i>
                                </div>  
                                <h6 class="schedule-day-name mb-0"><?= labels($key, $day) ?></h6>
                            </div>

                            <div class="schedule-day-shifts">
                                <div class="day-time-row d-flex align-items-center">
                                    <div class="flex-fill">
                                        <input type="time" class="form-control form-control-sm start_time"
                                            name="start_time[]" value="<?= $opening_time ?>">
                                    </div>
                                    <span class="text-muted mx-2 font-weight-bold">—</span>
                                    <div class="flex-fill endTime">
                                        <input type="time" class="form-control form-control-sm end_time"
                                            name="end_time[]" value="<?= $closing_time ?>">
                                    </div>
                                    <button type="button" class="btn btn-add-shift" aria-label="<?= labels('add_break_shift', 'Add Break / Shift') ?>"
                                        data-tooltip="<?= labels('add_break_shift', 'Add Break / Shift') ?>">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                                <div class="extra-shifts">
                                    <?php foreach ($extraShifts as $extra):
                                        $extraStart = substr((string) ($extra['opening_time'] ?? ''), 0, 5);
                                        $extraEnd   = substr((string) ($extra['closing_time'] ?? ''), 0, 5);
                                        ?>
                                        <div class="day-time-row d-flex align-items-center extra-shift-row">
                                            <div class="flex-fill">
                                                <input type="time" class="form-control form-control-sm extra_start_time"
                                                    name="extra_shifts[<?= $key ?>][start][]" value="<?= $extraStart ?>">
                                            </div>
                                            <span class="text-muted mx-2 font-weight-bold">—</span>
                                            <div class="flex-fill">
                                                <input type="time" class="form-control form-control-sm extra_end_time"
                                                    name="extra_shifts[<?= $key ?>][end][]" value="<?= $extraEnd ?>">
                                            </div>
                                            <button type="button" class="btn btn-sm btn-remove-shift ml-2" aria-label="<?= labels('remove_shift', 'Remove shift') ?>">
                                                <i class="far fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <span class="schedule-day-closed"><?= labels('closed', 'Closed') ?></span>
                        </div>
                    <?php endforeach; ?>
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
    $(document).on('change', '.start_time', function() {
        var v = $(this).val();
        $(this).closest('.day-time-row').find('.end_time').attr('min', v);
    });

    // Local submit handler — prevents global `.form-submit-event` handlers (partner.js
    // reloads the page on success). We post via AJAX, show toast, and leave the
    // already-rendered UI in place since user input already reflects the saved state.
    // Attached directly to the form (not document) so it fires before the
    // document-delegated `.form-submit-event` handlers and stopImmediatePropagation
    // actually prevents them.
    $(function () {
        var $form = $('#partner_working_hours_form');
        if (!$form.length) return;
        $form.removeClass('form-submit-event');
        $form.on('submit', onWorkingHoursFormSubmit);
    });

    function onWorkingHoursFormSubmit(e) {
        e.preventDefault();
        e.stopImmediatePropagation();

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
