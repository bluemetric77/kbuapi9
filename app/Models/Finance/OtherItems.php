<?php

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Model;

class OtherItems extends Model
{
    protected $table = 'm_others_item';
    protected $casts = [
        'amount' => 'float'
    ];
}
