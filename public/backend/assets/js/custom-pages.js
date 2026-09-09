/**
 * Custom Pages - DataTable formatters, status toggle, and utility functions
 */

// Status toggle formatter for DataTable column
function statusFormatter(value, row) {
    if (typeof CAN_UPDATE_CUSTOM_PAGES !== 'undefined' && CAN_UPDATE_CUSTOM_PAGES) {
        var checked = value == 1 ? 'checked' : '';
        return '<label class="custom-switch">' +
            '<input type="checkbox" class="custom-switch-input toggle-status" ' + checked +
            ' data-id="' + row.id + '">' +
            '<span class="custom-switch-indicator"></span>' +
            '</label>';
    }
    // Read-only badge when user lacks update permission
    var activeLabel = (typeof switchTextMap !== 'undefined' && switchTextMap['Active']) ? switchTextMap['Active'] : 'Active';
    var inactiveLabel = (typeof switchTextMap !== 'undefined' && switchTextMap['Inactive']) ? switchTextMap['Inactive'] : 'Inactive';
    if (value == 1) {
        return '<span class="badge badge-success">' + activeLabel + '</span>';
    }
    return '<span class="badge badge-danger">' + inactiveLabel + '</span>';
}

// Content formatter with read more / read less
function contentFormatter(value, row) {
    if (!value || value.trim() === '') {
        return '-';
    }

    // Strip HTML tags for plain text preview
    var tempDiv = document.createElement('div');
    tempDiv.innerHTML = value;
    var plainText = tempDiv.textContent || tempDiv.innerText || '';
    plainText = plainText.trim();

    var maxLength = 150;
    if (plainText.length <= maxLength) {
        return '<div style="max-width:400px; word-wrap:break-word;">' + plainText + '</div>';
    }

    var uniqueId = 'content-' + row.id;
    var truncated = plainText.substring(0, maxLength) + '...';

    return '<div style="max-width:400px; word-wrap:break-word;">' +
        '<span id="' + uniqueId + '-short">' + truncated +
        ' <a href="javascript:void(0);" class="text-primary read-more-btn" data-target="' + uniqueId + '" style="white-space:nowrap;">Read more</a>' +
        '</span>' +
        '<span id="' + uniqueId + '-full" style="display:none;">' + plainText +
        ' <a href="javascript:void(0);" class="text-primary read-less-btn" data-target="' + uniqueId + '" style="white-space:nowrap;">Read less</a>' +
        '</span>' +
        '</div>';
}

// Read more click handler
$(document).on('click', '.read-more-btn', function() {
    var target = $(this).data('target');
    $('#' + target + '-short').hide();
    $('#' + target + '-full').show();
});

// Read less click handler
$(document).on('click', '.read-less-btn', function() {
    var target = $(this).data('target');
    $('#' + target + '-full').hide();
    $('#' + target + '-short').show();
});

// Status toggle AJAX handler
$(document).on('change', '.toggle-status', function() {
    var id = $(this).data('id');
    var status = $(this).is(':checked') ? 1 : 0;
    $.post(BASE_URL + '/admin/custom-pages/toggle-status', {
        [csrfName]: csrfHash,
        id: id,
        status: status
    }, function(data) {
        csrfName = data.csrfName;
        csrfHash = data.csrfHash;
        if (data.error == false) {
            showToastMessage(data.message, 'success');
        } else {
            showToastMessage(data.message, 'error');
            $('#custom_pages_list').bootstrapTable('refresh');
        }
    });
});
