<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('view_service', 'View Service') ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active">
                    <a href="<?= base_url('partner/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i>
                        <?= labels('Dashboard', 'Dashboard') ?></a>
                </div>
                <div class="breadcrumb-item active">
                    <a href="<?= base_url('partner/services') ?>"><?= labels('services', 'Services') ?></a>
                </div>
                <div class="breadcrumb-item"><?= labels('view_service', 'View Service') ?></div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-4">
                        <img class="img-fluid" src="<?= esc($service['image'] ?? '') ?>" alt="<?= esc($service['title'] ?? '') ?>">
                    </div>
                    <div class="col-md-8">
                        <h2><?= esc($service['title'] ?? '') ?></h2>
                        <p><strong><?= labels('category', 'Category') ?>:</strong> <?= esc($service['category_name'] ?? '') ?></p>
                        <p><strong><?= labels('price', 'Price') ?>:</strong> <?= esc($service['price'] ?? '') ?></p>
                        <p><strong><?= labels('discounted_price', 'Discounted Price') ?>:</strong> <?= esc($service['discounted_price'] ?? '') ?></p>
                        <p><strong><?= labels('duration', 'Duration') ?>:</strong> <?= esc($service['duration'] ?? '') ?> min</p>
                        <p><strong><?= labels('status', 'Status') ?>:</strong> <?= ((int) ($service['status'] ?? 0) === 1) ? labels('active', 'Active') : labels('deactive', 'Deactive') ?></p>
                    </div>
                </div>
                <hr>
                <h5><?= labels('description', 'Description') ?></h5>
                <p><?= nl2br(esc($service['description'] ?? '')) ?></p>
                <?php if (!empty($service['long_description'])): ?>
                    <h5><?= labels('long_description', 'Long Description') ?></h5>
                    <p><?= nl2br(esc($service['long_description'])) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>