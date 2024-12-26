<?php

namespace App\Models\Master;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class Items extends Model
{
    protected $table = 'm_item';
    public $timestamps = true;
    const CREATED_AT = 'update_timestamp';
    const UPDATED_AT = 'update_timestamp';
    protected $hidden=['db_version','app_version','update_userid','update_timestamp','update_location'];
    protected $casts = [
        'convertion'=>'float',
        'on_hand'=>'float',
        'price'=>'float',
        'minimum_stock'=>'float',
        'maximum_stock'=>'float',
        'net_price'=>'float',
        'is_active'=>'string',
        'is_stock_record'=>'string',
        'is_calculate_order'=>'string',
        'is_hold'=>'string',
        'stock_in'=>'float',
        'stock_out'=>'float',
        'stock_so'=>'float',
        'begining_stock'=>'float',
        'end_stock'=>'float',
        'inventory_value'=>'float'
    ];
}
