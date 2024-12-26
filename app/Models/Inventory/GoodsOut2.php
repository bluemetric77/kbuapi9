<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

class GoodsOut2 extends Model
{
    protected $table = 't_inventory_inout2';
    protected $primaryKey = 'sysid';
    public $timestamps = false;
    protected $casts = [
        'qty_item' => 'float',
        'qty_used'=>'float'
    ];

}
