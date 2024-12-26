<?php

namespace App\Http\Middleware;

use Illuminate\Support\Facades\DB;
use Closure;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Hash;

class Appauth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    protected $ignored_auth=false;

    public function handle($request, Closure $next)
    {
        $allowed = false;
        $jwt = $request->header('x_jwt');
        $ip  = request()->ip();
        $session_jwt=DB::table('o_session')
        ->selectRaw("sysid,now() as curr_time,expired_date,ip_number")
        ->where('hash_code',$jwt)
        ->where('ip_number',$ip)
        ->first();
        if ($session_jwt) {
            if ($session_jwt->curr_time>$session_jwt->expired_date){
                DB::table('o_session')->where('hash_code',$jwt)->delete();
                $message="token was expired, access dennied (APP-AUTH)";
            } else {
                $allowed= true;
            }
        } else {
            $message="token invalid, access dennied (APP-AUTH)";
        }
        if (($allowed==true) || ($this->ignored_auth==true)){
            return $next($request);
        } else {
            return response()->error('',401,$message);
        }
    }
}
