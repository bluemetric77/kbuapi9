<?php

namespace App\Models\Master;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class Vehiclegroup extends Model
{
    protected $table = 'm_vehicle_group';
    protected $primaryKey = 'sysid';
    public $timestamps = true;
    const CREATED_AT = 'update_timestamp';
    const UPDATED_AT = 'update_timestamp';
    protected $casts =[
        'is_active'=>'string',
        'update_timestamp'=>'datetime:Y-m-d H:i:s'
    ];
}
