<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

class ItemCorrection2 extends Model
{
    protected $table = 't_inventory_correction2';
    protected $primaryKey = 'sysid';
    public $timestamps = false;
    protected $casts = [
        'freeze_stock'=>'float',
        'movement_stock'=>'float',
        'final_stock'=>'float',
        'current_stock'=>'float',
        'opname_stock'=>'float',
        'adjustment_stock'=>'float',
        'end_stock'=>'float',
        'cost_current'=>'float',
        'cost_adjustment'=>'float'
    ];

}
