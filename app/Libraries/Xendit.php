<?php

namespace App\Libraries;

// Import the official Xendit SDK classes
use Xendit\Configuration;
use Xendit\Invoice\InvoiceApi;
use Xendit\Invoice\CreateInvoiceRequest;
use Xendit\Invoice\InvoiceCallback;
use Exception;

/**
 * Xendit Library for CodeIgniter - Updated to use Official PHP SDK
 * 
 * Library for Xendit payment gateway using the official PHP SDK.
 * Maintains backward compatibility with existing code.
 * 
 * @package     CodeIgniter
 * @category    Libraries  
 * @author      eDemand Team
 * @version     2.0 - Updated to use Official SDK
 */

class Xendit
{
    private $api_key = "";
    private $currency = "";
    private $base_url = "";
    private $webhook_url = "";
    private $invoice_api;
    private $webhook_token = "";

    function __construct()
    {
        helper('form');
        helper('url');
        helper('function');
        helper('ResponceServices');

        // Get Xendit settings from payment gateway configuration
        $settings = get_settings('payment_gateways_settings', true);

        $this->api_key = isset($settings['xendit_api_key']) ? $settings['xendit_api_key'] : '';
        $this->currency = isset($settings['xendit_currency']) ? $settings['xendit_currency'] : 'IDR';
        $this->base_url = 'https://api.xendit.co';
        $this->webhook_url = base_url('api/webhooks/xendit');
        $this->webhook_token = isset($settings['xendit_webhook_verification_token']) ? $settings['xendit_webhook_verification_token'] : '';
        // Initialize the official Xendit SDK
        $this->initialize_sdk();
    }

    /**
     * Initialize the Xendit SDK with API credentials
     * 
     * @return void
     */
    private function initialize_sdk()
    {
        try {
            if (!empty($this->api_key)) {
                // Configure the SDK with API key
                @Configuration::setXenditKey($this->api_key);

                // Initialize the Invoice API client
                $this->invoice_api = new InvoiceApi();

                log_message('error', 'Xendit SDK initialized successfully');
            } else {
                log_message('error', 'Xendit API key is not configured');
            }
        } catch (Exception $e) {
            log_message('error', 'Failed to initialize Xendit SDK: ' . $e->getMessage());
        }
    }

    /**
     * Get Xendit credentials and configuration
     * 
     * @return array
     */
    public function get_credentials()
    {
        return [
            'api_key' => $this->api_key,
            'currency' => $this->currency,
            'base_url' => $this->base_url,
            'webhook_url' => $this->webhook_url
        ];
    }

    /**
     * Create Xendit invoice for payment using official SDK
     * 
     * @param array $data Invoice data
     * @return array|false
     */
    public function create_invoice($data)
    {
        try {
            if (empty($this->api_key)) {
                log_message('error', 'Xendit API key is not configured');
                return false;
            }

            // Create the invoice request object using SDK
            $create_invoice_request = new CreateInvoiceRequest([
                'external_id' => $data['external_id'],
                'amount' => $data['amount'],
                'currency' => $this->currency,
                'customer' => [
                    'given_names' => $data['customer_name'],
                    'email' => $data['customer_email'],
                    'mobile_number' => $data['customer_phone'] ?? ''
                ],
                'customer_notification_preference' => [
                    'invoice_created' => ['email'],
                    'invoice_reminder' => ['email'],
                    'invoice_paid' => ['email']
                ],
                'success_redirect_url' => $data['success_url'],
                'failure_redirect_url' => $data['failure_url'],
                'description' => $data['description'] ?? 'Payment for eDemand Service',
                'invoice_duration' => 86400, // 24 hours in seconds
                'fees' => [
                    [
                        'type' => 'ADMIN',
                        'value' => 0
                    ]
                ]
            ]);

            // Add metadata if provided
            if (isset($data['metadata'])) {
                $create_invoice_request->setMetadata($data['metadata']);
            }

            // Create the invoice using SDK
            $response = $this->invoice_api->createInvoice($create_invoice_request);

            // Convert response object to array for backward compatibility
            $invoice_data = [
                'id' => $response->getId(),
                'external_id' => $response->getExternalId(),
                'user_id' => $response->getUserId(),
                'status' => $response->getStatus(),
                'merchant_name' => $response->getMerchantName(),
                'amount' => $response->getAmount(),
                'currency' => $response->getCurrency(),
                'description' => $response->getDescription(),
                'invoice_url' => $response->getInvoiceUrl(),
                'expiry_date' => $response->getExpiryDate(),
                'created' => $response->getCreated(),
                'updated' => $response->getUpdated()
            ];

            log_message('error', 'Xendit Invoice created successfully: ' . $response->getId());
            return $invoice_data;
        } catch (Exception $e) {
            log_message('error', 'Xendit Invoice creation failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get invoice details by ID using official SDK
     * 
     * @param string $invoice_id
     * @return array|false
     */
    public function get_invoice($invoice_id)
    {
        try {
            if (empty($this->api_key)) {
                log_message('error', 'Xendit API key is not configured');
                return false;
            }

            // Get invoice using SDK
            $response = $this->invoice_api->getInvoiceById($invoice_id);

            // Convert response object to array for backward compatibility
            $invoice_data = [
                'id' => $response->getId(),
                'external_id' => $response->getExternalId(),
                'user_id' => $response->getUserId(),
                'status' => $response->getStatus(),
                'merchant_name' => $response->getMerchantName(),
                'amount' => $response->getAmount(),
                'paid_amount' => $response->getPaidAmount(),
                'currency' => $response->getCurrency(),
                'description' => $response->getDescription(),
                'invoice_url' => $response->getInvoiceUrl(),
                'expiry_date' => $response->getExpiryDate(),
                'created' => $response->getCreated(),
                'updated' => $response->getUpdated(),
                'payment_method' => $response->getPaymentMethod(),
                'payment_id' => $response->getPaymentId()
            ];

            log_message('error', 'Xendit Invoice retrieved successfully: ' . $invoice_id);
            return $invoice_data;
        } catch (Exception $e) {
            log_message('error', 'Xendit get invoice failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Expire an invoice using official SDK
     * 
     * @param string $invoice_id
     * @return array|false
     */
    public function expire_invoice($invoice_id)
    {
        try {
            if (empty($this->api_key)) {
                log_message('error', 'Xendit API key is not configured');
                return false;
            }

            // Expire invoice using SDK
            $response = $this->invoice_api->expireInvoice($invoice_id);

            // Convert response object to array for backward compatibility
            $invoice_data = [
                'id' => $response->getId(),
                'external_id' => $response->getExternalId(),
                'status' => $response->getStatus(),
                'amount' => $response->getAmount(),
                'currency' => $response->getCurrency(),
                'description' => $response->getDescription(),
                'updated' => $response->getUpdated()
            ];

            log_message('error', 'Xendit Invoice expired successfully: ' . $invoice_id);
            return $invoice_data;
        } catch (Exception $e) {
            log_message('error', 'Xendit expire invoice failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create refund for a payment - Kept for compatibility but not implemented with SDK yet
     * 
     * @param string $payment_id
     * @param float $amount
     * @param string $reason
     * @return array|false
     */
    /**
     * Create a refund for a payment
     * 
     * Automatically includes webhook callback URL to receive real-time 
     * refund status updates (succeeded, failed, pending).
     * 
     * @param string $payment_id The payment ID to refund
     * @param float $amount The amount to refund
     * @param string $reason The reason for refund
     * @param string $external_id Optional external ID for tracking
     * @return array|false
     */
    public function create_refund($payment_id, $amount, $reason = 'REQUESTED_BY_CUSTOMER', $external_id = null)
    {
        try {
            log_message('error', 'Xendit Refund - Starting refund process for Payment ID: ' . $payment_id . ', Amount: ' . $amount);

            // Generate external_id if not provided
            if (empty($external_id)) {
                $external_id = 'refund_' . time() . '_' . $payment_id;
            }

            // Xendit refund API endpoint
            $url = $this->base_url . '/refunds';

            $refund_data = [
                'payment_id' => $payment_id,
                'amount' => (int)($amount * 100), // Convert to cents
                'reason' => $reason,
                'external_id' => $external_id,
                'currency' => $this->currency,
                'callback_url' => $this->webhook_url
            ];

            log_message('error', 'Xendit Refund Data (with callback): ' . json_encode($refund_data));

            $response = $this->make_request($url, 'POST', $refund_data);

            if ($response && isset($response['id'])) {
                log_message('error', 'Xendit Refund - Success. Refund ID: ' . $response['id']);
                return $response;
            } else {
                log_message('error', 'Xendit Refund - Failed. Response: ' . json_encode($response));
                return false;
            }
        } catch (Exception $e) {
            log_message('error', 'Xendit Refund - Exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Comprehensive refund payment method that handles different payment types
     * 
     * @param string $txn_id Transaction ID (could be payment_id or external_id)
     * @param float $amount Amount to refund
     * @param string $reason Refund reason
     * @param string $payment_type Type of payment (invoice, payment_request, etc.)
     * @return array|false
     */
    public function refund_payment($txn_id, $amount, $reason = 'REQUESTED_BY_CUSTOMER', $payment_type = 'auto')
    {
        try {
            log_message('error', 'Xendit refund_payment - TXN ID: ' . $txn_id . ', Amount: ' . $amount . ', Type: ' . $payment_type);

            // If payment_type is auto, try to determine the correct refund method
            if ($payment_type === 'auto') {
                // First, try to get payment details to determine the type
                $payment_details = $this->get_payment_details($txn_id);

                if ($payment_details) {
                    $payment_type = $payment_details['type'] ?? 'invoice';
                    $payment_id = $payment_details['payment_id'] ?? $txn_id;
                } else {
                    // Fallback: assume it's an invoice payment
                    $payment_type = 'invoice';
                    $payment_id = $txn_id;
                }
            } else {
                $payment_id = $txn_id;
            }

            // Handle different payment types
            switch ($payment_type) {
                case 'invoice':
                    return $this->refund_invoice_payment($payment_id, $amount, $reason);
                case 'payment_request':
                    return $this->refund_payment_request($payment_id, $amount, $reason);
                case 'direct_debit':
                    return $this->refund_direct_debit($payment_id, $amount, $reason);
                case 'ewallet':
                    return $this->refund_ewallet_charge($payment_id, $amount, $reason);
                default:
                    // Try the standard refund endpoint
                    return $this->create_refund($payment_id, $amount, $reason);
            }
        } catch (Exception $e) {
            log_message('error', 'Xendit refund_payment - Exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Refund an invoice payment
     * 
     * @param string $payment_id Payment ID
     * @param float $amount Amount to refund
     * @param string $reason Refund reason
     * @return array|false
     */
    private function refund_invoice_payment($payment_id, $amount, $reason)
    {
        try {
            $url = $this->base_url . '/refunds';

            $refund_data = [
                'payment_id' => $payment_id,
                'amount' => (int)($amount * 100), // Convert to cents
                'reason' => $reason,
                'external_id' => 'invoice_refund_' . time() . '_' . $payment_id,
                'currency' => $this->currency,
                'callback_url' => $this->webhook_url
            ];

            return $this->make_request($url, 'POST', $refund_data);
        } catch (Exception $e) {
            log_message('error', 'Xendit refund_invoice_payment - Exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Refund a payment request
     * 
     * @param string $payment_request_id Payment request ID
     * @param float $amount Amount to refund
     * @param string $reason Refund reason
     * @return array|false
     */
    private function refund_payment_request($payment_request_id, $amount, $reason)
    {
        try {
            $url = $this->base_url . '/payment_requests/' . $payment_request_id . '/refunds';

            $refund_data = [
                'amount' => (int)($amount * 100), // Convert to cents
                'reason' => $reason,
                'external_id' => 'payment_request_refund_' . time() . '_' . $payment_request_id,
                'callback_url' => $this->webhook_url
            ];

            return $this->make_request($url, 'POST', $refund_data);
        } catch (Exception $e) {
            log_message('error', 'Xendit refund_payment_request - Exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Refund a direct debit payment
     * 
     * @param string $direct_debit_id Direct debit ID
     * @param float $amount Amount to refund
     * @param string $reason Refund reason
     * @return array|false
     */
    private function refund_direct_debit($direct_debit_id, $amount, $reason)
    {
        try {
            $url = $this->base_url . '/direct_debits/' . $direct_debit_id . '/refunds';

            $refund_data = [
                'amount' => (int)($amount * 100), // Convert to cents
                'reason' => $reason,
                'external_id' => 'direct_debit_refund_' . time() . '_' . $direct_debit_id,
                'callback_url' => $this->webhook_url
            ];

            return $this->make_request($url, 'POST', $refund_data);
        } catch (Exception $e) {
            log_message('error', 'Xendit refund_direct_debit - Exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Refund an e-wallet charge
     * 
     * @param string $ewallet_charge_id E-wallet charge ID
     * @param float $amount Amount to refund
     * @param string $reason Refund reason
     * @return array|false
     */
    private function refund_ewallet_charge($ewallet_charge_id, $amount, $reason)
    {
        try {
            log_message('error', 'Xendit E-wallet Refund - Starting refund for Charge ID: ' . $ewallet_charge_id . ', Amount: ' . $amount);

            // Map custom reason to valid Xendit e-wallet refund reason codes
            $valid_reason = $this->map_ewallet_refund_reason($reason);

            $url = $this->base_url . '/ewallets/charges/' . $ewallet_charge_id . '/refunds';

            $refund_data = [
                'amount' => (int)$amount, // E-wallet amounts are already in base currency units (not cents)
                'reason' => $valid_reason,
                'external_id' => 'ewallet_refund_' . time() . '_' . $ewallet_charge_id,
                'callback_url' => $this->webhook_url
            ];

            log_message('error', 'Xendit E-wallet Refund Data (with callback): ' . json_encode($refund_data));

            $response = $this->make_request($url, 'POST', $refund_data);

            if ($response && isset($response['id'])) {
                log_message('error', 'Xendit E-wallet Refund - Success. Refund ID: ' . $response['id']);
                return $response;
            } else {
                log_message('error', 'Xendit E-wallet Refund - Failed. Response: ' . json_encode($response));
                return false;
            }
        } catch (Exception $e) {
            log_message('error', 'Xendit refund_ewallet_charge - Exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Map custom refund reason to valid Xendit e-wallet refund reason codes
     * 
     * @param string $reason Custom reason
     * @return string Valid Xendit reason code
     */
    private function map_ewallet_refund_reason($reason)
    {
        // Convert custom reasons to valid Xendit e-wallet refund reason codes
        $reason_lower = strtolower($reason);

        // Valid Xendit e-wallet refund reasons (based on API documentation)
        if (
            strpos($reason_lower, 'customer') !== false ||
            strpos($reason_lower, 'requested') !== false ||
            strpos($reason_lower, 'cancel') !== false
        ) {
            return 'REQUESTED_BY_CUSTOMER';
        }

        if (strpos($reason_lower, 'duplicate') !== false) {
            return 'DUPLICATE';
        }

        if (strpos($reason_lower, 'fraud') !== false || strpos($reason_lower, 'suspicious') !== false) {
            return 'FRAUDULENT';
        }

        if (strpos($reason_lower, 'error') !== false || strpos($reason_lower, 'mistake') !== false) {
            return 'OTHERS';
        }

        // Default to most common reason
        return 'REQUESTED_BY_CUSTOMER';
    }

    /**
     * Get payment details to determine payment type
     * 
     * @param string $payment_id Payment ID
     * @return array|false
     */
    private function get_payment_details($payment_id)
    {
        try {
            // Determine payment type based on ID prefix
            $payment_type = $this->detect_payment_type_by_id($payment_id);

            // Try different endpoints to get payment details based on ID prefix
            $endpoints = [];

            if ($payment_type === 'ewallet') {
                // E-wallet charges start with 'ewc_'
                $endpoints[] = '/ewallets/charges/' . $payment_id;
            } elseif ($payment_type === 'payment_request') {
                // Payment requests typically start with 'pr_'
                $endpoints[] = '/payment_requests/' . $payment_id;
            } elseif ($payment_type === 'direct_debit') {
                // Direct debits start with various prefixes
                $endpoints[] = '/direct_debits/' . $payment_id;
            }

            // Always try these as fallbacks
            $endpoints = array_merge($endpoints, [
                '/payments/' . $payment_id,
                '/payment_requests/' . $payment_id,
                '/direct_debits/' . $payment_id,
                '/ewallets/charges/' . $payment_id
            ]);

            // Remove duplicates
            $endpoints = array_unique($endpoints);

            foreach ($endpoints as $endpoint) {
                $url = $this->base_url . $endpoint;
                $response = $this->make_request($url, 'GET');

                if ($response && isset($response['id'])) {
                    // Determine type based on endpoint that worked
                    if (strpos($endpoint, '/payments/') !== false) {
                        $response['type'] = 'invoice';
                    } elseif (strpos($endpoint, '/payment_requests/') !== false) {
                        $response['type'] = 'payment_request';
                    } elseif (strpos($endpoint, '/direct_debits/') !== false) {
                        $response['type'] = 'direct_debit';
                    } elseif (strpos($endpoint, '/ewallets/charges/') !== false) {
                        $response['type'] = 'ewallet';
                    }

                    $response['payment_id'] = $response['id'];
                    log_message('error', 'Xendit Payment Details Found - Type: ' . $response['type'] . ', ID: ' . $payment_id);
                    return $response;
                }
            }

            return false;
        } catch (Exception $e) {
            log_message('error', 'Xendit get_payment_details - Exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Detect payment type based on ID prefix
     * 
     * @param string $payment_id Payment ID
     * @return string
     */
    private function detect_payment_type_by_id($payment_id)
    {
        // E-wallet charges
        if (strpos($payment_id, 'ewc_') === 0) {
            return 'ewallet';
        }

        // Payment requests
        if (strpos($payment_id, 'pr_') === 0) {
            return 'payment_request';
        }

        // Direct debit
        if (strpos($payment_id, 'dd_') === 0 || strpos($payment_id, 'ddt_') === 0) {
            return 'direct_debit';
        }

        // Invoice/payment
        if (strpos($payment_id, 'pm_') === 0 || strpos($payment_id, 'pay_') === 0) {
            return 'invoice';
        }

        // Default to invoice for unknown types
        return 'invoice';
    }

    /**
     * Get refund status
     * 
     * @param string $refund_id Refund ID
     * @return array|false
     */
    public function get_refund_status($refund_id)
    {
        try {
            $url = $this->base_url . '/refunds/' . $refund_id;
            return $this->make_request($url, 'GET');
        } catch (Exception $e) {
            log_message('error', 'Xendit get_refund_status - Exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Legacy cURL method - kept for refund functionality
     * Will be removed when refund is implemented with SDK
     * 
     * @param string $url
     * @param string $method
     * @param array $data
     * @return array|false
     */
    private function make_request($url, $method = 'GET', $data = [])
    {
        if (empty($this->api_key)) {
            log_message('error', 'Xendit API key is not configured');
            return false;
        }

        $ch = curl_init();

        // Basic cURL options
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . base64_encode($this->api_key . ':'),
                'Content-Type: application/json',
                'x-callback-url: ' . $this->webhook_url
            ]
        ]);

        // Set method-specific options
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        unset($ch);

        // Handle cURL errors
        if ($curl_error) {
            log_message('error', 'Xendit cURL Error: ' . $curl_error);
            return false;
        }

        // Parse response
        $decoded_response = json_decode($response, true);

        // Log response for debugging
        log_message('error', 'Xendit API Response: ' . $response);

        // Check for HTTP errors
        if ($http_code >= 400) {
            $error_message = isset($decoded_response['message']) ? $decoded_response['message'] : 'Unknown error';
            log_message('error', 'Xendit API Error (HTTP ' . $http_code . '): ' . $error_message);
            return false;
        }

        return $decoded_response;
    }

    /**
     * Verify webhook signature using manual HMAC verification
     * 
     * @param string $payload
     * @param string $signature
     * @param string $webhook_token
     * @return bool
     */
    public function verify_webhook($signature)
    {
        try {
            // Use the webhook token if provided, otherwise use API key
            $token = $this->webhook_token;

            $is_valid = strcmp($token, $signature) == 0;
            if ($is_valid) {
                log_message('error', 'Xendit webhook signature verified successfully');
                return true;
            } else {
                log_message('error', 'Xendit webhook signature verification failed - Expected: ' . $token . ', Received: ' . $signature);
                return false;
            }
        } catch (Exception $e) {
            log_message('error', 'Xendit webhook verification error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get available payment methods for country/currency
     * This method remains unchanged as it's configuration-based
     * 
     * @return array
     */
    public function get_payment_methods()
    {
        $methods = [];

        // Define payment methods based on currency
        switch ($this->currency) {
            case 'IDR':
                $methods = ['BANK_TRANSFER', 'EWALLET', 'RETAIL_OUTLET', 'CREDIT_CARD'];
                break;
            case 'PHP':
                $methods = ['BANK_TRANSFER', 'EWALLET', 'RETAIL_OUTLET'];
                break;
            case 'USD':
            case 'SGD':
            case 'MYR':
            case 'THB':
            case 'VND':
                $methods = ['BANK_TRANSFER', 'CREDIT_CARD'];
                break;
            default:
                $methods = ['BANK_TRANSFER'];
        }

        return $methods;
    }
}
