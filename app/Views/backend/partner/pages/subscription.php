<?php
$check_payment_gateway = get_settings('payment_gateways_settings', true);
$cod_setting =  $check_payment_gateway['cod_setting'];
$payment_gateway_setting =  $check_payment_gateway['payment_gateway_setting'];
?>
<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('subscription', " Subscription") ?><span class="breadcrumb-item p-3 pt-2 text-primary"></span></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('partner/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item"></i> <?= labels('subscription', 'Subscription') ?></div>
            </div>
        </div>
        <?= helper('form'); ?>
        <div class="section-body">
            <?php if (session()->has('error')) : ?>
                <script>
                    $(document).ready(function() {
                        iziToast.error({
                            title: "",
                            message: "<?= session('error') ?>",
                            position: "topRight",
                        });
                    });
                </script>
            <?php endif; ?>
            <?php if (session()->has('success')) : ?>
                <script>
                    $(document).ready(function() {
                        iziToast.success({
                            title: "",
                            message: "<?= session('success') ?>",
                            position: "topRight",
                        });
                    });
                </script>
            <?php endif; ?>
            <?php
            if (!empty($active_subscription_details) && isset($active_subscription_details[0])) { ?>
                <div class="tickets-container">
                    <div class="col-md-12 m-0 p-0">
                        <div class="item">
                            <div class="item-right">
                                <button class="buy-button my-2"> <?= !empty($active_subscription_details[0]['translations']['name']) ? $active_subscription_details[0]['translations']['name'] : $active_subscription_details[0]['name'] ?></button>
                                <div class="buy">
                                    <span class="up-border"></span>
                                    <span class="down-border"></span>
                                </div>
                                <?php
                                $price = calculate_partner_subscription_price($active_subscription_details[0]['partner_id'], $active_subscription_details[0]['subscription_id'], $active_subscription_details[0]['id']);
                                ?>
                                <h4 class="active_subscription_plan_price"><?= $currency ?> <?= $price[0]['price_with_tax'] ?></h4>
                                <?php
                                if ($active_subscription_details[0]['expiry_date'] != $active_subscription_details[0]['purchase_date']) { ?>
                                    <div class="active_subscription_plan_expiry_date mt-5">
                                        <div class="form-group m-0 p-0">
                                            <?php
                                            // print_R($active_subscription_details);
                                            echo labels('yourSubscriptionWillBeValidTill', "Your subscription will be valid till ") . " " . $active_subscription_details[0]['expiry_date'];
                                            ?>
                                        </div>
                                    </div>
                                <?php  } else { ?>
                                    <div class="active_subscription_plan_expiry_date mt-5">
                                        <div class="form-group m-0 p-0">
                                            <?php echo labels('enjoySubscriptionForUnlimitedDays', "Lifetime Subscription – seize success without limits!") ?>;
                                        </div>
                                    </div>
                                <?php      } ?>
                            </div>
                            <div class="item-left w-100">
                                <div class="row">
                                    <div class="col-md-10">
                                        <div class="active_plan_title "><?= labels('features', 'Features') ?></div>
                                    </div>
                                    <div class="col-md-2 text-right" style="white-space:nowrap;">
                                        <div class="tag border-0 rounded-md bg-emerald-grey ">
                                            <?php
                                            if ($active_subscription_details[0]['is_payment'] == 1) {
                                                $status = labels('success', "Success");
                                            } elseif ($active_subscription_details[0]['is_payment'] == 0) {
                                                $status = labels('pending', "Pending");
                                            } else {
                                                $status = labels('failed', "Failed");
                                            }
                                            ?>
                                            <?= $status ?>
                                        </div>
                                    </div>
                                </div>
                                <ul class="active_subscription_feature_list mb-3 mt-3" style="margin:28px">
                                    <li>
                                        <span class="icon">
                                            <svg height="24" width="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M0 0h24v24H0z" fill="none"></path>
                                                <path fill="currentColor" d="M10 15.172l9.192-9.193 1.415 1.414L10 18l-6.364-6.364 1.414-1.414z"></path>
                                            </svg>
                                        </span>
                                        <span>
                                            <?php
                                            if (isset($active_subscription_details[0]['max_order_limit'])) {
                                                if ($active_subscription_details[0]['order_type'] == "unlimited") {
                                                    echo labels('enjoyUnlimitedOrders', "Unlimited Orders: No limits, just success.");
                                                } else {
                                                    echo labels('enjoyGenerousOrderLimitOf', "Enjoy a generous order limit of") . " " . $active_subscription_details[0]['max_order_limit'] . " " . labels('ordersDuringYourSubscriptionPeriod', "orders during your subscription period");
                                                }
                                            }
                                            ?>
                                        </span>
                                    </li>
                                    <li>
                                        <span class="icon">
                                            <svg height="24" width="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M0 0h24v24H0z" fill="none"></path>
                                                <path fill="currentColor" d="M10 15.172l9.192-9.193 1.415 1.414L10 18l-6.364-6.364 1.414-1.414z"></path>
                                            </svg>
                                        </span>
                                        <?php
                                        if ($active_subscription_details[0]['duration'] == "unlimited") {
                                            echo labels('enjoySubscriptionForUnlimitedDays', "Lifetime Subscription – seize success without limits!");
                                        } else {
                                            echo labels('yourSubscriptionWillBeValidTill', "Your subscription will be valid for") . " " . $active_subscription_details[0]['duration'] . " " . labels('days', "Days");
                                        }
                                        ?>
                                    </li>
                                    <li>
                                        <span class="icon">
                                            <svg height="24" width="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M0 0h24v24H0z" fill="none"></path>
                                                <path fill="currentColor" d="M10 15.172l9.192-9.193 1.415 1.414L10 18l-6.364-6.364 1.414-1.414z"></path>
                                            </svg>
                                        </span>
                                        <?php
                                        if ($active_subscription_details[0]['is_commision'] == "yes") {
                                            // Show commission percentage when set, then the label text
                                            $pct = isset($active_subscription_details[0]['commission_percentage']) && $active_subscription_details[0]['commission_percentage'] !== '' && $active_subscription_details[0]['commission_percentage'] !== null
                                                ? $active_subscription_details[0]['commission_percentage'] . '% – '
                                                : '';
                                            echo $pct . labels('commissionWillBeAppliedToYourEarnings', "Commission will be applied to your earnings");
                                        } else {
                                            echo labels('noNeedToPayExtraCommission', "Your income, your rules – no hidden commission charges on your profits");
                                        }
                                        ?>
                                    </li>
                                    <li>
                                        <span class="icon">
                                            <svg height="24" width="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <path d="M0 0h24v24H0z" fill="none"></path>
                                                <path fill="currentColor" d="M10 15.172l9.192-9.193 1.415 1.414L10 18l-6.364-6.364 1.414-1.414z"></path>
                                            </svg>
                                        </span>
                                        <?php
                                        if ($active_subscription_details[0]['is_commision'] == "yes"  &&  ($cod_setting == 1) && ($payment_gateway_setting == 1)) {
                                            // Amount must be outside labels() so it always shows (translations replace the full default)
                                            $threshold = $active_subscription_details[0]['commission_threshold'] ?? '';
                                            echo labels('commissionThreshold', "Pay on Delivery threshold: The Pay on Service option will be closed, once the cash of the ") . ' ' . $currency . $threshold . ' ' . labels('AmountIsReached', " amount is reached");
                                        } else {
                                            echo labels('noThresholdOnPayOnDeliveryAmount', "There is no threshold on the Pay on Service amount.");
                                        }
                                        ?>
                                    </li>
                                    <?php if ($price[0]['tax_percentage'] != "0") { ?>
                                        <li>
                                            <span class="icon">
                                                <svg height="24" width="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="M0 0h24v24H0z" fill="none"></path>
                                                    <path fill="currentColor" d="M10 15.172l9.192-9.193 1.415 1.414L10 18l-6.364-6.364 1.414-1.414z"></path>
                                                </svg>
                                            </span>
                                            <span>
                                                <?php
                                                // Percentage must be outside labels() so it always shows (translations replace the full default)
                                                echo $price[0]['tax_percentage'] . '% ' . labels('tax_included', "tax included");
                                                ?></span>
                                        </li>
                                    <?php } ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            <?php } else { ?>
                <div class="row d-flex">
                    <?php foreach ($subscription_details as $row) { ?>
                        <div class="col-md-4 mb-md-3">
                            <div class="plan d-flex flex-column h-100">
                                <div class="inner  h-100">
                                    <div class="plan_title">
                                        <b><?= !empty($row['translations']['name']) ? $row['translations']['name'] : $row['name'] ?></b>
                                    </div>
                                    <?php
                                    $price = calculate_subscription_price($row['id']);;
                                    ?>
                                    <h5>
                                        <p class="plan_price"><b><?= $currency ?><?= $price[0]['price_with_tax'] ?></b></p>
                                    </h5>
                                    <ul class="features mb-3">
                                        <li>
                                            <span class="icon">
                                                <svg height="24" width="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="M0 0h24v24H0z" fill="none"></path>
                                                    <path fill="currentColor" d="M10 15.172l9.192-9.193 1.415 1.414L10 18l-6.364-6.364 1.414-1.414z"></path>
                                                </svg>
                                            </span>
                                            <span>
                                                <strong>
                                                    <?php
                                                    if ($row['order_type'] == "unlimited") {
                                                        echo labels('enjoyUnlimitedOrders', "Unlimited Orders: No limits, just success.");
                                                    } else {
                                                        echo labels('enjoyGenerousOrderLimitOf', "Enjoy a generous order limit of") . " " . $row['max_order_limit'] . " " . labels('ordersDuringYourSubscriptionPeriod', "orders during your subscription period");
                                                    }
                                                    ?>
                                                </strong>
                                            </span>
                                        </li>
                                        <li>
                                            <span class="icon">
                                                <svg height="24" width="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="M0 0h24v24H0z" fill="none"></path>
                                                    <path fill="currentColor" d="M10 15.172l9.192-9.193 1.415 1.414L10 18l-6.364-6.364 1.414-1.414z"></path>
                                                </svg>
                                            </span>
                                            <span>
                                                <strong>
                                                    <?php
                                                    if ($row['duration'] == "unlimited") {
                                                        echo labels('enjoySubscriptionForUnlimitedDays', "Lifetime Subscription – seize success without limits!");
                                                    } else {
                                                        echo labels('yourSubscriptionWillBeValidTill', "Your subscription will be valid for") . " " . $row['duration'] . " " . labels('days', "Days");
                                                    }
                                                    ?>
                                                </strong>
                                            </span>
                                        </li>
                                        <li>
                                            <span class="icon">
                                                <svg height="24" width="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                    <path fill="currentColor" d="M10 15.172l9.192-9.193 1.415 1.414L10 18l-6.364-6.364 1.414-1.414z"></path>
                                                </svg>
                                            </span>
                                            <span>
                                                <strong>
                                                    <?php
                                                    if ($row['is_commision'] == "yes") {
                                                        // Show commission percentage when set, then the label text
                                                        $pct = isset($row['commission_percentage']) && $row['commission_percentage'] !== '' && $row['commission_percentage'] !== null
                                                            ? $row['commission_percentage'] . '% – '
                                                            : '';
                                                        echo $pct . labels('commissionWillBeAppliedToYourEarnings', "Commission will be applied to your earnings");
                                                    } else {
                                                        echo labels('noNeedToPayExtraCommission', "Your income, your rules – no hidden commission charges on your profits");
                                                    }
                                                    ?>
                                                </strong>
                                            </span>
                                        </li>
                                        <li>
                                            <span class="icon">
                                                <svg height="24" width="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="M0 0h24v24H0z" fill="none"></path>
                                                    <path fill="currentColor" d="M10 15.172l9.192-9.193 1.415 1.414L10 18l-6.364-6.364 1.414-1.414z"></path>
                                                </svg>
                                            </span>
                                            <span>
                                                <strong>
                                                    <?php
                                                    if ($row['is_commision'] == "yes" && ($cod_setting == 1) && ($payment_gateway_setting == 1)) {
                                                        // Amount must be outside labels() so it always shows (translations replace the full default)
                                                        $threshold = $row['commission_threshold'] ?? '';
                                                        echo labels('commissionThreshold', "Pay on Delivery threshold: The Pay on Service option will be closed, once the cash of the ") . ' ' . $currency . $threshold . ' ' . labels('AmountIsReached', " amount is reached");
                                                    } else {
                                                        echo labels('noThresholdOnPayOnDeliveryAmount', "There is no threshold on the Pay on Service amount.");
                                                    }
                                                    ?>
                                                </strong>
                                            </span>
                                        </li>
                                        <?php if ($price[0]['tax_percentage'] != "0") { ?>
                                            <li>
                                                <span class="icon">
                                                    <svg height="24" width="24" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                        <path d="M0 0h24v24H0z" fill="none"></path>
                                                        <path fill="currentColor" d="M10 15.172l9.192-9.193 1.415 1.414L10 18l-6.364-6.364 1.414-1.414z"></path>
                                                    </svg>
                                                </span>
                                                <span>
                                                    <strong>
                                                        <?php
                                                        // Percentage must be outside labels() so it always shows (translations replace the full default)
                                                        echo $price[0]['tax_percentage'] . '% ' . labels('tax_included', "tax included");
                                                        ?>
                                                    </strong>
                                                </span>
                                            </li>
                                        <?php     } ?>
                                        <a href="javascript:void(0);" class="toggle-description">
                                            <span class="icon" style="font-size: 11px;">
                                                <i class="fa-solid fa-eye fa-sm"></i>
                                                <i class="fa-solid fa-eye-slash fa-sm"></i>
                                            </span>
                                            <span class="text"><?= labels('view_description', 'View Description') ?></span>
                                        </a>
                                        <div class="description">
                                            <?= !empty($row['translated_description']) ? $row['translated_description'] : $row['description'] ?>
                                        </div>
                                    </ul>
                                </div>
                                <form class="needs-validation make_payment_form" id="make_payment_for_subscription1" method="POST" action="<?= base_url('partner/make_payment_for_subscription') ?>">
                                    <input type="hidden" name="stripe_key_id" id="stripe_key_id" value="<?= $stripe_credentials['publishable_key'] ?>" />
                                    <input id="subscription_id" name="subscription_id" class="form-control" value="<?= $row['id'] ?>" type="hidden" name="">
                                    <input type="hidden" name="stripe_client_secret" id="stripe_client_secret" value="" />
                                    <input type="hidden" name="stripe_payment_id" id="stripe_payment_id" value="" />
                                    <input type="hidden" id="payment_gateway_count" value="<?= count($payment_gateway) ?>" />
                                    <input type="hidden" id="payment_gateway_amount" value="<?= $price[0]['price_with_tax'] ?>" />
                                    <input type="hidden" id="payment_gateway_setting" value="<?= $payment_gateway_setting ?>" />
                                    <?php if (count($payment_gateway) == 1) : ?>
                                        <input id="payment_method" name="payment_method" class="form-control" value="<?= $payment_gateway[0] ?>" type="hidden">
                                    <?php else : ?>
                                        <input id="payment_method" name="payment_method" class="form-control" value="" type="hidden">
                                    <?php endif; ?>

                                    <?php
                                    // Only show payment gateway selection for paid plans when multiple gateways are available
                                    if (count($payment_gateway) > 1 && ($price[0]['price_with_tax'] != 0)) : ?>
                                        <div class="card card-primary paymentGatewaySelectionCard" style="display: none;background-color:#f4f6f9;">

                                            <div class="card-header">
                                                <h4><?= labels('select_payment_gateway', 'Select Payment Gateway') ?></h4>

                                            </div>
                                            <div class="card-body">
                                                <ul>
                                                    <?php foreach ($payment_gateway as $gateway) : ?>
                                                        <li style="list-style: none; cursor: pointer;" class="mb-3" id="<?= $gateway ?>">
                                                            <div class="row text-dark">
                                                                <div class="col-md-1 w-auto m-0">
                                                                    <?php $icon_path = base_url("public/uploads/site/" . $gateway . "_icon.svg"); ?>

                                                                    <img src="<?= $icon_path; ?>" alt="<?= $gateway ?> icon" />
                                                                </div>
                                                                <div class="col-md-4 w-auto m-0">
                                                                    <div class="form-check">
                                                                        <label class="form-check-label mt-2">
                                                                            <?= ucfirst($gateway) ?>
                                                                        </label>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6 w-auto d-flex justify-content-end">
                                                                    <input class="form-check-input payment_gateway_radio" type="radio" name="payment_gateway_<?= $row['id'] ?>" id="payment_gateway_<?= $gateway ?>" value="<?= $gateway ?>" required>
                                                                </div>
                                                            </div>
                                                        </li>

                                                    <?php endforeach; ?>
                                                </ul>

                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="card-footer mt-auto">
                                        <?php
                                        // Check if all payment gateways are disabled
                                        $all_payment_gateways_disabled = (count($payment_gateway) == 0);

                                        // Determine button text and behavior based on plan type
                                        // Providers can always purchase subscriptions, even when online payment is disabled for customers
                                        if ($price[0]['price_with_tax'] == 0) {
                                            // Free plan - always allow activation
                                            $button_text = labels('activate_free_plan', 'Activate Free Plan');
                                            $button_class = "btn btn-block text-white bg-primary";
                                            $button_disabled = "";
                                        } else {
                                            // Paid plan - check if payment gateways are available
                                            if ($all_payment_gateways_disabled) {
                                                // All payment gateways are disabled - show message instead of button
                                                $button_text = "";
                                                $button_class = "";
                                                $button_disabled = "";
                                            } else {
                                                // Payment gateways are available - show buy button
                                                $button_text = labels('buy', 'Buy');
                                                $button_class = "btn btn-block text-white bg-primary";
                                                $button_disabled = "";
                                            }
                                        }
                                        ?>
                                        <?php if (!empty($button_text)) : ?>
                                            <button type="submit" class="<?= $button_class ?>" <?= $button_disabled ?>>
                                                <?= $button_text ?>
                                            </button>
                                        <?php else : ?>
                                            <div class="alert alert-primary text-white text-center mb-0" role="alert">
                                                <i class="fas fa-exclamation-triangle"></i>
                                                <?= str_replace(
                                                    '{link}',
                                                    '<a href="' . base_url('partner/admin-support') . '" class="alert-link font-weight-bold">' . labels('contact_admin', 'contact admin') . '</a>',
                                                    labels('online_payment_not_supported', 'Online payment is not supported. Please {link} to purchase subscription')
                                                ) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php            } ?>
                <?php } ?>
                </div>
        </div>
    </section>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const toggleDescriptionLinks = document.querySelectorAll('.toggle-description');
        toggleDescriptionLinks.forEach(function(link) {
            link.addEventListener('click', function() {
                const description = link.nextElementSibling;
                description.classList.toggle('show');
                const icon = link.querySelector('.icon');
                const eyeIcon = icon.querySelector('.fa-eye');
                const eyeSlashIcon = icon.querySelector('.fa-eye-slash');
                if (description.classList.contains('show')) {
                    link.querySelector('.text').textContent = '<?= labels('hide_description', 'Hide Description') ?>';
                    eyeIcon.style.display = 'none';
                    eyeSlashIcon.style.display = 'inline-block';
                } else {
                    link.querySelector('.text').textContent = '<?= labels('view_description', 'View Description') ?>';
                    eyeIcon.style.display = 'inline-block';
                    eyeSlashIcon.style.display = 'none';
                }
            });
        });
    });
</script>
<style>
    .description {
        display: none;
    }

    .description.show {
        display: block;
    }

    .fa-eye-slash {
        display: none;
    }

    /* Payment gateway icon styling - ensure all icons are the same size */
    .paymentGatewaySelectionCard img {
        width: 32px;
        height: 32px;
        object-fit: contain;
        display: block;
        margin: 0 auto;
    }

    /* Enhanced styling for payment gateway selection items */
    .paymentGatewaySelectionCard li {
        transition: all 0.3s ease;
        border-radius: 8px;
        padding: 8px;
        margin-bottom: 8px;
        border: 2px solid transparent;
    }

    .paymentGatewaySelectionCard li:hover {
        background-color: rgba(0, 0, 0, 0.05);
        transform: translateY(-1px);
    }

    .paymentGatewaySelectionCard li.selected_payment_method {
        border-color: #007bff;
        background-color: rgba(0, 123, 255, 0.1);
        box-shadow: 0 2px 4px rgba(0, 123, 255, 0.2);
    }

    /* Ensure consistent spacing and alignment for payment gateway items */
    .paymentGatewaySelectionCard .row {
        align-items: center;
        margin: 0;
    }

    .paymentGatewaySelectionCard .col-md-1 {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* Responsive design for payment gateway icons */
    @media (max-width: 768px) {
        .paymentGatewaySelectionCard img {
            width: 28px !important;
            height: 28px !important;
        }

        .paymentGatewaySelectionCard .col-md-1 {
            min-width: 40px;
        }
    }
</style>

<script>
    // Payment Gateway Selection Script
    // This script handles payment gateway selection for subscription cards
    // Each subscription card now has independent payment gateway selection
    // When a user selects a payment gateway for one card, it doesn't affect other cards
    // Only one payment gateway selection dropdown is visible at a time for better UX
    // Flow: Click Buy → Show payment gateway dropdown → Select gateway → Auto-submit form
    $(document).ready(function() {
        // Initially hide all payment gateway selection cards
        // Each subscription card now has its own independent payment gateway selection
        $('.make_payment_form').each(function() {
            $(this).find('.paymentGatewaySelectionCard').hide();
        });

        // Handle card click and selection - now specific to each subscription card
        $('.paymentGatewaySelectionCard li').on('click', function() {
            const selectedGateway = $(this).attr('id');
            const currentCard = $(this).closest('.make_payment_form');
            selectGateway(selectedGateway, currentCard);
        });

        const gateways = ["paypal", "paystack", "stripe", "razorpay", "flutterwave", "xendit"];

        function selectGateway(selectedGateway, currentCard) {
            // This function handles payment gateway selection for a specific subscription card
            // It ensures that only one gateway can be selected per card and doesn't affect other cards

            // Clear all selections in the current card only
            currentCard.find('.paymentGatewaySelectionCard li').removeClass('selected_payment_method');
            currentCard.find('.payment_gateway_radio').prop('checked', false);

            // Select the clicked gateway in the current card only
            if (selectedGateway) {
                currentCard.find(`#${selectedGateway}`).addClass('selected_payment_method');
                currentCard.find(`#payment_gateway_${selectedGateway}`).prop('checked', true);
            }

            // Update hidden input with selected gateway value for current card only
            currentCard.find('#payment_method').val(selectedGateway);

            // Hide payment gateway selection cards for all other subscription cards
            // This ensures only one payment gateway selection is visible at a time
            $('.make_payment_form').not(currentCard).each(function() {
                $(this).find('.paymentGatewaySelectionCard').hide();
            });

            // Auto-submit the form when a payment gateway is selected
            if (selectedGateway) {
                // Hide the payment gateway selection card
                currentCard.find('.paymentGatewaySelectionCard').hide();
                // Submit the form
                currentCard.submit();
            }
        }

        // Handle radio button change - now specific to each subscription card
        $('.make_payment_form .payment_gateway_radio').change(function() {
            var form = $(this).closest('form');
            var selectedGateway = form.find('input[name^=payment_gateway_]:checked').val();
            form.find('#payment_method').val(selectedGateway);

            // Update visual selection for current card only
            form.find('.paymentGatewaySelectionCard li').removeClass('selected_payment_method');
            if (selectedGateway) {
                form.find(`#${selectedGateway}`).addClass('selected_payment_method');
            }

            // Hide payment gateway selection cards for all other subscription cards
            // This ensures only one payment gateway selection is visible at a time
            $('.make_payment_form').not(form).each(function() {
                $(this).find('.paymentGatewaySelectionCard').hide();
            });

            // Auto-submit the form when a payment gateway is selected
            if (selectedGateway) {
                // Hide the payment gateway selection card
                form.find('.paymentGatewaySelectionCard').hide();
                // Submit the form
                form.submit();
            }
        });

        // Handle form submission - now specific to each subscription card
        $('.make_payment_form').submit(function() {
            var form = $(this);
            var selectedGateway = form.find('input[name^=payment_gateway_]:checked').val();
            var paymentGatewayCount = form.find('#payment_gateway_count').val();
            var paymentGatewayAmount = form.find('#payment_gateway_amount').val();
            var paymentGatewaySetting = form.find('#payment_gateway_setting').val();
            var subscriptionId = form.find('#subscription_id').val();

            // For free plans, always allow submission
            if (paymentGatewayAmount == 0) {
                return true;
            }

            // For paid plans, providers can always proceed with payment
            // The online payment disabled setting only applies to customers, not providers

            // Check if all payment gateways are disabled
            // If disabled, prevent form submission - user can click the link in the warning message
            if (paymentGatewayCount == 0 && paymentGatewayAmount != 0) {
                return false;
            }

            // For paid plans, check payment gateway selection for this specific card
            // Only require selection if multiple gateways are available
            if (paymentGatewayCount > 1 && !selectedGateway && paymentGatewayAmount != 0) {
                alert('Please select a payment gateway for this subscription plan.');
                return false;
            }

            // If only one gateway exists, ensure it's set in the payment_method field
            if (paymentGatewayCount == 1 && !selectedGateway && paymentGatewayAmount != 0) {
                // Get the single gateway value from the hidden input
                var singleGateway = form.find('#payment_method').val();
                if (!singleGateway) {
                    // Fallback: try to get it from the payment gateway radio if it exists
                    var firstRadio = form.find('.payment_gateway_radio').first();
                    if (firstRadio.length) {
                        form.find('#payment_method').val(firstRadio.val());
                    }
                }
            }

            // Track checkout_completed event when checkout is initiated (for paid plans)
            // Get the payment gateway - either from selection or from single gateway
            var paymentGateway = selectedGateway || form.find('#payment_method').val();
            if (paymentGatewayAmount != 0 && paymentGateway && typeof trackCheckoutCompleted === 'function') {
                // Get subscription name from the form's parent card
                var subscriptionName = form.closest('.plan').find('.plan_title b').text().trim() || '';
                trackCheckoutCompleted(
                    subscriptionId,
                    subscriptionName,
                    paymentGatewayAmount,
                    paymentGateway
                );
            }

            // If we reach here, the form is ready to submit
            return true;
        });

        // Toggle payment gateway selection card visibility - now specific to each subscription card
        function togglePaymentGatewaySelectionCard(form) {
            var paymentGatewayCard = form.find('.paymentGatewaySelectionCard');
            var paymentGatewayAmount = form.find('#payment_gateway_amount').val();
            var paymentGatewaySetting = form.find('#payment_gateway_setting').val();

            // Only show payment gateway selection for paid plans
            // Providers can always use payment gateways, even when online payment is disabled for customers
            if (form.find('.payment_gateway_radio').length > 1 && paymentGatewayAmount != 0) {
                paymentGatewayCard.show();
            } else {
                paymentGatewayCard.hide();
            }
        }

        $('.make_payment_form button[type=submit]').click(function(e) {
            e.preventDefault(); // Prevent default form submission
            var form = $(this).closest('form');
            var paymentGatewayAmount = form.find('#payment_gateway_amount').val();
            var paymentGatewaySetting = form.find('#payment_gateway_setting').val();
            var paymentGatewayCount = parseInt(form.find('#payment_gateway_count').val());

            // For free plans, allow immediate submission
            if (paymentGatewayAmount == 0) {
                form.submit();
                return;
            }

            // Check if all payment gateways are disabled
            // If disabled, prevent action - user can click the link in the warning message
            if (paymentGatewayCount == 0) {
                return;
            }

            // For paid plans, providers can always proceed with payment
            // The online payment disabled setting only applies to customers, not providers

            // If only one payment gateway is enabled, proceed directly with payment
            // No need to show selection card for single gateway
            if (paymentGatewayCount == 1) {
                // Get the single payment gateway value from the hidden input
                var singleGateway = form.find('#payment_method').val();
                if (singleGateway) {
                    // Payment method is already set, submit directly
                    form.submit();
                    return;
                }
            }

            // If multiple payment gateways exist, show selection card
            // Hide payment gateway selection cards for all other subscription cards first
            // This ensures only one payment gateway selection is visible at a time
            $('.make_payment_form').not(form).each(function() {
                $(this).find('.paymentGatewaySelectionCard').hide();
            });

            // Then toggle the current card's payment gateway selection
            togglePaymentGatewaySelectionCard(form);
        });

        // Close payment gateway selection when clicking outside - now specific to each card
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.paymentGatewaySelectionCard, .make_payment_form button[type=submit]').length) {
                // Hide only the payment gateway cards that are not part of the clicked form
                $('.paymentGatewaySelectionCard').each(function() {
                    var cardForm = $(this).closest('.make_payment_form');
                    if (!cardForm.is(e.target) && !cardForm.has(e.target).length) {
                        $(this).hide();
                    }
                });
            }
        });

        // Prevent event bubbling when clicking inside payment gateway selection
        $('.paymentGatewaySelectionCard').on('click', function(e) {
            e.stopPropagation();
        });


    });
</script>