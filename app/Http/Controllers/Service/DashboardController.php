<?php

namespace App\Http\Controllers\Service;

use App\Models\Master\Vehicle;
use App\Models\Service\Service;
use App\Models\Ops\Storing;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PagesHelp;
use Inventory;
use Accounting;
use App\Models\Accounting\Journal1;
use App\Models\Accounting\Journal2;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\View;
use PDF;

class DashboardController extends Controller
{
    public function checking(Request $request){
        $filter = isset($request->filter) ? $request->filter :'';
        $limit = $request->limit;
        $descending = $request->descending=="true";
        $sortBy = $request->sortBy;
        $pool_code = PagesHelp::PoolCode($request);
        $data=Vehicle::from('m_vehicle as a')
        ->selectRaw("a.vehicle_no,a.descriptions,a.police_no,b.doc_number,b.ref_date,b.driver_id,c.personal_name AS driver_name,d.route_name")
        ->leftjoin("t_operation as b",'a.last_operation','=','b.sysid')
        ->leftjoin('m_personal as c','b.driver_id','=','c.employee_id')
        ->leftjoin('m_bus_route as d','a.default_route_id','=','d.sysid')
        ->where('a.pool_code',$pool_code)
        ->where('a.is_active','1')
        ->where('a.vehicle_status',"Pengecekan");
        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where(function($q) use ($filter) {
                    $q->where('a.vehicle_no','like',$filter)
                    ->orwhere('a.descriptions','like',$filter)
                    ->orwhere('a.police_no','like',$filter)
                    ->orwhere('a.chasis_no','like',$filter)
                    ->orwhere('a.vin','like',$filter);
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
        return response()->success('Success',$data);
    }
    public function service(Request $request){
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending=="true";
        $sortBy = $request->sortBy;
        $pool_code = PagesHelp::PoolCode($request);
        $data=Service::from('t_workorder_service as a')
        ->selectRaw("a.sysid,a.doc_number,a.ref_date,a.vehicle_no,a.police_no,a.problem,a.service_type,
                    a.planning_date,a.planning_time,a.estimate_date,a.estimate_time")
        ->where('a.pool_code',$pool_code)
        ->where('a.is_closed',"0")
        ->where('a.is_cancel',"0");
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
    public function storing(Request $request){
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending=="true";
        $sortBy = $request->sortBy;
        $pool_code = PagesHelp::PoolCode($request);
        $data=Storing::from('t_storing as a')
        ->selectRaw("a.sysid,b.doc_number,a.ref_date,a.vehicle_no,a.police_no,a.problems,a.location")
        ->leftjoin('t_operation as b','a.sysid_operation','=','b.sysid')
        ->where('a.is_approved',"0")
        ->where('a.is_cancel',"0")
        ->where('b.is_cancel',"0")
        ->where('a.pool_code',$pool_code);
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

    public function cancel_storing(Request $request){
        $id=isset($request->sysid) ? $request->sysid:-1;
        DB::beginTransaction();
        try {
            $ctrl=Storing::where('sysid',$id);
            if (!($ctrl==null)) {
                Storing::where('sysid',$id)->update(['is_cancel'=>'1']);
                DB::commit();
                return response()->success('Success','Data Kontrol berhasil dihapus');
            } else {
                DB::rollback();
                return response()->error('',501,'Data tidak ditemukan');
            }
        } catch(Exception $e){
            DB::rollback();
            return response()->error('',501,$e);
        }
    }

    public function approved_storing(Request $request){
        $req= $request->json()->all();
        $sysid=$req['sysid'];
        DB::beginTransaction();
        try{
            $data=Storing::where('sysid',$sysid)->first();
            if (!($data)){
                DB::rollback();
                return response()->error('',501,'Data storing tidak ditemukan');
            }
            $service = Service::where('doc_number',$data->service_no)->first();
            $pool_code=PagesHelp::PoolCode($request);
            if ($service==null){
                $docservice=Service::GenerateNumber($pool_code,$data->ref_date);
                Service::insert([
                    'doc_number'=>$docservice,
                    'ref_date'=>$data->ref_date,
                    'ref_time'=>$data->ref_time,
                    'pool_code'=>$pool_code,
                    'vehicle_no'=>$data->vehicle_no,
                    'requester'=>'',
                    'problem'=>strip_tags($data->problems),
                    'status'=>'permintaan',
                    'police_no'=>$data->police_no,
                    'update_userid'=>'SYSTEM',
                    'update_timestamp'=>Date('Y-m-d'),
                    'service_type'=>'Storing'
                ]);
                } else {
                    $docservice=$data->service_no;
                    Service::where('doc_number',$data->service_no)->update([
                    'ref_date'=>$data->ref_date,
                    'ref_time'=>$data->ref_time,
                    'pool_code'=>$pool_code,
                    'vehicle_no'=>$data->vehicle_no,
                    'requester'=>'',
                    'problem'=>strip_tags($data->recomendation),
                    'status'=>'permintaan',
                    'police_no'=>$data->police_no,
                    'update_userid'=>'SYSTEM',
                    'update_timestamp'=>Date('Y-m-d'),
                    'service_type'=>'Storing'
                    ]);
                }
            Storing::where('sysid',$sysid)
            ->update(['is_approved'=>'1',
                      'service_no'=>$docservice]);
            DB::commit();
            return response()->success('Success','Simpan data Berhasil');
		} catch (Exception $e) {
            DB::rollback();
            return response()->error('',501,$e);
        }
    }
    public function state(Request $request){
        $pool_code=PagesHelp::PoolCode($request);
        $data['check']=  Vehicle::select('vehicle_no')
                ->where('pool_code',$pool_code)
                ->where('vehicle_status','Pengecekan')
                ->where('is_active',1)
                ->get()->count();
        $data['service']=  Service::select('doc_number')
                ->where('pool_code',$pool_code)
                ->where('is_closed','0')
                ->where('is_cancel','0')
                ->get()->count();
        $data['storing']=  Storing::from('t_storing as a')->selectRaw('a.sysid')
                ->leftjoin('t_operation as b','a.sysid_operation','=','b.sysid')
                ->where('b.pool_code',$pool_code)
                ->where('a.is_approved','0')
                ->where('a.is_cancel','0')
                ->get()->count();
        return response()->success('Success',$data);
    }

}
