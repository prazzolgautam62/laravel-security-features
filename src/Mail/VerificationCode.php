<?php

namespace Prajwol\LaravelSecurityFeatures\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VerificationCode extends Mailable
{
    use Queueable, SerializesModels;

    public $code;
    public $verification_code_expiry_time;
    public $username;
    public $subject;

    public function __construct($code, $verification_code_expiry_time, $username, $subject)
    {
        $this->code = $code;
        $this->verification_code_expiry_time = $verification_code_expiry_time;
        $this->username = $username;
        $this->subject = $subject;
    }

    public function build()
    {
        return $this->from(config('security-features.email_from'), config('security-features.email_from_name'))
                    ->subject('' . config('security-features.platform_name'). ': ' . $this->subject)
                    ->view('security-features::emails.verification_code') // You'll publish this view later
                    ->with(['code' => $this->code, 'verification_code_expiry_time' => $this->verification_code_expiry_time, 'username'=> $this->username]);
    }
}