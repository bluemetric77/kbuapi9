<?php

namespace App\Models\Ops;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class DeviceLog extends Model
{
    protected $table = 't_gps_device_log';
    protected $primaryKey = 'sysid';
    public $timestamps = false;
    protected $casts = [
        'deviceid' => 'string'
    ];
}
