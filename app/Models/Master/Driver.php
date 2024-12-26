<?php

namespace App\Models\Master;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    protected $table = 'm_personal';
    protected $primaryKey = 'personal_id';
    public $timestamps = false;
    const CREATED_AT = 'update_timestamp';
    const UPDATED_AT = 'update_timestamp';
    protected $casts = [
        'is_active'=>'string',
        'is_crew'=>'string',
        'on_duty'=>'string',
        'update_timestamp'=>'datetime:Y-m-d H:i:s'
    ];
}
