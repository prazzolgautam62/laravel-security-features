

# Laravel Security Features Package

This package enhances Laravel applications with advanced security features including email verification, two-factor authentication (2FA), login logging, and device management. It is designed for Laravel 6.2 and supports PHP versions 7.2 to 7.4. The package assumes a `users` table exists in your database and is tailored for use with Laravel Passport for API authentication.

## Features
- **Email Verification**: Ensures users verify their email addresses before accessing the application.
- **Two-Factor Authentication (2FA)**: Adds an extra layer of security by requiring a verification code sent to the user's email.
- **Login Logs**: Tracks user login activities (if enabled).
- **Device Management**: Manages and verifies devices used for login, preventing unauthorized access from unrecognized devices.

## Requirements
- PHP 7.2, 7.3, or 7.4
- A `users` table in your database

## Installation

Follow these steps to install and configure the package in your Laravel application:

1. **Install the Package**
   Run the following command to install the package via Composer:
   ```bash
   composer require prajwol/laravel-security-features
   ```

2. **Register the Service Provider**
   Add the service provider to the `providers` array in `config/app.php`:
   ```php
   Prajwol\LaravelSecurityFeatures\LaravelSecurityFeaturesServiceProvider::class,
   ```

3. **Publish the Configuration**
   Publish the package configuration file to customize settings:
   ```bash
   php artisan vendor:publish --tag=config
   ```
   This will create a `config/security-features.php` file in your Laravel project.

4. **Publish the view**
   Publish the package view file:
   ```bash
   php artisan vendor:publish --tag=views
   ```
   This will create a required view file in your Laravel project.

5. **Run Migrations**
   Run the migrations to create the necessary tables (e.g., for device management):
   ```bash
   php artisan migrate
   ```

6. **Configure the .env File**
   Add the following environment variables to your `.env` file to enable or disable features and customize settings:
   ```env
   # Enable/disable email verification
   SECURITY_EMAIL_VERIFY=false

   # Enable/disable two-factor authentication
   SECURITY_2FA=false

   # Enable/disable login logging
   SECURITY_LOGIN_LOGS=false

   # Enable/disable device management
   SECURITY_DEVICE_MANAGEMENT=false

   # Email address for sending verification codes
   MAIL_FROM_ADDRESS=no-reply@yourapp.com

   # Number of days a 2FA verification remains valid
   SECURITY_2FA_VALIDITY_DAYS=30
   ```
   - Set `SECURITY_EMAIL_VERIFY=true` to require email verification for users.
   - Set `SECURITY_2FA=true` to enable 2FA for users.
   - Set `SECURITY_LOGIN_LOGS=true` to log user login activities.
   - Set `SECURITY_DEVICE_MANAGEMENT=true` to track and verify user devices.
   - Adjust `SECURITY_2FA_VALIDITY_DAYS` to control how long a 2FA verification remains valid (default is 30 days).

7. **Integrate with LoginController**
   In your `LoginController`, use the `HandlesSecurityFeatures` trait and call the `handlePostLogin` method after a successful `Auth::attempt`. Example:
   ```php
   use Prajwol\LaravelSecurityFeatures\Traits\HandlesSecurityFeatures;
   use Illuminate\Http\Request;
   use Illuminate\Support\Facades\Auth;

   class LoginController extends Controller
   {
       use HandlesSecurityFeatures;

       public function login(Request $request)
       {
           $credentials = $request->only('email', 'password');

           if (Auth::attempt($credentials)) {
               $response = $this->handlePostLogin($request);

               if ($response) {
                   return $response; // Returns JSON if verification is needed
               }

               // Proceed with token issuance (e.g., for Passport)
               $token = Auth::user()->createToken('API Token')->accessToken;
               return response()->json(['token' => $token], 200);
           }

           return response()->json(['message' => 'Invalid credentials'], 401);
       }
   }
   ```

8. **Set Up Verification Endpoint**
   Create a route and controller method to handle verification code submission. Use the `verifyCode` method from the `HandlesSecurityFeatures` trait. Example:
   ```php
   use Prajwol\LaravelSecurityFeatures\Traits\HandlesSecurityFeatures;
   use Illuminate\Http\Request;

   class VerifyController extends Controller
   {
       use HandlesSecurityFeatures;

       public function verify(Request $request)
       {
           $user = $this->verifyCode($request);

           if ($user instanceof \Illuminate\Http\JsonResponse) {
               return $user; // Returns error response if verification fails
           }

           // Log the user in and issue a token
           Auth::login($user);
           $token = $user->createToken('API Token')->accessToken;
           return response()->json(['token' => $token], 200);
       }
   }
   ```
   Add the route in `routes/api.php`:
   ```php
   Route::post('/verify', [VerifyController::class, 'verify']);
   ```

## Configuration Details (`config/security-features.php`)

The package provides a configuration file to customize its behavior. Below is an example configuration with explanations:

```php
<?php

return [
    // Enable/disable email verification for users
    'enable_email_verify' => env('SECURITY_EMAIL_VERIFY', false),

    // Enable/disable two-factor authentication for users
    'enable_2fa' => env('SECURITY_2FA', false),

    // Enable/disable login logging
    'enable_login_logs' => env('SECURITY_LOGIN_LOGS', false),

    // Enable/disable device management
    'enable_device_management' => env('SECURITY_DEVICE_MANAGEMENT', false),

    // Specify the User model (default: App\Models\User)
    'user_model' => 'App\\Models\\User',

    // Verification code expiry time in minutes
    'verification_code_expiry' => 10,

    // Device identifier method ('user_agent' or 'user_agent_ip')
    'device_identifier' => 'user_agent',

    // Email address for sending verification codes
    'email_from' => env('MAIL_FROM_ADDRESS', 'no-reply@yourapp.com'),

    // Number of days a 2FA verification remains valid
    '2fa_validity_days' => env('SECURITY_2FA_VALIDITY_DAYS', 30),
];
```

- **`enable_email_verify`**: When true, users must verify their email before logging in.
- **`enable_2fa`**: When true, users with 2FA enabled receive a verification code during login.
- **`enable_login_logs`**: When true, logs user login attempts.
- **`enable_device_management`**: When true, tracks devices and requires verification for new devices.
- **`user_model`**: Specifies the User model if it’s not in the default location (`App\Models\User`).
- **`verification_code_expiry`**: Sets how long (in minutes) a verification code is valid.
- **`device_identifier`**: Determines how devices are identified:
  - `'user_agent'`: Uses the browser’s user agent string.
  - `'user_agent_ip'`: Combines user agent and IP address (note: IP addresses may change frequently).
- **`email_from`**: The email address used to send verification codes.
- **`2fa_validity_days`**: Duration (in days) that a 2FA verification remains valid for a device.

## How It Works

1. **Post-Login Handling**:
   - After a successful `Auth::attempt`, the `handlePostLogin` method checks if email verification, 2FA, or device management is required.
   - If verification is needed, a 6-digit code is sent to the user’s email, and the user is logged out temporarily.
   - A JSON response is returned with a message indicating that verification is required.

2. **Verification Process**:
   - The user submits the verification code to the `/verify` endpoint.
   - The `verifyCode` method validates the code and email, updates the user’s email verification status (if applicable), and manages device records.
   - On successful verification, the user is logged in, and a token is issued.

3. **Device Management**:
   - If enabled, the package tracks devices using a hash of the user agent (or user agent + IP).
   - New or unverified devices trigger a verification code email.
   - Verified devices are stored in the `user_devices` table with details like user agent, IP address, and last verification time.

4. **2FA Expiry**:
   - If 2FA is enabled, verified devices are re-verified after the `2fa_validity_days` period expires.

## Example Workflow
1. A user attempts to log in via the `/login` endpoint.
2. If email verification is enabled and the user’s email is unverified, or if 2FA/device management is enabled and the device is new or 2FA has expired, a verification code is emailed.
3. The user submits the code to the `/verify` endpoint.
4. Upon successful verification, the user is logged in, and a Passport token is issued.

## Notes
- Ensure your Laravel application is configured to send emails (e.g., via `MAIL_*` settings in `.env`).
- The package uses Laravel’s `Cache` and `Mail` facades for temporary code storage and email sending.
- If using `device_identifier=user_agent_ip`, be aware that IP changes (e.g., due to mobile networks) may trigger frequent re-verifications.
- The package is designed for API authentication with Laravel Passport but can be adapted for other authentication systems.

## Support
For issues or feature requests, please open an issue on the [package’s GitHub repository](https://github.com/prazzolgautam62/laravel-security-features) or contact the maintainer at [prazzolgautam@gmail.com].

