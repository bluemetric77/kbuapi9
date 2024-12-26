<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

class Deposit2 extends Model
{
    protected $table = 't_deposit2';
    protected $casts = [
        'amount' => 'float',
        'paid' => 'float'
    ];
}
