<?php

namespace Prajwol\LaravelSecurityFeatures\Models;

use Illuminate\Database\Eloquent\Model;

class LoginLog extends Model
{
    protected $guarded = [];
    protected $table = 'login_logs';

    public function user()
    {
        return $this->belongsTo(config('security-features.user_model'));
    }
}