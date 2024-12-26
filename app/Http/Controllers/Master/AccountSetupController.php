<?php

namespace App\Http\Controllers\Master;

use App\Models\Master\Account;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PagesHelp;

class AccountSetupController extends Controller
{
    public function show(Request $request){
        $data=DB::table('o_account_general')
        ->where('id',1)
        ->first();
        $return['account']=$data;
        $account_name=array();
        foreach ($data as $key => $value) {
            if (($value==null) || ($value=='')){
              $account_name[$key]=$value;
            } else {
              $account=Account::select('account_name')
                ->where('account_no',$value)
                ->first();
              if ($account) {
                $account_name[$key]=$value.' - '.$account['account_name'];
              }
            }
        }
        $return['account_name']=$account_name;
        return response()->success('Success',$return);
    }

    public function post(Request $request){
        $data= $request->json()->all();
        $opr=$data['operation'];
        $where=$data['where'];
        $rec=$data['data'];
        unset($rec['id']);
        try{
            DB::table('o_account_general')
                ->where('id',1)
                ->update($rec);
            return response()->success('Success','Simpan data Berhasil');
		} catch (Exception $e) {
            return response()->error('',501,$e);
        }
    }

    public function cashlink(Request $request){
        $cashid= $request->cash_id;
        $data= DB::table('m_cash_operation_link as a')
            ->select('a.sysid','a.account_no','c.account_name','b.descriptions',
            'a.update_userid','a.update_timestamp')
            ->Join('m_cash_operation as b','b.sysid','=','a.sysid')
            ->leftJoin('m_account as c','c.account_no','=','a.account_no')
            ->where('a.link_id',$cashid);
        $data=$data->orderBy('a.sysid','asc')->paginate(50);
        return response()->success('Success',$data);
    }

    public function deletecashbank(Request $request){
        $link_id=$request->link_id;
        $cash_id=$request->cash_id;
        $data=DB::table('m_cash_operation_link')
          ->where('link_id',$link_id)
          ->where('sysid',$cash_id)
          ->delete();
        return response()->success('Success','Data berhasil dihapus');
    }

    public function postbank(Request $request){
        $data= $request->json()->all();
        $opr=$data['operation'];
        $where=$data['where'];
        $rec=$data['data'];
        unset($rec['account_name']);
        $rec['update_userid']=PagesHelp::UserID($request);
        $rec['update_timestamp']=Date('Y-m-d H:i:s');
        try{
            if ($opr=='updated'){
                DB::table('m_cash_operation_link')
                    ->where($where)
                    ->update($rec);
            } else if ($opr='inserted'){
                DB::table('m_cash_operation_link')
                    ->insert($rec);
            }
            return response()->success('Success','Simpan data Berhasil');
		} catch (Exception $e) {
            return response()->error('',501,$e);
        }
    }

}
