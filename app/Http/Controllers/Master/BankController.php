<?php

namespace App\Http\Controllers\Master;

use App\Models\Master\Bank;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PagesHelp;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BankController extends Controller
{
    public function show(Request $request){
        $filter = $request->filter;
        $limit = $request->limit;
        $sorting = ($request->descending=="true") ? 'desc' :'asc';
        $sortBy = $request->sortBy;
        $data= Bank::select('sysid','descriptions','bank_account','account_name','account_number','saldo',
          'last_transaction','is_active','no_account','voucher_in','voucher_out','voucher_ge','is_recorded');
        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where('descriptions','like',$filter)
               ->orwhere('bank_account','like',$filter)
               ->orwhere('account_name','like',$filter)
               ->orwhere('account_number','like',$filter);
        }
        $data=$data->orderBy($sortBy,$sorting)->paginate($limit);
        return response()->success('Success',$data);
    }

    public function destroy(Request $request){
        $id=$request->sysid;
        $data=Bank::selectRaw("sysid,descriptions,bank_name,account_name,account_number,is_active,voucher_in,voucher_out,
        voucher_ge,is_recorded,pool_code")->where('sysid',$id)->first();
        if ($data) {
            DB::beginTransaction();
            try{
                Bank::where('sysid',$id)->delete();
                PagesHelp::write_log($request,$data->sysid,'','Delete recods ['.$data->descriptions.']');
                DB::commit();
                return response()->success('Success','Data berhasil dihapus');
            } catch(Exception $e){
                DB::rollback();
                return response()->error('',501,$e);
            }
        } else {
           return response()->error('',501,'Data tidak ditemukan');
        }
    }
    public function get(Request $request){
        $id=$request->sysid;
        $data=Bank::find($id);
        return response()->success('Success',$data);
    }
    public function post(Request $request){
        $data= $request->json()->all();
        $opr=$data['operation'];
        $where=$data['where'];
        $rec=$data['data'];
        $validator=Validator::make($rec,[
            'descriptions'=>'bail|required',
            'bank_account'=>'bail|required',
            'account_name'=>'bail|required',
            'account_number'=>'bail|required'
        ],[
            'descriptions.required'=>'Nama kas/bank harus diisi',
            'bank_account.required'=>'Nama Bank harus diisi',
            'account_name.required'=>'Nama akun harus diisi',
            'account_number.required'=>'Nomor akun harus diisi',
        ]);

        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }

        DB::beginTransaction();
        try{
            $sysid=$rec['sysid'];
            if ($opr=='updated'){
                Bank::where($where)
                    ->update($rec);
            } else if ($opr='inserted'){
                unset($rec['sysid']);
                Bank::insert($rec);
            }
            PagesHelp::write_log($request,$sysid,'','Add/Update record ['.$rec['descriptions'].']');
            DB::commit();
            return response()->success('Success','Simpan data Berhasil');
		} catch (Exception $e) {
            DB::rollback();
            return response()->error('',501,$e);
        }
    }
    public function getBank(Request $request){
        $all=isset($request->all) ? $request->all :0;
        $data=Bank::selectRaw("sysid,CONCAT(descriptions,'-',IFNULL(account_number,'')) as descriptions,voucher_in,voucher_out")
             ->where('is_active','1')
             ->orderby('descriptions')
             ->get();
        if ($all=='1') {
            $allcode=array();
            $allcode['sysid']=99999;
            $allcode['descriptions']='ALL - SEMUA KAS/BANK';
            $allcode['voucher_in']='';
            $allcode['voucher_out']='';
            $data[]=$allcode ;
        }

        return response()->success('Success',$data);
    }
}
