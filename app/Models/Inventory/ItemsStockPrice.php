<?php

namespace App\Models\Inventory;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class ItemsStockPrice extends Model
{
    protected $table = 'm_item_stock_price';
    protected $primaryKey = 'sysid';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = [
        'on_hand'=>'float',
        'price'=>'float',
        'is_hold'=>'string',
        'is_allow_negatif'=>'string'
    ];
}
