<?php

namespace App\Models\Service;

use Illuminate\Database\Eloquent\Model;
use PagesHelp;

class ServiceExternal extends Model
{
    protected $table = 't_workorder_external';
    public $timestamps = false;
    protected $casts = [
        'cost_estimation' => 'float'
    ];


    public static function GenerateNumber($pool_code,$ref_date){
        $PREFIX = 'EXT';
        return PagesHelp::GetDocseries($pool_code,$PREFIX,$ref_date);
    }
}

