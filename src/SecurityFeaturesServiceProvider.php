<?php

namespace Prajwol\LaravelSecurityFeatures;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Prajwol\LaravelSecurityFeatures\Listeners\LogSuccessfulLogin;
use Prajwol\LaravelSecurityFeatures\Http\Middleware\EmailVerifiedMiddleware;

class SecurityFeaturesServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../config/security-features.php' => config_path('security-features.php'),
        ], 'config');

        // Publish migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Publish email view
        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/security-features'),
        ], 'views');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'security-features');

         // Publish routes
        $this->publishes([
            __DIR__ . '/../routes/laravel-security-feature.php' => base_path('routes/laravel-security-feature.php'),
        ], 'routes');

         // Load API routes automatically (with middleware)
        Route::middleware('api')
            ->prefix('api')
            ->group(__DIR__ . '/../routes/laravel-security-feature.php');

        $this->publishes([
            __DIR__ . '/../stubs/LaravelSecurityFeatureController.php' => app_path('Http/Controllers/Auth/LaravelSecurityFeatureController.php'),
        ], 'controller');

        $this->app['router']->aliasMiddleware('laravel-security-feature.email-verified', EmailVerifiedMiddleware::class);

        // Register event listener for login logs
        if (config('security-features.enable_login_logs')) {
            Event::listen('Illuminate\Auth\Events\Login', LogSuccessfulLogin::class);
        }
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/security-features.php', 'security-features');
    }
}