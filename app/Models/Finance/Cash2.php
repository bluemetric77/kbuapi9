<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

class Cash2 extends Model
{
    protected $table = 't_cash_bank2';
    protected $casts = [
        'amount' => 'float'
    ];
}
