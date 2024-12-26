<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use PagesHelp;

class InTransit1 extends Model
{
    protected $table = 't_intransit1';
    protected $primaryKey = 'sysid';
    public $timestamps = TRUE;
    const CREATED_AT = 'update_timestamp';
    const UPDATED_AT = 'update_timestamp';
    protected $casts = [
        'amount' => 'float',
        'deposit'=>'float',
        'sys_jurnal'=>'integer',
        'sysid'=>'integer',
        'is_void'=>'string',
        'update_timestamp'=>'datetime'
    ];

    public static function GenerateNumber($pool_code,$ref_date){
        $PREFIX = 'CIT';
        return PagesHelp::GetDocseries($pool_code,$PREFIX,$ref_date);
    }
    public static function GenerateVoucherSeries($trans_code,$ref_date){
        return PagesHelp::GetVoucherseries($trans_code,$ref_date);
    }}
