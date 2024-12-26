<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Model;
use PagesHelp;

class OperationChecklist1 extends Model
{
    protected $table = 't_vehicle_checklist1';
    public $timestamps = true;
    const CREATED_AT = 'update_timestamp';
    const UPDATED_AT = 'update_timestamp';

    public static function GenerateNumber($pool_code,$ref_date){
        $PREFIX = 'CHK';
        return PagesHelp::GetDocseries($pool_code,$PREFIX,$ref_date);
    }
}
