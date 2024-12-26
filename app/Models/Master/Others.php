<?php

namespace App\Models\Master;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class Others extends Model
{
    protected $table = 'm_others_item';
    protected $primaryKey = 'sysid';
    public $timestamps = true;
    const CREATED_AT = 'update_timestamp';
    const UPDATED_AT = 'update_timestamp';
    protected $casts = [
        'is_active'=>'string',
        'amount'=>'float'
    ];
}
