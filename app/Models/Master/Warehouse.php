<?php

namespace App\Models\Master;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    protected $table = 'm_warehouse';
    public $timestamps = true;
    const CREATED_AT = 'update_timestamp';
    const UPDATED_AT = 'update_timestamp';
    protected $casts = [
        'is_allow_receive'=>'string',
        'is_allow_transfer'=>'string',
        'is_auto_transfer'=>'string',
        'is_active'=>'string'
    ];
}
