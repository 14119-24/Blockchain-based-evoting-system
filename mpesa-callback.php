<?php
/**
 * M-Pesa Callback Handler
 * 
 * This file receives callbacks from M-Pesa when:
 * - Payment is completed
 * - Payment times out
 * - User cancels payment
 * 
 * M-Pesa will POST the payment result to this URL configured in MpesaConfig::$CALLBACK_URL
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/mpesa.php';

// Log all incoming callback data for debugging
$callbackData = file_get_contents('php://input');
error_log("M-Pesa Callback Received: " . $callbackData);

// Parse the callback
$data = json_decode($callbackData, true);

if (!$data) {
    http_response_code(400);
    error_log("Invalid callback data");
    exit;
}

try {
    $db = new Database();
    $pdo = $db->connect();
    
    if (!$pdo) {
        throw new Exception("Database connection failed");
    }
    
    // M-Pesa callback structure for STK Push:
    // {
    //   "Body": {
    //     "stkCallback": {
    //       "MerchantRequestID": "...",
    //       "CheckoutRequestID": "...",
    //       "ResultCode": 0,  // 0 = success, 1 = cancelled, non-zero = error
    //       "ResultDesc": "The service request has been processed successfully.",
    //       "CallbackMetadata": {
    //         "Item": [
    //           {"Name": "Amount", "Value": 5.0},
    //           {"Name": "MpesaReceiptNumber", "Value": "LHG31AL60V"},
    //           {"Name": "TransactionDate", "Value": 20240123120530},
    //           {"Name": "PhoneNumber", "Value": "254720123456"}
    //         ]
    //       }
    //     }
    //   }
    // }
    
    $stkCallback = $data['Body']['stkCallback'] ?? null;
    
    if (!$stkCallback) {
        throw new Exception("Invalid STK callback structure");
    }
    
    $checkoutRequestID = $stkCallback['CheckoutRequestID'] ?? null;
    $resultCode = intval($stkCallback['ResultCode'] ?? -1);
    $resultDesc = $stkCallback['ResultDesc'] ?? 'Unknown';
    
    // Extract metadata
    $items = $stkCallback['CallbackMetadata']['Item'] ?? [];
    $callbackMetadata = [];
    
    foreach ($items as $item) {
        $callbackMetadata[$item['Name']] = $item['Value'];
    }
    
    $amount = $callbackMetadata['Amount'] ?? 0;
    $mpesaReceiptNumber = $callbackMetadata['MpesaReceiptNumber'] ?? '';
    $transactionDate = $callbackMetadata['TransactionDate'] ?? '';
    $phoneNumber = $callbackMetadata['PhoneNumber'] ?? '';
    
    // Map M-Pesa result codes to payment status
    $statusMap = [
        0 => 'completed',
        1 => 'cancelled'
    ];
    
    $status = $statusMap[$resultCode] ?? 'failed';
    
    // Find the payment request by checkout request ID
    $stmt = $pdo->prepare("
        SELECT * FROM payment_requests 
        WHERE mpesa_response_code = ?
    ");
    $stmt->execute([$checkoutRequestID]);
    
    if ($stmt->rowCount() === 0) {
        error_log("Payment request not found for CheckoutRequestID: $checkoutRequestID");
        // Still return 200 to acknowledge receipt
        http_response_code(200);
        exit;
    }
    
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Update payment request with M-Pesa response
    $updateStmt = $pdo->prepare("
        UPDATE payment_requests 
        SET status = ?, 
            mpesa_confirmation_code = ?, 
            updated_at = NOW()
        WHERE transaction_id = ?
    ");
    $updateStmt->execute([$status, $mpesaReceiptNumber, $payment['transaction_id']]);
    
    error_log("Payment Status Updated: TxnID={$payment['transaction_id']}, Status=$status, Receipt=$mpesaReceiptNumber, Phone=$phoneNumber, Amount=$amount");
    
    // If payment is successful, you might want to trigger other actions here
    // For example: sending confirmation email, updating candidate status, etc.
    if ($status === 'completed') {
        // TODO: Add any post-payment actions here
        error_log("Payment Completed: TxnID={$payment['transaction_id']}, Candidate can now be registered");
    }
    
    // Return success to M-Pesa
    http_response_code(200);
    
} catch (Exception $e) {
    error_log("M-Pesa Callback Error: " . $e->getMessage());
    http_response_code(500);
}
?>
