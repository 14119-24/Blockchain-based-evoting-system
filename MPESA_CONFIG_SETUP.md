# M-Pesa Configuration Setup

The payment system is now installed, but you need to configure your M-Pesa credentials to enable payment prompts.

## Quick Setup

### Step 1: Get Credentials from Safaricom
1. Go to https://developer.safaricom.co.ke/
2. Sign up/Login to your account
3. Create a new application
4. You'll receive:
   - **Consumer Key** (looks like: `abc123def456`)
   - **Consumer Secret** (looks like: `xyz789uvw123`)
   - **Business Shortcode** (Paybill number - e.g., `174379`)
   - **Passkey** (for STK Push - e.g., `bfb279f9aa9bdbcf158e97dd1a503f6`)

### Step 2: Update Configuration
Edit `config/mpesa.php` and replace:

```php
public static $CONSUMER_KEY = 'YOUR_CONSUMER_KEY_HERE';
public static $CONSUMER_SECRET = 'YOUR_CONSUMER_SECRET_HERE';
public static $BUSINESS_SHORTCODE = 'YOUR_PAYBILL_NUMBER_HERE';
public static $PASSKEY = 'YOUR_PASSKEY_HERE';
public static $CALLBACK_URL = 'https://yoursite.com/api/mpesa-callback.php';
```

With your actual credentials:

```php
public static $CONSUMER_KEY = 'your_actual_consumer_key';
public static $CONSUMER_SECRET = 'your_actual_consumer_secret';
public static $BUSINESS_SHORTCODE = '174379';
public static $PASSKEY = 'bfb279f9aa9bdbcf158e97dd1a503f6';
public static $CALLBACK_URL = 'http://localhost/voting_system/api/mpesa-callback.php';
```

### Step 3: Set Callback URL
In your Safaricom developer dashboard, set the Callback URL to:
```
http://localhost/voting_system/api/mpesa-callback.php
```
(For production, use your actual domain)

### Step 4: Test Payment Flow
1. Go to http://localhost/voting_system/public/candidate-register.html
2. Fill in the registration form
3. Enter your phone number in payment section
4. Click "Send Payment Prompt"
5. You should receive an M-Pesa prompt on your phone

## Testing with Sandbox
- The config is set to **sandbox mode** by default
- Use test credentials from the Safaricom dashboard
- Safaricom provides test phone numbers for sandbox testing

## Production Deployment
When ready for production:
1. Update `ENVIRONMENT` to `production`
2. Uncomment production API URLs in config/mpesa.php
3. Get production credentials from Safaricom
4. Update `CALLBACK_URL` to your production domain
5. Ensure your server has internet access to Safaricom APIs

## Receiver Account (0758488543)
If you want payments to go to the number `0758488543`:
- This must be a registered Paybill/Till with Safaricom
- Set `BUSINESS_SHORTCODE` to that paybill number
- All payments from the form will be sent to this account

## Payment Flow
1. Candidate fills registration form with phone number
2. System initiates M-Pesa STK push to that phone
3. Payment prompt appears on candidate's phone
4. Candidate enters M-Pesa PIN
5. Payment is processed and stored in `payment_requests` table
6. Candidate can complete registration

## Troubleshooting

### "Network error" when sending payment prompt
- Check PHP error log: `C:\xampp\apache\logs\error.log`
- Ensure M-Pesa credentials are filled in config/mpesa.php
- Verify internet connectivity (API calls to Safaricom)
- Check that callback URL is accessible

### Payment status not updating
- Ensure payment_requests table exists in database
- Check M-Pesa response codes in database
- Verify callback handler is configured

### Invalid phone number
- Use format: `0XXXXXXXXX`, `254XXXXXXXXX`, or `+254XXXXXXXXX`
- Must be 10 digits after country code
