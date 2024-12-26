<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

class OpsUnpaid extends Model
{
    protected $table = 't_operation_unpaid';
    protected $casts = [
        'unpaid' => 'float',
        'paid' => 'float',
        'is_paid' => 'string'
    ];
}
