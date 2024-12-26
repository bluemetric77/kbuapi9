<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use PagesHelp;

class AccountMutation extends Model
{
    protected $table = 't_account_mutation';
    protected $primaryKey = 'sysid';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = [
        'debit' => 'float',
        'credit' => 'float',
        'is_void'=>'string',
        'is_verified'=>'string',
        'project'=>'string'
    ];
}
