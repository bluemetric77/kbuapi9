<?php

namespace App\Http\Controllers\Config;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Config\Users;
use App\Models\Config\Reports;
use PagesHelp;
use PDF;
use qrCode;

class HomeController extends Controller
{
    public function Datadef(Request $request){
        $id=$request->id;
        $jwt = $request->header('x_jwt');
        $id=$request->id;
        $session=DB::table('o_session')
            ->select('user_id','pool_code')
            ->where('hash_code',$jwt)->first();
        if ($session){
            $user=DB::table('o_users as a')->selectRaw("a.sysid,IFNULL(b.user_level,'USER') as security_level")
                ->join('o_users_pool as b',function($join){
                     $join->on("a.user_id","=","b.user_id");
                     $join->on("b.is_allow","=",DB::raw("'1'"));
                })
                ->where('a.user_id',$session->user_id)
                ->where('b.pool_code',$session->pool_code)
                ->first();
        } else {
            $user=null;
        }

        if ($user) {
            $user_sysid =$user->sysid;
            $id=$request->id;
            $item=PagesHelp::DataDef($id);
            $access=array();
            $action=json_decode($item->security,true);
            foreach ($action as $act) {
                $access[$act['action']]=true;
            }
            if ($user->security_level=='USER') {
                foreach ($action as $act) {
                    $access[$act['action']]=false;
                }
                $allowed=DB::table('o_users_access')->selectRaw("security")
                ->where('sysid',$user_sysid)
                ->where('object_id',$item->id)->first();
                $action=json_decode($allowed->security,true);
                foreach ($action as $act) {
                    $access[$act]=true;
                }
            }
            $item->{'access'}=$access;
            return response()->success('Success',$item);
        } else {
            return response()->error('',301,"Tidak ada akses");
        }
    }

    public function getItem(Request $request){
        $jwt = $request->jwt;
        $data=DB::table('o_session')
            ->select('user_id','pool_code')
            ->where('hash_code',$jwt)->first();
        if ($data) {
            $user_id=$data->user_id;
            $pool_code=$data->pool_code;
            $user=Users::from('o_users as a')
            ->selectRaw('a.sysid,b.pool_code,b.user_level')
            ->join('o_users_pool as b','a.user_id','=','b.user_id')
            ->where('a.user_id',$user_id)
            ->where('b.pool_code',$pool_code)
            ->first();
            if ($user){
                $sysid=$user->sysid;
                if ($user->user_level=='USER'){
                    $item = DB::table('o_object_items')
                    ->select('id','group_id','sort_number','level','title','image','objectid','icon','hints','url_link','is_header')
                    ->where('is_active',1)
                    ->whereIn('id',function ($query) use ($sysid,$pool_code){
                        $query->select('object_id')
                            ->from('o_users_access')
                            ->where('sysid', $sysid)
                            ->where('pool_code', $pool_code)
                            ->distinct()
                            ->get();
                    });
                    $item=$item
                        ->orwhere('is_header',1)
                        ->orwhere('sort_number',9001)
                        ->distinct()
                        ->orderBy('sort_number')
                        ->get();

                } else {
                    $item = DB::table('o_object_items')
                    ->select('id','group_id','sort_number','level','title','image','objectid','icon','hints','url_link','is_header')
                    ->where('is_active',1)
                    ->distinct()
                    ->orderBy('sort_number')
                    ->get();
                }
                return response()->success('Success',$item);
            } else {
            return response()->error('',301,"Tidak ada akses pool");
            }
        } else {
            return response()->error('',301,"Tidak ada akses");
        }
    }

    public function getReport(Request $request){
        $jwt = $request->jwt;
        $data=DB::table('o_session')
            ->select('user_id','pool_code')
            ->where('hash_code',$jwt)->first();
        if ($data) {
            $user_id=$data->user_id;
            $pool_code=$data->pool_code;
            $user=Users::from('o_users as a')
            ->selectRaw('a.sysid,b.pool_code,b.user_level')
            ->join('o_users_pool as b','a.user_id','=','b.user_id')
            ->where('a.user_id',$user_id)
            ->where('b.pool_code',$pool_code)
            ->first();
            if ($user){
                $sysid=$user->sysid;
                if ($user->user_level=='USER'){
                    $item = DB::table('o_reports')
                    ->selectRaw('id,level,sort_number,group_id,title,image,url_link,icon,notes,colidx,is_header,dialog_model')
                    ->where('group_id','<>',-1)
                    ->where(function($query) use ($sysid,$pool_code) {
                        $query->whereIn('id',function ($query) use ($sysid,$pool_code){
                            $query->select('report_id')
                                ->from('o_user_report')
                                ->where('sysid', $sysid)
                                ->where('pool_code', $pool_code)
                                ->where('is_allow', '1')
                                ->distinct()
                                ->get();
                        })
                        ->orwhere('is_header','1');
                    });
                    $item=$item
                    ->distinct()
                    ->orderBy('sort_number')
                    ->get();
                } else {
                    $item = DB::table('o_reports')
                    ->selectRaw('id,level,sort_number,group_id,title,image,url_link,icon,notes,colidx,is_header,dialog_model')
                    ->where('group_id','<>',-1)
                    ->distinct()
                    ->orderBy('sort_number')
                    ->get();
                }
                return response()->success('Success',$item);
            } else {
            return response()->error('',301,"Tidak ada akses pool");
            }
        } else {
            return response()->error('',301,"Tidak ada akses");
        }
    }
    public function setReport(Request $request){
        $uuid      = isset($request->uuid) ? $request->uuid :'';
        $pool_code = isset($request->pool_code) ? $request->pool_code :'';
        $user      = Users::where('uuid_rec',$uuid)->first();
        $sysid     = $user->sysid ?? -1;

        $item = Reports::from('o_reports as a')
                ->selectRaw('a.id,a.level,a.sort_number,a.group_id,a.title,a.colidx,a.is_header,
                IFNULL(b.is_allow,a.is_selected) as is_selected')
                ->leftjoin('o_user_report as b', function($join) use ($sysid,$pool_code)
                {
                    $join->on('a.id', '=', 'b.report_id');
                    $join->on('b.sysid', '=', DB::raw($sysid));
                    $join->on('b.pool_code','=',DB::raw("'$pool_code'"));
                })
                ->where('a.group_id','<>',-1)
                ->distinct()
                ->orderBy('a.sort_number')
                ->get();
        return response()->success('Success',$item);
    }
}
