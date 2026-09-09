"use strict";
// WAIT_TEXT keeps loader text translated so every submit state honors locale.
var WAIT_TEXT =
  typeof window !== "undefined" &&
    typeof window.please_wait_text !== "undefined" &&
    window.please_wait_text !== null &&
    window.please_wait_text.toString().trim() !== ""
    ? window.please_wait_text
    : "Please Wait...";
function showToastMessage(message, type) {
  switch (type) {
    case "error":
      $().ready(
        iziToast.error({
          title: "",
          message: message,
          position: "topRight",
        })
      );
      break;
    case "success":
      $().ready(
        iziToast.success({
          title: "",
          message: message,
          position: "topRight",
        })
      );
      break;
  }
}
function set_locale(language_code) {
  $.ajax({
    url: baseUrl + "/lang/" + language_code,
    type: "GET",
    success: function (result) { },
  }).then(() => {
    location.reload();
  });
}
function detailFormatter(index, row) {
  var html = [];
  $.each(row, function (key, value) {
    if (key != "base_64" && key != "is_ssml" && key != "operate") {
      html.push("<p><b>" + key + ":</b> " + value + "</p>");
    }
  });
  return html.join("");
}
$(".repeat_usage").hide();
if ($("input[name='repeat_usage']").is(":checked")) {
  $(".repeat_usage").show();
}
$("#repeat_usage").on("click", function () {
  $(".repeat_usage").hide();
  if ($("input[name='repeat_usage']").is(":checked")) {
    $(".repeat_usage").show();
  }
});
window.orders_events = {
  "click .delete_orders": function (e, value, row, index) {
    var id = row.id;
    Swal.fire({
      title: are_your_sure,
      text: you_wont_be_able_to_revert_this,
      icon: "error",
      showCancelButton: true,
      confirmButtonText: yes_proceed,
      cancelButtonText: cancel,
    }).then((result) => {
      if (result.isConfirmed) {
        $.post(
          baseUrl + "/admin/Orders/delete_orders",
          {
            [csrfName]: csrfHash,
            id: id,
          },
          function (data) {
            csrfName = data.csrfName;
            csrfHash = data.csrfHash;
            if (data.error == false) {
              showToastMessage(data.message, "success");
              setTimeout(() => {
                $("#user_list").bootstrapTable("refresh");
              }, 500);
              window.location.reload();
              return;
            } else {
              return showToastMessage(data.message, "error");
            }
          }
        );
      }
    });
  },

  // Partner-side "Chat" action from the orders table.
  // We deliberately avoid inline onclick (blocked by GlobalSanitizer) and instead
  // use this Bootstrap-table event handler. The `row` object has all IDs we need.
  "click .open-booking-chat": function (e, value, row, index) {
    e.preventDefault();
    if (!row || !row.id) {
      return;
    }
    // `row.partner_id` and `row.user_id` are available in the server response.
    openBookingChat(row.id, row.partner_id, row.user_id);
  },
};
window.promo_codes_events = {
  "click .delete": function (e, value, row, index) {
    e.preventDefault();
    var id = row.id;
    Swal.fire({
      title: are_your_sure,
      text: be_aware_this_shall_fordid_the_data,
      icon: "error",
      showCancelButton: true,
      confirmButtonText: yes_proceed,
      cancelButtonText: cancel,
    }).then((result) => {
      if (result.isConfirmed) {
        $.post(
          baseUrl + "/partner/promo_codes/delete",
          {
            [csrfName]: csrfHash,
            id: id,
          },
          function (data) {
            csrfName = data.csrfName;
            csrfHash = data.csrfHash;
            if (data.error == false) {
              showToastMessage(data.message, "success");
              setTimeout(() => {
                $("#promocode_table").bootstrapTable("refresh");
              }, 2000);
              return;
            } else {
              return showToastMessage(data.message, "error");
            }
          }
        );
      }
    });
  },
  "click .edit": function (e, value, row, index) {
    $("#image_edit").html("");
    e.preventDefault();

    // Get promocode data with translations
    var input_body = {
      [csrfName]: csrfHash,
      id: row.id,
    };

    $.ajax({
      type: "POST",
      url: baseUrl + "partner/promo_codes/get_promocode_data",
      data: input_body,
      dataType: "json",
      success: function (response) {
        csrfName = response["csrfName"];
        csrfHash = response["csrfHash"];

        if (response.error == false) {
          var promocode = response.data.promocode;
          var translations = response.data.translations;

          // Set basic promocode fields
          $('input[name="promo_id"]').val(promocode.id);
          $('input[name="promo_code"]').val(promocode.promo_code);

          // Set number of users field (moved before setTimeout for consistency)
          // This ensures the field is prefilled when the modal opens
          $('input[name="no_of_users"]').val(promocode.no_of_users || '');

          // Set minimum booking amount field
          $('input[name="minimum_order_amount"]').val(promocode.minimum_order_amount || '');

          // Set discount field
          $('input[name="discount"]').val(promocode.discount || '');

          // Set max discount amount field
          $('input[name="max_discount_amount"]').val(promocode.max_discount_amount || '');

          // Set discount type and trigger change to show/hide max discount field
          $("#discount_type").val(promocode.discount_type || 'percentage').trigger("change");

          // Extract only date part (YYYY-MM-DD) from datetime string if present
          // This ensures date pickers show only date, not time
          var startDate = promocode.start_date ? promocode.start_date.split(' ')[0] : '';
          var endDate = promocode.end_date ? promocode.end_date.split(' ')[0] : '';
          $('input[name="start_date"]').val(startDate);
          $('input[name="end_date"]').val(endDate);

          // Set date picker values after a small delay to ensure daterangepicker is initialized
          // This is needed because the modal might not be fully shown when AJAX callback executes
          setTimeout(function () {
            var startDatePicker = $('#start_date');
            var endDatePicker = $('#end_date');

            // Check if daterangepicker is initialized before accessing it
            if (startDatePicker.length && startDatePicker.data('daterangepicker')) {
              var startPicker = startDatePicker.data('daterangepicker');
              if (startDate && typeof startPicker.setStartDate === 'function') {
                startPicker.setStartDate(moment(startDate, 'YYYY-MM-DD'));
              }
            }

            if (endDatePicker.length && endDatePicker.data('daterangepicker')) {
              var endPicker = endDatePicker.data('daterangepicker');
              if (endDate && typeof endPicker.setStartDate === 'function') {
                endPicker.setStartDate(moment(endDate, 'YYYY-MM-DD'));
              }
              // Set minimum date for end date picker based on start date
              if (startDate && typeof endPicker.setMinDate === 'function') {
                endPicker.setMinDate(moment(startDate, 'YYYY-MM-DD'));
              }
            }
          }, 100);

          // Handle image preview - similar to admin panel
          // The backend returns direct image URLs (or formatted URL if file exists)
          // So we can use the URL directly to create an img element
          // IMPORTANT SECURITY NOTE:
          // We validate the image URL before using it to avoid DOM-based XSS
          // (e.g. someone trying to pass a "javascript:" or "data:" URL).

          function isSafeImageUrl(url) {
            // Basic, easy-to-read URL safety check
            if (typeof url !== "string") {
              return false;
            }

            var trimmed = url.trim();
            if (!trimmed) {
              return false;
            }

            var lower = trimmed.toLowerCase();

            // Block clearly dangerous URL schemes
            if (
              lower.indexOf("javascript:") === 0 ||
              lower.indexOf("data:") === 0 ||
              lower.indexOf("vbscript:") === 0
            ) {
              return false;
            }

            // Allow common safe patterns:
            // - http/https URLs
            // - absolute paths (/images/...)
            // - relative paths (./, ../, etc.)
            var safePattern = /^(https?:\/\/|\/|\.{1,2}\/)/i;
            return safePattern.test(trimmed);
          }

          if (promocode.image && isSafeImageUrl(promocode.image)) {
            // Check if img element already exists, if not create one
            var imgElement = $("#image_edit img");
            if (imgElement.length === 0) {
              // Create img element with proper styling to match admin panel
              imgElement = $('<img>', {
                src: promocode.image,
                style: 'border-radius: 8px; height: 100px; width: 100px !important;',
                alt: 'Promocode image preview',
                class: 'w-50'
              });
              // Use empty().append() instead of html() to avoid injecting raw HTML strings
              $("#image_edit").empty().append(imgElement);
            } else {
              // Update existing img element src
              imgElement.attr("src", promocode.image);
            }
          } else {
            // Fallback to default image if no URL is provided
            var imgElement = $("#image_edit img");
            if (imgElement.length === 0) {
              imgElement = $('<img>', {
                src: baseUrl + "public/backend/assets/default.png",
                style: 'border-radius: 8px; height: 100px; width: 100px !important;',
                alt: 'Promocode image preview',
                class: 'w-50'
              });
              // Use empty().append() instead of html() to keep DOM updates safe and explicit
              $("#image_edit").empty().append(imgElement);
            } else {
              imgElement.attr("src", baseUrl + "public/backend/assets/default.png");
            }
          }

          // Load translations into textareas for all languages
          // Get all available languages from the modal (all language options)
          var allLanguages = [];
          var defaultLanguageCode = null;

          $('.language-modal-option').each(function () {
            var langCode = $(this).data('language');
            if (langCode) {
              allLanguages.push(langCode);
              // Check if this is the default language (has "selected" class or contains "(Default)" text)
              if ($(this).hasClass('selected') || $(this).text().includes('(Default)')) {
                defaultLanguageCode = langCode;
              }
            }
          });

          // Function to populate translations
          function populateTranslations() {
            if (translations) {
              // Populate all language textareas
              Object.keys(translations).forEach(function (language_code) {
                var textarea = $('textarea[name="message[' + language_code + ']"]');
                if (textarea.length) {
                  textarea.val(translations[language_code] || '');
                }

                // Also try to populate using ID selector as backup
                var textareaById = $('#message' + language_code);
                if (textareaById.length && !textareaById.val()) {
                  textareaById.val(translations[language_code] || '');
                }
              });
            }
          }

          // Try to populate immediately
          populateTranslations();

          // Also try after a delay to ensure modal is fully loaded
          setTimeout(populateTranslations, 300);

          // Also try when modal is fully shown
          $('#update_modal').on('shown.bs.modal', function () {
            populateTranslations();
          });

          setTimeout(function () {
            if (promocode.status == "Active") {
              $(".editInModel").prop("checked", false).trigger("click");
            } else {
              $(".editInModel").prop("checked", true).trigger("click");
            }
            if (promocode.repeat_usage == 1) {
              $("#repeat_usage").prop("checked", false).trigger("click");
              $(".repeat_usage").show();
              $('input[name="no_of_repeat_usage"]').val(promocode.no_of_repeat_usage);
            } else {
              $("#repeat_usage").prop("checked", true).trigger("click");
              $(".repeat_usage").hide();
            }
          }, 600);
        }
      }
    });
  },
};
$(document).on("submit", "#withdrawal_request_form", function (e) {
  e.preventDefault();
  var $form = $(this);
  // Guard against double-submission: ignore further submits while a request
  // is already in flight. Re-enabled only on error (success redirects away).
  if ($form.data("submitting")) {
    return;
  }
  $form.data("submitting", true);
  var $submitBtn = $form.find('button[type="submit"]');
  // Stash original label so we can restore it if the request fails.
  $submitBtn.data("originalHtml", $submitBtn.html());
  $submitBtn
    .prop("disabled", true)
    .html('<i class="fas fa-spinner fa-spin"></i> ' + $submitBtn.text().trim());
  var formData = new FormData(this);
  formData.append(csrfName, csrfHash);
  $.ajax({
    type: "post",
    url: this.action,
    data: formData,
    cache: false,
    processData: false,
    contentType: false,
    dataType: "json",
    success: function (result) {
      csrfName = result["csrf_token"];
      csrfHash = result["csrf_hash"];
      if (result.error == true) {
        var message = "";
        Object.keys(result.message).map((key) => {
          iziToast.error({
            title: "",
            message: result.message[key],
            position: "topRight",
          });
        });
        // Validation/business error: allow the provider to fix and resubmit.
        $form.data("submitting", false);
        $submitBtn.prop("disabled", false).html($submitBtn.data("originalHtml"));
      } else {
        showToastMessage(result.message, "success");
        setTimeout(function () {
          location.href = baseUrl + "/partner/withdrawal_requests";
        }, 500);
      }
    },
    error: function () {
      // Network/server failure: re-enable so the provider can retry.
      $form.data("submitting", false);
      $submitBtn.prop("disabled", false).html($submitBtn.data("originalHtml"));
    },
  });
});
window.payment_request_events = {
  "click .delete": function (e, value, row, index) {
    e.preventDefault();
    var id = row.id;
    Swal.fire({
      title: are_your_sure,
      text: be_aware_this_shall_fordid_the_data,
      icon: "error",
      showCancelButton: true,
      confirmButtonText: yes_proceed,
      cancelButtonText: cancel,
    }).then((result) => {
      if (result.isConfirmed) {
        $.post(
          baseUrl + "/partner/withdrawal_requests/delete",
          {
            [csrfName]: csrfHash,
            id: id,
          },
          function (data) {
            csrfName = data.csrfName;
            csrfHash = data.csrfHash;
            if (data.error == false) {
              showToastMessage(data.message, "success");
              setTimeout(() => {
                $("#withdrawal_requests_table").bootstrapTable("refresh");
              }, 2000);
              return;
            } else {
              return showToastMessage(data.message, "error");
            }
          }
        );
      }
    });
  },
  "click .edit": function (e, value, row, index) {
    e.preventDefault();
    $('input[name="user_id"]').val(row.user_id);
    $('input[name="request_id"]').val(row.id);
    $('input[name="amount"]').val(row.amount);
    $('textarea[name="payment_address"]').val(row.payment_address);
  },
};
$(document).on("submit", ".form-submit-event", function (e) {
  e.preventDefault();
  var formData = new FormData(this);
  var submit_btn = $(this).find(".submit_btn");
  var btn_html = $(this).find(".submit_btn").html();
  var btn_val = $(this).find(".submit_btn").val();
  var button_text =
    btn_html != "" || btn_html != "undefined" ? btn_html : btn_val;
  formData.append(csrfName, csrfHash);
  $.ajax({
    type: "POST",
    url: $(this).attr("action"),
    data: formData,
    cache: false,
    contentType: false,
    processData: false,
    dataType: "json",
    beforeSend: function () {
      submit_btn.html(WAIT_TEXT);
      submit_btn.attr("disabled", true);
    },
    success: function (response) {
      csrfName = response["csrfName"];
      csrfHash = response["csrfHash"];
      if (response.error == false) {
        showToastMessage(response.message, "success");

        // Check if response has reload flag and reload the page
        if (response.reload === true) {
          window.location.reload();
          return; // Exit early to prevent other actions
        }

        // When user changed phone (e.g. email-login user), backend destroys session and
        // asks for re-login. Redirect to login so user lands already logged out.
        if (response.require_relogin === true && response.redirect_url) {
          if (isSafeRedirectUrl(response.redirect_url)) {
            setTimeout(function () {
              window.location.href = response.redirect_url;
            }, 1500);
            return;
          }
        }

        // Redirect to service list page when backend explicitly asks for it
        // Added 2.5 second delay to allow users time to read the success toast message before redirecting
        // IMPORTANT SECURITY NOTE:
        // We validate the redirect URL to avoid open-redirect and XSS via dangerous schemes.

        function isSafeRedirectUrl(url) {
          if (typeof url !== "string") {
            return false;
          }

          var trimmed = url.trim();
          if (!trimmed) {
            return false;
          }

          var lower = trimmed.toLowerCase();

          // Block clearly dangerous URL schemes
          if (
            lower.indexOf("javascript:") === 0 ||
            lower.indexOf("data:") === 0 ||
            lower.indexOf("vbscript:") === 0
          ) {
            return false;
          }

          // Allow only:
          // - relative paths (./, ../, or starting with / but not //)
          // - or absolute URLs pointing to the same origin
          try {
            // If it looks like an absolute URL, compare origin
            var parsed = new URL(trimmed, window.location.origin);
            return parsed.origin === window.location.origin;
          } catch (e) {
            // Fallback: basic relative-path validation
            return (
              trimmed.charAt(0) === "/" ||
              trimmed.indexOf("./") === 0 ||
              trimmed.indexOf("../") === 0
            );
          }
        }

        if (response.redirect_url && isSafeRedirectUrl(response.redirect_url)) {
          setTimeout(function () {
            window.location.href = response.redirect_url;
          }, 3000);
          return;
        }

        setTimeout(() => {
          $("#user_list").bootstrapTable("refresh");
          window.location.reload();
          submit_btn.html(button_text);
        }, 500);
        $(".close").click();
      } else {
        if (
          typeof response.message === "object" &&
          !Array.isArray(response.message) &&
          response.message !== null
        ) {
          for (var k in response.message) {
            if (response.message.hasOwnProperty(k)) {
              showToastMessage(response.message[k], "error");
            }
          }
        } else {
          showToastMessage(response.message, "error");
        }
        submit_btn.attr("disabled", false);
        submit_btn.html(button_text);
        $("#update_modal").bootstrapTable("refresh");
      }
    },
  });
});
function readURL(input) {
  var reader = new FileReader();
  reader.onload = function (e) {
    document
      .querySelector("#service_image")
      .setAttribute("src", e.target.result);
    document
      .querySelector("#update_service_image")
      .setAttribute("src", e.target.result);
  };
  reader.readAsDataURL(input.files[0]);
}
$(document).ready(() => {
  setTimeout(() => {
    $("#category_item").select2({
      placeholder: select_category,
    });
    $("#sub_category").select2({
      placeholder: select_sub_category,
    });
  }, 100);
});
$("#category_item").on("change", function (e) {
  e.preventDefault();
  $(".error").remove();
  $.post(
    baseUrl + "/admin/categories/list",
    {
      [csrfName]: csrfHash,
      id: $(this).val(),
      from_app: true,
    },
    function (data) {
      csrfName = data.csrfName;
      csrfHash = data.csrfHash;
      if (data.error == false) {
        var sub_categories = data.data;

        // IMPORTANT SECURITY NOTE:
        // We now create <option> elements using jQuery and set their
        // value/text via .val() and .text() instead of building an HTML string.
        // This makes sure any remote data (element.id / element.name)
        // is treated as plain text and cannot inject HTML/JS.
        $("#sub_category").empty();

        sub_categories.forEach((element) => {
          var $opt = $("<option></option>");
          if (typeof element.id !== "undefined") {
            $opt.val(element.id);
          }
          if (typeof element.name !== "undefined") {
            $opt.text(element.name);
          }
          $("#sub_category").append($opt);
          $("#sub_category").val(element.id);
        });
        $("#sub_category").attr("disabled", false);
        $("#sub_category")
          .parent()
          .append('<span class="text-danger error"></span>');
      } else {
        $("#sub_category").empty();
        $("#sub_category").attr("disabled", true);
        $("#sub_category")
          .parent()
          .append(
            '<span class="text-danger error">No Found sub categories on this category Please change categories</span>'
          );
      }
    }
  );
});
window.services_events = {
  "click .delete": function (e, value, row, index) {
    var id = row.id;
    Swal.fire({
      title: are_your_sure,
      text: you_wont_be_able_to_revert_this,
      icon: "error",
      showCancelButton: true,
      confirmButtonText: yes_proceed,
      cancelButtonText: cancel,
    }).then((result) => {
      if (result.isConfirmed) {
        $.post(
          baseUrl + "/partner/services/delete_service",
          {
            [csrfName]: csrfHash,
            id: id,
          },
          function (data) {
            csrfName = data.csrfName;
            csrfHash = data.csrfHash;
            if (data.error == false) {
              showToastMessage(data.message, "success");
              $("#partner_service_list").bootstrapTable("refresh");
            } else {
            }
          }
        );
      }
    });
  },
  "click .edit": function (e, value, row, index) {
    e.preventDefault();
    $("#sub_category").empty();
    $(".image").empty();
    $("#update_modal").on("hide.bs.modal", function () {
      $('input[name="is_cancelable"]').prop("checked", false);
    });
    $('input[name="service_id"]').val(row.id);
    $('input[name="user_id"]').val(row.user_id);
    $('input[name="title"]').val(row.title);
    $('textarea[name="description"]').val(row.description);
    $('input[name="tags[]"]').val(row.tags);
    $('input[name="price"]').val(row.price);
    $('input[name="discounted_price"]').val(row.discounted_price);
    $('input[name="members"]').val(row.number_of_members_required);
    $('input[name="duration"]').val(row.duration);
    $('input[name="max_qty"]').val(row.max_quantity_allowed);
    $('input[name="tax"]').val(row.tax);
    $('input[name="tax_type"]').val(row.tax_type);
    $("#category_item").val(row.category_id);
    $("#tax_id").val(row.tax_id);
    $("#category_item").val(row.category_id).trigger("change");
    $("#edit_tax_type").val(row.tax_type.trim());
    $("#edit_tax").val(row.tax_id);
    if (row.cancelable == "1") {
      $('input[name="is_cancelable"]').prop("checked", true);
      $(".cancelable-till").show();
      $("#cancelable_till").val(row.cancelable_till);
    } else {
      $('input[name="is_cancelable"]').prop("checked", false);
      $(".cancelable-till").hide();
      $("#cancelable_till").val("");
    }
    if (row.status_number == "1") {
      $("#edit_status_active").prop("checked", true);
    } else {
      $("#edit_status_deactive").prop("checked", true);
    }
    if (row.is_pay_later_allowed == "1") {
      $('input[name="pay_later"]').attr("checked", true);
    } else {
      $('input[name="pay_later"]').attr("checked", false);
    }
    $(".image").append(row.image_of_the_service);
    $('input[name="old_icon"]').val(row.image_of_the_service);
  },
};
$(".cancelable-till").hide();
if ($("input[name='is_cancelable']").is(":checked")) {
  $(".cancelable-till").show();
}
$("#is_cancelable").on("click", function () {
  $(".cancelable-till").hide();
  if ($("input[name='is_cancelable']").is(":checked")) {
    $(".cancelable-till").show();
  }
});
var order_status_filter = "";
$("#order_status_filter").on("change", function () {
  order_status_filter = $(this).find("option:selected").val();
});
$("#filter").on("click", function (e) {
  $("#user_list").bootstrapTable("refresh");
});
function orders_query(p) {
  return {
    search: p.search,
    limit: p.limit,
    sort: p.sort,
    order: p.order,
    offset: p.offset,
    order_status_filter: order_status_filter,
  };
}
function withdraw_request_query(p) {
  return {
    search: p.search,
    limit: p.limit,
    sort: p.sort,
    order: p.order,
    offset: p.offset,
  };
}
function update_order_status() {
  var status = $(".update_order_status").val();
  var customer_id = $(".update_order_status").attr("data-customer_id");
  var order_id = $('input[name="order_id"]').val();
  Swal.fire({
    title: are_your_sure,
    text: you_want_to_update_order_status,
    icon: "error",
    showCancelButton: true,
    confirmButtonText: yes_proceed,
    cancelButtonText: cancel,
  }).then((result) => {
    if (result.isConfirmed) {
      $.ajax({
        type: "get",
        url: siteUrl + "/partner/orders/update_order_status",
        data: {
          status: status,
          order_id: order_id,
          customer_id: customer_id,
        },
        cache: false,
        dataType: "json",
        success: function (result) {
          if (result.error == false) {
            if (result.contact != null) {
              Swal.fire({
                title: call_now,
                text: result.contact,
                icon: "error",
                showCancelButton: true,
                confirmButtonText: yes_proceed,
                cancelButtonText: cancel,
              });
            }
            showToastMessage(result.message, "success");
          } else {
            showToastMessage(result.message, "error");
            setTimeout(() => {
              location.reload();
            }, 2000);
            return;
          }
        },
      });
    }
  });
}

// let autocomplete;
// let map;
// let marker = "";
// let partner_location = "";
// var partner_map = document.getElementById("map");
// var latitude = $("#partner_latitude").val();
// var longitude = $("#partner_longitude").val();
// let center = {
//   lat: parseFloat(latitude),
//   lng: parseFloat(longitude),
// };
// function initautocomplete() {
//   if ($("#city_search").length > 0) {
//     autocomplete = new google.maps.places.Autocomplete(
//       document.getElementById("city_search"),
//       {
//         types: ["establishment"],
//         componentRestriction: {
//           country: ["India"],
//         },
//         fields: ["place_id", "geometry", "name"],
//       }
//     );
//     autocomplete.addListener("place_changed", onPlaceChanged);
//     var place = autocomplete.getPlace();
//   }
//   latitude =
//     typeof place != "undefined"
//       ? place.geometry.location.lat()
//       : parseFloat(latitude);
//   longitude =
//     typeof place != "undefined"
//       ? place.geometry.location.lng()
//       : parseFloat(longitude);
//   var center = {
//     lat: latitude,
//     lng: longitude,
//   };
//   if (document.getElementById("map") != null) {
//     partner_location = new google.maps.Map(document.getElementById("map"), {
//       center,
//       zoom: 16,
//     });
//     set_map_marker_for_partner("", latitude, longitude, "", partner_location);
//     /* add marker on clicked location */
//     google.maps.event.addListener(partner_location, "click", function (event) {
//       var latitude = event.latLng.lat();
//       var longitude = event.latLng.lng();
//       set_map_marker_for_partner("", latitude, longitude, "", partner_location);
//       $("#partner_latitude").val(latitude);
//       $("#partner_longitude").val(longitude);
//     }); //end addListener
//   }
//   function onPlaceChanged(e) {
//     place = autocomplete.getPlace();
//     let latitude = place.geometry.location.lat();
//     let longitude = place.geometry.location.lng();
//     set_map_marker_for_partner(place, "", "", "", partner_location);
//     $("#partner_latitude").val(latitude);
//     $("#partner_longitude").val(longitude);
//   }
// }
// function set_map_marker_for_partner(
//   place = "",
//   latitude = "",
//   longitude = "",
//   name = "",
//   map = ""
// ) {
//   if (place !== "") {
//     latitude = place.geometry.location.lat();
//     longitude = place.geometry.location.lng();
//   } else {
//     latitude = parseFloat(latitude);
//     longitude = parseFloat(longitude);
//   }
//   let title = place.name ? place.name : name;
//   let contentString = "<h6> " + title + " </h6>";
//   center = {
//     lat: place ? place.geometry.location.lat() : latitude,
//     lng: place ? place.geometry.location.lng() : longitude,
//   };
//   const infowindow = new google.maps.InfoWindow({
//     content: contentString,
//   });
//   if (!map) {
//     partner_location = new google.maps.Map(partner_map, {
//       center,
//       zoom: 16,
//     });
//   } else {
//     partner_location = map;
//   }
//   if (marker == "") {
//     marker = new google.maps.Marker({
//       title: title,
//       animation: google.maps.Animation.DROP,
//       position: center,
//       map: partner_location,
//       // draggable: true
//     });
//   } else {
//     marker.setPosition({ lat: latitude, lng: longitude });
//   }
//   if (place != "") {
//     partner_location.setCenter(center);
//     partner_location.setZoom(16);
//   }
//   marker.addListener("click", () => {
//     infowindow.open({
//       anchor: marker,
//       map: partner_location,
//       shouldFocus: false,
//     });
//   });
// }
// window.initMap = initautocomplete;
$(function () {
  FilePond.registerPlugin(
    FilePondPluginImagePreview,
    FilePondPluginFileValidateSize,
    FilePondPluginFileValidateType
  );
  $(".filepond").filepond({
    credits: null,
    allowFileSizeValidation: "true",
    maxFileSize: "5MB",
    labelMaxFileSizeExceeded: file_is_too_large,
    labelIdle: drag_and_drop_files_here + " " + or + ' <span class="filepond--label-action">' + browse_files + '</span>',
    labelMaxFileSize: maximum_file_size_is + " {filesize}",
    allowFileTypeValidation: true,
    acceptedFileTypes: ["image/*", "video/*", "application/pdf"],
    labelFileTypeNotAllowed: file_of_invalid_type,
    fileValidateTypeLabelExpectedTypes:
      "Expects {allButLastType} or {lastType}",
    storeAsFile: true,
    allowPdfPreview: true,
    pdfPreviewHeight: 320,
    pdfComponentExtraParams: "toolbar=0&navpanes=0&scrollbar=0&view=fitH",
    allowVideoPreview: true,
    allowAudioPreview: true,
  });
  $(".filepond-docs").filepond({
    credits: null,
    allowFileSizeValidation: "true",
    maxFileSize: "25MB",
    labelMaxFileSizeExceeded: file_is_too_large,
    labelIdle: drag_and_drop_files_here + " " + or + ' <span class="filepond--label-action">' + browse_files + '</span>',
    labelMaxFileSize: maximum_file_size_is + " {filesize}",
    allowFileTypeValidation: true,
    acceptedFileTypes: [
      "application/pdf",
      "application/msword",
      "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
    ],
    labelFileTypeNotAllowed: file_of_invalid_type,
    labelIdle: drag_and_drop_files_here + " " + or + ' <span class="filepond--label-action">' + browse_files + '</span>',
    fileValidateTypeLabelExpectedTypes:
      "Expects {allButLastType} or {lastType}",
    storeAsFile: true,
    allowPdfPreview: true,
    pdfPreviewHeight: 320,
    pdfComponentExtraParams: "toolbar=0&navpanes=0&scrollbar=0&view=fitH",
    allowVideoPreview: true,
    allowAudioPreview: true,
  });
  $(".filepond-excel").filepond({
    credits: null,
    allowFileSizeValidation: true,
    maxFileSize: "25MB",
    labelMaxFileSizeExceeded: file_is_too_large,
    labelIdle: drag_and_drop_files_here + " " + or + ' <span class="filepond--label-action">' + browse_files + '</span>',
    labelMaxFileSize: maximum_file_size_is + " {filesize}",
    allowFileTypeValidation: true,
    acceptedFileTypes: [
      "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
      "application/vnd.ms-excel",
      "text/csv",
      "application/csv",
      "text/plain",
    ],
    labelFileTypeNotAllowed:
      invalid_file_type_please_upload_an_excel_or_csv_file,
    labelIdle: drag_and_drop_files_here + " " + or + ' <span class="filepond--label-action">' + browse_files + '</span>',
    fileValidateTypeLabelExpectedTypes:
      "Expects {allButLastType} or {lastType}",
    storeAsFile: true,
    allowPdfPreview: false,
    allowVideoPreview: false,
    allowAudioPreview: false,
  });
  $(".filepond-only-images-and-videos").filepond({
    credits: null,
    allowFileSizeValidation: "true",
    maxFileSize: "5MB",
    labelMaxFileSizeExceeded: file_is_too_large,
    labelMaxFileSize: maximum_file_size_is + " {filesize}",
    allowFileTypeValidation: true,
    acceptedFileTypes: ["image/*", "video/*"],
    labelFileTypeNotAllowed: file_of_invalid_type,
    labelIdle: drag_and_drop_files_here + " " + or + ' <span class="filepond--label-action">' + browse_files + '</span>',
    fileValidateTypeLabelExpectedTypes:
      "Expects {allButLastType} or {lastType}",
    storeAsFile: true,
    allowPdfPreview: true,
    pdfPreviewHeight: 320,
    pdfComponentExtraParams: "toolbar=0&navpanes=0&scrollbar=0&view=fitH",
    allowVideoPreview: true,
    allowAudioPreview: true,
  });
});
$("#rating_table").on({
  "load-success.bs.table , page-change.bs.table, check.bs.table, uncheck.bs.table, column-switch.bs.table":
    function (e) {
      for (let i = 0; i < $(".service-ratings").length; i++) {
        let element = $(".service-ratings")[i];
        let id = $(".service-ratings")[i]["id"];
        let ratings = $(element).attr("data-value");
        $(document).ready(function () {
          $("#" + id).rateYo({
            rating: ratings,
            spacing: "5px",
            readOnly: true,
            starWidth: "25px",
          });
        });
      }
    },
});
var elems = Array.prototype.slice.call(
  document.querySelectorAll(".status-switch")
);
elems.forEach(function (elem) {
  var switchery = new Switchery(elem, {
    size: "small",
    color: "#47C363",
    secondaryColor: "#EB4141",
    jackColor: "#ffff",
    jackSecondaryColor: "#ffff",
  });
});

$(".filepond-other_image").filepond({
  credits: null,
  allowFileSizeValidation: "true",
  maxFileSize: "25MB",
  labelMaxFileSizeExceeded: file_is_too_large,
  labelIdle: drag_and_drop_files_here + " " + or + ' <span class="filepond--label-action">' + browse_files + '</span>',
  labelMaxFileSize: maximum_file_size_is + " {filesize}",
  allowFileTypeValidation: true,
  acceptedFileTypes: ["image/*", "video/*", "application/pdf"],
  labelFileTypeNotAllowed: file_of_invalid_type,
  labelIdle: drag_and_drop_files_here + " " + or + ' <span class="filepond--label-action">' + browse_files + '</span>',
  fileValidateTypeLabelExpectedTypes: "Expects {allButLastType} or {lastType}",
  storeAsFile: true,
  allowPdfPreview: true,
  pdfPreviewHeight: 320,
  pdfComponentExtraParams: "toolbar=0&navpanes=0&scrollbar=0&view=fitH",
  allowVideoPreview: true,
  allowAudioPreview: true,
  allowMultiple: true,
});
if ($(".summernotes").length) {
  tinymce.init({
    selector: ".summernotes",
    height: 300,
    menubar: false,
    plugins: [
      "advlist autolink lists link image charmap print preview anchor",
      "searchreplace visualblocks code fullscreen",
      "insertdatetime media table paste",
    ],
    toolbar:
      "undo redo | styleselect | bold italic underline | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image table code",
    maxlength: null,
    relative_urls: false,
    remove_script_host: false,
    document_base_url: baseUrl,
  });
}
var stripe1;
$("#make_payment_for_subscription").on("submit", function (event) {
  event.preventDefault();
  stripe1 = stripe_setup($("#stripe_key_id").val());
  $.post(
    baseUrl + "/partner/subscription/pre-payment-setup",
    {
      [csrfName]: csrfHash,
      payment_method: "stripe",
    },
    function (data) {
      $("#stripe_client_secret").val(data.client_secret);
      $("#stripe_payment_id").val(data.id);
      var stripe_client_secret = data.client_secret;
      stripe_payment(stripe1.stripe, stripe1.card, stripe_client_secret);
      csrfName = data.csrfName;
      csrfHash = data.csrfHash;
    },
    "json"
  );
});
function stripe_setup(key) {
  var stripe = Stripe(key);
  var elements = stripe.elements();
  var style = {
    base: {
      color: "#32325d",
      fontFamily: "Arial, sans-serif",
      fontSmoothing: "antialiased",
      fontSize: "16px",
      "::placeholder": {
        color: "#32325d",
      },
    },
    invalid: {
      fontFamily: "Arial, sans-serif",
      color: "#fa755a",
      iconColor: "#fa755a",
    },
  };
  var card = elements.create("card", {
    style: style,
  });
  card.mount("#stripe-card-element");
  card.on("change", function (event) {
    document.querySelector("button").disabled = event.empty;
    document.querySelector("#card-error").textContent = event.error
      ? event.error.message
      : "";
  });
  return {
    stripe: stripe,
    card: card,
  };
}
function stripe_payment(stripe, card, clientSecret) {
  stripe
    .confirmCardPayment(clientSecret, {
      payment_method: {
        card: card,
      },
    })
    .then(function (result) {
      if (result.error) {
        var errorMsg = document.querySelector("#card-error");
        errorMsg.textContent = result.error.message;
        setTimeout(function () {
          errorMsg.textContent = "";
        }, 4000);
        Toast.fire({
          icon: "error",
          title: result.error.message,
        });
        $("#buy").attr("disabled", false).html("Buy");
      } else {
        purchase_subscription().done(function (result) {
          if (result.error == false) {
            setTimeout(function () {
              location.href = baseUrl + "payment/success";
            }, 1000);
          }
        });
      }
    });
}
function purchase_subscription() {
  let myForm = document.getElementById("make_payment_for_subscription");
  var formdata = new FormData(myForm);
  return $.ajax({
    type: "POST",
    data: formdata,
    url: base_url + "partner/subscription-payment",
    dataType: "json",
    cache: false,
    processData: false,
    contentType: false,
    beforeSend: function () {
      $("#buy").attr("disabled", true).html(WAIT_TEXT);
    },
    success: function (data) {
      csrfName = data.csrfName;
      csrfHash = data.csrfHash;
      $("#buy").attr("disabled", false).html("Buy");
      if (data.error == false) {
        Toast.fire({
          icon: "success",
          title: data.message,
        });
      } else {
        Toast.fire({
          icon: "error",
          title: data.message,
        });
      }
    },
  });
}
var stripe1;
$(document).ready(function () {
  $("#make_payment_for_subscription").on("submit", function (event) {
    stripe1 = stripe_setup($("#stripe_key_id").val());
    event.preventDefault();
    $.post(
      baseUrl + "/partner/subscription/pre-payment-setup",
      {
        [csrfName]: csrfHash,
        payment_method: "stripe",
      },
      function (data) {
        $("#stripe_client_secret").val(data.client_secret);
        $("#stripe_payment_id").val(data.id);
        var stripe_client_secret = data.client_secret;
        stripe_payment(stripe_client_secret);
        csrfName = data.csrfName;
        csrfHash = data.csrfHash;
      },
      "json"
    );
  });
});
function stripe_setup(key) {
  var stripe = Stripe(key);
  var elements = stripe.elements();
  var style = {
    base: {
      color: "#32325d",
      fontFamily: "Arial, sans-serif",
      fontSmoothing: "antialiased",
      fontSize: "16px",
      "::placeholder": {
        color: "#32325d",
      },
    },
    invalid: {
      fontFamily: "Arial, sans-serif",
      color: "#fa755a",
      iconColor: "#fa755a",
    },
  };
  var card = elements.create("card", {
    style: style,
  });
  card.mount("#stripe-card-element");
  card.on("change", function (event) {
    document.querySelector("button").disabled = event.empty;
    document.querySelector("#card-error").textContent = event.error
      ? event.error.message
      : "";
  });
  return {
    stripe: stripe,
    card: card,
  };
}
function stripe_payment(clientSecret) {
  stripe1.stripe
    .confirmCardPayment(clientSecret, {
      payment_method: {
        card: stripe1.card,
      },
    })
    .then(function (result) {
      if (result.error) {
        var errorMsg = document.querySelector("#card-error");
        errorMsg.textContent = result.error.message;
        setTimeout(function () {
          errorMsg.textContent = "";
        }, 4000);
        $("#buy").attr("disabled", false).html("Buy");
        return showToastMessage(result.error.message, "error");
      } else {
        purchase_subscription().done(function (result) {
          if (result.error == false) {
            setTimeout(function () {
              location.href = baseUrl + "payment/success";
            }, 1000);
          }
        });
      }
    });
}
function setupColumnToggle(tableId, columns_name, containerId) {
  $(document).ready(function () {
    var $table = $("#" + tableId);
    function toggleColumnVisibility() {
      $(".column-toggle").each(function () {
        var field = $(this).data("field");
        // console.log(field);
        var isVisible = $(this).prop("checked");
        if (isVisible) {
          $table.bootstrapTable("showColumn", field);
        } else {
          $table.bootstrapTable("hideColumn", field);
        }
      });
    }
    // $("#columnToggleContainer").on("change", ".column-toggle", function () {
    //   toggleColumnVisibility();
    // });
    $("#apply_filter").on("click", function () {
      $("#filterDrawer").removeClass("open");
      $("#filterBackdrop").fadeOut(200);
      toggleColumnVisibility();
    });
    var container = $("#" + containerId);
    var row;
    $.each(columns_name, function (index, column) {
      if (index % 2 === 0) {
        row = $("<div>").addClass("row");
      }
      var checkbox = $("<input>")
        .attr("type", "checkbox")
        .addClass("column-toggle")
        .data("field", column.field)
        .prop("checked", column.visible !== false);
      var label = $("<label>")
        .append(checkbox)
        .append(" " + column.label);
      var columnDiv = $("<div>").addClass("col-md-6");
      columnDiv.append(label);
      row.append(columnDiv);
      container.append(row);
    });
    toggleColumnVisibility();
  });
}
function for_drawer(buttonId, drawerId, backdropId, cancelButtonId) {
  $(buttonId).click(function () {
    $(drawerId).toggleClass("open");
    $(backdropId).fadeToggle(220);
  });
  $(cancelButtonId).click(function () {
    $(drawerId).removeClass("open");
    $(backdropId).fadeOut(200);
  });
}
// Convert a composite/image cell (e.g. partner_profile: <img> + name/email
// blocks) into clean plain text. Removes images/icons/media so exports never
// contain image markup or broken binary, and separates block text so the
// remaining name/email/phone stays readable. Applied to ALL partner-panel
// exports so image exclusion is respected without per-view config.
function te_clean_cell_html(html) {
  if (html === null || typeof html === "undefined") return "";
  if (typeof html !== "string") return ("" + html).trim();
  // Fast path: no markup, nothing to strip.
  if (html.indexOf("<") === -1) return html.trim();
  var div = document.createElement("div");
  div.innerHTML = html;
  // Drop media/icon nodes entirely.
  div
    .querySelectorAll("img, svg, picture, video, canvas, i, .material-symbols-outlined")
    .forEach(function (el) {
      el.parentNode && el.parentNode.removeChild(el);
    });
  // Put a space between block-level chunks so name/company/email don't collide.
  div.querySelectorAll("div, p, br, tr, li").forEach(function (el) {
    el.appendChild(document.createTextNode(" "));
  });
  var text = div.textContent || div.innerText || "";
  return text.replace(/\s+/g, " ").trim();
}

function custome_export(type, label, table_name) {
  var selector = "#" + table_name;
  if (type === "pdf") {
    $(selector).tableExport({
      fileName: label,
      type: "pdf",
      jspdf: {
        format: "bestfit",
        margins: {
          left: 20,
          right: 10,
          top: 50,
          bottom: 20,
        },
        autotable: {
          styles: {
            overflow: "linebreak",
          },
          tableWidth: "wrap",
          tableExport: {
            onBeforeAutotable: DoBeforeAutotable,
            onCellData: DoCellData,
          },
        },
      },
    });
  } else if (type === "excel") {
    $(selector).tableExport({
      fileName: label,
      type: "excel",
      onCellHtmlData: DoCellHtmlData,
    });
  } else if (type === "csv") {
    $(selector).tableExport({
      fileName: label,
      type: "csv",
      onCellHtmlData: DoCellHtmlData,
    });
  }
}
// excel/csv: replace raw cell HTML with sanitized plain text (strips images).
function DoCellHtmlData(cell, rowIndex, colIndex, htmlData) {
  return te_clean_cell_html(htmlData);
}
// pdf (autotable): return sanitized text so image cells export as text only.
function DoCellData(cell, row, col, data) {
  return te_clean_cell_html(typeof data === "string" ? data : (cell && cell.html ? cell.html() : data));
}
function DoBeforeAutotable(table, headers, rows, AutotableSettings) { }
function doDocCreated(doc) {
  var PartName = $("#filter_party").find("option:selected").data("name");
  PartName = "WayBill Report | " + PartName + " | " + $("#filter_date").val();
  doc.text(500, 30, PartName);
}

document.addEventListener("DOMContentLoaded", function () {
  var filterBackdrop = document.getElementById("filterBackdrop");
  var drawer = document.querySelector(".drawer");
  if (filterBackdrop && drawer) {
    filterBackdrop.addEventListener("click", function () {
      drawer.classList.remove("open");
      filterBackdrop.style.display = "none";
    });
  }
});
// var filterBackdrop = document.getElementById("filterBackdrop");
// var drawer = document.querySelector(".drawer");
// filterBackdrop.addEventListener("click", function () {
//   drawer.classList.remove("open");
//   filterBackdrop.style.display = "none";
// });
$("#filter").click(function () {
  $("#filterDrawer").removeClass("open");
  $("#filterBackdrop").fadeOut(200);
});
function fetchColumns(tableId) {
  var columns = [];
  $("#" + tableId + " thead th").each(function () {
    var field = $(this).data("field");
    var label = $(this).text().trim();
    var visible = $(this).data("visible") !== false;
    columns.push({
      field: field,
      label: label,
      visible: visible,
    });
  });
  return columns;
}
function renderChatMessage(message, files) {
  let html = "";
  const totalImages = files.filter((image) => {
    const fileType = image ? image.file_type.toLowerCase() : "";
    return fileType.includes("image");
  }).length;
  files = files.filter((file) => {
    const fileType = file ? file.file_type.toLowerCase() : "";
    return fileType.includes("image");
  });
  if (message.message !== "" && totalImages === 0) {
    html += '<div class="chat-msg-text">' + message.message + "</div>";
  }
  let templateDiv;
  if (totalImages >= 5) {
    html += generateChatMessageHTML(
      message,
      files,
      "five_plus_img_div",
      totalImages
    );
  } else if (totalImages === 4) {
    html += generateChatMessageHTML(
      message,
      files,
      "four_img_div",
      totalImages
    );
  } else if (totalImages === 3) {
    html += generateChatMessageHTML(
      message,
      files,
      "three_img_div",
      totalImages
    );
  } else if (totalImages === 2) {
    html += generateChatMessageHTML(message, files, "two_img_div", totalImages);
  } else if (totalImages === 1) {
    html += generateSingleImageHTML(message, files);
  }
  return html;
}
function generateChatMessageHTML(message, files, templateClass, totalImages) {
  let templateDivHTML = '<div class="chat-msg-text">';
  let templateDiv = $(`.${templateClass}`).clone().removeClass("d-none");
  let templateDiv1 = $("<div></div>");
  let imageLimit =
    templateClass === "five_plus_img_div" ? 5 : templateClass.split("_")[0];
  if (imageLimit == "two") {
    imageLimit = 2;
  } else if (imageLimit == "three") {
    imageLimit = 3;
  } else if (imageLimit == "four") {
    imageLimit = 4;
  }
  $.each(files, function (index, value) {
    if (index < imageLimit) {
      templateDiv.find("img").eq(index).attr("src", value.file);
      templateDiv.find("a").eq(index).attr("href", value.file);
    }
  });
  if (totalImages > imageLimit) {
    templateDiv.find(".img_count").removeClass("d-none");
    let countFile = totalImages - imageLimit;
    templateDiv.find(".img_count").html(`<h2>+${countFile}</h2>`);
    $(document).on("click", ".img_count", function () {
      const images = files.map(
        (
          file
        ) => `<div class="col-md-3"><a href="${file.file}" data-lightbox="image-1"><img height="200px" width="200px" style="    padding: 8px;
      border-radius: 11px;
      box-shadow: rgba(99, 99, 99, 0.2) 0px 2px 8px 0px;
      margin: 8px;" src="${file.file}" alt=""></a></div>`
      );
      const rowHtml = `<div class="row">${images.join("")}</div>`;
      $("#imageContainer").html(rowHtml);
      $("#imageModal").modal("show");
    });
  }
  if (message.message !== "") {
    templateDiv1.append(
      '<div style="display: block;">' + message.message + "</div>"
    );
  }
  templateDivHTML += templateDiv.prop("outerHTML");
  templateDivHTML += templateDiv1.prop("outerHTML");
  templateDivHTML += "</div>";
  return templateDivHTML;
}
function generateSingleImageHTML(message, files) {
  let html = "";
  $.each(files, function (index, value) {
    if (index < 1) {
      html += '<div class="chat-msg-text">';
      html +=
        '<a href="' +
        value.file +
        '" data-lightbox="image-1"><img height="80px" src="' +
        value.file +
        '" alt=""></a>';
      if (message.message !== "") {
        html += '<div class="">' + message.message + "</div>";
      }
      html += "</div>";
    }
  });
  return html;
}
function generateFileHTML(file) {
  var html = "";
  if (file && file.file) {
    var fileName = file.file.substring(file.file.lastIndexOf("/") + 1);
    var fileType = file.file_type ? file.file_type.toLowerCase() : "";
    if (
      fileType.includes("excel") ||
      fileType.includes("word") ||
      fileType.includes("text") ||
      fileType.includes("zip") ||
      fileType.includes("sql") ||
      fileType.includes("php") ||
      fileType.includes("json") ||
      fileType.includes("doc") ||
      fileType.includes("octet-stream") ||
      fileType.includes("pdf")
    ) {
      html += '<div class="chat-msg-text">';
      html +=
        '<a href="' +
        file.file +
        '" download="' +
        fileName +
        '" class="text-white">' +
        fileName +
        "</a>";
      html += '<i class="fa-solid fa-circle-down text-white ml-2"></i>';
      html += "</div>";
    } else if (fileType.includes("video")) {
      html += '<div class="chat-msg-text ">';
      html +=
        '<video controls class="w-100 h-100" style="height:200px!important;;width:200px!important;">';
      html +=
        '<source src="' +
        file.file +
        '" type="' +
        fileType +
        '" class="text-white">';
      html += '<i class="fa-solid fa-circle-down text-white ml-2"></i>';
      html += "</video>";
      html += "</div>";
    }
  }
  return html;
}
function renderMessage(message, currentUserId) {
  var html = "";
  var messageDate = new Date(message.created_at);
  var messageDateStr = "";
  if (
    !lastDisplayedDate ||
    messageDate.toDateString() !== lastDisplayedDate.toDateString()
  ) {
    messageDateStr = getMessageDateHeading(messageDate);
    lastDisplayedDate = messageDate;
  }
  html += messageDateStr;
  var messageClass = message.sender_id == currentUserId ? "owner" : "";
  html += '<div class="chat-msg ' + messageClass + '">';
  
  html += '<div class="chat-msg-content">';
  const chatMessageHTML = renderChatMessage(message, message.file);
  html += chatMessageHTML;
  if (message.file && message.file.length > 0) {
    message.file.forEach(function (file) {
      html += generateFileHTML(file);
    });
  }

  // Profile (avatar + date) below the message bubble
  html += '<div class="chat-msg-profile">';
  if (message.sender_id != currentUserId) {
    html +=
      '<img class="chat-msg-img" src="' + message.profile_image + '" alt="" />';
  }
  let createdAt = new Date(message.created_at);
  if (message.receiver_id != currentUserId) {
    let hours = createdAt.getHours();
    let minutes = createdAt.getMinutes();
    let ampm = hours >= 12 ? "PM" : "AM";
    hours = hours % 12;
    hours = hours ? hours : 12;
    minutes = minutes < 10 ? "0" + minutes : minutes;
    let formattedTime = hours + ":" + minutes + " " + ampm;
    let displayMessage = formattedTime;
    html += '<div class="chat-msg-date">' + displayMessage + "</div>";
  } else {
    let hours = createdAt.getHours();
    let minutes = createdAt.getMinutes();
    let ampm = hours >= 12 ? "PM" : "AM";
    hours = hours % 12;
    hours = hours ? hours : 12;
    minutes = minutes < 10 ? "0" + minutes : minutes;
    let formattedTime = hours + ":" + minutes + " " + ampm;
    let displayMessage = formattedTime;
    html += '<div class="chat-msg-date">' + displayMessage + "</div>";
  }
  html += "</div>";

  html += "</div>";
  html += "</div>";
  return html;
}
function openBookingChat(bookingId, sender_id, receiver_id) {
  window.location.href =
    baseUrl + "/partner/provider-booking-chats/" + bookingId;
}

$(document).ready(function () {
  // Commented out for testing — being replaced by detaching dropdown menus
  // to <body> (position:fixed) instead of forcing extra table-body height.
  // See app/Views/backend/partner/pages/handymen.php for the new approach.
  /*
  function patchSingleRowTableHeight() {
    $('.fixed-table-body').each(function () {
      var $body = $(this);
      if ($body.closest('.bootstrap-table').find('#invoice_table').length) {
        return;
      }
      // Count visible rows in tbody
      var $rows = $body.find('tbody tr:visible');
      if ($rows.length === 1) {
        // Set a minimum height (adjust as needed, e.g., 150px)
        $body.css('min-height', '280px');
      } else {
        // Reset min-height if more than one row
        $body.css('min-height', '');
      }
    });
  }

  // Run on page load
  patchSingleRowTableHeight();

  // If tables are reloaded via AJAX or Bootstrap Table events, re-apply the patch
  $(document).on('post-body.bs.table', function () {
    patchSingleRowTableHeight();
  });
  */

  // Bootstrap-table operations dropdowns: .fixed-table-body has
  // overflow-y:auto, so a menu still parented inside it stays confined to
  // that scrollable box no matter its z-index. Detach it to <body> with
  // position:fixed while open, positioned from the toggle button's real
  // screen coordinates — that escapes the table container entirely.
  // Popper.js (used internally by Bootstrap 4 dropdowns) positions the menu
  // AFTER show.bs.dropdown fires, and flips it upward with its own
  // transform when the toggle is close to the viewport bottom — that
  // leftover transform stacks on top of our fixed top/right, throwing the
  // menu far from the trigger for rows near the bottom of the table.
  // Reasserting on shown.bs.dropdown (once Popper is done) fixes it.
  $(document).on('show.bs.dropdown', '.bootstrap-table .dropdown', function () {
    var $dropdown = $(this);
    if ($dropdown.hasClass('is-detached')) return;

    var $toggle = $dropdown.find('[data-toggle="dropdown"]').first();
    var $menu = $dropdown.find('.dropdown-menu').first();
    if (!$toggle.length || !$menu.length) return;

    // Completely disable Popper.js for this dropdown to stop it from fighting our positioning
    $toggle.attr('data-display', 'static');

    // Prevent the menu from rendering inside the table for 1 frame (which shifts RTL tables!)
    // We force display:none !important to override Bootstrap's .show class temporarily
    $menu[0].style.setProperty('display', 'none', 'important');

    // We must wait for Bootstrap's internal show event sequence to finish
    // before we detach the menu, otherwise Bootstrap 4 crashes because it expects the menu
    // to be in the DOM relative to the toggle.
    setTimeout(function() {
      $dropdown.data('detached-menu', $menu);
      $dropdown.addClass('is-detached');
      
      // Detach and append to body
      $menu.appendTo('body');
      
      // Clear the temporary inline display:none so we can measure it
      $menu[0].style.removeProperty('display');
      
      // Ensure it is visible, but hidden from screen while we position it
      $menu.addClass('show');
      $menu.css({
        'position': 'absolute',
        'visibility': 'hidden',
        'display': 'block'
      });

      var $actualButton = $toggle.children().length > 0 ? $toggle.children().first() : $toggle;
      var offset = $actualButton.offset();
      var menuWidth = $menu.outerWidth();
      var menuHeight = $menu.outerHeight();

      var topPos = offset.top + $actualButton.outerHeight();
      var leftPos;
      
      var isRTL = $('html').attr('dir') === 'rtl' || $('body').attr('dir') === 'rtl';

      if (isRTL) {
          // In RTL, align the left edge of the menu with the left edge of the button
          leftPos = offset.left;
      } else {
          // In LTR, align the right edge of the menu with the right edge of the button
          leftPos = offset.left + $actualButton.outerWidth() - menuWidth;
      }

      // Flip up if it goes out the bottom
      if (topPos + menuHeight > $(window).scrollTop() + $(window).height()) {
          topPos = offset.top - menuHeight;
      }
      
      // Screen edge boundary checks
      if (leftPos < 0) {
          leftPos = 10;
      } else if (leftPos + menuWidth > $(window).width()) {
          leftPos = $(window).width() - menuWidth - 10;
      }

      $menu.css({
        'visibility': 'visible',
        'display': 'block',
        'position': 'absolute',
        'top': topPos + 'px',
        'left': leftPos + 'px',
        'right': 'auto',
        'bottom': 'auto',
        'transform': 'none',
        'margin': '0',
        'z-index': 1060
      });

      // Force with !important to prevent Popper from overriding if it somehow triggers
      $menu[0].style.setProperty('position', 'absolute', 'important');
      $menu[0].style.setProperty('top', topPos + 'px', 'important');
      $menu[0].style.setProperty('left', leftPos + 'px', 'important');
      $menu[0].style.setProperty('right', 'auto', 'important');
      $menu[0].style.setProperty('bottom', 'auto', 'important');
      $menu[0].style.setProperty('margin', '0', 'important');
      $menu[0].style.setProperty('transform', 'none', 'important');
      $menu[0].style.setProperty('z-index', '1060', 'important');
    }, 0);
  });

  $(document).on('hide.bs.dropdown', '.bootstrap-table .dropdown', function () {
    var $dropdown = $(this);
    var $menu = $dropdown.data('detached-menu');
    if ($menu) {
      $dropdown.removeClass('is-detached');
      $menu.removeClass('show');
      $menu.css({
        'display': '',
        'position': '',
        'top': '',
        'left': '',
        'right': '',
        'bottom': '',
        'transform': '',
        'margin': '',
        'z-index': ''
      });
      $dropdown.append($menu.detach());
      $dropdown.removeData('detached-menu');
    }
  });

  $(document).on('post-body.bs.table', function () {
    $('body > .dropdown-menu').remove();
  });

  $(document).on('scroll', '.fixed-table-body', function() {
    if ($('body > .dropdown-menu.show').length > 0) {
      $(document).trigger('click');
    }
  });
});

