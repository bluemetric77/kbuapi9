<?php

namespace App\Http\Controllers\Master;

use App\Models\Master\Others;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use PagesHelp;


class OthersController extends Controller
{
    public function show(Request $request){
        $filter = $request->filter;
        $limit = $request->limit;
        $sorting = ($request->descending=="true") ? 'desc' :'asc';
        $sortBy = $request->sortBy;
        $data= Others::from('m_others_item as a')->select('a.sysid','a.item_code','a.item_name','a.amount','a.is_active',DB::raw("CONCAT(b.descriptions,'-',IFNULL(b.account_number,'N/A')) as descriptions"))
        ->leftjoin('m_cash_operation as b','a.bank_id','=','b.sysid');
        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where('a.item_name','like',$filter)
               ->orwhere('a.descriptions','like',$filter);
        }
        $data=$data->orderBy($sortBy,$sorting)->paginate($limit);
        return response()->success('Success',$data);
    }

    public function destroy(Request $request){
        $id=$request->id;
        $data=Others::find($id);
        if ($data) {
            DB::beginTransaction();
            try{
                Others::find($id)->delete();
                PagesHelp::write_log($request,$data->sysid,$data->item_code,'Delete recods ['.$data->item_code.'-'.$data->item_name.']');
                DB::commit();
                return response()->success('Success','Data berhasil dihapus');
            } catch(Exception $e) {
                DB::rollback();
               return response()->error('',501,$e);
            }
        } else {
           return response()->error('',501,'Data tidak ditemukan');
        }
    }
    public function get(Request $request){
        $id=$request->id;
        $data=Others::find($id);
        return response()->success('Success',$data);
    }
    public function post(Request $request){
        $data= $request->json()->all();
        $opr=$data['operation'];
        $where=$data['where'];
        $rec=$data['data'];
        $rec['update_timestamp']=Date('Y-m-d H:i:s');
        $validator=Validator::make($rec,[
            'item_code'=>'bail|required',
            'item_name'=>'bail|required'
        ],[
            'pool_code.required'=>'Kode harus diisi',
            'descriptions.required'=>'Nama komponen lain2 harus diisi'
        ]);
        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }
        DB::beginTransaction();
        try{
            if ($opr=='updated'){
                $sysid=$rec['sysid'];
                Others::where($where)
                    ->update($rec);
            } else if ($opr='inserted'){
                $sysid=-1;
                unset($rec['sysid']);
                Others::insert($rec);
            }
            PagesHelp::write_log($request,$sysid,$rec['item_code'],'Add/Update record ['.$rec['item_code'].'-'.$rec['item_name'].']');
            DB::commit();
            return response()->success('Success','Simpan data Berhasil');
		} catch (Exception $e) {
            DB::rolllback();
            return response()->error('',501,$e);
        }
    }
    public function getOthers(Request $request){
        $pool=Others::select('item_code',DB::raw('CONCAT(item_code, " - ", item_name) AS item_name'))
             ->where('is_active','1')
             ->get();
        return response()->success('Success',$pool);
    }
}
