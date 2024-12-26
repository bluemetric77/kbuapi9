<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use PagesHelp;

class Operation extends Model
{
    protected $table = 't_operation';
    protected $primaryKey = 'sysid';
    public $timestamps = true;
    const CREATED_AT = 'update_timestamp';
    const UPDATED_AT = 'update_timestamp';
    protected $casts = [
        'route_default' => 'int',
        'route_id'=>'int',
        'distance' => 'float',
        'odometer' => 'float',
        'rate' => 'float',
        'target' => 'float',
        'target2' => 'float',
        'deposit'=>'float',
        'debt_accident'=>'float',
        'debt_deposit'=>'float',
        'passenger'=>'float',
        'revenue'=>'float',
        'others'=>'float',
        'total'=>'float',
        'dispensation'=>'float',
        'paid'=>'float',
        'unpaid'=>'float',
        'ks'=>'float',
        'is_ops_closed'=>'string',
        'is_storing'=>'string',
        'is_cancel'=>'string',
        'passenger'=>'float',
        'standar_cost'=>'float',
        'internal_cost'=>'float',
        'external_cost'=>'float',
        'cost'=>'float',
        'operation_fee'=>'float',
        'station_fee'=>'float'
    ];

    public static function GenerateNumber($pool_code,$ref_date){
        $PREFIX = 'SPJ';
        return PagesHelp::GetDocseries($pool_code,$PREFIX,$ref_date);
    }
}
