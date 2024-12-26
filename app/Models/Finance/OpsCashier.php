<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use PagesHelp;

class OpsCashier extends Model
{
    protected $table = 't_operation_cashier';
    protected $primaryKey = 'sysid';
    public $timestamps = true;
    const CREATED_AT = 'update_timestamp';
    const UPDATED_AT = 'update_timestamp';
    protected $casts = [
        'target2' => 'float',
        'target' => 'float',
        'revenue'=>'float',
        'others' => 'float',
        'total' => 'float',
        'station_fee'=>'float',
        'operation_fee'=>'float',
        'external_cost'=>'float',
        'internal_cost'=>'float',
        'station_fee'=>'float',
        'operation_fee'=>'float',
        'paid' => 'float',
        'deposit'=>'float',
        'ks'=>'float',
        'dispensation' => 'float',
        'unpaid' => 'float',
        'standar_cost'=>'float',
        'passenger'=>'float',
        'sysid_jurnal'=>'integer',
        'sysid_void'=>'integer',
        'update_timestamp'=>'datetime'
    ];

    public static function GenerateNumber($pool_code,$ref_date){
        $PREFIX = 'STR';
        return PagesHelp::GetDocseries($pool_code,$PREFIX,$ref_date);
    }
}
