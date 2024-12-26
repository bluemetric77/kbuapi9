<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

class ItemPartner extends Model
{
    protected $table = 'm_item_partner';
    protected $primaryKey = 'sysid';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = [
        'price' => 'float',
        'discount'=>'float',
        'prc_discount'=>'float',
        'prc_tax'=>'float'
    ];

}
