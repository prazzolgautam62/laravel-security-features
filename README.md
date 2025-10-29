# Laravel Security Features Package

This package enhances Laravel applications with advanced security features including email verification, two-factor authentication (2FA), login logging, and device management. It is designed for Laravel 6.2 and supports PHP versions 7.2 to 7.4. The package assumes a `users` table exists in your database.

## Features
- **Email Verification Middleware**: Ensures users verify their email addresses before accessing specific protected routes.
- **Two-Factor Authentication (2FA)**: Adds an extra layer of security by requiring a verification code sent to the user's email. (if enabled in config and by user both.)
- **Login Logs**: Tracks user login activities (if enabled).
- **Device Management**: Manages and verifies devices used for login, preventing unauthorized access from unrecognized devices.

## Requirements
- PHP 7.2, 7.3, or 7.4
- A `users` table in your database with an `email_verified_at` column

## Installation

Follow these steps to install and configure the package in your Laravel application:

1. **Install the Package**
   ```bash
   composer require prajwol/laravel-security-features
   ```

2. **Publish Config, Views, Routes, and Controller**
   ```bash
   php artisan vendor:publish --tag=config
   php artisan vendor:publish --tag=views
   php artisan vendor:publish --tag=routes
   php artisan vendor:publish --tag=controller (only in dev on first install)
   ```

3. **Run Migrations**
   ```bash
   php artisan migrate
   ```

4. **.env Configuration**
   ```env
   SECURITY_EMAIL_VERIFY=false
   SECURITY_2FA=false
   SECURITY_LOGIN_LOGS=false
   SECURITY_DEVICE_MANAGEMENT=false
   VERIFICATION_CODE_EXPIRY=2
   SECURITY_2FA_VALIDITY_DAYS=30
   SECURITY_OTP_LENGTH=6
   SECURITY_SUPERADMIN_EMAIL_TO=no-reply@laravelsecurity.com
   SECURITY_PLATFORM_NAME='Laravel Security'
   ```

6. **Using the Middleware**
   A middleware named `laravel-security-feature.email-verified` is automatically registered.  
   You can use it to protect routes that require verified emails:

7. **LoginController Integration**
   Use the provided trait in your controller:
   ```php
   use Prajwol\LaravelSecurityFeatures\Traits\HandlesSecurityFeatures;

   class LoginController extends Controller
   {
       use HandlesSecurityFeatures;

       public function login(Request $request)
       {
           if (Auth::attempt($request->only('email', 'password'))) {
               $response = $this->handlePostLogin($request);
               if ($response) return $response;

               $token = Auth::user()->createToken('access_token')->accessToken;
               return response()->json(['token' => $token], 200);
           }

           return response()->json(['message' => 'Invalid credentials'], 401);
       }
   }
   ```

8. **Available API Routes**
   ```php
   Route::post('/laravel-security-feature/verify', [LaravelSecurityFeatureController::class, 'verify']);
   Route::post('/laravel-security-feature/changeEmailAndSendOtp/{user_id}', [LaravelSecurityFeatureController::class, 'changeEmailAndSendOtp']);
   Route::post('/laravel-security-feature/verifyEmailOnly/{user_id}', [LaravelSecurityFeatureController::class, 'verifyEmailOnlyForUser']);
   ```

## Configuration File (`config/security-features.php`)

```php
return [
    'enable_email_verify' => env('SECURITY_EMAIL_VERIFY', false),
    'enable_2fa' => env('SECURITY_2FA', false),
    'enable_login_logs' => env('SECURITY_LOGIN_LOGS', false),
    'enable_device_management' => env('SECURITY_DEVICE_MANAGEMENT', false),
    'user_model' => 'App\\Models\\User',
    'verification_code_expiry' => 10,
    'device_identifier' => 'user_agent',
    'email_from' => env('MAIL_FROM_ADDRESS', 'no-reply@laravelsecurity.com'),
    '2fa_validity_days' => env('SECURITY_2FA_VALIDITY_DAYS', 30),
    'otp_length' => env('SECURITY_OTP_LENGTH',6),
    'superadmin_email_to' => env('SECURITY_SUPERADMIN_EMAIL_TO', 'no-reply@laravelsecurity.com'),
];
```

## How It Works

1. **Post Login Checks**
   - Validates 2FA, device, and email verification logic after successful login.

2. **Verification Flow**
   - Users receive a 6-digit verification code.
   - The `/verify` endpoint confirms identity and updates records.

3. **Device Management**
   - Identifies devices using `user_agent` or `user_agent_ip`.
   - Creates/updates records in `user_devices`.

4. **Email Verification Middleware**
   - Protects sensitive routes.
   - Automatically denies unverified users.

## Support
For issues or feature requests, visit [GitHub](https://github.com/prazzolgautam62/laravel-security-features)  
or email **prazzolgautam@gmail.com**.
