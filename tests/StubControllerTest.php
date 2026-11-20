<?php

namespace Prajwol\LaravelSecurityFeatures\Tests;

use Illuminate\Support\Facades\Mail;
use Prajwol\LaravelSecurityFeatures\Mail\VerificationCode;
use Prajwol\LaravelSecurityFeatures\Models\UserDevice;

/**
 * Exercises the publishable controller stub through the package's API routes.
 */
class StubControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        require_once __DIR__ . '/../stubs/LaravelSecurityFeatureController.php';
        Mail::fake();
    }

    public function test_resend_otp_endpoint()
    {
        $user = $this->makeUser();

        $this->postJson('/api/laravel-security-feature/reSendOtp', ['email' => $user->email])
            ->assertStatus(200)
            ->assertJson(['status' => true, 'needs_verify' => true, 'email' => $user->email]);

        Mail::assertSent(VerificationCode::class, 1);
    }

    public function test_resend_otp_endpoint_validates_email()
    {
        $this->postJson('/api/laravel-security-feature/reSendOtp', ['email' => 'bad'])
            ->assertStatus(422);
    }

    public function test_verify_endpoint_consumes_code_and_registers_device()
    {
        $user = $this->makeUser();
        $this->security->handlePostLogin($this->makeRequest(['email' => $user->email]));
        $code = $this->activeOtpFor($user)->otp_code;

        $this->withHeaders(['User-Agent' => self::USER_AGENT])
            ->postJson('/api/laravel-security-feature/verify', [
                'email' => $user->email,
                'code' => $code,
                'remember_option' => 'remember',
            ])
            ->assertStatus(200);

        $this->assertNull($this->activeOtpFor($user));
        $this->assertSame(1, UserDevice::where('user_id', $user->id)->count());
    }
}
