<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use PagesHelp;

class Cash1 extends Model
{
    protected $table = 't_cash_bank1';
    protected $primaryKey = 'sysid';
    public $timestamps = TRUE;
    const CREATED_AT = 'update_timestamp';
    const UPDATED_AT = 'update_timestamp';
    protected $casts = [
        'amount' => 'float',
        'sysid_jurnal'=>'integer',
        'sysid'=>'integer',
        'update_timestamp'=>'datetime',
        'is_void'=>'string',
        'sysid_void'=>'integer'
    ];

    public static function GenerateNumber($trans_code,$ref_date){
        return PagesHelp::GetVoucherseries($trans_code,$ref_date);
    }
    public static function DocNumber($pool_code,$ref_date){
        $PREFIX = 'CB';
        return PagesHelp::GetDocseries($pool_code,$PREFIX,$ref_date);
    }
}
