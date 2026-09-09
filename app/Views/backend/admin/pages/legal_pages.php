<!-- Main Content -->
<?php
/**
 * @var string $legal_page_type
 * @var array  $legal_page_types
 * @var array  $languages
 * @var array  $content
 */

use App\Services\utility\PermissionService;

$db      = \Config\Database::connect();
$builder = $db->table('users u');
$builder->select('u.*,ug.group_id')
    ->join('users_groups ug', 'ug.user_id = u.id')
    ->where('ug.group_id', 1)
    ->where(['phone' => $_SESSION['identity']]);
$user1  = $builder->get()->getResultArray();
$userId = !empty($user1) ? $user1[0]['id'] : 0;

$permissionService = new PermissionService();

$termsTypes   = ['customer_terms_conditions', 'terms_conditions', 'handyman_terms_conditions'];
$privacyTypes = ['customer_privacy_policy', 'privacy_policy', 'handyman_privacy_policy'];
?>
<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('terms_and_privacy_settings', 'Terms & Privacy Settings') ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item "><a href="<?= base_url('/admin/settings/system-settings') ?>"><?= labels('system_settings', "System Settings") ?></a></div>
                <div class="breadcrumb-item" id="legal-page-breadcrumb"><?= labels($legal_page_types[$legal_page_type]['label_key'], $legal_page_types[$legal_page_type]['label_default']) ?></div>
            </div>
        </div>

        <div class="row">
            <div class="col-12 col-lg-3 mb-3 mb-lg-0">
                <div class="card p-3">
                    <div class="text-muted small text-uppercase fw-semibold mb-2"><?= labels('terms_and_conditions', 'Terms & Conditions') ?></div>
                    <ul class="nav nav-pills flex-column gap-1 mb-3" id="legal-page-menu">
                        <?php foreach ($termsTypes as $type) : ?>
                            <li class="nav-item">
                                <a class="nav-link legal-page-link <?= $legal_page_type === $type ? 'active' : '' ?>" data-type="<?= $type ?>" href="<?= base_url('admin/settings/legal-pages/' . $type) ?>">
                                    <?= labels($legal_page_types[$type]['label_key'], $legal_page_types[$type]['label_default']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="text-muted small text-uppercase fw-semibold mb-2"><?= labels('privacy_policy', 'Privacy Policy') ?></div>
                    <ul class="nav nav-pills flex-column gap-1">
                        <?php foreach ($privacyTypes as $type) : ?>
                            <li class="nav-item">
                                <a class="nav-link legal-page-link <?= $legal_page_type === $type ? 'active' : '' ?>" data-type="<?= $type ?>" href="<?= base_url('admin/settings/legal-pages/' . $type) ?>">
                                    <?= labels($legal_page_types[$type]['label_key'], $legal_page_types[$type]['label_default']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <div class="col-12 col-lg-9">
                <form id="legal-page-form" class="create-form-without-reset" data-fv data-success-function="onLegalPageSaved" action="<?= base_url('admin/settings/legal-pages/' . $legal_page_type) ?>" method="post">
                    <input type="hidden" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>">
                    <input type="hidden" id="legal-page-type-input" value="<?= $legal_page_type ?>">
                    <div class="container-fluid card p-3">
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <div class="d-flex flex-wrap align-items-center gap-4">
                                    <?php
                                    foreach ($languages as $language) {
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
                        </div>
                        <div class="row">
                            <?php
                            foreach ($languages as $language) {
                            ?>
                            <div class="col-lg" id="translationDiv-<?= $language['code'] ?>" <?= $language['code'] == $current_language ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                <textarea rows="50" class="form-control h-50 summernotes" id="legal-editor-<?= $language['code'] ?>" name="<?= $legal_page_type ?>[<?= $language['code'] ?>]"><?= $content[$language['code']] ?? '' ?></textarea>
                            </div>
                            <?php } ?>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-6 mt-3 mb-4">
                                <a href="<?= base_url('admin/settings/legal-pages/preview/' . $legal_page_type) ?>" id="legal-page-preview-link" target="_blank" class="btn btn-primary"><i class="fa fa-eye"></i> <?= labels('preview', 'Preview') ?></a>
                            </div>
                            <?php if ($permissionService->can($userId, 'update', 'settings')) : ?>
                                <div class="col-md d-flex justify-content-end mt-3">
                                    <div class="form-group">
                                        <button type='submit' id='update' class='btn btn-primary submit_btn'><?= labels('save_changes', "Update") ?></button>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </section>
</div>

<script>
    const legalPageLanguages = <?= json_encode(array_column($languages, 'code')) ?>;

    function setEditorContent(langCode, html) {
        const editorId = 'legal-editor-' + langCode;
        if (typeof tinymce !== 'undefined' && tinymce.get(editorId)) {
            tinymce.get(editorId).setContent(html || '');
        } else {
            $('#' + editorId).val(html || '');
        }
    }

    function onLegalPageSaved(response) {
        if (!response.content) {
            return;
        }
        legalPageLanguages.forEach(function(langCode) {
            const html = response.content[langCode] ? response.content[langCode] : '';
            setEditorContent(langCode, html);
        });
    }

    $(document).ready(function() {
        // select default language
        let default_language = '<?= $current_language ?>';
        const languages = legalPageLanguages;
        const baseUrl = '<?= base_url('admin/settings/legal-pages/') ?>';
        const previewBaseUrl = '<?= base_url('admin/settings/legal-pages/preview/') ?>';

        window.history.replaceState({
            legalPageType: '<?= $legal_page_type ?>'
        }, document.title, window.location.href);

        $(document).on('click', '.language-option', function() {
            const language = $(this).data('language');

            $('.language-underline').css('width', '0%');
            $('#language-' + language).find('.language-underline').css('width', '100%');

            $('.language-text').removeClass('text-primary fw-medium');
            $('.language-text').addClass('text-muted');
            $('#language-' + language).find('.language-text').removeClass('text-muted');
            $('#language-' + language).find('.language-text').addClass('text-primary');

            if (language != default_language) {
                $('#translationDiv-' + language).show();
                $('#translationDiv-' + default_language).hide();
            }

            default_language = language;
        });

        $(document).on('click', '.legal-page-link', function(e) {
            e.preventDefault();
            const $link = $(this);
            const type = $link.data('type');

            if ($link.hasClass('active')) {
                return;
            }

            const url = baseUrl + type;

            $.ajax({
                type: 'GET',
                url: url,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                success: function(response) {
                    if (!response || response.error) {
                        return;
                    }

                    $('.legal-page-link').removeClass('active');
                    $link.addClass('active');

                    $('#legal-page-breadcrumb').text(response.label);
                    document.title = response.title;

                    $('#legal-page-type-input').val(response.legal_page_type);
                    $('#legal-page-form').attr('action', url);
                    $('#legal-page-preview-link').attr('href', previewBaseUrl + response.legal_page_type);

                    languages.forEach(function(langCode) {
                        const html = response.content && response.content[langCode] ? response.content[langCode] : '';
                        setEditorContent(langCode, html);
                        $('textarea[id="legal-editor-' + langCode + '"]').attr('name', response.legal_page_type + '[' + langCode + ']');
                    });

                    window.history.pushState({
                        legalPageType: response.legal_page_type
                    }, response.title, url);
                }
            });
        });

        window.addEventListener('popstate', function(e) {
            const type = e.state && e.state.legalPageType;
            if (type) {
                $('.legal-page-link[data-type="' + type + '"]').trigger('click');
            }
        });
    });
</script>
