<!-- Main Content -->
<div class="main-content">
    <section class="section" id="pill-general_settings" role="tabpanel">
        <div class="section-header mt-2">
            <h1><?= labels('gallery', "Gallery") ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/partner/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item"><?= labels('gallery', "Gallery") ?></a></div>
            </div>
        </div>
        <div class="container-fluid card p-3">
            <div class="row">
                <?php if (empty($folders)) : ?>
                    <div class="col-md-12 d-flex justify-content-center">
                        <div class="empty-state" data-height="400" style="height: 400px;">
                            <div class="empty-state-icon bg-primary">
                                <i class="fas fa-question text-white "></i>
                            </div>
                            <h2><?= labels('we_could_not_find_any_files', 'We could not find any Files') ?></h2>
                        </div>
                    </div>
                <?php else : ?>
                    <?php foreach ($folders as $folder) : ?>

                        <div class="col-xxl-3 col-xl-2 col-lg-6 col-md-12 mb-3 text-center folder-item">
                            <a class="text-dark " href="<?= base_url("partner/gallery/get-gallery-files/" . $folder['path']) ?>">
                                <i class="fa-solid fa-folder text-primary" style="font-size: 50px;;"></i>
                                <div class="text-capitalize"><?= labels(normalize_folder_name($folder['name']), str_replace('_', ' ', $folder['name']))  ?> (<?= esc($folder['file_count']) ?> files)</div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>