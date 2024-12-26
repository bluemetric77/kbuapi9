<?php

namespace App\Http\Controllers\Service;

use App\Models\Service\ServicePeriodic;
use App\Models\Service\ServicePeriodicJob;
use App\Models\Service\ServicePeriodicDtl;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PeriodicController extends Controller
{
    public function show(Request $request){
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending=="true";
        $sortBy = $request->sortBy;
        $data= ServicePeriodic::selectRaw('sysid,service_code,odometer,descriptions,job_count');
        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where('descriptions','like',$filter);
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
        $sysid=isset($request->sysid) ? $request->sysid : -1;
        DB::beginTransaction();
        try{
            ServicePeriodic::where('sysid',$sysid)->delete();
            ServicePeriodicJob::where('sysid',$sysid)->delete();
            ServicePeriodicDtl::where('sysid',$sysid)->delete();
            DB::commit();
            return response()->success('Success', 'Data berhasil dihapus');
		} catch (Exception $e) {
            DB::rollback();
            return response()->error('',501,$e);
        }
    }
    public function get(Request $request){
        $id=$request->id;
        $header=ServicePeriodic::selectRaw('sysid,service_code,odometer,descriptions,job_count')->where('sysid',$id)->first();
        $detail=ServicePeriodicJob::where('sysid',$id)->get();
        $job=ServicePeriodicDtl::where('sysid',$id)
        ->orderby('group_line','asc')
        ->orderby('item_line','asc')
        ->get();
        $data['header']=$header;
        $data['detail']=$detail;
        $data['job']=$job;
        return response()->success('Success',$data);
    }
    public function post(Request $request){
        $data= $request->json()->all();
        $opr=$data['operation'];
        $where=$data['where'];
        $rec=$data['data'];
        $detail=$data['detail'];
        $job=$data['job'];
        DB::beginTransaction();
        try{
            $sysid=$rec['sysid'];
            $rec['job_count']=count($detail);
            unset($rec['sysid']);
            if ($opr=='updated'){
                ServicePeriodic::where($where)
                    ->update($rec);
                ServicePeriodicJob::where('sysid',$sysid)->delete();
                ServicePeriodicDtl::where('sysid',$sysid)->delete();
            } else if ($opr='inserted'){
                $sysid=ServicePeriodic::insertGetId($rec);
            }
            foreach($detail as $record) {
                $dtl=(array)$record;
                $dtl['sysid']=$sysid;
                ServicePeriodicJob::insert($dtl);
            }
            foreach($job as $record) {
                $dtl=(array)$record;
                $dtl['sysid']=$sysid;
                ServicePeriodicDtl::insert($dtl);
            }
            DB::commit();
            return response()->success('Success', 'Simpan data Berhasil');
		} catch (Exception $e) {
            DB::rollback();
            return response()->error('',501,$e);
        }
    }
    public function getPeriodic(Request $request){
        $data= ServicePeriodic::selectRaw('sysid,odometer,descriptions')->get();
        return response()->success('Success',$data);
    }
}
