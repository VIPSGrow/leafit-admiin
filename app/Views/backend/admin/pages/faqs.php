<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('faqs', "FAQs") ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item"><?= labels('FAQs', 'FAQs') ?></a></div>
            </div>
        </div>
        <?= helper('form'); ?>
        <div class="row">
            <div class="col-md-5">
                <div class="container-fluid card">
                    <div class="row ">
                        <div class="col mb-12" style="border-bottom: solid 1px #e5e6e9;">
                            <div class="toggleButttonPostition"><?= labels('manage_FAQs', 'Manage FAQs') ?></div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?= form_open('admin/faqs/add_faqs', ['method' => "post", 'class' => 'form-submit-event', 'id' => 'add_faqs', 'enctype' => "multipart/form-data"]); ?>
                        <div class="row mb-3">
                            <div class="col-md-12">
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
                        </div>
                        <?php
                        foreach ($languages as $index => $language) {
                        ?>
                            <div id="translationDiv-<?= $language['code'] ?>" <?= $language['code'] == $current_language ? 'style="display: block;"' : 'style="display: none;"' ?>>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="question<?= $language['code'] ?>" <?= $language['is_default'] ? 'class="required"' : '' ?>><?= labels('question', "Question") . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                            <input id="question<?= $language['code'] ?>" class="form-control" type="text" name="question[<?= $language['code'] ?>]" placeholder="<?= labels('enter_question', 'Enter the question here') ?>" <?= $language['is_default'] ? 'required' : '' ?>>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="answer<?= $language['code'] ?>" <?= $language['is_default'] ? 'class="required"' : '' ?>><?= labels('answer', "Answer") . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                            <textarea id="answer<?= $language['code'] ?>" style="min-height:60px" class="form-control" name="answer[<?= $language['code'] ?>]" placeholder="<?= labels('enter_answer', 'Enter answer') ?>" <?= $language['is_default'] ? 'required' : '' ?>></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                        <div class="row">
                            <div class="col-md d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary submit_btn"><?= labels('add_FAQS', "Add FAQs") ?></button>
                            </div>
                        </div>
                        <?= form_close(); ?>
                    </div>
                </div>
            </div>
            <div class="col-md-7">
                <div class="container-fluid card">
                    <div class="row ">
                        <div class="col mb-12" style="border-bottom: solid 1px #e5e6e9;">
                            <div class="toggleButttonPostition"><?= labels('FAQs', "FAQs") ?></div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12">
                                <div class="row mt-4 mb-3 ">
                                    <div class="col-md-4 col-sm-2 mb-2">
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="customSearch" placeholder="<?= labels('search', 'Search') ?>" aria-label="Search" aria-describedby="customSearchBtn">
                                            <div class="input-group-append">
                                                <button class="btn btn-primary" type="button">
                                                    <i class="fa fa-search d-inline"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="dropdown d-inline ml-2">
                                        <button class="btn export_download dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            <?= labels('download', 'Download') ?>
                                        </button>
                                        <div class="dropdown-menu" x-placement="bottom-start" style="position: absolute; transform: translate3d(0px, 28px, 0px); top: 0px; left: 0px; will-change: transform;">
                                            <a class="dropdown-item" onclick="custome_export('pdf','FAQs list','user_list');"><?= labels('pdf', "PDF") ?></a>
                                            <a class="dropdown-item" onclick="custome_export('excel','FAQs list','user_list');"><?= labels('excel', "Excel") ?></a>
                                            <a class="dropdown-item" onclick="custome_export('csv','FAQs list','user_list')"><?= labels('csv', "CSV") ?></a>
                                        </div>
                                    </div>
                                </div>
                                <table class="table " data-fixed-columns="true" id="user_list" data-detail-formatter="user_formater"
                                    data-auto-refresh="true" data-toggle="table"
                                    data-url="<?= base_url("admin/faqs/list") ?>" data-side-pagination="server" data-pagination="true" data-page-list="[5, 10, 25, 50, 100, 200, All]"
                                    data-search="false" data-show-columns="false" data-show-columns-search="true" data-show-refresh="false" data-sort-name="id" data-sort-order="DESC"
                                    data-query-params="faqs_query_params" data-pagination-successively-size="2">
                                    <thead>
                                        <tr>
                                            <th data-field="id" class="text-center" data-sortable="true"><?= labels('id', 'ID') ?></th>
                                            <th data-field="question" class="text-center" data-sortable="true"><?= labels('question', 'Question') ?></th>
                                            <th data-field="answer" class="text-center" data-sortable="true"><?= labels('answer', 'Answer') ?></th>
                                            <th data-field="created_at" class="text-center" data-visible="false" data-sortable="true"><?= labels('created_at', 'Created At') ?></th>
                                            <th data-field="operations" class="text-center" data-events="faqs_events"><?= labels('operations', 'Operations') ?></th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- update modal -->
    <div class="modal fade" id="update_modal" tabindex="-1" aria-labelledby="update_modal_thing" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel"><?= labels('edit_FAQs', 'Edit FAQs') ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <?= form_open('admin/faqs/edit_faqs', ['method' => "post", 'class' => 'form-submit-event', 'id' => 'edit_faqs', 'enctype' => "multipart/form-data"]); ?>
                    <input type="hidden" name="id" id="id">
                    <div class="d-flex flex-wrap align-items-center gap-4 mb-3">
                        <?php
                        foreach ($languages as $index => $language) {
                            if ($language['is_default'] == 1) {
                                $current_modal_language = $language['code'];
                            }
                        ?>
                            <div class="language-modal-option position-relative <?= $language['is_default'] ? 'selected' : '' ?>"
                                id="language-modal-<?= $language['code'] ?>"
                                data-language="<?= $language['code'] ?>"
                                style="cursor: pointer; padding: 0.5rem 0;">
                                <span class="language-modal-text px-2 <?= $language['is_default'] ? 'text-primary fw-medium' : 'text-muted' ?>"
                                    style="font-size: 0.875rem; transition: color 0.3s ease; white-space: nowrap;">
                                    <?= $language['language'] ?><?= $language['is_default'] ? '(Default)' : '' ?>
                                </span>
                                <div class="language-modal-underline"
                                    style="position: absolute; bottom: 0; left: 0; width: <?= $language['is_default'] ? '100%' : '0' ?>; height: 2px; background: #0d6efd; transition: width 0.3s ease; border-radius: 1px;"></div>
                            </div>
                        <?php } ?>
                    </div>
                    <?php
                    foreach ($languages as $index => $language) {
                    ?>
                        <div id="translationModalDiv-<?= $language['code'] ?>" <?= $language['code'] == $current_modal_language ? 'style="display: block;"' : 'style="display: none;"' ?>>
                            <div class="form-group">
                                <label for="edit_question<?= $language['code'] ?>" <?= $language['is_default'] ? 'class="required"' : '' ?>><?= labels('question', "Question") . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                <input id="edit_question<?= $language['code'] ?>" class="form-control" type="text" name="question[<?= $language['code'] ?>]" placeholder="<?= labels('enter_question', 'Enter the question here') ?>" <?= $language['is_default'] ? 'required' : '' ?>>
                            </div>
                            <div class="form-group">
                                <label for="edit_answer<?= $language['code'] ?>" <?= $language['is_default'] ? 'class="required"' : '' ?>><?= labels('answer', "Answer") . ($language['is_default'] ? '' : ' (' . $language['code'] . ')') ?></label>
                                <textarea id="edit_answer<?= $language['code'] ?>" style="min-height:60px" class="form-control col-md-12" name="answer[<?= $language['code'] ?>]" placeholder="<?= labels('enter_answer', 'Enter the answer here') ?>" <?= $language['is_default'] ? 'required' : '' ?>></textarea>
                            </div>
                        </div>
                    <?php } ?>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary" name="submit"><?= labels('save_changes', "Save changes") ?></button>
                    <?php form_close() ?>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= labels('close', "Close") ?></button>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    $("#customSearch").on('keydown', function() {
        $('#user_list').bootstrapTable('refresh');
    });
</script>
<script>
    // select default language
    $(document).ready(function() {
        let default_language = '<?= $current_language ?>';
        let current_modal_language = '<?= $current_modal_language ?>';

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

        $(document).on('click', '.language-modal-option', function() {
            const language = $(this).data('language');

            $('.language-modal-underline').css('width', '0%');
            $('#language-modal-' + language).find('.language-modal-underline').css('width', '100%');

            $('.language-modal-text').removeClass('text-primary fw-medium');
            $('.language-text-faqs').addClass('text-muted');
            $('#language-modal-' + language).find('.language-modal-text').removeClass('text-muted');
            $('#language-modal-' + language).find('.language-modal-text').addClass('text-primary');

            if (language != current_modal_language) {
                $('#translationModalDiv-' + language).show();
                $('#translationModalDiv-' + current_modal_language).hide();
            }

            current_modal_language = language;
        });

        // Function to populate edit modal with FAQ data
        function populateEditModal(faqData, translations) {
            // Get default language code
            const defaultLang = '<?= $current_language ?>';

            // Clear all form fields first
            $('input[name^="question["], textarea[name^="answer["]').val('');

            // Populate default language fields with main FAQ data
            $('#edit_question' + defaultLang).val(faqData.question || '');
            $('#edit_answer' + defaultLang).val(faqData.answer || '');

            // Populate translated fields if translations exist
            if (translations && typeof translations === 'object') {
                Object.keys(translations).forEach(function(languageCode) {
                    const translation = translations[languageCode];
                    if (translation) {
                        $('#edit_question' + languageCode).val(translation.question || '');
                        $('#edit_answer' + languageCode).val(translation.answer || '');
                    }
                });
            }
        }

        // Handle edit button click
        $(document).on('click', '.edit_faqs', function() {
            const faqId = $(this).data('id');

            // Set the FAQ ID in the hidden field
            $('#id').val(faqId);

            // Fetch FAQ data with translations
            $.ajax({
                url: '<?= base_url("admin/faqs/get_faq_data") ?>',
                type: 'POST',
                data: {
                    id: faqId,
                    '<?= csrf_token() ?>': '<?= csrf_hash() ?>'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.error === false && response.data) {
                        // Populate the modal with FAQ data and translations
                        populateEditModal(response.data.faq, response.data.translations);
                    } else {
                        console.error('Error fetching FAQ data:', response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error);
                }
            });
        });
    });
</script>