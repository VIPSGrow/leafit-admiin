/**
 * Microsoft Clarity Event Tracking Module for Partner Panel
 * 
 * This module provides centralized event tracking functions for Microsoft Clarity analytics.
 * All partner panel events should be tracked through this module.
 */

// Check if Clarity is loaded and available
function isClarityAvailable() {
    return typeof clarity !== 'undefined' && typeof clarity.track === 'function';
}

/**
 * Main function to track partner panel events
 * 
 * @param {string} eventName - The name of the event to track
 * @param {object} eventData - Object containing event parameters
 */
function trackPartnerEvent(eventName, eventData) {
    // Check if Clarity is available
    if (!isClarityAvailable()) {
        // Silently fail - don't break existing functionality
        if (typeof console !== 'undefined' && console.debug) {
            console.debug('Microsoft Clarity not available, event not tracked:', eventName);
        }
        return false;
    }

    // Validate event name
    if (!eventName || typeof eventName !== 'string') {
        if (typeof console !== 'undefined' && console.error) {
            console.error('Invalid event name provided to trackPartnerEvent');
        }
        return false;
    }

    // Ensure eventData is an object
    if (!eventData || typeof eventData !== 'object') {
        eventData = {};
    }

    // Add timestamp to event data
    eventData.timestamp = new Date().toISOString();

    try {
        // Track the event using Clarity
        clarity.track(eventName, eventData);
        return true;
    } catch (error) {
        // Log error but don't break functionality
        if (typeof console !== 'undefined' && console.error) {
            console.error('Error tracking Clarity event:', eventName, error);
        }
        return false;
    }
}

/**
 * AUTH EVENTS
 */

// Track login attempt
function trackLoginAttempt(phoneNumber, countryCode) {
    trackPartnerEvent('login_attempt', {
        phone_number: phoneNumber || '',
        country_code: countryCode || ''
    });
}

// Track login success
function trackLoginSuccess(providerId, providerName, companyName) {
    trackPartnerEvent('login_success', {
        provider_id: providerId || '',
        provider_name: providerName || '',
        company_name: companyName || ''
    });
}

// Track login failure
function trackLoginFailure(errorMessage) {
    trackPartnerEvent('login_failure', {
        error_message: errorMessage || 'Unknown error'
    });
}

// Track logout
function trackLogout() {
    trackPartnerEvent('logout', {});
}

// Track registration attempt
function trackRegistrationAttempt(phoneNumber, countryCode) {
    trackPartnerEvent('registration_attempt', {
        phone_number: phoneNumber || '',
        country_code: countryCode || ''
    });
}

// Track registration completed
function trackRegistrationCompleted(username, companyName) {
    trackPartnerEvent('registration_completed', {
        username: username || '',
        company_name: companyName || ''
    });
}

/**
 * SERVICE MANAGEMENT EVENTS
 */

// Track service created
function trackServiceCreated(serviceId, serviceName, servicePrice, categoryId, categoryName) {
    trackPartnerEvent('service_created', {
        service_id: serviceId || '',
        service_name: serviceName || '',
        service_price: servicePrice || '',
        category_id: categoryId || '',
        category_name: categoryName || ''
    });
}

// Track service updated
function trackServiceUpdated(serviceId, serviceName) {
    trackPartnerEvent('service_updated', {
        service_id: serviceId || '',
        service_name: serviceName || ''
    });
}

// Track service deleted
function trackServiceDeleted(serviceId) {
    trackPartnerEvent('service_deleted', {
        service_id: serviceId || ''
    });
}

// Track service viewed
function trackServiceViewed(serviceId, serviceName, servicePrice, categoryId, categoryName) {
    trackPartnerEvent('service_viewed', {
        service_id: serviceId || '',
        service_name: serviceName || '',
        service_price: servicePrice || '',
        category_id: categoryId || '',
        category_name: categoryName || ''
    });
}

/**
 * BOOKING EVENTS
 */

// Track booking accepted
function trackBookingAccepted(bookingId, status, customerId) {
    trackPartnerEvent('booking_accepted', {
        booking_id: bookingId || '',
        status: status || '',
        customer_id: customerId || ''
    });
}

// Track booking rejected
function trackBookingRejected(bookingId, status, customerId) {
    trackPartnerEvent('booking_rejected', {
        booking_id: bookingId || '',
        status: status || '',
        customer_id: customerId || ''
    });
}

// Track booking cancelled
function trackBookingCancelled(bookingId, status, customerId) {
    trackPartnerEvent('booking_cancelled', {
        booking_id: bookingId || '',
        status: status || '',
        customer_id: customerId || ''
    });
}

// Track booking completed
function trackBookingCompleted(bookingId, status, customerId) {
    trackPartnerEvent('booking_completed', {
        booking_id: bookingId || '',
        status: status || '',
        customer_id: customerId || ''
    });
}

// Track booking status updated
function trackBookingStatusUpdated(bookingId, status, customerId) {
    trackPartnerEvent('booking_status_updated', {
        booking_id: bookingId || '',
        status: status || '',
        customer_id: customerId || ''
    });
}

/**
 * PROMO CODE EVENTS
 */

// Track promo code created
function trackPromocodeCreated(promocodeId, promocodeName, discount, discountType) {
    trackPartnerEvent('promocode_created', {
        promocode_id: promocodeId || '',
        promocode_name: promocodeName || '',
        discount: discount || '',
        discount_type: discountType || ''
    });
}

// Track promo code updated
function trackPromocodeUpdated(promocodeId, promocodeName) {
    trackPartnerEvent('promocode_updated', {
        promocode_id: promocodeId || '',
        promocode_name: promocodeName || ''
    });
}

// Track promo code deleted
function trackPromocodeDeleted(promocodeId) {
    trackPartnerEvent('promocode_deleted', {
        promocode_id: promocodeId || ''
    });
}

/**
 * PAYMENT & SUBSCRIPTION EVENTS
 */

// Track subscription purchase
function trackSubscriptionPurchase(subscriptionId, subscriptionName, price, paymentMethod) {
    trackPartnerEvent('subscription_purchase', {
        subscription_id: subscriptionId || '',
        subscription_name: subscriptionName || '',
        price: price || '',
        payment_method: paymentMethod || ''
    });
}

// Track subscription renewed
function trackSubscriptionRenewed(subscriptionId, price) {
    trackPartnerEvent('subscription_renewed', {
        subscription_id: subscriptionId || '',
        price: price || ''
    });
}

// Track subscription cancelled
function trackSubscriptionCancelled(subscriptionId) {
    trackPartnerEvent('subscription_cancelled', {
        subscription_id: subscriptionId || ''
    });
}

// Track payout requested
function trackPayoutRequested(amount, paymentAddress, remainingBalance) {
    trackPartnerEvent('payout_requested', {
        amount: amount || '',
        payment_address: paymentAddress || '',
        remaining_balance: remainingBalance || ''
    });
}

// Track withdrawal request sent
function trackWithdrawalRequestSent(amount, paymentAddress, remainingBalance) {
    trackPartnerEvent('withdrawal_request_sent', {
        amount: amount || '',
        payment_address: paymentAddress || '',
        remaining_balance: remainingBalance || ''
    });
}

// Track cash collection completed
function trackCashCollectionCompleted(amount, bookingId) {
    trackPartnerEvent('cash_collection_completed', {
        amount: amount || '',
        booking_id: bookingId || ''
    });
}

/**
 * REVIEWS & RATINGS EVENTS
 */

// Track review viewed
function trackReviewViewed(serviceId) {
    trackPartnerEvent('review_viewed', {
        service_id: serviceId || ''
    });
}

/**
 * CHAT EVENTS
 */

// Track chat message sent
function trackChatMessageSent(messageId, receiverId, bookingId, messageType) {
    trackPartnerEvent('chat_message_sent', {
        message_id: messageId || '',
        receiver_id: receiverId || '',
        booking_id: bookingId || '',
        message_type: messageType || ''
    });
}

// Track chat started
function trackChatStarted(receiverId, bookingId) {
    trackPartnerEvent('chat_started', {
        receiver_id: receiverId || '',
        booking_id: bookingId || ''
    });
}

// Track user blocked
function trackUserBlocked(userId) {
    trackPartnerEvent('user_blocked', {
        user_id: userId || ''
    });
}

// Track user unblocked
function trackUserUnblocked(userId) {
    trackPartnerEvent('user_unblocked', {
        user_id: userId || ''
    });
}

// Track user reported
function trackUserReported(userId, reasonId) {
    trackPartnerEvent('user_reported', {
        user_id: userId || '',
        reason_id: reasonId || ''
    });
}

/**
 * PROFILE & SETTINGS EVENTS
 */

// Track profile updated
function trackProfileUpdated(providerId, companyName) {
    trackPartnerEvent('profile_updated', {
        provider_id: providerId || '',
        company_name: companyName || ''
    });
}

/**
 * JOB REQUEST EVENTS
 */

// Track custom job applied
function trackCustomJobApplied(jobRequestId, counterPrice, duration) {
    trackPartnerEvent('custom_job_applied', {
        job_request_id: jobRequestId || '',
        counter_price: counterPrice || '',
        duration: duration || ''
    });
}

// Track job request viewed
function trackJobRequestViewed(jobRequestId) {
    trackPartnerEvent('job_request_viewed', {
        job_request_id: jobRequestId || ''
    });
}

/**
 * CHECKOUT EVENTS
 */

// Track checkout completed (when checkout page is opened for subscription)
function trackCheckoutCompleted() {
    trackPartnerEvent('checkout_completed', {});
}

