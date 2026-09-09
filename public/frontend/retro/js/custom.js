/**
 *
 * You can write your JS code here, DO NOT touch the default style file
 * because it will make it harder for you to update.
 *
 */

"use strict";

$("#identity").keydown(function (e) {
  if (e.which === 38 || e.which === 40) {
    e.preventDefault();
  }
});
$("#number").keydown(function (e) {
  if (e.which === 38 || e.which === 40) {
    e.preventDefault();
  }
});
$("#otp").keydown(function (e) {
  if (e.which === 38 || e.which === 40) {
    e.preventDefault();
  }
});

setTimeout(function () {
  $("#logout_msg").hide("slow");
}, 2000);
$(".otp_show").hide();
$("#step_2").hide();
$("#steper_1").addClass("bg-primary");
$("#steper_2").addClass("bg-dark");

function render() {
  window.recaptchaVerifier = new firebase.auth.RecaptchaVerifier("rec");
  recaptchaVerifier.render();
}
// function for send message
var cd = {};
// const code_result = [];
function phoneAuth(code_result) {
  var number =
    "" +
    document.getElementById("country_code").value +
    document.getElementById("number").value;
  document.getElementById("phone").value =
    document.getElementById("number").value;
  document.getElementById("store_country_code").value =
    document.getElementById("country_code").value;
  // Store OTP identity for flows that need email/phone branching.
  // This is used by `codeverify()` to choose the correct verification endpoint.
  var otpIdentityEl = document.getElementById("otp_identity");
  if (otpIdentityEl) {
    otpIdentityEl.value = document.getElementById("number").value;
  }

  firebase
    .auth()
    .signInWithPhoneNumber(number, window.recaptchaVerifier)
    .then(function (confirmationResult) {
      window.confirmationResult = confirmationResult;
      // console.log(confirmationResult);
      code_result = confirmationResult;
      cd = code_result;

      var btn = $("#sender");
      if (btn.length && btn.data('original-text')) {
        btn.prop('disabled', false).html(btn.data('original-text'));
      }

      $("#send").hide();
      $(".otp_show").show();
      $(".step").html(2);
    })
    .catch(function (error) {
      var btn = $("#sender");
      if (btn.length && btn.data('original-text')) {
        btn.prop('disabled', false).html(btn.data('original-text'));
      }
      alert(error.message);
    });
}

// function for code verify

function sms_codeverify() {
  if ($("#otp").val() == "") {
    Swal.fire({
      icon: "warning",
      title: oops + "...",
      text: please_enter_otp_before_proceeding_any_further,
    });
  } else {
    var code = document.getElementById("otp").value;
    var identityValue = document.getElementById("number").value;
    var isEmail = identityValue.includes('@') || /[a-zA-Z]/.test(identityValue);

    // Choose verification endpoint based on identity type
    var verifyUrl = baseUrl + (isEmail ? "/auth/verify_email_otp" : "/auth/verify_sms_otp");
    var countryCodeEl = document.getElementById("country_code") || document.getElementById("store_country_code");
    var countryCode = (countryCodeEl && countryCodeEl.value) ? String(countryCodeEl.value).trim() : "";
    var numberForVerify = isEmail ? identityValue : (countryCode + identityValue);
    var dataPayload = isEmail ?
      { code: code, email: identityValue } :
      { code: code, number: numberForVerify };

    $.ajax({
      type: "POST",
      url: verifyUrl,
      data: dataPayload,
      dataType: "json",
      success: function (response) {
        if (response.error == false) {
          showToastMessage(response.message, "success");

          // Persist the verification method for registration (if the field exists on the page).
          // email OTP => loginType=email, phone OTP => loginType=phone
          var loginTypeEl = document.getElementById("loginType");
          if (loginTypeEl) {
            loginTypeEl.value = isEmail ? "email" : "phone";
          }

          // Sync step-2 form fields with the verified identity.
          // - If email is verified: prefill email + lock it, allow phone entry.
          // - If phone is verified: prefill phone + lock it, keep country code.
          if (isEmail) {
            var emailEl = document.getElementById("email");
            if (emailEl) {
              emailEl.value = identityValue;
              emailEl.readOnly = true;
            }
            var phoneEl = document.getElementById("phone");
            if (phoneEl) {
              phoneEl.readOnly = false;
            }
          } else {
            var phoneEl = document.getElementById("phone");
            if (phoneEl) {
              phoneEl.value = identityValue;
              phoneEl.readOnly = true;
            }
            var storeCountryCodeEl = document.getElementById("store_country_code");
            var countryCodeEl = document.getElementById("country_code");
            if (storeCountryCodeEl && countryCodeEl) {
              storeCountryCodeEl.value = countryCodeEl.value;
            }
            // When email OTP was used, step 1 has no country; use default for step 2 dropdown
            if (storeCountryCodeEl && !storeCountryCodeEl.value && typeof window.registerDefaultCallingCode !== "undefined") {
              storeCountryCodeEl.value = window.registerDefaultCallingCode;
            }
          }

          $("#step_2").show();
          $("#step_1").hide();
          $(".step").html(3);
          $("#steper_1").addClass("bg-dark");
          $("#steper_2").removeClass("bg-dark");
          $("#steper_2").addClass("bg-primary");
        } else {
          Swal.fire({
            icon: "error",
            title: oops + "...",
            text: entered_otp_is_wrong_please_confirm_it_and_try_again,
          });
        }
      },
    });
  }
}

function codeverify() {
  if ($("#otp").val() == "") {
    Swal.fire({
      icon: "warning",
      title: oops + "...",
      text: please_enter_otp_before_proceeding_any_further,
    });
  } else {
    var code = document.getElementById("otp").value;
    // Prefer `#otp_identity` when present (registration).
    // Fallback to `#phone` (forgot-password flow stores identity there).
    var otpIdentityEl = document.getElementById("otp_identity");
    var phoneIdentityEl = document.getElementById("phone");
    var identityValue = otpIdentityEl ? (otpIdentityEl.value || "") : (phoneIdentityEl ? (phoneIdentityEl.value || "") : "");
    var isEmail = identityValue.includes('@') || /[a-zA-Z]/.test(identityValue);

    // Check if Firebase confirmation result exists (cd is set during phoneAuth)
    // If not, it means we're using email OTP or SMS gateway, not Firebase
    if (typeof cd !== 'undefined' && cd && typeof cd.confirm === 'function') {
      // Firebase OTP verification
      cd.confirm(code)
        .then(function () {
          // Firebase is only used for phone OTP. Keep registration values consistent (if present).
          var loginTypeEl = document.getElementById("loginType");
          if (loginTypeEl) {
            loginTypeEl.value = "phone";
          }
          var phoneEl = document.getElementById("phone");
          if (phoneEl && identityValue) {
            phoneEl.value = identityValue;
            phoneEl.readOnly = true;
          }
          var storeCountryCodeEl = document.getElementById("store_country_code");
          var countryCodeEl = document.getElementById("country_code");
          if (storeCountryCodeEl && countryCodeEl) {
            storeCountryCodeEl.value = countryCodeEl.value;
          }
          if (storeCountryCodeEl && !storeCountryCodeEl.value && typeof window.registerDefaultCallingCode !== "undefined") {
            storeCountryCodeEl.value = window.registerDefaultCallingCode;
          }

          $("#step_2").show();
          $("#step_1").hide();
          $(".step").html(3);

          $("#steper_1").addClass("bg-dark");
          $("#steper_2").removeClass("bg-dark");
          $("#steper_2").addClass("bg-primary");
        })
        .catch(function () {
          Swal.fire({
            icon: "error",
            title: oops + "...",
            text: entered_otp_is_wrong_please_confirm_it_and_try_again,
          });
        });
    } else {
      // Email OTP or SMS gateway verification - use AJAX
      // OTP is stored with identity = country_code + number. Send the same format for verification.
      var verifyUrl = isEmail ? "verify_email_otp" : "verify_sms_otp";
      var countryCodeEl = document.getElementById("country_code") || document.getElementById("store_country_code");
      var countryCode = (countryCodeEl && countryCodeEl.value) ? String(countryCodeEl.value).trim() : "";
      var numberForVerify = isEmail ? identityValue : (countryCode + identityValue);
      var dataPayload = isEmail ?
        { code: code, email: identityValue } :
        { code: code, number: numberForVerify };

      $.ajax({
        type: "POST",
        url: verifyUrl,
        data: dataPayload,
        dataType: "json",
        success: function (response) {
          if (response.error == false) {
            showToastMessage(response.message, "success");

            // Persist verification method for registration (if present).
            var loginTypeEl = document.getElementById("loginType");
            if (loginTypeEl) {
              loginTypeEl.value = isEmail ? "email" : "phone";
            }

            // Sync step-2 form fields with the verified identity.
            if (isEmail) {
              var emailEl = document.getElementById("email");
              if (emailEl) {
                emailEl.value = identityValue;
                emailEl.readOnly = true;
              }
              var phoneEl = document.getElementById("phone");
              if (phoneEl) {
                phoneEl.readOnly = false;
              }
            } else {
              var phoneEl = document.getElementById("phone");
              if (phoneEl) {
                phoneEl.value = identityValue;
                phoneEl.readOnly = true;
              }
              var storeCountryCodeEl = document.getElementById("store_country_code");
              var countryCodeEl = document.getElementById("country_code");
              if (storeCountryCodeEl && countryCodeEl) {
                storeCountryCodeEl.value = countryCodeEl.value;
              }
              if (storeCountryCodeEl && !storeCountryCodeEl.value && typeof window.registerDefaultCallingCode !== "undefined") {
                storeCountryCodeEl.value = window.registerDefaultCallingCode;
              }
            }

            $("#step_2").show();
            $("#step_1").hide();
            $(".step").html(3);
            $("#steper_1").addClass("bg-dark");
            $("#steper_2").removeClass("bg-dark");
            $("#steper_2").addClass("bg-primary");
          } else {
            Swal.fire({
              icon: "error",
              title: oops + "...",
              text: entered_otp_is_wrong_please_confirm_it_and_try_again,
            });
          }
        },
        error: function () {
          Swal.fire({
            icon: "error",
            title: oops + "...",
            text: entered_otp_is_wrong_please_confirm_it_and_try_again,
          });
        }
      });
    }
  }
}

$(document).ready(() => {
  //select2 - only initialize if there are multiple country codes
  setTimeout(() => {
    // Check if country_code element is a select (dropdown) and not a read-only input
    var countryCodeElement = $("#country_code");
    if (countryCodeElement.length > 0 && countryCodeElement.prop('tagName') === 'SELECT') {
      countryCodeElement.select2({
        placeholder: select_country_code,
      });
    }
  }, 100);
});

function sms_phoneAuth() {
  // Keep step-2 fields in sync for SMS gateway flow too.
  // This fixes cases where OTP could be verified but the registration form had empty phone/country_code.
  if (document.getElementById("phone")) {
    document.getElementById("phone").value = document.getElementById("number").value;
  }
  if (document.getElementById("store_country_code")) {
    document.getElementById("store_country_code").value = document.getElementById("country_code").value;
  }
  var otpIdentityEl = document.getElementById("otp_identity");
  if (otpIdentityEl) {
    otpIdentityEl.value = document.getElementById("number").value;
  }

  $.ajax({
    type: "POST",
    url: "send_sms_otp",
    data: {
      number: document.getElementById("number").value,
      country_code: document.getElementById("country_code").value,
    },
    dataType: "json",
    success: function (response) {
      var btn = $("#sender");
      if (btn.length && btn.data('original-text')) {
        btn.prop('disabled', false).html(btn.data('original-text'));
      }

      if (response.error == false) {
        showToastMessage(response.message, "success");
        $("#send").hide();
        $(".otp_show").show();
        $(".step").html(2);
      } else {
        showToastMessage(response.message, "error");
      }
    },
    error: function () {
      var btn = $("#sender");
      if (btn.length && btn.data('original-text')) {
        btn.prop('disabled', false).html(btn.data('original-text'));
      }
    }
  });
}

$("#sender").on("click", function () {
  var btn = $(this);
  if (!btn.data('original-text')) {
    btn.data('original-text', btn.html());
  }
  btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

  var identityValue = (document.getElementById("number").value || "").trim();
  var isEmail = identityValue.includes("@") || /[a-zA-Z]/.test(identityValue);

  // Store identity so OTP verification can reliably decide email vs phone.
  var otpIdentityEl = document.getElementById("otp_identity");
  if (otpIdentityEl) {
    otpIdentityEl.value = identityValue;
  }
  // Default the stored country code (used by provider registration) when available.
  // For email-OTP flow, phone is collected in step 2 but we still want a sane default country code.
  var storeCountryCodeEl = document.getElementById("store_country_code");
  var countryCodeEl = document.getElementById("country_code");
  if (storeCountryCodeEl && countryCodeEl) {
    storeCountryCodeEl.value = countryCodeEl.value || "";
  }

  $.ajax({
    type: "POST",
    url: "check_number",
    data: {
      number: identityValue,
      country_code: document.getElementById("country_code").value,
      // Scope check by login_type so backend only conflicts when same identity + same method (email vs phone).
      login_type: isEmail ? "email" : "phone",
    },

    dataType: "json",
    success: function (response) {
      if (response.error == false) {
        // Email OTP flow (independent of SMS/Firebase mode)
        if (isEmail) {
          sendEmailOTP(identityValue);
          return;
        }

        // Phone OTP flow
        if (response.data.authentication_mode == "sms_gateway") {
          sms_phoneAuth();
        } else if (response.data.authentication_mode == "firebase") {
          phoneAuth();
        }
      } else {
        var btn = $("#sender");
        if (btn.length && btn.data('original-text')) {
          btn.prop('disabled', false).html(btn.data('original-text'));
        }
        showToastMessage(response.message, "error");

        setTimeout(function () {
          window.location.href = baseUrl + "/auth/create_user";
        }, 60000);

      }
    },
    error: function () {
      var btn = $("#sender");
      if (btn.length && btn.data('original-text')) {
        btn.prop('disabled', false).html(btn.data('original-text'));
      }
    }
  });
});
$("#sender_forgot_password").on("click", function () {
  var btn = $(this);
  if (!btn.data('original-text')) {
    btn.data('original-text', btn.html());
  }
  btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');

  // Get the identity value (email or phone)
  var identityValue = $("#number").val();
  var isEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(identityValue);
  var countryCodeValue = isEmail ? "" : $("#country_code").val();
  var userType = $('#userType').val();

  $("#phone").val(identityValue);

  var requestData = {
    identity: identityValue,
    userType: userType,
  };

  if (countryCodeValue != '') {
    requestData.country_code = countryCodeValue;
  }

  $.ajax({
    type: "POST",
    url: baseUrl + "/auth/check_identity_for_forgot_password",
    data: requestData,

    dataType: "json",
    success: function (response) {
      csrfName = response.csrfName;
      csrfHash = response.csrfHash;

      if (response.error == false) {
        // User found - check if email or phone
        if (isEmail) {
          // Email-based forgot password - send OTP to email
          sendEmailOTP(identityValue);
        } else {
          // Phone-based forgot password - send OTP via SMS/Firebase
          if (response.data.authentication_mode == "sms_gateway") {
            SMSphoneAuthForForgotPassword();
          } else if (response.data.authentication_mode == "firebase") {
            phoneAuthForForgotPassword();
          }
        }
      } else {
        // User not found - show error message
        var btn = $("#sender_forgot_password");
        if (btn.length && btn.data('original-text')) {
          btn.prop('disabled', false).html(btn.data('original-text'));
        }
        showToastMessage(response.message, "error");
        setTimeout(function () {
          window.location.href = baseUrl + "/auth/forgot-password/";
        }, 60000);

      }
    },
    error: function (jqXHR, textStatus, errorThrown) {
      var btn = $("#sender_forgot_password");
      if (btn.length && btn.data('original-text')) {
        btn.prop('disabled', false).html(btn.data('original-text'));
      }
      // Handle the error here
      console.error("Ajax request failed:", jqXHR.status, textStatus);
      console.error("Error Details:", errorThrown);

      // Check if the response is HTML or some other non-JSON content
      if (jqXHR.status === 200 && jqXHR.responseText.startsWith("<")) {
        // The response is HTML; handle it as needed
        console.error("HTML response received:", jqXHR.responseText);
        // Display an error message or take appropriate action
      } else {
        // The response is not HTML; it might be another format or an unexpected error
        // Handle it as needed
      }
    },
  });
});

function SMSphoneAuthForForgotPassword() {
  $.ajax({
    type: "POST",
    url: baseUrl + "/auth/send_sms_otp",
    data: {
      number: document.getElementById("number").value,
      country_code: document.getElementById("country_code").value,
    },
    dataType: "json",
    success: function (response) {
      var btn = $("#sender_forgot_password");
      if (btn.length && btn.data('original-text')) {
        btn.prop('disabled', false).html(btn.data('original-text'));
      }

      if (response.error == false) {
        showToastMessage(response.message, "success");
        $("#send").hide();
        $(".otp_show").show();
        setTimeout(function () {
          $(".step").html(2);
        }, 60000);
        // $(".step").html(2);
      } else {
        showToastMessage(response.message, "error");
      }
    },
    error: function () {
      var btn = $("#sender_forgot_password");
      if (btn.length && btn.data('original-text')) {
        btn.prop('disabled', false).html(btn.data('original-text'));
      }
    }
  });
}

// Function to send OTP to email for forgot password
function sendEmailOTP(email) {
  $.ajax({
    type: "POST",
    url: baseUrl + "/auth/send_email_otp",
    data: {
      email: email
    },
    dataType: "json",
    success: function (response) {
      var btn1 = $("#sender");
      if (btn1.length && btn1.data('original-text')) {
        btn1.prop('disabled', false).html(btn1.data('original-text'));
      }
      var btn2 = $("#sender_forgot_password");
      if (btn2.length && btn2.data('original-text')) {
        btn2.prop('disabled', false).html(btn2.data('original-text'));
      }

      if (response.error == false) {
        showToastMessage(response.message, "success");
        $("#send").hide();
        $(".otp_show").show();
        $(".step").html(2);
      } else {
        showToastMessage(response.message, "error");
      }
    },
    error: function (xhr, status, error) {
      var btn1 = $("#sender");
      if (btn1.length && btn1.data('original-text')) {
        btn1.prop('disabled', false).html(btn1.data('original-text'));
      }
      var btn2 = $("#sender_forgot_password");
      if (btn2.length && btn2.data('original-text')) {
        btn2.prop('disabled', false).html(btn2.data('original-text'));
      }
      showToastMessage("Failed to send OTP. Please try again.", "error");
    }
  });
}
function phoneAuthForForgotPassword(code_result) {
  var number =
    "" +
    document.getElementById("country_code").value +
    document.getElementById("number").value;
  document.getElementById("phone").value =
    document.getElementById("number").value;
  document.getElementById("store_country_code").value =
    document.getElementById("country_code").value;

  firebase
    .auth()
    .signInWithPhoneNumber(number, window.recaptchaVerifier)
    .then(function (confirmationResult) {
      var btn = $("#sender_forgot_password");
      if (btn.length && btn.data('original-text')) {
        btn.prop('disabled', false).html(btn.data('original-text'));
      }

      window.confirmationResult = confirmationResult;
      // console.log(confirmationResult);
      code_result = confirmationResult;
      cd = code_result;

      $("#send").hide();
      $(".otp_show").show();
      // $(".step").html(2);

      setTimeout(function () {
        $(".step").html(2);
      }, 60000);
    })
    .catch(function (error) {
      var btn = $("#sender_forgot_password");
      if (btn.length && btn.data('original-text')) {
        btn.prop('disabled', false).html(btn.data('original-text'));
      }
      alert(error.message);
    });
}
$("#register").on("submit", function (e) {
  e.preventDefault();

  var form = $(this);
  $.ajax({
    type: "POST",
    url: baseUrl + "/auth/reset",
    data: form.serialize(),
    dataType: "json",

    success: function (response) {
      // console.log(response);
      // console.log("success");
      if (response.error == false) {
        window.location.href = baseUrl + "/partner/login";
      } else {
        iziToast.error({
          title: "",
          message: response.message,
          position: "topRight",
        });
      }
    },
  });
});

$("#forgot_password").on("submit", function (e) {
  e.preventDefault();

  // Get password values
  var password = $("#password").val();
  var password_confirm = $("#password_confirm").val();

  // Client-side validation: Check if passwords are empty
  if (!password || password.trim() === "") {
    iziToast.error({
      title: "",
      message: "Password is required",
      position: "topRight",
    });
    $("#password").focus();
    return false;
  }

  if (!password_confirm || password_confirm.trim() === "") {
    iziToast.error({
      title: "",
      message: "Confirm password is required",
      position: "topRight",
    });
    $("#password_confirm").focus();
    return false;
  }

  // Client-side validation: Check if passwords match
  if (password !== password_confirm) {
    iziToast.error({
      title: "",
      message: typeof password_mismatch !== 'undefined' ? password_mismatch : "Passwords do not match",
      position: "topRight",
    });
    $("#password").css("border-color", "#FF3300");
    $("#password_confirm").css("border-color", "#FF3300");
    $("#password_confirm").focus();
    return false;
  }

  // Reset border colors if validation passes
  $("#password").css("border-color", "");
  $("#password_confirm").css("border-color", "");

  var form = $(this);
  $.ajax({
    type: "POST",
    url: baseUrl + "/auth/reset_password_otp",
    data: form.serialize(),
    dataType: "json",

    success: function (response) {
      // Handle response message (could be string, array, or object)
      var message = response.message;
      if (typeof message === 'object') {
        // If message is an object/array, convert to string
        if (Array.isArray(message)) {
          message = message.join(', ');
        } else {
          message = JSON.stringify(message);
        }
      }

      if (response.error == false) {
        // Show success toast message
        iziToast.success({
          title: "",
          message: message || "Password reset successfully",
          position: "topRight",
        });
        // Redirect after a short delay to allow user to see the success message
        // Add query parameter to show success message on login page
        // Determine login URL based on userType
        var userType = $("#userType").val();
        var loginUrl = baseUrl + "/partner/login?password_changed=1";
        if (userType === "admin") {
          loginUrl = baseUrl + "/admin/login?password_changed=1";
        }
        setTimeout(function () {
          window.location.href = loginUrl;
        }, 1500);
      } else {
        // Show error toast message
        iziToast.error({
          title: "",
          message: message || "Something went wrong",
          position: "topRight",
        });
      }
    },
    error: function (xhr, status, error) {
      // Handle AJAX errors
      var errorMessage = "Something went wrong";
      if (xhr.responseJSON && xhr.responseJSON.message) {
        var msg = xhr.responseJSON.message;
        if (typeof msg === 'object') {
          if (Array.isArray(msg)) {
            errorMessage = msg.join(', ');
          } else {
            errorMessage = JSON.stringify(msg);
          }
        } else {
          errorMessage = msg;
        }
      }
      iziToast.error({
        title: "",
        message: errorMessage,
        position: "topRight",
      });
    },
  });
});
