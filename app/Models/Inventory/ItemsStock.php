<?php

namespace App\Models\Inventory;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class ItemsStock extends Model
{
    protected $table = 'm_item_stock';
    protected $primaryKey = 'sysid';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = [
        'qty'=>'float',
        'item_cost'=>'float',
        'is_deleted'=>'string'
    ];
}
