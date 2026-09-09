"use strict";
$(document).ready(function () {
  $("#available-slots").hide();
  $(".rescheduled_date").hide();
  $(".work_started_proof").hide();
  $(".work_completed_proof").hide();
  $(".booking_ended_additional_charge").hide();
  const $rescheduleInput = $("#rescheduled_date");
  const rescheduleMinDate = $rescheduleInput.data("min-date") || "";
  const rescheduleMaxDate = $rescheduleInput.data("max-date") || "";
  const rawAdvanceDays = $rescheduleInput.data("advance-days");
  const parsedAdvanceDays =
    rawAdvanceDays === "" || typeof rawAdvanceDays === "undefined"
      ? null
      : parseInt(rawAdvanceDays, 10);
  const allowedAdvanceDays = Number.isNaN(parsedAdvanceDays)
    ? null
    : parsedAdvanceDays;
  const minDateError =
    $rescheduleInput.data("min-error") || "Please select an upcoming date.";
  const maxDateError =
    $rescheduleInput.data("max-error") ||
    "You cannot choose a date beyond allowed advance booking days.";
  const noAdvanceError =
    $rescheduleInput.data("no-advance-error") ||
    "Advanced booking for this partner is not available.";

  const resetRescheduleUI = () => {
    // Clear slots whenever the selected date becomes invalid so we never show stale options.
    $("#available-slots").empty();
  };

  const isBeforeMin = (value) =>
    rescheduleMinDate !== "" && value < rescheduleMinDate;
  const isBeyondMax = (value) =>
    rescheduleMaxDate !== "" && value > rescheduleMaxDate;

  $("#status").change(function (e) {
    e.preventDefault();
    var status = $("#status").val();
    if (status === "rescheduled") {
      $("#available-slots").show();
      $(".rescheduled_date").show();
      $(".work_started_proof").hide();
      $(".work_completed_proof").hide();
      $(".booking_ended_additional_charge").hide();
    } else {
      $("#available-slots").hide();
      $(".rescheduled_date").hide();
      $(".work_started_proof").hide();
      $(".work_completed_proof").hide();
      $(".booking_ended_additional_charge").hide();
    }
    if (status == "started") {
      $(".work_started_proof").show();
      $(".booking_ended_additional_charge").hide();
    } else {
      $(".work_started_proof").hide();
      $(".booking_ended_additional_charge").hide();
    }
    // if (status == "completed") {
    //   $(".work_completed_proof").show();
    //   $(".booking_ended_additional_charge").hide();

    // } else {
    //   $(".work_completed_proof").hide();
    //   $(".booking_ended_additional_charge").hide();

    // }
    if (status == "booking_ended") {
      $(".booking_ended_additional_charge").show();
      $(".work_completed_proof").show();
    } else {
      $(".booking_ended_additional_charge").hide();
      $(".work_completed_proof").hide();
    }
  });
  $("#rescheduled_date").change(function (e) {
    e.preventDefault();
    resetRescheduleUI();
    var date = $("#rescheduled_date").val();
    if (!date) {
      return;
    }
    if (isBeforeMin(date)) {
      showToastMessage(minDateError, "error");
      $(this).val("");
      return;
    }
    if (
      allowedAdvanceDays === 0 &&
      rescheduleMinDate !== "" &&
      date > rescheduleMinDate
    ) {
      showToastMessage(noAdvanceError, "error");
      $(this).val("");
      return;
    }
    if (isBeyondMax(date)) {
      showToastMessage(maxDateError, "error");
      $(this).val("");
      return;
    }
    var id = $("#order_id").val();
    var input_body = {
      [csrfName]: csrfHash,
      id: id,
      date: date,
    };
    $.ajax({
      type: "POST",
      url: baseUrl + "/partner/orders/get_slots",
      data: input_body,
      dataType: "JSON",
      success: function (response) {
        // New SlotService shape:
        // { error, message, data: { all_slots: [ { time:"HH:MM:SS", is_available:0|1, remaining_capacity, shift_id, message? } ] } }
        var $slotsContainer = $("#available-slots").empty();

        // "HH:MM:SS" 24h -> "hh:MM AM/PM" for display. Strips angle brackets as XSS net.
        function slotDisplayTime(t) {
          t = String(t == null ? "" : t);
          var parts = t.split(":");
          if (parts.length < 2) return t.replace(/[<>]/g, "");
          var h = parseInt(parts[0], 10);
          var m = parts[1];
          if (isNaN(h)) return t.replace(/[<>]/g, "");
          var ap = h >= 12 ? "PM" : "AM";
          var h12 = h % 12;
          if (h12 === 0) h12 = 12;
          // Non-breaking space keeps "09:00 AM" from wrapping out of the slot button.
          return (h12 < 10 ? "0" + h12 : h12) + ":" + m + " " + ap;
        }

        function showMessage(text, cls) {
          var $col = $('<div class="col-md-12 form-group"></div>');
          var $sg = $('<div class="selectgroup"></div>').appendTo($col);
          var $lbl = $('<label class="selectgroup-item"></label>').appendTo($sg);
          $("<span></span>")
            .addClass(cls)
            .text(text == null ? "" : text)
            .appendTo($lbl);
          $slotsContainer.append($col);
        }

        if (response.error == true) {
          showMessage(response.message, "text-danger");
          setTimeout(() => {
            $("#ordered_services_list").bootstrapTable("refresh");
          }, 2000);
          return;
        }

        var allSlots =
          response.data && response.data.all_slots
            ? response.data.all_slots
            : [];
        var available = allSlots.filter(function (s) {
          return Number(s.is_available) === 1;
        });

        if (available.length === 0) {
          showMessage(
            $slotsContainer.data("empty-msg") ||
              "No slot available on this date!",
            "text-danger"
          );
          return;
        }

        // SECURITY: DOM nodes built manually; the slot time is only ever inserted
        // as plain text or an input value, never as raw HTML (DOM-XSS safe).
        available.forEach(function (slot) {
          var rawTime = String(slot.time == null ? "" : slot.time);

          // Responsive Bootstrap grid: 2/3/4/6 per row, mb-3 gutter spacing.
          var $col = $('<div class="col-6 col-md-3 mb-3"></div>');

          // Plain full-width label button. The theme .selectgroup component
          // (inline-flex) kept collapsing the button to icon width and
          // spilling the time text out of the column, so it is not used here.
          var $label = $(
            '<label class="reschedule-slot d-block mb-0 text-center"></label>'
          ).css({
            border: "1px solid #e4e6fc",
            "border-radius": "4px",
            background: "#fdfdff",
            padding: "8px 6px",
            "font-size": "13px",
            color: "#191d21",
            cursor: "pointer",
            width: "100%",
            "box-sizing": "border-box",
          });

          // Keep .selectgroup-input class: the submit handler counts it
          // ($(".selectgroup-input").length > 1). Radio value = 24h HH:MM:SS
          // the slot engine expects on reschedule.
          $('<input type="radio" name="reschedule" class="selectgroup-input">')
            .val(rawTime)
            .css({ position: "absolute", opacity: 0, width: 0, height: 0 })
            .appendTo($label);

          $('<i class="fas fa-sun d-block"></i>').appendTo($label);
          $label.append(" ");
          $('<span class="d-block mt-1"></span>')
            .text(slotDisplayTime(rawTime))
            .appendTo($label);

          // Spillover hint (e.g. "Order scheduled for the multiple days").
          if (slot.message) {
            $('<small class="d-block text-warning"></small>')
              .css("white-space", "normal")
              .text(slot.message)
              .appendTo($label);
          }

          $col.append($label);
          $slotsContainer.append($col);
        });

        // Highlight the picked slot (no theme :checked CSS used here).
        $slotsContainer
          .off("change.reschedule")
          .on("change.reschedule", 'input[name="reschedule"]', function () {
            $slotsContainer.find("label.reschedule-slot").css({
              background: "#fdfdff",
              color: "#191d21",
              "border-color": "#e4e6fc",
            });
            $(this).closest("label.reschedule-slot").css({
              background: "var(--primary-color)",
              color: "#fff",
              "border-color": "var(--primary-color)",
            });
          });
      },
    });
  });
  $("#change_status").on("click", function (e) {
    e.preventDefault();
    var status = $("#status").val();

    if (status === "on_the_way" && navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(
        function (pos) {
          submitStatusChange(status, pos.coords.latitude, pos.coords.longitude);
        },
        function () {
          submitStatusChange(status, null, null);
        },
        { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 }
      );
      return;
    }

    submitStatusChange(status, null, null);
  });

  function submitStatusChange(status, latitude, longitude) {
    if (status === "completed" && window.ADDITIONAL_CHARGE_PAYMENT_PENDING) {
      $("#additionalChargePendingModal").modal("show");
      return;
    }
    var order_id = $("#order_id").val();
    var date = $("#rescheduled_date").val();
    var is_otp_enable = $("#is_otp_enable").val();
    var selected_time = "";
    var formdata = new FormData($("#myForm")[0]);
    if (latitude != null && longitude != null) {
      formdata.append("latitude", latitude);
      formdata.append("longitude", longitude);
    }
    if ($(".selectgroup-input").length > 1) {
      selected_time = $('input[name="reschedule"]:checked').val();
    }
    if (is_otp_enable == 1) {
      if (status == "completed") {
        Swal.fire({
          title: are_your_sure,
          text: you_wont_be_able_to_revert_this,
          icon: "error",
          input: "number",
          inputPlaceholder: enter_otp_here,
          inputAttributes: {
            autocapitalize: "off",
            required: "true",
          },
          showCancelButton: true,
          cancelButtonText: cancel,
          confirmButtonText: yes_proceed,
        }).then((result) => {
          if (result.value) {
            formdata.append("otp", result.value);
            $.ajaxSetup({
              headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
              },
            });
            $.ajax({
              url: baseUrl + "/partner/orders/update_order_status",
              data: formdata,
              processData: false,
              contentType: false,
              type: "post",
              dataType: "json",
              beforeSend: function () {
                $("#change_status").attr("disabled", true);
                $("#change_status").removeClass("btn-primary");
                $("#change_status").addClass("btn-secondary");
                $("#change_status").html(
                  '<div class="spinner-border text-primary spinner-border-sm mx-3" role="status"><span class="visually-hidden"></span></div>'
                );
              },
              success: function (response) {
                // Parse response if it's a string
                if (typeof response === 'string') {
                  try {
                    response = JSON.parse(response);
                  } catch (e) {
                    console.error('Failed to parse response:', e);
                  }
                }
                
                if (response.error == false) {
                  showToastMessage(response.message, "success");
                  
                  // Track Microsoft Clarity booking events
                  if (response.data && response.data.clarity_event) {
                    var eventType = response.data.clarity_event;
                    var bookingId = response.data.booking_id;
                    var status = response.data.status;
                    var customerId = response.data.customer_id;
                    
                    if (eventType === 'booking_accepted' && typeof trackBookingAccepted === 'function') {
                      trackBookingAccepted(bookingId, status, customerId);
                    } else if (eventType === 'booking_rejected' && typeof trackBookingRejected === 'function') {
                      trackBookingRejected(bookingId, status, customerId);
                    } else if (eventType === 'booking_cancelled' && typeof trackBookingCancelled === 'function') {
                      trackBookingCancelled(bookingId, status, customerId);
                    } else if (eventType === 'booking_completed' && typeof trackBookingCompleted === 'function') {
                      trackBookingCompleted(bookingId, status, customerId);
                    } else if (eventType === 'booking_status_updated' && typeof trackBookingStatusUpdated === 'function') {
                      trackBookingStatusUpdated(bookingId, status, customerId);
                    }
                  }
                  
                  setTimeout(() => {
                    window.location.reload(true);
                  }, 2000);
                } else {
                  showToastMessage(response.message, "error");
                  setTimeout(() => {
                    window.location.reload(true);
                  }, 2000);
                }
                return;
              },
              error: function (response) {},
            });
          }
        });
      } else {
        $.ajaxSetup({
          headers: {
            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
          },
        });
        $.ajax({
          url: baseUrl + "/partner/orders/update_order_status",
          data: formdata,
          type: "post",
          dataType: "json",
          processData: false,
          contentType: false,
          beforeSend: function () {
            $("#change_status").attr("disabled", true);
            $("#change_status").removeClass("btn-primary");
            $("#change_status").addClass("btn-secondary");
            $("#change_status").html(
              '<div class="spinner-border text-primary spinner-border-sm mx-3" role="status"><span class="visually-hidden"></span></div>'
            );
          },
          success: function (response) {
            // Parse response if it's a string
            if (typeof response === 'string') {
              try {
                response = JSON.parse(response);
              } catch (e) {
                console.error('Failed to parse response:', e);
              }
            }
            
            if (response.error == false) {
                showToastMessage(response.message, "success");
              
              // Track Microsoft Clarity booking events
              if (response.data && response.data.clarity_event) {
                var eventType = response.data.clarity_event;
                var bookingId = response.data.booking_id;
                var status = response.data.status;
                var customerId = response.data.customer_id;
                
                if (eventType === 'booking_accepted' && typeof trackBookingAccepted === 'function') {
                  trackBookingAccepted(bookingId, status, customerId);
                } else if (eventType === 'booking_rejected' && typeof trackBookingRejected === 'function') {
                  trackBookingRejected(bookingId, status, customerId);
                } else if (eventType === 'booking_cancelled' && typeof trackBookingCancelled === 'function') {
                  trackBookingCancelled(bookingId, status, customerId);
                } else if (eventType === 'booking_completed' && typeof trackBookingCompleted === 'function') {
                  trackBookingCompleted(bookingId, status, customerId);
                } else if (eventType === 'booking_status_updated' && typeof trackBookingStatusUpdated === 'function') {
                  trackBookingStatusUpdated(bookingId, status, customerId);
                }
              }
              
              setTimeout(() => {
                window.location.reload(true);
              }, 2000);
            } else {
              showToastMessage(response.message, "error");
              setTimeout(() => {
                window.location.reload(true);
              }, 2000);
            }
            return;
          },
          error: function (xhr) {
            showToastMessage(response.message, "error");
            setTimeout(() => {
              window.location.reload(true);
            }, 2000);
          },
        });
      }
    } else {
      $.ajaxSetup({
        headers: {
          "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
        },
      });
      $.ajax({
        url: baseUrl + "/partner/orders/update_order_status",
        data: formdata,
        processData: false,
        contentType: false,
        type: "post",
        dataType: "json",
        beforeSend: function () {
          $("#change_status").attr("disabled", true);
          $("#change_status").removeClass("btn-primary");
          $("#change_status").addClass("btn-secondary");
          $("#change_status").html(
            '<div class="spinner-border text-primary spinner-border-sm mx-3" role="status"><span class="visually-hidden"></span></div>'
          );
        },
        success: function (response) {
          if (response.error == false) {
            showToastMessage(response.message, "success");
            setTimeout(() => {
              window.location.reload(true);
            }, 2000);
          } else {
            showToastMessage(response.message, "error");
            setTimeout(() => {
              window.location.reload(true);
            }, 2000);
          }
          return;
        },
        error: function (response) {
          showToastMessage(response.message, "error");
          setTimeout(() => {
            window.location.reload(true);
          }, 2000);
        },
      });
    }
  }
});
window.order_service_event = {
  "click .cancel_service": function (e, value, row, index) {},
};
