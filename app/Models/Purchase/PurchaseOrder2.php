<?php

namespace App\Models\Purchase;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrder2 extends Model
{
    protected $table = 't_purchase_order2';
    protected $primaryKey = 'sysid';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = [
        'qty_draft' => 'float',
        'qty_order'=>'float',
        'price' => 'float',
        'prc_discount1' => 'float',
        'prc_discount2' => 'float',
        'prc_tax' => 'float',
        'total' => 'float',
        'convertion'=>'float',
        'qty_request'=>'float',
        'current_stock'=>'float'
    ];

}
