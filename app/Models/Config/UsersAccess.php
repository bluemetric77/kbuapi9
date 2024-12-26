<?php

namespace App\Models\Config;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class UsersAccess extends Model
{
    protected $table = 'o_users_access';
    public $timestamps = false;
    protected $guarded = [];
}
