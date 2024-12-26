<?php

namespace App\Models\Inventory;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class ItemsPrice extends Model
{
    protected $table = 't_item_price';
    protected $primaryKey = 'line_sysid';
    public $timestamps = false;
    protected $guarded = [];
    /*const CREATED_AT = 'created_date';
    const UPDATED_AT = 'updated_date';
    protected $casts = [
        'qty'=>'float',
        'item_cost'=>'float',
        'is_deleted'=>'string'
    ];*/
}
