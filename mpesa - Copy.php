<?php

$localConfigPath = __DIR__ . '/mpesa.local.php';
if (file_exists($localConfigPath)) {
    require_once $localConfigPath;
    return;
}

require_once __DIR__ . '/env.php';

class MpesaConfig {
    public static $CONSUMER_KEY = '';
    public static $CONSUMER_SECRET = '';
    public static $BUSINESS_SHORTCODE = '';
    public static $PASSKEY = '';
    public static $TRANSACTION_TYPE = 'CustomerPayBillOnline';
    public static $ENVIRONMENT = 'sandbox';
    public static $SIMULATE = true;
    public static $OAUTH_URL = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
    public static $STK_PUSH_URL = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
    public static $QUERY_URL = 'https://sandbox.safaricom.co.ke/mpesa/stkpushquery/v1/query';
    public static $CALLBACK_URL = '';
    public static $TIMEOUT = 30;
    public static $CONNECTION_TIMEOUT = 15;

    public static function initialize() {
        $environment = strtolower(trim((string) app_env('MPESA_ENVIRONMENT', 'sandbox')));
        $isProduction = in_array($environment, ['production', 'live'], true);

        self::$CONSUMER_KEY = (string) app_env('MPESA_CONSUMER_KEY', '');
        self::$CONSUMER_SECRET = (string) app_env('MPESA_CONSUMER_SECRET', '');
        self::$BUSINESS_SHORTCODE = (string) app_env('MPESA_BUSINESS_SHORTCODE', '');
        self::$PASSKEY = (string) app_env('MPESA_PASSKEY', '');
        self::$TRANSACTION_TYPE = (string) app_env('MPESA_TRANSACTION_TYPE', 'CustomerPayBillOnline');
        self::$ENVIRONMENT = $isProduction ? 'production' : 'sandbox';
        self::$SIMULATE = app_env_bool('MPESA_SIMULATE', !$isProduction);
        self::$CALLBACK_URL = trim((string) app_env('MPESA_CALLBACK_URL', ''));
        self::$TIMEOUT = app_env_int('MPESA_TIMEOUT', 30);
        self::$CONNECTION_TIMEOUT = app_env_int('MPESA_CONNECTION_TIMEOUT', 15);

        if ($isProduction) {
            self::$OAUTH_URL = 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
            self::$STK_PUSH_URL = 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
            self::$QUERY_URL = 'https://api.safaricom.co.ke/mpesa/stkpushquery/v1/query';
            return;
        }

        self::$OAUTH_URL = 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
        self::$STK_PUSH_URL = 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
        self::$QUERY_URL = 'https://sandbox.safaricom.co.ke/mpesa/stkpushquery/v1/query';
    }
}

MpesaConfig::initialize();
