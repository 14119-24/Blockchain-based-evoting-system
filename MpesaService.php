<?php
/**
 * M-Pesa Service Class
 * Handles all M-Pesa API interactions including:
 * - OAuth token generation
 * - STK Push (payment prompt to phone)
 * - Payment status queries
 * - Callback handling
 */

require_once __DIR__ . '/../config/mpesa.php';

class MpesaService {
    private $accessToken = null;
    private $tokenExpiry = null;

    private function getBusinessShortcode() {
        return preg_replace('/\D+/', '', (string) MpesaConfig::$BUSINESS_SHORTCODE);
    }

    private function getTransactionType() {
        $configuredType = property_exists('MpesaConfig', 'TRANSACTION_TYPE')
            ? trim((string) MpesaConfig::$TRANSACTION_TYPE)
            : '';

        return $configuredType !== '' ? $configuredType : 'CustomerPayBillOnline';
    }

    private function resolveCallbackUrl() {
        $configuredUrl = trim((string) MpesaConfig::$CALLBACK_URL);
        $hasPlaceholder = $configuredUrl === ''
            || strpos($configuredUrl, '<your-public-host>') !== false
            || strpos($configuredUrl, 'yoursite.com') !== false;

        if (!$hasPlaceholder) {
            return $configuredUrl;
        }

        $host = $_SERVER['HTTP_HOST'] ?? '';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';

        if ($host === '' || $scriptName === '') {
            return $configuredUrl;
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $scriptDir = str_replace('\\', '/', dirname($scriptName));
        $scriptDir = $scriptDir === '/' || $scriptDir === '.' ? '' : rtrim($scriptDir, '/');

        return $scheme . '://' . $host . $scriptDir . '/mpesa-callback.php';
    }
    
    /**
     * Get OAuth access token from M-Pesa API
     * Tokens are cached for 1 hour
     */
    public function getAccessToken() {
        // If simulation mode is enabled, return a fake token for local testing
        if (!empty(MpesaConfig::$SIMULATE)) {
            $this->accessToken = 'SIMULATED_TOKEN';
            $this->tokenExpiry = time() + 3300;
            return $this->accessToken;
        }

        // Validate configuration before attempting network calls
        $this->validateConfig();

        // Return cached token if still valid
        if ($this->accessToken && $this->tokenExpiry && time() < $this->tokenExpiry) {
            return $this->accessToken;
        }
        
        $credentials = base64_encode(MpesaConfig::$CONSUMER_KEY . ':' . MpesaConfig::$CONSUMER_SECRET);
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => MpesaConfig::$OAUTH_URL,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => MpesaConfig::$CONSUMER_KEY . ':' . MpesaConfig::$CONSUMER_SECRET,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_TIMEOUT => MpesaConfig::$CONNECTION_TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        
        if ($error) {
            error_log("M-Pesa OAuth Error: $error");
            throw new Exception("Failed to connect to M-Pesa OAuth endpoint: $error. Check network connectivity and your Mpesa credentials in config/mpesa.php.");
        }
        
        if ($httpCode != 200) {
            error_log("M-Pesa OAuth HTTP Error: $httpCode - $response");
            throw new Exception("M-Pesa OAuth failed with HTTP $httpCode. Verify your Consumer Key/Secret and ensure the OAuth URL is reachable.");
        }
        
        $data = json_decode($response, true);
        
        if (!isset($data['access_token'])) {
            error_log("M-Pesa OAuth Response: " . $response);
            throw new Exception("No access token in M-Pesa response");
        }
        
        $this->accessToken = $data['access_token'];
        // Token expires in 3600 seconds (1 hour), cache for 55 minutes
        $this->tokenExpiry = time() + 3300;
        
        error_log("M-Pesa: New access token obtained, valid until " . date('Y-m-d H:i:s', $this->tokenExpiry));
        
        return $this->accessToken;
    }
    
    /**
     * Initiate STK Push - sends payment prompt to customer's phone
     * 
     * @param string $phoneNumber Customer's M-Pesa registered phone (format: 254XXXXXXXXX or +254XXXXXXXXX)
     * @param float $amount Amount to charge (in KES)
     * @param string $accountReference Reference for the transaction
     * @param string $description Transaction description
     * @return array Response with checkout_request_id
     */
    public function stkPush($phoneNumber, $amount, $accountReference, $description = '') {
        // If simulation mode is enabled, return a fake CheckoutRequestID
        if (!empty(MpesaConfig::$SIMULATE)) {
            $phoneNumber = $this->formatPhoneNumber($phoneNumber) ?: $phoneNumber;
            $simId = 'SIMCHECK-' . uniqid();
            error_log("M-Pesa SIMULATE STK Push: CheckoutRequestID=$simId, Phone=$phoneNumber, Amount=$amount");
            return [
                'success' => true,
                'checkout_request_id' => $simId,
                'response_code' => '0',
                'response_message' => 'Simulated request accepted',
                'customer_message' => 'Simulated: enter PIN on phone'
            ];
        }

        // Validate configuration before attempting STK push
        $this->validateConfig();

        // Validate and format phone number
        $phoneNumber = $this->formatPhoneNumber($phoneNumber);
        if (!$phoneNumber) {
            throw new Exception("Invalid phone number format. Use 254XXXXXXXXX or +254XXXXXXXXX");
        }
        
        // Get access token
        $token = $this->getAccessToken();
        
        // Generate timestamp for password encoding
        $timestamp = date('YmdHis');
        $businessShortcode = $this->getBusinessShortcode();
        $callbackUrl = $this->resolveCallbackUrl();
        
        // Generate password: Base64(Shortcode + Passkey + Timestamp)
        $password = base64_encode($businessShortcode . MpesaConfig::$PASSKEY . $timestamp);
        
        // Prepare STK Push request
        $payload = [
            'BusinessShortCode' => $businessShortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => $this->getTransactionType(),
            'Amount' => intval($amount),
            'PartyA' => $phoneNumber,
            'PartyB' => $businessShortcode,
            'PhoneNumber' => $phoneNumber,
            'CallBackURL' => $callbackUrl,
            'AccountReference' => $accountReference,
            'TransactionDesc' => $description ?: 'Payment for ' . $accountReference
        ];
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => MpesaConfig::$STK_PUSH_URL,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => MpesaConfig::$TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        
        if ($error) {
            error_log("M-Pesa STK Push Error: $error");
            throw new Exception("Failed to send payment prompt: $error. Check network connectivity, Mpesa endpoints and that your credentials are correct.");
        }
        
        $data = json_decode($response, true);
        
        if ($httpCode != 200) {
            $errorMsg = $data['errorMessage'] ?? ($data['error'] ?? 'Unknown error');
            error_log("M-Pesa STK Push HTTP Error: $httpCode - $errorMsg");
            throw new Exception("M-Pesa STK Push failed (HTTP $httpCode): $errorMsg. Ensure your Business Shortcode and Passkey are correct and that your callback URL is reachable.");
        }
        
        if (!isset($data['CheckoutRequestID'])) {
            error_log("M-Pesa STK Push Response: " . $response);
            throw new Exception("No CheckoutRequestID in M-Pesa response");
        }
        
        error_log("M-Pesa STK Push Success: CheckoutRequestID = " . $data['CheckoutRequestID'] . ", Phone = $phoneNumber, Amount = $amount");
        
        return [
            'success' => true,
            'checkout_request_id' => $data['CheckoutRequestID'],
            'response_code' => $data['ResponseCode'] ?? '0',
            'response_message' => $data['ResponseDescription'] ?? 'Request accepted',
            'customer_message' => $data['CustomerMessage'] ?? 'Please enter your M-Pesa PIN'
        ];
    }
    
    /**
     * Query STK Push status
     * Use this to check if customer completed/cancelled the payment
     * 
     * @param string $checkoutRequestId The checkout request ID from stkPush response
     * @return array Status information
     */
    public function querySTKPushStatus($checkoutRequestId) {
        // If simulation mode is enabled, return a simulated completed status
        if (!empty(MpesaConfig::$SIMULATE)) {
            error_log("M-Pesa SIMULATE Query: CheckoutRequestID=$checkoutRequestId -> completed");
            return [
                'success' => true,
                'status' => 'completed',
                'result_code' => 0,
                'result_description' => 'Simulated payment completed'
            ];
        }

        // Validate configuration before attempting query
        $this->validateConfig();

        $token = $this->getAccessToken();
        $timestamp = date('YmdHis');
        $businessShortcode = $this->getBusinessShortcode();
        $password = base64_encode($businessShortcode . MpesaConfig::$PASSKEY . $timestamp);
        
        $payload = [
            'BusinessShortCode' => $businessShortcode,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'CheckoutRequestID' => $checkoutRequestId
        ];
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => MpesaConfig::$QUERY_URL,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => MpesaConfig::$TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        
        if ($error) {
            error_log("M-Pesa Query Error: $error");
            return [
                'success' => false,
                'error' => "Failed to query payment status: $error. Check network connectivity and Mpesa query URL."
            ];
        }
        
        $data = json_decode($response, true);
        
        if ($httpCode != 200) {
            $errorMsg = $data['errorMessage'] ?? ($data['error'] ?? 'Unknown error');
            error_log("M-Pesa Query HTTP Error: $httpCode - $errorMsg");
            return [
                'success' => false,
                'error' => "M-Pesa query failed (HTTP $httpCode): $errorMsg"
            ];
        }
        
        // Result code 0 = success, 1 = user cancelled
        $resultCode = intval($data['ResultCode'] ?? -1);
        
        $statusMap = [
            0 => 'completed',
            1 => 'cancelled',
            -1 => 'pending'
        ];
        
        $status = $statusMap[$resultCode] ?? 'unknown';
        
        error_log("M-Pesa Query: CheckoutRequestID = $checkoutRequestId, Status = $status, ResultCode = $resultCode");
        
        return [
            'success' => true,
            'status' => $status,
            'result_code' => $resultCode,
            'message_indicator' => $data['MessageIndicator'] ?? null,
            'merchant_request_id' => $data['MerchantRequestID'] ?? null,
            'result_description' => $data['ResultDesc'] ?? null
        ];
    }

    /**
     * Validate Mpesa configuration values to catch obvious misconfiguration early.
     * Throws Exception if configuration appears to be uninitialized or invalid.
     */
    private function validateConfig() {
        // Allow simulation mode to bypass config validation
        if (!empty(MpesaConfig::$SIMULATE)) {
            return true;
        }
        $missing = [];
        if (empty(MpesaConfig::$CONSUMER_KEY) || strpos(MpesaConfig::$CONSUMER_KEY, 'YOUR_') === 0) {
            $missing[] = 'CONSUMER_KEY';
        }
        if (empty(MpesaConfig::$CONSUMER_SECRET) || strpos(MpesaConfig::$CONSUMER_SECRET, 'YOUR_') === 0) {
            $missing[] = 'CONSUMER_SECRET';
        }
        $businessShortcode = $this->getBusinessShortcode();
        if (
            $businessShortcode === ''
            || strpos((string) MpesaConfig::$BUSINESS_SHORTCODE, 'YOUR_') === 0
            || strlen($businessShortcode) < 5
            || strlen($businessShortcode) > 7
        ) {
            $missing[] = 'BUSINESS_SHORTCODE';
        }
        if (empty(MpesaConfig::$PASSKEY) || strpos(MpesaConfig::$PASSKEY, 'YOUR_') === 0) {
            $missing[] = 'PASSKEY';
        }
        $callbackUrl = $this->resolveCallbackUrl();
        if (
            $callbackUrl === ''
            || strpos($callbackUrl, 'yoursite.com') !== false
            || strpos($callbackUrl, '<your-public-host>') !== false
        ) {
            $missing[] = 'CALLBACK_URL';
        }

        if (!empty($missing)) {
            $list = implode(', ', $missing);
            throw new Exception("Mpesa configuration appears incomplete or contains placeholders: $list. Please set the values in config/mpesa.php and ensure the callback URL is reachable.");
        }
    }
    
    /**
     * Format phone number to M-Pesa standard format (254XXXXXXXXX)
     * Accepts: 0XXXXXXXXX, 254XXXXXXXXX, +254XXXXXXXXX, +254 X XXXX XXXX, etc.
     */
    private function formatPhoneNumber($phone) {
        // Remove all non-digit characters except leading +
        $phone = preg_replace('/[^\d\+]/', '', $phone);
        
        // Remove + if present
        $phone = ltrim($phone, '+');
        
        // If starts with 0, replace with 254
        if (substr($phone, 0, 1) === '0') {
            $phone = '254' . substr($phone, 1);
        }
        
        // If doesn't start with 254, assume it's missing
        if (substr($phone, 0, 3) !== '254') {
            $phone = '254' . ltrim($phone, '0');
        }
        
        // Validate: should be 254 + 9 digits
        if (!preg_match('/^254\d{9}$/', $phone)) {
            return false;
        }
        
        return $phone;
    }
}
?>
