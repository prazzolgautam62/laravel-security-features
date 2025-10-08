<?php

return [
    'enable_2fa' => env('SECURITY_2FA', false),
    'enable_login_logs' => env('SECURITY_LOGIN_LOGS', false),
    'enable_device_management' => env('SECURITY_DEVICE_MANAGEMENT', false),
    'user_model' => 'App\\Models\\User', // Configurable if users table is elsewhere
    'verification_code_expiry' => 10, // Minutes
    'device_identifier' => 'user_agent', // Or 'user_agent_ip' for stricter (but IP changes often)
    'email_from' => env('MAIL_FROM_ADDRESS', 'no-reply@yourapp.com'),
];