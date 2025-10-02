<?php
/**
 * Payment Gateway Manager
 * Handles multiple payment gateway integrations
 */

class PaymentGatewayManager {
    private $db;
    private $gateways = [];
    
    public function __construct($database) {
        $this->db = $database;
        $this->loadGateways();
    }
    
    /**
     * Load all active payment gateways
     */
    private function loadGateways() {
        try {
            $stmt = $this->db->getConnection()->prepare("
                SELECT * FROM payment_gateways 
                WHERE is_active = 1 
                ORDER BY is_default DESC, gateway_name ASC
            ");
            $stmt->execute();
            $this->gateways = $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Payment gateway loading error: " . $e->getMessage());
            $this->gateways = [];
        }
    }
    
    /**
     * Get all available gateways
     */
    public function getAvailableGateways() {
        return $this->gateways;
    }
    
    /**
     * Get default gateway
     */
    public function getDefaultGateway() {
        foreach ($this->gateways as $gateway) {
            if ($gateway['is_default']) {
                return $gateway;
            }
        }
        return !empty($this->gateways) ? $this->gateways[0] : null;
    }
    
    /**
     * Get gateway by ID
     */
    public function getGatewayById($id) {
        foreach ($this->gateways as $gateway) {
            if ($gateway['id'] == $id) {
                return $gateway;
            }
        }
        return null;
    }
    
    /**
     * Get gateway by type
     */
    public function getGatewayByType($type) {
        foreach ($this->gateways as $gateway) {
            if ($gateway['gateway_type'] == $type) {
                return $gateway;
            }
        }
        return null;
    }
    
    /**
     * Process payment
     */
    public function processPayment($gatewayId, $amount, $currency, $description, $metadata = []) {
        $gateway = $this->getGatewayById($gatewayId);
        if (!$gateway) {
            return ['success' => false, 'error' => 'Payment gateway not found'];
        }
        
        // Validate amount limits
        if ($amount < $gateway['min_amount'] || $amount > $gateway['max_amount']) {
            return [
                'success' => false, 
                'error' => "Amount must be between {$gateway['min_amount']} and {$gateway['max_amount']}"
            ];
        }
        
        // Check currency support
        $supportedCurrencies = json_decode($gateway['supported_currencies'], true) ?? [];
        if (!empty($supportedCurrencies) && !in_array($currency, $supportedCurrencies)) {
            return [
                'success' => false, 
                'error' => "Currency {$currency} not supported by this gateway"
            ];
        }
        
        // Calculate processing fee
        $processingFee = $this->calculateProcessingFee($gateway, $amount);
        $totalAmount = $amount + $processingFee;
        
        // Process based on gateway type
        switch ($gateway['gateway_type']) {
            case 'stripe':
                return $this->processStripePayment($gateway, $totalAmount, $currency, $description, $metadata);
            case 'paypal':
                return $this->processPayPalPayment($gateway, $totalAmount, $currency, $description, $metadata);
            case 'paystack':
                return $this->processPaystackPayment($gateway, $totalAmount, $currency, $description, $metadata);
            case 'hubtel':
                return $this->processHubtelPayment($gateway, $totalAmount, $currency, $description, $metadata);
            case 'flutterwave':
                return $this->processFlutterwavePayment($gateway, $totalAmount, $currency, $description, $metadata);
            case 'razorpay':
                return $this->processRazorpayPayment($gateway, $totalAmount, $currency, $description, $metadata);
            default:
                return ['success' => false, 'error' => 'Unsupported payment gateway'];
        }
    }
    
    /**
     * Calculate processing fee
     */
    private function calculateProcessingFee($gateway, $amount) {
        $percentageFee = ($amount * $gateway['processing_fee_percentage']) / 100;
        return $percentageFee + $gateway['processing_fee_fixed'];
    }
    
    /**
     * Process Stripe payment
     */
    private function processStripePayment($gateway, $amount, $currency, $description, $metadata) {
        try {
            $config = json_decode($gateway['config_data'], true);
            
            if ($gateway['test_mode']) {
                $secretKey = $config['test_secret_key'] ?? $config['secret_key'] ?? '';
            } else {
                $secretKey = $config['secret_key'] ?? '';
            }
            
            if (empty($secretKey)) {
                return ['success' => false, 'error' => 'Stripe configuration incomplete'];
            }
            
            // Initialize Stripe
            require_once 'vendor/stripe/stripe-php/init.php';
            \Stripe\Stripe::setApiKey($secretKey);
            
            // Create payment intent
            $intent = \Stripe\PaymentIntent::create([
                'amount' => $amount * 100, // Convert to cents
                'currency' => strtolower($currency),
                'description' => $description,
                'metadata' => $metadata
            ]);
            
            return [
                'success' => true,
                'payment_intent_id' => $intent->id,
                'client_secret' => $intent->client_secret,
                'amount' => $amount,
                'currency' => $currency
            ];
        } catch (Exception $e) {
            error_log("Stripe payment error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Payment processing failed'];
        }
    }
    
    /**
     * Process PayPal payment
     */
    private function processPayPalPayment($gateway, $amount, $currency, $description, $metadata) {
        try {
            $config = json_decode($gateway['config_data'], true);
            
            if ($gateway['test_mode']) {
                $clientId = $config['test_client_id'] ?? $config['client_id'] ?? '';
                $clientSecret = $config['test_client_secret'] ?? $config['client_secret'] ?? '';
                $baseUrl = 'https://api.sandbox.paypal.com';
            } else {
                $clientId = $config['client_id'] ?? '';
                $clientSecret = $config['client_secret'] ?? '';
                $baseUrl = 'https://api.paypal.com';
            }
            
            if (empty($clientId) || empty($clientSecret)) {
                return ['success' => false, 'error' => 'PayPal configuration incomplete'];
            }
            
            // Get access token
            $tokenResponse = $this->makeHttpRequest($baseUrl . '/v1/oauth2/token', [
                'grant_type' => 'client_credentials'
            ], [
                'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret),
                'Content-Type: application/x-www-form-urlencoded'
            ]);
            
            if (!$tokenResponse || !isset($tokenResponse['access_token'])) {
                return ['success' => false, 'error' => 'Failed to authenticate with PayPal'];
            }
            
            // Create order
            $orderData = [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => number_format($amount, 2, '.', '')
                    ],
                    'description' => $description
                ]]
            ];
            
            $orderResponse = $this->makeHttpRequest($baseUrl . '/v2/checkout/orders', $orderData, [
                'Authorization: Bearer ' . $tokenResponse['access_token'],
                'Content-Type: application/json'
            ], 'POST');
            
            if (!$orderResponse || !isset($orderResponse['id'])) {
                return ['success' => false, 'error' => 'Failed to create PayPal order'];
            }
            
            return [
                'success' => true,
                'order_id' => $orderResponse['id'],
                'approval_url' => $orderResponse['links'][1]['href'] ?? '',
                'amount' => $amount,
                'currency' => $currency
            ];
        } catch (Exception $e) {
            error_log("PayPal payment error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Payment processing failed'];
        }
    }
    
    /**
     * Process Paystack payment
     */
    private function processPaystackPayment($gateway, $amount, $currency, $description, $metadata) {
        try {
            $config = json_decode($gateway['config_data'], true);
            
            if ($gateway['test_mode']) {
                $secretKey = $config['test_secret_key'] ?? $config['secret_key'] ?? '';
            } else {
                $secretKey = $config['secret_key'] ?? '';
            }
            
            if (empty($secretKey)) {
                return ['success' => false, 'error' => 'Paystack configuration incomplete'];
            }
            
            // Create transaction
            $transactionData = [
                'amount' => $amount * 100, // Convert to kobo
                'currency' => $currency,
                'description' => $description,
                'metadata' => $metadata
            ];
            
            $response = $this->makeHttpRequest('https://api.paystack.co/transaction/initialize', $transactionData, [
                'Authorization: Bearer ' . $secretKey,
                'Content-Type: application/json'
            ], 'POST');
            
            if (!$response || !$response['status']) {
                return ['success' => false, 'error' => 'Failed to initialize Paystack payment'];
            }
            
            return [
                'success' => true,
                'reference' => $response['data']['reference'],
                'authorization_url' => $response['data']['authorization_url'],
                'access_code' => $response['data']['access_code'],
                'amount' => $amount,
                'currency' => $currency
            ];
        } catch (Exception $e) {
            error_log("Paystack payment error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Payment processing failed'];
        }
    }
    
    /**
     * Process Hubtel payment
     */
    private function processHubtelPayment($gateway, $amount, $currency, $description, $metadata) {
        try {
            $config = json_decode($gateway['config_data'], true);
            
            if ($gateway['test_mode']) {
                $clientId = $config['test_client_id'] ?? $config['client_id'] ?? '';
                $clientSecret = $config['test_client_secret'] ?? $config['client_secret'] ?? '';
                $baseUrl = 'https://devapi.hubtel.com';
            } else {
                $clientId = $config['client_id'] ?? '';
                $clientSecret = $config['client_secret'] ?? '';
                $baseUrl = 'https://api.hubtel.com';
            }
            
            if (empty($clientId) || empty($clientSecret)) {
                return ['success' => false, 'error' => 'Hubtel configuration incomplete'];
            }
            
            // Create payment request
            $paymentData = [
                'amount' => $amount,
                'currency' => $currency,
                'description' => $description,
                'customer_name' => $metadata['customer_name'] ?? 'Customer',
                'customer_email' => $metadata['customer_email'] ?? '',
                'customer_phone' => $metadata['customer_phone'] ?? ''
            ];
            
            $response = $this->makeHttpRequest($baseUrl . '/v1/merchantaccount/onlinecheckout/invoice/create', $paymentData, [
                'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret),
                'Content-Type: application/json'
            ], 'POST');
            
            if (!$response || !isset($response['ResponseCode']) || $response['ResponseCode'] !== '0000') {
                return ['success' => false, 'error' => 'Failed to create Hubtel payment'];
            }
            
            return [
                'success' => true,
                'invoice_token' => $response['Data']['InvoiceToken'],
                'checkout_url' => $response['Data']['CheckoutUrl'],
                'amount' => $amount,
                'currency' => $currency
            ];
        } catch (Exception $e) {
            error_log("Hubtel payment error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Payment processing failed'];
        }
    }
    
    /**
     * Process Flutterwave payment
     */
    private function processFlutterwavePayment($gateway, $amount, $currency, $description, $metadata) {
        try {
            $config = json_decode($gateway['config_data'], true);
            
            if ($gateway['test_mode']) {
                $secretKey = $config['test_secret_key'] ?? $config['secret_key'] ?? '';
            } else {
                $secretKey = $config['secret_key'] ?? '';
            }
            
            if (empty($secretKey)) {
                return ['success' => false, 'error' => 'Flutterwave configuration incomplete'];
            }
            
            // Create payment
            $paymentData = [
                'amount' => $amount,
                'currency' => $currency,
                'tx_ref' => 'TXN_' . time() . '_' . rand(1000, 9999),
                'redirect_url' => $gateway['return_url'] ?? '',
                'customer' => [
                    'email' => $metadata['customer_email'] ?? '',
                    'name' => $metadata['customer_name'] ?? 'Customer'
                ],
                'customizations' => [
                    'title' => 'Application Fee Payment',
                    'description' => $description
                ]
            ];
            
            $response = $this->makeHttpRequest('https://api.flutterwave.com/v3/payments', $paymentData, [
                'Authorization: Bearer ' . $secretKey,
                'Content-Type: application/json'
            ], 'POST');
            
            if (!$response || $response['status'] !== 'success') {
                return ['success' => false, 'error' => 'Failed to create Flutterwave payment'];
            }
            
            return [
                'success' => true,
                'transaction_reference' => $paymentData['tx_ref'],
                'checkout_url' => $response['data']['link'],
                'amount' => $amount,
                'currency' => $currency
            ];
        } catch (Exception $e) {
            error_log("Flutterwave payment error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Payment processing failed'];
        }
    }
    
    /**
     * Process Razorpay payment
     */
    private function processRazorpayPayment($gateway, $amount, $currency, $description, $metadata) {
        try {
            $config = json_decode($gateway['config_data'], true);
            
            if ($gateway['test_mode']) {
                $keyId = $config['test_key_id'] ?? $config['key_id'] ?? '';
                $keySecret = $config['test_key_secret'] ?? $config['key_secret'] ?? '';
            } else {
                $keyId = $config['key_id'] ?? '';
                $keySecret = $config['key_secret'] ?? '';
            }
            
            if (empty($keyId) || empty($keySecret)) {
                return ['success' => false, 'error' => 'Razorpay configuration incomplete'];
            }
            
            // Create order
            $orderData = [
                'amount' => $amount * 100, // Convert to paise
                'currency' => $currency,
                'receipt' => 'TXN_' . time() . '_' . rand(1000, 9999),
                'notes' => [
                    'description' => $description
                ]
            ];
            
            $response = $this->makeHttpRequest('https://api.razorpay.com/v1/orders', $orderData, [
                'Authorization: Basic ' . base64_encode($keyId . ':' . $keySecret),
                'Content-Type: application/json'
            ], 'POST');
            
            if (!$response || !isset($response['id'])) {
                return ['success' => false, 'error' => 'Failed to create Razorpay order'];
            }
            
            return [
                'success' => true,
                'order_id' => $response['id'],
                'amount' => $amount,
                'currency' => $currency,
                'key_id' => $keyId
            ];
        } catch (Exception $e) {
            error_log("Razorpay payment error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Payment processing failed'];
        }
    }
    
    /**
     * Verify payment
     */
    public function verifyPayment($gatewayId, $paymentData) {
        $gateway = $this->getGatewayById($gatewayId);
        if (!$gateway) {
            return ['success' => false, 'error' => 'Payment gateway not found'];
        }
        
        switch ($gateway['gateway_type']) {
            case 'stripe':
                return $this->verifyStripePayment($gateway, $paymentData);
            case 'paypal':
                return $this->verifyPayPalPayment($gateway, $paymentData);
            case 'paystack':
                return $this->verifyPaystackPayment($gateway, $paymentData);
            case 'hubtel':
                return $this->verifyHubtelPayment($gateway, $paymentData);
            case 'flutterwave':
                return $this->verifyFlutterwavePayment($gateway, $paymentData);
            case 'razorpay':
                return $this->verifyRazorpayPayment($gateway, $paymentData);
            default:
                return ['success' => false, 'error' => 'Unsupported payment gateway'];
        }
    }
    
    /**
     * Verify Stripe payment
     */
    private function verifyStripePayment($gateway, $paymentData) {
        try {
            $config = json_decode($gateway['config_data'], true);
            $secretKey = $gateway['test_mode'] ? 
                ($config['test_secret_key'] ?? $config['secret_key'] ?? '') : 
                ($config['secret_key'] ?? '');
            
            require_once 'vendor/stripe/stripe-php/init.php';
            \Stripe\Stripe::setApiKey($secretKey);
            
            $intent = \Stripe\PaymentIntent::retrieve($paymentData['payment_intent_id']);
            
            return [
                'success' => $intent->status === 'succeeded',
                'status' => $intent->status,
                'amount' => $intent->amount / 100,
                'currency' => $intent->currency
            ];
        } catch (Exception $e) {
            error_log("Stripe verification error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Payment verification failed'];
        }
    }
    
    /**
     * Verify PayPal payment
     */
    private function verifyPayPalPayment($gateway, $paymentData) {
        try {
            $config = json_decode($gateway['config_data'], true);
            $baseUrl = $gateway['test_mode'] ? 'https://api.sandbox.paypal.com' : 'https://api.paypal.com';
            
            $clientId = $gateway['test_mode'] ? 
                ($config['test_client_id'] ?? $config['client_id'] ?? '') : 
                ($config['client_id'] ?? '');
            $clientSecret = $gateway['test_mode'] ? 
                ($config['test_client_secret'] ?? $config['client_secret'] ?? '') : 
                ($config['client_secret'] ?? '');
            
            // Get access token
            $tokenResponse = $this->makeHttpRequest($baseUrl . '/v1/oauth2/token', [
                'grant_type' => 'client_credentials'
            ], [
                'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret),
                'Content-Type: application/x-www-form-urlencoded'
            ]);
            
            // Capture order
            $captureResponse = $this->makeHttpRequest(
                $baseUrl . '/v2/checkout/orders/' . $paymentData['order_id'] . '/capture',
                [],
                [
                    'Authorization: Bearer ' . $tokenResponse['access_token'],
                    'Content-Type: application/json'
                ],
                'POST'
            );
            
            return [
                'success' => $captureResponse['status'] === 'COMPLETED',
                'status' => $captureResponse['status'],
                'amount' => $captureResponse['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? 0,
                'currency' => $captureResponse['purchase_units'][0]['payments']['captures'][0]['amount']['currency_code'] ?? ''
            ];
        } catch (Exception $e) {
            error_log("PayPal verification error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Payment verification failed'];
        }
    }
    
    /**
     * Verify Paystack payment
     */
    private function verifyPaystackPayment($gateway, $paymentData) {
        try {
            $config = json_decode($gateway['config_data'], true);
            $secretKey = $gateway['test_mode'] ? 
                ($config['test_secret_key'] ?? $config['secret_key'] ?? '') : 
                ($config['secret_key'] ?? '');
            
            $response = $this->makeHttpRequest(
                'https://api.paystack.co/transaction/verify/' . $paymentData['reference'],
                [],
                [
                    'Authorization: Bearer ' . $secretKey,
                    'Content-Type: application/json'
                ]
            );
            
            return [
                'success' => $response['status'] && $response['data']['status'] === 'success',
                'status' => $response['data']['status'] ?? 'failed',
                'amount' => $response['data']['amount'] / 100 ?? 0,
                'currency' => $response['data']['currency'] ?? ''
            ];
        } catch (Exception $e) {
            error_log("Paystack verification error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Payment verification failed'];
        }
    }
    
    /**
     * Verify Hubtel payment
     */
    private function verifyHubtelPayment($gateway, $paymentData) {
        try {
            $config = json_decode($gateway['config_data'], true);
            $baseUrl = $gateway['test_mode'] ? 'https://devapi.hubtel.com' : 'https://api.hubtel.com';
            
            $clientId = $gateway['test_mode'] ? 
                ($config['test_client_id'] ?? $config['client_id'] ?? '') : 
                ($config['client_id'] ?? '');
            $clientSecret = $gateway['test_mode'] ? 
                ($config['test_client_secret'] ?? $config['client_secret'] ?? '') : 
                ($config['client_secret'] ?? '');
            
            $response = $this->makeHttpRequest(
                $baseUrl . '/v1/merchantaccount/onlinecheckout/invoice/status/' . $paymentData['invoice_token'],
                [],
                [
                    'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret),
                    'Content-Type: application/json'
                ]
            );
            
            return [
                'success' => $response['ResponseCode'] === '0000' && $response['Data']['Status'] === 'Paid',
                'status' => $response['Data']['Status'] ?? 'Failed',
                'amount' => $response['Data']['Amount'] ?? 0,
                'currency' => $response['Data']['Currency'] ?? ''
            ];
        } catch (Exception $e) {
            error_log("Hubtel verification error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Payment verification failed'];
        }
    }
    
    /**
     * Verify Flutterwave payment
     */
    private function verifyFlutterwavePayment($gateway, $paymentData) {
        try {
            $config = json_decode($gateway['config_data'], true);
            $secretKey = $gateway['test_mode'] ? 
                ($config['test_secret_key'] ?? $config['secret_key'] ?? '') : 
                ($config['secret_key'] ?? '');
            
            $response = $this->makeHttpRequest(
                'https://api.flutterwave.com/v3/transactions/' . $paymentData['transaction_id'] . '/verify',
                [],
                [
                    'Authorization: Bearer ' . $secretKey,
                    'Content-Type: application/json'
                ]
            );
            
            return [
                'success' => $response['status'] === 'success' && $response['data']['status'] === 'successful',
                'status' => $response['data']['status'] ?? 'failed',
                'amount' => $response['data']['amount'] ?? 0,
                'currency' => $response['data']['currency'] ?? ''
            ];
        } catch (Exception $e) {
            error_log("Flutterwave verification error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Payment verification failed'];
        }
    }
    
    /**
     * Verify Razorpay payment
     */
    private function verifyRazorpayPayment($gateway, $paymentData) {
        try {
            $config = json_decode($gateway['config_data'], true);
            $keyId = $gateway['test_mode'] ? 
                ($config['test_key_id'] ?? $config['key_id'] ?? '') : 
                ($config['key_id'] ?? '');
            $keySecret = $gateway['test_mode'] ? 
                ($config['test_key_secret'] ?? $config['key_secret'] ?? '') : 
                ($config['key_secret'] ?? '');
            
            $response = $this->makeHttpRequest(
                'https://api.razorpay.com/v1/payments/' . $paymentData['payment_id'],
                [],
                [
                    'Authorization: Basic ' . base64_encode($keyId . ':' . $keySecret),
                    'Content-Type: application/json'
                ]
            );
            
            return [
                'success' => $response['status'] === 'captured',
                'status' => $response['status'] ?? 'failed',
                'amount' => $response['amount'] / 100 ?? 0,
                'currency' => $response['currency'] ?? ''
            ];
        } catch (Exception $e) {
            error_log("Razorpay verification error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Payment verification failed'];
        }
    }
    
    /**
     * Make HTTP request
     */
    private function makeHttpRequest($url, $data = [], $headers = [], $method = 'GET') {
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log("HTTP request error: " . $error);
            return false;
        }
        
        if ($httpCode >= 400) {
            error_log("HTTP request failed with code: " . $httpCode);
            return false;
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Update gateway configuration
     */
    public function updateGatewayConfig($gatewayId, $configData) {
        try {
            $stmt = $this->db->getConnection()->prepare("
                UPDATE payment_gateways 
                SET config_data = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            return $stmt->execute([json_encode($configData), $gatewayId]);
        } catch (Exception $e) {
            error_log("Gateway config update error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Set default gateway
     */
    public function setDefaultGateway($gatewayId) {
        try {
            $this->db->beginTransaction();
            
            // Remove default from all gateways
            $stmt1 = $this->db->getConnection()->prepare("UPDATE payment_gateways SET is_default = 0");
            $stmt1->execute();
            
            // Set new default
            $stmt2 = $this->db->getConnection()->prepare("UPDATE payment_gateways SET is_default = 1 WHERE id = ?");
            $result = $stmt2->execute([$gatewayId]);
            
            $this->db->commit();
            return $result;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Set default gateway error: " . $e->getMessage());
            return false;
        }
    }
}
