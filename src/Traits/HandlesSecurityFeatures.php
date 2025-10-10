<?php

namespace Prajwol\LaravelSecurityFeatures\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Prajwol\LaravelSecurityFeatures\Mail\VerificationCode;
use Prajwol\LaravelSecurityFeatures\Models\UserDevice;

trait HandlesSecurityFeatures
{
    /**
     * To be called after successful Auth::attempt() in your login method.
     * Returns null if no verification needed, or a JSON response if pending.
     */
    public function handlePostLogin(Request $request)
    {
        $userClass = config('security-features.user_model');
        $user = $userClass::where('email', $request->email)->first();;
        $needsVerification = false;

        // Check email verification if enabled in config and user hasn't verified
        // if (config('security-features.enable_email_verify') && !$user->email_verified_at) {
        //     $needsVerification = true;
        // }

        if (config('security-features.enable_2fa') || config('security-features.enable_device_management')) {
            $deviceHash = $this->getDeviceHash($request);
            $device = UserDevice::where('user_id', $user->id)
                ->where('device_hash', $deviceHash)
                ->first();

            $requires2fa = config('security-features.enable_2fa') && $user->enable_2fa;
            $isNewDevice = config('security-features.enable_device_management') && !$device;
            $is2faExpired = false;

            // Check if 2FA has expired based on validity days
            if ($requires2fa && $device && $device->last_verified_at) {
                $validityDays = config('security-features.2fa_validity_days', 30);
                $is2faExpired = $device->last_verified_at->diffInDays(now()) > $validityDays;
            }

            if ($requires2fa && ($isNewDevice || $is2faExpired || !$device)) {
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

        if ($needsVerification) {
            $code = $this->generateVerificationCode();
            $user_email = $user->type == 1 ? config('security-features.superadmin_email_to') : $user->email;
            Cache::put("verification_code_{$user->id}", $code, now()->addMinutes(config('security-features.verification_code_expiry')));
            Mail::to($user_email)->send(new VerificationCode($code));

            return response()->json([
                'status' => false,
                'needs_verify' => true,
                'email' => $user->email,
                'message' => 'Verification code sent to your email. Please verify to complete login.',
            ], 200);
        }

        return null; // Proceed to issue token
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

        $cachedCode = Cache::get("verification_code_{$user->id}");

        if (!$cachedCode || $cachedCode !== $request->code) {
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired code.']
            ]);
        }

        // Clear cache
        Cache::forget("verification_code_{$user->id}");

        // Email verification
        if (config('security-features.enable_email_verify') && !$user->email_verified_at) {
            $user->email_verified_at = now();
            $user->save();
        }

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

        if ($pendingDevice && config('security-features.enable_device_management')) {
            if ($device) {
                $device->update([
                    'user_agent'       => $pendingDevice['user_agent'],
                    'ip_address'       => $pendingDevice['ip_address'],
                    'device_info'      => $pendingDevice['device_info'],
                    'last_verified_at' => now(),
                ]);
            } else {
                UserDevice::create([
                    'user_id'          => $user->id,
                    'device_hash'      => $pendingDevice['hash'],
                    'user_agent'       => $pendingDevice['user_agent'],
                    'ip_address'       => $pendingDevice['ip_address'],
                    'device_info'      => $pendingDevice['device_info'],
                    'last_verified_at' => now(),
                ]);
            }
        } elseif ($device && config('security-features.enable_2fa') && $user->enable_2fa) {
            $device->update(['last_verified_at' => now()]);
        }
    }

    protected function generateVerificationCode()
    {
        return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
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
