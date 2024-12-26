<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

class PurchaseRequest2 extends Model
{
    protected $table = 't_purchase_request2';
    public $timestamps = false;
    protected $casts = [
        'qty_order' => 'float',
        'qty_invoice'=>'float',
        'purchase_price' => 'float',
        'prc_discount1' => 'float',
        'prc_discount2' => 'float',
        'prc_tax' => 'float',
        'total' => 'float'
    ];
}
