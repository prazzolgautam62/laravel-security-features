<?php

namespace Prajwol\LaravelSecurityFeatures\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VerificationCode extends Mailable
{
    use Queueable, SerializesModels;

    public $code;

    public function __construct($code)
    {
        $this->code = $code;
    }

    public function build()
    {
        return $this->from(config('security-features.email_from'))
                    ->subject('Your Verification Code')
                    ->text('security-features::emails.verification_code') // You'll publish this view later
                    ->with(['code' => $this->code]);
    }
}