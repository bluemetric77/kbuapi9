<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use PagesHelp;

class AccountMutationCheck extends Model
{
    protected $table = 't_account_mutation_check';
    protected $primaryKey = 'sysid';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = [
        'debit' => 'float',
        'credit' => 'float',
        'is_valid'=>'string'
    ];
}
