<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

class ItemInvoice2 extends Model
{
    protected $table = 't_item_invoice2';
    protected $primaryKey = 'sysid';
    protected $guarded = [];
    public $timestamps = false;
    protected $casts = [
        'qty_order' => 'float',
        'qty_invoice'=>'float',
        'purchase_price' => 'float',
        'prc_discount1' => 'float',
        'prc_discount2' => 'float',
        'prc_tax' => 'float',
        'total' => 'float',
        'qty_retur'=>'float',
        'convertion'=>'float',
    ];

}
