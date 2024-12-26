<?php

namespace App\Models\Ops;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Pricesetup extends Model
{
    protected $table = 'm_customer_price';
    public $timestamps = true;
    protected $hidden=['db_version','app_version','update_userid','update_timestamp','update_location'];
    const CREATED_AT = 'update_timestamp';
    const UPDATED_AT = 'update_timestamp';
    protected $casts = [
        'standart_price'=>'float',
        'other_fee'=>'float'
    ];
}
