<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class OperationRoute extends Model
{
    protected $table = 't_operation_route';
    public $timestamps = false;
    protected $casts = [
        'line_id' => 'int',
        'checkpoint_sysid'=>'int',
        'factor_point' => 'float',
        'point' => 'float',
        'passenger' => 'float',
        'total' => 'float',
        'is_confirm'=>'boolean'
    ];

}
