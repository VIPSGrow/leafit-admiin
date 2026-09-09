"use strict";

/**
 * Central notification redirect handler (Partner + Admin web panels)
 * -----------------------------------------------------------------
 *
 * Goal:
 * - Provide ONE place to decide "where to go" when a notification is clicked.
 *
 * Current rollout:
 * - Provider/Partner panel first.
 * - Admin panel can enable it by rendering `data-notification-context` the same way.
 *
 * Expected HTML:
 * - An anchor element that contains:
 *   - `data-notification-context="<urlencoded-json>"`
 *   - optional `data-notification-panel="partner|admin"`
 *
 * Context keys supported (from backend `context_data`):
 * - `order_id` or `booking_id`                 -> booking/order detail
 * - `subscription_id` + `provider_id`          -> provider subscription view page (admin)
 * - `service_id`                               -> service detail (admin) / service edit (partner)
 * - `category_id`                              -> categories list
 *
 * Notes:
 * - We intentionally keep this file dependency-free beyond jQuery (already used across panels).
 * - Partner panel requirement: mark notification as read on click (before redirect).
 */
(function (window, $) {
  if (!$) return;

  /**
   * Normalize a URL stored in DB / HTML attributes.
   *
   * Why this helper exists:
   * - Notifications.url may be:
   *   - absolute: https://...
   *   - root-relative: /partner/orders
   *   - baseUrl-prefixed: https://site.com/partner/orders
   *   - path-only: partner/orders (BAD if used as-is; browser would resolve relative to current path)
   *
   * We normalize it so redirects are consistent from any page.
   */
  function normalizeUrl(rawUrl) {
    var u = String(rawUrl || "").trim();
    if (!u) return "";

    // Absolute URL (http/https)
    if (/^https?:\/\//i.test(u)) return u;

    // Root-relative URL
    if (u.indexOf("/") === 0) return u;

    // baseUrl-prefixed URL (already absolute)
    var base = getBaseUrl();
    if (base && u.indexOf(base) === 0) return u;

    // Plain path -> make it root-based
    return joinUrl(base, u);
  }

  function getBaseUrl() {
    // Both partner/admin templates define `baseUrl` globally.
    var b = window.baseUrl || window.base_url || "";
    return String(b || "").replace(/\/+$/, "");
  }

  function joinUrl(base, path) {
    base = String(base || "").replace(/\/+$/, "");
    path = String(path || "").replace(/^\/+/, "");
    if (!base) return "/" + path;
    return base + "/" + path;
  }

  function safeJsonParse(str) {
    try {
      var v = JSON.parse(str);
      return v && typeof v === "object" ? v : null;
    } catch (e) {
      return null;
    }
  }

  function detectPanel() {
    // Prefer explicit markers; fallback to URL path.
    if ($("#partner-notification-dropdown").length) return "partner";
    if ($("#admin-notification-dropdown").length) return "admin";
    if ($("#handyman-notification-dropdown").length) return "handyman";

    var p = String(window.location && window.location.pathname ? window.location.pathname : "");
    if (p.indexOf("/partner") === 0) return "partner";
    if (p.indexOf("/admin") === 0) return "admin";
    if (p.indexOf("/handyman") === 0) return "handyman";
    return "";
  }

  /**
   * Get first non-empty value from context for given keys.
   * Used by admin event-type redirects to resolve (:any) from context_data.
   */
  function getContextValue(context, keys) {
    if (!context || typeof context !== "object" || !keys || !keys.length) return null;
    for (var i = 0; i < keys.length; i++) {
      var v = context[keys[i]];
      if (v !== undefined && v !== null && String(v).trim() !== "") return String(v).trim();
    }
    return null;
  }

  /**
   * Admin-only: build redirect URL from event_type + context_data.
   * Routes source: app/Config/Routes_admin.php.
   * Returns full URL or "" if no rule matches or required context is missing.
   */
  function buildAdminRedirectByEventType(eventType, context) {
    var base = getBaseUrl();
    var type = String(eventType || "").toLowerCase();
    if (!type) return "";

    // 1) provider_update_information -> admin/partners/general_outlook/(:any) [provider_id]
    if (type === "provider_update_information") {
      var pid = getContextValue(context, ["provider_id"]);
      if (pid) return joinUrl(base, "admin/partners/general_outlook/" + encodeURIComponent(pid));
      return "";
    }
    // 2) new_user_registered -> admin/users
    if (type === "new_user_registered") return joinUrl(base, "admin/users");
    // 3) payment_refund_successful -> admin/payment_refunds
    if (type === "payment_refund_successful") return joinUrl(base, "admin/payment_refunds");
    // 4) new_provider_registerd -> admin/partners/general_outlook/(:any) [provider_id]
    if (type === "new_provider_registerd") {
      pid = getContextValue(context, ["provider_id"]);
      if (pid) return joinUrl(base, "admin/partners/general_outlook/" + encodeURIComponent(pid));
      return "";
    }
    // 5) new_booking_received_for_provider -> admin/orders/veiw_orders/(:any) [order_id|booking_id]
    if (type === "new_booking_received_for_provider") {
      var orderOrBookingId = getContextValue(context, ["order_id", "booking_id"]);
      if (orderOrBookingId) return joinUrl(base, "admin/orders/veiw_orders/" + encodeURIComponent(orderOrBookingId));
      return "";
    }
    // 6) new_custom_job_request -> admin/custom-job-requests
    if (type === "new_custom_job_request") return joinUrl(base, "admin/custom-job-requests");
    // 7) withdraw_request_received -> admin/partners/payment_request
    if (type === "withdraw_request_received") return joinUrl(base, "admin/partners/payment_request");
    // 8) provider_edits_service_details -> admin/services/service_detail/(:any) [service_id]
    if (type === "provider_edits_service_details") {
      var sid = getContextValue(context, ["service_id"]);
      if (sid) return joinUrl(base, "admin/services/service_detail/" + encodeURIComponent(sid));
      return "";
    }
    // 9) user_query_submitted -> admin/customer_queris
    if (type === "user_query_submitted") return joinUrl(base, "admin/customer_queris");
    // 10) subscription_purchased -> admin/partners/partner_subscription/(:any) [provider_id]
    if (type === "subscription_purchased") {
      pid = getContextValue(context, ["provider_id"]);
      if (pid) return joinUrl(base, "admin/partners/partner_subscription/" + encodeURIComponent(pid));
      return "";
    }
    // 11) cash_collection_by_provider -> admin/partners/cash_collection
    if (type === "cash_collection_by_provider") return joinUrl(base, "admin/partners/cash_collection");
    // 12) booking lifecycle events -> admin/orders/veiw_orders/(:any) [booking_id|order_id]
    var bookingLifecycleTypes = ["booking_confirmed", "booking_started", "booking_cancelled", "booking_completed", "booking_ended", "booking_rescheduled"];
    if (bookingLifecycleTypes.indexOf(type) !== -1) {
      orderOrBookingId = getContextValue(context, ["booking_id", "order_id"]);
      if (orderOrBookingId) return joinUrl(base, "admin/orders/veiw_orders/" + encodeURIComponent(orderOrBookingId));
      return "";
    }
    // 13) subscription_expired -> admin/partners/partner_subscription/(:any) [provider_id]
    if (type === "subscription_expired") {
      pid = getContextValue(context, ["provider_id"]);
      if (pid) return joinUrl(base, "admin/partners/partner_subscription/" + encodeURIComponent(pid));
      return "";
    }
    // 14) user_blocked -> admin/user_reports
    if (type === "user_blocked") return joinUrl(base, "admin/user_reports");
    // 15) promo_code_added -> admin/promo_codes
    if (type === "promo_code_added") return joinUrl(base, "admin/promo_codes");
    // 16) user_reported -> admin/user_reports
    // if (type === "user_reported") return joinUrl(base, "admin/user_reports");

    return "";
  }

  /**
   * Partner-only: build redirect URL from event_type + context_data.
   * Routes source: app/Config/Routes_partner.php and app/Config/Routes.php (reported_users).
   * Returns full URL or "" if no rule matches or required context is missing.
   * Event types match notifications.type (exact strings).
   */
  function buildPartnerRedirectByEventType(eventType, context) {
    var base = getBaseUrl();
    var type = String(eventType || "").toLowerCase();
    if (!type) return "";

    // 1) provider_approved, provider_disapproved -> partner/dashboard
    if (type === "provider_approved" || type === "provider_disapproved") {
      return joinUrl(base, "partner/dashboard");
    }
    // 2) withdraw_request_approved, withdraw_request_disapproved -> partner/withdrawal_requests
    if (type === "withdraw_request_approved" || type === "withdraw_request_disapproved") {
      return joinUrl(base, "partner/withdrawal_requests");
    }
    // 3) payment_settlement -> partner/settlement_cashcollection_history
    if (type === "payment_settlement") {
      return joinUrl(base, "partner/settlement_cashcollection_history");
    }
    // 4) service_approved, service_disapproved -> partner/services
    if (type === "service_approved" || type === "service_disapproved") {
      return joinUrl(base, "partner/services");
    }
    // 5) new_rating_given_by_customer -> partner/review
    if (type === "new_rating_given_by_customer") {
      return joinUrl(base, "partner/review");
    }
    // 6) cash_collection_by_provider -> partner/cash_collection
    if (type === "cash_collection_by_provider") {
      return joinUrl(base, "partner/cash_collection");
    }
    // 7) new_custom_job_request -> partner/JobRequests
    if (type === "new_custom_job_request") {
      return joinUrl(base, "partner/JobRequests");
    }
    // 8) new_booking_received_for_provider -> partner/orders/veiw_orders/(:any) [booking_id|order_id]
    if (type === "new_booking_received_for_provider") {
      var orderOrBookingId = getContextValue(context, ["booking_id", "order_id"]);
      if (orderOrBookingId) return joinUrl(base, "partner/orders/veiw_orders/" + encodeURIComponent(orderOrBookingId));
      return "";
    }
    // 9) new_category_available, category_removed -> partner/categories
    if (type === "new_category_available" || type === "category_removed") {
      return joinUrl(base, "partner/categories");
    }
    // 10) user_reported, user_blocked -> partner/reported_users
    if (type === "user_blocked") { //type === "user_reported" || 
      return joinUrl(base, "partner/reported_users");
    }
    // 11) promo_code_added -> partner/promo_codes
    if (type === "promo_code_added") {
      return joinUrl(base, "partner/promo_codes");
    }
    // 12) subscription lifecycle -> partner/subscription
    var subscriptionTypes = [
      "subscription_payment_successful", "subscription_payment_failed", "subscription_payment_pending",
      "subscription_expired", "subscription_changed", "subscription_removed"
    ];
    if (subscriptionTypes.indexOf(type) !== -1) {
      return joinUrl(base, "partner/subscription");
    }
    // 13) privacy_policy_changed, terms_and_conditions_changed -> partner/dashboard
    if (type === "privacy_policy_changed" || type === "terms_and_conditions_changed") {
      return joinUrl(base, "partner/dashboard");
    }

    return "";
  }

  function buildRedirectUrl(panel, context) {
    if (!context || typeof context !== "object") return "";

    var base = getBaseUrl();
    var orderId = context.order_id || context.booking_id;

    // 1) Order / booking detail
    if (orderId !== undefined && orderId !== null && String(orderId).trim() !== "") {
      if (panel === "partner") {
        // Route source: app/Config/Routes_partner.php
        // partner/orders/veiw_orders/(:any)
        return joinUrl(base, "partner/orders/veiw_orders/" + encodeURIComponent(String(orderId)));
      }
      if (panel === "admin") {
        // Route source: app/Config/Routes_admin.php
        // admin/orders/veiw_orders/(:any)
        // NOTE: spelling is "veiw_orders" in routes (legacy).
        return joinUrl(base, "admin/orders/veiw_orders/" + encodeURIComponent(String(orderId)));
      }
      if (panel === "handyman") {
        // Route source: app/Config/Routes_handyman.php
        // handyman/bookings/(:num)
        return joinUrl(base, "handyman/bookings/" + encodeURIComponent(String(orderId)));
      }
    }

    // 2) Subscription view (admin) / subscription list (partner)
    // Requirement:
    // - If both `subscription_id` and `provider_id` exist, redirect to the subscription view page.
    var subscriptionId = context.subscription_id;
    var providerId = context.provider_id;
    if (subscriptionId !== undefined && subscriptionId !== null && String(subscriptionId).trim() !== "") {
      // Admin panel: provider subscription view page
      if (panel === "admin" && providerId !== undefined && providerId !== null && String(providerId).trim() !== "") {
        // Route source: app/Config/Routes_admin.php
        // admin/partners/partner_subscription/(:any)
        return joinUrl(base, "admin/partners/partner_subscription/" + encodeURIComponent(String(providerId)));
      }

      // Partner panel: subscription list page (partners manage their own subscription).
      if (panel === "partner") return joinUrl(base, "partner/subscription");
      if (panel === "admin") return joinUrl(base, "admin/subscription");
    }

    // 3) Service detail
    if (context.service_id !== undefined && context.service_id !== null && String(context.service_id).trim() !== "") {
      var sid = encodeURIComponent(String(context.service_id));
      if (panel === "admin") {
        // Route source: app/Config/Routes_admin.php
        // admin/services/service_detail/(:any)
        return joinUrl(base, "admin/services/service_detail/" + sid);
      }
      if (panel === "partner") {
        // Route source: app/Config/Routes_partner.php
        // partner/services/edit_service/(:any)
        return joinUrl(base, "partner/services/edit_service/" + sid);
      }
    }

    // 4) Categories list
    if (context.category_id !== undefined && context.category_id !== null && String(context.category_id).trim() !== "") {
      if (panel === "partner") return joinUrl(base, "partner/categories");
      if (panel === "admin") return joinUrl(base, "admin/categories");
    }

    return "";
  }

  function readContextFromElement($el) {
    var raw = $el.attr("data-notification-context") || "";
    if (!raw) return null;

    // Our renderer encodes JSON using encodeURIComponent(JSON.stringify(...))
    try {
      raw = decodeURIComponent(raw);
    } catch (e) {
      // If decoding fails, try parsing the raw string anyway.
    }

    return safeJsonParse(raw);
  }

  function updateCsrfIfPresent(resp) {
    if (!resp || typeof resp !== "object") return;
    if (resp.csrfName) window.csrfName = resp.csrfName;
    if (resp.csrfHash) window.csrfHash = resp.csrfHash;
  }

  /**
   * POST helper backed by native fetch, not $.ajax.
   *
   * Why: jQuery's XHR transport throws "Cannot read properties of undefined
   * (reading 'open')" when called after a Turbo morph navigation (same
   * jQuery/Turbo interaction worked around for bootstrap-table in scripts.php
   * and for the chat sidebar badge in sidebar.php). Returns a jQuery Deferred
   * promise so existing .done()/.fail()/.always() call sites are unaffected;
   * on failure the rejected value exposes .status/.responseJSON like a jqXHR.
   */
  function ajaxPostJSON(endpoint, payload, timeoutMs) {
    var dfd = $.Deferred();
    var body = new URLSearchParams();
    Object.keys(payload).forEach(function (key) { body.append(key, payload[key]); });

    var controller = (typeof AbortController !== "undefined") ? new AbortController() : null;
    var timer = controller ? setTimeout(function () { controller.abort(); }, timeoutMs) : null;

    fetch(endpoint, {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
        "Accept": "application/json",
        "X-Requested-With": "XMLHttpRequest"
      },
      body: body,
      signal: controller ? controller.signal : undefined
    }).then(function (res) {
      if (timer) clearTimeout(timer);
      return res.json().catch(function () { return null; }).then(function (json) {
        if (!res.ok) {
          dfd.reject({ status: res.status, responseJSON: json });
          return;
        }
        dfd.resolve(json);
      });
    }).catch(function () {
      if (timer) clearTimeout(timer);
      dfd.reject({ status: 0, responseJSON: null });
    });

    return dfd.promise();
  }

  /**
   * Return list of redirect targets that need validation (order_id or provider_id in context).
   * Each item: { type: "order"|"provider", id: number }.
   * Used so we can call validate_redirect_target before redirecting.
   */
  function getRedirectValidationTargetsFromContext(context) {
    var list = [];
    if (!context || typeof context !== "object") return list;

    var orderId = getContextValue(context, ["order_id", "booking_id"]);
    if (orderId) {
      var oid = parseInt(orderId, 10);
      if (!isNaN(oid) && oid > 0) list.push({ type: "order", id: oid });
    }

    var providerId = context.provider_id;
    if (providerId !== undefined && providerId !== null && String(providerId).trim() !== "") {
      var pid = parseInt(providerId, 10);
      if (!isNaN(pid) && pid > 0) list.push({ type: "provider", id: pid });
    }

    return list;
  }

  /**
   * Parse destination URL and return validation targets when path is order or provider detail.
   * Use when context is missing so we still validate before redirect (e.g. link from event_type or stored url).
   */
  function getRedirectValidationTargetsFromDestination(destination) {
    var list = [];
    if (!destination || typeof destination !== "string") return list;

    var path = destination;
    try {
      if (destination.indexOf("http") === 0) {
        var u = new URL(destination);
        path = u.pathname || "";
      } else if (destination.indexOf("/") === 0 || destination.indexOf(getBaseUrl()) === 0) {
        path = destination.replace(getBaseUrl(), "").replace(/^\//, "");
      }
    } catch (e) {
      return list;
    }

    // admin/orders/veiw_orders/123 or partner/orders/veiw_orders/123 (path may have leading / or not)
    var orderMatch = path.match(/(?:^|\/)(?:admin|partner)\/orders\/veiw_orders\/(\d+)/i);
    if (orderMatch) {
      var oid = parseInt(orderMatch[1], 10);
      if (!isNaN(oid) && oid > 0) list.push({ type: "order", id: oid });
    }

    // admin/partners/general_outlook/123 or admin/partners/partner_subscription/123
    var providerMatch = path.match(/(?:^|\/)admin\/partners\/(?:general_outlook|partner_subscription)\/(\d+)/i);
    if (providerMatch) {
      var pid = parseInt(providerMatch[1], 10);
      if (!isNaN(pid) && pid > 0) list.push({ type: "provider", id: pid });
    }

    return list;
  }

  /**
   * Combined: validation targets from context and from destination URL, deduped by type+id.
   */
  function getRedirectValidationTargets(context, destination) {
    var seen = {};
    var list = [];
    function add(t) {
      var key = t.type + ":" + t.id;
      if (!seen[key]) {
        seen[key] = true;
        list.push(t);
      }
    }
    getRedirectValidationTargetsFromContext(context || null).forEach(add);
    getRedirectValidationTargetsFromDestination(destination || "").forEach(add);
    return list;
  }

  /**
   * Call backend validate_redirect_target for one target.
   * Returns a jQuery promise that resolves to { exists: boolean, message: string }.
   */
  function validateRedirectTarget(panel, targetType, targetId) {
    var endpoint = panel === "partner"
      ? joinUrl(getBaseUrl(), "partner/notifications/validate_redirect_target")
      : panel === "handyman"
      ? joinUrl(getBaseUrl(), "handyman/notifications/validate_redirect_target")
      : joinUrl(getBaseUrl(), "admin/notifications/validate_redirect_target");

    var payload = {};
    if (window.csrfName && window.csrfHash) {
      payload[window.csrfName] = window.csrfHash;
    }
    payload.target_type = targetType;
    payload.target_id = targetId;

    return ajaxPostJSON(endpoint, payload, 5000).then(function (resp) {
      updateCsrfIfPresent(resp);
      return { exists: resp && resp.exists === true, message: (resp && resp.message) ? resp.message : "" };
    }, function (xhr) {
      var resp = xhr && xhr.responseJSON ? xhr.responseJSON : null;
      updateCsrfIfPresent(resp);
      var fallbackMsg = typeof window.i18n_validation_failed !== 'undefined' ? window.i18n_validation_failed : "Validation failed.";
      return { exists: false, message: (resp && resp.message) ? resp.message : fallbackMsg };
    });
  }

  /**
   * Run all validation targets; resolve with true only if every target still exists.
   * Otherwise resolve with false and the first failure message for the toast.
   */
  function validateAllRedirectTargets(panel, targets) {
    if (!targets || targets.length === 0) return $.Deferred().resolve(true, "").promise();

    var firstFailMessage = "";
    var done = 0;
    var allExist = true;
    var dfd = $.Deferred();

    function checkDone() {
      done++;
      if (done === targets.length) {
        dfd.resolve(allExist, firstFailMessage);
      }
    }

    targets.forEach(function (t) {
      validateRedirectTarget(panel, t.type, t.id).done(function (result) {
        if (!result.exists) {
          allExist = false;
          if (!firstFailMessage) firstFailMessage = result.message || "User no longer exists.";
        }
        checkDone();
      });
    });

    return dfd.promise();
  }

  /**
   * Show a toast that the redirect target no longer exists.
   * Uses showToastMessage (admin/partner layout) if available, else iziToast, else alert.
   */
  function showNoLongerExistsToast(message) {
    var msg = message || "User no longer exists.";
    if (typeof window.showToastMessage === "function") {
      window.showToastMessage(msg, "error");
      return;
    }
    if (typeof window.iziToast !== "undefined" && window.iziToast && window.iziToast.error) {
      window.iziToast.error({ title: "", message: msg, position: "topRight" });
      return;
    }
    window.alert(msg);
  }

  function updateUnreadBadge(panel, unreadCount) {
    var safe = parseInt(unreadCount, 10);
    if (isNaN(safe) || safe < 0) safe = 0;

    if (panel === "partner") {
      // Requirement:
      // - Hide when unread count is empty/0.
      // - Show and display the number only when > 0.
      var $badgeP = $("#partner-notification-count");
      if (safe <= 0) {
        $badgeP.text("").hide();
      } else {
        $badgeP.text(String(safe)).show();
      }
      $badgeP.attr("aria-label", "Unread notifications: " + safe);
      return;
    }
    if (panel === "admin") {
      // Requirement:
      // - Hide when unread count is empty/0.
      // - Show and display the number only when > 0.
      var $badgeA = $("#admin-notification-count");
      if (safe <= 0) {
        $badgeA.text("").hide();
      } else {
        $badgeA.text(String(safe)).show();
      }
      $badgeA.attr("aria-label", "Unread notifications: " + safe);
      return;
    }
    if (panel === "handyman") {
      // Badge is Alpine-owned (x-text="unreadCount" / x-show="unreadCount > 0" in
      // navbar.php), and persists across Turbo navigations via data-turbo-permanent.
      // Writing the DOM directly here would desync from Alpine's reactive state and
      // cause it to snap back to the stale value on the next Alpine re-render/nav.
      var handymanDropdownEl = document.getElementById("handyman-notification-dropdown");
      var handymanComponent = (handymanDropdownEl && typeof window.Alpine !== "undefined")
        ? window.Alpine.$data(handymanDropdownEl)
        : null;
      if (handymanComponent) {
        handymanComponent.unreadCount = safe;
        return;
      }
      var $badgeH = $("#handyman-notification-count");
      if (safe <= 0) {
        $badgeH.text("").hide();
      } else {
        $badgeH.text(String(safe)).show();
      }
      $badgeH.attr("aria-label", "Unread notifications: " + safe);
      return;
    }
  }

  function markNotificationRead(panel, notificationId) {
    var id = parseInt(notificationId, 10);
    if (isNaN(id) || id <= 0) return $.Deferred().resolve().promise();

    var endpoint = panel === "partner"
      ? joinUrl(getBaseUrl(), "partner/notifications/mark_read")
      : panel === "handyman"
      ? joinUrl(getBaseUrl(), "handyman/notifications/mark_read")
      : joinUrl(getBaseUrl(), "admin/notifications/mark_read");

    var payload = {};
    if (window.csrfName && window.csrfHash) {
      payload[window.csrfName] = window.csrfHash;
    }
    payload.notification_id = id;

    // Keep this quick so UX stays snappy.
    return ajaxPostJSON(endpoint, payload, 4000).done(function (resp) {
      updateCsrfIfPresent(resp);
      if (resp && resp.unread_count !== undefined) {
        updateUnreadBadge(panel, resp.unread_count);
      }
    }).fail(function (xhr) {
      // Even if it fails, we still redirect. Try to keep CSRF in sync if possible.
      var resp = xhr && xhr.responseJSON ? xhr.responseJSON : null;
      updateCsrfIfPresent(resp);
      if (resp && resp.unread_count !== undefined) {
        updateUnreadBadge(panel, resp.unread_count);
      }
    });
  }

  /**
   * Fire-and-forget mark-as-read (best effort) for middle click / ctrl+click.
   *
   * Why:
   * - Some users open notifications in a new tab (middle click / ctrl+click).
   * - That does not always trigger our "redirect override" flow reliably.
   * - So we send a non-blocking mark-read request on pointer down.
   */
  function markNotificationReadBestEffort(panel, notificationId) {
    var id = parseInt(notificationId, 10);
    if (isNaN(id) || id <= 0) return;

    var endpoint = panel === "partner"
      ? joinUrl(getBaseUrl(), "partner/notifications/mark_read")
      : panel === "handyman"
      ? joinUrl(getBaseUrl(), "handyman/notifications/mark_read")
      : joinUrl(getBaseUrl(), "admin/notifications/mark_read");

    // Prefer sendBeacon when available because it survives tab navigations.
    if (window.navigator && typeof window.navigator.sendBeacon === "function") {
      try {
        var fd = new FormData();
        if (window.csrfName && window.csrfHash) {
          fd.append(window.csrfName, window.csrfHash);
        }
        fd.append("notification_id", String(id));
        window.navigator.sendBeacon(endpoint, fd);
        return;
      } catch (e) {
        // Fall back to async ajax
      }
    }

    // Fallback: async ajax (do NOT block navigation)
    markNotificationRead(panel, id);
  }

  /**
   * Compute redirect URL from panel + event_type + notification_type + context + href.
   * Shared by anchor click and admin notifications table row click.
   *
   * @param {string} panel - "partner" or "admin"
   * @param {string} eventType - from data-notification-event-type / event_type
   * @param {string} notificationType - from data-notification-type / notification_type
   * @param {object|null} context - parsed context_data (order_id, provider_id, etc.)
   * @param {string} href - optional url/href for sent_by_admin + url fallback
   * @returns {string} destination URL or ""
   */
  function computeDestination(panel, eventType, notificationType, context, href) {
    var destination = "";

    if (eventType === "sent_by_admin") {
      if (notificationType === "category") {
        destination = joinUrl(getBaseUrl(), (panel === "partner" ? "partner/categories" : "admin/categories"));
      }
      if (notificationType === "url" && href) {
        destination = normalizeUrl(href);
      }
    }

    if (!destination && panel === "admin" && eventType) {
      destination = buildAdminRedirectByEventType(eventType, context);
    }

    if (!destination && panel === "partner" && eventType) {
      destination = buildPartnerRedirectByEventType(eventType, context);
    }

    if (!destination && context) {
      destination = buildRedirectUrl(panel, context);
    }

    if (!destination && href) {
      destination = normalizeUrl(href);
    }

    return destination || "";
  }

  /**
   * Handle notification link click: compute destination, mark read, then redirect.
   * Uses capture phase so we run before Bootstrap dropdown and can prevent default reliably.
   */
  function handleNotificationLinkClick(e) {
    var target = e.target;
    var anchor = target && target.closest ? target.closest("a[data-notification-context], a[data-notification-event-type]") : null;
    if (!anchor) return;

    var $a = $(anchor);

    // Panel can be explicitly provided; otherwise detect from DOM/path.
    var panel = ($a.attr("data-notification-panel") || "").toLowerCase() || detectPanel();
    var notificationId = $a.attr("data-notification-id") || "";

    // If user is opening in a new tab/window (ctrl/cmd/shift click), do NOT hijack navigation.
    if (e && (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey)) {
      return;
    }

    var eventType = String($a.attr("data-notification-event-type") || "").toLowerCase();
    var eventKey = String($a.attr("data-notification-event-key") || "").toLowerCase();
    // For system_generated notifications, redirect rules use the template key (event_key), not "system_generated".
    var effectiveEventType = (eventType === "system_generated" && eventKey) ? eventKey : eventType;
    var notificationType = String($a.attr("data-notification-type") || "").toLowerCase();
    var ctx = readContextFromElement($a);
    var destination = computeDestination(panel, effectiveEventType, notificationType, ctx, $a.attr("href"));

    // When there is no redirect destination, still mark the notification as read on click
    // (so unread count and badge stay correct in both partner and admin panels).
    if (!destination) {
      if (notificationId) {
        markNotificationRead(panel, notificationId);
      }
      return;
    }

    // Run first: prevent default and stop propagation so dropdown doesn't swallow the click.
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();

    var REDIRECT_TIMEOUT_MS = 1500;
    var didRedirect = false;
    function go() {
      if (didRedirect) return;
      didRedirect = true;
      window.location.href = destination;
    }

    // If redirect is to an order or provider page, validate that user/order still exists before redirecting.
    var validationTargets = getRedirectValidationTargets(ctx, destination);
    if (validationTargets.length > 0) {
      validateAllRedirectTargets(panel, validationTargets).done(function (valid, failMessage) {
        if (!valid) {
          showNoLongerExistsToast(failMessage);
          markNotificationRead(panel, notificationId);
          return;
        }
        markNotificationRead(panel, notificationId).always(go);
        setTimeout(go, REDIRECT_TIMEOUT_MS);
      });
    } else {
      markNotificationRead(panel, notificationId).always(go);
      setTimeout(go, REDIRECT_TIMEOUT_MS);
    }
  }

  // Capture phase so we run before Bootstrap dropdown handlers (admin/partner).
  document.addEventListener("click", handleNotificationLinkClick, true);

  /**
   * Admin notifications table: row click uses same redirect rules as dropdown.
   * Table rows get data-* from rowAttributes (see admin notifications.php).
   * Always mark the notification as read on row click (then redirect if we have a destination).
   */
  $(document).on("click", "#admin_notification_list tbody tr[data-notification-id]", function (e) {
    // Do not redirect when user clicked "Read more" / "Read less" link in the message cell.
    if ($(e.target).closest("a").length) return;

    var $tr = $(this);
    var notificationId = $tr.attr("data-notification-id");
    if (!notificationId) return;

    var eventType = String($tr.attr("data-notification-event-type") || "").toLowerCase();
    var eventKey = String($tr.attr("data-notification-event-key") || "").toLowerCase();
    var effectiveEventType = (eventType === "system_generated" && eventKey) ? eventKey : eventType;
    var notificationType = String($tr.attr("data-notification-type") || "").toLowerCase();
    var href = $tr.attr("data-href") || "";

    var context = null;
    var contextEnc = $tr.attr("data-notification-context");
    if (contextEnc) {
      try {
        context = safeJsonParse(decodeURIComponent(contextEnc));
      } catch (err) {
        context = null;
      }
    }

    var destination = computeDestination("admin", effectiveEventType, notificationType, context, href);

    e.preventDefault();
    e.stopPropagation();

    function doAdminRedirect() {
      markNotificationRead("admin", notificationId).always(function () {
        if (destination) {
          window.location.href = destination;
        } else {
          var $table = $("#admin_notification_list");
          if ($table.length && typeof $table.bootstrapTable === "function") {
            $table.bootstrapTable("refresh");
          }
        }
      });
    }

    var validationTargets = getRedirectValidationTargets(context, destination);
    if (validationTargets.length > 0) {
      validateAllRedirectTargets("admin", validationTargets).done(function (valid, failMessage) {
        if (!valid) {
          showNoLongerExistsToast(failMessage);
          markNotificationRead("admin", notificationId);
          return;
        }
        doAdminRedirect();
      });
    } else {
      doAdminRedirect();
    }
  });

  /**
   * Partner notifications table: row click uses same redirect rules as dropdown.
   * Table rows get data-* from rowAttributes (see partner notifications.php).
   * Always mark the notification as read on row click (then redirect if we have a destination).
   */
  $(document).on("click", "#partner_notification_list tbody tr[data-notification-id]", function (e) {
    // Do not redirect when user clicked "Read more" / "Read less" link in the message cell.
    if ($(e.target).closest("a").length) return;

    var $tr = $(this);
    var notificationId = $tr.attr("data-notification-id");
    if (!notificationId) return;

    var eventType = String($tr.attr("data-notification-event-type") || "").toLowerCase();
    var eventKey = String($tr.attr("data-notification-event-key") || "").toLowerCase();
    var effectiveEventType = (eventType === "system_generated" && eventKey) ? eventKey : eventType;
    var notificationType = String($tr.attr("data-notification-type") || "").toLowerCase();
    var href = $tr.attr("data-href") || "";

    var context = null;
    var contextEnc = $tr.attr("data-notification-context");
    if (contextEnc) {
      try {
        context = safeJsonParse(decodeURIComponent(contextEnc));
      } catch (err) {
        context = null;
      }
    }

    var destination = computeDestination("partner", effectiveEventType, notificationType, context, href);

    e.preventDefault();
    e.stopPropagation();

    function doPartnerRedirect() {
      markNotificationRead("partner", notificationId).always(function () {
        if (destination) {
          window.location.href = destination;
        } else {
          var $table = $("#partner_notification_list");
          if ($table.length && typeof $table.bootstrapTable === "function") {
            $table.bootstrapTable("refresh");
          }
        }
      });
    }

    var validationTargets = getRedirectValidationTargets(context, destination);
    if (validationTargets.length > 0) {
      validateAllRedirectTargets("partner", validationTargets).done(function (valid, failMessage) {
        if (!valid) {
          showNoLongerExistsToast(failMessage);
          markNotificationRead("partner", notificationId);
          return;
        }
        doPartnerRedirect();
      });
    } else {
      doPartnerRedirect();
    }
  });

  /**
   * Handyman notifications table: row click uses same redirect rules as dropdown.
   * Table rows get data-* from rowAttributes (see backend/handyman/pages/notifications.php).
   * Always mark the notification as read on row click (then redirect if we have a destination).
   */
  $(document).on("click", "#handyman_notification_list tbody tr[data-notification-id]", function (e) {
    // Do not redirect when user clicked "Read more" / "Read less" link in the message cell.
    if ($(e.target).closest("a").length) return;

    var $tr = $(this);
    var notificationId = $tr.attr("data-notification-id");
    if (!notificationId) return;

    var eventType = String($tr.attr("data-notification-event-type") || "").toLowerCase();
    var eventKey = String($tr.attr("data-notification-event-key") || "").toLowerCase();
    var effectiveEventType = (eventType === "system_generated" && eventKey) ? eventKey : eventType;
    var notificationType = String($tr.attr("data-notification-type") || "").toLowerCase();
    var href = $tr.attr("data-href") || "";

    var context = null;
    var contextEnc = $tr.attr("data-notification-context");
    if (contextEnc) {
      try {
        context = safeJsonParse(decodeURIComponent(contextEnc));
      } catch (err) {
        context = null;
      }
    }

    var destination = computeDestination("handyman", effectiveEventType, notificationType, context, href);

    e.preventDefault();
    e.stopPropagation();

    function doHandymanRedirect() {
      markNotificationRead("handyman", notificationId).always(function () {
        if (destination) {
          if (typeof window.Turbo !== "undefined") {
            window.Turbo.visit(destination);
          } else {
            window.location.href = destination;
          }
        } else {
          var $table = $("#handyman_notification_list");
          if ($table.length && typeof $table.bootstrapTable === "function") {
            $table.bootstrapTable("refresh");
          }
        }
      });
    }

    var validationTargets = getRedirectValidationTargets(context, destination);
    if (validationTargets.length > 0) {
      validateAllRedirectTargets("handyman", validationTargets).done(function (valid, failMessage) {
        if (!valid) {
          showNoLongerExistsToast(failMessage);
          markNotificationRead("handyman", notificationId);
          return;
        }
        doHandymanRedirect();
      });
    } else {
      doHandymanRedirect();
    }
  });

  // Best-effort mark-as-read for middle click / normal click before navigation happens.
  // We avoid right-click (button=2) because it just opens the context menu.
  $(document).on("mousedown", "a[data-notification-id]", function (e) {
    if (e && e.button === 2) return; // right click: do nothing

    var $a = $(this);
    if ($a.attr("data-mark-read-sent") === "1") return;
    $a.attr("data-mark-read-sent", "1");

    var panel = ($a.attr("data-notification-panel") || "").toLowerCase() || detectPanel();
    var notificationId = $a.attr("data-notification-id") || "";
    markNotificationReadBestEffort(panel, notificationId);
  });
})(window, window.jQuery);

