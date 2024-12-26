<?php

namespace App\Models\Config;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class UsersPool extends Model
{
    protected $table = 'o_users_pool';
    protected $primaryKey = 'sysid';
    public $timestamps = true;
    const CREATED_AT = 'created_date';
    const UPDATED_AT = 'updated_date';
    protected $guarded = [];
    protected $casts=[
        'is_allow'=>'string',
        'is_read'=>'string'
    ];
}
