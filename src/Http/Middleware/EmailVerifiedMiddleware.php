<?php

namespace Prajwol\LaravelSecurityFeatures\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmailVerifiedMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (config('security-features.enable_email_verify') && Auth::check()) {
            $userModel = config('security-features.user_model');
            $user = Auth::user();

            if (!$user || !$user->email_verified_at) {
                return response()->json([
                    'status' => false,
                    'needs_email_verification' => true,
                    'message' => 'Email not verified. Please verify your email to continue.',
                ], 200);
            }
        }

        return $next($request);
    }
}