<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

class StockRequest2 extends Model
{
    protected $table = 't_inventory_request2';
    protected $primaryKey = 'sysid';
    public $timestamps = false;
    protected $casts = [
        'item_code'=>'string',
        'part_number'=>'string',
        'description'=>'string',
        'qty_item' => 'float',
        'item_cost'=>'float',
        'line_cost' => 'float',
         'on_hand'=>'float'
    ];

}
