<?php

namespace App\Models\Master;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $table = 'm_vehicle';
    protected $primaryKey = 'sysid';
    protected $guarded=[];
    public $timestamps = true;
    const CREATED_AT = 'update_timestamp';
    const UPDATED_AT = 'update_timestamp';
    protected $casts = [
        'is_storing'=>'string',
        'device_id'=>'string',
        'is_active'=>'string'
    ];
}
