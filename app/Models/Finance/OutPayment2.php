<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

class OutPayment2 extends Model
{
    protected $table = 't_outpayment2';
    protected $casts = [
        'amount' => 'float',
        'discount' => 'float',
        'tax' => 'float',
        'total' => 'float',
        'paid' => 'float'
    ];
}
