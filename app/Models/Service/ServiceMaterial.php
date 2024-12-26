<?php

namespace App\Models\Service;

use Illuminate\Database\Eloquent\Model;

class ServiceMaterial extends Model
{
    protected $table = 't_workorder_material';
    protected $primaryKey = 'sysid';
    public $timestamps = false;
    protected $casts = [
        'request' => 'float',
        'used'=>'float',
        'line_cost'=>'float',
        'line_cost'=>'float'
    ];

}
