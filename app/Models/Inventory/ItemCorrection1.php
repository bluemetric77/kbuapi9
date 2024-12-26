<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use PagesHelp;

class ItemCorrection1 extends Model
{
    protected $table = 't_inventory_correction1';
    protected $primaryKey = 'sysid';
    public $timestamps = TRUE;
    const CREATED_AT = 'update_timestamp';
    const UPDATED_AT = 'update_timestamp';
    protected $casts = [
        'inventory_cost' => 'float'
    ];

    public static function GenerateNumber($pool_code,$ref_date){
        $PREFIX = 'ISO';
        return PagesHelp::GetDocseries($pool_code,$PREFIX,$ref_date);
    }
}
