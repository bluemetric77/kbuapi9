<?php

namespace App\Models\Master;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class Devices extends Model
{
    protected $table = 'm_gps_device';
    protected $primaryKey = 'sysid';
    public $timestamps = false;
    protected $casts = [
        'deviceid' => 'string',
        'is_active'=>'string',
        'update_timestamp'=>'datetime:Y-m-d H:i:s'
    ];
}
