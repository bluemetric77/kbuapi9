<?php

namespace App\Models\Config;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class UserLogs extends Model
{
    protected $table = 't_user_logs';
    protected $primaryKey = 'sysid';
    public $timestamps = true;
    const CREATED_AT = 'create_date';
    const UPDATED_AT = 'update_date';
    protected $casts=[
        'is_suspend'=>'string',
        'is_group'=>'string'
    ];

}
