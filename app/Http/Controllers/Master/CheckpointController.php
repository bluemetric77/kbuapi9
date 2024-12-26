<?php

namespace App\Http\Controllers\Master;

use App\Models\Master\Checkpoint;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use PagesHelp;

class CheckpointController extends Controller
{
    public function show(Request $request){
        $filter = $request->filter;
        $limit = $request->limit;
        $sorting = ($request->descending=="true") ? 'desc' : 'asc';
        $sortBy = $request->sortBy;
        $data= Checkpoint::select('sysid','checkpoint_name','notes','latitude','longitude','is_active');
        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where('checkpoint_name','like',$filter);
        }
        $data=$data->orderBy($sortBy,$sorting)->paginate($limit);
        return response()->success('Success',$data);
    }

    public function destroy(Request $request){
        $id=$request->id;
        $data=Checkpoint::find($id);
        if ($data) {
            DB::beginTransaction();
            try{
                $data->delete();
                PagesHelp::write_log($request,$data->sysid,'','Delete recods ['.$data->checkpoint_name.']');
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
        $data=Checkpoint::find($id);
        return response()->success('Success',$data);
    }
    public function post(Request $request){
        $data= $request->json()->all();
        $opr=$data['operation'];
        $where=$data['where'];
        $rec=$data['data'];
        $validator=Validator::make($rec,[
            'checkpoint_name'=>'bail|required',
        ],[
            'checkpoint_name.required'=>'Lokasi checkpoint harus diisi',
        ]);

        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }
        DB::beginTransaction();
        try{
            if ($opr=='updated'){
                $sysid=-1;
                Checkpoint::where($where)
                    ->update($rec);
            } else if ($opr='inserted'){
                $sysid=$rec['sysid'];
                unset($rec['sysid']);
                Checkpoint::insert($rec);
            }
            PagesHelp::write_log($request,$sysid,'','Add/Update record ['.$rec['checkpoint_name'].']');
            DB::commit();
            return response()->success('Success','Simpan data Berhasil');
		} catch (Exception $e) {
            DB::rollback();
            return response()->error('',501,$e);
        }
    }
    public function getCheckpoint(Request $request){
        $pool=Checkpoint::select('sysid','checkpoint_name')
             ->where('is_active','1')
             ->get();
        $bank=array();
        $bank['sysid']=-1;
        $bank['checkpoint_name']='N/A';
        $pool[]=$bank ;
        return response()->success('Success',$pool);
    }
    public function getCheckpoint2(Request $request){
        $na=isset($request->isna) ? $request->isna:1;

        $pool=Checkpoint::select('sysid','checkpoint_name')
             ->where('is_active','1')
             ->get();
        $bank=array();
        if ($na==1){
            $bank['sysid']='';
            $bank['checkpoint_name']='N/A';
        } else {
            $bank['sysid']='';
            $bank['checkpoint_name']='Semua Pool';
        }
        $pool[]=$bank ;
        return response()->success('Success',$pool);
    }

}
