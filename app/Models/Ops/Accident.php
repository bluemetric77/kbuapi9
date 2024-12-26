<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Model;
use PagesHelp;

class Accident extends Model
{
    protected $table = 't_accident_document';
    protected $primaryKey = 'sysid';
    public $timestamps = true;
    const CREATED_AT = 'update_timestamp';
    const UPDATED_AT = 'update_timestamp';
    protected $casts = [
        'cost' => 'float',
        'office_cost' => 'float',
        'driver_cost' => 'float',
        'is_link'=>'string',
        'cash_link'=>'integer'

    ];
    public static function GenerateNumber($pool_code,$ref_date){
        $PREFIX = 'ACD';
        return PagesHelp::GetDocseries($pool_code,$PREFIX,$ref_date);
    }
}
