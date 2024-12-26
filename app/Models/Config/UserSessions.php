<?php

namespace App\Models\Config;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class UserSessions extends Model
{
    protected $table = 'o_session';
    protected $primaryKey = 'sysid';
    public $timestamps = false;
    protected $casts=[
        'is_deleted'=>'string',
        'ignored'=>'string',
        'is_locked'=>'string'
    ];

}
