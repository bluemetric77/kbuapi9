<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

class OpsChashierOthers extends Model
{
    protected $table = 't_operation_cashier_others';
    protected $casts = [
        'o_001' => 'float',
        'o_002' => 'float',
        'o_003' => 'float',
        'o_004' => 'float',
        'o_005' => 'float',
        'o_006' => 'float',
        'o_007' => 'float',
        'o_008' => 'float',
        'o_009' => 'float',
        'o_010' => 'float',
        'o_011' => 'float',
        'o_012' => 'float',
        'o_013' => 'float',
        'o_014' => 'float',
        'o_015' => 'float',
        'o_016' => 'float',
        'o_017' => 'float',
        'o_018' => 'float',
        'o_019' => 'float',
        'o_020' => 'float'
    ];
}
