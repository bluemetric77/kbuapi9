<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

class InPayment2 extends Model
{
    protected $table = 't_inpayment2';
    protected $casts = [
        'amount' => 'float',
        'discount' => 'float',
        'tax' => 'float',
        'total' => 'float',
        'paid' => 'float'
    ];
}
