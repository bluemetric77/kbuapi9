<?php

namespace App\Models\Purchase;

use Illuminate\Database\Eloquent\Model;

class JobInvoice2 extends Model
{
    protected $table = 't_job_invoice2';
    protected $primaryKey = 'sysid';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = [
        'qty_invoice'=>'float',
        'price' => 'float',
        'discount' => 'float',
        'total' => 'float'
    ];

}
