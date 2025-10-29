<?php

return [
    'enable_email_verify' => env('SECURITY_EMAIL_VERIFY', false), // tenant email verify
    'enable_2fa' => env('SECURITY_2FA', false), // each user verify
    'enable_login_logs' => env('SECURITY_LOGIN_LOGS', false),
    'enable_device_management' => env('SECURITY_DEVICE_MANAGEMENT', false),
    'user_model' => 'App\\Models\\User', // Configurable if users table is elsewhere
    'verification_code_expiry' => env('VERIFICATION_CODE_EXPIRY',2), // Minutes
    'device_identifier' => 'user_agent', // Or 'user_agent_ip' for stricter (but IP changes often)
    'email_from' => env('MAIL_FROM_ADDRESS', 'no-reply@laravelsecurity.com'),
    'email_from_name' => env('MAIL_FROM_NAME', 'no-reply@laravelsecurity.com'),
    '2fa_validity_days' => env('SECURITY_2FA_VALIDITY_DAYS',30),
    'otp_length' => env('SECURITY_OTP_LENGTH',6),
    'superadmin_email_to' => env('SECURITY_SUPERADMIN_EMAIL_TO','superadmin@laravelsecurity.com'),
    'platform_name' => env('SECURITY_PLATFORM_NAME','Laravel Security')
];