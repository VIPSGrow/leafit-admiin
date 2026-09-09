<div class="main-content">
    <section class="section">

        <div class="row mt-5">


            <div class="col-md-12">
                <div class="check-container mx-auto">
                    <div class="check-background">
                        <svg viewBox="0 0 65 51" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M7 25L27.3077 44L58.5 7" stroke="white" stroke-width="13" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </div>
                    <div class="check-shadow"></div>
                </div>
            </div>
        </div>

        <div class="row mt-5">
            <div class="col-md-12 d-flex justify-content-center">

                <h5>
                    <?= str_replace(
                        '{company}',
                        $company,
                        labels('subscription_success_message', 'Congratulations on your subscription! Now is the time to shine on {company} and seize new business opportunities. Welcome aboard and best of luck!')
                    ) ?>
                </h5>

            </div>
        </div>

    </section>
</div>

<script>
    // Track subscription purchase event
    $(document).ready(function() {
        <?php if (isset($clarity_event_data) && $clarity_event_data['clarity_event'] === 'subscription_purchase'): ?>
            if (typeof trackSubscriptionPurchase === 'function') {
                trackSubscriptionPurchase(
                    '<?= $clarity_event_data['subscription_id'] ?>',
                    '<?= htmlspecialchars($clarity_event_data['subscription_name'], ENT_QUOTES, 'UTF-8') ?>',
                    '<?= $clarity_event_data['price'] ?>',
                    '<?= htmlspecialchars($clarity_event_data['payment_method'], ENT_QUOTES, 'UTF-8') ?>'
                );
            }
        <?php endif; ?>
    });
</script>