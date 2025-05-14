<?php
// callback.php - Payment callback endpoint for Africa's Talking

require_once 'config.php';
require_once 'database.php';
require_once 'payment_handler.php';
require_once 'sms_handler.php';

// Initialize the database connection
$db = new Database();
$conn = $db->getConnection();

// Get the JSON payload
$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

// Log the callback if in debug mode
if (DEBUG_MODE) {
    error_log("Payment Callback Received: " . $payload);
}

// Validate the data
if (!$data || !isset($data['status'])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid payload']);
    exit;
}

// Process the payment notification
$paymentHandler = new PaymentHandler();
$result = $paymentHandler->handlePaymentCallback($data);

// Respond to the callback
if ($result) {
    http_response_code(200);
    echo json_encode(['status' => 'success']);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to process payment callback']);
}