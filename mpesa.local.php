<?php
/**
 * M-Pesa Configuration
 * 
 * To use this, you need M-Pesa Business API credentials from Safaricom:
 * 1. Go to https://developer.safaricom.co.ke/
 * 2. Create an account and register your application
 * 3. You'll receive:
 *    - Consumer Key
 *    - Consumer Secret
 *    - Business Shortcode (Paybill number)
 *    - Passkey (for STK Push)
 * 4. Fill in the credentials below
 */

class MpesaConfig {
    // ===== FILL IN YOUR CREDENTIALS HERE =====
    // Get these from https://developer.safaricom.co.ke/
    
    // OAuth credentials
    public static $CONSUMER_KEY = 'QAoTWdQk75Gu4dstA0eRG9sNUtTOGy03mDoFeUFTbtMICIEx';
    public static $CONSUMER_SECRET = 'Tbk7n6t98Qr9hUH7ALWNcXt6RBWA2efyysTGFRxyaVasDkIEJDByC0TTRTpA2OrF';
    
    // Business account details
    public static $BUSINESS_SHORTCODE = '174379';
    public static $PASSKEY = 'bfb279f9aa9bdbcf158e97dd1a503f6';
    public static $TRANSACTION_TYPE = 'CustomerPayBillOnline';
    
    // API URLs (use sandbox for testing, production for live)
    public static $ENVIRONMENT = 'sandbox';
    // Set to false to enable real STK Push calls to Safaricom's API.
    // WARNING: when false you MUST provide valid M-Pesa credentials and a publicly
    // reachable callback URL (use ngrok for local development). Do NOT commit
    // real secrets to a public repo.
    public static $SIMULATE = false;
    
    public static $OAUTH_URL = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
    public static $STK_PUSH_URL = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
    public static $QUERY_URL = 'https://sandbox.safaricom.co.ke/mpesa/stkpushquery/v1/query';
    
    // Callback must be reachable by Safaricom (HTTPS recommended). Example for ngrok:
    // 'https://abcd1234.ngrok.io/voting_system/api/mpesa-callback.php'
    public static $CALLBACK_URL = 'https://<your-public-host>/voting_system/api/mpesa-callback.php';
    
    public static $TIMEOUT = 30;
    public static $CONNECTION_TIMEOUT = 15;
}
?>
