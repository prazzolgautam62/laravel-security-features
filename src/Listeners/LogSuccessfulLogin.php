<?php

namespace Prajwol\LaravelSecurityFeatures\Listeners;

use Illuminate\Auth\Events\Login;
use Prajwol\LaravelSecurityFeatures\Models\LoginLog;
use Illuminate\Http\Request;

class LogSuccessfulLogin
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function handle(Login $event)
    {
        if (config('security-features.enable_login_logs')) {
            LoginLog::create([
                'user_id' => $event->user->id,
                'ip_address' => $this->request->ip(),
                'user_agent' => $this->request->userAgent(),
            ]);
        }
    }
}