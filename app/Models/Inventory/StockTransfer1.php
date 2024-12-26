<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use PagesHelp;

class StockTransfer1 extends Model
{
    protected $table = 't_inventory_transfer1';
    protected $primaryKey = 'sysid';
    public $timestamps = TRUE;
    const CREATED_AT = 'update_timestamp';
    const UPDATED_AT = 'update_timestamp';
    protected $guarded=[];
    protected $casts = [
        'inventory_cost' => 'float',
        'is_canceled'=>'string',
        'is_autoposted'=>'string',
        'is_received'=>'string'
    ];

    public static function GenerateNumber($pool_code,$ref_date){
        $PREFIX = 'ITO';
        return PagesHelp::GetDocseries($pool_code,$PREFIX,$ref_date);
    }
    public static function GenerateNumber2($pool_code,$ref_date){
        $PREFIX = 'ITI';
        return PagesHelp::GetDocseries($pool_code,$PREFIX,$ref_date);
    }}
