<?php

namespace App\Models\Service;

use Illuminate\Database\Eloquent\Model;

class GoodsRequest2 extends Model
{
    protected $table = 't_inventory_booked2';
    protected $primaryKey = 'sysid';
    public $timestamps = false;
    protected $guarded=[];
    protected $casts = [
        'qty_item' => 'float',
        'qty_supply' => 'float',
        'qty_used'=> 'float',
        'qty_return' => 'float',
        'item_cost'=>'float',
        'line_cost' => 'float'
    ];

}
