<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;

class Journal2 extends Model
{
    protected $table = 't_jurnal2';
    protected $guarded = [];
    protected $casts = [
        'debit' => 'float',
        'credit' => 'float',
        'is_verified'=>'string',
        'is_void'=>'string',
        'project'=>'string',
        'no_account'=>'string',
        'is_void'=>'string'
    ];
}
