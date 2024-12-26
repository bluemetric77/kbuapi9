<?php

namespace App\Http\Controllers\Master;

use App\Models\Master\Pools;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use PagesHelp;


class PoolController extends Controller
{
    public function show(Request $request){
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending=="true";
        $sortBy = $request->sortBy;
        $data= Pools::select('sysid','pool_code','descriptions','warehouse_code','project_code','cash_intransit','is_active');
        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where('pool_code','like',$filter)
               ->orwhere('descriptions','like',$filter);
        }
        if ($descending) {
            $data=$data->orderBy($sortBy,'desc')->paginate($limit);
        } else {
            $data=$data->orderBy($sortBy,'asc')->paginate($limit);
        }
        return response()->success('Success',$data);
    }

    public function destroy(Request $request){
        $id=$request->id;
        $data=Pools::find($id);
        if ($data) {
            DB::beginTransaction();
            try{
                Pools::find($id)->delete();
                PagesHelp::write_log($request,$data->sysid,$data->pool_code,'Delete recods ['.$data->pool_code.'-'.$data->descriptions.']');
                DB::commit();
                return response()->success('Success','Data berhasil dihapus');
            }catch(\Exception $e) {
               DB::rollback();
               return response()->error('',501,$e);
            }
        } else {
           return response()->error('',501,'Data tidak ditemukan');
        }
    }
    public function get(Request $request){
        $id=$request->id;
        $data=Pools::selectRaw("sysid,pool_code,descriptions,project_code,warehouse_code,cash_intransit,is_active")->find($id);
        return response()->success('Success',$data);
    }
    public function post(Request $request){
        $data= $request->json()->all();
        $opr=$data['operation'];
        $where=$data['where'];
        $rec=$data['data'];
        $validator=Validator::make($rec,[
            'pool_code'=>'bail|required',
            'descriptions'=>'bail|required',
            'warehouse_code'=>'bail|required'
        ],[
            'pool_code.required'=>'Kode harus diisi',
            'descriptions.required'=>'Nama Pool harus diisi',
            'warehouse_code.required'=>'Gudang terkait harus diisi'
        ]);
        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }

        $rec['update_userid']=PagesHelp::UserID($request);
        $rec['update_timestamp']=Date('Y-m-d H:i:s');
        DB::beginTransaction();
        try{
            if ($opr=='updated'){
                Pools::where($where)
                    ->update($rec);
            } else if ($opr='inserted'){
                unset($rec['sysid']);
                Pools::insert($rec);
            }
            PagesHelp::write_log($request,-1,$rec['pool_code'],'Add/Update record ['.$rec['pool_code'].'-'.$rec['descriptions'].']');
            DB::commit();
            return response()->success('Success','Simpan data Berhasil');
		} catch (Exception $e) {
            DB::rollback();
            return response()->error('',501,$e);
        }
    }
    public function getPool(Request $request){
        $na=isset($request->isna) ? $request->isna:1;
        $pool=Pools::selectRaw("sysid,pool_code,CONCAT(pool_code, ' - ', descriptions) AS descriptions")
             ->where('is_active','1')
             ->get();
        if ($na==1){
            $row=array();
            $row['sysid']=-1;
            $row['pool_code']='N/A';
            $row['descriptions']='N/A';
            $pool[]=$row ;
        }
        return response()->success('Success',$pool);
    }
    public function getPool2(Request $request){
        $na=isset($request->isna) ? $request->isna:1;

        $pool=Pools::selectRaw("sysid,pool_code,CONCAT(pool_code, ' - ', descriptions) AS descriptions")
             ->where('is_active','1')
             ->get();
        $bank=array();
        if ($na==1){
            $bank['pool_code']='';
            $bank['descriptions']='N/A';
        } else {
            $bank['pool_code']='';
            $bank['descriptions']='Semua Pool';
        }
        $pool[]=$bank ;
        return response()->success('Success',$pool);
    }
    public function getProject(Request $request){
        $data=DB::table('m_project')
        ->selectRaw("project_code,CONCAT(project_code,'-',project_name) as project_name")->get();
        return response()->success('Success',$data);
    }
}
