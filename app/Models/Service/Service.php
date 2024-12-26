<?php

namespace App\Models\Service;

use Illuminate\Database\Eloquent\Model;
use PagesHelp;

class Service extends Model
{
    protected $table = 't_workorder_service';
    public $timestamps = false;
    protected $casts = [
        'is_closed' => 'string',
        'is_cancel'=>'string',
        'entry_date'=>'datetime:Y-m-d H:i:s',
        'close_service'=>'datetime:Y-m-d H:i:s',
        'service'=>'float',
        'total'=>'float',
        'is_external_wo'=>'string'
    ];

    public static function GenerateNumber($pool_code,$ref_date){
        $PREFIX = 'SRV';
        return PagesHelp::GetDocseries($pool_code,$PREFIX,$ref_date);
    }
}

