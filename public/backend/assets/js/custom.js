"use strict";
$(document).ready(function () {
  $("#loading").hide();
});
function showToastMessage(message, type) {
  switch (type) {
    case "error":
      $().ready(
        iziToast.error({
          title: "",
          message: message,
          position: "topRight",
          pauseOnHover: true,
          timeout: 5000, // Auto-close after 5 seconds
          progressBar: true, // Show progress bar
        })
      );
      break;
    case "success":
      $().ready(
        iziToast.success({
          title: "",
          message: message,
          position: "topRight",
          timeout: 3000, // Auto-close after 3 seconds
          progressBar: true, // Show progress bar
        })
      );
      break;
  }
}
$(document).on("submit", ".add-provider-with-subscription", function (e) {
  e.preventDefault();
  var formData = new FormData(this);
  var form_id = $(this).attr("id");
  var error_box = $("#error_box", this);
  var submit_btn = $(this).find(".submit_btn");
  var btn_html = $(this).find(".submit_btn").html();
  var btn_val = $(this).find(".submit_btn").val();
  var button_text =
    btn_html != "" || btn_html != "undefined" ? btn_html : btn_val;
  // password section for system users
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
      submit_btn.prop("disabled", true);
      submit_btn.removeClass("btn-primary");
      submit_btn.addClass("btn-secondary");
      submit_btn.html(
        '<div class="spinner-border text-light spinner-border-sm mx-3" role="status"><span class="visually-hidden"></span></div>'
      );
    },
    success: function (response) {
      csrfName = response["csrfName"];
      csrfHash = response["csrfHash"];
      if (response.error == false) {
        submit_btn.html(button_text);
        Swal.fire({
          title: response.message,
          text: do_you_want_to_assign_subscription_plan,
          icon: "success",
          showCancelButton: true,
          confirmButtonColor: "#3085d6",
          cancelButtonColor: "#d33",
          confirmButtonText: yes,
          cancelButtonText: no,
          didOpen: () => {
            $('input[name="partner_id"]').val(response.data.partner_id);
          },
        }).then((result) => {
          if (result.isConfirmed) {
            var partner_id = response.data.partner_id;
            window.location.href =
              baseUrl + "/admin/partners/partner_subscription/" + partner_id;
          } else {
            window.location.href = baseUrl + "/admin/partners";
          }
        });
        $("form#" + form_id).trigger("reset");
        $(".close").click();
        $("#user_list").bootstrapTable("refresh");
        $("#slider_list").bootstrapTable("refresh");
      } else {

        // Handle errors: check for 'errors' array first for step-by-step display
        // This provides a better UX by showing each validation error individually
        // Matches the behavior used for sliders and other forms
        if (response.errors && Array.isArray(response.errors) && response.errors.length > 0) {
          // Display each error one by one with a small delay for better readability
          // This prevents overwhelming the user with all errors at once
          response.errors.forEach(function (error, index) {
            setTimeout(function () {
              showToastMessage(error, "error");
            }, index * 500); // 500ms delay between each error message
          });
        } else if (
          typeof response.message === "object" &&
          !Array.isArray(response.message) &&
          response.message !== null
        ) {
          // Handle object errors (associative array from validation) - convert to array and show individually
          // This handles cases where errors come as an object instead of an array
          var errorArray = [];
          for (var k in response.message) {
            if (response.message.hasOwnProperty(k)) {
              errorArray.push(response.message[k]);
            }
          }
          // Display each error one by one with a small delay
          if (errorArray.length > 0) {
            errorArray.forEach(function (error, index) {
              setTimeout(function () {
                showToastMessage(error, "error");
              }, index * 500); // 500ms delay between each error message
            });
          }
        } else if (typeof response.message === "string" && response.message.includes(",")) {
          // Handle case where message is a comma-separated string of errors
          // Split by comma and show each error individually
          var errorMessages = response.message.split(",").map(function (msg) {
            return msg.trim();
          }).filter(function (msg) {
            return msg.length > 0;
          });
          if (errorMessages.length > 0) {
            errorMessages.forEach(function (error, index) {
              setTimeout(function () {
                showToastMessage(error, "error");
              }, index * 500); // 500ms delay between each error message
            });
          }
        } else {
          // Single error message
          showToastMessage(response.message, "error");
        }
        submit_btn.attr("disabled", false);
        submit_btn.html(button_text);
        $("#update_modal").bootstrapTable("refresh");
      }
    },
  });
});
function displaySubscriptionModal() {
  setTimeout(function () {
    $("#partner_subscriptions_add").modal("show");
  }, 200);
}
if ($(".summernotes").length) {
  tinymce.init({
    selector: ".summernotes",
    height: 200,
    menubar: true,
    plugins: [
      "advlist",
      "autolink",
      "lists",
      "link",
      "image",
      "charmap",
      "preview",
      "code",
      "anchor",
      "searchreplace",
      "visualblocks",
      "fullscreen",
      "insertdatetime",
      "media",
      "directionality",
      "table",
      "help",
      "wordcount",
      "imagetools",
    ],
    toolbar:
      "undo redo | image media | code fullscreen| formatpainter casechange blocks fontsize | bold italic forecolor backcolor | " +
      "alignleft aligncenter alignright alignjustify | " +
      "bullist numlist checklist outdent indent | removeformat | ltr rtl |a11ycheck table help",
    maxlength: null, // Remove text limit
    relative_urls: false,
    remove_script_host: false,
    document_base_url: baseUrl,
    file_picker_callback: function (callback, value, meta) {
      if (meta.filetype == "media" || meta.filetype == "image") {
        const input = document.createElement("input");
        input.setAttribute("type", "file");
        input.setAttribute("accept", "image/* audio/* video/*");
        input.addEventListener("change", (e) => {
          const file = e.target.files[0];
          var reader = new FileReader();
          var fd = new FormData();
          var files = file;
          fd.append("documents[]", files);
          fd.append("filetype", meta.filetype);
          fd.append(csrfName, csrfHash);
          var filename = "";
          jQuery.ajax({
            url: baseUrl + "/admin/media/upload",
            type: "post",
            data: fd,
            contentType: false,
            processData: false,
            async: false,
            success: function (response) {
              filename = response.file_name;
            },
          });
          reader.onload = function (e) {
            const imageUrl = baseUrl + "/public/uploads/media/" + filename;
            callback(imageUrl.replace(/&quot;/g, ""));
          };
          reader.readAsDataURL(file);
        });
        input.click();
      }
    },
    image_uploadtab: true,
  });
}
function comming_soon(element) { }
$(document).ready(function () {
  var check_box = $(".check_box");
  var start_time = $(".start_time");
  var end_time = $(".end_time");
  $(".check_box").on("click", function () {
    for (let index = 0; index < check_box.length; index++) {
      if (!$(check_box[index]).is(":checked")) {
        $(start_time[index]).attr("readOnly", "readOnly");
        $(end_time[index]).attr("readOnly", "readOnly");
      } else {
        $(start_time[index]).removeAttr("readOnly");
        $(end_time[index]).removeAttr("readOnly");
      }
    }
  });
  for (let index = 0; index < check_box.length; index++) {
    if (!$(check_box[index]).is(":checked")) {
      $(start_time[index]).attr("readOnly", "readOnly");
      $(end_time[index]).attr("readOnly", "readOnly");
    } else {
      $(start_time[index]).removeAttr("readOnly");
      $(end_time[index]).removeAttr("readOnly");
    }
  }
});
// Order filter variables, handlers, and orders_query function moved to inline script in orders.php for auto-refresh functionality
function language_query(p) {
  return {
    search: $("#customSearch").val() ? $("#customSearch").val() : p.search,
    limit: p.limit,
    sort: p.sort,
    order: p.order,
    offset: p.offset,
  };
}
// Filter click handler removed - now handled inline in orders.php with auto-refresh
function fetch_cites(element) {
  $.ajax({
    type: "POST",
    url: "delete_details",
    data: {
      id: $(element).data("id"),
    },
    dataType: "json",
    success: function (result) {
      csrfName = result.csrfName;
      csrfHash = result.csrfHash;
      if (result.error == false) {
        iziToast.success({
          title: "",
          message: result.message,
          position: "topRight",
        });
        var tableId = $(element).data("table-id");
      } else {
        iziToast.error({
          title: "",
          message: result.message,
          position: "topRight",
        });
      }
    },
  });
}
function delete_details(element) {
  $.ajax({
    type: "POST",
    url: "delete_details",
    data: {
      id: $(element).data("id"),
      table: $(element).data("table"),
      csrf_test_name: csrfHash,
    },
    dataType: "json",
    success: function (result) {
      csrfName = result.csrfName;
      csrfHash = result.csrfHash;
      if (result.error == false) {
        iziToast.success({
          title: "",
          message: result.message,
          position: "topRight",
        });
        var tableId = $(element).data("table-id");
        $("#" + tableId).bootstrapTable("refresh");
      } else {
        iziToast.error({
          title: "",
          message: result.message,
          position: "topRight",
        });
      }
    },
  });
}
function set_locale(language_code) {
  $.ajax({
    url: baseUrl + "/lang/" + language_code,
    type: "GET",
    dataType: "json",
    success: function (result) {
      var is_rtl = result.is_rtl;
      var language = result.language;
      localStorage.setItem("is_rtl", JSON.stringify(is_rtl));
      localStorage.setItem("language", JSON.stringify(language));
      location.reload();
    },
    error: function (xhr, status, error) {
      console.error("Failed to fetch language details.", status, error);
      location.reload();
    },
  });
}

$(".delete-language-btn").on("click", function (e) {
  e.preventDefault();
  if (confirm("Are you sure want to delete language?")) {
    window.location.href = $(this).attr("href");
  }
});
function active_sub(element) {
  $("#user_id").val($(element).data("uid"));
  $("#id").val($(element).data("sid"));
}
function receipt_check(element) {
  $("#bank_transfer_id").val($(element).data("id"));
  $("#user_id").val($(element).data("uid"));
}
function activate_user(element) {
  $("#user_id_active").val($(element).data("uid"));
}
function deactivate_user(element) {
  $("#user_id").val($(element).data("uid"));
}
$(document).ready(function () {
  $("#deactivate_user_form").on("submit", function (e) {
    e.preventDefault();
    let formdata = new FormData(this);
    formdata.append(csrfName, csrfHash);
    $.ajax({
      type: $(this).attr("method"),
      url: $(this).attr("action"),
      data: formdata,
      dataType: "json",
      cache: false,
      beforeSend: function () {
        $("#deactive_btn").attr("disabled", true);
        $("#deactive_btn").html("Deactivating.. .");
      },
      processData: false,
      contentType: false,
      success: function (response) {
        if (response.error == false) {
          iziToast.success({
            title: "",
            message: response.message,
            position: "topRight",
          });
          $("#deactive_btn").attr("disabled", false);
          $("#deactive_btn").html("Deactivate User");
          $(".close").click();
          $("#user_list").bootstrapTable("refresh");
        } else {
          iziToast.error({
            title: "",
            message: response.message,
            position: "topRight",
          });
          $(".close").click();
          window.location.reload();
        }
      },
    });
  });
});
$(document).ready(function () {
  $("#activate_user_form").on("submit", function (e) {
    e.preventDefault();
    let formdata = new FormData(this);
    formdata.append(csrfName, csrfHash);
    $.ajax({
      type: $(this).attr("method"),
      url: $(this).attr("action"),
      data: formdata,
      dataType: "json",
      cache: false,
      beforeSend: function () {
        $("#activate_btn").attr("disabled", true);
        $("#activate_btn").html("Activating.. .");
      },
      processData: false,
      contentType: false,
      success: function (response) {
        if (response.error == false) {
          iziToast.success({
            title: "",
            message: response.message,
            position: "topRight",
          });
          $("#activate_btn").attr("disabled", false);
          $("#activate_btn").html("Activated...");
          $(".close").click();
          $("#user_list").bootstrapTable("refresh");
        } else {
          iziToast.error({
            title: "",
            message: response.message,
            position: "topRight",
          });
          $(".close").click();
          window.location.reload();
        }
      },
    });
  });
});
$(document).ready(function () {
  $("#update_category_process").on("submit", function (e) {
    e.preventDefault();
    let formdata = new FormData($(this)[0]);
    formdata.append(csrfName, csrfHash);
    var name = $("#name").val();
    $.ajax({
      type: $(this).attr("method"),
      url: $(this).attr("action"),
      data: formdata,
      dataType: "json",
      processData: false,
      contentType: false,
      beforeSend: function () {
        $("#Category_btn").attr("disabled", true);
        $("#Category_btn").html("Adding.. .");
      },
      success: function (response) {
        if (response.error == false) {
          iziToast.success({
            title: "",
            message: response.message,
            position: "topRight",
          });
          setTimeout(function () {
            location.href = baseUrl + "/admin/categories";
          }, 500);
        } else {
          iziToast.error({
            title: "",
            message: response.message,
            position: "topRight",
          });
          setTimeout(function () {
            location.href = baseUrl + "admin/categories";
          }, 500);
        }
      },
    });
  });
});
$(document).ready(function () {
  // if ($("#password") != null && $("#confirm_password") != null) {
  //   $("#confirm_password").on("blur", function (e) {
  //     if ($("#password").val() == "") {
  //       $("#password").css("border-color", "#FF3300");
  //       showToastMessage(empty_password, "error");
  //       return false;
  //     }
  //   });
  //   $("#confirm_password").on("blur", function (e) {
  //     if ($("#confirm_password").val() == "") {
  //       $("#password").css("border-color", "#FF3300");
  //       $("#confirm_password").css("border-color", "#FF3300");
  //       showToastMessage(empty_confirm_password, "error");
  //       return false;
  //     } else if ($("#password").val() != $("#confirm_password").val()) {
  //       e.preventDefault();
  //       $("#password").css("border-color", "#FF3300");
  //       $("#confirm_password").css("border-color", "#FF3300");
  //       showToastMessage(password_mismatch, "error");
  //       return false;
  //     } else {
  //       $("#password").css("border-color", "#66FF00");
  //       $("#confirm_password").css("border-color", "#66FF00");
  //       return true;
  //     }
  //   });
  // }
  $(document).on("submit", ".form-submit-event", function (e) {
    e.preventDefault();
    // Sync TinyMCE editor content into textareas before building FormData.
    // Otherwise FormData captures empty/minimal textarea values (e.g. send_emails template with images).
    if (typeof tinymce !== "undefined") {
      tinymce.triggerSave();
    }
    var formData = new FormData(this);
    var form_id = $(this).attr("id");

    // Ensure send_email form sends template with full HTML (including img tags) as-is.
    // Explicitly set template from TinyMCE editor so options.message is not missing content.
    if (form_id === "send_email") {
      var sendEmailEditor = typeof tinymce !== "undefined" && tinymce.get("send_email_template");
      if (sendEmailEditor) {
        formData.set("template", sendEmailEditor.getContent());
      }
    }

    var error_box = $("#error_box", this);
    var submit_btn = $(this).find(".submit_btn");
    var btn_html = $(this).find(".submit_btn").html();
    var btn_val = $(this).find(".submit_btn").val();
    var button_text =
      btn_html != "" || btn_html != "undefined" ? btn_html : btn_val;

    // Client-side validation for notification form: check if user is selected when "Specific User" is chosen
    // This prevents infinite loading when user tries to submit without selecting a user
    if (form_id === "add_notification") {
      var userType = $("#user_type").val();
      if (userType === "specific_user") {
        var selectedUsers = $("#users").val();
        // Check if no users are selected (empty array or null)
        if (!selectedUsers || selectedUsers.length === 0) {
          // Show error message (server-side validation will provide translated message if this is bypassed)
          showToastMessage('Please select at least one user', "error");
          // Re-enable submit button since we're preventing submission
          submit_btn.prop("disabled", false);
          submit_btn.removeClass("btn-secondary");
          submit_btn.addClass("btn-primary");
          submit_btn.html(button_text);
          return false; // Prevent form submission
        }
      }
    }

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
        submit_btn.prop("disabled", true);
        submit_btn.removeClass("btn-primary");
        submit_btn.addClass("btn-secondary");
        submit_btn.html(
          '<div class="spinner-border text-light spinner-border-sm mx-3" role="status"><span class="visually-hidden"></span></div>'
        );
      },
      success: function (response) {
        csrfName = response["csrfName"];
        csrfHash = response["csrfHash"];
        if (response.error == false) {
          showToastMessage(response.message, "success");

          // Track Microsoft Clarity events if event data is present in response
          if (response.data && response.data.clarity_event) {
            var eventType = response.data.clarity_event;
            if (eventType === 'service_created' && typeof trackServiceCreated === 'function') {
              trackServiceCreated(
                response.data.service_id,
                response.data.service_name,
                response.data.service_price,
                response.data.category_id,
                response.data.category_name
              );
            } else if (eventType === 'service_updated' && typeof trackServiceUpdated === 'function') {
              trackServiceUpdated(
                response.data.service_id,
                response.data.service_name
              );
            } else if (eventType === 'service_deleted' && typeof trackServiceDeleted === 'function') {
              trackServiceDeleted(response.data.service_id);
            } else if (eventType === 'promocode_created' && typeof trackPromocodeCreated === 'function') {
              trackPromocodeCreated(
                response.data.promocode_id,
                response.data.promocode_name,
                response.data.discount,
                response.data.discount_type
              );
            } else if (eventType === 'promocode_updated' && typeof trackPromocodeUpdated === 'function') {
              trackPromocodeUpdated(
                response.data.promocode_id,
                response.data.promocode_name
              );
            } else if (eventType === 'promocode_deleted' && typeof trackPromocodeDeleted === 'function') {
              trackPromocodeDeleted(response.data.promocode_id);
            } else if (eventType === 'withdrawal_request_sent' && typeof trackWithdrawalRequestSent === 'function') {
              trackWithdrawalRequestSent(
                response.data.amount,
                response.data.payment_address,
                response.data.remaining_balance
              );
            } else if (eventType === 'profile_updated' && typeof trackProfileUpdated === 'function') {
              trackProfileUpdated(
                response.data.provider_id,
                response.data.company_name
              );
            } else if (eventType === 'custom_job_applied' && typeof trackCustomJobApplied === 'function') {
              trackCustomJobApplied(
                response.data.job_request_id,
                response.data.counter_price,
                response.data.duration
              );
            }
          }

          // Check if response has reload flag and reload the page
          if (response.reload === true) {
            location.reload();
            return; // Exit early to prevent other actions
          }

          // Redirect admins when the backend explicitly asks for it so they land on the relevant listing view right after saving.
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
            submit_btn.html(button_text);
            submit_btn.attr("disabled", false);
            setTimeout(function () {
              window.location.href = response.redirect_url;
            }, 3000);
            return;
          }

          $("form#" + form_id).trigger("reset");
          submit_btn.html(button_text);
          $(".close").click();
          $("#user_list").bootstrapTable("refresh");
          $("#category_list").bootstrapTable("refresh");
          $("#language_list").bootstrapTable("refresh");

          $("#slider_list").bootstrapTable("refresh");

          // Data-driven table refresh: forms can declare which Bootstrap Tables
          // to refresh after a successful submit via `data-refresh-table` (one
          // or more comma-separated jQuery selectors). Keeps generic handler
          // free of per-page hardcoded table IDs.
          var refreshTargets = $("#" + form_id).data("refresh-table");
          if (refreshTargets) {
            String(refreshTargets)
              .split(",")
              .map(function (sel) { return sel.trim(); })
              .filter(Boolean)
              .forEach(function (sel) {
                var $table = $(sel);
                if ($table.length && typeof $table.bootstrapTable === "function") {
                  $table.bootstrapTable("refresh");
                }
              });
          }

          // Refresh email list table after sending email notification
          if (form_id === "send_email") {
            $("#email_list").bootstrapTable("refresh");
          }

          // Refresh language dropdown if we're on the languages page
          if (typeof refreshLanguageDropdown === 'function') {
            refreshLanguageDropdown();
          }
          $("#update_modal").modal("hide");

          submit_btn.attr("disabled", false);
          // Call the function for each class
          removeFilesFromClass("filepond");
          removeFilesFromClass("filepond-docs");
          removeFilesFromClass("filepond-excel");
          removeFilesFromClass("filepond-only-images-and-videos");

          $("select").val(false).trigger("change");

          // For category creation we refresh the entire view after a short delay
          // so the left-hand form and right-hand list stay perfectly in sync.
          if (form_id === "add_Category") {
            setTimeout(function () {
              window.location.reload();
            }, 1200);
          }
        } else {
          // Handle errors: check for 'errors' array first for step-by-step display
          // This provides a better UX by showing each validation error individually
          // Applies to provider add and send_notification forms
          if (response.errors && Array.isArray(response.errors) && response.errors.length > 0) {
            // Display each error one by one with a small delay for better readability
            // This prevents overwhelming the user with all errors at once
            response.errors.forEach(function (error, index) {
              setTimeout(function () {
                showToastMessage(error, "error");
              }, index * 500); // 500ms delay between each error message
            });
          } else if (
            typeof response.message === "object" &&
            !Array.isArray(response.message) &&
            response.message !== null
          ) {
            // Handle object errors (associative array from validation)
            for (var k in response.message) {
              if (response.message.hasOwnProperty(k)) {
                showToastMessage(response.message[k], "error");
              }
            }
          } else {
            // Single error message
            showToastMessage(response.message, "error");
          }
          submit_btn.attr("disabled", false);
          submit_btn.html(button_text);
          $("#update_modal").bootstrapTable("refresh");
        }
      },
    });
  });
  $(document).on("submit", ".for-payment-request-form-submit-event", function (e) {
    e.preventDefault();
    const $form = $(this);
    const formData = new FormData(this);

    const $submit_btn = $form.find(".submit_btn");
    const originalBtnHtml = $submit_btn.html();
    const button_text = $.trim(originalBtnHtml) !== "" ? originalBtnHtml : $submit_btn.val();
    const originalBtnClasses = $submit_btn.attr("class") || "";

    // CSRF handling (if variables exist globally)
    if (typeof csrfName !== "undefined" && typeof csrfHash !== "undefined") {
      formData.append(csrfName, csrfHash);
    }

    $.ajax({
      type: "POST",
      url: $form.attr("action"),
      data: formData,
      cache: false,
      contentType: false,
      processData: false,
      dataType: "json",
      beforeSend: function () {
        $submit_btn.prop("disabled", true)
          .removeClass("btn-success")
          .addClass("btn-secondary")
          .html('<div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden"></span></div>');
      },
      success: function (response) {
        if (response && response.csrfName && response.csrfHash) {
          csrfName = response.csrfName;
          csrfHash = response.csrfHash;
        }

        if (response.error === false) {
          showToastMessage(response.message, "success");
          $form.trigger("reset");

          // Close the correct modal
          $("#edit_modal").modal("hide");

          // Refresh only relevant table
          $("#payment_request_list").bootstrapTable("refresh");
        } else {
          if (typeof response.message === "object" && response.message !== null) {
            for (let k in response.message) {
              if (response.message.hasOwnProperty(k)) {
                showToastMessage(response.message[k], "error");
              }
            }
          } else {
            showToastMessage(response.message || "Something went wrong", "error");
          }
        }
      },
      error: function (xhr, status, err) {
        showToastMessage("Request failed: " + (err || status), "error");
      },
      complete: function () {
        $submit_btn.prop("disabled", false)
          .attr("class", originalBtnClasses)
          .html(button_text);
      }
    });
  });

  $(document).on("submit", ".update-form", function (e) {
    e.preventDefault();
    var formData = new FormData(this);
    var form_id = $(this).attr("id");
    var error_box = $("#error_box", this);
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
        submit_btn.prop("disabled", true);
        submit_btn.removeClass("btn-primary");
        submit_btn.addClass("btn-secondary");
        submit_btn.html(
          '<div class="spinner-border text-light spinner-border-sm mx-3" role="status"><span class="visually-hidden"></span></div>'
        );
      },
      success: function (response) {
        csrfName = response["csrfName"];
        csrfHash = response["csrfHash"];
        if (response.error == false) {
          showToastMessage(response.message, "success");

          // Check if response has reload flag and reload the page
          if (response.reload === true) {
            location.reload();
            return; // Exit early to prevent other actions
          }

          // Redirect to service list page when backend explicitly asks for it
          // Added 3 second delay to allow users time to read the success toast message before redirecting
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
            submit_btn.html(button_text);
            submit_btn.attr("disabled", false);
            submit_btn.removeClass("btn-secondary");
            submit_btn.addClass("btn-primary");
            setTimeout(function () {
              window.location.href = response.redirect_url;
            }, 3000);
            return;
          }

          location.reload();
          $("form#" + form_id).trigger("reset");
          submit_btn.html(button_text);
          $(".close").click();
          $("#user_list").bootstrapTable("refresh");
          $("#category_list").bootstrapTable("refresh");

          $("#slider_list").bootstrapTable("refresh");
          $("#update_modal").modal("hide");

          submit_btn.attr("disabled", false);
          // Call the function for each class
          removeFilesFromClass("filepond");
          removeFilesFromClass("filepond-docs");
          removeFilesFromClass("filepond-excel");
          removeFilesFromClass("filepond-only-images-and-videos");

          $("select").val(false).trigger("change");
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
});
function notification_id(element) {
  $("#id").val($(element).data("id"));
  $("#did").val($(element).data("id"));
}
$("#gen-list a").on("click", function (e) {
  $(this).tab("show");
});
$(document).ready(function () { });
// Simple function to set category ID (used for delete operations and other non-edit actions)
function category_id(element) {
  var categoryId = $(element).data("id");
  if (categoryId) {
    $("#id").val(categoryId);
    $("#did").val(categoryId);
  }
}
function language_id(element) {
  $("#id").val($(element).data("id"));
  $("#did").val($(element).data("id"));
}
function template_id(element) {
  $("#template_id").val($(element).data("id"));
}
$("#categories_select1").hide();
$("#user_select").hide();
$("#provider_select").hide();
$("#category_select").hide();
$("#url").hide();
$(document).ready(function () {
  $("#type1").change(function (e) {
    if ($("#type1").val() == "general") {
      $("#categories_select").show();
      $("#provider_select").hide();
      $("#category_select").hide();
      $("#url").hide();
    }
    if ($("#type1").val() == "provider") {
      $("#provider_select").show();
      $("#categories_select").hide();
      $("#category_select").hide();
      $("#url").hide();
    } else if ($("#type1").val() == "category") {
      $("#provider_select").hide();
      $("#categories_select").hide();
      $("#category_select").show();
      $("#url").hide();
    } else if ($("#type1").val() == "url") {
      $("#provider_select").hide();
      $("#categories_select").hide();
      $("#category_select").hide();
      $("#url").show();
    } else {
      $("#provider_select").hide();
      $("#category_select").hide();
      $("#url").hide();
    }
  });
});
$(document).ready(function () {
  $("#user_type").change(function (e) {
    /**
     * Admin notifications screen rule:
     * When "Send To" is Provider, we must NOT allow "Notification Type = provider".
     *
     * Why:
     * - "Send To = provider" already targets provider users (audience).
     * - "Notification Type = provider" is used as a click-target type (provider-details).
     * - Combining both is confusing in UI and can lead to wrong payload expectations.
     *
     * Implementation notes:
     * - We remove the <option value="provider"> from #type1 when user_type=provider.
     * - If it was selected, we reset the type to "general" and hide conditional sections.
     * - When user_type changes away from provider, we restore the option if missing.
     * - This is done defensively: only if #type1 exists on the page.
     */
    (function enforceNotificationTypeRuleForProviders() {
      var $typeSelect = $("#type1");
      if ($typeSelect.length === 0) return;

      var userType = $("#user_type").val();
      var providerOptionExists = $typeSelect.find('option[value="provider"]').length > 0;

      if (userType === "provider") {
        // Remove provider option for provider audience
        if (providerOptionExists) {
          $typeSelect.find('option[value="provider"]').remove();
        }

        // If provider type was selected (e.g. before changing user_type), reset it.
        if ($typeSelect.val() === "provider") {
          $typeSelect.val("general").trigger("change");
        }

        // Always ensure provider-specific field is hidden when provider option is not available.
        $("#provider_select").hide();
      } else {
        // Restore provider option when user_type is not provider (if missing).
        if (!providerOptionExists) {
          // Insert it after "general" if present, otherwise append at the end.
          var $general = $typeSelect.find('option[value="general"]');
          var providerOptionHtml = '<option value="provider">Provider</option>';
          if ($general.length > 0) {
            $general.after(providerOptionHtml);
          } else {
            $typeSelect.append(providerOptionHtml);
          }
        }
      }

      // If select2 is active, refresh it after option changes.
      // This is safe to call even if select2 is not attached.
      try {
        $typeSelect.trigger("change.select2");
      } catch (err) {
        // no-op
      }
    })();

    if ($("#user_type").val() == "all_users") {
      $("#user_select").hide();
    } else if ($("#user_type").val() == "specific_user") {
      $("#user_select").show();
    } else if ($("#user_type").val() == "existing_user") {
      $("#user_select").hide();
      $("#email").prop("required", false);
      $("#name").prop("required", false);
      $("#mobile").prop("required", false);
      $("#password").prop("required", false);
      $("#confirm_password").prop("required", false);
    } else if ($("#user_type").val() == "new_user") {
      $("#user_select").hide();
      $("#email").prop("required", true);
      $("#name").prop("required", true);
      $("#mobile").prop("required", true);
      $("#password").prop("required", true);
      $("#confirm_password").prop("required", true);
    } else {
      $("#user_select").hide();
    }
  });
});
$("#image_checkbox").on("click", function () {
  if (this.checked) {
    $(this).prop("checked", true);
    $(".include_image").removeClass("d-none");
  } else {
    $(this).prop("checked", false);
    $(".include_image").addClass("d-none");
    // Clear FilePond instance when checkbox is unchecked
    // This prevents previously uploaded image from being included in the form submission
    var imageInput = $("#image");
    if (imageInput.length > 0) {
      // Get the FilePond instance using FilePond.find() or jQuery data
      var filepondInstance = FilePond.find(imageInput[0]);
      if (filepondInstance) {
        // Remove all files from FilePond instance
        filepondInstance.removeFiles();
      }
    }
  }
});
// Handle type dropdown change in add form to show/hide conditional fields
// Using d-none class ensures hidden elements don't take up space in the layout
$(document).ready(function () {
  $(".conditional").addClass("d-none");

  $("#type").on("change", function () {
    $(".conditional").addClass("d-none");

    let val = $(this).val();

    if (val === "Category") {
      $("#slider_categories_select").removeClass("d-none");
    }
    else if (val === "provider") {
      $("#slider_provider_select").removeClass("d-none");
    }
    else if (val === "url") {
      $("#url_section").removeClass("d-none");
    }
  });
});

function update_slider(element) {
  $("#id").val($(element).data("id"));
  $("#id").val($(element).data("id"));
}
$("#gen-list a").on("click", function (e) {
  $(this).tab("show");
});

function feature_section_id(element) {
  $("#id").val($(element).data("id"));
  $("#id").val($(element).data("id"));
}
$("#gen-list a").on("click", function (e) {
  $(this).tab("show");
});
$(document).ready(function () { });
function order_id(element) {
  $("#id").val($(element).data("id"));
}
function view_order(e) {
  var order_id = $(e).attr("data-id");
  $.post(baseUrl + "/admin/orders/view_details", {
    [csrfName]: csrfHash,
  });
}
$("#gen-list a").on("click", function (e) {
  $(this).tab("show");
});
$(document).ready(function () { });
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
              window.location.reload();
            } else {
              return showToastMessage(data.message, "error");
            }
          }
        );
      }
    });
  },
};
function services_id(element) {
  $("#id").val($(element).data("id"));
  $("#id").val($(element).data("id"));
}
$("#gen-list a").on("click", function (e) {
  $(this).tab("show");
});
$(document).ready(function () { });
window.services_events = {
  "click .delete-services": function (e, value, row, index) {
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
          baseUrl + "/admin/services/delete-services",
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
};
function promo_codes_id(element) {
  $("#id").val($(element).data("id"));
}
$("#gen-list a").on("click", function (e) {
  $(this).tab("show");
});
function readURL(input) {
  var reader = new FileReader();
  reader.onload = function (e) {
    document
      .querySelector("#service_image")
      .setAttribute("src", e.target.result);
    if (document.querySelector("#update_service_image") != null) {
      document
        .querySelector("#update_service_image")
        .setAttribute("src", e.target.result);
    }
  };
  reader.readAsDataURL(input.files[0]);
}
function readURLCategory(input) {
  var reader = new FileReader();
  reader.onload = function (e) {
    document
      .querySelector("#catgeory_image")
      .setAttribute("src", e.target.result);
    if (document.querySelector("#update_service_image") != null) {
      document
        .querySelector("#update_service_image")
        .setAttribute("src", e.target.result);
    }
  };
  reader.readAsDataURL(input.files[0]);
}
$("#section_type").on("change", function () {
  // Define the classes for each section type
  const sections = {
    partners: ".partners_ids",
    categories: ".Category_item",
    top_rated_partner: ".top_rated_providers",
    previous_order: ".previous_order",
    ongoing_order: ".ongoing_order",
    near_by_provider: ".near_by_providers",
    banner: ".banner_section",
  };
  // Get the selected value from the dropdown
  const selectedSection = $(this).val();
  // Hide all sections
  $(
    ".Category_item, .partners_ids, .top_rated_providers, .previous_order, .ongoing_order, .near_by_providers,.banner_section"
  ).addClass("d-none");
  if (selectedSection == "banner") {
    $(".title").hide();
  } else {
    $(".title").show();
    // Reset banner sub-type fields when changing away from banner
    // Hide all banner sub-type fields first
    $("#banner_providers_select").hide();
    $("#banner_categories_select").hide();
    $("#banner_url_section").hide();
    // Reset banner_type select
    $("#banner_type").val("").trigger("change");
    // Clear banner sub-type field values
    if ($("#Category_item").hasClass("select2-hidden-accessible")) {
      $("#Category_item").val(null).trigger("change.select2");
    } else {
      $("#Category_item").val("").trigger("change");
    }
    if ($("#service_item").hasClass("select2-hidden-accessible")) {
      $("#service_item").val(null).trigger("change.select2");
    } else {
      $("#service_item").val("").trigger("change");
    }
    $("#slider_url").val("");
  }
  // Show the selected section if it exists in the sections map
  if (sections[selectedSection]) {
    $(sections[selectedSection]).removeClass("d-none");
  }
});
$(
  "#banner_providers_select,#banner_categories_select,#banner_url_section"
).hide();
$("#banner_type").on("change", function () {
  if ($("#banner_type").val() == "banner_default") {
    $("#banner_providers_select").hide();
    $("#banner_categories_select").hide();
    $("#banner_url_section").hide();
  }
  if ($("#banner_type").val() == "banner_provider") {
    $("#banner_providers_select").show();
    $("#banner_categories_select").hide();
    $("#banner_url_section").hide();
  } else if ($("#banner_type").val() == "banner_category") {
    $("#banner_providers_select").hide();
    $("#banner_categories_select").show();
    $("#banner_url_section").hide();
  } else if ($("#banner_type").val() == "banner_url") {
    $("#banner_providers_select").hide();
    $("#banner_categories_select").hide();
    $("#banner_url_section").show();
  } else {
    $("#banner_providers_select").hide();
    $("#banner_categories_select").hide();
    $("#banner_url_section").hide();
  }
});
$("#category_item").on("change", function () {
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

      $("#sub_category").empty();

      if (data.error == false) {
        var sub_categories = data.data;

        sub_categories.forEach((element) => {
          Option = $("<option>", {
            value: element.id,
            text: element.name,
          });
          $("#sub_category").append(option);
        });

        $("#sub_category").prop("disabled", false);
        $("#sub_category")
          .parent()
          .append('<span class="text-danger error"></span>');
      } else {
        $("#sub_category").empty().prop("disabled", true);

        const msg = could_not_find_sub_categories_on_this_category_please_change_categories;

        const errorSpan = $("<span>", {
          class: "text-danger error",
          text: msg,
        });

        $("#sub_category").parent().append(errorSpan);
      }
    }
  );
});
$("#edit_category_item").on("change", function () {
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

      $("#edit_sub_category").empty();

      if (data.error === false) {
        const sub_categories = data.data;

        sub_categories.forEach((element) => {
          // Build option safely (no HTML injection)
          const option = $("<option>", {
            value: element.id,
            text: element.name, // auto-escaped by jQuery
          });

          $("#edit_sub_category").append(option);
        });

        $("#edit_sub_category").prop("disabled", false);

        $("#edit_sub_category")
          .parent()
          .append('<span class="text-danger error"></span>');
      } else {
        $("#edit_sub_category").empty().prop("disabled", true);

        // Add safe error message
        const errorMsg = $("<span>", {
          class: "text-danger error",
          text: could_not_find_sub_categories_on_this_category_please_change_categories,
        });

        $("#edit_sub_category").parent().append(errorMsg);
      }
    }
  );
});
function faqs_id(element) {
  $("#id").val($(element).data("id"));
  $("#id").val($(element).data("id"));
}
$("#gen-list a").on("click", function (e) {
  $(this).tab("show");
});
$(document).ready(function () { });
window.faqs_events = {
  "click .remove_faqs": function (e, value, row, index) {
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
          baseUrl + "/admin/faqs/remove_faqs",
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
  "click .edit_faqs": function (e, value, row, index) {
    $("#id").val(row.id);
    $("#edit_question").val(row.question);
    $("#edit_answer").val(row.answer);
  },
};
function taxes_id(element) {
  $("#id").val($(element).data("id"));
  $("#id").val($(element).data("id"));
}
$("#gen-list a").on("click", function (e) {
  $(this).tab("show");
});
$(document).ready(function () { });
window.taxes_events = {
  "click .remove_taxes": function (e, value, row, index) {
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
          baseUrl + "/admin/tax/remove_taxes",
          {
            [csrfName]: csrfHash,
            id: id,
          },
          function (data) {
            csrfName = data.csrfName;
            csrfHash = data.csrfHash;
            if (data.error == false) {
              showToastMessage(data.message, "success");
              $("#user_list").bootstrapTable("refresh");
              return;
            } else {
              return showToastMessage(data.message, "error");
            }
          }
        );
      }
    });
  },
  "click .edit_taxes": function (e, value, row, index) {
    $("#edit_tax_id").val(row.id);
    $("#edit_percentage").val(row.percentage);

    // Use pre-loaded translation data from the list response
    var translations = row.translations || {};

    // Get the default language code from the modal
    // Find the language option with "selected" class or check for "(Default)" text
    var defaultLanguageCode = '';

    // First, try to find by "selected" class
    $('.language-option.selected').each(function () {
      var langCode = $(this).data('language');
      var langText = $(this).find('.language-text').text();
      // Check if it contains "(Default)" to confirm it's the default language
      if (langCode && langText.includes('(Default)')) {
        defaultLanguageCode = langCode;
        return false; // break the loop
      }
    });

    // If not found by class, find by checking for "(Default)" in language text
    if (!defaultLanguageCode) {
      $('.language-option').each(function () {
        var langText = $(this).find('.language-text').text();
        if (langText.includes('(Default)')) {
          defaultLanguageCode = $(this).data('language');
          return false; // break the loop
        }
      });
    }

    // Final fallback: find by checking label text (default language has no parentheses)
    if (!defaultLanguageCode) {
      $('[id^="edit_title_"]').each(function () {
        var labelText = $(this).closest('[id^="translationDiv-edit-"]').find('label').text().trim();
        var isDefaultLanguage = !labelText.includes('(') && !labelText.includes(')');
        if (isDefaultLanguage) {
          var fieldId = $(this).attr('id');
          defaultLanguageCode = fieldId.replace('edit_title_', '');
          return false; // break the loop
        }
      });
    }

    // Clear all edit title fields first
    $('[id^="edit_title_"]').val('');

    // Get all language fields and populate them with proper fallback logic
    $('[id^="edit_title_"]').each(function () {
      var fieldId = $(this).attr('id');
      var languageCode = fieldId.replace('edit_title_', '');

      // Check if translation exists for this language
      if (translations[languageCode] && translations[languageCode].title) {
        // Use the translation if available
        $(this).val(translations[languageCode].title);
      } else {
        // For default language, use default_title from main table (always in default language)
        // For other languages, leave empty (no fallback to main table)
        if (languageCode === defaultLanguageCode && row.default_title) {
          $(this).val(row.default_title);
        }
      }
    });

    if (row.og_status == 1) {
      $("#status_edit").prop("checked", true);
      $("#tax_status_edit").text(switchTextMap.Enable);
    } else {
      $("#status_edit").prop("checked", false);
      $("#tax_status_edit").text(switchTextMap.Disable);
    }
  },
};
function tickets_id(element) {
  $("#id").val($(element).data("id"));
  $("#id").val($(element).data("id"));
}
$("#gen-list a").on("click", function (e) {
  $(this).tab("show");
});
$(document).ready(function () { });
window.tickets_events = {
  "click .remove_tickets": function (e, value, row, index) {
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
          baseUrl + "/admin/tickets/remove_tickets",
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
};
// // mini map
// // code for map start
// let update_location = "";
// let map_update = "";
// let partner_location = "";
// let marker = "";
// let autocomplete = "";
// let add_partner_location = "";
// let view_partner_location = "";
// let map_view = "";
// let map = "";
// var latitude = $("#latitude").val();
// var longitude = $("#longitude").val();
// let center = {
//   lat: parseFloat(latitude),
//   lng: parseFloat(longitude),
// };
// // div for maps
// var map_location = document.getElementById("map");
// var map_location_update = document.getElementById("map_u");
// var partner_map = document.getElementById("partner_map");
// function initautocomplete() {
//   if (document.getElementById("search_places") != null) {
//     autocomplete = new google.maps.places.Autocomplete(
//       document.getElementById("search_places"),
//       {
//         types: ["locality"],
//         fields: ["place_id", "geometry", "name"],
//       }
//     );
//     autocomplete.addListener("place_changed", onPlaceChanged);
//   }
//   $("#update_modal").on("show.bs.modal", function (e) {
//     // for update
//     if (document.getElementById("search_places_u") != null) {
//       update_location = new google.maps.places.Autocomplete(
//         document.getElementById("search_places_u"),
//         {
//           types: ["locality"],
//           fields: ["place_id", "geometry", "name"],
//         }
//       );
//     }
//   });
//   // add
//   function onPlaceChanged(e) {
//     place = autocomplete.getPlace();
//     let contentString = "<h6> " + place.name + " </h6>";
//     center = {
//       lat: place.geometry.location.lat(),
//       lng: place.geometry.location.lng(),
//     };
//     const infowindow = new google.maps.InfoWindow({
//       content: contentString,
//     });
//     map = new google.maps.Map(map_location, {
//       center,
//       zoom: 10,
//     });
//     const marker = new google.maps.Marker({
//       title: place.name,
//       animation: google.maps.Animation.DROP,
//       position: center,
//       map: map,
//     });
//     marker.addListener("click", () => {
//       infowindow.open({
//         anchor: marker,
//         map,
//         shouldFocus: false,
//       });
//     });
//     $("#latitude").val(latitude);
//     $("#longitude").val(longitude);
//     $("#city_name").val(place.name);
//   }
//   // for update
//   if (document.getElementById("search_places_u") != null) {
//     update_location = new google.maps.places.Autocomplete(
//       document.getElementById("search_places_u")
//     );
//     update_location.addListener("place_changed", onUpdatePlace);
//   }
//   if (document.getElementById("partner_location") != null) {
//     add_partner_location = new google.maps.places.Autocomplete(
//       document.getElementById("partner_location")
//     );
//     add_partner_location.addListener("place_changed", on_add_partner);
//   }
//   if (autocomplete) {
//     var place = autocomplete.getPlace();
//   }
//   var latitude =
//     typeof place != "undefined"
//       ? place.geometry.location.lat()
//       : parseFloat("23.242697188102483");
//   var longitude =
//     typeof place != "undefined"
//       ? place.geometry.location.lng()
//       : parseFloat("69.6639650758625");
//   var name =
//     typeof place != "undefined" ? place.geometry.location.lng() : "Bhuj";
//   center = {
//     lat: latitude,
//     lng: longitude,
//   };
//   if (partner_map != null) {
//     if (
//       $.trim($("#partner_latitude").val()) !== "" &&
//       $.trim($("#partner_longitude").val()) !== ""
//     ) {
//       var edit_latitude = parseFloat($("#partner_latitude").val());
//       var edit_longitude = parseFloat($("#partner_longitude").val());
//       center = {
//         lat: edit_latitude,
//         lng: edit_longitude,
//       };
//     }
//     partner_location = new google.maps.Map(partner_map, {
//       center,
//       zoom: 5,
//     });
//     if (
//       $.trim($("#partner_latitude").val()) !== "" &&
//       $.trim($("#partner_longitude").val()) !== ""
//     ) {
//       var edit_latitude = parseFloat($("#partner_latitude").val());
//       var edit_longitude = parseFloat($("#partner_longitude").val());
//       set_map_marker_for_partner(
//         "",
//         edit_latitude,
//         edit_longitude,
//         "",
//         partner_location
//       );
//       var geocoder = new google.maps.Geocoder();
//       geocoder.geocode({ location: center }, function (results, status) {
//         if (status === "OK") {
//           if (results[0]) {
//             var placeName = results[0].formatted_address;
//             $("#partner_location").val(placeName);
//           } else {
//             console.error("No results found");
//           }
//         } else {
//           console.error("Geocoder failed due to: " + status);
//         }
//       });
//     }
//     /* add marker on clicked location */
//     google.maps.event.addListener(partner_location, "click", function (event) {
//       var latitude = event.latLng.lat();
//       var longitude = event.latLng.lng();
//       set_map_marker_for_partner("", latitude, longitude, "", partner_location);
//       $("#partner_latitude").val(latitude);
//       $("#partner_longitude").val(longitude);
//     }); //end addListener
//   }
//   function on_add_partner() {
//     place = add_partner_location.getPlace();
//     let latitude = place.geometry.location.lat();
//     let longitude = place.geometry.location.lng();
//     set_map_marker_for_partner(place, "", "", "", partner_location);
//     $("#partner_latitude").val(latitude);
//     $("#partner_longitude").val(longitude);
//   }
//   if (map_location != null) {
//     map = new google.maps.Map(map_location, {
//       center,
//       zoom: 8,
//     });
//   }
//   if (map_location_update != null) {
//     map_update = new google.maps.Map(map_location_update, {
//       center,
//       zoom: 8,
//     });
//   }
//   function onUpdatePlace(e) {
//     place = update_location.getPlace();
//     let latitude = place.geometry.location.lat();
//     let longitude = place.geometry.location.lng();
//     set_map_marker(place);
//     $("#u_city_name").val(place.name);
//     $("#u_latitude").val(latitude);
//     $("#u_longitude").val(longitude);
//   }
//   var info_window = "";
//   view_partner_location = document.getElementById("map_tuts");
//   if (view_partner_location != null) {
//     var view_latitude = parseFloat($("#lat").val());
//     var view_longitude = parseFloat($("#lon").val());
//     if (view_latitude != "" && view_longitude != "") {
//       center = {
//         lat: view_latitude,
//         lng: view_longitude,
//       };
//       map_view = new google.maps.Map(view_partner_location, {
//         center,
//         zoom: 16,
//       });
//       const marker = new google.maps.Marker({
//         // title: title,
//         animation: google.maps.Animation.DROP,
//         position: center,
//         map: map_view,
//       });
//       marker.addListener("click", () => {
//         info_window.open({
//           anchor: marker,
//           map_view,
//           shouldFocus: false,
//         });
//       });
//     } else {
//       $(view_partner_location).text("<h6> No Data passed </h6>");
//     }
//   } else {
//     // console.log("view_partner_location is empty");
//   }
// }
// window.initMap = initautocomplete;
// // google.maps.event.addDomListener(window, 'load', initAutocomplete);
// // mini map ends here
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
// function set_map_marker(place = "", latitude = "", longitude = "", name = "") {
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
//   map = new google.maps.Map(map_location_update, {
//     center,
//     zoom: 10,
//   });
//   const marker = new google.maps.Marker({
//     title: title,
//     animation: google.maps.Animation.DROP,
//     position: center,
//     map: map,
//   });
//   marker.addListener("click", () => {
//     infowindow.open({
//       anchor: marker,
//       map,
//       shouldFocus: false,
//     });
//   });
// }

// // ====== GLOBAL VARS ======
// let map;
// let autocomplete;
// let partnerMap;
// let marker;

// // Keys injected by PHP (from include-scripts.php)
// const MAPS_KEY = window.GOOGLE_MAP_API_KEY;     // for rendering maps
// const PLACES_KEY = window.GOOGLE_PLACES_API_KEY; // for fetching places data

// // ====== MAIN INIT CALLBACK ======
// function initGoogle() {
//   // console.log("Google Maps init callback fired ✅");
//   initMap?.();
//   initAutocomplete?.();
//   initPartnerMap?.();
//   initPartnerAutocomplete?.();
// }

// // ====== MAP RENDERING ======
// function initMap() {
//   const mapEl = document.getElementById("map");
//   if (!mapEl) return;

//   let lat = parseFloat($("#latitude").val()) || 23.2427;
//   let lng = parseFloat($("#longitude").val()) || 69.6639;

//   map = new google.maps.Map(mapEl, {
//     center: { lat, lng },
//     zoom: 6,
//   });

//   marker = new google.maps.Marker({
//     position: { lat, lng },
//     map,
//     title: "Selected Location",
//   });
// }

// function initAutocomplete() {
//   const searchInput = document.getElementById("search_places");
//   if (!searchInput) return;

//   autocomplete = new google.maps.places.Autocomplete(searchInput, {
//     types: ["(cities)"], // or ["locality"] for smaller areas
//   });

//   autocomplete.addListener("place_changed", () => {
//     const place = autocomplete.getPlace();
//     if (!place.geometry) return;

//     const lat = place.geometry.location.lat();
//     const lng = place.geometry.location.lng();

//     $("#latitude").val(lat);
//     $("#longitude").val(lng);
//     $("#city_name").val(place.name);

//     if (!map) {
//       map = new google.maps.Map(document.getElementById("map"), {
//         center: { lat, lng },
//         zoom: 10,
//       });
//     } else {
//       map.setCenter({ lat, lng });
//       map.setZoom(10);
//     }

//     if (!marker) {
//       marker = new google.maps.Marker({
//         position: { lat, lng },
//         map,
//         title: place.name,
//         animation: google.maps.Animation.DROP,
//       });
//     } else {
//       marker.setPosition({ lat, lng });
//       marker.setTitle(place.name);
//     }
//   });
// }

// function initPartnerAutocomplete() {
//   const partnerInput = document.getElementById("partner_location");
//   if (!partnerInput) return;

//   const partnerAutocomplete = new google.maps.places.Autocomplete(partnerInput, {
//     types: ["geocode"], // or ["locality"] / ["(cities)"], depends on what you need
//   });

//   partnerAutocomplete.addListener("place_changed", () => {
//     const place = partnerAutocomplete.getPlace();
//     if (!place.geometry) return;

//     const lat = place.geometry.location.lat();
//     const lng = place.geometry.location.lng();

//     $("#partner_latitude").val(lat);
//     $("#partner_longitude").val(lng);

//     // update the partner map marker
//     if (!partnerMap) {
//       partnerMap = new google.maps.Map(document.getElementById("partner_map"), {
//         center: { lat, lng },
//         zoom: 10,
//       });
//     } else {
//       partnerMap.setCenter({ lat, lng });
//       partnerMap.setZoom(10);
//     }

//     if (!marker) {
//       marker = new google.maps.Marker({
//         position: { lat, lng },
//         map: partnerMap,
//         draggable: true,
//       });
//     } else {
//       marker.setPosition({ lat, lng });
//     }
//   });
// }


// // ====== PARTNER MAP ======
// function initPartnerMap() {
//   const partnerEl = document.getElementById("partner_map");
//   if (!partnerEl) return;

//   let lat = parseFloat($("#partner_latitude").val()) || 23.2427;
//   let lng = parseFloat($("#partner_longitude").val()) || 69.6639;

//   partnerMap = new google.maps.Map(partnerEl, {
//     center: { lat, lng },
//     zoom: 5,
//   });

//   let partnerMarker = new google.maps.Marker({
//     position: { lat, lng },
//     map: partnerMap,
//     draggable: true,
//   });

//   // Update hidden inputs when dragging
//   partnerMarker.addListener("dragend", (e) => {
//     $("#partner_latitude").val(e.latLng.lat());
//     $("#partner_longitude").val(e.latLng.lng());
//   });

//   // Update when clicking on map
//   partnerMap.addListener("click", (e) => {
//     const newLat = e.latLng.lat();
//     const newLng = e.latLng.lng();
//     partnerMarker.setPosition({ lat: newLat, lng: newLng });
//     $("#partner_latitude").val(newLat);
//     $("#partner_longitude").val(newLng);
//   });
// }

// // Attach to global so Google Maps callback can see it
// window.initGoogle = initGoogle;


$("#member").hide();
$(document).ready(function () {
  $("#type").on("change", function (e) {
    if ($("#type").val() == "0" || $("#type").val() == "sel") {
      $("#member").hide();
    } else {
      $("#member").show();
    }
  });
});
window.payment_events = {
  "click .edit_request": function (e, value, row, index) {
    $("#request_id").val(row.id);
    $("#user_id").val(row.user_id);
    $("#amount").val(row.amount);
  },
};
function get_message(messages) {
  var messages_html;
  var data = JSON.parse(messages);
  let message_html;
  for (let i = 0; i < data["rows"].length; i++) {
    let element = data["rows"][i];
    var user_type = element["user_type"];
    var user_name = element["username"];
    var updated_at = element["updated_at"];
    var message = element["message"];
    var is_left = user_type == "user" ? "left" : "right";
    var bg_color =
      is_left == "left" ? "bg-primary text-white" : "bg-success text-white";
    var atch_html;
    let attachments =
      element["attachments"] != "" ? JSON.parse(element["attachments"]) : null;
    if (attachments != null && attachments.length > 0) {
      attachments.forEach((element) => {
        let attachment = element;
        atch_html =
          "<div class='container-fluid image-upload-section'>" +
          "<a class='btn btn-danger btn-xs mr-1 mb-1' href=' " +
          attachment +
          "'  target='_blank' alt='Attachment Not Found'>Attachment</a>" +
          "<div class='col-md-3 col-sm-12 shadow p-3 mb-5 bg-white rounded m-4 text-center grow image d-none'></div>" +
          "</div>";
        messages_html =
          "<div class='direct-chat-msg " +
          is_left +
          "'>" +
          "<div class='direct-chat-infos clearfix'>" +
          "<span class='direct-chat-name float-" +
          is_left +
          "' id='name'> " +
          user_name +
          "</span>" +
          "<span class='direct-chat-timestamp float-" +
          is_left +
          "' id='last_updated'> &nbsp;" +
          updated_at +
          "</span>" +
          "</div>";
        if (message != null) {
          messages_html +=
            "<div class='direct-chat-text " +
            bg_color +
            " float-" +
            is_left +
            "' id=" +
            user_type +
            ">" +
            message +
            "</div> <br> <br>";
        }
        messages_html +=
          "<div class='direct-chat-text  float-" +
          is_left +
          "' id='message'> " +
          atch_html +
          "</div> <br> <br>" +
          "</div>";
      });
    } else {
      messages_html =
        "<div class='direct-chat-msg " +
        is_left +
        "'>" +
        "<div class='direct-chat-infos clearfix'>" +
        "<span class='direct-chat-name float-" +
        is_left +
        "' id='name'> " +
        user_name +
        "</span>" +
        "<span class='direct-chat-timestamp float-" +
        is_left +
        "' id='last_updated'> &nbsp;" +
        updated_at +
        "</span>" +
        "</div>" +
        "<div class='direct-chat-text " +
        bg_color +
        " float-" +
        is_left +
        "' id=" +
        user_type +
        ">" +
        message +
        "</div>  <br> <br>" +
        "</div>";
    }
    $(".ticket_msg").prepend(messages_html);
  }
}
$(document).ready(function () { });
function printDiv(divName) {
  var printContents = document.getElementById(divName).innerHTML;
  var originalContents = document.body.innerHTML;
  document.body.innerHTML = printContents;
  window.print();
  document.body.innerHTML = originalContents;
}
$(document).ready(function () {
  $("#old_user").hide();
  $("#new_user").hide();
  $("#user_type").on("change", function (e) {
    if ($("#user_type").val() == "new_user") {
      $("#old_user").hide();
      $("#new_user").show();
    } else {
      $("#old_user").show();
      $("#new_user").hide();
    }
  });
});
function change_order_Status() {
  var status = $(".update_order_status").val();
  var order_id = $("#order_id").val();
  var input_body = {
    [csrfName]: csrfHash,
    status: status,
    order_id: order_id,
  };
  $.ajax({
    type: "POST",
    url: baseUrl + "/admin/orders/change_order_status",
    data: input_body,
    dataType: "json",
    success: function (response) {
      csrfName = response["csrfName"];
      csrfHash = response["csrfHash"];
      if (response.error != false) {
        showToastMessage(response.message, "success");
        setTimeout(() => {
          window.location.reload();
        }, 3000);
      } else {
        showToastMessage(response.message, "error");
        setTimeout(() => {
          window.location.reload();
        }, 3000);
      }
    },
  });
}
$(window).ready(function () {
  const checkDiv = setInterval(() => {
    if ($(".partner-rating").length > 0) {
      clearInterval(checkDiv);
      for (let i = 0; i < $(".partner-rating").length; i++) {
        let element = $(".partner-rating")[i];
        let id = $(".partner-rating")[i]["id"];
        let ratings = $(element).attr("data-value");
        $(document).ready(function () {
          $("#" + id).rateYo({
            rating: ratings,
            spacing: "5px",
            readOnly: true,
            starWidth: "15px",
            starHeight: "85px",
          });
        });
      }
    }
  }, 100);
});
$(window).ready(function () {
  $("#partner_list").on({
    "": function (e) { },
  });
  $("#partner_list").on({
    "load-success.bs.table , page-change.bs.table, check.bs.table, uncheck.bs.table, column-switch.bs.table":
      function (e) {
        for (let i = 0; i < $(".partner-rating").length; i++) {
          let element = $(".partner-rating")[i];
          let id = $(".partner-rating")[i]["id"];
          let ratings = $(element).attr("data-value");
          $(document).ready(function () {
            $("#" + id).rateYo({
              rating: ratings,
              spacing: "5px",
              readOnly: true,
              starWidth: "25px",
              starHeight: "85px",
            });
          });
        }
      },
  });
});
$(document).ready(function () {
  const checkDiv = setInterval(() => {
    if ($(".service-ratings").length > 0) {
      clearInterval(checkDiv);
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
    }
  }, 1);
  $("#view_rating_model").on("show.bs.modal ", function (e) {
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
  });
});
$(document).ready(function () {
  // Hide search icons in navbar/menu, but keep search button icons visible
  // Exclude search icons that are inside buttons or input-group-append elements
  // This ensures search buttons in tables and forms remain visible on all screen sizes
  $(".fa-search").not("button .fa-search, .input-group-append .fa-search, .btn .fa-search").addClass("d-none");
});
window.customSearchFormatter = function (value, searchText) {
  return value
    .toString()
    .replace(
      new RegExp("(" + searchText + ")", "gim"),
      '<span style="background-color: pink;border: 1px solid red;border-radius:90px;padding:4px">$1</span>'
    );
};
$(document).ready(function () {
  $("#parent").hide();
  var option = $("#make_parent").val();
  $("#make_parent").change(function (e) {
    e.preventDefault();
    if ($(this).val() == 1) {
      $("#parent").show();
    } else {
      $("#parent").hide();
    }
  });
});
$(document).ready(function () {
  $("#edit_make_parent").trigger("change");
  $("#edit_parent").hide();
  var option = $("#edit_make_parent").val();
  $("#edit_make_parent").change(function (e) {
    if ($(this).val() == "1") {
      $("#edit_parent").show();
    } else {
      $("#edit_parent").hide();
    }
  });
});
$("#rescheduled_form").on("submit", function (e) {
  e.preventDefault();
});
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
    labelMaxFileSize: maximum_file_size_is + " {filesize}",
    allowFileTypeValidation: true,
    acceptedFileTypes: ["image/*", "video/*", "application/pdf"],
    labelFileTypeNotAllowed: file_of_invalid_type,
    labelIdle: drag_and_drop_files_here + ' ' + or + ' <span class="filepond--label-action">' + browse_files + '</span>',
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
    labelMaxFileSize: maximum_file_size_is + " {filesize}",
    allowFileTypeValidation: true,
    acceptedFileTypes: [
      "application/pdf",
      "application/msword",
      "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
    ],
    labelFileTypeNotAllowed: file_of_invalid_type,
    labelIdle: drag_and_drop_files_here + ' ' + or + ' <span class="filepond--label-action">' + browse_files + '</span>',
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
    labelIdle: drag_and_drop_files_here + ' ' + or + ' <span class="filepond--label-action">' + browse_files + '</span>',
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
    labelIdle: drag_and_drop_files_here + ' ' + or + ' <span class="filepond--label-action">' + browse_files + '</span>',
    labelMaxFileSize: maximum_file_size_is + " {filesize}",
    allowFileTypeValidation: true,
    acceptedFileTypes: ["image/*", "video/*"],
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
  // FilePond configuration for image-only uploads (strict validation)
  $(".filepond-image-only").filepond({
    credits: null,
    allowFileSizeValidation: "true",
    maxFileSize: "10MB",
    labelMaxFileSizeExceeded: file_is_too_large,
    labelIdle: drag_and_drop_files_here + ' ' + or + ' <span class="filepond--label-action">' + browse_files + '</span>',
    labelMaxFileSize: maximum_file_size_is + " {filesize}",
    allowFileTypeValidation: true,
    // Use specific image MIME types to prevent HTML and other non-image files
    acceptedFileTypes: [
      "image/jpeg",
      "image/jpg",
      "image/png",
      "image/gif",
      "image/webp",
      "image/bmp",
      "image/svg+xml",
      "image/tiff",
      "image/x-icon"
    ],
    labelFileTypeNotAllowed: file_of_invalid_type || "File must be an image (JPEG, PNG, GIF, WebP, etc.)",
    fileValidateTypeLabelExpectedTypes: "Expects an image file (JPEG, PNG, GIF, WebP, etc.)",
    storeAsFile: true,
    allowPdfPreview: false,
    allowVideoPreview: false,
    allowAudioPreview: false
  });
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
var elems1 = Array.prototype.slice.call(
  document.querySelectorAll(".switchery-yes-no")
);
elems1.forEach(function (elems1) {
  var switchery = new Switchery(elems1, {
    size: "small",
    color: "#47C363",
    secondaryColor: "#EB4141",
    jackColor: "#ffff",
    jackSecondaryColor: "#FFFF",
  });
});
$(document).ready(function () {
  for (let i = 0; i < $(".average_service-ratings").length; i++) {
    let element = $(".average_service-ratings")[i];
    let id = $(".average_service-ratingss")[i]["id"];
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
});
var partner_filter = "";
$("#partner_filter_all").on("click", function () {
  partner_filter = "";
  $("#partner_list").bootstrapTable("refresh");
});
$("#partner_filter_active").on("click", function () {
  partner_filter = "1";
  $("#partner_list").bootstrapTable("refresh");
});
$("#partner_filter_deactivate").on("click", function () {
  partner_filter = "0";
  $("#partner_list").bootstrapTable("refresh");
});
$("#partner_filter_individual_partner").on("click", function () {
  partner_filter = "individual_partner";
  $("#partner_list").bootstrapTable("refresh");
});
$("#partner_filter_orgenization_partner").on("click", function () {
  partner_filter = "orgenization_partner";
  $("#partner_list").bootstrapTable("refresh");
});
// partner list params
function partner_list_query_params(p) {
  return {
    search: p.search,
    limit: p.limit,
    sort: p.sort,
    order: p.order,
    offset: p.offset,
    partner_filter: partner_filter,
  };
}
var top_rated_provider_filter = "";
// Order status filter change handler moved to inline script in orders.php
// Filter click handler removed - now handled inline in orders.php with auto-refresh
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
$("#make_payment_for_subscription").on("submit", function (event) {
  event.preventDefault();
  $.post(
    base_url + "/partner/subscription/pre-payment-setup233",
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
  // }
});
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
              location.href = base_url + "/payment/success";
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
    url: base_url + "/partner/subscription-payment",
    dataType: "json",
    cache: false,
    processData: false,
    contentType: false,
    beforeSend: function () {
      $("#buy").attr("disabled", true).html("Please Wait...");
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
// includeOnlyColumns: optional 5th arg. Columns to add to export even if not in the table.
// Each item is { field: 'fieldName', title: 'Column Title' } or just 'fieldName' (title = title-cased).
function custome_export(type, label, table_name, excludeColumns = [], includeOnlyColumns = []) {
  var selector = "#" + table_name;
  var $table = $(selector);

  function normalizeKey(v) {
    return (v ?? "").toString().trim().toLowerCase();
  }

  // Convert HTML strings (e.g. status tags / profile blocks) to plain text.
  // This ensures exports never contain <img> tags or other markup.
  function stripHtml(input) {
    if (input === null || typeof input === "undefined") return "";
    if (typeof input !== "string") return ("" + input).trim();
    // Fast path: no markup.
    if (input.indexOf("<") === -1) return input.trim();
    var div = document.createElement("div");
    div.innerHTML = input;
    // Drop media/icon nodes entirely (composite profile cells: <img> + text).
    div
      .querySelectorAll("img, svg, picture, video, canvas, i, .material-symbols-outlined")
      .forEach(function (el) {
        el.parentNode && el.parentNode.removeChild(el);
      });
    // Separate block-level chunks so name/company/email don't collide.
    div.querySelectorAll("div, p, br, tr, li").forEach(function (el) {
      el.appendChild(document.createTextNode(" "));
    });
    var text = div.textContent || div.innerText || "";
    return text.replace(/\s+/g, " ").trim();
  }

  // Title-case a field name for export header (e.g. "phone" -> "Phone").
  function titleCaseField(name) {
    var s = (name ?? "").toString().trim();
    return s.length === 0 ? s : s.charAt(0).toUpperCase() + s.slice(1).toLowerCase();
  }

  // Build exclusion set only from the excludeColumns argument passed in the call.
  // Use field names or header titles (e.g. "Status", "Operations", "partner_profile").
  var excludeList = Array.isArray(excludeColumns) ? excludeColumns.slice() : [];
  var excludeSet = new Set(excludeList.map(normalizeKey));
  // So "Operations" (plural) matches column title "Operation" (singular)
  excludeSet.forEach(function (key) {
    if (key.length > 1 && key[key.length - 1] === "s") {
      excludeSet.add(key.slice(0, -1));
    }
  });

  // Normalize includeOnlyColumns to { field, title }[] and dedupe by field.
  var includeOnly = [];
  var includeOnlySeen = new Set();
  (Array.isArray(includeOnlyColumns) ? includeOnlyColumns : []).forEach(function (item) {
    var field = typeof item === "string" ? item : (item && item.field);
    if (!field) return;
    var norm = normalizeKey(field);
    if (includeOnlySeen.has(norm)) return;
    includeOnlySeen.add(norm);
    includeOnly.push({
      field: field,
      title: (typeof item === "object" && item && item.title) ? item.title : titleCaseField(field),
    });
  });

  // Columns to export, then append export-only columns.
  // When bootstrap-table is active, use its LIVE visible-column state so
  // runtime column-toggle (filter drawer show/hide) is respected. Otherwise
  // fall back to the static <th> data-visible attribute.
  // Export-only columns pull from row[field] even if the column is not in the view.
  function getExportColumns(liveVisibleColumns) {
    var cols = [];
    var colFields = new Set();

    function pushCol(title, field) {
      // Skip columns that have no field key (cannot map from list response)
      if (!field) return;
      var normTitle = normalizeKey(title);
      var normField = normalizeKey(field);
      // Exclude by title or field (from excludeColumns argument; plural/singular handled above)
      if (excludeSet.has(normTitle) || excludeSet.has(normField)) return;
      if (colFields.has(normField)) return;
      cols.push({ title: title, field: field });
      colFields.add(normField);
    }

    if (Array.isArray(liveVisibleColumns)) {
      // bootstrap-table getVisibleColumns(): already filtered to visible only.
      liveVisibleColumns.forEach(function (c) {
        if (!c || c.visible === false) return;
        var field = c.field;
        // Prefer the rendered <th> text for the header title.
        var $th = field
          ? $table.find('thead th[data-field="' + field + '"]')
          : $();
        var title = $th.length ? $th.text().trim() : (c.title || field);
        pushCol(title, field);
      });
    } else {
      // Fallback: static <th> scan (non-bootstrap-table pages).
      $table.find("thead th").each(function (index, th) {
        var $th = $(th);
        if ($th.data("visible") === false) return;
        pushCol($th.text().trim(), $th.data("field"));
      });
    }

    // Append export-only columns (not already in table columns).
    includeOnly.forEach(function (c) {
      if (colFields.has(normalizeKey(c.field))) return;
      cols.push({ title: c.title, field: c.field });
    });
    return cols;
  }

  function exportRows(columns, rows) {
    var headers = columns.map((c) => c.title);
    var body = (rows || []).map((row) => {
      return columns.map((c) => stripHtml(row ? row[c.field] : ""));
    });

    if (type === "pdf") {
      try {
        var marginLeft = 40;
        var marginRight = 40;
        var marginTop = 50;

        var landscapeWidth = 842;   // A4 landscape width
        var landscapeHeight = 595;

        var minColWidth = 60; // safe width per column
        var pageWidth = Math.max(
          landscapeWidth,
          columns.length * minColWidth + marginLeft + marginRight
        );

        var doc = new window.jspdf.jsPDF("l", "pt", [pageWidth, landscapeHeight]);
        var tableWidth = pageWidth - marginLeft - marginRight;

        doc.autoTable({
          head: [headers],
          body: body,
          startY: marginTop,
          tableWidth: tableWidth,
          styles: {
            fontSize: 7,
            cellPadding: 3,
            overflow: "linebreak"
          },
          headStyles: {
            fontSize: 7
          },
          columnStyles: columns.reduce((acc, _, i) => {
            acc[i] = {
              cellWidth: tableWidth / columns.length
            };
            return acc;
          }, {}),
          margin: {
            left: marginLeft,
            right: marginRight
          },
          didDrawPage: function () {
            doc.text(label, marginLeft, 30);
          }
        });

        doc.save(label + ".pdf");
      } catch (error) {
        console.error("Error during PDF export:", error);
        alert("An error occurred during PDF export.");
      }
      return;
    }

    if (type === "excel" || type === "csv") {
      try {
        var wb = XLSX.utils.book_new();
        var ws = XLSX.utils.aoa_to_sheet([headers].concat(body));
        XLSX.utils.book_append_sheet(wb, ws, "Sheet1");
        XLSX.writeFile(wb, label + "." + (type === "excel" ? "xlsx" : "csv"));
      } catch (error) {
        console.error("Error during " + type.toUpperCase() + " export:", error);
        alert(
          "An error occurred during " +
          type.toUpperCase() +
          " export. Please try again or contact support."
        );
      }
    }
  }

  // Prefer exporting from the bootstrap-table list response (server data),
  // not from the rendered <tbody> text.
  var canUseBootstrapTable =
    typeof $table.bootstrapTable === "function" &&
    $table.data("bootstrap.table");

  if (canUseBootstrapTable) {
    var opts = $table.bootstrapTable("getOptions") || {};
    var url = opts.url || $table.data("url");
    // Live visible columns (respects runtime column-toggle drawer state).
    var liveVisibleColumns = null;
    try {
      liveVisibleColumns = $table.bootstrapTable("getVisibleColumns");
    } catch (e) {
      liveVisibleColumns = null;
    }
    var columns = getExportColumns(liveVisibleColumns);

    // If we cannot determine columns or URL, fall back to DOM export.
    if (url && columns.length > 0) {
      // Build params using the table's own queryParams so filters/search match the list.
      var baseParams = {
        search: "",
        limit: 999999,
        offset: 0,
        sort: opts.sortName || "id",
        order: opts.sortOrder || "desc",
      };

      var params =
        typeof opts.queryParams === "function"
          ? opts.queryParams(baseParams)
          : baseParams;

      // Force "all rows" export with current filters.
      params = params || {};
      params.limit = 999999;
      params.offset = 0;

      $.ajax({
        url: url,
        type: (opts.method || "GET").toString(),
        data: params,
        dataType: "json",
        success: function (response) {
          // bootstrap-table expects `{ total, rows: [...] }`
          var rows = [];
          if (response && Array.isArray(response.rows)) {
            rows = response.rows;
          } else if (response && Array.isArray(response.data)) {
            rows = response.data;
          } else if (Array.isArray(response)) {
            rows = response;
          }
          exportRows(columns, rows);
        },
        error: function (xhr, status, error) {
          console.error("Export fetch failed:", status, error);
          alert(
            "Unable to export right now. Please try again or contact support."
          );
        },
      });
      return;
    }
  }

  // Fallback: DOM-based export (kept for non-bootstrap-table pages).
  // This path still respects exclusions correctly (no column shifting).
  var columnsFallback = getExportColumns();
  if (columnsFallback.length === 0) {
    // As a last resort, include visible headers by title only.
    columnsFallback = [];
    $table.find("thead th").each(function (index, th) {
      var title = $(th).text().trim();
      var visible = $(th).data("visible");
      if (visible === false) return;
      if (excludeSet.has(normalizeKey(title))) return;
      columnsFallback.push({ title: title, field: null });
    });
  }

  var bodyFallback = [];
  $table.find("tbody tr").each(function (rowIndex, tr) {
    var row = [];
    $(tr)
      .find("td")
      .each(function (colIndex, td) {
        var text = $(td).text().trim();
        row.push(text);
      });
    if (row.length > 0) bodyFallback.push(row);
  });

  exportRows(
    columnsFallback.map((c, i) => ({
      title: c.title,
      field: c.field || i,
    })),
    bodyFallback.map((r) => {
      // Convert row array -> object with numeric keys
      var obj = {};
      r.forEach((val, i) => {
        obj[i] = val;
      });
      return obj;
    })
  );
}
function DoBeforeAutotable(
  table,
  headers,
  rows,
  AutotableSettings,
  excludeColumns
) {
  if (excludeColumns.length > 0) {
    let headerIndexesToRemove = [];
    headers.forEach((header, index) => {
      if (excludeColumns.includes(header.title)) {
        headerIndexesToRemove.push(index);
      }
    });
    // Sort indices in descending order to prevent index shifting issues
    headerIndexesToRemove.sort((a, b) => b - a);
    // Remove corresponding columns from headers and rows
    headerIndexesToRemove.forEach((index) => {
      headers.splice(index, 1);
      rows.forEach((row) => row.splice(index, 1));
    });
    // Ensure all headers have necessary properties
    headers = headers.map((header) => ({
      title: header.title || "",
      dataKey: header.dataKey || "",
      style: header.style || {},
    }));
    // Update AutotableSettings to reflect changes
    AutotableSettings.columns = headers;
  }
  // Ensure all rows have the correct number of cells
  const headerCount = headers.length;
  rows.forEach((row) => {
    while (row.length < headerCount) {
      row.push(""); // Add empty cells if necessary
    }
  });
}

var service_filter = "";
var service_custom_provider_filter = "";
var service_filter_approve = "";
var service_custom_provider_filter = "";
$("#service_custom_provider_filter").on("change", function () {
  service_custom_provider_filter = $(this).find("option:selected").val();
});
var service_category_custom_filter = "";
$("#service_category_custom_filter").on("change", function () {
  service_category_custom_filter = $(this).find("option:selected").val();
});

$("#service_filter_all").on("click", function (e) {
  $("#service_list").bootstrapTable("refresh");
});

$("#service_filter").on("click", function (e) {
  $("#service_list").bootstrapTable("refresh");
});
// Panel state persistence: search + status chips, restored on back-nav/reload.
var PANEL_STATE_PREFIX = "panelState:" + window.location.pathname + ":";

function panelStateGet(key) {
  try {
    return sessionStorage.getItem(PANEL_STATE_PREFIX + key);
  } catch (e) {
    return null;
  }
}
function panelStateSet(key, value) {
  try {
    if (value === null || value === "" || value === undefined) {
      sessionStorage.removeItem(PANEL_STATE_PREFIX + key);
    } else {
      sessionStorage.setItem(PANEL_STATE_PREFIX + key, value);
    }
  } catch (e) {
    /* sessionStorage unavailable */
  }
}

function customSearchRefreshTables() {
  $('table[data-toggle="table"]').each(function () {
    if ($(this).data("bootstrap.table")) {
      $(this).bootstrapTable("refresh");
    }
  });
}

// Restore search at parse time, before bootstrap-table reads queryParams.
(function restorePanelState() {
  var $search = $("#customSearch");
  if ($search.length) {
    var savedSearch = panelStateGet("search");
    if (savedSearch !== null && $.trim($search.val()) === "") {
      $search.val(savedSearch);
    }
  }
})();

// Persist last-clicked status chip; replay once after the table's first load.
$(document).on("click", ".filters_table", function () {
  panelStateSet("chip", this.id || null);
  // Move the active highlight to the clicked chip within its group (siblings
  // sharing the same parent container).
  $(this).parent().find(".filters_table").removeClass("active-filter");
  $(this).addClass("active-filter");
});
// After the table's first load: the browser natively restores drawer selects
// on back-nav but without firing change, so per-view globals stay empty and the
// initial AJAX is unfiltered. Re-fire change on any non-empty drawer select so
// the per-view handlers repopulate their globals, then one refresh (chip replay
// already refreshes; otherwise refresh explicitly).
$(document).one(
  "load-success.bs.table",
  'table[data-toggle="table"]',
  function () {
    var needsRefresh = false;
    $("#filterDrawer select").each(function () {
      var v = $(this).val();
      if ($.trim(String(v == null ? "" : v)) !== "") {
        $(this).trigger("change");
        needsRefresh = true;
      }
    });
    // Default-highlight the first chip in each group so an active filter is
    // always visible; a persisted chip replay below overrides this.
    var seenGroups = [];
    $(".filters_table").each(function () {
      var grp = $(this).parent().get(0);
      if (seenGroups.indexOf(grp) === -1) {
        seenGroups.push(grp);
        $(this).addClass("active-filter");
      }
    });
    var chipId = panelStateGet("chip");
    if (chipId && $("#" + chipId).length) {
      $("#" + chipId).trigger("click");
      return;
    }
    if (needsRefresh) {
      customSearchRefreshTables();
    }
  }
);

$("#customSearchBtn").on("click", function () {
  panelStateSet("search", $("#customSearch").val());
  customSearchRefreshTables();
});

// Allow Enter key to trigger search button click
$("#customSearch").on("keypress", function (e) {
  if (e.which == 13) {
    e.preventDefault();
    $("#customSearchBtn").click();
  }
});

function service_list_query_params1(p) {
  return {
    search: $("#customSearch").val() ? $("#customSearch").val() : p.search,
    limit: p.limit,
    sort: p.sort,
    order: p.order,
    offset: p.offset,
    service_filter: service_filter,
    service_custom_provider_filter: service_custom_provider_filter,
    service_category_custom_filter: service_category_custom_filter,
    service_filter_approve: service_filter_approve,
  };
}
function setupColumnToggle(tableId, columns_name, containerId) {
  $(document).ready(function () {
    var $table = $("#" + tableId);
    function toggleColumnVisibility() {
      $(".column-toggle").each(function () {
        var field = $(this).data("field");
        var isVisible = $(this).prop("checked");
        if (isVisible) {
          $table.bootstrapTable("showColumn", field);
        } else {
          $table.bootstrapTable("hideColumn", field);
        }
      });
    }
    //     $("#columnToggleContainer").on("change", ".column-toggle", function () {
    function persistColumns() {
      var map = {};
      $(".column-toggle").each(function () {
        map[$(this).data("field")] = $(this).prop("checked");
      });
      panelStateSet("cols:" + tableId, JSON.stringify(map));
    }
    $("#apply_filter").on("click", function () {
      $("#filterDrawer").removeClass("open");
      $("#filterBackdrop").fadeOut(200);
      toggleColumnVisibility();
      persistColumns();
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
    // Restore saved column-toggle states before applying visibility.
    var savedCols;
    try {
      savedCols = JSON.parse(panelStateGet("cols:" + tableId) || "null");
    } catch (e) {
      savedCols = null;
    }
    if (savedCols) {
      $(".column-toggle").each(function () {
        var field = $(this).data("field");
        if (Object.prototype.hasOwnProperty.call(savedCols, field)) {
          $(this).prop("checked", !!savedCols[field]);
        }
      });
    }
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
document.addEventListener("DOMContentLoaded", function () {
  var filterBackdrop = document.querySelector(".filter-backdrop");
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
function copyToClipboard(name) {
  var copyText = document.querySelector("[name=" + name + "]");
  if (copyText) {
    copyText.select();
    document.execCommand("copy");
    showToastMessage("Copied", "success");
  } else {
    showToastMessage("Error copying text", "error");
  }
}
function partner_settlement_and_cash_collection_history_query_params(p) {
  return {
    search: $("#customSearch").val() ? $("#customSearch").val() : p.search,
    limit: p.limit,
    sort: p.sort,
    order: p.order,
    offset: p.offset,
    history_filter: history_filter,
  };
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
    let countFile = totalImages - imageLimit;
    templateDiv.find(".img_count").html(`<h2>+${countFile}</h2>`);
    $(document).on("click", ".img_count", function () {
      const images = files.map(
        (file) =>
          `<div class="col-md-3"><a href="${file.file}" data-lightbox="image-1"><img height="200px"width="200px" style="    padding: 8px;
          box-shadow: rgba(99, 99, 99, 0.2) 0px 2px 8px 0px;
          border-radius: 11px;
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
        '" class="text-dark">' +
        fileName +
        "</a>";
      html += '<i class="fa-solid fa-circle-down text-dark ml-2"></i>';
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
        '" class="text-dark">';
      html += '<i class="fa-solid fa-circle-down text-dark ml-2"></i>';
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
    var senderName = message.sender_name || message.username || "U";
    if (
      !message.profile_image ||
      message.profile_image === "" ||
      message.profile_image === "null" ||
      message.profile_image === "undefined" ||
      !isValidImageUrlClient(message.profile_image)
    ) {
      var firstLetter = senderName.charAt(0).toUpperCase();
      var colorClass = "color-" + ((senderName.charCodeAt(0) % 8) + 1);
      html +=
        '<div class="chat-msg-img-placeholder ' +
        colorClass +
        '">' +
        firstLetter +
        "</div>";
    } else {
      html +=
        '<img class="chat-msg-img" src="' +
        message.profile_image +
        '" alt="' +
        senderName +
        '" data-username="' +
        senderName +
        '" onerror="handleImageError(this, \'' +
        senderName +
        "', 'chat-msg-img-placeholder')\" />";
    }
  }
  let createdAt = new Date(message.created_at);
  if (message.sender_id != currentUserId) {
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
$(".delete-email-template").on("click", function (e) {
  e.preventDefault();
  if (confirm("Are you sure want to delete email template?")) {
    window.location.href = $(this).attr("href");
  }
});
function email_id(element) {
  $("#id").val($(element).data("id"));
}

function removeFilesFromClass(className) {
  let filePondElements = document.getElementsByClassName(className);
  for (let i = 0; i < filePondElements.length; i++) {
    let filePond = FilePond.find(filePondElements[i]);
    if (filePond != null) {
      filePond.removeFiles();
    }
  }
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


// CSRF sync on turbo:load removed — ajaxRequest()/formAjaxRequest() (scripts.js) now
// read the csrf-token/csrf-name <meta> tags fresh on every AJAX call, so requests
// never depend on Turbo navigation timing for a valid token.
