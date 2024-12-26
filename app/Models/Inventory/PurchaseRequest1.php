<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use PagesHelp;

class PurchaseRequest1 extends Model
{
    protected $table = 't_purchase_request1';
    protected $primaryKey = 'sysid';
    public $timestamps = TRUE;
    const CREATED_AT = 'update_timestamp';
    const UPDATED_AT = 'update_timestamp';
    protected $casts = [
        'qty_order' => 'float',
        'qty_invoice'=>'float',
        'purchase_price' => 'float',
        'prc_discount1' => 'float',
        'prc_discount2' => 'float',
        'prc_tax' => 'float',
        'total' => 'float',
        'qty_request'=>'float',
        'line_supply'=>'float',
        'convertion'=>'float',
        'current_stock'=>'float',
        'is_draft'=>'string',
        'is_posted'=>'string',
        'is_purcahse_order'=>'string',
        'is_cancel'=>'string',
    ];

    public static function GenerateNumber($pool_code,$ref_date){
        $PREFIX = 'PEG';
        return PagesHelp::GetDocseries($pool_code,$PREFIX,$ref_date);
    }
}
