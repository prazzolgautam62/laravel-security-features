<?php

namespace Prajwol\LaravelSecurityFeatures\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
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
        $user = Auth::user();
        $needsVerification = false;

        if (config('security-features.enable_2fa')) {
            $needsVerification = true;
        }

        if (config('security-features.enable_device_management')) {
            $deviceHash = $this->getDeviceHash($request);
            $device = UserDevice::where('user_id', $user->id)
                                ->where('device_hash', $deviceHash)
                                ->first();

            if (!$device) {
                $needsVerification = true;
                // Temporarily store the new device hash in cache for verification
                Cache::put("pending_device_{$user->id}", $deviceHash, config('security-features.verification_code_expiry'));
            }
        }

        if ($needsVerification) {
            $code = $this->generateVerificationCode();
            Cache::put("verification_code_{$user->id}", $code, config('security-features.verification_code_expiry'));
            Mail::to($user->email)->send(new VerificationCode($code));

            // Logout temporarily to prevent access until verified
            Auth::logout();

            return response()->json([
                'status' => 'pending',
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
            'code' => 'required|string|size:6',
        ]);

        $user = config('security-features.user_model')::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['message' => 'Invalid email'], 401);
        }

        $cachedCode = Cache::get("verification_code_{$user->id}");

        if (!$cachedCode || $cachedCode !== $request->code) {
            return response()->json(['message' => 'Invalid or expired code'], 401);
        }

        // Clear cache
        Cache::forget("verification_code_{$user->id}");

        // If new device, record it
        if (config('security-features.enable_device_management')) {
            $pendingDeviceHash = Cache::pull("pending_device_{$user->id}");
            if ($pendingDeviceHash) {
                UserDevice::create([
                    'user_id' => $user->id,
                    'device_hash' => $pendingDeviceHash,
                    'last_verified_at' => now(),
                ]);
            }
        }

        // Log in the user and issue Passport token
        Auth::login($user);
        $token = $user->createToken('Personal Access Token')->accessToken;

        // Log the login if enabled (event will handle it)
        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
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