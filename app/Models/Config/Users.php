<?php

namespace App\Models\Config;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class Users extends Model
{
    protected $table = 'o_users';
    protected $primaryKey = 'sysid';
    public $timestamps = true;
    const CREATED_AT = 'create_date';
    const UPDATED_AT = 'update_date';
    protected $guarded = [];
    protected $casts=[
        'is_suspend'=>'string',
        'is_group'=>'string'
    ];

    public static function info($jwt){
        $ses=DB::table('o_session')->select('user_id')->where('hash_code',$jwt)->first();
        $user = Users::selectRaw('sysid,user_id,user_name,password,password2,email,phone,security_level,is_suspend,
        is_group,security_group,failed_attemp,attemp_lock,ip_number,photo,sign')
        ->where('user_id', isset($ses->user_id) ?$ses->user_id :'')
        ->first();
        return $user;
    }

}
