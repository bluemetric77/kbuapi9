<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use PagesHelp;

class StockRequest1 extends Model
{
    protected $table = 't_inventory_request1';
    protected $primaryKey = 'sysid';
    public $timestamps = TRUE;

    const CREATED_AT = 'update_timestamp';
    const UPDATED_AT = 'update_timestamp';
    protected $guarded =[];
    protected $casts = [
        'total' => 'float',
        'update_timestamp'=>'datetime',
        'is_processed'=>'string',
        'is_canceled'=>'string',
        'is_authorize'=>'string',
        'item_code'=>'string',
        'part_number'=>'string',
        'description'=>'string',
        'on_hand'=>'float'
    ];

    public static function GenerateNumber($pool_code,$ref_date){
        $PREFIX = 'ISR';
        return PagesHelp::GetDocseries($pool_code,$PREFIX,$ref_date);
    }
}
