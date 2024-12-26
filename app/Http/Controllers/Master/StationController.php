<?php

namespace App\Http\Controllers\Master;

use App\Models\Master\Station;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use PagesHelp;

class StationController extends Controller
{
    public function show(Request $request){
        $filter = $request->filter;
        $limit = $request->limit;
        $sorting = ($request->descending=="true")  ?'desc' :'asc';
        $sortBy = $request->sortBy;
        $data= Station::select('sysid','station_id','station_name','city','state','latitude','longitude','is_active');
        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where('station_id','like',$filter)
               ->orwhere('station_name','like',$filter);
        }
        $data=$data->orderBy($sortBy,$sorting)->paginate($limit);
        return response()->success('Success',$data);
    }

    public function destroy(Request $request){
        $id=$request->id;
        $data=Station::find($id);
        if ($data) {
            DB::beginTransaction();
            try{
                Station::find($id)->delete();
                PagesHelp::write_log($request,$data->sysid,$data->station_id,'Delete recods ['.$data->station_id.'-'.$data->station_name.']');
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
        $data=Station::find($id);
        return response()->success('Success',$data);
    }
    public function post(Request $request){
        $data= $request->json()->all();
        $opr=$data['operation'];
        $where=$data['where'];
        $rec=$data['data'];
        $validator=Validator::make($rec,[
            'station_id'=>'bail|required',
            'station_name'=>'bail|required',
        ],[
            'station_id.required'=>'Kode terminal diisi',
            'station_name.required'=>'Nama terminal harus diisi',
        ]);
        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }
        DB::beginTransaction();
        try{
            if ($opr=='updated'){
                Station::where($where)
                    ->update($rec);
            } else if ($opr='inserted'){
                unset($rec['sysid']);
                Station::insert($rec);
            }
            PagesHelp::write_log($request,-1,$rec['station_id'],'Add/Update record ['.$rec['station_id'].'-'.$rec['station_name'].']');
            DB::commit();
            return response()->success('Success','Simpan data Berhasil');
		} catch (Exception $e) {
            return response()->error('',501,$e);
        }
    }
    public function getStation(Request $request){
        $pool=Station::select('sysid',DB::raw('CONCAT(station_id, " - ", station_name) AS station_name'))
             ->where('is_active','1')
             ->get();
        $bank=array();
        $bank['sysid']=-1;
        $bank['station_name']='N/A';
        $pool[]=$bank ;
        return response()->success('Success',$pool);
    }
    public function getStation2(Request $request){
        $na=isset($request->isna) ? $request->isna:1;

        $pool=Pools::select('station_id',DB::raw('CONCAT(station_id, " - ", station_name) AS station_name'))
             ->where('is_active','1')
             ->get();
        $bank=array();
        if ($na==1){
            $bank['station_id']='';
            $bank['station_name']='N/A';
        } else {
            $bank['station_id']='';
            $bank['station_name']='Semua Terminal';
        }
        $pool[]=$bank ;
        return response()->success('Success',$pool);
    }

}
