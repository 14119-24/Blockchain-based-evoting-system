# M-Pesa Integration Setup Guide

## Overview

Your voting system now has **real M-Pesa payment integration** implemented. When candidates register and need to pay the 5 KES fee, the system will send an actual M-Pesa payment prompt to their phone.

## Prerequisites

Before you can use the real M-Pesa integration, you need:

1. **Safaricom M-Pesa Business Account** - A registered M-Pesa merchant account
2. **M-Pesa Business API Credentials** - Get these from [Safaricom Developer Portal](https://developer.safaricom.co.ke/)
3. **SSL Certificate** - For production use (callback URL must be HTTPS)
4. **Public Server** - Your callback URL must be publicly accessible

## Step 1: Get M-Pesa API Credentials

1. Go to https://developer.safaricom.co.ke/
2. Register or log in to your account
3. Create a new application (choose "M-Pesa" as the product)
4. You'll receive:
   - **Consumer Key** 
   - **Consumer Secret**
   - **Business Shortcode** (your Paybill number)
   - **Passkey** (for STK Push)

## Step 2: Configure Your M-Pesa Credentials

Edit the file: `/config/mpesa.php`

Replace the placeholders with your actual credentials:

```php
class MpesaConfig {
    public static $CONSUMER_KEY = 'YOUR_CONSUMER_KEY_HERE';
    public static $CONSUMER_SECRET = 'YOUR_CONSUMER_SECRET_HERE';
    public static $BUSINESS_SHORTCODE = 'YOUR_PAYBILL_NUMBER_HERE';
    public static $PASSKEY = 'YOUR_PASSKEY_HERE';
    
    // For testing, use sandbox. For production, comment out sandbox and uncomment production URLs
    public static $ENVIRONMENT = 'sandbox';
}
```

## Step 3: Configure Callback URL

In `/config/mpesa.php`, set your callback URL:

```php
public static $CALLBACK_URL = 'https://yoursite.com/api/mpesa-callback.php';
```

**Important:** 
- Use `https://` (not `http://`)
- Must be publicly accessible
- Safaricom will POST payment results to this URL

## Step 4: Test the Integration

### Testing in Sandbox (Recommended First)

The credentials you get from Safaricom are for **sandbox (test) environment** by default.

Safaricom provides test phone numbers for testing:
- **254708374149** - Test account that completes payments
- **254799999999** - Test account for other scenarios

To test:
1. Go to candidate registration
2. Fill in all fields
3. Use one of the test phone numbers
4. When prompted for M-Pesa PIN, enter: **1234**
5. The system will auto-complete the payment

### Switching to Production

When ready for live payments:

1. Request production approval from Safaricom
2. In `/config/mpesa.php`, change:
   ```php
   public static $ENVIRONMENT = 'production';
   ```
3. Uncomment the production URLs in the config file
4. Update `$CALLBACK_URL` to your production URL
5. Ensure SSL certificate is valid (production requires HTTPS)

## How It Works

### Payment Flow

1. **Candidate initiates payment** → Enters phone number and clicks "Send Payment Prompt"
2. **Backend creates transaction** → Stores payment request in `payment_requests` table
3. **M-Pesa STK Push sent** → API sends payment popup to candidate's phone
4. **Candidate enters M-Pesa PIN** → On their phone (not on the website)
5. **M-Pesa processes payment** → Sends confirmation back to your server
6. **Frontend polls for status** → Checks every 1 second if payment completed
7. **Payment detected** → System auto-registers the candidate
8. **Dashboard loads** → Candidate redirected to their dashboard

### Files Involved

| File | Purpose |
|------|---------|
| `/config/mpesa.php` | M-Pesa credentials and configuration |
| `/core/MpesaService.php` | M-Pesa API wrapper (OAuth, STK Push, Queries) |
| `/api/candidate-auth.php` | Handles payment initiation & status checks |
| `/api/mpesa-callback.php` | Receives payment confirmations from M-Pesa |
| `/database/create-payment-table.sql` | Tracks all payment requests |

### Database Table

The `payment_requests` table stores:
- `transaction_id` - Unique ID for each payment attempt
- `phone_number` - Customer's M-Pesa phone
- `amount` - Payment amount (KES)
- `status` - pending, completed, failed, cancelled, expired
- `mpesa_response_code` - M-Pesa checkout request ID
- `mpesa_confirmation_code` - M-Pesa receipt number (set on successful payment)
- `created_at` - When payment was initiated
- `expires_at` - Auto-expires after 2 minutes

## API Endpoints

### 1. Initiate Payment (Start STK Push)

**Endpoint:** `POST /api/candidate-auth.php?action=initiate_mpesa_payment`

**Request:**
```json
{
    "phone_number": "254720123456",
    "amount": 5,
    "currency": "KES",
    "description": "Candidate Registration Fee"
}
```

**Response (Success):**
```json
{
    "success": true,
    "message": "Payment prompt sent to your phone",
    "transaction_id": "TXN-1703262000-12345"
}
```

**Response (Error):**
```json
{
    "success": false,
    "error": "Invalid phone number format"
}
```

### 2. Check Payment Status

**Endpoint:** `POST /api/candidate-auth.php?action=check_payment_status`

**Request:**
```json
{
    "transaction_id": "TXN-1703262000-12345"
}
```

**Response:**
```json
{
    "success": true,
    "transaction_id": "TXN-1703262000-12345",
    "payment_status": "completed",
    "phone_number": "254720123456",
    "amount": 5,
    "currency": "KES"
}
```

## Troubleshooting

### "Invalid credentials" or "401 Unauthorized"

- Verify Consumer Key and Consumer Secret are correct
- Make sure you're using the right keys (sandbox vs production)
- Check that credentials haven't expired

### "M-Pesa STK Push failed"

- Verify phone number format (should be 254XXXXXXXXX)
- Check that business shortcode is correct
- Ensure amount is within limits
- Verify callback URL is configured

### Payment not appearing on phone

- For sandbox, use test phone numbers provided by Safaricom
- Check server error logs for API errors
- Ensure `mpesa-callback.php` is accessible
- Verify callback URL matches configuration

### Callback not being received

- Ensure callback URL is public (not localhost)
- Must be HTTPS for production
- Check firewall isn't blocking incoming requests
- Verify callback URL matches exactly in config

### Check Logs

Server error logs are saved to:
- On Windows XAMPP: `C:\xampp\apache\logs\error.log`
- On Linux: `/var/log/apache2/error.log` or `php-fpm.log`

To see real-time logs:
```bash
tail -f /var/log/apache2/error.log
```

Search for "M-Pesa" to find payment-related logs.

## Phone Number Formats

The system accepts these formats and auto-converts them:

| Input | Converted To |
|-------|--------------|
| `0720123456` | `254720123456` |
| `254720123456` | `254720123456` |
| `+254720123456` | `254720123456` |
| `+254 720 123 456` | `254720123456` |
| `254-720-123-456` | `254720123456` |

## Cost Considerations

- **Safaricom charges per STK Push** - Typically 0.5-1 KES per prompt
- **You can only control the payment amount, not the prompt charge**
- This is separate from the 5 KES registration fee

## Security Notes

1. **Never commit credentials to git** - Add to `.gitignore`
2. **Use environment variables for sensitive data** - Consider using `.env` files
3. **HTTPS required for production** - Non-HTTPS callbacks will fail
4. **Validate callbacks** - The callback handler should verify request signatures
5. **PCI Compliance** - Your system doesn't store card data, so PCI compliance is simplified

## Support Resources

- **Safaricom Developer Portal**: https://developer.safaricom.co.ke/
- **M-Pesa API Documentation**: https://developer.safaricom.co.ke/docs
- **Test Credentials**: Available in your Safaricom developer account
- **STK Push Documentation**: Look for "Lipa Na M-Pesa Online" in the docs

## Next Steps

1. ✅ Add your M-Pesa credentials to `/config/mpesa.php`
2. ✅ Test with sandbox credentials and test phone numbers
3. ✅ Verify callbacks are being received
4. ✅ Monitor logs for any errors
5. ✅ Request production approval from Safaricom
6. ✅ Switch to production credentials
7. ✅ Monitor your first live payments

Once configured, the system will automatically:
- Send payment prompts to candidate phones
- Track all payment attempts
- Update candidate status on successful payment
- Auto-register candidates after payment
- Handle payment expiration (2 minutes)

Your system is ready for real M-Pesa payments! 🎉
