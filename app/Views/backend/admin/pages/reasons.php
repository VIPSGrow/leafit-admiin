<style>
    .language-underline-<?= $type ?> {
        position: absolute;
        bottom: 0;
        left: 0;
        height: 2px;
        background: #0d6efd;
        transition: width 0.3s ease;
        border-radius: 1px;
    }
</style>
<div class="main-content">
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= $main_title ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item"><?= $main_title ?></a></div>
            </div>
        </div>
        <?= helper('form'); ?>
        <div class="row">
            <div class="col-md-5">
                <?= view('backend/admin/partials/reasons/_add_form', $this->data) ?>
            </div>
            <div class="col-md-7">
                <?= view('backend/admin/partials/reasons/_list_table', $this->data) ?>
            </div>
        </div>
    </section>
    <?= view('backend/admin/partials/reasons/_update_modal', $this->data) ?>
</div>

<script>
    var csrfName = '<?= csrf_token() ?>';
    var csrfHash = '<?= csrf_hash() ?>';

    $(document).ready(function() {
        // Search and Filter logic
        $("#customSearchBtn").on('click', function() {
            $('#user_list').bootstrapTable('refresh');
        });

        $("#customSearch").on('keypress', function(e) {
            if (e.which == 13) {
                e.preventDefault();
                $('#customSearchBtn').click();
            }
        });

        // Query Params logic
        window.<?= $type ?>_reasons_query_params = function(p) {
            return {
                limit: p.limit,
                offset: p.offset,
                sort: p.sort,
                order: p.order,
                search: $("#customSearch").val()
            };
        };

        // Table Formatter
        window.reasonFormatter = function(value, row, index) {
            return row.reason || 'No translation available';
        };

        // Table Events
        window.<?= $type ?>_reasons_events = {
            'click .edit_reason': function(e, value, row, index) {
                $('#id-<?= $type ?>').val(row.id);
                if (row.needs_additional_info == 1) {
                    $('#edit_needs_additional_info-<?= $type ?>').prop('checked', true);
                } else {
                    $('#edit_needs_additional_info-<?= $type ?>').prop('checked', false);
                }

                if (row.translations) {
                    for (const [lang, val] of Object.entries(row.translations)) {
                        $('#edit_reason-<?= $type ?>-' + lang).val(val);
                    }
                }
                $('#update_modal-<?= $type ?>').modal('show');
            },
            'click .delete_reason': function (e, value, row, index) {
                e.preventDefault();
                e.stopPropagation();

                Swal.fire({
                    title: '<?= labels('are_your_sure', 'Are you sure?') ?>',
                    text: "<?= labels('you_wont_be_able_to_revert_this', "You won't be able to revert this!") ?>",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: '<?= labels('yes_proceed', 'Yes, Proceed!') ?>',
                    cancelButtonText: '<?= labels('cancel', 'Cancel') ?>'
                }).then(function (result) {
                    if (!result.isConfirmed) return;

                    $.ajax({
                        url: "<?= $delete_url ?>",
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            [csrfName]: csrfHash,
                            id: row.id
                        },
                        success: function (response) {
                            if (response && response.csrfName && response.csrfHash) {
                                csrfName = response.csrfName;
                                csrfHash = response.csrfHash;
                            }

                            if (response && response.error === false) {
                                if (typeof showToastMessage === 'function') {
                                    showToastMessage(response.message || '<?= labels('data_deleted_successfully', 'Data deleted successfully') ?>', 'success');
                                }
                                $('#user_list').bootstrapTable('refresh');
                            } else {
                                if (typeof showToastMessage === 'function') {
                                    showToastMessage((response && response.message) ? response.message : '<?= labels('something_went_wrong', 'Something went wrong') ?>', 'error');
                                }
                            }
                        },
                        error: function () {
                            if (typeof showToastMessage === 'function') {
                                showToastMessage('<?= labels('something_went_wrong', 'Something went wrong') ?>', 'error');
                            }
                        }
                    });
                });
            }
        };

        // Language Tab Switching (Add Form)
        $(document).on('click', '.language-option-<?= $type ?>', function() {
            const language = $(this).data('language');
            const type = '<?= $type ?>';
            
            $('.language-underline-<?= $type ?>').css('width', '0%');
            $(this).find('.language-underline-<?= $type ?>').css('width', '100%');

            $('.language-text-<?= $type ?>').removeClass('text-primary fw-medium').addClass('text-muted');
            $(this).find('.language-text-<?= $type ?>').removeClass('text-muted').addClass('text-primary fw-medium');

            $('.translationDiv-<?= $type ?>').hide();
            $('#translationDiv-<?= $type ?>-' + language).show();
        });

        // Language Tab Switching (Modal)
        $(document).on('click', '.language-modal-option-<?= $type ?>', function() {
            const language = $(this).data('language');
            
            $('.language-modal-underline-<?= $type ?>').css('width', '0%');
            $(this).find('.language-modal-underline-<?= $type ?>').css('width', '100%');

            $('.language-modal-text-<?= $type ?>').removeClass('text-primary fw-medium').addClass('text-muted');
            $(this).find('.language-modal-text-<?= $type ?>').removeClass('text-muted').addClass('text-primary fw-medium');

            $('.translationModalDiv-<?= $type ?>').hide();
            $('#translationModalDiv-<?= $type ?>-' + language).show();
        });
    });
</script>
