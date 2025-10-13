<?php

namespace Prajwol\LaravelSecurityFeatures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OtpRequest extends Model
{
    use SoftDeletes;
    protected $guarded = [];
    protected $table = 'otp_requests';

    public function user()
    {
        return $this->belongsTo(config('security-features.user_model'));
    }
}