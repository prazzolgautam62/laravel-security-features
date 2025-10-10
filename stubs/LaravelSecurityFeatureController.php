<?php

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Prajwol\LaravelSecurityFeatures\Traits\HandlesSecurityFeatures;


class LaravelSecurityFeatureController extends Controller
{
    use HandlesSecurityFeatures;

    public function verify(Request $request)
    {
        $user = $this->verifyCode($request);
        // custom logic here...
    }
}

