<?php

namespace App\Models\Master;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    protected $table = 'm_voucher_jurnal';
    public $timestamps = true;
    const CREATED_AT = 'update_timestamp';
    const UPDATED_AT = 'update_timestamp';
    protected $casts = [
        'sysid'=>'integer',
        'account_no' => 'string',
        'is_active'=>'string',
        'is_recorded'=>'string'
    ];
}
