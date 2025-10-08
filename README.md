# Laravel Security Features Package

## Installation

1. `composer require prajwol/laravel-security-features`
2. Add to config/app.php providers: Prajwol\SecurityFeatures\LaravelSecurityFeaturesServiceProvider::class
3. `php artisan vendor:publish --tag=config`
4. `php artisan migrate`
5. In .env, set SECURITY_2FA=true, etc.
6. In your LoginController, use the HandlesSecurityFeatures trait and call handlePostLogin after Auth::attempt.
7. Add a /verify endpoint using verifyCode method from the trait.

Supports Laravel 6.2, PHP 7.2-7.4. Assumes users table exists. For Passport API login.