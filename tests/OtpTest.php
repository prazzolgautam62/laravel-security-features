<?php

namespace Prajwol\LaravelSecurityFeatures\Tests;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Prajwol\LaravelSecurityFeatures\Mail\VerificationCode;
use Prajwol\LaravelSecurityFeatures\Models\OtpRequest;

class OtpTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_generate_and_send_otp_sends_once_while_active()
    {
        $user = $this->makeUser();

        $first = $this->security->generateAndSendOtp($user->id, $user->email, $user->name);
        $second = $this->security->generateAndSendOtp($user->id, $user->email, $user->name);
        $changed = $this->security->generateAndSendOtp($user->id, $user->email, $user->name, true);

        $this->assertTrue($first['status']);
        $this->assertSame('OTP sent to your email. Please verify to complete login.', $first['message']);
        $this->assertSame('An OTP has already been sent. Please check your email.', $second['message']);
        $this->assertSame('Please wait for some to send OTP.', $changed['message']);
        Mail::assertSent(VerificationCode::class, 1);
    }

    public function test_generate_and_send_otp_replaces_expired_otps()
    {
        $user = $this->makeUser();
        OtpRequest::create(['user_id' => $user->id, 'otp_code' => '123456', 'expiry_time' => now()->subMinute()]);

        $this->security->generateAndSendOtp($user->id, $user->email, $user->name);

        $this->assertSame(1, OtpRequest::where('user_id', $user->id)->count());
        $this->assertNotNull($this->activeOtpFor($user));
        Mail::assertSent(VerificationCode::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email) && Str::endsWith($mail->subject, 'Account Verification');
        });
    }

    public function test_resend_otp_for_unknown_email()
    {
        $result = $this->security->resendOtp($this->makeRequest(['email' => 'nobody@example.com']));

        $this->assertFalse($result['status']);
        $this->assertSame('Email not found', $result['message']);
        Mail::assertNothingSent();
    }

    public function test_resend_otp_waits_while_otp_active()
    {
        $user = $this->makeUser();
        $this->security->generateAndSendOtp($user->id, $user->email, $user->name);

        $result = $this->security->resendOtp($this->makeRequest(['email' => $user->email]));

        $this->assertFalse($result['status']);
        $this->assertSame('Please wait for some time to resend OTP.', $result['message']);
        Mail::assertSent(VerificationCode::class, 1);
    }

    public function test_resend_otp_sends_new_code_after_expiry()
    {
        $user = $this->makeUser();
        OtpRequest::create(['user_id' => $user->id, 'otp_code' => '123456', 'expiry_time' => now()->subMinute()]);

        $result = $this->security->resendOtp($this->makeRequest(['email' => $user->email]));

        $this->assertTrue($result['status']);
        $this->assertSame($user->email, $result['email']);
        $this->assertNotNull($this->activeOtpFor($user));
        Mail::assertSent(VerificationCode::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email) && Str::endsWith($mail->subject, 'One-Time Password (OTP)');
        });
    }

    public function test_resend_otp_for_superadmin_uses_configured_address()
    {
        $user = $this->makeUser(['role_name' => 'superadmin']);

        $result = $this->security->resendOtp($this->makeRequest(['email' => $user->email]));

        $this->assertSame('root@example.com', $result['email']);
        Mail::assertSent(VerificationCode::class, function ($mail) {
            return $mail->hasTo('root@example.com');
        });
    }

    public function test_resend_otp_validates_email()
    {
        $this->expectException(ValidationException::class);
        $this->security->resendOtp($this->makeRequest(['email' => 'not-an-email']));
    }

    public function test_verify_email_only_marks_email_as_verified()
    {
        $user = $this->makeUser(['email_verified_at' => null]);
        $this->security->generateAndSendOtp($user->id, $user->email, $user->name);
        $code = $this->activeOtpFor($user)->otp_code;

        $verified = $this->security->verifyEmailOnly($this->makeRequest(['email' => $user->email, 'code' => $code]));

        $this->assertSame($user->id, $verified->id);
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertNull($this->activeOtpFor($user));
    }

    public function test_verify_email_only_leaves_email_untouched_when_feature_disabled()
    {
        config(['security-features.enable_email_verify' => false]);
        $user = $this->makeUser(['email_verified_at' => null]);
        $this->security->generateAndSendOtp($user->id, $user->email, $user->name);
        $code = $this->activeOtpFor($user)->otp_code;

        $this->security->verifyEmailOnly($this->makeRequest(['email' => $user->email, 'code' => $code]));

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_verify_email_only_rejects_wrong_code()
    {
        $user = $this->makeUser(['email_verified_at' => null]);
        OtpRequest::create(['user_id' => $user->id, 'otp_code' => '123456', 'expiry_time' => now()->addMinutes(2)]);

        try {
            $this->security->verifyEmailOnly($this->makeRequest(['email' => $user->email, 'code' => '654321']));
            $this->fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('code', $e->errors());
        }

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_revoke_otps_removes_all_codes_for_user()
    {
        $user = $this->makeUser();
        $other = $this->makeUser(['email' => 'other@example.com']);
        $this->security->generateAndSendOtp($user->id, $user->email);
        $this->security->generateAndSendOtp($other->id, $other->email);

        $this->security->revokeOtps($user->id);

        $this->assertSame(0, OtpRequest::where('user_id', $user->id)->count());
        $this->assertSame(1, OtpRequest::where('user_id', $other->id)->count());
    }
}
