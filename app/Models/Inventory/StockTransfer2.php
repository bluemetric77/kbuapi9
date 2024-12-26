<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

class StockTransfer2 extends Model
{
    protected $table = 't_inventory_transfer2';
    protected $primaryKey = 'sysid';
    public $timestamps = false;
    protected $casts = [
        'qty_item' => 'float',
        'itemcost'=>'float',
        'line_cost' => 'float'
    ];

}
