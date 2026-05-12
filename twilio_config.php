<?php
/**
 * Twilio SMS Configuration
 * Get these values from your Twilio Console: https://console.twilio.com
 */

// TEST MODE: Set to true to log SMS instead of sending (free testing)
define('TWILIO_TEST_MODE', true);

// Your Twilio Account SID (starts with AC...) - Required if TEST_MODE is false
define('TWILIO_ACCOUNT_SID', 'ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');

// Your Twilio Auth Token - Required if TEST_MODE is false
define('TWILIO_AUTH_TOKEN', 'your_auth_token_here');

// Your Twilio phone number (starts with +) - Required if TEST_MODE is false
define('TWILIO_PHONE_NUMBER', '+1234567890');

// Default country code for phone numbers (without +)
define('DEFAULT_COUNTRY_CODE', '91'); // India: 91, USA: 1, UK: 44, etc.

/**
 * SETUP INSTRUCTIONS:
 *
 * 1. Sign up at https://www.twilio.com/try-twilio
 * 2. Verify your email and phone number
 * 3. Go to Console Dashboard
 * 4. Copy Account SID and Auth Token
 * 5. Get a Twilio phone number from Phone Numbers > Manage
 * 6. Replace the YOUR_TWILIO_* values above with your actual credentials
 * 7. Test with a small amount of credits first
 *
 * COST: Approximately $0.05-0.10 per SMS depending on destination
 */
?>