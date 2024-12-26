<?php

namespace App\Models\Master;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class ItemsJob extends Model
{
    protected $table = 'm_item_job';
    public $timestamps = false;
    protected $casts = [
        'qty'=>'float',
        'price'=>'float',
        'total'=>'float'
    ];
}
