<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

class CustomerAccount extends Model
{
    protected $table = 't_customer_account';
    protected $primaryKey = 'sysid';
    public $timestamps = FALSE;
    protected $casts = [
        'amount' => 'float',
        'paid' => 'float',
        'unpaid' => 'float',
        'tax' => 'float',
        'discount' => 'float',
        'total_paid'=>'float',
        'is_approved'=>'string',
        'is_void'=>'string'
    ];
}
