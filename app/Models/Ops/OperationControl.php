<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Model;
use PagesHelp;

class OperationControl extends Model
{
    protected $table = 't_vehicle_control';
    public $timestamps = false;

    public static function GenerateNumber($pool_code,$ref_date){
        $PREFIX = 'CHK';
        return PagesHelp::GetDocseries($pool_code,$PREFIX,$ref_date);
    }
}

