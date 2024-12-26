<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use PagesHelp;

class OutPayment1 extends Model
{
    protected $table = 't_outpayment1';
    protected $primaryKey = 'sysid';
    public $timestamps = true;
    const CREATED_AT = 'update_timestamp';
    const UPDATED_AT = 'update_timestamp';
    protected $casts = [
        'amount' => 'float',
        'paid' => 'float',
        'unpaid' => 'float',
        'no_jurnal'=>'integer',
        'sysid'=>'integer',
        'is_void'=>'string',
        'is_approved'=>'string',
        'total'=>'float',
        'payment'=>'float',
        'update_timestamp'=>'datetime:Y-m-d H:i:s',
        'approved_date1'=>'datetime:Y-m-d H:i:s',
        'approved_date2'=>'datetime:Y-m-d H:i:s'
    ];

    public static function GenerateNumber($ref_date){
        $PREFIX = 'BOP';
        return PagesHelp::GetDocseries('',$PREFIX,$ref_date);
    }

    public static function VoucherNumber($trans_code,$ref_date){
        return PagesHelp::GetVoucherseries($trans_code,$ref_date);
    }
}
