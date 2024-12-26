<?php

namespace App\Models\Master;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class Partner extends Model
{
    protected $table = 'm_partner';
    // protected $primaryKey = 'partner_id';
    public $timestamps = true;
    const CREATED_AT = 'update_timestamp';
    const UPDATED_AT = 'update_timestamp';
    protected $casts = [
        'is_active'=>'string',
        'is_document'=>'string',
        'update_timestamp'=>'datetime:Y-m-d H:i:s'

    ];
}
