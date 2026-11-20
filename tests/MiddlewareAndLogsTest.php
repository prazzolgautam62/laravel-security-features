<?php

namespace Prajwol\LaravelSecurityFeatures\Tests;

use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Prajwol\LaravelSecurityFeatures\Mail\VerificationCode;
use Prajwol\LaravelSecurityFeatures\Models\LoginLog;

class MiddlewareAndLogsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/protected', function () {
            return 'ok';
        })->middleware('laravel-security-feature.email-verified');
    }

    public function test_unverified_user_is_blocked()
    {
        $user = $this->makeUser(['email_verified_at' => null]);

        $this->actingAs($user)->getJson('/protected')
            ->assertStatus(200)
            ->assertJson(['status' => false, 'needs_email_verification' => true]);
    }

    public function test_verified_user_passes()
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->get('/protected');

        $response->assertStatus(200);
        $this->assertSame('ok', $response->getContent());
    }

    public function test_guest_passes_through()
    {
        $this->assertSame('ok', $this->get('/protected')->getContent());
    }

    public function test_unverified_user_passes_when_feature_disabled()
    {
        config(['security-features.enable_email_verify' => false]);
        $user = $this->makeUser(['email_verified_at' => null]);

        $this->assertSame('ok', $this->actingAs($user)->get('/protected')->getContent());
    }

    public function test_login_event_writes_login_log()
    {
        $user = $this->makeUser();

        event(new Login('web', $user, false));

        $log = LoginLog::first();
        $this->assertNotNull($log);
        $this->assertEquals($user->id, $log->user_id);
        $this->assertNotNull($log->logged_in_at);
        $this->assertEquals($user->id, $log->user->id);
    }

    public function test_login_log_respects_runtime_config()
    {
        config(['security-features.enable_login_logs' => false]);
        $user = $this->makeUser();

        event(new Login('web', $user, false));

        $this->assertSame(0, LoginLog::count());
    }

    public function test_post_login_records_login_log()
    {
        Mail::fake();
        $user = $this->makeUser(['enable_2fa' => 0]);

        $this->security->handlePostLogin($this->makeRequest(['email' => $user->email]));

        $this->assertSame(1, LoginLog::where('user_id', $user->id)->count());
    }

    public function test_verification_mail_renders()
    {
        $mail = new VerificationCode('482913', 2, 'Jane Doe', 'One-Time Password (OTP)');

        $html = $mail->render();

        $this->assertStringContainsString('482913', $html);
        $this->assertStringContainsString('Hi, Jane Doe', $html);
        $this->assertStringContainsString('(2 minutes)', $html);
        $this->assertSame('Test Platform: One-Time Password (OTP)', $mail->subject);
        $this->assertTrue($mail->hasFrom('noreply@example.com'));
    }

    public function test_verification_mail_can_be_sent_through_mailer()
    {
        $user = $this->makeUser();

        Mail::to($user->email)->send(new VerificationCode('482913', 2, 'Jane Doe', 'One-Time Password (OTP)'));

        // Reaching here means the mailable built and the array transport accepted it.
        $this->assertTrue(true);
    }
}
