<?php

namespace App\Models\Purchase;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use PagesHelp;

class JobInvoice1 extends Model
{
    protected $table = 't_job_invoice1';
    protected $primaryKey = 'sysid';
    public $timestamps = TRUE;
    const CREATED_AT = 'update_timestamp';
    const UPDATED_AT = 'update_timestamp';
    protected $guarded = [];
    protected $casts = [
        'amount' => 'float',
        'discount' => 'float',
        'tax' => 'float',
        'total' => 'float',
        'payment' => 'float',
        'unpaid'=>'float',
        'is_void'=>'string',
        ];

    public static function GenerateNumber($pool_code,$ref_date){
        $PREFIX = 'SPK';
        return PagesHelp::GetDocseries($pool_code,$PREFIX,$ref_date);
    }
}
