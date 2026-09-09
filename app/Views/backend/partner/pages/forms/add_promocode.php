<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('add_promocodes', 'Add Promocode') ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/partner/dashboard') ?>"> <i class="fas fa-home-alt text-primary"></i><?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item"><a href="<?= base_url('partner/promo_codes') ?>"> <?= labels('promocode', 'Promocodes') ?></a></div>
            </div>
        </div>
        <div class="section-body">
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <form method="post" action="<?= base_url('partner/promo_codes/save') ?>" id="promo_code_form" class="form-submit-event">
                            <div class="row pl-3">
                                <div class="col border_bottom_for_cards">
                                    <div class="toggleButttonPostition"><?= labels('add_promocodes', 'Add Promocode') ?></div>
                                </div>
                                <div class="col d-flex justify-content-end mr-3 mt-4 border_bottom_for_cards">
                                    <input type="checkbox" id="promocode_status" name="status" class="status-switch">
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="promo_code" class="required"><?= labels('promocode', 'Promocode') ?></label>
                                            <input type="text" class="form-control" id="promo_code" name="promo_code">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="start_date" class="required"><?= labels('start_date', 'Start Date') ?></label>
                                            <input type="text" class="form-control datepicker" id="start_date" name="start_date">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="end_date" class="required"><?= labels('end_date', 'End Date') ?></label>
                                            <input type="text" class="form-control datepicker" id="end_date" name="end_date">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="no_of_users" class="required"><?= labels('no_of_users', 'No. of users') ?></label>
                                            <i data-content=" <?= labels('data_content_for_no_of_user', "Only the first X number of users can apply it. For example, if you have allowed 10, then the first 10 users can use this promo code.") ?>" class="fa fa-question-circle" data-original-title="" title=""></i>
                                            <input type="number" class="form-control" id="no_of_users" name="no_of_users" min="0" oninput="this.value = !!this.value && Math.abs(this.value) >= 0 ? Math.abs(this.value) : null">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="minimum_order_amount" class="required"><?= labels('minimum_order_amount', 'Minimum order amount') ?></label>
                                            <i data-content=" <?= labels('data_content_for_minimum_booking_amount', "Customers can apply a promo code if the subtotal of their service is higher than the Minimum Booking amount.") ?>" class="fa fa-question-circle" data-original-title="" title=""></i>
                                            <input type="number" class="form-control" id="minimum_order_amount" name="minimum_order_amount" min="0" oninput="this.value = !!this.value && Math.abs(this.value) >= 0 ? Math.abs(this.value) : null">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="discount" class="required"><?= labels('discount', 'Discount') ?></label>
                                            <input type="number" class="form-control" id="discount" name="discount" min="0" oninput="this.value = !!this.value && Math.abs(this.value) >= 0 ? Math.abs(this.value) : null">
                                        </div>
                                    </div>

                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="discount_type" class="required"><?= labels('discount_type', 'Discount Type') ?></label>
                                            <i data-content=" <?= labels('data_content_for_max_discount_amount', "You want to offer a discount based on a percentage or a fixed amount of the total cost of the services.") ?>" class="fa fa-question-circle" data-original-title="" title=""></i>
                                            <select name="discount_type" id="discount_type" class="form-control">
                                                <option value="amount"><?= labels('amount', 'Amount') ?></option>
                                                <option value="percentage"><?= labels('percentage', 'Percentage') ?></option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="max_discount_amount"><?= labels('max_discount_amount', 'Max Discount Amount') ?></label>
                                            <i data-content=" <?= labels('data_content_for_discount_type', "This promo code gives customers a maximum discount of X amount.") ?>" class="fa fa-question-circle" data-original-title="" title=""></i>
                                            <input type="number" class="form-control" id="max_discount_amount" name="max_discount_amount">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="image" class="required"><?= labels('image', 'Image') ?></label> <small>(<?= labels('promocode_image_recommended_size', 'We recommend 50 x 50 pixels') ?>)</small>
                                            <input type="file" class="filepond" id="image" name="image" accept="image/*">
                                            <input type="hidden" name="old_image" id="old_image" value="">
                                        </div>
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label class="custom-switch mt-2">
                                            <span class="custom-switch-description"><?= labels('repeat_usage', 'Repeat Usage ?') ?></span>
                                            <i data-content=" <?= labels('data_content_for_repeat_usage', "If it's allowed, customers can use this promo code many times.") ?>" class="fa fa-question-circle mr-2" data-original-title="" title=""></i>
                                        </label>
                                        <input type="checkbox" id="repeat_usage" name="repeat_usage" class="status-switch editRepeatUsageInModel">
                                    </div>
                                    <div class="col-md-4 repeat_usage">
                                        <div class="form-group" class="required">
                                            <label for="no_of_repeat_usage"><?= labels('no_of_repeat_usage', 'No. of repeat usage') ?></label>
                                            <i data-content=" <?= labels('data_content_for_no_of_repeat_usage', "customers can use the promo code a certain number of times. For example, if you set it to 10, customers can use the promo code up to 10 times when booking the services, as long as the conditions are met.") ?>" class="fa fa-question-circle" data-original-title="" title=""></i>
                                            <input type="number" class="form-control" id="no_of_repeat_usage" name="no_of_repeat_usage" min="0" oninput="this.value = !!this.value && Math.abs(this.value) >= 0 ? Math.abs(this.value) : null">
                                        </div>
                                    </div>
                                </div>
                                <!-- Multi-language message section -->
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <div class="d-flex flex-wrap align-items-center gap-4">
                                            <?php
                                            foreach ($languages as $index => $language) {
                                                if ($language['is_default'] == 1) {
                                                    $current_language = $language['code'];
                                                }
                                            ?>
                                                <div class="language-option position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                                    id="language-<?= $language['code'] ?>"
                                                    data-language="<?= $language['code'] ?>"
                                                    style="cursor: pointer; padding: 0.5rem 0;">
                                                    <span class="language-text px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                                        style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                                        <?= $language['language'] ?><?= $language['is_default'] ? '(Default)' : '' ?>
                                                    </span>
                                                    <div class="language-underline"
                                                        style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: #0d6efd; transition: width 0.3s ease; border-radius: 1px;"></div>
                                                </div>
                                            <?php } ?>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <?php
                                        foreach ($languages as $index => $language) {
                                        ?>
                                            <div class="form-group" id="translationDiv-<?= $language['code'] ?>" <?= $language['code'] == $current_language ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                                <label for="message<?= $language['code'] ?>" class=""> <?= labels('message', 'Message') . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?> </label>
                                                <textarea id="message<?= $language['code'] ?>" class="form-control h-25 border" name="message[<?= $language['code'] ?>]" style="min-height:60px"></textarea>
                                            </div>
                                        <?php } ?>
                                    </div>
                                    <div class="col-md-6">
                                        <!-- Empty column to maintain layout balance -->
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md d-flex justify-content-end">
                                        <button class="btn btn-primary" type="submit"><?= labels('add_promocodes', 'Add Promo Code') ?></button>
                                    </div>
                                </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
</div>
</section>
</div>
<script>
    // Initialize date pickers with date-only format (no time)
    $(document).ready(function() {
        if ($("#start_date").length && $("#end_date").length) {
            $("#start_date").daterangepicker({
                locale: {
                    format: "YYYY-MM-DD",
                },
                singleDatePicker: true,
                autoUpdateInput: false
            });

            $("#end_date").daterangepicker({
                locale: {
                    format: "YYYY-MM-DD",
                },
                singleDatePicker: true,
                autoUpdateInput: false
            });

            // Update end date minimum when start date changes
            $("#start_date").on('apply.daterangepicker', function(ev, picker) {
                $(this).val(picker.startDate.format('YYYY-MM-DD'));
                $("#end_date").data('daterangepicker').setMinDate(picker.startDate);
            });

            // Format end date when applied (date only, no time)
            $("#end_date").on('apply.daterangepicker', function(ev, picker) {
                $(this).val(picker.startDate.format('YYYY-MM-DD'));
            });
        }
    });
    $(document).ready(function() {
        $('#repeat_usage').siblings('.switchery').addClass('not_allowed-content').removeClass('allowed-content');
        $('#promocode_status').siblings('.switchery').addClass('deactive-content').removeClass('active-content');

        function handleSwitchChange(checkbox) {
            var switchery = checkbox.nextElementSibling;
            if (checkbox.checked) {
                switchery.classList.add('active-content');
                switchery.classList.remove('deactive-content');
            } else {
                switchery.classList.add('deactive-content');
                switchery.classList.remove('active-content');
            }
        }

        function handleRepeatSwitchChange(checkbox) {
            var switchery1 = checkbox.nextElementSibling;
            var repeatUsageWrapper = $('.repeat_usage');
            if (checkbox.checked) {
                switchery1.classList.add('allowed-content');
                switchery1.classList.remove('not_allowed-content');
                // Show repeat usage field whenever toggle is on so partners can set a value
                repeatUsageWrapper.slideDown(150);
            } else {
                switchery1.classList.add('not_allowed-content');
                switchery1.classList.remove('allowed-content');
                // Hide and reset repeat usage field when toggle is off to avoid invalid data
                repeatUsageWrapper.slideUp(150);
                repeatUsageWrapper.find('input').val('');
            }
        }
        var repeat_usage = document.querySelector('#repeat_usage');
        repeat_usage.addEventListener('change', function() {
            handleRepeatSwitchChange(repeat_usage);
        });
        // Ensure correct initial visibility when page loads (e.g., during edit scenarios)
        handleRepeatSwitchChange(repeat_usage);
        var promocode_status = document.querySelector('#promocode_status');
        promocode_status.addEventListener('change', function() {
            handleSwitchChange(promocode_status);
        });
    });
</script>
<script>
    $(function() {
        $('.fa').popover({
            trigger: "hover"
        });
    })
</script>
<script>
    // Language switching functionality for multi-language message fields
    $(document).ready(function() {
        let default_language = '<?= $current_language ?>';

        $(document).on('click', '.language-option', function() {
            const language = $(this).data('language');

            // Update language tab styling
            $('.language-underline').css('width', '0%');
            $('#language-' + language).find('.language-underline').css('width', '100%');

            $('.language-text').removeClass('text-primary fw-medium');
            $('.language-text').addClass('text-muted');
            $('#language-' + language).find('.language-text').removeClass('text-muted');
            $('#language-' + language).find('.language-text').addClass('text-primary');

            // Show/hide appropriate message textarea
            if (language != default_language) {
                $('#translationDiv-' + language).show();
                $('#translationDiv-' + default_language).hide();
            }

            default_language = language;
        });
    });
</script>

<script>
    $(document).ready(function() {

        function toggleMaxDiscount() {
            let type = $('#discount_type').val();

            if (type === 'amount') {

                // Hide field
                $('#max_discount_amount').closest('.form-group').hide();

                // Disable input
                $('#max_discount_amount').prop('disabled', true);

                // Remove required styling if any
                $('#max_discount_amount').removeClass('required');

                // Clear value (so no accidental old value gets saved)
                $('#max_discount_amount').val('');

            } else if (type === 'percentage') {

                // Show field
                $('#max_discount_amount').closest('.form-group').show();

                // Enable input
                $('#max_discount_amount').prop('disabled', false);

                // Add required class back if needed
                $('#max_discount_amount').addClass('required');
            }
        }

        // Trigger on load (for edit mode)
        toggleMaxDiscount();

        // Trigger on change
        $('#discount_type').on('change', toggleMaxDiscount);
    });
</script>