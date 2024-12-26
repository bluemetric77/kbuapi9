<?php

namespace App\Models\Config;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class UsersCredential extends Model
{
    protected $table = 'o_users_credentials';
    protected $primaryKey = 'sysid';
    public $timestamps = true;
    const CREATED_AT = 'created_date';
    const UPDATED_AT = 'updated_date';
    protected $guarded = [];
}
