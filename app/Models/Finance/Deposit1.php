<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use PagesHelp;

class Deposit1 extends Model
{
    protected $table = 't_deposit1';
    protected $primaryKey = 'sysid';
    public $timestamps = TRUE;
    const CREATED_AT = 'update_timestamp';
    const UPDATED_AT = 'update_timestamp';
    protected $casts = [
        'amount' => 'float',
        'paid'=>'float',
        'deposit'=>'float',
        'sysid_jurnal'=>'integer',
        'sysid'=>'integer',
        'is_approved'=>'string'
    ];

    public static function GenerateNumber($ref_date){
        $PREFIX = 'DIT';
        return PagesHelp::GetDocseries('',$PREFIX,$ref_date);
    }

    public static function VoucherNumber($trans_code,$ref_date){
        return PagesHelp::GetVoucherseries($trans_code,$ref_date);
    }
}
