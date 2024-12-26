<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use PagesHelp;

class PaymentSubmission1 extends Model
{
    protected $table = 't_payment_submission1';
    protected $primaryKey = 'sysid';
    public $timestamps = TRUE;
    const CREATED_AT = 'update_timestamp';
    const UPDATED_AT = 'update_timestamp';
    protected $guarded=[];
    protected $casts = [
        'total' => 'float',
        'payment' => 'float',
        'sysid'=>'integer',
        'is_void'=>'string',
        'is_approved'=>'string',
        'is_realization'=>'string',
        'update_timestamp'=>'datetime:Y-m-d H:i:s',
        'approved_date1'=>'datetime:Y-m-d H:i:s',
        'approved_date2'=>'datetime:Y-m-d H:i:s'
    ];

    public static function GenerateNumber($ref_date){
        $PREFIX = 'BRB';
        return PagesHelp::GetDocseries('',$PREFIX,$ref_date);
    }

    public static function VoucherNumber($trans_code,$ref_date){
        return PagesHelp::GetVoucherseries($trans_code,$ref_date);
    }
}
