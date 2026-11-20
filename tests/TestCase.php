<?php

namespace Prajwol\LaravelSecurityFeatures\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Prajwol\LaravelSecurityFeatures\Models\OtpRequest;
use Prajwol\LaravelSecurityFeatures\SecurityFeaturesServiceProvider;
use Prajwol\LaravelSecurityFeatures\Tests\Fixtures\SecurityHarness;
use Prajwol\LaravelSecurityFeatures\Tests\Fixtures\User;

abstract class TestCase extends Orchestra
{
    const USER_AGENT = 'TestAgent/1.0';
    const IP = '10.0.0.1';

    /** @var SecurityHarness */
    protected $security;

    protected function setUp(): void
    {
        parent::setUp();

        // The package expects the host application to already have a users table.
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role_name')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        $this->app->make('Illuminate\Contracts\Console\Kernel')->call('migrate');

        $this->security = new SecurityHarness();
    }

    protected function getPackageProviders($app)
    {
        return [SecurityFeaturesServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        $config = $app['config'];

        $config->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
        $config->set('database.default', 'testing');
        $config->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $config->set('cache.default', 'array');
        $config->set('mail.default', 'array');
        $config->set('mail.driver', 'array');
        $config->set('auth.providers.users.model', User::class);

        $config->set('security-features.user_model', User::class);
        $config->set('security-features.enable_email_verify', true);
        $config->set('security-features.enable_2fa', true);
        $config->set('security-features.enable_login_logs', true);
        $config->set('security-features.enable_device_management', true);
        $config->set('security-features.superadmin_email_to', 'root@example.com');
        $config->set('security-features.platform_name', 'Test Platform');
        $config->set('security-features.email_from', 'noreply@example.com');
    }

    protected function makeUser(array $attributes = [])
    {
        return User::create(array_merge([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'not-a-real-hash',
            'email_verified_at' => now(),
            'enable_2fa' => 1,
        ], $attributes));
    }

    protected function makeRequest(array $data = [], array $cookies = [], $userAgent = self::USER_AGENT)
    {
        return Request::create('/login', 'POST', $data, $cookies, [], [
            'HTTP_USER_AGENT' => $userAgent,
            'REMOTE_ADDR' => self::IP,
        ]);
    }

    protected function activeOtpFor($user)
    {
        return OtpRequest::where('user_id', $user->id)->where('expiry_time', '>=', now())->first();
    }
}
