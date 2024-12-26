<?php

namespace App\Http\Controllers\Config;

use App\Models\Master\Pools;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PagesHelp;
use App\Models\Config\Users;

class CompanyController extends Controller
{
    public function CompanyProfile(){
        $company = DB::table('o_system')
         ->select('key_word','key_value_nvarchar')
         ->whereRaw("LEFT(key_word,7) = 'PROFILE'")
         ->get();
         foreach ($company as $row) {
            $profile[strtolower(substr($row->key_word,8,10))]=$row->key_value_nvarchar;
         }
         //$data['data']=$profile;
        return response()->success('Success',$profile);
    }

    public function getPool(Request $request){
        $jwt = $request->header('x_jwt');
        $session=DB::table('o_session')->selectRaw("user_id")->where('hash_code',$jwt)->first();
        $userid=isset($session->user_id) ? $session->user_id :'';
        $all=isset($request->all) ? $request->all : '0';
        $data=DB::table('o_users_pool as a')
            ->selectRaw("a.pool_code,CONCAT(a.pool_code, ' - ', b.descriptions) AS descriptions")
            ->join('m_pool as b','a.pool_code','=','b.pool_code')
             ->where('a.user_id',$userid)
             ->where('a.is_allow','1')
             ->get();
        $count=Pools::count();
        $allow_all=$count==count($data);
        if (($all=='1') && ($allow_all==true)) {
            $allcode=array();
            $allcode['pool_code']='ALL';
            $allcode['descriptions']='ALL - SEMUA POOL';
            $data[]=$allcode ;
        }
        return response()->success('Success',$data);
    }

}
