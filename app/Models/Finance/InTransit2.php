<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

class InTransit2 extends Model
{
    protected $table = 't_intransit2';
    protected $casts = [
        'amount' => 'float',
        'deposit'=> 'float'
    ];
}
