<?php

namespace Prajwol\LaravelSecurityFeatures\Models;

use Illuminate\Database\Eloquent\Model;

class UserDevice extends Model
{
    protected $guarded = [];
    protected $table = 'user_devices';

    public function user()
    {
        return $this->belongsTo(config('security-features.user_model'));
    }
}