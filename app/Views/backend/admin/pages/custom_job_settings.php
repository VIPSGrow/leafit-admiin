<div class="main-content">
    <section class="section" id="pill-custom_job_settings" role="tabpanel">
        <div class="section-header mt-2">
            <h1><?= labels('custom_job_settings', 'Custom Job Settings') ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item"><a href="<?= base_url('/admin/settings/system-settings') ?>"><?= labels('system_settings', "System Settings") ?></a></div>
                <div class="breadcrumb-item"><?= labels('custom_job_settings', "custom Job Settings") ?></div>
            </div>
        </div>

        <?= form_open_multipart(base_url('admin/settings/custom_job_settings/save')) ?>

        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">
                        <!-- ================= FILE CONFIGURATION ================= -->
                        <div class="mb-4">
                            <div class="d-flex align-items-center mb-2">
                                <i class="fas fa-file-upload text-primary mr-3 fa-lg"></i>
                                <h3 class="mb-0 font-weight-bold"><?= labels('file_configuration', 'File Configuration') ?></h3>
                            </div>
                            <p class="text-muted mb-4">
                                <?= labels('custom_job_file_config_description', 'Configure attachment limits for custom job requests.') ?>
                            </p>

                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <div class="card border-2 h-100 setting-card" style="min-height: 140px;">
                                        <div class="card-body d-flex flex-column">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-layer-group text-primary mr-2"></i>
                                                    <strong><?= labels('max_files_allowed', 'Max files allowed') ?></strong>
                                                </div>
                                            </div>
                                            <div class="mb-2">
                                                <input type="number" class="form-control" id="max_files_allowed" name="max_files_allowed"
                                                    min="1" value="<?= isset($custom_job_settings['max_files_allowed']) ? esc($custom_job_settings['max_files_allowed']) : '5' ?>">
                                            </div>
                                            <small class="text-muted">
                                                <?= labels('max_files_allowed_description', 'Maximum number of files that can be attached to a single quote request.') ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-3 mb-3">
                                    <div class="card border-2 h-100 setting-card" id="allow-image-uploads-card" style="min-height: 140px;">
                                        <div class="card-body d-flex flex-column">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-image text-primary mr-2"></i>
                                                    <strong><?= labels('allow_image_uploads', 'Allow image uploads') ?></strong>
                                                </div>
                                                <div class="custom-control custom-switch">
                                                    <input type="checkbox" class="custom-control-input"
                                                        id="allow_image_uploads"
                                                        name="allow_image_uploads"
                                                        <?= (!empty($custom_job_settings['allow_image_uploads'])) ? 'checked' : '' ?>>
                                                    <label class="custom-control-label" for="allow_image_uploads"></label>
                                                </div>
                                            </div>
                                            <small class="text-muted">
                                                <?= labels('allow_image_uploads_description', 'Enable customers to attach image files to their custom job requests.') ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-3 mb-3">
                                    <div class="card border-2 h-100 setting-card" id="allow-video-uploads-card" style="min-height: 140px;">
                                        <div class="card-body d-flex flex-column">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-video text-primary mr-2"></i>
                                                    <strong><?= labels('allow_video_uploads', 'Allow video uploads') ?></strong>
                                                </div>
                                                <div class="custom-control custom-switch">
                                                    <input type="checkbox" class="custom-control-input"
                                                        id="allow_video_uploads"
                                                        name="allow_video_uploads"
                                                        <?= (!empty($custom_job_settings['allow_video_uploads'])) ? 'checked' : '' ?>>
                                                    <label class="custom-control-label" for="allow_video_uploads"></label>
                                                </div>
                                            </div>
                                            <small class="text-muted">
                                                <?= labels('allow_video_uploads_description', 'Enable customers to attach video files to their custom job requests.') ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-3 mb-3">
                                    <div class="card border-2 h-100 setting-card" id="allow-document-uploads-card" style="min-height: 140px;">
                                        <div class="card-body d-flex flex-column">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-file-alt text-primary mr-2"></i>
                                                    <strong><?= labels('allow_document_uploads', 'Allow document uploads') ?></strong>
                                                </div>
                                                <div class="custom-control custom-switch">
                                                    <input type="checkbox" class="custom-control-input"
                                                        id="allow_document_uploads"
                                                        name="allow_document_uploads"
                                                        <?= (!empty($custom_job_settings['allow_document_uploads'])) ? 'checked' : '' ?>>
                                                    <label class="custom-control-label" for="allow_document_uploads"></label>
                                                </div>
                                            </div>
                                            <small class="text-muted">
                                                <?= labels('allow_document_uploads_description', 'Enable customers to attach other document files to their custom job requests.') ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row mt-3">
                                <div class="col-md-3 mb-3" id="max-file-size-images-wrapper">
                                    <div class="card border-2 h-100 setting-card" style="min-height: 140px;">
                                        <div class="card-body d-flex flex-column">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-image text-primary mr-2"></i>
                                                    <strong><?= labels('max_file_size_images', 'Max size for images (MB)') ?></strong>
                                                </div>
                                            </div>
                                            <div class="mb-2">
                                                <input type="number" class="form-control" id="max_file_size_images" name="max_file_size_images"
                                                    min="1" value="<?= isset($custom_job_settings['max_file_size_images']) ? esc($custom_job_settings['max_file_size_images']) : '5' ?>">
                                            </div>
                                            <small class="text-muted">
                                                <?= labels('max_file_size_images_description', 'Maximum size allowed for image files in megabytes (MB).') ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3" id="max-file-size-video-wrapper">
                                    <div class="card border-2 h-100 setting-card" style="min-height: 140px;">
                                        <div class="card-body d-flex flex-column">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-video text-primary mr-2"></i>
                                                    <strong><?= labels('max_file_size_video', 'Max size for video (MB)') ?></strong>
                                                </div>
                                            </div>
                                            <div class="mb-2">
                                                <input type="number" class="form-control" id="max_file_size_video" name="max_file_size_video"
                                                    min="1" value="<?= isset($custom_job_settings['max_file_size_video']) ? esc($custom_job_settings['max_file_size_video']) : '100' ?>">
                                            </div>
                                            <small class="text-muted">
                                                <?= labels('max_file_size_video_description', 'Maximum size allowed for video files in megabytes (MB).') ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3" id="max-file-size-other-wrapper">
                                    <div class="card border-2 h-100 setting-card" style="min-height: 140px;">
                                        <div class="card-body d-flex flex-column">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div class="d-flex align-items-center">
                                                    <i class="fas fa-file-alt text-primary mr-2"></i>
                                                    <strong><?= labels('max_file_size_other', 'Max size for other documents (MB)') ?></strong>
                                                </div>
                                            </div>
                                            <div class="mb-2">
                                                <input type="number" class="form-control" id="max_file_size_other" name="max_file_size_other"
                                                    min="1" value="<?= isset($custom_job_settings['max_file_size_other']) ? esc($custom_job_settings['max_file_size_other']) : '10' ?>">
                                            </div>
                                            <small class="text-muted">
                                                <?= labels('max_file_size_other_description', 'Maximum size allowed for other document types in megabytes (MB).') ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-3">
            <div class="col-12 d-flex justify-content-end">
                <button type="submit" class="btn btn-lg bg-new-primary">
                    <i class="fas fa-save mr-2"></i>
                    <?= labels('save_changes', 'Save Changes') ?>
                </button>
            </div>
        </div>

        <?= form_close() ?>
    </section>
</div>

<style>
    /* Minimal custom styles for active state and hover effects */
    .setting-card {
        transition: all 0.3s ease;
        background-color: #f8f9fa;
    }

    .setting-card:has(.custom-switch) {
        cursor: pointer;
    }

    .setting-card:hover {
        box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.08) !important;
    }

    .setting-card.active {
        border-color: var(--primary, #007bff) !important;
        background-color: #e7f3ff;
    }

    .alert-info-box {
        background: #fff3cd;
        border-left: 4px solid #ffc107;
        padding: 16px;
        border-radius: 8px;
        margin-bottom: 20px;
        display: flex;
        align-items: start;
        gap: 12px;
    }

    .alert-info-box i {
        color: #ffc107;
        font-size: 20px;
        margin-top: 2px;
    }

    .alert-info-box-content {
        flex: 1;
    }

    .alert-info-box-title {
        font-weight: 600;
        color: #856404;
        margin-bottom: 4px;
    }

    .alert-info-box-text {
        font-size: 14px;
        color: #856404;
        margin: 0;
    }

    .custom-switch {
        align-items: unset;
    }
</style>

<script>
    $(document).ready(function() {
        // Highlight input cards on focus
        $('input.form-control').on('focus', function() {
            $(this).closest('.setting-card').addClass('active');
        }).on('blur', function() {
            $(this).closest('.setting-card').removeClass('active');
        });

        function updateCardState(cardId, isActive) {
            if (isActive) {
                $(cardId).addClass('active');
            } else {
                $(cardId).removeClass('active');
            }
        }

        const uploadToggles = [
            { input: '#allow_image_uploads', card: '#allow-image-uploads-card', sizeWrapper: '#max-file-size-images-wrapper' },
            { input: '#allow_video_uploads', card: '#allow-video-uploads-card', sizeWrapper: '#max-file-size-video-wrapper' },
            { input: '#allow_document_uploads', card: '#allow-document-uploads-card', sizeWrapper: '#max-file-size-other-wrapper' }
        ];

        uploadToggles.forEach(function(t) {
            const $input = $(t.input);
            updateCardState(t.card, $input.is(':checked'));
            $(t.sizeWrapper).toggle($input.is(':checked'));
            $input.on('change', function() {
                updateCardState(t.card, this.checked);
                $(t.sizeWrapper).toggle(this.checked);
            });
        });

        // Make toggle setting-cards clickable
        $(document).on('click', '.setting-card', function(e) {
            if ($(e.target).closest('.custom-control').length) {
                return;
            }
            if ($(e.target).is('input, label, small')) {
                return;
            }
            var $checkbox = $(this).find('input[type="checkbox"].custom-control-input');
            if ($checkbox.length) {
                $checkbox.prop('checked', !$checkbox.prop('checked')).trigger('change');
            }
        });
    });
</script>
