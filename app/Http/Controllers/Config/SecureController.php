<?php

namespace App\Http\Controllers\Config;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Config\Users;
use Illuminate\Support\Facades\Hash;

class SecureController extends Controller
{
    public function Verified(Request $request){
        $jwt = $request->jwt;
        $objects = $request->objects;
        $session_jwt=DB::table('o_session')
        ->selectRaw("sysid,now() as curr_time,user_id,expired_date,refresh,is_locked,pool_code")
        ->where('hash_code',$jwt)->first();
        $data= array();
        if ($session_jwt) {
            if ($session_jwt->curr_time>$session_jwt->expired_date){
                $data['is_login']=false;
                $data['is_allowed']=false;
                $data['is_locked'] = ($session->is_locked==1);
                DB::table('o_session')->where('jwt',$jwt)->delete();
                   return response()->error('Unallowed page',301,$data);
            } else {
                $data['is_login']=true;
                $data['is_allowed']=false;
                $data['is_locked'] = ($session_jwt->is_locked==1);
                if ($session_jwt->curr_time>$session_jwt->refresh){
                    $hash_code=Hash::make($session_jwt->user_id.'##'.$session_jwt->pool_code.'@@S4ltedRose'.Date('YmdHis'));
                    $refresh_date =  date('Y-m-d H:i:s', strtotime('+30 minutes'));
                    $data['new_jwt'] = $hash_code;
                    DB::table('o_session')
                    ->where('sysid',$session_jwt->sysid)
                    ->update(['hash_code'=>$hash_code,
                              'refresh'=>$refresh_date]);
                }
                if (!($objects=="/")){
                    $user=Users::where('user_id',$session_jwt->user_id)->first();
                    if ($user){
                        if (!($user->user_level=='USER')) {
                            $data['is_allowed']=true;
                        } else {
                            if (UserObjects::from('o_users_access as a')
                            ->join('o_object_items as b',"a.object_id","=","b.id")
                            ->where("a.sysid",$user->$sysid)
                            ->where("b.url_link",$objects)->exist()){
                                $data['is_allowed']=true;
                            }
                        }
                    }
                } else {
                    $data['is_allowed']=true;
                }
                return response()->success('Allowed page',$data);
            }
        } else {
            $data['allowed']=false;
            return response()->error('Unallowed page',301,$data);
        }
    }

    public function getSecurityForm(Users $user, Request $request){
        $jwt = $request->jwt;
        $usrinfo=$user->info($jwt);
        if ($usrinfo) {
            $sysid=$usrinfo->sysid;
            $security_level=$usrinfo->security_level;
            if ($security_level=='USER'){
                $item = DB::table('o_object_items')
                ->select('id','group_id','title','image','objectid','icon','hints','url_link')
                ->where('is_header','0')
                ->distinct()
                ->get();
            } else {
                $item = DB::table('o_object_items')
                ->select('id','group_id','title','image','objectid','icon','hints','url_link')
                ->where('is_header','0')
                ->distinct()
                ->get();
            }
            return response()->success('Success',$item);
        }
    }
}
