<?php

namespace Prajwol\LaravelSecurityFeatures\Tests\Fixtures;

use Illuminate\Http\Request;
use Prajwol\LaravelSecurityFeatures\Traits\HandlesSecurityFeatures;

/**
 * Minimal class using the trait so its methods can be exercised directly.
 */
class SecurityHarness
{
    use HandlesSecurityFeatures;

    public function code()
    {
        return $this->generateVerificationCode();
    }

    public function deviceHash(Request $request)
    {
        return $this->getDeviceHash($request);
    }
}
