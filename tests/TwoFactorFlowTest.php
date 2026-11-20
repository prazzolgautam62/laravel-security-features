<?php

namespace Prajwol\LaravelSecurityFeatures\Tests;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Prajwol\LaravelSecurityFeatures\Mail\VerificationCode;
use Prajwol\LaravelSecurityFeatures\Models\OtpRequest;
use Prajwol\LaravelSecurityFeatures\Models\UserDevice;

class TwoFactorFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_unknown_user_gets_invalid_credentials_response()
    {
        $response = $this->security->handlePostLogin($this->makeRequest(['email' => 'nobody@example.com']));

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(['status' => false, 'message' => 'Invalid credentials.'], $response->getData(true));
    }

    public function test_login_proceeds_when_user_has_2fa_disabled()
    {
        $user = $this->makeUser(['enable_2fa' => 0]);

        $this->assertNull($this->security->handlePostLogin($this->makeRequest(['email' => $user->email])));
        $this->assertSame(0, OtpRequest::count());
        Mail::assertNothingSent();
    }

    public function test_login_proceeds_when_2fa_feature_is_disabled()
    {
        config(['security-features.enable_2fa' => false, 'security-features.enable_device_management' => false]);
        $user = $this->makeUser();

        $this->assertNull($this->security->handlePostLogin($this->makeRequest(['email' => $user->email])));
        Mail::assertNothingSent();
    }

    public function test_new_device_triggers_otp_email()
    {
        $user = $this->makeUser();

        $response = $this->security->handlePostLogin($this->makeRequest(['email' => $user->email]));

        $this->assertInstanceOf(JsonResponse::class, $response);
        $data = $response->getData(true);
        $this->assertFalse($data['status']);
        $this->assertTrue($data['needs_verify']);
        $this->assertSame($user->email, $data['email']);

        $otp = $this->activeOtpFor($user);
        $this->assertNotNull($otp);
        $this->assertSame(1, preg_match('/^\d{6}$/', $otp->otp_code));

        Mail::assertSent(VerificationCode::class, function ($mail) use ($user, $otp) {
            return $mail->hasTo($user->email)
                && $mail->code === $otp->otp_code
                && $mail->username === 'Jane Doe';
        });

        $pending = Cache::get("pending_device_{$user->id}");
        $this->assertSame(md5(self::USER_AGENT), $pending['hash']);
        $this->assertSame(self::IP, $pending['ip_address']);
    }

    public function test_active_otp_is_not_resent_on_repeat_login()
    {
        $user = $this->makeUser();

        $this->security->handlePostLogin($this->makeRequest(['email' => $user->email]));
        $response = $this->security->handlePostLogin($this->makeRequest(['email' => $user->email]));

        $this->assertSame('OTP has already been sent. Please check your email.', $response->getData(true)['message']);
        $this->assertSame(1, OtpRequest::count());
        Mail::assertSent(VerificationCode::class, 1);
    }

    public function test_superadmin_otp_goes_to_configured_address()
    {
        $user = $this->makeUser(['role_name' => 'superadmin']);

        $this->security->handlePostLogin($this->makeRequest(['email' => $user->email]));

        Mail::assertSent(VerificationCode::class, function ($mail) {
            return $mail->hasTo('root@example.com') && $mail->username === 'Veda Billing Super Admin';
        });
    }

    public function test_verify_code_with_remember_creates_device_and_cookie()
    {
        $user = $this->makeUser();
        $this->security->handlePostLogin($this->makeRequest(['email' => $user->email]));
        $code = $this->activeOtpFor($user)->otp_code;

        list($verifiedUser, $cookie) = $this->security->verifyCode($this->makeRequest([
            'email' => $user->email,
            'code' => $code,
            'remember_option' => 'remember',
        ]));

        $this->assertSame($user->id, $verifiedUser->id);
        $this->assertNotNull($cookie);
        $this->assertSame('device_token', $cookie->getName());
        $this->assertSame(40, strlen($cookie->getValue()));
        $this->assertTrue($cookie->isHttpOnly());

        $device = UserDevice::where('user_id', $user->id)->first();
        $this->assertNotNull($device);
        $this->assertSame(md5(self::USER_AGENT), $device->device_hash);
        $this->assertSame($cookie->getValue(), $device->device_token);
        $this->assertEquals(1, $device->remember_device);
        $this->assertNotNull($device->last_verified_at);

        // OTP is consumed (soft deleted)
        $this->assertNull($this->activeOtpFor($user));
        $this->assertSame(1, OtpRequest::withTrashed()->count());
        $this->assertNull(Cache::get("pending_device_{$user->id}"));
    }

    public function test_verify_code_with_ask_every_time_sets_no_cookie()
    {
        $user = $this->makeUser();
        $this->security->handlePostLogin($this->makeRequest(['email' => $user->email]));
        $code = $this->activeOtpFor($user)->otp_code;

        list(, $cookie) = $this->security->verifyCode($this->makeRequest(['email' => $user->email, 'code' => $code]));

        $this->assertNull($cookie);
        $this->assertEquals(0, UserDevice::where('user_id', $user->id)->value('remember_device'));
    }

    public function test_remembered_device_skips_otp_on_next_login()
    {
        $user = $this->makeUser();
        $this->security->handlePostLogin($this->makeRequest(['email' => $user->email]));
        $code = $this->activeOtpFor($user)->otp_code;
        list(, $cookie) = $this->security->verifyCode($this->makeRequest([
            'email' => $user->email,
            'code' => $code,
            'remember_option' => 'remember',
        ]));

        $response = $this->security->handlePostLogin(
            $this->makeRequest(['email' => $user->email], ['device_token' => $cookie->getValue()])
        );

        $this->assertNull($response);
        Mail::assertSent(VerificationCode::class, 1);
    }

    public function test_unremembered_device_requires_otp_again()
    {
        $user = $this->makeUser();
        $this->makeDevice($user, ['remember_device' => 0, 'last_verified_at' => now()->subDay()]);

        $response = $this->security->handlePostLogin(
            $this->makeRequest(['email' => $user->email], ['device_token' => 'known-token'])
        );

        $this->assertTrue($response->getData(true)['needs_verify']);
    }

    public function test_2fa_expires_after_validity_days()
    {
        $user = $this->makeUser();
        $this->makeDevice($user, ['last_verified_at' => now()->subDays(31)]);

        $response = $this->security->handlePostLogin(
            $this->makeRequest(['email' => $user->email], ['device_token' => 'known-token'])
        );

        $this->assertNotNull($response);
        $this->assertTrue($response->getData(true)['needs_verify']);
    }

    public function test_2fa_is_valid_until_validity_days_are_fully_exceeded()
    {
        // 30 days and some hours counts as 30 whole days, which is not > 30 (Carbon 2 and 3 alike).
        $user = $this->makeUser();
        $this->makeDevice($user, ['last_verified_at' => now()->subDays(30)->subHours(5)]);

        $response = $this->security->handlePostLogin(
            $this->makeRequest(['email' => $user->email], ['device_token' => 'known-token'])
        );

        $this->assertNull($response);
    }

    public function test_device_token_belonging_to_another_user_is_not_trusted()
    {
        $owner = $this->makeUser(['email' => 'owner@example.com']);
        $this->makeDevice($owner);
        $user = $this->makeUser();

        $response = $this->security->handlePostLogin(
            $this->makeRequest(['email' => $user->email], ['device_token' => 'known-token'])
        );

        $this->assertTrue($response->getData(true)['needs_verify']);
    }

    public function test_verify_code_rejects_wrong_code()
    {
        $user = $this->makeUser();
        $this->security->handlePostLogin($this->makeRequest(['email' => $user->email]));
        $wrong = $this->activeOtpFor($user)->otp_code === '000000' ? '111111' : '000000';

        $this->expectException(ValidationException::class);
        $this->security->verifyCode($this->makeRequest(['email' => $user->email, 'code' => $wrong]));
    }

    public function test_verify_code_rejects_expired_code()
    {
        $user = $this->makeUser();
        OtpRequest::create(['user_id' => $user->id, 'otp_code' => '123456', 'expiry_time' => now()->subMinute()]);

        $this->expectException(ValidationException::class);
        $this->security->verifyCode($this->makeRequest(['email' => $user->email, 'code' => '123456']));
    }

    public function test_verify_code_rejects_unknown_email()
    {
        $this->expectException(ValidationException::class);
        $this->security->verifyCode($this->makeRequest(['email' => 'nobody@example.com', 'code' => '123456']));
    }

    public function test_verify_code_validates_input()
    {
        $this->expectException(ValidationException::class);
        $this->security->verifyCode($this->makeRequest(['email' => 'not-an-email', 'code' => '12']));
    }

    public function test_device_hash_respects_identifier_config()
    {
        $request = $this->makeRequest();
        $this->assertSame(md5(self::USER_AGENT), $this->security->deviceHash($request));

        config(['security-features.device_identifier' => 'user_agent_ip']);
        $this->assertSame(md5(self::USER_AGENT . self::IP), $this->security->deviceHash($request));
    }

    public function test_generated_codes_are_six_digits()
    {
        for ($i = 0; $i < 200; $i++) {
            $this->assertSame(1, preg_match('/^\d{6}$/', $this->security->code()));
        }
    }

    protected function makeDevice($user, array $attributes = [])
    {
        return UserDevice::create(array_merge([
            'user_id' => $user->id,
            'device_hash' => md5(self::USER_AGENT),
            'user_agent' => self::USER_AGENT,
            'ip_address' => self::IP,
            'device_info' => self::USER_AGENT,
            'device_token' => 'known-token',
            'remember_device' => 1,
            'last_verified_at' => now(),
        ], $attributes));
    }
}
