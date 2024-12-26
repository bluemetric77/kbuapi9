<?php
namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

class ItemMutationYearly extends Model
{
    protected $table = 't_item_mutation_yearly';
    public $timestamps = false;
    protected $casts = [
        'price' => 'float',
        'discount'=>'float',
        'prc_discount'=>'float',
        'prc_tax'=>'float'
    ];

}
