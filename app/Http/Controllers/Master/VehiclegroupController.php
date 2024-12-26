<?php

namespace App\Http\Controllers\Master;

use App\Models\Master\Vehiclegroup;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PagesHelp;

class VehiclegroupController extends Controller
{
    public function show(Request $request){
        $filter = $request->filter;
        $limit = $request->limit;
        $sorting = ($request->descending=="true") ? 'desc' :'asc';
        $sortBy = $request->sortBy;
        $data= Vehiclegroup::selectRaw("sysid,vehicle_type,descriptions,is_active");
        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where('vehicle_type','like',$filter)
               ->orwhere('descriptions','like',$filter);
        }
        $data=$data->orderBy($sortBy,$sorting)->paginate($limit);
        return response()->success('Success',$data);
    }

    public function destroy(Request $request){
        $sysid=$request->sysid;
        $data=Vehiclegroup::where('sysid',$sysid)->first();
        if ($data) {
            try{
                Vehiclegroup::where('sysid',$sysid)->delete();
                PagesHelp::write_log($request,$data->sysid,$data->vehicle_type,'Delete recods ['.$data->vehicle_type.'-'.$data->descriptions.']');
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
        $sysid=$request->sysid;
        $data=Vehiclegroup::selectRaw("sysid,vehicle_type,descriptions,is_active")->where('sysid',$sysid)->first();
        return response()->success('Success',$data);
    }
    public function post(Request $request){
        $data= $request->json()->all();
        $opr=$data['operation'];
        $where=$data['where'];
        $rec=$data['data'];
        $rec['update_userid']=PagesHelp::UserID($request);
        $rec['update_timestamp']=Date('Y-m-d H:i:s');
        DB::beginTransaction();
        try{
            if ($opr=='updated'){
                Vehiclegroup::where($where)
                    ->update($rec);
            } else if ($opr='inserted'){
                Vehiclegroup::insert($rec);
            }
            PagesHelp::write_log($request,-1,$rec['vehicle_type'],'Add/Update record ['.$rec['vehicle_type'].'-'.$rec['descriptions'].']');
            DB::commit();
            return response()->success('Success','Simpan data Berhasil');
		} catch (Exception $e) {
            DB::rollback();
            return response()->error('',501,$e);
        }
    }
    public function getvehiclegroup(){
        $data=Vehiclegroup::select('vehicle_type',DB::raw("CONCAT(vehicle_type,'-',descriptions) descriptions"))
             ->where('is_active','1')
             ->orderBy('vehicle_type','asc')
             ->get();
        return response()->success('Success',$data);
    }
}
