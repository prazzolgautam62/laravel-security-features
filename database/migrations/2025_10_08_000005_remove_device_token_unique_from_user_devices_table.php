<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveDeviceTokenUniqueFromUserDevicesTable extends Migration
{
    public function up()
    {
        Schema::table('user_devices', function (Blueprint $table) {
             $table->dropUnique(['device_token']);
        });
    }

    public function down()
    {
        Schema::table('user_devices', function (Blueprint $table) {
            $table->unique('device_token');
        });
    }
}