<?php

namespace App\Models\Ops;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class Busroutepoint extends Model
{
    protected $table = 'm_bus_route_checkpoint';
    protected $primaryKey=['sysid','checkpoint_sysid'];
    public $incrementing=false;
    public $primaryType='string';
    public $timestamps = false;
    const CREATED_AT = 'update_timestamp';
    const UPDATED_AT = 'update_timestamp';
    protected $casts = [
        'factor_point'=>'float',
        'point'=>'float',
    ];
}
