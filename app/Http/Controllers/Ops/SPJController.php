<?php

namespace App\Http\Controllers\Ops;

use App\Models\Ops\Operation;
use App\Models\Ops\OperationRoute;
use App\Models\Finance\OtherItems;
use App\Models\Ops\OperationUnpaid;
use App\Models\Ops\InTransit;
use App\Models\Master\Vehicle;
use App\Models\Master\Driver;
use App\Models\Master\Bank;
use App\Models\Config\Users;
use App\Models\Master\Busroute;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PagesHelp;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PDF;
use QrCode;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Writer as Writer;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Illuminate\Support\Facades\Log;

class SPJController extends Controller
{
    public function show(Request $request)
    {
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending == "true";
        $sortBy = $request->sortBy;
        $start_date = isset($request->start_date) ? $request->start_date : '1899-01-01';
        $end_date = isset($request->end_date) ? $request->end_date : '1899-01-01';
        $isactive = isset($request->isactive) ? $request->isactive :'0';
        $pool_code = PagesHelp::PoolCode($request);
        $data= Operation::from('t_operation as a')
        ->selectRaw("a.sysid,a.doc_number,a.ref_date,a.time_boarding,a.pool_code,a.vehicle_no,a.police_no,
            a.driver_id,c.personal_name AS driver,a.helper_id,d.personal_name AS helper,a.conductor_id,e.personal_name AS conductor,
            a.odometer,a.distance,a.is_change_route,a.passenger,a.model,
            a.route_id,b.route_name,a.route_default,f.route_name AS routing_default,
            a.is_closed,a.odometer,a.distance,a.rate,a.target,a.deposit,a.is_closed,a.is_ops_closed,a.dispensation,a.ks,a.paid,a.is_cancel,
            a.pool_entry_date,a.pool_entry_time")
        ->leftJoin('m_bus_route as b','a.route_id','=','b.sysid')
        ->leftJoin('m_personal as c','a.driver_id','=','c.employee_id')
        ->leftJoin('m_personal as d','a.helper_id','=','d.employee_id')
        ->leftJoin('m_personal as e','a.conductor_id','=','e.employee_id')
        ->leftJoin('m_bus_route as f','a.route_default','=','f.sysid')
        ->where('a.pool_code',$pool_code);
        if ($isactive=="1"){
            $data=$data->where('is_closed',0)
            ->where('is_cancel',0);
        } else {
            $data=$data->where('a.ref_date', '>=', $start_date)
            ->where('a.ref_date', '<=', $end_date);
        }
        if (!($filter == '')) {
            $filter = '%' . trim($filter) . '%';
            $data = $data->where(function ($q) use ($filter) {
                $q->where('a.doc_number', 'like', $filter)
                    ->orwhere('b.route_name', 'like', $filter)
                    ->orwhere('a.vehicle_no', 'like', $filter)
                    ->orwhere('a.police_no', 'like', $filter);
            });
        }
        if (!($sortBy == '')) {
            if ($descending) {
                $data = $data->orderBy($sortBy, 'desc')->paginate($limit);
            } else {
                $data = $data->orderBy($sortBy, 'asc')->paginate($limit);
            }
        } else {
            $data = $data->paginate($limit);
        }
        return response()->success('Success', $data);
    }

    public function cancel(Request $request)
    {
        $data = $request->json()->all();
        $where = $data['where'];
        $rec=$data['data'];
        $id=$where['sysid'];
        $data = Operation::selectRaw('doc_number,is_cancel,is_closed,is_ops_closed,ref_date')->where('sysid', $id)->first();
        $slimit=Date('Y-m-d');
        $limit=date_create($slimit);
        $limit=$limit->modify("-2 day");
        /*if ($rec['pool_code']=='SKBM') {
            $limit=$limit->modify("-365 day");
        } else {
            $limit=$limit->modify("-2 day");
        }*/
        $limit=date_format($limit,'Y-m-d');
        if ($data->ref_date<$limit) {
            return response()->error('', 501, 'SPJ ' . $data->doc_number . ' tidak bisa diubah atau dibatalkan (lebih dari 2 hari)');
        } else if ($data->is_closed == 1) {
            return response()->error('', 501, 'Order ' . $data->doc_number . ' sudah diclose/penerimaan kasir');
        } else if ($data->is_ops_closed == '1') {
            return response()->error('', 501, 'Order ' . $data->doc_number . ' sudah approved');
        } else if ($data->is_cancel == '1') {
            return response()->error('', 501, 'Order ' . $data->doc_number . ' sudah dibatalkan');
        }
        DB::beginTransaction();
        try {
            $data = Operation::where('sysid', $id)->first();
            if ($data) {
                $info=Operation::selectRaw('checklist_doc,driver_id,helper_id,conductor_id')->where('sysid',$id)->first();
                DB::update("UPDATE m_vehicle a INNER JOIN t_operation b ON a.vehicle_no=b.vehicle_no
                     SET a.vehicle_status='Siap',last_operation=-1
                     WHERE b.sysid=?",[$id]);
                DB::update("UPDATE t_vehicle_checklist1 a INNER JOIN t_operation b ON a.vehicle_no=b.vehicle_no
                     SET a.doc_ops='',sysid_ops=-1,is_used=0
                     WHERE a.sysid_ops=?",[$id]);
                Driver::where('employee_id',$info->driver_id)->update(['on_duty'=>'0']);
                Driver::where('employee_id',$info->helper_id)->update(['on_duty'=>'0']);
                Driver::where('employee_id',$info->conductor_id)->update(['on_duty'=>'0']);
                $row['header']=Operation::where('sysid',$id)->first();
                $row['detail']=DB::table('t_operation_route')->where('sysid',$id)->get();
                DB::table('t_deleted')
                ->insert([
                    'log_date'=>Date('Y-m-d H:i:s'),
                    'notes'=>$rec['notes'],
                    'doc_number'=>$data->doc_number,
                    'module'=>'SPJ',
                    'data'=>json_encode($row),
                    'update_userid'=>PagesHelp::UserID($request)
                ]);
                Operation::where('sysid',$id)
                ->update([
                    'is_cancel'=>'1',
                    'cancel_date'=>Date('Y-m-d'),
                    'cancel_notes'=>$rec['notes'],
                    'cancel_by'=>PagesHelp::UserID($request)
                ]);
                DB::commit();
                return response()->success('Success', 'Order berhasil dibatalkan');
            } else {
                DB::rollback();
                return response()->error('', 501, 'Data tidak ditemukan');
            }
        } catch (Exception $e) {
            DB::rollback();
            return response()->error('', 501, $e);
        }
    }
    public function get(Request $request)
    {
        $id = $request->id;
        $header = Operation::from('t_operation as a')
        ->selectRaw("a.sysid,a.pool_code,a.doc_number,a.ref_date,a.time_boarding,a.vehicle_no,a.police_no,
        a.odometer,a.distance,a.driver_id,a.helper_id,a.conductor_id,a.route_default,a.route_id,
        a.start_station,a.dest_station,a.is_change_route,a.is_closed,a.price_model,a.checklist_doc,
        a.valid_checklist,a.debt_deposit,a.debt_accident,a.target,a.deposit,a.is_ops_closed,
        a.is_storing,a.target2,a.revenue,a.others,a.total,a.model,b.personal_name as driver_name,
        d.personal_name as conductor_name,c.personal_name as helper_name,
        IFNULL(b.photo,'') as driverphoto,IFNULL(c.photo,'') as helperphoto,IFNULL(d.photo,'') as conductorphoto")
        ->leftJoin('m_personal as b','a.driver_id','=','b.employee_id')
        ->leftJoin('m_personal as c','a.helper_id','=','c.employee_id')
        ->leftJoin('m_personal as d','a.conductor_id','=','d.employee_id')
        ->where('a.sysid', $id)->first();
        $data['header']=$header;
        $data['route1']=OperationRoute::select('line_id','checkpoint_sysid','checkpoint_name','passenger','factor_point','point')
            ->where('sysid',$id)
            ->where('flag_route','GO')
            ->get();
        $data['route2']=OperationRoute::select('line_id','checkpoint_sysid','checkpoint_name','passenger','factor_point','point')
            ->where('sysid',$id)
            ->where('flag_route','BACK')
            ->get();
        $server=PagesHelp::my_server_url();
        if (!($data['header']['driverphoto']=='')) {
            $data['header']['driverphoto']=$server.'/'.$data['header']['driverphoto'];
        }
        if (!($data['header']['helperphoto']=='')) {
            $data['header']['helperphoto']=$server.'/'.$data['header']['helperphoto'];
        }
        if (!($data['header']['conductorphoto']=='')) {
            $data['header']['conductorphoto']=$server.'/'.$data['header']['conductorphoto'];
        }
        return response()->success('Success', $data);
    }

    public function post(Request $request)
    {
        $data = $request->json()->all();
        $opr = $data['operation'];
        $where = $data['where'];
        $rec = $data['data'];
        $rec['pool_code'] = PagesHelp::PoolCode($request);
        $route1 = $data['route1'];
        $route2 = $data['route2'];
        unset($rec['driverphoto']);
        unset($rec['helperphoto']);
        unset($rec['conductorphoto']);
        $validator=Validator::make($rec,[
            'ref_date'=>'bail|required',
            'pool_code'=>'bail|required',
            'vehicle_no'=>'bail|required',
            'driver_id'=>'bail|required',
            'conductor_id'=>'bail|required',
            'helper_id'=>'bail|required',
            'route_id'=>'bail|required',
            'model'=>'bail|required'
        ],[
            'ref_date.required'=>'Tanggal harus diisi',
            'pool_code.required'=>'Pool harus diisi',
            'vehicle_no.required'=>'Unit kendaraan harus diisi',
            'driver_id.required'=>'Pengemudi harus diisi',
            'conductor_id.required'=>'Kondektur haruS disi',
            'helper_id.required'=>'Kernet harus disi',
            'route_id.required'=>'Trayek kendaraan harus disi',
            'model.required'=>'Model setoran harus disi'
        ]);
        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }
        if ($rec['route_id']=='-1'){
            return response()->error('',501,"Trayek kendaraan harus diisi");
        }
        if (($rec['driver_id']=='-') || ($rec['conductor_id']=='-')) {
            return response()->error('',501,'Pengemudi dan Kondektur harus diisi');
        }
        if (!(Driver::where('employee_id',$rec['driver_id'])->exists())) {
            return response()->error('',501,'Data pengemudi tidak ditemukan');
        }
        if (!(Driver::where('employee_id',$rec['conductor_id'])->exists())) {
            return response()->error('',501,'Data kondektur tidak ditemukan');
        }

        $sysid = $rec['sysid'];
        $slimit=Date('Y-m-d');
        $limit=date_create($slimit);
        if ($rec['pool_code']=='SKBM') {
            $limit=$limit->modify("-365 day");
        } else {
            $limit=$limit->modify("-20 day");
        }

        $limit=date_format($limit,'Y-m-d');

        if ($rec['ref_date']<$limit) {
            return response()->error('', 501,'Backdate(Mundur) tanggal operasional tidak boleh lebih dari 2 hari');
        }
        if ($rec['model']=='-') {
            return response()->error('', 501,'Jenis setoran salah, silahkan cek kembali');
        }
        if ($opr=='inserted') {
            $lastOps=Operation::from('t_operation as a')->selectRaw("a.ref_date,a.doc_number,a.vehicle_no,a.conductor_id,b.personal_name")
            ->leftjoin('m_personal as b','a.conductor_id','=','b.employee_id')
            ->where('a.conductor_id',$rec['conductor_id'])
            ->where('a.is_closed','0')
            ->where('a.is_cancel','0')
            ->where('a.sysid','<>',$rec['sysid'])
            ->first();
            if ($lastOps) {
                if ($lastOps->ref_date<$limit) {
                    return response()->error('', 501, 'Silahkan setor SPJ ' . $lastOps->doc_number . '/'.$lastOps->personal_name.' (Kondektur) dikasir terlebih dahulu (>4hari)');
                }
            }
            $lastOps=Operation::selectRaw("ref_date,doc_number,vehicle_no,conductor_id")
            ->where('vehicle_no',$rec['vehicle_no'])
            ->where('is_closed','0')
            ->where('is_cancel','0')
            ->where('sysid','<>',$rec['sysid'])
            ->first();
            if ($lastOps) {
                if ($lastOps->ref_date<$limit) {
                    return response()->error('', 501, 'Silahkan setor SPJ ' . $lastOps->doc_number . '/'.$lastOps->vehicle_no.' dikasir terlebih dahulu (>3hari)');
                }
            }
        }  else if ($opr == 'updated') {
            $info = Operation::selectRaw('doc_number,is_closed,is_cancel,ref_date')->where('sysid', $sysid)->first();
            if ($info->is_closed == 1) {
                return response()->error('', 501, 'SPJ ' . $info->doc_number . ' sudah diclose/penerimaan kasir');
            } else if ($info->is_cancel == '1') {
                return response()->error('', 501, 'SPJ ' . $info->doc_number . ' dibatalkan');
            } else if ($info->ref_date<$limit) {
                return response()->error('', 501, 'SPJ ' . $info->doc_number . ' tidak bisa diubah atau dibatalkan (lebih dari 4 hari)');
            }
        }
        $data=Vehicle::select('police_no','default_route_id','last_operation','vehicle_status','ops_permit','ops_permit_valid')->where('vehicle_no',$rec['vehicle_no'])->first();
        if ($data){
            $rec['police_no']=$data->police_no;
            $rec['route_default']=$data->default_route_id;
            if (($data->vehicle_status=='Beroperasi') && ($opr=='inserted')) {
                return response()->error('', 501,'Unit '.$data->police_no." Sedang dalam operasi");
            } else if (($data->vehicle_status=='Service') && ($opr=='inserted')) {
                return response()->error('', 501,'Unit '.$data->police_no." Sedang dalam perbaikan/service");
            } else if (($data->vehicle_status=='Pengecekan') && ($opr=='inserted')) {
                return response()->error('', 501,'Unit '.$data->police_no." belum dibuatkan surat layak jalan");
            } else if (($data->ops_permit=='-') || ($data->ops_permit=='')){
                return response()->error('', 501,'Unit '.$data->police_no." belum ada surat layak jalan");
            } else {
                if ($data->ops_permit_valid<$rec['ref_date']){
                    return response()->error('', 501,'Masa berlaku surat layak jalan untuk unit '.$data->police_no." sudah habis, harus dibuatkan surat layak jalan kembali");
                }
            }
        }

        DB::beginTransaction();
        try {
            $rec['update_userid'] = PagesHelp::UserID($request);
            unset($rec['sysid']);
            unset($rec['driver_name']);
            unset($rec['conductor_name']);
            unset($rec['helper_name']);

            /* Deposit */
            $ks=Driver::select('account')->where('employee_id',$rec['conductor_id'])->first();
            if ($ks) {
                $rec['debt_deposit']=$ks->account;
            }
            $accident=Driver::select('account')->where('employee_id',$rec['driver_id'])->first();
            if ($accident) {
                $rec['debt_accident']=$accident->account;
            }
            $rec['cost_id']=-1;
            $rec['internal_cost'] = 0;
            $rec['external_cost'] = 0;
            $rec['standar_cost']  = 0;
            if ($rec['model']=='POINT-BIAYA 1'){
                $cost=DB::table('m_vehicle_routepoint')->selectRaw('sysid,cost')
                ->where('vehicle_no',$rec['vehicle_no'])
                ->where('route_id',$rec['route_id'])->first();
                if ($cost) {
                    $rec['cost_id']=$cost->sysid;
                    $rec['standar_cost']=$cost->cost;
                    $rec['external_cost']=$cost->cost;
                }
            }
            if (($rec['model']=='POINT-TASIK')  || ($rec['model']=='POINT-TASIK-2') || ($rec['model']=='POINT-SUKABUMI')) {
                $cost=DB::table('m_vehicle_routepoint')->selectRaw('sysid,cost')
                ->where('vehicle_no',$rec['vehicle_no'])
                ->where('route_id',$rec['route_id'])->first();
                if ($cost) {
                    $rec['internal_cost']=$cost->cost;
                }
            }
            if ($opr == 'updated') {
                $info=Operation::selectRaw('checklist_doc,driver_id,helper_id,conductor_id')->where($where)->first();
                DB::update("UPDATE m_vehicle a INNER JOIN t_operation b ON a.vehicle_no=b.vehicle_no
                     SET a.vehicle_status='Siap',last_operation=-1
                     WHERE b.sysid=?",[$sysid]);
                DB::update("UPDATE t_vehicle_checklist1 a INNER JOIN t_operation b ON a.vehicle_no=b.vehicle_no
                     SET a.doc_ops='',sysid_ops=-1,is_used=0
                     WHERE a.sysid_ops=?",[$sysid]);
                Driver::where('employee_id',$info->driver_id)->update(['on_duty'=>'0']);
                Driver::where('employee_id',$info->helper_id)->update(['on_duty'=>'0']);
                Driver::where('employee_id',$info->conductor_id)->update(['on_duty'=>'0']);
                Operation::where($where)
                    ->update($rec);
                DB::table('t_operation_route')
                ->where('sysid',$sysid)->delete();
            } else if ($opr = 'inserted') {
                if (Driver::where('employee_id',$rec['driver_id'])
                   ->where('on_duty','1')
                   ->where('is_active','1')->exists()) {
                    return response()->error('', 501,'Pengemudi dalam status operasi');
                }
                if (Driver::where('employee_id',$rec['helper_id'])
                   ->where('on_duty','1')
                   ->where('is_active','1')->exists()) {
                    return response()->error('', 501,'Kernet dalam status operasi');
                }
                if (Driver::where('employee_id',$rec['conductor_id'])
                   ->where('on_duty','1')
                   ->where('is_active','1')->exists()) {
                    return response()->error('', 501,'Kondektur dalam status operasi');
                }
                $rec['doc_number'] = Operation::GenerateNumber($rec['pool_code'],$rec['ref_date']);
                $sysid = Operation::insertGetId($rec);
            }
            foreach($route1 as $row){
                $dtl['sysid']=$sysid;
                $dtl['ref_date']=$rec['ref_date'];
                $dtl['flag_route']='GO';
                $dtl['line_id']=$row['line_id'];
                $dtl['checkpoint_sysid']=$row['checkpoint_sysid'];
                $dtl['checkpoint_name']=$row['checkpoint_name'];
                $dtl['factor_point']=$row['factor_point'];
                $dtl['point']=$row['point'];
                $dtl=(array)$dtl;
                OperationRoute::insert($dtl);
            }
            foreach($route2 as $row){
                $dtl['sysid']=$sysid;
                $dtl['ref_date']=$rec['ref_date'];
                $dtl['flag_route']='BACK';
                $dtl['line_id']=$row['line_id'];
                $dtl['checkpoint_sysid']=$row['checkpoint_sysid'];
                $dtl['checkpoint_name']=$row['checkpoint_name'];
                $dtl['factor_point']=$row['factor_point'];
                $dtl['point']=$row['point'];
                $dtl=(array)$dtl;
                OperationRoute::insert($dtl);
            }
            $odometer=floatval($rec['odometer'])+floatval($rec['distance']);
            Vehicle::where('vehicle_no',$rec['vehicle_no'])
                    ->update(['vehicle_status'=>'Beroperasi',
                              'last_operation'=>$sysid,
                              'odometer'=>$odometer]);
            Driver::where('employee_id',$rec['driver_id'])->update(['on_duty'=>'1']);
            Driver::where('employee_id',$rec['helper_id'])->update(['on_duty'=>'1']);
            Driver::where('employee_id',$rec['conductor_id'])->update(['on_duty'=>'1']);
            DB::update("UPDATE t_vehicle_checklist1 a INNER JOIN t_operation b ON a.vehicle_no=b.vehicle_no
                     SET a.doc_ops=b.doc_number,sysid_ops=a.sysid,a.is_used=1
                     WHERE a.doc_number=? AND b.sysid=?",[$rec['checklist_doc'],$sysid]);
            DB::commit();
            $respon['sysid']=$sysid;
            $respon['message']="Simpan data berhasil";
            return response()->success('Success', $respon);
        } catch (Exception $e) {
            DB::rollback();
            return response()->error('', 501, $e);
        }
    }
    public function open(Request $request)
    {
        $filter     = $request->filter;
        $limit      = $request->limit;
        $sorting    = ($request->descending == "true") ? 'desc' : 'asc';
        $sortBy     = $request->sortBy;
        $start_date = $request->start_date;
        $end_date   = $request->end_date;
        $status     = isset($request->status) ? $request->status :'all';
        $pool_code  = PagesHelp::Session()->pool_code;
        $data= Operation::from('t_operation as a')
        ->selectRaw("a.sysid,a.doc_number,a.ref_date,a.time_boarding,a.pool_code,a.vehicle_no,a.police_no,a.driver_id,c.personal_name AS driver,
            a.helper_id,d.personal_name AS helper,a.conductor_id,e.personal_name AS conductor,
            a.route_id,b.route_name,a.route_default,a.is_closed,a.is_ops_closed")
        ->leftJoin('m_bus_route as b','a.route_id','=','b.sysid')
        ->leftJoin('m_personal as c','a.driver_id','=','c.employee_id')
        ->leftJoin('m_personal as d','a.helper_id','=','d.employee_id')
        ->leftJoin('m_personal as e','a.conductor_id','=','e.employee_id')
        ->where('a.pool_code',$pool_code)
        ->where('a.is_closed',0)
        ->where('a.is_cancel',0);
        if ($status=='approved'){
            $data=$data->where('is_ops_closed','1');
        } else if ($status=='operation'){
            $data=$data->where('is_ops_closed','0');
        }
        if (!($filter == '')) {
            $filter = '%' . trim($filter) . '%';
            $data = $data->where(function ($q) use ($filter) {
                $q->where('a.doc_number', 'like', $filter)
                    ->orwhere('b.route_name', 'like', $filter)
                    ->orwhere('a.vehicle_no', 'like', $filter)
                    ->orwhere('a.police_no', 'like', $filter);
             });
        }

        $data = $data->orderBy($sortBy, $sorting)->paginate($limit);

        return response()->success('Success', $data);
    }

    public function print(Request $request){
        $sysid=$request->sysid;
        $header=Operation::from('t_operation as a')
            ->selectRaw("a.sysid,a.pool_code,a.doc_number,a.ref_date,a.time_boarding,a.vehicle_no,a.police_no,a.odometer,
            a.driver_id,b.personal_name AS driver,
            a.helper_id,c.personal_name AS helper,
            a.conductor_id,d.personal_name AS conductor,
            a.route_id,e.route_name,
            a.start_station,a.dest_station,a.checklist_doc,a.debt_accident,a.debt_deposit,a.update_userid,f.user_name")
            ->leftJoin('m_personal as b','a.driver_id','=','b.employee_id')
            ->leftJoin('m_personal as c','a.helper_id','=','c.employee_id')
            ->leftJoin('m_personal as d','a.conductor_id','=','d.employee_id')
            ->leftJoin('m_bus_route as e','a.route_id','=','e.sysid')
            ->leftJoin('o_users as f','a.update_userid','=','f.user_id')
            ->where('a.sysid',$sysid)->first();
        if (!($header==null)){
            $header->ref_date=date_format(date_create($header->ref_date),'d-m-Y');
            $data['header']=$header;
            $go=OperationRoute::select('checkpoint_name','factor_point')
            ->where('sysid',$sysid)
            ->where('flag_route','GO')
            ->where('checkpoint_sysid','<>','99')
            ->orderBy('line_id')
            ->get();
            $back=OperationRoute::select('checkpoint_name','factor_point')
            ->where('sysid',$sysid)
            ->where('flag_route','BACK')
            ->where('checkpoint_sysid','<>','99')
            ->orderBy('line_id')
            ->get();
            $sign['user']='';
            $home=storage_path();
            $user=Users::selectRaw("IFNULL(sign,'-') as sign")->where('user_id',$header->update_userid)->first();
            if ($user) {
                $sign=($user->sign<>'-') ? storage_path().'/app/'.$user->sign :'';
            } else {
                $sign='';
            }

            $profile=PagesHelp::Profile();
            $qrcode = base64_encode(QrCode::format('svg')->size(100)->errorCorrection('H')->generate($header->doc_number));
            $pdf = PDF::loadView('ops.Spj',['header'=>$header,'go'=>$go,'back'=>$back,
                'qrcode'=>$qrcode,'sign'=>$sign,'profile'=>$profile])->setPaper('legal','potrait');
            return $pdf->stream();
        } else {
            return response()->error('',501,'Data Tidak ditemukan');
        }
    }

    public function passenger(Request $request)
    {
        $id = $request->id;
        $header = Operation::from('t_operation as a')
            ->selectRaw("a.sysid,a.pool_code,a.doc_number,a.ref_date,a.time_boarding,CONCAT(a.vehicle_no,'  - ',a.police_no) AS vehicle_no,a.odometer,a.distance,
                b.route_name,a.rate,a.target,a.target2,a.deposit,a.price_model,c.personal_name AS driver,d.personal_name AS helper, e.personal_name AS conductor,passenger,
                operation_notes,is_ops_closed,revenue,others,total,dispensation,ks,paid,unpaid,model,external_cost,internal_cost,standar_cost,pool_entry_date,pool_entry_time")
            ->leftjoin('m_bus_route as b','a.route_id','=','b.sysid')
            ->leftjoin('m_personal as c','a.driver_id','=','c.employee_id')
            ->leftjoin('m_personal as d','a.helper_id','=','d.employee_id')
            ->leftjoin('m_personal as e','a.conductor_id','=','e.employee_id')
            ->where('a.sysid', $id)->first();
        $data['header']=$header;
        if ($header) {
            $data['route1']=OperationRoute::select('line_id','checkpoint_sysid','checkpoint_name','point','factor_point','passenger','total')
                ->where('sysid',$id)
                ->where('flag_route','GO')
                ->get();
            $data['route2']=OperationRoute::select('line_id','checkpoint_sysid','checkpoint_name','point','factor_point','passenger','total')
                ->where('sysid',$id)
                ->where('flag_route','BACK')
                ->get();
        }
        return response()->success('Success', $data);
    }

    public function postpassenger(Request $request)
    {
        $data   = $request->json()->all();
        $where  = $data['where'];
        $rec    = $data['data'];
        $route1 = $data['route1'];
        $route2 = $data['route2'];
        $others = $data['others'];
        $sysid = $rec['sysid'];
        $info = Operation::where('sysid', $sysid)->first();

        if ($info->is_closed == 1) {
            return response()->error('', 501, 'SPJ ' . $info->doc_number . ' sudah diclose/penerimaan kasir');
        }

        if ($rec['model']=='POINT-SUKABUMI') {
            $total=floatval($rec['revenue'])-(floatval($rec['dispensation'])+floatval($rec['external_cost'])+
                    floatval($rec['station_fee'])+floatval($rec['operation_fee']));
        } else {
            $total=floatval($rec['total'])-(floatval($rec['dispensation'])+floatval($rec['external_cost'])+
                    floatval($rec['internal_cost'])+floatval($rec['station_fee'])+floatval($rec['operation_fee']));
        }
        if ( $total <> (floatval($rec['paid'])+floatval($rec['unpaid']))) {
            $paid=floatval($rec['paid'])+floatval($rec['unpaid']);
            $mustpaid=floatval($rec['total_mustpaid']);

            return response()->error('', 501, "Penerimaan melebihi yang seharusnya dibayar, Harus setor # ".number_format($mustpaid,0,",",".").", Bayar #".number_format($paid,0,",","."));
        }

        if ($rec['is_ops_closed']=='1'){
            if (!($rec['pool_entry_date']) || !($rec['pool_entry_time'])){
                return response()->error('', 501, "Tanggal dan kembali unit ke pool harus diisi");
            }
        }

        if (floatval($rec['paid']<0) && floatval($rec['unpaid']<>0)) {
            $must=(floatval($rec['total'])-floatval($rec['dispensation']))-floatval($rec['external_cost']);
            return response()->error('', 501,'Kurang bayar harus NOL jika nilai bayar kurang dari NOL, Pembayaran seharusnya # '.number_format($must,0,",","."));
        } else if (floatval($rec['paid']>0) && floatval($rec['unpaid']<0)) {
            $must=(floatval($rec['total'])-floatval($rec['dispensation']))-floatval($rec['external_cost']);
            return response()->error('', 501,'Kurang bayar tidak boleh minus,Pembayaran seharunya # '.number_format($must,0,",","."));
        }

        DB::beginTransaction();
        try {
            $rec['update_userid'] = PagesHelp::UserID($request);
            Operation::where($where)
                ->update(['passenger'=>$rec['passenger'],
                 'deposit'=>$rec['deposit'],
                 'revenue'=>$rec['revenue'],
                 'others'=>$rec['others'],
                 'total'=>$rec['total'],
                 'external_cost'=>$rec['external_cost'],
                 'internal_cost'=>$rec['internal_cost'],
                 'operation_fee'=>$rec['operation_fee'],
                 'station_fee'=>$rec['station_fee'],
                 'dispensation'=>$rec['dispensation'],
                 'ks'=>$rec['unpaid'],
                 'paid'=>$rec['paid'],
                 'unpaid'=>$rec['unpaid'],
                 'operation_notes'=>$rec['operation_notes'],
                 'is_ops_closed'=>$rec['is_ops_closed'],
                 'pool_entry_date'=>$rec['pool_entry_date'],
                 'pool_entry_time'=>$rec['pool_entry_time']
                 ]);
            foreach($route1 as $row){
                OperationRoute::where('sysid',$sysid)
                ->where('flag_route','GO')
                ->where('line_id',$row['line_id'])
                ->update(['passenger'=>$row['passenger'],
                'total'=>$row['total']]);
            }
            foreach($route2 as $row){
                OperationRoute::where('sysid',$sysid)
                ->where('flag_route','BACK')
                ->where('line_id',$row['line_id'])
                ->update(['passenger'=>$row['passenger'],
                'total'=>$row['total']]);
            }
            DB::table('t_operation_others_draft')->where('sysid',$sysid)->delete();
            foreach($others  as $row){
               DB::table('t_operation_others_draft')->insert([
                   'sysid'=>$sysid,
                   'item_code'=>$row['item_code'],
                   'item_name'=>$row['item_name'],
                   'amount'=>$row['amount']
               ]);
            }
            DB::update("UPDATE m_vehicle a INNER JOIN t_operation b ON a.vehicle_no=b.vehicle_no
                     SET a.vehicle_status='Beroperasi',last_operation=?
                     WHERE a.sysid=?",[$sysid,$sysid]);
            if ($rec['is_ops_closed']=='1') {
                DB::update("UPDATE m_vehicle a INNER JOIN t_operation b ON a.vehicle_no=b.vehicle_no
                        SET a.last_operation=-1,a.odometer=IFNULL(a.odometer,0)+IFNULL(b.distance,0),
                        vehicle_status='Pengecekan',ops_permit='',ops_permit_valid=NULL WHERE b.sysid=?",[$sysid]);
                $ops=Operation::select('driver_id','helper_id','conductor_id')->where('sysid',$sysid)->first();
                Driver::where('employee_id',$ops->driver_id)->update(['on_duty'=>'0']);
                Driver::where('employee_id',$ops->helper_id)->update(['on_duty'=>'0']);
                Driver::where('employee_id',$ops->conductor_id)->update(['on_duty'=>'0']);
            }

            DB::commit();
            return response()->success('Success', 'Simpan data berhasil');
        } catch (Exception $e) {
            DB::rollback();
            return response()->error('', 501, $e);
        }
    }
    public function expenses(Request $request){
        $sysid=$request->sysid;
        $data=OtherItems::from('m_others_item as a')
        ->selectRaw("a.item_code,a.item_name,IFNULL(b.amount,a.amount) AS amount")
        ->leftJoin('t_operation_others_draft as b', function($join) use($sysid)
            {
                $join->on('a.item_code', '=', 'b.item_code');
                $join->on('b.sysid','=',DB::raw("$sysid"));
            })
        ->where('a.is_active',1)
        ->get();
        return response()->success('Success', $data);
    }
    public function query(Request $request)
    {
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending == "true";
        $sortBy = $request->sortBy;
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $pool_code = $request->pool_code;
        $data= Operation::from('t_operation as a')
        ->selectRaw("a.sysid as _index,a.doc_number,a.ref_date,a.time_boarding,a.pool_entry_date,a.pool_entry_time,a.vehicle_no,
                    a.route_id,b.route_name,a.passenger AS jpoint,a.distance,a.revenue,a.target,a.target2,a.internal_cost,a.external_cost,
                    a.operation_fee,a.station_fee,a.others,a.model,
                    a.total,a.dispensation,a.paid,a.unpaid,a.driver_id,c.personal_name AS driver_name,a.odometer,a.passenger,
                    a.conductor_id,d.personal_name AS conductor_name,
                    a.helper_id,e.personal_name AS helper_name,a.pool_code,a.update_userid,a.update_timestamp,
                    CASE
                    WHEN a.is_cancel=1 THEN 'BATAL OPERASI'
                    WHEN a.is_closed=0 AND a.is_ops_closed=0 THEN 'BLM APPROVED'
                    WHEN a.is_closed=0 AND a.is_ops_closed=1 THEN 'SUDAH APPROVED'
                    WHEN a.is_cancel=0 AND a.is_closed=1 THEN 'SUDAH SETOR'
                    ELSE 'N/A' END as state")
        ->leftJoin('m_bus_route as b','a.route_id','=','b.sysid')
        ->leftJoin('m_personal as c','a.driver_id','=','c.employee_id')
        ->leftJoin('m_personal as d','a.conductor_id','=','d.employee_id')
        ->leftJoin('m_personal as e','a.helper_id','=','e.employee_id')
        ->where('a.ref_date', '>=', $start_date)
        ->where('a.ref_date', '<=', $end_date)
        ->where('a.is_cancel','0');
        if (!($pool_code=='ALL')){
            $data=$data->where('a.pool_code',$pool_code);
        }
        if (!($filter == '')) {
            $filter = '%' . trim($filter) . '%';
            $data = $data->where(function ($q) use ($filter) {
                $q->where('a.doc_number', 'like', $filter)
                    ->orwhere('a.doc_number', 'like', $filter)
                    ->orwhere('a.vehicle_no', 'like', $filter)
                    ->orwhere('a.police_no', 'like', $filter)
                    ->orwhere('a.pool_code', 'like', $filter);
            });
        }
        if (!($sortBy == '')) {
            if ($descending) {
                $data = $data->orderBy($sortBy, 'desc')->paginate($limit);
            } else {
                $data = $data->orderBy($sortBy, 'asc')->paginate($limit);
            }
        } else {
            $data = $data->paginate($limit);
        }
        return response()->success('Success', $data);
    }
    public function report(Request $request)
    {
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $pool_code = $request->pool_code;
        $data= Operation::from('t_operation as a')
        ->selectRaw("a.sysid as _index,a.doc_number,a.ref_date,a.time_boarding,a.pool_entry_date,a.pool_entry_time,a.vehicle_no,a.route_id,b.route_name,a.passenger AS jpoint,
                    a.total,a.dispensation,a.paid,a.unpaid,a.driver_id,c.personal_name AS driver_name,a.odometer,a.passenger,
                    a.conductor_id,d.personal_name AS conductor_name,a.revenue,a.target,a.target2,a.internal_cost,a.external_cost,
                    a.operation_fee,a.station_fee,a.others,a.model,
                    a.helper_id,e.personal_name AS helper_name,a.pool_code,a.update_userid,a.update_timestamp,
                    CASE
                    WHEN a.is_cancel=1 THEN 'BATAL OPERASI'
                    WHEN a.is_closed=0 AND a.is_ops_closed=0 THEN 'BLM APPROVED'
                    WHEN a.is_closed=0 AND a.is_ops_closed=1 THEN 'SUDAH APPROVED'
                    WHEN a.is_cancel=0 AND a.is_closed=1 THEN 'SUDAH SETOR'
                    ELSE 'N/A' END as state")
        ->leftJoin('m_bus_route as b','a.route_id','=','b.sysid')
        ->leftJoin('m_personal as c','a.driver_id','=','c.employee_id')
        ->leftJoin('m_personal as d','a.conductor_id','=','d.employee_id')
        ->leftJoin('m_personal as e','a.helper_id','=','e.employee_id')
        ->where('a.ref_date', '>=', $start_date)
        ->where('a.ref_date', '<=', $end_date)
        ->where('a.is_cancel','0');
        if (!($pool_code=='ALL')){
            $data=$data->where('a.pool_code',$pool_code);
        }
        $data=$data->orderBy("a.ref_date",'asc')
        ->orderBy("a.time_boarding",'asc');

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet(0);
        \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder( new \PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder());

        $sheet->setCellValue('A1', 'LAPORAN S.P.J KELUAR');
        $sheet->setCellValue('A2', 'PERIODE');
        $sheet->setCellValue('B2', ': '.date_format(date_create($start_date),"d-m-Y").' s/d '.date_format(date_create($end_date),"d-m-Y"));
        if ($pool_code=='ALL') {
            $sheet->setCellValue('A3', 'POOL');
            $sheet->setCellValue('B3', ': SEMUA POOL');
        } else {
            $sheet->setCellValue('A3', 'POOL');
            $sheet->setCellValue('B3', ': '.$pool_code);
        }
        $sheet->setCellValue('A5', 'No.Bodi');
        $sheet->setCellValue('B5', 'No.SPJ');
        $sheet->setCellValue('C5', 'Tanggal');
        $sheet->setCellValue('D5', 'Jam.Berangkat');
        $sheet->setCellValue('E5', 'Tgl.Kembali');
        $sheet->setCellValue('F5', 'Jam.Kembali');
        $sheet->setCellValue('G5', 'Trayek');
        $sheet->setCellValue('H5', 'KM Jarak');
        $sheet->setCellValue('I5', 'Model');
        $sheet->setCellValue('J5', 'Poin');
        $sheet->setCellValue('K5', 'Pendapatan');
        $sheet->setCellValue('L5', 'Komisi Terminal');
        $sheet->setCellValue('M5', 'Komisi Krew');
        $sheet->setCellValue('N5', 'Total');
        $sheet->setCellValue('O5', 'Biaya Operasi');
        $sheet->setCellValue('P5', 'Bonus');
        $sheet->setCellValue('Q5', 'KS');
        $sheet->setCellValue('R5', 'Dispensasi');
        $sheet->setCellValue('S5', 'Target 1');
        $sheet->setCellValue('T5', 'Target 2');
        $sheet->setCellValue('U5', 'Lain2');
        $sheet->setCellValue('V5', 'Penerimaan');
        $sheet->setCellValue('W5', 'NIK Pengemudi');
        $sheet->setCellValue('X5', 'Pengemudi');
        $sheet->setCellValue('Y5', 'NIK Kondektur');
        $sheet->setCellValue('Z5', 'Kondektur');
        $sheet->setCellValue('AA5', 'NIK Kernet');
        $sheet->setCellValue('AB5', 'Kernet');
        $sheet->setCellValue('AC5', 'Status');
        $sheet->setCellValue('AD5', 'Pool');
        $sheet->setCellValue('AE5', 'User Input');
        $sheet->setCellValue('AF5', 'Tgl.Input');
        $sheet->getStyle('A5:AF5')->getAlignment()->setHorizontal('center');
        $idx=5;
        $data=$data->chunk(1000, function ($datas) use ($sheet,&$idx) {
            foreach($datas as $row) {
                $idx=$idx+1;
                $sheet->setCellValue('A'.$idx, $row->vehicle_no);
                $sheet->setCellValue('B'.$idx, $row->doc_number);
                $sheet->setCellValue('C'.$idx, $row->ref_date);
                $sheet->setCellValue('D'.$idx, $row->time_boarding);
                $sheet->setCellValue('E'.$idx, $row->pool_entry_date);
                $sheet->setCellValue('F'.$idx, $row->pool_entry_time);
                $sheet->setCellValue('G'.$idx, $row->route_name);
                $sheet->setCellValue('H'.$idx, $row->odometer);
                $sheet->setCellValue('I'.$idx, $row->model);
                $sheet->setCellValue('J'.$idx, $row->passenger);
                $sheet->setCellValue('K'.$idx, $row->revenue);
                $sheet->setCellValue('L'.$idx, $row->station_fee);
                $sheet->setCellValue('M'.$idx, $row->external_cost);
                $sheet->setCellValue('N'.$idx, $row->total);
                $sheet->setCellValue('O'.$idx, $row->internal_cost);
                $sheet->setCellValue('P'.$idx, $row->operation_fee);
                $sheet->setCellValue('Q'.$idx, $row->unpaid);
                $sheet->setCellValue('R'.$idx, $row->dispensation);
                $sheet->setCellValue('S'.$idx, $row->target);
                $sheet->setCellValue('T'.$idx, $row->target2);
                $sheet->setCellValue('U'.$idx, $row->others);
                $sheet->setCellValue('V'.$idx, $row->paid);
                $sheet->setCellValue('W'.$idx, $row->driver_id);
                $sheet->setCellValue('X'.$idx, $row->driver_name);
                $sheet->setCellValue('Y'.$idx, $row->conductor_id);
                $sheet->setCellValue('Z'.$idx, $row->conductor_name);
                $sheet->setCellValue('AA'.$idx, $row->helper_id);
                $sheet->setCellValue('AB'.$idx, $row->helper_name);
                $sheet->setCellValue('AC'.$idx, $row->state);
                $sheet->setCellValue('AD'.$idx, $row->pool_code);
                $sheet->setCellValue('AE'.$idx, $row->update_userid);
                $sheet->setCellValue('AF'.$idx, $row->update_timestamp);
            }
        });
        $sheet->getStyle('C6:C'.$idx)->getNumberFormat()->setFormatCode('dd-mm-yyyy');
        $sheet->getStyle('D6:D'.$idx)->getNumberFormat()->setFormatCode('HH:MM');
        $sheet->getStyle('E6:E'.$idx)->getNumberFormat()->setFormatCode('dd-mm-yyyy');
        $sheet->getStyle('F6:F'.$idx)->getNumberFormat()->setFormatCode('HH:MM');
        $sheet->getStyle('AF6:AF'.$idx)->getNumberFormat()->setFormatCode('dd-mm-yyyy HH:MM');
        $last=$idx;
        $idx=$idx+1;
        $sheet->setCellValue('H'.$idx, "TOTAL");
        $sheet->setCellValue('I'.$idx, "=SUM(I6:I$last)");
        $sheet->setCellValue('J'.$idx, "=SUM(J6:J$last)");
        $sheet->setCellValue('K'.$idx, "=SUM(K6:K$last)");
        $sheet->setCellValue('L'.$idx, "=SUM(L6:L$last)");
        $sheet->setCellValue('M'.$idx, "=SUM(M6:M$last)");
        $sheet->setCellValue('N'.$idx, "=SUM(N6:N$last)");
        $sheet->setCellValue('O'.$idx, "=SUM(O6:O$last)");
        $sheet->setCellValue('P'.$idx, "=SUM(P6:P$last)");
        $sheet->setCellValue('Q'.$idx, "=SUM(Q6:Q$last)");
        $sheet->setCellValue('R'.$idx, "=SUM(R6:R$last)");
        $sheet->setCellValue('S'.$idx, "=SUM(S6:S$last)");
        $sheet->setCellValue('T'.$idx, "=SUM(T6:T$last)");
        $sheet->setCellValue('U'.$idx, "=SUM(U6:U$last)");
        $sheet->setCellValue('V'.$idx, "=SUM(V6:V$last)");
        $sheet->getStyle('H6:V'.$idx)->getNumberFormat()->setFormatCode('#,##0;[RED](#,##0)');
        // Formater
        $sheet->getStyle('A1:AF5')->getFont()->setBold(true);
        $sheet->getStyle('A'.$idx.':'.'AF'.$idx)->getFont()->setBold(true);
        $styleArray = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        ],
                    ],
                ];
        $sheet->getStyle('A5:AF'.$idx)->applyFromArray($styleArray);
        foreach(range('C','AF') as $columnID) {
             $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }
        $sheet->getColumnDimension('A')->setWidth(5);
        $sheet->getColumnDimension('B')->setWidth(12);
        $sheet->getColumnDimension('AA')->setAutoSize(true);
        $sheet->getColumnDimension('AB')->setAutoSize(true);
        $sheet->getColumnDimension('AC')->setAutoSize(true);
        $sheet->getColumnDimension('AD')->setAutoSize(true);
        $sheet->getColumnDimension('AE')->setAutoSize(true);
        $sheet->getColumnDimension('AF')->setAutoSize(true);
        ob_end_clean();
        $response = response()->streamDownload(function() use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(true);
            $writer->save('php://output');
        });
        $xls="laporan_spj_".$start_date.'_'.$end_date.".xlsx";
        PagesHelp::Response($response,$xls)->send();
    }

    public function unpaid(Request $request)
    {
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending == "true";
        $sortBy = $request->sortBy;
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $pool_code = $request->pool_code;
        $data= OperationUnpaid::from('t_ar_document as a')
        ->selectRaw("a.line_id AS _index,a.conductor_id,b.personal_name,a.doc_number,a.ops_number,
                    a.ref_date,a.descriptions,a.amount,a.paid,d.vehicle_no,d.police_no,d.ref_date AS ops_date")
        ->leftJoin('m_personal as b','a.conductor_id','=','b.employee_id')
        ->leftJoin('t_operation_cashier as c','a.sysid','=','c.sysid')
        ->leftJoin('t_operation as d','c.sysid_operation','=','d.sysid')
        ->where('a.ref_date', '>=', $start_date)
        ->where('a.ref_date', '<=', $end_date);
        if (!($pool_code=='ALL')){
            $data=$data->where('c.pool_code',$pool_code);
        }
        if (!($filter == '')) {
            $filter = '%' . trim($filter) . '%';
            $data = $data->where(function ($q) use ($filter) {
                $q->where('a.doc_number', 'like', $filter)
                    ->orwhere('a.ops_number', 'like', $filter)
                    ->orwhere('a.conductor_id', 'like', $filter)
                    ->orwhere('b.personal_name', 'like', $filter)
                    ->orwhere('d.police_no', 'like', $filter);
            });
        }
        if (!($sortBy == '')) {
            if ($descending) {
                $data = $data->orderBy($sortBy, 'desc')->paginate($limit);
            } else {
                $data = $data->orderBy($sortBy, 'asc')->paginate($limit);
            }
        } else {
            $data = $data->paginate($limit);
        }
        return response()->success('Success', $data);
    }
    public function unpaidxls(Request $request)
    {
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $pool_code = $request->pool_code;
        $data= OperationUnpaid::from('t_ar_document as a')
        ->selectRaw("a.line_id AS _index,a.conductor_id,b.personal_name,a.doc_number,a.ops_number,
                    a.ref_date,a.descriptions,a.amount,a.paid,d.vehicle_no,d.police_no,d.ref_date AS ops_date")
        ->leftJoin('m_personal as b','a.conductor_id','=','b.employee_id')
        ->leftJoin('t_operation_cashier as c','a.sysid','=','c.sysid')
        ->leftJoin('t_operation as d','c.sysid_operation','=','d.sysid')
        ->where('a.ref_date', '>=', $start_date)
        ->where('a.ref_date', '<=', $end_date);
        if (!($pool_code=='ALL')){
            $data=$data->where('c.pool_code',$pool_code);
        }
        $data=$data->get();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet(0);
        \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder( new \PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder());

        $sheet->setCellValue('A1', 'LAPORAN KURANG SETOR');
        $sheet->setCellValue('A2', 'PERIODE');
        $sheet->setCellValue('B2', ': '.date_format(date_create($start_date),"d-m-Y").' s/d '.date_format(date_create($end_date),"d-m-Y"));
        if ($pool_code=='ALL') {
            $sheet->setCellValue('A3', 'POOL');
            $sheet->setCellValue('B3', ': SEMUA POOL');
        } else {
            $sheet->setCellValue('A3', 'POOL');
            $sheet->setCellValue('B3', ': '.$pool_code);
        }
        $sheet->setCellValue('A5', 'No.Setor');
        $sheet->setCellValue('B5', 'Tanggal');
        $sheet->setCellValue('C5', 'No.SPJ');
        $sheet->setCellValue('D5', 'Tgl.SPJ');
        $sheet->setCellValue('E5', 'Kondektur');
        $sheet->setCellValue('F5', 'Keterangan');
        $sheet->setCellValue('G5', 'Kurang Setor');
        $sheet->setCellValue('H5', 'Penerimaan');
        $sheet->setCellValue('I5', 'No.Unit');
        $sheet->setCellValue('J5', 'No.Polisi');
        $sheet->getStyle('A5:J5')->getAlignment()->setHorizontal('center');

        $idx=5;
        foreach($data as $row){
            $idx=$idx+1;
            $sheet->setCellValue('A'.$idx, $row->doc_number);
            $sheet->setCellValue('B'.$idx, $row->ref_date);
            $sheet->setCellValue('C'.$idx, $row->ops_number);
            $sheet->setCellValue('D'.$idx, $row->ops_date);
            $sheet->setCellValue('E'.$idx, $row->personal_name);
            $sheet->setCellValue('F'.$idx, $row->descriptions);
            $sheet->setCellValue('G'.$idx, $row->amount);
            $sheet->setCellValue('H'.$idx, $row->paid);
            $sheet->setCellValue('I'.$idx, $row->vehicle_no);
            $sheet->setCellValue('J'.$idx, $row->police_no);
        }

        $sheet->getStyle('B6:B'.$idx)->getNumberFormat()->setFormatCode('dd-mm-yyyy');
        $sheet->getStyle('D6:D'.$idx)->getNumberFormat()->setFormatCode('dd-mm-yyyy');
        $sheet->getStyle('G6:G'.$idx)->getNumberFormat()->setFormatCode('#,##0;[RED](#,##0)');
        $sheet->getStyle('H6:H'.$idx)->getNumberFormat()->setFormatCode('#,##0;[RED](#,##0)');
        $last=$idx;
        $idx=$idx+1;
        $sheet->setCellValue('F'.$idx, "TOTAL");
        $sheet->setCellValue('G'.$idx, "=SUM(G6:G$last)");
        $sheet->setCellValue('H'.$idx, "=SUM(H6:H$last)");
        $sheet->getStyle('G6:G'.$idx)->getNumberFormat()->setFormatCode('#,##0;[RED](#,##0)');
        $sheet->getStyle('H6:H'.$idx)->getNumberFormat()->setFormatCode('#,##0;[RED](#,##0)');
        // Formater
        $sheet->getStyle('A1:J5')->getFont()->setBold(true);
        $sheet->getStyle('A'.$idx.':'.'J'.$idx)->getFont()->setBold(true);
        $styleArray = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        ],
                    ],
                ];
        $sheet->getStyle('A5:J'.$idx)->applyFromArray($styleArray);
        foreach(range('C','J') as $columnID) {
             $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(12);
        ob_end_clean();
        $response = response()->streamDownload(function() use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(true);
            $writer->save('php://output');
        });
        $xls="laporan_ks_".$start_date.'_'.$end_date.".xlsx";
        PagesHelp::Response($response,$xls)->send();
    }

    public function intransit(Request $request)
    {
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending == "true";
        $sortBy = $request->sortBy;
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $cash_id = isset($request->cash_id) ? $request->cash_id:-1;
        $data= Intransit::from('t_intransit as a')
        ->selectRaw("a.sysid as _index,a.sysid,a.pool_code,a.doc_number,a.ref_date,a.descriptions,a.amount,a.deposit,a.amount-a.deposit AS undeposit,a.vehicle_no,
            a.cash_id,CONCAT(b.bank_account,',-',account_number) AS bank_name,a.update_userid,a.update_timestamp,c.police_no")
        ->leftJoin('m_cash_operation as b','a.cash_id','=','b.sysid')
        ->leftJoin('m_vehicle as c','a.vehicle_no','=','c.vehicle_no')
        ->where('a.ref_date', '>=', $start_date)
        ->where('a.ref_date', '<=', $end_date);
        if (!($cash_id==99999)){
            $data=$data->where('a.cash_id',$cash_id);
        }
        if (!($filter == '')) {
            $filter = '%' . trim($filter) . '%';
            $data = $data->where(function ($q) use ($filter) {
                $q->where('a.doc_number', 'like', $filter)
                    ->orwhere('a.doc_number', 'like', $filter)
                    ->orwhere('a.vehicle_no', 'like', $filter)
                    ->orwhere('a.descriptions', 'like', $filter);
            });
        }
        if (!($sortBy == '')) {
            if ($descending) {
                $data = $data->orderBy($sortBy, 'desc')->paginate($limit);
            } else {
                $data = $data->orderBy($sortBy, 'asc')->paginate($limit);
            }
        } else {
            $data = $data->paginate($limit);
        }
        return response()->success('Success', $data);
    }
    public function intransitxls(Request $request)
    {
        $sortBy = $request->sortBy;
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $cash_id = isset($request->cash_id) ? $request->cash_id:-1;
        $data= Intransit::from('t_intransit as a')
        ->selectRaw("a.sysid as _index,a.sysid,a.pool_code,a.doc_number,a.ref_date,a.descriptions,a.amount,a.deposit,a.amount-a.deposit AS undeposit,a.vehicle_no,
            a.cash_id,CONCAT(b.bank_account,',-',account_number) AS bank_name,a.update_userid,a.update_timestamp,c.police_no")
        ->leftJoin('m_cash_operation as b','a.cash_id','=','b.sysid')
        ->leftJoin('m_vehicle as c','a.vehicle_no','=','c.vehicle_no')
        ->where('a.ref_date', '>=', $start_date)
        ->where('a.ref_date', '<=', $end_date);
        if (!($cash_id==99999)){
            $data=$data->where('a.cash_id',$cash_id);
        }
        $data=$data->get();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet(0);
        \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder( new \PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder());

        $sheet->setCellValue('A1', 'LAPORAN DATA INTRANSIT');
        $sheet->setCellValue('A2', 'PERIODE');
        $sheet->setCellValue('B2', ': '.date_format(date_create($start_date),"d-m-Y").' s/d '.date_format(date_create($end_date),"d-m-Y"));
        if (!($cash_id==99999)){
            $sheet->setCellValue('A3', 'KAS/BANK');
            $sheet->setCellValue('B3', ': SEMUA KAS/BANK');
        } else {
            $sheet->setCellValue('A3', 'KAS/BANK');
            $bank=Bank::where('sysid',$cash_id)->first();
            if ($bank){
                $sheet->setCellValue('B3', ': '.$bank->bank_account.' - '.$data->account_number);
            }
        }
        $sheet->setCellValue('A5', 'No.Setor');
        $sheet->setCellValue('B5', 'Keterangan');
        $sheet->setCellValue('C5', 'Tanggal');
        $sheet->setCellValue('D5', 'Penerimaan');
        $sheet->setCellValue('E5', 'Setor');
        $sheet->setCellValue('F5', 'Blm.Setor');
        $sheet->setCellValue('G5', 'No.Unit');
        $sheet->setCellValue('H5', 'No.Polisi');
        $sheet->setCellValue('I5', 'Pool');
        $sheet->setCellValue('J5', 'User Input');
        $sheet->setCellValue('K5', 'Tgl.Input');
        $sheet->getStyle('A5:K5')->getAlignment()->setHorizontal('center');

        $idx=5;
        foreach($data as $row){
            $idx=$idx+1;
            $sheet->setCellValue('A'.$idx, $row->doc_number);
            $sheet->setCellValue('B'.$idx, $row->descriptions);
            $sheet->setCellValue('C'.$idx, $row->ref_date);
            $sheet->setCellValue('D'.$idx, $row->amount);
            $sheet->setCellValue('E'.$idx, $row->deposit);
            $sheet->setCellValue('F'.$idx, $row->undeposit);
            $sheet->setCellValue('G'.$idx, $row->vehicle_no);
            $sheet->setCellValue('H'.$idx, $row->police_no);
            $sheet->setCellValue('I'.$idx, $row->pool_code);
            $sheet->setCellValue('J'.$idx, $row->update_userid);
            $sheet->setCellValue('K'.$idx, $row->update_timestamp);
        }

        $sheet->getStyle('C6:C'.$idx)->getNumberFormat()->setFormatCode('dd-mm-yyyy');
        $sheet->getStyle('K6:K'.$idx)->getNumberFormat()->setFormatCode('dd-mm-yyyy HH:MM');
        $last=$idx;
        $idx=$idx+1;
        $sheet->setCellValue('C'.$idx, "TOTAL");
        $sheet->setCellValue('D'.$idx, "=SUM(D6:D$last)");
        $sheet->setCellValue('E'.$idx, "=SUM(E6:E$last)");
        $sheet->setCellValue('F'.$idx, "=SUM(F6:F$last)");
        $sheet->getStyle('D6:F'.$idx)->getNumberFormat()->setFormatCode('#,##0;[RED](#,##0)');
        // Formater
        $sheet->getStyle('A1:K5')->getFont()->setBold(true);
        $sheet->getStyle('A'.$idx.':'.'K'.$idx)->getFont()->setBold(true);
        $styleArray = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        ],
                    ],
                ];
        $sheet->getStyle('A5:K'.$idx)->applyFromArray($styleArray);
        foreach(range('C','K') as $columnID) {
             $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(45);
        ob_end_clean();
        $response = response()->streamDownload(function() use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(true);
            $writer->save('php://output');
        });
        $xls="laporan_intransit_".$start_date.'_'.$end_date.".xlsx";
        PagesHelp::Response($response,$xls)->send();
    }

    public function passenger_query(Request $request)
    {
        $filter = $request->filter;
        $limit = $request->limit;
        $sorting = ($request->descending == "true") ?'desc':'asc';
        $sortBy = $request->sortBy;
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $route_sysid = $request->route_sysid;
        $data= Operation::from('t_operation as a')
        ->selectRaw("a.sysid as _index,a.sysid,a.doc_number,a.ref_date,a.pool_entry_date,a.pool_entry_time,a.vehicle_no,
                    a.police_no,a.route_id,b.route_name,a.distance,a.model,
                    a.paid,a.driver_id,c.personal_name AS driver_name,a.odometer,a.passenger,
                    a.conductor_id,d.personal_name AS conductor_name,
                    a.helper_id,e.personal_name AS helper_name,a.pool_code")
        ->leftJoin('m_bus_route as b','a.route_id','=','b.sysid')
        ->leftJoin('m_personal as c','a.driver_id','=','c.employee_id')
        ->leftJoin('m_personal as d','a.conductor_id','=','d.employee_id')
        ->leftJoin('m_personal as e','a.helper_id','=','e.employee_id')
        ->where('a.ref_date', '>=', $start_date)
        ->where('a.ref_date', '<=', $end_date)
        ->where('a.route_id',$route_sysid);
        if (!($filter == '')) {
            $filter = '%' . trim($filter) . '%';
            $data = $data->where(function ($q) use ($filter) {
                $q->where('a.doc_number', 'like', $filter)
                    ->orwhere('a.doc_number', 'like', $filter)
                    ->orwhere('a.vehicle_no', 'like', $filter)
                    ->orwhere('a.police_no', 'like', $filter)
                    ->orwhere('a.pool_code', 'like', $filter);
            });
        }
        $data = $data->orderBy($sortBy,$sorting)->paginate($limit);
        $data=$data->toArray();
        $rows=array();
        foreach($data['data'] as $row){
            $row['go_1']=0;
            $row['go_2']=0;
            $row['go_3']=0;
            $row['go_4']=0;
            $row['go_5']=0;
            $row['go_6']=0;
            $row['go_7']=0;
            $row['back_1']=0;
            $row['back_2']=0;
            $row['back_3']=0;
            $row['back_4']=0;
            $row['back_5']=0;
            $row['back_6']=0;
            $row['back_7']=0;
            $rows[]=$row;
        }
        $data['data']=$rows;

        $rows=array();
        foreach($data['data'] as $row){
            $go=OperationRoute::where('sysid',$row['sysid'])
                ->where('flag_route','GO')->get();
            foreach($go as $line) {
                $field='go_'.$line->line_id;
                $row[$field]=$line->passenger;
            }
            $back=OperationRoute::where('sysid',$row['sysid'])
                ->where('flag_route','BACK')->get();
            foreach($back as $line) {
                $field='back_'.$line->line_id;
                $row[$field]=$line->passenger;
            }
            $rows[]=$row;
        }
        $data['data']=$rows;

        return response()->success('Success', $data);
    }

    public function passenger_xls(Request $request)
    {
        $filter = $request->filter;
        $limit = $request->limit;
        $sorting = ($request->descending == "true") ?'desc':'asc';
        $sortBy = $request->sortBy;
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $route_sysid = $request->route_sysid;
        $data= Operation::from('t_operation as a')
        ->selectRaw("a.sysid as _index,a.sysid,a.doc_number,a.ref_date,a.pool_entry_date,a.pool_entry_time,a.vehicle_no,
                    a.police_no,a.route_id,b.route_name,a.distance,a.model,
                    a.paid,a.driver_id,c.personal_name AS driver_name,a.odometer,a.passenger,
                    a.conductor_id,d.personal_name AS conductor_name,
                    a.helper_id,e.personal_name AS helper_name,a.pool_code")
        ->leftJoin('m_bus_route as b','a.route_id','=','b.sysid')
        ->leftJoin('m_personal as c','a.driver_id','=','c.employee_id')
        ->leftJoin('m_personal as d','a.conductor_id','=','d.employee_id')
        ->leftJoin('m_personal as e','a.helper_id','=','e.employee_id')
        ->where('a.ref_date', '>=', $start_date)
        ->where('a.ref_date', '<=', $end_date)
        ->where('a.route_id',$route_sysid)
        ->orderBy('a.ref_date','asc')
        ->orderBy('a.sysid')->get();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet(0);
        \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder( new \PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder());

        $sheet->setCellValue('A1', 'LAPORAN DATA PENUMPANG');
        $sheet->setCellValue('A2', 'PERIODE');
        $sheet->setCellValue('B2', ': '.date_format(date_create($start_date),"d-m-Y").' s/d '.date_format(date_create($end_date),"d-m-Y"));
        $route=Busroute::selectRaw("route_name")->where('sysid',$route_sysid)->first();
        $sheet->setCellValue('A3', 'RUTE');
        $sheet->setCellValue('B3', ':'.isset($route->route_name) ? $route->route_name :'N/A');
        $sheet->mergeCells('A5:A6');
        $sheet->mergeCells('B5:B6');
        $sheet->mergeCells('C5:C6');
        $sheet->mergeCells('D5:D6');
        $sheet->mergeCells('E5:E6');
        $sheet->mergeCells('F5:F6');
        $sheet->mergeCells('G5:G6');
        $sheet->mergeCells('H5:H6');
        $sheet->setCellValue('A5', 'Pool');
        $sheet->setCellValue('B5', 'No.SPJ');
        $sheet->setCellValue('C5', 'Tanggal');
        $sheet->setCellValue('D5', 'No.Unit');
        $sheet->setCellValue('E5', 'No. Polisi');
        $sheet->setCellValue('F5', 'ID Pengemudi');
        $sheet->setCellValue('G5', 'Pengemudi');
        $sheet->setCellValue('H5', 'Penumpang');

        $sheet->getStyle('A5:K5')->getAlignment()->setHorizontal('center');

        $idx=6;
        $firstline=true;
        $char=['I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z'];
        $lastcolumns='H';
        foreach($data as $row){
            $idx=$idx+1;
            $sheet->setCellValue('A'.$idx, $row->pool_code);
            $sheet->setCellValue('B'.$idx, $row->doc_number);
            $sheet->setCellValue('C'.$idx, $row->ref_date);
            $sheet->setCellValue('D'.$idx, $row->vehicle_no);
            $sheet->setCellValue('E'.$idx, $row->police_no);
            $sheet->setCellValue('F'.$idx, $row->driver_id);
            $sheet->setCellValue('G'.$idx, $row->driver_name);
            $sheet->setCellValue('H'.$idx, $row->passenger);
            $col=0;
            $go=OperationRoute::selectRaw("checkpoint_name,passenger")
                ->where('sysid',$row->sysid)
                ->where('flag_route','GO')
                ->orderBy("line_id","asc")
                ->get();
            foreach($go as $line) {
                if ($firstline) {
                    $sheet->setCellValue($char[$col].'6', $line->checkpoint_name);
                    $lastcolumns=$char[$col];
                }
                $sheet->setCellValue($char[$col].$idx, $line->passenger);
                $col=$col+1;
            }
            if ($firstline) {
                $sheet->mergeCells('I5:'.$lastcolumns.'5');
                $sheet->setCellValue('I5', 'RUTE JALAN');
            }
            $firstcolum=$char[$col];
            $back=OperationRoute::selectRaw("checkpoint_name,passenger")
                ->where('sysid',$row->sysid)
                ->where('flag_route','BACK')
                ->orderBy("line_id","asc")
                ->get();
            foreach($back as $line) {
                if ($firstline) {
                    $sheet->setCellValue($char[$col].'6', $line->checkpoint_name);
                    $lastcolumns=$char[$col];
                }
                $sheet->setCellValue($char[$col].$idx, $line->passenger);
                $col=$col+1;
            }
            if ($firstline) {
                $sheet->mergeCells($firstcolum.'5:'.$lastcolumns.'5');
                $sheet->setCellValue($firstcolum.'5', 'RUTE KEMBALI');
            }
            $firstline=false;
        }

        $sheet->getStyle('A1:'.$lastcolumns.'6')->getFont()->setBold(true);
        $styleArray = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        ],
                    ],
                ];
        $sheet->getStyle('A5:'.$lastcolumns.$idx)->applyFromArray($styleArray);
        $styleArray = [
            'font' => [
                'bold' => true,
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => [
                    'argb' => 'FF7A00',
                ],
                'endColor' => [
                    'argb' => 'FF7A00',
                ],
            ],
        ];
        $sheet->getStyle('A5:'.$lastcolumns.'6')->applyFromArray($styleArray);

        foreach(range('C',$lastcolumns) as $columnID) {
             $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }
        $sheet->getColumnDimension('A')->setWidth(20);
        $sheet->getColumnDimension('B')->setWidth(20);
        ob_end_clean();
        $response = response()->streamDownload(function() use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(true);
            $writer->save('php://output');
        });
        $xls="laporan_penumpang".$start_date.'_'.$end_date.".xlsx";
        PagesHelp::Response($response,$xls)->send();

        $rows=array();
        foreach($data['data'] as $row){
            $go=OperationRoute::where('sysid',$row['sysid'])
                ->where('flag_route','GO')->get();
            foreach($go as $line) {
                $field='go_'.$line->line_id;
                $row[$field]=$line->passenger;
            }
            $back=OperationRoute::where('sysid',$row['sysid'])
                ->where('flag_route','BACK')->get();
            foreach($back as $line) {
                $field='back_'.$line->line_id;
                $row[$field]=$line->passenger;
            }
            $rows[]=$row;
        }
        $data['data']=$rows;

        return response()->success('Success', $data);
    }

    public function query_route(Request $request)
    {
        $filter = $request->filter;
        $limit = $request->limit;
        $sorting = ($request->descending == "true") ?'desc':'asc';
        $sortBy = $request->sortBy;
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $pool_code = $request->pool_code;
        $data= Operation::from('t_operation as a')
        ->selectRaw("a.route_id AS _index,b.route_name,SUM(a.passenger) AS passenger,SUM(a.total) AS total,
                    SUM(a.dispensation) AS dispensation,SUM(a.paid) AS paid,SUM(a.unpaid) AS unpaid")
        ->leftJoin('m_bus_route as b','a.route_id','=','b.sysid')
        ->where('a.ref_date', '>=', $start_date)
        ->where('a.ref_date', '<=', $end_date)
        ->where('a.is_cancel','0');
        if (!($pool_code=='ALL')){
            $data=$data->where('a.pool_code',$pool_code);
        }
        if (!($filter == '')) {
            $filter = '%' . trim($filter) . '%';
            $data = $data->where(function ($q) use ($filter) {
                $q->where('a.doc_number', 'like', $filter)
                    ->orwhere('a.doc_number', 'like', $filter)
                    ->orwhere('a.vehicle_no', 'like', $filter)
                    ->orwhere('a.police_no', 'like', $filter)
                    ->orwhere('a.pool_code', 'like', $filter);
            });
        }
        $data=$data->groupBy('a.route_id')->groupBy('b.route_name');
        $data = $data->orderBy($sortBy,$sorting)->paginate($limit);
        return response()->success('Success', $data);
    }
    public function report_route(Request $request)
    {
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $pool_code = $request->pool_code;
        $data= Operation::from('t_operation as a')
        ->selectRaw("a.route_id AS _index,b.route_name,SUM(a.passenger) AS passenger,SUM(a.total) AS total,
                    SUM(a.dispensation) AS dispensation,SUM(a.paid) AS paid,SUM(a.unpaid) AS unpaid")
        ->leftJoin('m_bus_route as b','a.route_id','=','b.sysid')
        ->where('a.ref_date', '>=', $start_date)
        ->where('a.ref_date', '<=', $end_date)
        ->where('a.is_cancel','0');
        if (!($pool_code=='ALL')){
            $data=$data->where('a.pool_code',$pool_code);
        }
        $data=$data->groupBy('a.route_id')->groupBy('b.route_name')->get();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet(0);
        \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder( new \PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder());

        $sheet->setCellValue('A1', 'LAPORAN REKAP PER RUTE');
        $sheet->setCellValue('A2', 'PERIODE');
        $sheet->setCellValue('B2', ': '.date_format(date_create($start_date),"d-m-Y").' s/d '.date_format(date_create($end_date),"d-m-Y"));
        if ($pool_code=='ALL') {
            $sheet->setCellValue('A3', 'POOL');
            $sheet->setCellValue('B3', ': SEMUA POOL');
        } else {
            $sheet->setCellValue('A3', 'POOL');
            $sheet->setCellValue('B3', ': '.$pool_code);
        }
        $sheet->setCellValue('A5', 'Rute');
        $sheet->setCellValue('B5', 'Pendapatan');
        $sheet->setCellValue('C5', 'Dispensasi');
        $sheet->setCellValue('D5', 'Penerimaan');
        $sheet->setCellValue('E5', 'Kurang Setor');
        $sheet->getStyle('A5:E5')->getAlignment()->setHorizontal('center');

        $idx=5;
        foreach($data as $row){
            $idx=$idx+1;
            $sheet->setCellValue('A'.$idx, $row->route_name);
            $sheet->setCellValue('B'.$idx, $row->total);
            $sheet->setCellValue('C'.$idx, $row->dispensation);
            $sheet->setCellValue('D'.$idx, $row->paid);
            $sheet->setCellValue('E'.$idx, $row->unpaid);
        }
        $last=$idx;
        $idx=$idx+1;
        $sheet->setCellValue('A'.$idx, "TOTAL");
        $sheet->setCellValue('B'.$idx, "=SUM(B6:B$last)");
        $sheet->setCellValue('C'.$idx, "=SUM(C6:C$last)");
        $sheet->setCellValue('D'.$idx, "=SUM(D6:D$last)");
        $sheet->setCellValue('E'.$idx, "=SUM(E6:E$last)");
        $sheet->getStyle('B6:E'.$idx)->getNumberFormat()->setFormatCode('#,##0;[RED](#,##0)');
        // Formater
        $sheet->getStyle('A1:E5')->getFont()->setBold(true);
        $sheet->getStyle('A'.$idx.':'.'E'.$idx)->getFont()->setBold(true);
        $styleArray = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        ],
                    ],
                ];
        $sheet->getStyle('A5:E'.$idx)->applyFromArray($styleArray);
        foreach(range('B','E') as $columnID) {
             $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }
        $sheet->getColumnDimension('A')->setWidth(30);
        ob_end_clean();
        $response = response()->streamDownload(function() use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(true);
            $writer->save('php://output');
        });
        $xls="laporan_rekap_rute_".$start_date.'_'.$end_date.".xlsx";
        PagesHelp::Response($response,$xls)->send();
    }

    public function query_unit(Request $request)
    {
        $filter = $request->filter;
        $limit = $request->limit;
        $sorting = ($request->descending == "true") ?'desc':'asc';
        $sortBy = $request->sortBy;
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $pool_code = $request->pool_code;
        $data= Operation::from('t_operation as a')
        ->selectRaw("b.sysid AS _index,b.vehicle_no,b.police_no,b.descriptions,IFNULL(c.route_name,'') AS route_name,
                    SUM(a.passenger) AS passenger,SUM(a.total) AS total,
                    SUM(a.dispensation) AS dispensation,SUM(a.paid) AS paid,SUM(a.unpaid) AS unpaid")
        ->leftJoin('m_vehicle as b','a.vehicle_no','=','b.vehicle_no')
        ->leftJoin('m_bus_route as c','b.default_route_id','=','c.sysid')
        ->where('a.ref_date', '>=', $start_date)
        ->where('a.ref_date', '<=', $end_date)
        ->where('a.is_cancel','0');
        if (!($pool_code=='ALL')){
            $data=$data->where('a.pool_code',$pool_code);
        }
        if (!($filter == '')) {
            $filter = '%' . trim($filter) . '%';
            $data = $data->where(function ($q) use ($filter) {
                $q->where('a.doc_number', 'like', $filter)
                    ->orwhere('a.doc_number', 'like', $filter)
                    ->orwhere('a.vehicle_no', 'like', $filter)
                    ->orwhere('a.police_no', 'like', $filter)
                    ->orwhere('a.pool_code', 'like', $filter);
            });
        }
        $data=$data->groupBy('b.sysid')
            ->groupBy('b.vehicle_no')
            ->groupBy('b.police_no')
            ->groupBy('b.descriptions')
            ->groupBy('c.route_name')
            ->orderBy($sortBy,$sorting)->paginate($limit);
        return response()->success('Success', $data);
    }
    public function report_unit(Request $request)
    {
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $pool_code = $request->pool_code;
        $data= Operation::from('t_operation as a')
        ->selectRaw("b.sysid AS _index,b.vehicle_no,b.police_no,b.descriptions,IFNULL(c.route_name,'') AS route_name,
                    SUM(a.passenger) AS passenger,SUM(a.total) AS total,
                    SUM(a.dispensation) AS dispensation,SUM(a.paid) AS paid,SUM(a.unpaid) AS unpaid")
        ->leftJoin('m_vehicle as b','a.vehicle_no','=','b.vehicle_no')
        ->leftJoin('m_bus_route as c','b.default_route_id','=','c.sysid')
        ->where('a.ref_date', '>=', $start_date)
        ->where('a.ref_date', '<=', $end_date)
        ->where('a.is_cancel','0');
        if (!($pool_code=='ALL')){
            $data=$data->where('a.pool_code',$pool_code);
        }
        $data=$data->groupBy('b.sysid')
            ->groupBy('b.vehicle_no')
            ->groupBy('b.police_no')
            ->groupBy('b.descriptions')
            ->groupBy('c.route_name')
            ->get();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet(0);
        \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder( new \PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder());

        $sheet->setCellValue('A1', 'LAPORAN REKAP PER UNIT');
        $sheet->setCellValue('A2', 'PERIODE');
        $sheet->setCellValue('B2', ': '.date_format(date_create($start_date),"d-m-Y").' s/d '.date_format(date_create($end_date),"d-m-Y"));
        if ($pool_code=='ALL') {
            $sheet->setCellValue('A3', 'POOL');
            $sheet->setCellValue('B3', ': SEMUA POOL');
        } else {
            $sheet->setCellValue('A3', 'POOL');
            $sheet->setCellValue('B3', ': '.$pool_code);
        }
        $sheet->setCellValue('A5', 'No.Unit');
        $sheet->setCellValue('B5', 'No.Polisi');
        $sheet->setCellValue('C5', 'Keterangan');
        $sheet->setCellValue('D5', 'Rute Utama(Default)');
        $sheet->setCellValue('E5', 'Pendapatan');
        $sheet->setCellValue('F5', 'Dispensasi');
        $sheet->setCellValue('G5', 'Penerimaan');
        $sheet->setCellValue('H5', 'Kurang Setor');
        $sheet->getStyle('A5:H5')->getAlignment()->setHorizontal('center');

        $idx=5;
        foreach($data as $row){
            $idx=$idx+1;
            $sheet->setCellValue('A'.$idx, $row->vehicle_no);
            $sheet->setCellValue('B'.$idx, $row->police_no);
            $sheet->setCellValue('C'.$idx, $row->descriptions);
            $sheet->setCellValue('D'.$idx, $row->route_name);
            $sheet->setCellValue('E'.$idx, $row->total);
            $sheet->setCellValue('F'.$idx, $row->dispensation);
            $sheet->setCellValue('G'.$idx, $row->paid);
            $sheet->setCellValue('H'.$idx, $row->unpaid);
        }
        $last=$idx;
        $idx=$idx+1;
        $sheet->setCellValue('D'.$idx, "TOTAL");
        $sheet->setCellValue('E'.$idx, "=SUM(E6:E$last)");
        $sheet->setCellValue('F'.$idx, "=SUM(F6:F$last)");
        $sheet->setCellValue('G'.$idx, "=SUM(G6:F$last)");
        $sheet->setCellValue('H'.$idx, "=SUM(H6:H$last)");
        $sheet->getStyle('E6:H'.$idx)->getNumberFormat()->setFormatCode('#,##0;[RED](#,##0)');
        // Formater
        $sheet->getStyle('A1:H5')->getFont()->setBold(true);
        $sheet->getStyle('A'.$idx.':'.'H'.$idx)->getFont()->setBold(true);
        $styleArray = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        ],
                    ],
                ];
        $sheet->getStyle('A5:H'.$idx)->applyFromArray($styleArray);
        foreach(range('C','H') as $columnID) {
             $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }
        $sheet->getColumnDimension('A')->setWidth(30);
        ob_end_clean();
        $response = response()->streamDownload(function() use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(true);
            $writer->save('php://output');
        });
        $xls="laporan_rekap_unit_".$start_date.'_'.$end_date.".xlsx";
        PagesHelp::Response($response,$xls)->send();
    }

}
