<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use PagesHelp;

class InPayment1 extends Model
{
    protected $table = 't_inpayment';
    protected $primaryKey = 'sysid';
    public $timestamps = TRUE;
    protected $hidden=['update_userid','update_timestamp'];
    const CREATED_AT = 'update_timestamp';
    const UPDATED_AT = 'update_timestamp';
    protected $casts = [
        'amount' => 'float',
        'paid' => 'float',
        'unpaid' => 'float',
        'no_jurnal'=>'integer',
        'sysid'=>'integer'
    ];

    public static function GenerateNumber($trans_code,$ref_date){
        return PagesHelp::GetVoucherseries($trans_code,$ref_date);
    }
}
