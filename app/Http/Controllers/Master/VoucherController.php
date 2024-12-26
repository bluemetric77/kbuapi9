<?php

namespace App\Http\Controllers\Master;

use App\Models\Master\Voucher;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PagesHelp;
use Illuminate\Support\Facades\Validator;

class VoucherController extends Controller
{
    public function show(Request $request){
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending=="true";
        $sortBy = $request->sortBy;
        $data= Voucher::select();
        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where('voucher_code','like',$filter)
               ->orwhere('descriptions','like',$filter);
        }
        if (!($sortBy=='')) {
            if ($descending) {
                $data=$data->orderBy($sortBy,'desc')->paginate($limit);
            } else {
                $data=$data->orderBy($sortBy,'asc')->paginate($limit);
            }
        } else {
            $data=$data->paginate($limit);
        }
        return response()->success('Success',$data);
    }

    public function destroy(Request $request){
        $id=$request->id;
        $data=Voucher::where('voucher_code',$id)->first();
        if ($data) {
            DB::beginTransaction();
            try{
                Voucher::where('voucher_code',$id)->delete();
                PagesHelp::write_log($request,-1,$data->voucher_code,'Delete recods ['.$data->voucher_code.'-'.$data->descriptions.']');
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
        $id=$request->id;
        $data=Voucher::selectRaw("voucher_code,descriptions,is_active")->where('voucher_code',$id)->first();
        return response()->success('Success',$data);
    }
    public function post(Request $request){
        $data= $request->json()->all();
        $opr=$data['operation'];
        $where=$data['where'];
        $rec=$data['data'];
        $validator=Validator::make($rec,[
            'voucher_code'=>'bail|required',
            'descriptions'=>'bail|required',
        ],[
            'voucher_code.required'=>'Kode voucher jurnal harus diisi',
            'descriptions.required'=>'Nama voucher jurnal harus diisi',
        ]);

        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }

        DB::beginTransaction();
        try{
            if ($opr=='updated'){
                Voucher::where($where)
                    ->update($rec);
            } else if ($opr='inserted'){
                Voucher::insert($rec);
            }
            PagesHelp::write_log($request,-1,$rec['voucher_code'],'Add/Update recod ['.$rec['voucher_code'].'-'.$rec['descriptions'].']');
            DB::commit();
            return response()->success('Success','Simpan data Berhasil');
		} catch (Exception $e) {
            DB::rollback();
            return response()->error('',501,$e);
        }
    }
    public function getVoucher(Request $request){
        $pool=Voucher::select('voucher_code',DB::raw('CONCAT(voucher_code, " - ", descriptions) AS descriptions'))
             ->where('is_active','1')
             ->get();
        return response()->success('Success',$pool);
    }
    public function getJurnaltype(Request $request){
        $pool=DB::table('m_jurnal_type')->select('jurnal_type','descriptions')
             ->where('is_disable','0')
             ->get();
        return response()->success('Success',$pool);
    }


}
