<?php

namespace Prajwol\LaravelSecurityFeatures\Traits;

use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Prajwol\LaravelSecurityFeatures\Mail\VerificationCode;
use Prajwol\LaravelSecurityFeatures\Models\UserDevice;
use Prajwol\LaravelSecurityFeatures\Models\OtpRequest;

trait HandlesSecurityFeatures
{
    /**
     * To be called after successful Auth::attempt() in your login method.
     * Returns null if no verification needed, or a JSON response if pending.
     */
    public function handlePostLogin(Request $request)
    {
        $userClass = config('security-features.user_model');
        $user = $userClass::where('email', $request->email)->first();
        $needsVerification = false;

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid credentials.',
            ], 200);
        }

        // Check email verification if enabled in config and user hasn't verified
        // if (config('security-features.enable_email_verify') && !$user->email_verified_at) {
        //     $needsVerification = true;
        // }

        if (config('security-features.enable_2fa') || config('security-features.enable_device_management')) {
            $deviceHash = $this->getDeviceHash($request);
            $deviceToken = $request->cookie('device_token');
            $device = null;

            if ($deviceToken) {
                // Check cookie token first
                $device = UserDevice::where('user_id', $user->id)
                    ->where('device_token', $deviceToken)
                    ->first();

                // If cookie matches but browser is different, require 2FA
                if ($device && $device->device_hash !== $deviceHash) {
                    $device = null; // Force 2FA for new browser
                }
            }

            // Fallback: device hash only
            if (!$device) {
                $device = UserDevice::where('user_id', $user->id)
                    ->where('device_hash', $deviceHash)
                    ->first();
            }

            $requires2fa = config('security-features.enable_2fa') && $user->enable_2fa;
            $isNewDevice = config('security-features.enable_device_management') && !$device;
            $is2faExpired = false;

            // Check if 2FA has expired based on validity days
            if ($requires2fa && $device && $device->last_verified_at) {
                $validityDays = config('security-features.2fa_validity_days', 30);
                $is2faExpired = \Carbon\Carbon::parse($device->last_verified_at)->diffInDays(now()) > $validityDays;
            }

            // Handle remember_device logic
            $rememberDevice = $device && $device->remember_device == 1;

            if ($requires2fa && ($isNewDevice || $is2faExpired || !$device || !$rememberDevice)) {
                $needsVerification = true;
            }

            // Store device info in cache if verification is needed
            if ($needsVerification && config('security-features.enable_device_management')) {
                Cache::put("pending_device_{$user->id}", [
                    'hash' => $deviceHash,
                    'user_agent' => $request->userAgent(),
                    'ip_address' => $request->ip(),
                    'device_info' => $request->header('User-Agent')
                ], now()->addMinutes(config('security-features.verification_code_expiry')));
            }
        }
        if(config('security-features.enable_login_logs'))
            event(new Login('api', $user, false));

        if ($needsVerification) {
            $code = $this->generateVerificationCode();
            $user_email = $user->role_name == 'superadmin' ? config('security-features.superadmin_email_to') : $user->email;
            $username = $user->role_name == 'superadmin' ? 'Veda Billing Super Admin': $user->name;
            // Cache::put("verification_code_{$user->id}", $code, now()->addMinutes(config('security-features.verification_code_expiry')));

            //remove cache implementation and db implementation start
            $existingOtp = OtpRequest::where('user_id', $user->id)
                ->where('expiry_time', '>=', now())
                ->first();

            if ($existingOtp) {
                return response()->json([
                    'status' => false,
                    'needs_verify' => true,
                    'email' => $user->email,
                    'message' => 'A verification code has already been sent. Please check your email.',
                ], 200);
            }

            OtpRequest::create([
                'user_id' => $user->id,
                'otp_code' => $code,
                'expiry_time' => now()->addMinutes(config('security-features.verification_code_expiry'))
            ]);
            //remove cache implementation and db implementation end

            $verification_code_expiry_time = config('security-features.verification_code_expiry');
            Mail::to($user_email)->send(new VerificationCode($code, $verification_code_expiry_time, $username));

            return response()->json([
                'status' => false,
                'needs_verify' => true,
                'email' => $user->email,
                'message' => 'Verification code sent to your email. Please verify to complete login.',
            ], 200);
        }

        return null; // Proceed to issue token
    }

    public function generateAndSendOtp($user_id, $email, $username = '', $email_changed = false)
    {
        $code = $this->generateVerificationCode();
        // Cache::put("verification_code_{$user_id}", $code, now()->addMinutes(config('security-features.verification_code_expiry')));

        //remove cache implementation and db implementation start
        $existingOtp = OtpRequest::where('user_id', $user_id)
            ->where('expiry_time', '>=', now())
            ->first();

        if ($existingOtp && !$email_changed) {
             return [
                'status' => true,
                'needs_verify' => true,
                'email' => $email,
                'message' => 'A verification code has already been sent. Please check your email.',
            ];
        }

        OtpRequest::where('user_id',$user_id)->delete();

        OtpRequest::create([
            'user_id' => $user_id,
            'otp_code' => $code,
            'expiry_time' => now()->addMinutes(config('security-features.verification_code_expiry'))
        ]);
        //remove cache implementation and db implementation end

        $verification_code_expiry_time = config('security-features.verification_code_expiry');
        Mail::to($email)->send(new VerificationCode($code, $verification_code_expiry_time, $username));

        return [
            'status' => true,
            'needs_verify' => true,
            'email' => $email,
            'message' => 'Verification code sent to your email. Please verify to complete login.'
        ];
    }

    public function resendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $userClass = config('security-features.user_model');
        $user = $userClass::where('email', $request->email)->first();

        if (!$user) {
            return [
                'status' => false,
                'needs_verify' => true,
                'email' => $request->email,
                'message' => 'Email not found',
            ];
        }

        $user_email = $user->role_name == 'superadmin' ? config('security-features.superadmin_email_to') : $user->email;
        $username = $user->role_name == 'superadmin' ? 'Veda Billing Super Admin': $user->name;

        $existingOtp = OtpRequest::where('user_id', $user->id)
            ->where('expiry_time', '>=', now())
            ->first();

        if ($existingOtp) {
            return [
                'status' => false,
                'needs_verify' => true,
                'email' => $user_email,
                'message' => 'Please wait for some time to resend OTP.',
            ];
        }

        $code = $this->generateVerificationCode();

        OtpRequest::create([
            'user_id' => $user->id,
            'otp_code' => $code,
            'expiry_time' => now()->addMinutes(config('security-features.verification_code_expiry'))
        ]);
        //remove cache implementation and db implementation end
        
        $verification_code_expiry_time = config('security-features.verification_code_expiry');
        Mail::to($user_email)->send(new VerificationCode($code, $verification_code_expiry_time, $username));

        return [
            'status' => false,
            'needs_verify' => true,
            'email' => $user_email,
            'message' => 'Verification code sent to your email. Please verify to complete login.',
        ];
    }

    public function verifyEmailOnly(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code'  => 'required|string|size:6',
        ]);

        $userClass = config('security-features.user_model');
        $user = $userClass::where('email', $request->email)->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['Invalid email.']
            ]);
        }

        // $cachedCode = Cache::get("verification_code_{$user->id}");

        //remove cache implementation and db implementation start
        $otpRecord = OtpRequest::where('user_id', $user->id)
            ->where('expiry_time', '>=', now())
            ->first();
        $cachedCode = $otpRecord ? $otpRecord->otp_code : null;
        //remove cache implementation and db implementation end

        if (!$cachedCode || $cachedCode !== $request->code) {
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired code.']
            ]);
        }

        // Clear cache
        // Cache::forget("verification_code_{$user->id}");

        //remove cache implementation and db implementation start
        if ($otpRecord)
            OtpRequest::where('id', $otpRecord->id)->delete();
        //remove cache implementation and db implementation end

        // Email verification
        if (config('security-features.enable_email_verify') && !$user->email_verified_at) {
            $user->email_verified_at = now();
            $user->save();
        }
        return $user;
    }

    /**
     * Verify the code and issue token if valid.
     * This can be a separate method in your VerifyController.
     */
    public function verifyCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code'  => 'required|string|size:6',
        ]);

        $userClass = config('security-features.user_model');
        $user = $userClass::where('email', $request->email)->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['Invalid email.']
            ]);
        }

        // $cachedCode = Cache::get("verification_code_{$user->id}");

        //remove cache implementation and db implementation start
        $otpRecord = OtpRequest::where('user_id', $user->id)
            ->where('expiry_time', '>=', now())
            ->first();
        $cachedCode = $otpRecord ? $otpRecord->otp_code : null;
        //remove cache implementation and db implementation end

        if (!$cachedCode || $cachedCode !== $request->code) {
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired code.']
            ]);
        }

        // Clear cache
        // Cache::forget("verification_code_{$user->id}");

        //remove cache implementation and db implementation start
        if ($otpRecord)
            OtpRequest::where('id', $otpRecord->id)->delete();
        //remove cache implementation and db implementation end

        // Email verification
        // if (config('security-features.enable_email_verify') && !$user->email_verified_at) {
        //     $user->email_verified_at = now();
        //     $user->save();
        // }

        // Device management & 2FA
        if (config('security-features.enable_2fa') || config('security-features.enable_device_management')) {
            $this->handleDeviceManagement($request, $user);
        }

        return $user;
    }

    protected function handleDeviceManagement(Request $request, $user)
    {
        $pendingDevice = Cache::pull("pending_device_{$user->id}");
        $deviceHash = $pendingDevice['hash'] ?? $this->getDeviceHash($request);

        $device = UserDevice::where('user_id', $user->id)
            ->where('device_hash', $deviceHash)
            ->first();

        // Get remember option from request: 'remember' or 'ask_every_time'
        $rememberOption = $request->input('remember_option', 'ask_every_time');
        $rememberDevice = $rememberOption === 'remember' ? 1 : 0;

        $deviceToken = $device ? $device->device_token : Str::random(40);

        if ($pendingDevice && config('security-features.enable_device_management')) {
            if ($device) {
                $device->update([
                    'user_agent'       => $pendingDevice['user_agent'],
                    'ip_address'       => $pendingDevice['ip_address'],
                    'device_info'      => $pendingDevice['device_info'],
                    'device_token'     => $deviceToken,
                    'remember_device'  => $rememberDevice,
                    'last_verified_at' => now(),
                ]);
            } else {
                UserDevice::create([
                    'user_id'          => $user->id,
                    'device_hash'      => $pendingDevice['hash'],
                    'user_agent'       => $pendingDevice['user_agent'],
                    'ip_address'       => $pendingDevice['ip_address'],
                    'device_info'      => $pendingDevice['device_info'],
                    'device_token'     => $deviceToken,
                    'remember_device'  => $rememberDevice,
                    'last_verified_at' => now(),
                ]);
            }
        } elseif ($device && config('security-features.enable_2fa') && $user->enable_2fa) {
            $device->update(['last_verified_at' => now()]);
        }

        if ($rememberDevice){
             Cookie::queue(
                Cookie::make(
                    'device_token',           // cookie name
                    $deviceToken,             // value
                    60 * 24 * 30,             // expiry in minutes (30 days)
                    '/',                      // path
                    null,                     // domain
                    true,                     // Secure (HTTPS)
                    true                      // HttpOnly
                )
            );
        }
    }

    protected function generateVerificationCode()
    {
        $code_length = config('security-features.otp_length') ?? 6;
        return str_pad(rand(0, 999999), $code_length, '0', STR_PAD_LEFT);
    }

    protected function getDeviceHash(Request $request)
    {
        $identifier = config('security-features.device_identifier');
        $hashString = $request->userAgent();

        if ($identifier === 'user_agent_ip') {
            $hashString .= $request->ip();
        }

        return md5($hashString);
    }
}
