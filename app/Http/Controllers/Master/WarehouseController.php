<?php

namespace App\Http\Controllers\Master;

use App\Models\Master\Warehouse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use PagesHelp;

class WarehouseController extends Controller
{
    public function show(Request $request){
        $filter = $request->filter;
        $limit = $request->limit;
        $sorting = ($request->descending=="true") ?'desc':'asc';
        $sortBy = $request->sortBy;
        $data= Warehouse::selectRaw("warehouse_id,descriptions,is_allow_receive,is_allow_transfer,is_auto_transfer,is_active,warehouse_type");
        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where('warehouse_id','like',$filter)
               ->orwhere('descriptions','like',$filter);
        }
        $data=$data->orderBy($sortBy,$sorting)->paginate($limit);
        return response()->success('Success',$data);
    }

    public function destroy(Request $request){
        $id=$request->id;
        $data=Warehouse::where('warehouse_id',$id)->first();
        if ($data) {
            DB::beginTransaction();
            try{
                Warehouse::where('warehouse_id',$id)->delete();
                PagesHelp::write_log($request,-1,$data->warehouse_id,'Delete recods ['.$data->warehouse_id.'-'.$data->descriptions.']');
                DB::commit();
                return response()->success('Success','Data berhasil dihapus');
            } catch (Exception $e) {
                DB::rollback();
                return response()->error('',501,$e);
            }
        } else {
           return response()->error('',501,'Data tidak ditemukan');
        }
    }

    public function get(Request $request){
        $id=$request->id;
        $data=Warehouse::selectRaw("warehouse_id,descriptions,is_allow_receive,is_allow_transfer,is_auto_transfer,is_active,warehouse_type")
        ->where('warehouse_id',$id)->first();
        return response()->success('Success',$data);
    }
    public function post(Request $request){
        $data= $request->json()->all();
        $opr=$data['operation'];
        $where=$data['where'];
        $rec=$data['data'];
        $validator=Validator::make($rec,[
            'warehouse_id'=>'bail|required',
            'descriptions'=>'bail|required',
            'warehouse_type'=>'bail|required'
        ],[
            'warehouse_id.required'=>'Kode gudang harus diisi',
            'descriptions.required'=>'Nama gudang harus diisi',
            'warehouse_type.required'=>'Jenis gudang harus diisi'
        ]);
        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }
        $rec['update_timestamp']=Date('Y-m-d H:i:s');
        DB::beginTransaction();
        try{
            if ($opr=='updated'){
                Warehouse::where($where)
                    ->update($rec);
            } else if ($opr='inserted'){
                Warehouse::insert($rec);
            }
            PagesHelp::write_log($request,-1,$rec['warehouse_id'],'Add/Update record ['.$rec['warehouse_id'].'-'.$rec['descriptions'].']');
            DB::commit();
            return response()->success('Success','Simpan data Berhasil');
		} catch (Exception $e) {
            DB::rollback();
            return response()->error('',501,$e);
        }
    }
    public function getWarehouse(Request $request){
        $isna=isset($request->isna) ? $request->isna :'1';
        $all=isset($request->all) ? $request->all :'0';
        $warehouse=Warehouse::select('warehouse_id',DB::raw('CONCAT(warehouse_id, " - ", descriptions) AS descriptions'))
             ->where('is_active','1')
             ->get();
        if ($isna){
            $row=array();
            $row['warehouse_id']='N/A';
            $row['descriptions']='N/A';
            $warehouse[]=$row ;
        }
        if ($all=='1') {
            $allcode=array();
            $allcode['warehouse_id']='ALL';
            $allcode['descriptions']='ALL - SEMUA GUDANG';
            $warehouse[]=$allcode ;
        }
        return response()->success('Success',$warehouse);
    }
}
