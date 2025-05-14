<?php
// config.php - Configuration settings

// Application configuration
define('DEBUG_MODE', true);

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'event_registration');

// Africa's Talking configuration
define('AT_USERNAME', 'sandbox');  // Replace with your Africa's Talking username
define('AT_API_KEY', 'atsk_c03b1fa9ec5104f02fbdcb25055f0ecb900af3b10d20159223bc1e194ef015dc10063957');    // Replace with your Africa's Talking API key
define('AT_USSD_CODE', '384*84100#');    // Your specific USSD code

// SMS configuration
define('SMS_SENDER_ID', 'EightMM');    // Alphanumeric sender ID for Africa's Talking

// Payment configuration
define('PAYMENT_PRODUCT_NAME', 'Event Registration');
define('DEFAULT_CURRENCY', 'RWF');       // Rwandan Francs