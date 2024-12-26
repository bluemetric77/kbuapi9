<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

class PaymentSubmission2 extends Model
{
    protected $table = 't_payment_submission2';
    protected $guarded=[];
    protected $casts = [
        'total' => 'float',
        'paid' => 'float',
        'payment' => 'float'
    ];
}
