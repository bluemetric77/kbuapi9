<?php

namespace App\Http\Controllers\Ops;

use App\Models\Ops\sio1;
use App\Models\Ops\sio2;
use App\Models\Master\Driver;
use App\Models\Master\Vehicle;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PagesHelp;

class SioController extends Controller
{
    public function show(Request $request){
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending=="true";
        $sortBy = $request->sortBy;
        $pool_code=isset($request->pool_code) ? $request->pool_code: 'XXX';
        $date1 = $request->date1;
        $date2 = $request->date2;
        $data= sio1::select();
        $data=$data
           ->where('pool_code',$pool_code)
           ->where('ref_date','>=',$date1)
           ->where('ref_date','<=',$date2);
        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where(function($q) use ($filter) {
                    $q->where('doc_number','like',$filter)
                   ->orwhere('police_no','like',$filter)
                   ->orwhere('operation_note','like',$filter)
                   ->orwhere('driver_name','like',$filter);
            });
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
        $data->makeHidden(['work_order_no3','work_order_no4','work_order_no5','work_order_no6']);
        return response()->success('Success',$data);
    }

    public function destroy(Request $request){
        $id=$request->id;
        // return response()->error('',501,$id);
        DB::beginTransaction();
        try {
            $sio1=sio1::where('transid',$id);
            if (!($sio1==null)) {
                $sio1->delete();
                sio2::where('transid',$id)->delete();
                DB::commit();
                return response()->success('Success','Data SIO berhasil dihapus');
            } else {
                DB::rollback();
                return response()->error('',501,'Data tidak ditemukan');
            }
        } catch(Exception $e){
            DB::rollback();
            return response()->error('',501,$e);
        }
    }
    public function get(Request $request){
        $id=$request->transid;
        $data=sio1::where('transid',$id)->first();
        return response()->success('Success',$data);
    }
    public function post(Request $request){
        $data= $request->json()->all();
        $opr=$data['operation'];
        $where=$data['where'];
        $rec=$data['data'];
        DB::beginTransaction();
        try{
            $sysid=$rec['transid'];
            unset($rec['transid']);
            $driver=Driver::select('personal_id','employee_id','personal_name')
                ->where('employee_id',$rec['employee_id'])
                ->first();
            if ($driver){
                $rec['driver_id']=$driver->personal_id;
                $rec['driver_name']=$driver->personal_name;
            }
            $vehicle=Vehicle::select('police_no','descriptions')
                ->where('vehicle_no',$rec['vehicle_no'])
                ->first();
            if ($vehicle){
                $rec['police_no']=$vehicle->police_no;
                $rec['descriptions']=$vehicle->descriptions;
            }
            if ($opr=='updated'){
                sio1::where($where)
                    ->update($rec);
            } else if ($opr='inserted'){
                $number=sio1::GenerateNumber($rec['pool_code'],$rec['ref_date']);
                $rec['doc_number']=$number;
                $sysid=sio1::insertGetId($rec);
            }
            sio2::where('transid',$sysid)->delete();
            $recdetail=DB::table('m_fleet_checklist')
            ->select(DB::raw("$sysid as transid"),'id','group_code','group_name','sub_group','title','data_type')
            ->get();

            $detail = array();
            foreach($recdetail as $record) {
                $detail[] = (array)$record;
            }
            sio2::insert($detail);
            DB::commit();
            return response()->success('Success','Simpan data Berhasil');
		} catch (Exception $e) {
            DB::rollback();
            return response()->error('',501,$e);
        }
    }
    public function getsio_out(Request $request){
        $police_number=$request->police_number;
        $head=sio1::where('police_no',$police_number)->first();
        $detail=sio2::where('transid',$head->transid)->get();
        $data['head']=$head;
        $data['detail']=$detail;
        return response()->success('Success',$data);
    }

}
