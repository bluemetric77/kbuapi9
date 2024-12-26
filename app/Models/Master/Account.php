<?php

namespace App\Models\Master;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $table = 'm_account';
    protected $primaryKey = 'sysid';
    public $timestamps = true;
    const CREATED_AT = 'update_timestamp';
    const UPDATED_AT = 'update_timestamp';
    protected $casts = [
        'account_no' => 'string',
        'is_active' =>  'string',
        'intransit'=> 'string',
        'is_header'=> 'string',
        'is_cash_bank'=>'string',
        'is_posted'=>'string',
        'intransit'=> 'string',
        'intransit'=> 'string',
        'reversed1'=>'float',
        'reversed2'=>'float',
        'reversed3'=>'float',
        'reversed4'=>'float',
        'reversed5'=>'float',
        'reversed6'=>'float',
        'reversed7'=>'float',
        'reversed8'=>'float',
        'reversed9'=>'float',
        'reversed10'=>'float',
        'reversed11'=>'float',
        'reversed12'=>'float',
        'is_valid'=>'string',
        'debit'=>'float',
        'credit'=>'float',
        'gl_debit'=>'float',
        'gl_credit'=>'float',
    ];
}
