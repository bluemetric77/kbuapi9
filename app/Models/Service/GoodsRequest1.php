<?php

namespace App\Models\Service;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use PagesHelp;

class GoodsRequest1 extends Model
{
    protected $table = 't_inventory_booked1';
    protected $primaryKey = 'sysid';
    public $timestamps = true;

    const CREATED_AT = 'update_timestamp';
    const UPDATED_AT = 'update_timestamp';
    protected $guarded=[];
    protected $casts = [
        'total' => 'float',
        'is_approved'=>'string',
        'is_autoclosed'=>'string'
    ];

    public static function GenerateNumber($pool_code,$ref_date){
        $PREFIX = 'PBG';
        return PagesHelp::GetDocseries($pool_code,$PREFIX,$ref_date);
    }
    public static function GenerateRetur($pool_code,$ref_date){
        $PREFIX = 'RBG';
        return PagesHelp::GetDocseries($pool_code,$PREFIX,$ref_date);
    }
}
