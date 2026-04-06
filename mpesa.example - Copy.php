<?php
/**
 * Copy this file to config/mpesa.php and fill in your credentials.
 * Do not commit real M-Pesa secrets to GitHub.
 */

class MpesaConfig {
    public static $CONSUMER_KEY = 'YOUR_CONSUMER_KEY';
    public static $CONSUMER_SECRET = 'YOUR_CONSUMER_SECRET';

    public static $BUSINESS_SHORTCODE = 'YOUR_SHORTCODE';
    public static $PASSKEY = 'YOUR_PASSKEY';
    public static $TRANSACTION_TYPE = 'CustomerPayBillOnline';

    public static $ENVIRONMENT = 'sandbox';
    public static $SIMULATE = true;

    public static $OAUTH_URL = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
    public static $STK_PUSH_URL = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
    public static $QUERY_URL = 'https://sandbox.safaricom.co.ke/mpesa/stkpushquery/v1/query';

    public static $CALLBACK_URL = 'https://your-domain.example/voting_system/api/mpesa-callback.php';

    public static $TIMEOUT = 30;
    public static $CONNECTION_TIMEOUT = 15;
}
?>
