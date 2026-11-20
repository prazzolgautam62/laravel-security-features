<?php

namespace Prajwol\LaravelSecurityFeatures\Tests;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Prajwol\LaravelSecurityFeatures\Http\Middleware\EmailVerifiedMiddleware;
use Prajwol\LaravelSecurityFeatures\Models\UserDevice;
use Prajwol\LaravelSecurityFeatures\SecurityFeaturesServiceProvider;

class ServiceProviderTest extends TestCase
{
    public function test_package_config_defaults_are_merged()
    {
        $this->assertSame(6, config('security-features.otp_length'));
        $this->assertSame(30, config('security-features.2fa_validity_days'));
        $this->assertSame('user_agent', config('security-features.device_identifier'));
    }

    public function test_middleware_alias_is_registered()
    {
        $middleware = $this->app['router']->getMiddleware();

        $this->assertArrayHasKey('laravel-security-feature.email-verified', $middleware);
        $this->assertSame(EmailVerifiedMiddleware::class, $middleware['laravel-security-feature.email-verified']);
    }

    public function test_email_view_is_registered()
    {
        $this->assertTrue(View::exists('security-features::emails.verification_code'));
    }

    public function test_api_routes_are_registered_with_api_prefix()
    {
        $routes = Route::getRoutes();
        $routes->refreshNameLookups();

        $expected = [
            'laravel-security-feature.verify' => 'api/laravel-security-feature/verify',
            'laravel-security-feature.changeEmailAndSendOtp' => 'api/laravel-security-feature/changeEmailAndSendOtp/{user_id}',
            'laravel-security-feature.verifyEmailOnly' => 'api/laravel-security-feature/verifyEmailOnly/{user_id}',
            'laravel-security-feature.resendOtpForEmail' => 'api/laravel-security-feature/reSendOtp',
        ];

        foreach ($expected as $name => $uri) {
            $route = $routes->getByName($name);
            $this->assertNotNull($route, "Route [$name] is not registered.");
            $this->assertSame($uri, $route->uri());
            $this->assertContains('POST', $route->methods());
            $this->assertContains('api', $route->middleware());
        }
    }

    public function test_publishable_groups_are_registered()
    {
        foreach (['config', 'views', 'routes', 'controller'] as $group) {
            $paths = ServiceProvider::pathsToPublish(SecurityFeaturesServiceProvider::class, $group);
            $this->assertNotEmpty($paths, "Publish group [$group] is empty.");
            foreach (array_keys($paths) as $source) {
                $this->assertFileExists($source);
            }
        }
    }

    public function test_migrations_create_expected_schema()
    {
        $this->assertTrue(Schema::hasTable('login_logs'));
        $this->assertTrue(Schema::hasTable('user_devices'));
        $this->assertTrue(Schema::hasTable('otp_requests'));
        $this->assertTrue(Schema::hasColumn('users', 'enable_2fa'));
        $this->assertTrue(Schema::hasColumns('user_devices', [
            'user_id', 'device_hash', 'user_agent', 'ip_address', 'device_info',
            'device_token', 'remember_device', 'last_verified_at',
        ]));
        $this->assertTrue(Schema::hasColumns('otp_requests', ['user_id', 'otp_code', 'expiry_time', 'deleted_at']));
    }

    public function test_device_token_is_no_longer_unique()
    {
        $user = $this->makeUser();
        $other = $this->makeUser(['email' => 'other@example.com']);

        foreach ([$user, $other] as $owner) {
            UserDevice::create([
                'user_id' => $owner->id,
                'device_hash' => md5(self::USER_AGENT),
                'user_agent' => self::USER_AGENT,
                'ip_address' => self::IP,
                'device_info' => self::USER_AGENT,
                'device_token' => 'shared-token',
            ]);
        }

        $this->assertSame(2, UserDevice::where('device_token', 'shared-token')->count());
    }

    public function test_migrations_can_be_rolled_back()
    {
        // Laravel < 11 needs doctrine/dbal to drop columns on SQLite.
        if (version_compare($this->app->version(), '11.0.0', '<') && !class_exists('Doctrine\DBAL\Driver')) {
            $this->markTestSkipped('Dropping SQLite columns requires doctrine/dbal before Laravel 11.');
        }

        $this->app->make('Illuminate\Contracts\Console\Kernel')->call('migrate:rollback');

        $this->assertFalse(Schema::hasTable('login_logs'));
        $this->assertFalse(Schema::hasTable('user_devices'));
        $this->assertFalse(Schema::hasTable('otp_requests'));
        $this->assertFalse(Schema::hasColumn('users', 'enable_2fa'));
    }
}
