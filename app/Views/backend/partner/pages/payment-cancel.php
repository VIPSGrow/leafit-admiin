<section class="main-content">
    <div class="row">
        <div class="col-md-12 col-12 mt-4 pt-2">
            <div class="tab-content" id="pills-tabContent">
                <div class="tab-pane fade bg-white show active shadow rounded p-4 text-center" id="dash" role="tabpanel" aria-labelledby="dashboard">
                    <i class="fas fa-exclamation-triangle fa-4x text-warning" style="font-size:96px"></i>
                    <h4 class="h4 text-danger"><?= labels('payment_cancelled_failed', 'Payment Cancelled / Failed') ?></h4>
                    <p><?= labels('it_seems_like_payment_process_is_failed_or_cancelled', 'It seems like payment process is failed or cancelled. Please Try again.') ?></p>
                    <a class="btn btn-primary" href="<?= base_url('partner/subscription') ?>"><?= labels('try_again', 'Try Again') ?></a>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
    // Track subscription cancellation event
    $(document).ready(function() {
        <?php if (isset($clarity_event_data) && $clarity_event_data['clarity_event'] === 'subscription_cancelled'): ?>
            if (typeof trackSubscriptionCancelled === 'function') {
                trackSubscriptionCancelled('<?= $clarity_event_data['subscription_id'] ?>');
            }
        <?php endif; ?>
    });
</script>