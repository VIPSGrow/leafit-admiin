<!-- Main Content -->
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
    <section class="section">
        <div class="section-header mt-2">
            <h1><?= labels('country_codes', "Country Codes") ?></h1>
            <div class="section-header-breadcrumb">
                <div class="breadcrumb-item active"><a href="<?= base_url('/admin/dashboard') ?>"><i class="fas fa-home-alt text-primary"></i> <?= labels('Dashboard', 'Dashboard') ?></a></div>
                <div class="breadcrumb-item "><a href="<?= base_url('/admin/settings/system-settings') ?>"><?= labels('system_settings', "System Settings") ?></a></div>
                <div class="breadcrumb-item"><?= labels('country_codes', "Country Codes") ?></a></div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <?= helper('form'); ?>
                    <div class="row m-0">
                        <div class="col border_bottom_for_cards">
                            <div class="toggleButttonPostition"><?= labels('import_country_codes', "Import Country Codes") ?></div>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Import Country Codes Form -->
                        <?php if ($permissions['create']['settings'] == 1) : ?>
                            <?= form_open('admin/settings/import_country_codes', ['method' => "post", 'class' => 'form-submit-event', 'id' => 'import_country_codes_form', 'enctype' => "multipart/form-data"]); ?>
                            <div class="form-group">
                                <label for="country_select"><?= labels('select_countries_to_import', "Select Countries to Import") ?></label>
                                <select id="country_select" class="form-control select2" name="selected_countries[]" multiple="multiple" style="width: 100%;">
                                    <?php if (isset($available_countries) && !empty($available_countries)): ?>
                                        <?php foreach ($available_countries as $country): ?>
                                            <option value="<?= $country['country_name'] ?>"
                                                data-flag="<?= $country['flag_image'] ?>"
                                                data-country-code="<?= isset($country['country_code']) ? $country['country_code'] : '' ?>"
                                                data-calling-code="<?= $country['calling_code'] ?>">
                                                <?= $country['country_name'] ?> (<?= $country['calling_code'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="d-flex flex-column">
                                <!-- Info text section -->
                                <div class="d-flex align-items-center mb-2">
                                    <div class="text-muted small">
                                        <?php if (isset($available_countries) && !empty($available_countries)): ?>
                                            <i class="fas fa-info-circle"></i> <?= count($available_countries) ?> <?= labels('countries_available_for_import', 'countries available for import') ?>
                                        <?php else: ?>
                                            <i class="fas fa-exclamation-triangle"></i> <?= labels('no_countries_available', 'No new countries available for import') ?>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Button row section -->
                                <?php if (isset($available_countries) && !empty($available_countries)): ?>
                                    <div class="d-flex flex-wrap align-items-center justify-content-end gap-2">
                                        <button type="button" class="btn btn-outline-primary mr-2" id="select_all_countries_btn">
                                            <i class="fas fa-check-double"></i> <?= labels('select_all', "Select All") ?>
                                        </button>

                                        <button type="button" class="btn btn-lg bg-new-primary" id="import_selected_btn">
                                            <i class="fas fa-download"></i> <?= labels('import_selected_countries', "Import Selected Countries") ?>
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div id="import_info" class="mt-2"></div>
                            <?= form_close(); ?>
                        <?php else : ?>
                            <div class="alert alert-warning">
                                <?= labels('no_permission_import', 'You do not have permission to import country codes.') ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($permissions['read']['settings'] == 1) : ?>

                <div class="col-md-8">
                    <div class=" card">
                        <div class="row">
                            <div class="col-lg">
                                <div class="row m-0">
                                    <div class="col border_bottom_for_cards">
                                        <div class="toggleButttonPostition"><?= labels('country_codes', "Country codes") ?></div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div class="row mt-2">
                                        <div class="col-md-12">
                                            <div class="row pb-3 ">
                                                <div class="col-12">
                                                    <div class="row mb-3 ">
                                                        <div class="col-md-4 col-sm-2 mb-2">
                                                            <div class="input-group">
                                                                <input type="text" class="form-control" id="customSearch" placeholder="<?= labels('search_here', 'Search here!') ?>" aria-label="Search" aria-describedby="customSearchBtn">
                                                                <div class="input-group-append">
                                                                    <button class="btn btn-primary" type="button">
                                                                        <i class="fa fa-search d-inline"></i>
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <!-- <div class="dropdown d-inline ml-2">
                                                            <button class="btn export_download dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                                <?= labels('download', 'Download') ?>
                                                            </button>
                                                            <div class="dropdown-menu" x-placement="bottom-start" style="position: absolute; transform: translate3d(0px, 28px, 0px); top: 0px; left: 0px; will-change: transform;">
                                                                <a class="dropdown-item" onclick="custome_export('pdf','Tax list','tax_list');"> <?= labels('pdf', 'PDF') ?></a>
                                                                <a class="dropdown-item" onclick="custome_export('excel','Tax list','tax_list');"> <?= labels('excel', 'Excel') ?></a>
                                                                <a class="dropdown-item" onclick="custome_export('csv','Tax list','tax_list')"> <?= labels('csv', 'CSV') ?></a>
                                                            </div>
                                                        </div> -->
                                                    </div>
                                                </div>
                                            </div>
                                            <table class="table" data-pagination-successively-size="2" data-query-params="country_code_query_params" id="country_code_list" data-detail-formatter="user_formater" data-auto-refresh="true" data-toggle="table" data-url="<?= base_url("admin/settings/fetch_contry_code") ?>" data-side-pagination="server" data-pagination="true" data-page-list="[5, 10, 25, 50, 100, 200, All]" data-search="false" data-show-columns="false" data-show-columns-search="true" data-show-refresh="false" data-sort-name="id" data-sort-order="desc">
                                                <thead>
                                                    <tr>
                                                        <th data-field="id" class="text-center" data-sortable="true"><?= labels('id', 'ID') ?></th>
                                                        <th data-field="country_name" class="text-center" data-sortable="true"><?= labels('country_name', 'Country Name') ?></th>
                                                        <th data-field="calling_code" class="text-center" data-sortable="true"><?= labels('calling_code', 'Calling Code') ?></th>
                                                        <th data-field="country_code" class="text-center" data-sortable="true"><?= labels('country_code', 'Country Code') ?></th>
                                                        <th data-field="flag_image" name="flag_image" class="text-center"><?= labels('flag_image', 'Flag Image') ?></th>
                                                        <th data-field="default" class="text-center" data-sortable="false"><?= labels('default', 'Default') ?></th>
                                                        <th data-field="created_at" class="text-center" data-visible="false" data-sortable="true"><?= labels('created_at', 'Created At') ?></th>
                                                        <th data-field="operations" class="text-center" data-events="Countr_code_events"><?= labels('operations', 'Operations') ?></th>
                                                    </tr>
                                                </thead>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <?php endif; ?>

        </div>
    </section>
    <!-- update modal -->
    <div class="modal fade" id="update_modal" tabindex="-1" aria-labelledby="update_modal_thing" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header m-0 p-0">
                    <div class="row pl-3 w-100">
                        <div class="col-12 " style="border-bottom: solid 1px #e5e6e9;">
                            <div class="toggleButttonPostition"><?= labels('update_country_code', 'Update Country Code') ?></div>
                        </div>
                    </div>
                </div>
                <div class="modal-body">
                    <?= form_open('admin/settings/update_country_codes', ['method' => "post", 'class' => 'form-submit-event', 'id' => 'edit_country_code_form', 'enctype' => "multipart/form-data"]); ?>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="code"><?= labels('code', "Code") ?></label>
                                <input id="edit_code" class="form-control" type="text" name="code" placeholder="<?= labels('enter_code_here', 'Enter the code here') ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="name"><?= labels('name', "Name") ?></label>
                                <input id="edit_name" class="form-control" type="text" name="name" placeholder="<?= labels('enter_name_here', 'Enter the name here') ?>">
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="id" id="id">
                    <div class="modal-footer">
                        <button type="submit" class="btn bg-new-primary submit_btn"><?= labels('update_country_code', 'Update Country Code') ?></button>
                        <?php form_close() ?>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= labels('close', "Close") ?></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    // Search functionality for country codes table
    $("#customSearch").on('keydown', function() {
        $('#country_code_list').bootstrapTable('refresh');
    });

    // Initialize country code events (edit and delete functionality)
    $(document).ready(function() {
        window.Countr_code_events = {
            "click .delete-country_code": function(e, value, row, index) {
                var id = row.id;
                Swal.fire({
                    title: "<?= labels('are_your_sure', 'Are you sure?') ?>",
                    text: "<?= labels('you_wont_be_able_to_revert_this', 'You won\'t be able to revert this!') ?>",
                    icon: "error",
                    showCancelButton: true,
                    confirmButtonText: "<?= labels('yes_proceed', 'Yes, Proceed!') ?>",
                    cancelButtonText: "<?= labels('cancel', 'Cancel') ?>",
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.post(
                            baseUrl + "/admin/settings/delete_contry_code", {
                                [csrfName]: csrfHash,
                                id: id,
                            },
                            function(data) {
                                csrfName = data.csrfName;
                                csrfHash = data.csrfHash;

                                if (data.error == false) {
                                    showToastMessage(data.message, "success");
                                    setTimeout(() => {
                                        $("#country_code_list").bootstrapTable("refresh");
                                        // Refresh the available countries in the select dropdown after deletion
                                        refreshCountrySelect();
                                    }, 1000);
                                    return;
                                } else {
                                    return showToastMessage(data.message, "error");
                                }
                            }
                        );
                    }
                });
            },
            "click .edit_country_code": function(e, value, row, index) {
                $("#id").val(row.id);
                $("#edit_name").val(row.name);
                $("#edit_code").val(row.code);
            },
        };
    });

    // Handle default country code selection
    $(document).on('click', '.store_default_country_code', function() {
        var id = $(this).data("id");
        var base_url = baseUrl;
        $.ajax({
            url: baseUrl + "/admin/settings/store_default_country_code",
            type: "POST",
            dataType: "json",
            data: {
                id: id
            },
            success: function(result) {
                if (result) {
                    iziToast.success({
                        title: "",
                        message: result.message,
                        position: "topRight",
                    })
                    $("#country_code_list").bootstrapTable("refresh");
                }
            }
        });
    });

    // Handle edit form submission callback
    document.getElementById('edit_country_code_form').addEventListener('submit', function(e) {
        setTimeout(function() {
            $("#country_code_list").bootstrapTable("refresh");
        }, 500);
    });

    // Format functions for Select2 country options
    function formatCountryOption(option) {
        if (!option.id) return option.text;

        const flagUrl = $(option.element).data('flag');
        const text = option.text;

        if (!flagUrl) return text;

        return $(
            `<span style="display: flex; align-items: center;">
                <img src="${flagUrl}" style="width: 20px; height: 14px; margin-right: 8px; object-fit: cover;" />
                ${text}
            </span>`
        );
    }

    function formatCountrySelection(option) {
        const flagUrl = $(option.element).data('flag');
        const text = option.text;

        if (!flagUrl) return text;

        return $(
            `<span style="display: flex; align-items: center;">
                <img src="${flagUrl}" style="width: 20px; height: 14px; margin-right: 8px; object-fit: cover;" />
                ${text}
            </span>`
        );
    }

    // Function to refresh the country select dropdown
    function refreshCountrySelect() {
        // Fetch updated available countries from server
        $.ajax({
            url: baseUrl + "/admin/settings/get_available_countries", // You'll need to create this endpoint
            type: "GET",
            dataType: "json",
            success: function(response) {
                if (response.error === false && response.available_countries) {
                    // Clear existing options
                    $('#country_select').empty();

                    // Add new options
                    $.each(response.available_countries, function(index, country) {
                        var option = $('<option></option>')
                            .attr('value', country.country_name)
                            .attr('data-flag', country.flag_image)
                            .attr('data-country-code', country.country_code || '')
                            .attr('data-calling-code', country.calling_code)
                            .text(country.country_name + " (" + country.calling_code + ")");
                        $('#country_select').append(option);
                    });

                    // Reinitialize select2 if it was initialized
                    if ($('#country_select').hasClass("select2-hidden-accessible")) {
                        $('#country_select').select2('destroy');
                        // Reinitialize with same settings
                        $('#country_select').select2({
                            placeholder: '<?= labels("select_countries_placeholder", "Choose countries to import...") ?>',
                            allowClear: true,
                            width: '100%',
                            multiple: true,
                            closeOnSelect: false,
                            templateResult: formatCountryOption,
                            templateSelection: formatCountrySelection,
                            theme: 'default'
                        });
                    }

                    // Update info text
                    var availableCount = response.available_countries.length;
                    if (availableCount > 0) {
                        $('.text-muted.small').html(`<i class="fas fa-info-circle"></i> ${availableCount } <?= labels("countries_available_for_import", "countries available for import") ?>`);
                        $('#select_all_countries_btn, #import_selected_btn').show();
                    } else {
                        $('.text-muted.small').html(`<i class="fas fa-exclamation-triangle"></i> <?= labels("no_countries_available", "No new countries available for import") ?>`);
                        $('#select_all_countries_btn, #import_selected_btn').hide();
                    }

                    // Ensure import button is always enabled when visible
                    $('#import_selected_btn').prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error refreshing country select:', error);
            }
        });
    }

    // Import functionality JavaScript
    $(document).ready(function() {
        // Delay initialization to ensure DOM is fully ready
        setTimeout(function() {
            // Check if country_select element exists before initializing
            if ($('#country_select').length > 0) {
                try {
                    // Destroy any existing select2 instance first
                    if ($('#country_select').hasClass("select2-hidden-accessible")) {
                        $('#country_select').select2('destroy');
                    }

                    // Initialize select2 for better multi-select experience
                    $('#country_select').select2({
                        placeholder: '<?= labels("select_countries_placeholder", "Choose countries to import...") ?>',
                        allowClear: true,
                        width: '100%',
                        multiple: true,
                        closeOnSelect: false,
                        templateResult: formatCountryOption,
                        templateSelection: formatCountrySelection,
                        theme: 'default'
                    });


                    // Update info display based on selection (but keep button enabled)
                    $('#country_select').on('change.select2', function() {
                        var selectedCount = $(this).val() ? $(this).val().length : 0;

                        if (selectedCount > 0) {
                            $('#import_info').html(`<div class="alert alert-info alert-sm"><i class="fas fa-info-circle"></i> ${selectedCount} <?= labels("countries_selected", "countries selected for import") ?></div>`);
                        } else {
                            $('#import_info').html('');
                        }
                    });

                    // Initialize with current state
                    $('#country_select').trigger('change.select2');

                    // Handle Select All button click
                    $('#select_all_countries_btn').on('click', function() {
                        // Get all available option values
                        var allValues = [];
                        $('#country_select option').each(function() {
                            if ($(this).val()) { // Skip empty values
                                allValues.push($(this).val());
                            }
                        });

                        // Select all options and trigger change
                        $('#country_select').val(allValues).trigger('change.select2');
                    });

                    // Handle Import button click with AJAX
                    $('#import_selected_btn').on('click', function() {
                        var selectedValues = $('#country_select').val();

                        if (!selectedValues || selectedValues.length === 0) {
                            // Show warning message but don't prevent form submission
                            Swal.fire({
                                title: '<?= labels("no_countries_selected", "No Countries Selected") ?>',
                                text: '<?= labels("please_select_at_least_one_country", "Please select at least one country to import.") ?>',
                                icon: 'warning',
                                confirmButtonText: '<?= labels("ok", "OK") ?>',
                                confirmButtonColor: '#3085d6'
                            });
                            return;
                        }

                        // Prepare countries data with all required fields
                        var countriesData = [];
                        $.each(selectedValues, function(index, countryName) {
                            var option = $('#country_select option[value="' + countryName + '"]');
                            countriesData.push({
                                country_name: countryName,
                                country_code: option.data('country-code') || '',
                                calling_code: option.data('calling-code') || '',
                                flag_image: option.data('flag') || ''
                            });
                        });

                        // Show loading state
                        var originalText = $(this).html();
                        $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

                        // Show progress info
                        $('#import_info').html('<div class="alert alert-info alert-sm"><i class="fas fa-clock"></i></div>');

                        // Make AJAX request
                        $.ajax({
                            url: baseUrl + "/admin/settings/import_country_codes",
                            type: "POST",
                            dataType: "json",
                            data: {
                                [csrfName]: csrfHash,
                                countries_data: countriesData
                            },
                            success: function(response) {
                                // Update CSRF tokens
                                if (response.csrfName && response.csrfHash) {
                                    csrfName = response.csrfName;
                                    csrfHash = response.csrfHash;
                                }

                                if (response.error === false) {
                                    // Success response
                                    showToastMessage(response.message, 'success');

                                    // Clear selections and refresh table
                                    $('#country_select').val(null).trigger('change.select2');
                                    $("#country_code_list").bootstrapTable("refresh");

                                    // Refresh the available countries in the select dropdown
                                    refreshCountrySelect();

                                    // Show success info
                                    $('#import_info').html(`<div class="alert alert-success alert-sm"><i class="fas fa-check-circle"></i> ${response.message}</div>`);

                                    // Clear success message after 5 seconds
                                    setTimeout(function() {
                                        $('#import_info').html('');
                                    }, 5000);
                                } else {
                                    // Error response
                                    showToastMessage(response.message, 'error');
                                    $('#import_info').html(`<div class="alert alert-danger alert-sm"><i class="fas fa-exclamation-triangle"></i> ${response.message} </div>`);
                                }
                            },
                            error: function(xhr, status, error) {
                                // Network or server error
                                console.error('Import error:', error);
                                showToastMessage('<?= labels("import_failed", "Import failed. Please try again.") ?>', 'error');
                                $('#import_info').html('<div class="alert alert-danger alert-sm"><i class="fas fa-exclamation-triangle"></i> <?= labels("import_failed", "Import failed. Please try again.") ?></div>');
                            },
                            complete: function() {
                                // Reset button state (always enabled)
                                $('#import_selected_btn').html(originalText);
                            }
                        });
                    });

                } catch (error) {
                    console.error('Error initializing Select2:', error);
                    // Fallback to regular select if Select2 fails
                    $('#country_select').on('change', function() {
                        var selectedCount = $(this).val() ? $(this).val().length : 0;

                        if (selectedCount > 0) {
                            $('#import_info').html(`<div class="alert alert-info alert-sm"><i class="fas fa-info-circle"></i> ${selectedCount} <?= labels("countries_selected", "countries selected for import") ?></div>`);
                        } else {
                            $('#import_info').html('');
                        }
                    });

                    // Handle Select All button click (fallback)
                    $('#select_all_countries_btn').on('click', function() {
                        // Get all available option values
                        var allValues = [];
                        $('#country_select option').each(function() {
                            if ($(this).val()) { // Skip empty values
                                allValues.push($(this).val());
                            }
                        });

                        // Select all options and trigger change
                        $('#country_select').val(allValues).trigger('change');
                    });

                    // Handle Import button click with AJAX (fallback)
                    $('#import_selected_btn').on('click', function() {
                        var selectedValues = $('#country_select').val();

                        if (!selectedValues || selectedValues.length === 0) {
                            // Show warning message but don't prevent form submission
                            Swal.fire({
                                title: '<?= labels("no_countries_selected", "No Countries Selected") ?>',
                                text: '<?= labels("please_select_at_least_one_country", "Please select at least one country to import.") ?>',
                                icon: 'warning',
                                confirmButtonText: '<?= labels("ok", "OK") ?>',
                                confirmButtonColor: '#3085d6'
                            });
                            return;
                        }

                        // Prepare countries data with all required fields
                        var countriesData = [];
                        $.each(selectedValues, function(index, countryName) {
                            var option = $('#country_select option[value="' + countryName + '"]');
                            countriesData.push({
                                country_name: countryName,
                                country_code: option.data('country-code') || '',
                                calling_code: option.data('calling-code') || '',
                                flag_image: option.data('flag') || ''
                            });
                        });

                        // Show loading state
                        var originalText = $(this).html();
                        $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

                        // Show progress info
                        $('#import_info').html('<div class="alert alert-info alert-sm"><i class="fas fa-clock"></i></div>');

                        // Make AJAX request
                        $.ajax({
                            url: baseUrl + "/admin/settings/import_country_codes",
                            type: "POST",
                            dataType: "json",
                            data: {
                                [csrfName]: csrfHash,
                                countries_data: countriesData
                            },
                            success: function(response) {
                                // Update CSRF tokens
                                if (response.csrfName && response.csrfHash) {
                                    csrfName = response.csrfName;
                                    csrfHash = response.csrfHash;
                                }

                                if (response.error === false) {
                                    // Success response
                                    showToastMessage(response.message, 'success');

                                    // Clear selections and refresh table
                                    $('#country_select').val([]).trigger('change');
                                    $("#country_code_list").bootstrapTable("refresh");

                                    // Refresh the available countries in the select dropdown
                                    refreshCountrySelect();

                                    // Show success info
                                    $('#import_info').html(`<div class="alert alert-success alert-sm"><i class="fas fa-check-circle"></i> ${response.message} </div>`);

                                    // Clear success message after 5 seconds
                                    setTimeout(function() {
                                        $('#import_info').html('');
                                    }, 5000);
                                } else {
                                    // Error response
                                    showToastMessage(response.message, 'error');
                                    $('#import_info').html(`<div class="alert alert-danger alert-sm"><i class="fas fa-exclamation-triangle"></i> ${response.message} </div>`);
                                }
                            },
                            error: function(xhr, status, error) {
                                // Network or server error
                                console.error('Import error:', error);
                                showToastMessage('<?= labels("import_failed", "Import failed. Please try again.") ?>', 'error');
                                $('#import_info').html('<div class="alert alert-danger alert-sm"><i class="fas fa-exclamation-triangle"></i> <?= labels("import_failed", "Import failed. Please try again.") ?></div>');
                            },
                            complete: function() {
                                // Reset button state (always enabled)
                                $('#import_selected_btn').html(originalText);
                            }
                        });
                    });
                }
            } else {
                console.warn('Country select element not found');
            }

            // Note: Form submission is now handled via AJAX button click instead of form submit
        }, 50); // Small delay to ensure DOM is ready
    });
</script>