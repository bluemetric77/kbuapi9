<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Model;

class Drivergroup extends Model
{
    protected $table = 'm_driver_group';
    protected $primaryKey = 'sysid';
    public $timestamps = TRUE;
    const CREATED_AT = 'update_timestamp';
    const UPDATED_AT = 'update_timestamp';
}
