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
    <section class="section" id="pill-about_us" role="tabpanel">
        <div class="section-header mt-2">
            <h1> <?= labels('about_us', "About Us") ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item "><a href="<?= base_url('/admin/settings/system-settings') ?>"><?= labels('system_settings', "System Settings") ?></a></div>
                <div class="breadcrumb-item"><?= labels('about_us', 'About us') ?></div>
            </div>
        </div>
        <div class="">
            <!-- tab section -->
            <ul class="justify-content-start nav nav-fill nav-pills pl-3 py-2 setting" id="gen-list">
                <div class="row">
                    <li class="nav-item">
                        <a class="nav-link " aria-current="page" href="<?= base_url('admin/settings/general-settings') ?>" id="pills-general_settings-tab" aria-selected="true">
                            <?= labels('general_settings', "General Settings") ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="<?= base_url('admin/settings/about-us') ?>" id="pills-about_us" aria-selected="false">
                            <?= labels('about_us', "About Us") ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= base_url('admin/settings/contact-us') ?>" id="pills-about_us" aria-selected="false">
                            <?= labels('support_details', "Support Details") ?></a>
                    </li>
                </div>
            </ul>
            <!-- tab section ends here -->
        </div>
        <form action="<?= base_url('admin/settings/about-us') ?>" method="post">
            <input type="hidden" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>">
            <div class="container-fluid card p-3">
                <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="d-flex flex-wrap align-items-center gap-4">
                            <?php 
                            foreach ($languages as $index => $language) { 
                                if($language['is_default'] == 1) {  
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
                </div>
                <div class="row">
                    <?php 
                    foreach ($languages as $index => $language) { 
                    ?>
                    <div class="col-lg" id="translationDiv-<?= $language['code'] ?>" <?= $language['code'] == $current_language ? 'style="display: block;"' : 'style="display: none;"' ?>>
                        <textarea rows=50 class='form-control h-50 summernotes' name="about_us[<?= $language['code'] ?>]"><?= isset($about_us[$language['code']]) ? $about_us[$language['code']] : '' ?></textarea>
                    </div>
                    <?php } ?>
                </div>
                <div class="row mt-2">
                    <div class="col-md-6 mt-3 mb-4">
                        <a href="<?= base_url('admin/settings/about-us-preview'); ?>" class="btn btn-primary"><i class="fa fa-eye"></i> <?= labels('preview', 'Preview') ?></a>
                    </div>
                    <?php if ($permissions['update']['settings'] == 1) : ?>

                        <div class="col-md-6 mt-3  justify-content-end d-flex">
                            <div class="form-group">
                                <input type='submit' name='update' id='update' value='<?= labels('save_changes', "Update") ?>' class='btn btn-primary' />
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </section>
</div>

<script>
    $(document).ready(function() {
        // select default language
        let default_language = '<?= $current_language ?>';

        $(document).on('click', '.language-option', function() {
            const language = $(this).data('language');

            $('.language-underline').css('width', '0%');
            $('#language-' + language).find('.language-underline').css('width', '100%');

            $('.language-text').removeClass('text-primary fw-medium');
            $('.language-text').addClass('text-muted');
            $('#language-' + language).find('.language-text').removeClass('text-muted');
            $('#language-' + language).find('.language-text').addClass('text-primary');

            if(language != default_language){
                $('#translationDiv-' + language).show();
                $('#translationDiv-' + default_language).hide();
            }

            default_language = language;
        });
    });
</script>