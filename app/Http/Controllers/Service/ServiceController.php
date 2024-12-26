<?php

namespace App\Http\Controllers\Service;

use App\Models\Master\Driver;
use App\Models\Master\Vehicle;
use App\Models\Master\Partner;
use App\Models\Master\VehicleService;
use App\Models\Service\Service;
use App\Models\Service\ServicePeriodics;
use App\Models\Service\ServicePeriodicsDetail;
use App\Models\Service\ServiceJobs;
use App\Models\Service\ServiceMaterial;
use App\Models\Service\GoodsRequest1;
use App\Models\Purchase\JobInvoice1;
use App\Models\Config\Users;
use App\Models\Ops\Operation;
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
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Writer as Writer;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ServiceController extends Controller
{
    public function show(Request $request){
        $filter = $request->filter;
        $limit = $request->limit;
        $isactive = isset($request->isactive) ? $request->isactive : "0";
        $descending = $request->descending=="true";
        $sortBy = $request->sortBy;
        $pool_code = PagesHelp::PoolCode($request);

        $date1 = $request->start_date;
        $date2 = $request->end_date;
        if ($isactive=="1") {
            $data= Service::where('pool_code',$pool_code)
            ->whereRaw('is_closed=0')
            ->whereRaw('is_cancel=0');
        } else {
            $data= Service::where('pool_code',$pool_code)
            ->where('ref_date','>=',$date1)
            ->where('ref_date','<=',$date2);
        }
        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where(function($q) use ($filter) {
                    $q->where('doc_number','like',$filter)
                   ->orwhere('police_no','like',$filter);
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

    public function destroy(Request $request){
        $id=isset($request->sysid) ? $request->sysid :'-1';
        $notes=isset($request->notes) ? $request->notes :'';
        $ctrl=Service::selectRaw("sysid,is_closed,is_cancel,vehicle_no")->where('sysid',$id)->first();
        if ($ctrl) {
            $material=GoodsRequest1::where('service_no',$ctrl->service_no)
            ->where('is_approved',1)->first();
            if ($material){
                return response()->success('Success','Workorder service tidak bisa dibatalkan,sudah ada pengeluaran barang');
            } else if($ctrl->is_closed=='1'){
                return response()->success('Success','Workorder service tidak bisa dibatalkan,sudah selesai');
            } else if($ctrl->is_cancel=='1'){
                return response()->success('Success','Workorder service tidak bisa dibatalkan,sudah dibatalkan');
            }

            DB::beginTransaction();
            try {
                Service::where('sysid',$id)
                ->update([
                    'is_cancel'=>'1',
                    'cancel_date'=>Date('Y-m-d H:i:s'),
                    'cancel_reason'=>$notes
                ]);
                VehicleService::where('vehicle_no',$ctrl->vehicle_no)->delete();
                Vehicle::where('vehicle_no',$ctrl->vehicle_no)
                        ->where('vehicle_status','Service')
                        ->update(['vehicle_status'=>'Pengecekan']);
                DB::commit();
                return response()->success('Success','Workorder service berhasil dibatalkan');
            } catch(Exception $e){
                DB::rollback();
                return response()->error('',501,$e);
            }
        } else {
            return response()->error('',501,'Data tidak ditemukan');
        }
    }

    public function reopen(Request $request){
        $id=isset($request->sysid) ? $request->sysid :'-1';
        $ctrl=Service::selectRaw("sysid,doc_number,is_closed,is_cancel,vehicle_no")->where('sysid',$id)->first();
        if ($ctrl) {
            if ($ctrl->is_closed=='0') {
                return response()->success('Success','Workorder service masih dalam status open');
            } else if($ctrl->is_cancel=='1'){
                return response()->success('Success','Workorder service tidak dibuka kembali,sudah dibatalkan');
            }

            DB::beginTransaction();
            try {
                Service::where('sysid',$id)
                ->update([
                    'is_closed'=>'0'
                ]);
                DB::update("UPDATE t_inventory_booked1 SET is_autoclosed=0
                           WHERE service_no=? AND is_approved=0",[$ctrl->doc_number]);
                DB::commit();
                return response()->success('Success','Workorder service berhasil dibuka kembali');
            } catch(Exception $e){
                DB::rollback();
                return response()->error('',501,$e);
            }
        } else {
            return response()->error('',501,'Data tidak ditemukan');
        }
    }

    public function get(Request $request){
        $id=isset($request->id) ? $request->id :'';
        $data['header']=Service::where('sysid',$id)->first();
        $data['jobs']=ServiceJobs::selectRaw('item_code,line_no,line_notes,descriptions,estimate_time,notes')
            ->where('sysid',$id)
            ->get();
        $data['periodic']=ServicePeriodics::selectRaw('service_code,descriptions')
            ->where('sysid',$id)
            ->get();
        $data['periodic_detail']=ServicePeriodicsDetail::selectRaw("sysid,group_line,group_name,item_line,descriptions,is_service,notes,IFNULL(notes,'') as notes")
        ->orderby("group_line","asc")
        ->orderby("item_line","asc")
        ->where('sysid',$id)->get();
        return response()->success('Success',$data);
    }

    public function getdocservice(Request $request){
        $pool_code = PagesHelp::PoolCode($request);
        $data=Service::selectRaw("doc_number,CONCAT(doc_number,'-',vehicle_no,'-',IFNULL(police_no,'')) as descriptions,vehicle_no,police_no")
        ->where('pool_code',$pool_code)
        ->where('is_closed',0)
        ->where('is_cancel',0)
        ->get();
        return response()->success('Success',$data);
    }
    public function post(Request $request){
        $data= $request->json()->all();
        $opr=$data['operation'];
        $where=$data['where'];
        $header=$data['data'];
        $jobs=$data['jobs'];
        $periodic=$data['periodic'];
        $header['pool_code'] = PagesHelp::PoolCode($request);
        $header['service_planning']='-';
        if ($opr=='updated'){
            $service=Service::where($where)->first();
            if ($service){
                if ($service->is_closed=='1'){
                    return response()->error('',400,"Workorder tersebut sudah selesai");
                }
            }
        }
        $validator=Validator::make($header,[
            'ref_date'=>'bail|required',
            'ref_time'=>'bail|required',
            'planning_date'=>'bail|required',
            'planning_time'=>'bail|required',
            'estimate_date'=>'bail|required',
            'estimate_time'=>'bail|required',
            'pool_code'=>'bail|required',
            'service_advisor'=>'bail|required',
            'user_service'=>'bail|required',
            'service_planning'=>'bail|required',
            'vehicle_no'=>'bail|required',
        ],[
            'ref_date.required'=>'Tanggal harus diisi',
            'ref_time.required'=>'Jam harus diisi',
            'planning_date.required'=>'Tanggal rencana service harus diisi',
            'planning_time.required'=>'Jam rencana service harus diisi',
            'estimate_date.required'=>'Tanggal estimasi selesai harus diisi',
            'estimate_time.required'=>'Jam estimasi selesai harus diisi',
            'service_advisor.required'=>'Kepala mekanik harus diisi',
            'user_service.required'=>'Mekanik harus diisi',
            'service_planning.required'=>'Rencana kerja harus diisi',
            'vehicle_no.required'=>'Unit kendaraan harus diisi',
        ]);

        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }

        $ops=Operation::selectRaw("doc_number,vehicle_no")->where('vehicle_no',$header['vehicle_no'])
        ->where('is_ops_closed',0)
        ->where('is_cancel',0)->first();
        if ($ops) {
            return response()->error('',501,'Unit '.$ops->vehicle_no.' sedang dalam operasi SPJ '.$ops->doc_number);
        }

        if ($opr=='updated'){
            $wo=Service::selectRaw("doc_number,ref_date,is_closed,is_cancel")->where('sysid',$header['sysid'])->first();
            if (($wo->is_closed=='1') || ($wo->is_cancel=='1')) {
                return response()->error('Gagal',501,'Data tidak bisa diupdate, WO sudah selesai/dibatalkan');
            }
            if ($wo->ref_date>$header['ref_date']){
                return response()->error('Gagal',501,'Tanggal transaksi tidak bisa mundur');
            }
        } else {
            $cur_date=Date('Y-m-d');
            if ($header['ref_date']<$cur_date) {
                return response()->error('Gagal',501,'Tanggal transaksi tidak bisa mundur');
            }
        }

        DB::beginTransaction();
        try{
            $sysid=$header['sysid'];
            $header['update_userid'] = PagesHelp::UserID($request);
            $header['status'] = 'Permintaan';
            $unit=Vehicle::select('police_no')->where('vehicle_no',$header['vehicle_no'])->first();
            if ($unit){
                $header['police_no']=$unit->police_no;
            }
            unset($header['sysid']);
            if ($opr=='updated'){
                $service=Service::where($where)->first();
                VehicleService::where('vehicle_no',$service['vehicle_no'])->delete();
                Service::where($where)->update($header);
                ServiceJobs::where('sysid',$sysid)->delete();
                ServicePeriodics::where('sysid',$sysid)->delete();
                ServicePeriodicsDetail::where('sysid',$sysid)->delete();
            } else if ($opr='inserted'){
                $number=Service::GenerateNumber($header['pool_code'],$header['ref_date']);
                $header['doc_number']=$number;
                $sysid=Service::insertGetId($header);
            }
            foreach($jobs as $record){
                $dtl=(array)$record;
                $dtl['sysid']=$sysid;
                ServiceJobs::insert($dtl);
            }
            $periodic_code="";
            foreach($periodic as $record){
                $dtl=(array)$record;
                $dtl['sysid']=$sysid;
                $periodic_code=$record['service_code'];
                ServicePeriodics::insert($dtl);
            }
            VehicleService::insert([
                'vehicle_no'=>$header['vehicle_no'],
                'planning_date'=>$header['planning_date'],
                'planning_time'=>$header['planning_time'],
                'estimate_date'=>$header['estimate_date'],
                'estimate_time'=>$header['estimate_time'],
                'problem'=>$header['problem'],
                'service_planning'=>$header['service_planning'],
                'mechanic'=>$header['user_service'],
                'status'=>$header['status'],
            ]);
            if (!($periodic_code=="")){
                DB::insert("INSERT INTO t_workorder_periodic_detail(sysid,group_line,group_name,item_line,descriptions,is_service)
                SELECT ?,a.group_line,a.group_name,a.item_line,a.descriptions,0 FROM m_service_periodic_dtl a
                INNER JOIN `m_service_periodic` b ON a.sysid=b.sysid
                WHERE b.service_code=?",[$sysid,$periodic_code]);
            }
            DB::update("UPDATE m_vehicle a INNER JOIN t_workorder_service b ON a.vehicle_no=b.vehicle_no
                    SET a.vehicle_status='Service'
                    WHERE b.sysid=?",[$sysid]);
            DB::update("UPDATE t_workorder_jobs a INNER JOIN m_job_group b ON a.item_code=b.item_code
                    SET a.descriptions=b.descriptions
                    WHERE a.sysid=?",[$sysid]);
            DB::commit();
            return response()->success('Success','Simpan data Berhasil');
		} catch (Exception $e) {
            DB::rollback();
            return response()->error('',501,$e);
        }
    }

    public function postreport(Request $request){
        $data= $request->json()->all();
        $opr=$data['operation'];
        $where=$data['where'];
        $header=$data['data'];
        $jobs=$data['jobs'];
        $item=$data['item'];
        $periodicdetail=$data['periodicdetail'];
        $periodic=$data['periodic'];
        $header['pool_code'] = PagesHelp::PoolCode($request);
        $header['service_planning']='-';
        if ($opr=='updated'){
            $service=Service::where($where)->first();
            if ($service){
                if ($service->is_closed=='1'){
                    return response()->error('',400,"Workorder tersebut sudah selesai");
                }
            }
        }
        $validator=Validator::make($header,[
            'ref_date'=>'bail|required',
            'ref_time'=>'bail|required',
            'planning_date'=>'bail|required',
            'planning_time'=>'bail|required',
            'estimate_date'=>'bail|required',
            'estimate_time'=>'bail|required',
            'pool_code'=>'bail|required',
            'service_advisor'=>'bail|required',
            'user_service'=>'bail|required',
            'service_planning'=>'bail|required',
            'vehicle_no'=>'bail|required',
        ],[
            'ref_date.required'=>'Tanggal harus diisi',
            'ref_time.required'=>'Jam harus diisi',
            'planning_date.required'=>'Tanggal rencana service harus diisi',
            'planning_time.required'=>'Jam rencana service harus diisi',
            'estimate_date.required'=>'Tanggal estimasi selesai harus diisi',
            'estimate_time.required'=>'Jam estimasi selesai harus diisi',
            'service_advisor.required'=>'Kepala mekanik harus diisi',
            'user_service.required'=>'Mekanik harus diisi',
            'service_planning.required'=>'Rencana kerja harus diisi',
            'vehicle_no.required'=>'Unit kendaraan harus diisi',
        ]);
        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }

        $wo=Service::selectRaw("doc_number,ref_date,is_closed,is_cancel")->where('sysid',$header['sysid'])->first();
        if (($wo->is_closed=='1') || ($wo->is_cancel=='1')) {
            return response()->error('Gagal',501,'Data tidak bisa diupdate, WO sudah selesai/dibatalkan');
        }
        if ($wo->ref_date>$header['ref_date']){
            return response()->error('Gagal',501,'Tanggal transaksi tidak bisa mundur');
        }

        DB::beginTransaction();
        try{
            $sysid=$header['sysid'];
            $header['update_userid'] = PagesHelp::UserID($request);
            $header['status'] = 'Permintaan';
            $unit=Vehicle::select('police_no')->where('vehicle_no',$header['vehicle_no'])->first();
            if ($unit){
                $header['police_no']=$unit->police_no;
            }
            unset($header['sysid']);
            if ($opr=='updated'){
                $service=Service::where($where)->first();
                VehicleService::where('vehicle_no',$service['vehicle_no'])->delete();
                Service::where($where)->update($header);
                ServiceJobs::where('sysid',$sysid)->delete();
                ServicePeriodics::where('sysid',$sysid)->delete();
            }
            foreach($jobs as $record){
                $dtl=(array)$record;
                $dtl['sysid']=$sysid;
                ServiceJobs::insert($dtl);
            }
            foreach($periodic as $record){
                $dtl=(array)$record;
                $dtl['sysid']=$sysid;
                ServicePeriodics::insert($dtl);
            }
            VehicleService::insert([
                'vehicle_no'=>$header['vehicle_no'],
                'planning_date'=>$header['planning_date'],
                'planning_time'=>$header['planning_time'],
                'estimate_date'=>$header['estimate_date'],
                'estimate_time'=>$header['estimate_time'],
                'problem'=>$header['problem'],
                'service_planning'=>$header['service_planning'],
                'mechanic'=>$header['user_service'],
                'status'=>$header['status'],
            ]);
            foreach($item as $row){
                ServiceMaterial::
                where('sysid',$sysid)
                ->where('item_code',$row['item_code'])
                ->update(['used'=>$row['used']]);
            }
            Vehicle::where('vehicle_no',$header['vehicle_no'])
                ->where('vehicle_status','Service')
                ->update(['vehicle_status'=>'Service',
                'km_service'=>$header['odo_service'],
                'next_km_service'=>$header['next_service']]);

            foreach($periodicdetail as $row) {
                ServicePeriodicsDetail::where('sysid',$sysid)
                ->where('group_line',$row['group_line'])
                ->where('item_line',$row['item_line'])
                ->update([
                    'is_service'=>$row['is_service'],
                    'notes'=>$row['notes']
                ]);
            }

            if ($header['is_closed']=='1') {
                VehicleService::where('vehicle_no',$header['vehicle_no'])->delete();
                Vehicle::where('vehicle_no',$header['vehicle_no'])
                        ->where('vehicle_status','Service')
                        ->update(['vehicle_status'=>'Pengecekan',
                                  'km_service'=>$header['odo_service'],
                                  'next_km_service'=>$header['next_service']]);
                DB::update("UPDATE t_inventory_booked1 SET is_autoclosed=1
                           WHERE service_no=? AND is_approved=0",[$header['doc_number']]);
                DB::commit();
                $respon['sysid']=$sysid;
                $respon['message']="Simpan data berhasil";
                return response()->success('Success', $respon);
            } else {
                DB::commit();
                $respon['sysid']=$sysid;
                $respon['message']="Simpan data berhasil";
                return response()->success('Success', $respon);
            }
		} catch (Exception $e) {
            DB::rollback();
            return response()->error('',501,$e);
        }
    }
    public function SummaryItem(Request $request){
        $service_no=isset($request->service_no) ? $request->service_no:'xxx';
        $data=ServiceMaterial::from('t_workorder_material as a')
            ->selectRaw('a.*,b.part_number')
            ->leftJoin('m_item as b','a.item_code','=','b.item_code')
            ->where('a.service_no',$service_no)->get();
        return response()->success('Success',$data);
    }
    public static function build_jurnal($sysid,$request) {
        /* Biaya Service
             Inventory
         */
        $ret['state']=true;
        $ret['message']='';
        $data=ServiceMaterial::from('t_workorder_material as a')
        ->selectRaw('a.service_no,b.ref_date,a.sysid_jurnal,a.trans_code,a.trans_series,a.warehouse_id')
        ->leftjoin('t_workorder_service as b','a.sysid','=','b.sysid')
        ->where('a.sysid',$sysid)->first();
        if ($data){
            $pool_code=PagesHelp::PoolCode($request);
            $detail=ServiceMaterial::from('t_workorder_material as a')
            ->selectRaw('a.item_code,a.descriptions,ABS(b.qty) as qty,b.item_cost,ABS(b.qty)*b.item_cost as line_cost,d.inv_account,d.cost_account')
            ->join('t_item_price as b', function($join)
                {
                    $join->on('a.sysid', '=', 'b.sysid');
                    $join->on('a.item_code', '=', 'b.item_code');
                    $join->on('b.doc_type','=',DB::raw("'IOS'"));
                })
            ->leftjoin('m_item as c','a.item_code','=','c.item_code')
            ->leftJoin('m_item_group_account as d', function($join) use($pool_code)
                {
                    $join->on('c.item_group', '=', 'd.item_group');
                    $join->on('d.pool_code','=',DB::raw("'$pool_code'"));
                })
            ->where('a.sysid',$sysid)
            ->get();
            $realdate = date_create($data->ref_date);
            $year_period = date_format($realdate, 'Y');
            $month_period = date_format($realdate, 'm');
            if ($data->sysid_jurnal==-1){
                $series = Journal1::GenerateNumber('IOS',$data->ref_date);
                $sysid_jurnal=Journal1::insertGetId([
                  'ref_date'=>$data->ref_date,
                  'pool_code'=>$data->pool_code,
                  'reference1'=>$data->service_no,
                  'reference2'=>$data->service_no,
                  'posting_date'=>$data->ref_date,
                  'is_posted'=>'1',
                  'trans_code'=>'IOS',
                  'trans_series'=>$series,
                  'fiscal_year'=>$year_period,
                  'fiscal_month'=>$month_period,
                  'transtype'=>'7',
                  'notes'=>'Pengeluran barang Service '.$data->service_no.', dari '.$data->warehouse_id
              ]);
            } else {
                $sysid_jurnal=$data->sysid_jurnal;
                $series=$data->trans_series;
                Accounting::rollback($sysid_jurnal);
                Journal1::where('sysid',$sysid_jurnal)
                ->update([
                  'ref_date'=>$data->ref_date,
                  'pool_code'=>$data->pool_code,
                  'reference1'=>$data->service_no,
                  'reference2'=>$data->service_no,
                  'posting_date'=>$data->ref_date,
                  'is_posted'=>'1',
                  'fiscal_year'=>$year_period,
                  'fiscal_month'=>$month_period,
                  'transtype'=>'7',
                  'notes'=>'Pengeluran barang '.$data->service_no.', dari '.$data->warehouse_src
                ]);
            }
            /* Cost
                Inventory */
            $line=0;
            $ontransfer=0;
            foreach($detail as $row){
                $line++;
                Journal2::insert([
                    'sysid'=>$sysid_jurnal,
                    'line_no'=>$line,
                    'no_account'=>$row->cost_account,
                    'line_memo'=>'Pengeluaran '.$row->item_code.' - '.$row->descriptions.' (Qty : '.number_format($row->qty,2,",",".").
                                ', Harga Stock : '.number_format($row->item_cost,2,",",".").')',
                    'reference1'=>$data->service_no,
                    'reference2'=>'-',
                    'debit'=>$row->line_cost,
                    'credit'=>0,
                    'project'=>PagesHelp::Project($data->pool_code)
                ]);
                $line++;
                Journal2::insert([
                    'sysid'=>$sysid_jurnal,
                    'line_no'=>$line,
                    'no_account'=>$row->inv_account,
                    'line_memo'=>'Pengeluaran '.$row->item_code.' - '.$row->descriptions.' (Qty : '.number_format($row->qty,2,",",".").
                                ', Harga Stock : '.number_format($row->item_cost,2,",",".").')',
                    'reference1'=>$data->service_no,
                    'reference2'=>'-',
                    'debit'=>0,
                    'credit'=>$row->line_cost,
                    'project'=>PagesHelp::Project($data->pool_code)
                ]);
            }
            $info=Accounting::posting($sysid_jurnal,$request);
            if ($info['state']==true){
                ServiceMaterial::where('sysid',$sysid)
                ->update(['sysid_jurnal'=>$sysid_jurnal,
                'trans_code'=>'IOS',
                'trans_series'=>$series]);
            }
            $ret['state']=$info['state'];
            $ret['message']=$info['message'];
        } else {
            $ret['state']=false;
            $ret['message']='Data tidak ditemukan';
        }
        return $ret;
    }
    public function printreport(Request $request){
        $sysid=$request->sysid;
        $header=Service::from('t_workorder_service as a')
            ->selectRaw("a.sysid,a.doc_number,a.ref_date,a.ref_time,a.vehicle_no,a.police_no,a.requester,
            a.problem,a.odo_service,IFNULL(a.report,'-') AS report,a.user_service,
            a.service_type,a.service_advisor,b.personal_name as mechanic_name,a.close_date,a.close_time,
            IFNULL(c.vin,'') as vin,IFNULL(c.chasis_no,'') as chasis_no,ADDTIME(planning_date,planning_time) as planning_date,
            ADDTIME(estimate_date,estimate_time) as estimate_date,ADDTIME(close_date,close_time) as close_date,
            d.user_name,a.update_timestamp")
            ->leftjoin('m_personal as b','a.user_service','=','b.employee_id')
            ->leftjoin('m_vehicle as c','a.vehicle_no','=','c.vehicle_no')
            ->leftjoin('o_users as d','a.update_userid','=','d.user_id')
            ->where('a.sysid',$sysid)->first();
        if (!($header==null)){
            $sign['mekanik']='';
            $sign['supervisor']='';
            $home = storage_path().'/app/';
            $user=Driver::select('sign')->where('employee_id',$header->user_service)->first();
            if ($user) {
                if ($user->sign<>'-'){
                    $sign['mekanik']=$home.$user->sign;
                }
            }
            $header->ref_date=date_format(date_create($header->ref_date),'d-m-Y');
            $header->planning_date=date_format(date_create($header->planning_date),'d-m-Y H:i');
            $header->estimate_date=date_format(date_create($header->estimate_date),'d-m-Y H:i');
            $header->close_date=date_format(date_create($header->close_date),'d-m-Y H:i');
            $header->update_timestamp=date_format(date_create($header->update_timestamp),'d-m-Y H:i');
            $material=ServiceMaterial::from("t_workorder_material as a")
            ->selectRaw("a.item_code,a.descriptions,a.mou_inventory,a.request,a.approved,a.used,a.item_cost,a.line_cost,
                        IFNULL(b.part_number,'-') as part_number")
            ->leftjoin("m_item as b","a.item_code","=","b.item_code")
            ->where('a.sysid',$header->sysid)->get();
            $outsite=JobInvoice1::from('t_job_invoice1 as a')
            ->selectRaw("b.item_code,b.descriptions,b.mou_inventory,b.qty_invoice,b.price,b.total,'-' as part_number")
            ->join("t_job_invoice2 as b","a.sysid","=","b.sysid")
            ->where("a.service_no",$header->doc_number)
            ->get();
            $periodic=ServicePeriodics::selectRaw("service_code,descriptions")
            ->where('sysid',$header->sysid)->get();
            $jobs=ServiceJobs::selectRaw("line_no,item_code,descriptions,line_notes,mechanic,estimate_time,notes")
            ->where('sysid',$header->sysid)->get();
            $detail=ServicePeriodicsDetail::where('sysid',$sysid)->get();
            $profile=PagesHelp::Profile();
            $pdf = PDF::loadView('workshop.ServiceReport',
            ['header'=>$header,'profile'=>$profile,'material'=>$material,
             'periodic'=>$periodic,'jobs'=>$jobs,'detail'=>$detail,
             'outsite'=>$outsite,'sign'=>$sign])->setPaper('A4','potrait');
            return $pdf->stream();
        } else {
            return response()->error('',501,'Data Tidak ditemukan');
        }
    }
    public function printworkorder(Request $request){
        $sysid=$request->sysid;
        $header=Service::from('t_workorder_service as a')
            ->selectRaw("a.sysid,a.doc_number,a.ref_date,a.ref_time,a.vehicle_no,a.police_no,a.requester,
            a.problem,a.odo_service,IFNULL(a.report,'-') AS report,a.user_service,
            a.service_type,a.service_advisor,b.personal_name as mechanic_name,a.close_date,a.close_time,
            IFNULL(c.vin,'') as vin,IFNULL(c.chasis_no,'') as chasis_no,ADDTIME(planning_date,planning_time) as planning_date,
            ADDTIME(estimate_date,estimate_time) as estimate_date,ADDTIME(close_date,close_time) as close_date,
            d.user_name,a.update_timestamp")
            ->leftjoin('m_personal as b','a.user_service','=','b.employee_id')
            ->leftjoin('m_vehicle as c','a.vehicle_no','=','c.vehicle_no')
            ->leftjoin('o_users as d','a.update_userid','=','d.user_id')
            ->where('a.sysid',$sysid)->first();
        if (!($header==null)){
            $sign['mekanik']='';
            $sign['supervisor']='';
            $home = storage_path().'/app/';
            $user=Driver::select('sign')->where('employee_id',$header->user_service)->first();
            if ($user) {
                if ($user->sign<>'-'){
                    $sign['mekanik']=$home.$user->sign;
                }
            }
            $header->ref_date=date_format(date_create($header->ref_date),'d-m-Y');
            $header->planning_date=date_format(date_create($header->planning_date),'d-m-Y H:i');
            $header->estimate_date=date_format(date_create($header->estimate_date),'d-m-Y H:i');
            $header->close_date=date_format(date_create($header->close_date),'d-m-Y H:i');
            $header->update_timestamp=date_format(date_create($header->update_timestamp),'d-m-Y H:i');
            $material=ServiceMaterial::from("t_workorder_material as a")
            ->selectRaw("a.item_code,a.descriptions,a.mou_inventory,a.request,a.approved,a.used,a.item_cost,a.line_cost,
                        IFNULL(b.part_number,'-') as part_number")
            ->leftjoin("m_item as b","a.item_code","=","b.item_code")
            ->where('a.sysid',$header->sysid)->get();
            $outsite=JobInvoice1::from('t_job_invoice1 as a')
            ->selectRaw("b.item_code,b.descriptions,b.mou_inventory,b.qty_invoice,b.price,b.total,'-' as part_number")
            ->join("t_job_invoice2 as b","a.sysid","=","b.sysid")
            ->where("a.service_no",$header->doc_number)
            ->get();
            $periodic=ServicePeriodics::selectRaw("service_code,descriptions")
            ->where('sysid',$header->sysid)->get();
            $jobs=ServiceJobs::selectRaw("line_no,item_code,descriptions,line_notes,mechanic,estimate_time,notes")
            ->where('sysid',$header->sysid)->get();
            $detail=ServicePeriodicsDetail::where('sysid',$sysid)->get();
            $profile=PagesHelp::Profile();
            $pdf = PDF::loadView('workshop.WorkOrder',
            ['header'=>$header,'profile'=>$profile,'material'=>$material,
             'periodic'=>$periodic,'jobs'=>$jobs,'detail'=>$detail,
             'outsite'=>$outsite,'sign'=>$sign])->setPaper('A4','potrait');
            return $pdf->stream();
        } else {
            return response()->error('',501,'Data Tidak ditemukan');
        }
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
        $start_date = $request->start_date;
        $data= Service::from('t_workorder_service as a')
        ->selectRaw("a.sysid AS _index,a.doc_number,a.vehicle_no,a.police_no,a.odo_service,a.next_service,
                a.service_type,ADDTIME(a.ref_date,a.ref_time) AS entry_date,ADDTIME(a.close_date,a.close_time) AS close_service,
                a.is_closed,a.service_advisor,a.pool_code,a.problem,a.update_userid,a.update_timestamp,
                CASE a.is_closed WHEN 1 THEN 'SELESAI' ELSE 'BELUM SELESAI' END as state")
        ->where('a.ref_date', '>=', $start_date)
        ->where('a.ref_date', '<=', $end_date);
        if (!($pool_code=='ALL')){
            $data=$data->where('a.pool_code',$pool_code);
        }
        if (!($filter == '')) {
            $filter = '%' . trim($filter) . '%';
            $data = $data->where(function ($q) use ($filter) {
                $q->where('a.doc_number', 'like', $filter)
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
        $data=$data->toArray();
        $rows=array();
        foreach($data['data'] as $row){
            $row['sparepart']=0;
            $row['ban']=0;
            $row['pelumas']=0;
            $row['external']=0;
            $costs=DB::table('t_inventory_booked1 as a')
            ->selectRaw("a.service_no,c.item_group,SUM(b.line_cost ) AS line_cost")
            ->leftjoin("t_inventory_booked2 as b","a.sysid","=","b.sysid")
            ->leftjoin("m_item as c","b.item_code","=","c.item_code")
            ->where("a.service_no",$row['doc_number'])
            ->where("a.is_approved",'1')
            ->groupByRaw("a.service_no,c.item_group")
            ->get();
            foreach($costs as $cost) {
                if ($cost->item_group=='100'){
                    $row['sparepart']=floatval($cost->line_cost);
                } else if ($cost->item_group=='200'){
                    $row['ban']=floatval($cost->line_cost);
                } else if ($cost->item_group=='300'){
                    $row['pelumas']=floatval($cost->line_cost);
                }
            }
            $jobs=JobInvoice1::selectRaw("service_no,SUM(total) as total")
            ->where("service_no",$row['doc_number'])
            ->groupByRaw("service_no")
            ->get();
            foreach($jobs as $job) {
                $row['external']=floatval($job->total);
            }
            $rows[]=$row;
        }
        $data['data']=$rows;
        return response()->success('Success', $data);
    }

    public function report(Request $request)
    {
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $pool_code = $request->pool_code;
        $start_date = $request->start_date;
        $data= Service::from('t_workorder_service as a')
        ->selectRaw("a.sysid AS _index,a.doc_number,a.vehicle_no,a.police_no,a.odo_service,a.next_service,
                a.service_type,ADDTIME(a.ref_date,a.ref_time) AS entry_date,ADDTIME(a.close_date,a.close_time) AS close_service,
                a.is_closed,a.service_advisor,a.pool_code,a.problem,a.update_userid,a.update_timestamp,
                CASE a.is_closed WHEN 1 THEN 'SELESAI' ELSE 'BELUM SELESAI' END as state")
        ->where('a.ref_date', '>=', $start_date)
        ->where('a.ref_date', '<=', $end_date);
        if (!($pool_code=='ALL')){
            $data=$data->where('a.pool_code',$pool_code);
        }
        $data=$data->get();
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet(0);
        \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder( new \PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder());

        $sheet->setCellValue('A1', 'LAPORAN SERVICE');
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
        $sheet->setCellValue('B5', 'No.Polisi');
        $sheet->setCellValue('C5', 'KM Service');
        $sheet->setCellValue('D5', 'No. Service');
        $sheet->setCellValue('E5', 'Tgl&Jam Masuk');
        $sheet->setCellValue('F5', 'Tgl&Jam Selesai');
        $sheet->setCellValue('G5', 'Tipe Service');
        $sheet->setCellValue('H5', 'Supervisor');
        $sheet->setCellValue('I5', 'Keluhan');
        $sheet->setCellValue('J5', 'Sparepart');
        $sheet->setCellValue('K5', 'Ban');
        $sheet->setCellValue('L5', 'Pelumas');
        $sheet->setCellValue('M5', 'External');
        $sheet->setCellValue('N5', 'Pool');
        $sheet->setCellValue('O5', 'Status');
        $sheet->setCellValue('P5', 'User Input');
        $sheet->getStyle('A5:P5')->getAlignment()->setHorizontal('center');

        $idx=5;
        foreach($data as $row){
            $row['sparepart']=0;
            $row['ban']=0;
            $row['pelumas']=0;
            $row['external']=0;
            $costs=DB::table('t_inventory_booked1 as a')
            ->selectRaw("a.service_no,c.item_group,SUM(b.line_cost ) AS line_cost")
            ->leftjoin("t_inventory_booked2 as b","a.sysid","=","b.sysid")
            ->leftjoin("m_item as c","b.item_code","=","c.item_code")
            ->where("a.service_no",$row['doc_number'])
            ->where("a.is_approved",'1')
            ->groupByRaw("a.service_no,c.item_group")
            ->get();
            foreach($costs as $cost) {
                if ($cost->item_group=='100'){
                    $row['sparepart']=floatval($cost->line_cost);
                } else if ($cost->item_group=='200'){
                    $row['ban']=floatval($cost->line_cost);
                } else if ($cost->item_group=='300'){
                    $row['pelumas']=floatval($cost->line_cost);
                }
            }
            $jobs=JobInvoice1::selectRaw("service_no,SUM(total) as total")
            ->where("service_no",$row['doc_number'])
            ->groupByRaw("service_no")
            ->get();
            foreach($jobs as $job) {
                $row['external']=floatval($job->total);
            }
            $idx=$idx+1;
            $sheet->setCellValue('A'.$idx, $row->vehicle_no);
            $sheet->setCellValue('B'.$idx, $row->police_no);
            $sheet->setCellValue('C'.$idx, $row->odo_service);
            $sheet->setCellValue('D'.$idx, $row->doc_number);
            $sheet->setCellValue('E'.$idx, $row->entry_date);
            $sheet->setCellValue('F'.$idx, $row->close_service);
            $sheet->setCellValue('G'.$idx, $row->service_type);
            $sheet->setCellValue('H'.$idx, $row->service_advisor);
            $sheet->setCellValue('I'.$idx, $row->problem);
            $sheet->setCellValue('J'.$idx, $row->sparepart);
            $sheet->setCellValue('K'.$idx, $row->ban);
            $sheet->setCellValue('L'.$idx, $row->pelumas);
            $sheet->setCellValue('M'.$idx, $row->external);
            $sheet->setCellValue('N'.$idx, $row->pool_code);
            $sheet->setCellValue('O'.$idx, $row->state);
            $sheet->setCellValue('P'.$idx, $row->update_userid);
        }

        $sheet->getStyle('A6:D'.$idx)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
        $sheet->getStyle('E6:F'.$idx)->getNumberFormat()->setFormatCode('dd-mm-yyyy HH:MM');
        $last=$idx;
        $idx=$idx+1;
        $sheet->setCellValue('I'.$idx, "TOTAL");
        $sheet->setCellValue('J'.$idx, "=SUM(J6:J$last)");
        $sheet->setCellValue('K'.$idx, "=SUM(K6:K$last)");
        $sheet->setCellValue('L'.$idx, "=SUM(L6:L$last)");
        $sheet->setCellValue('M'.$idx, "=SUM(M6:M$last)");
        $sheet->getStyle('J6:M'.$idx)->getNumberFormat()->setFormatCode('#,##0;[RED](#,##0)');
        // Formater
        $sheet->getStyle('A1:P5')->getFont()->setBold(true);
        $styleArray = [
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        ],
                    ],
                ];
        $sheet->getStyle('A5:P'.$idx)->applyFromArray($styleArray);
        foreach(range('C','P') as $columnID) {
             $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }
        $sheet->getColumnDimension('A')->setWidth(10);
        $sheet->getColumnDimension('B')->setWidth(12);
        ob_end_clean();
        $response = response()->streamDownload(function() use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(true);
            $writer->save('php://output');
        });
        $xls="laporan_service_".$start_date.'_'.$end_date.".xlsx";
        PagesHelp::Response($response,$xls)->send();
    }


    public function query2(Request $request)
    {
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending == "true";
        $sortBy = $request->sortBy;
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $pool_code = $request->pool_code;
        $data= Vehicle::from('m_vehicle as a')
            ->selectRaw("a.sysid as _index,a.vehicle_no,police_no,a.year_production, 0 as ritase,0 as revenue,0 as dispensation,0 as sparepart,
                0 as ban, 0 as pelumas, 0 as external,0 as total,a.pool_code")
            ->where('is_active','1');
        if (!($pool_code=='ALL')){
            $data=$data->where('a.pool_code',$pool_code);
        }
        if (!($filter == '')) {
            $filter = '%' . trim($filter) . '%';
            $data = $data->where(function ($q) use ($filter) {
                $q->where('a.vehicle_no', 'like', $filter)
                    ->orwhere('a.police_no', 'like', $filter);
            });
        }
        if ($sortBy!=''){
            if ($sortBy=='ref_date') {
                $sortBy='a.vehicle_no';
            } else {
                $sortBy='a.'.$sortBy;
            }
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
        $data=$data->toArray();
        $rows=array();
        foreach($data['data'] as $row){
            $dtops=DB::table('t_operation')
                ->selectRaw("vehicle_no,COUNT(*) AS ritase,SUM(dispensation) AS dispensation,SUM(paid) AS revenue")
                ->where("ref_date",'>=',$start_date)
                ->where("ref_date",'<=',$end_date)
                ->where("vehicle_no",$row['vehicle_no'])
                ->where("is_cancel",'0')
                ->groupByRaw("vehicle_no")
                ->get();
            foreach($dtops as $ops) {
                $row['ritase']=floatval($ops->ritase);
                $row['dispensation']=floatval($ops->dispensation);
                $row['revenue']=floatval($ops->revenue);
            }
            $costs=DB::table('t_inventory_booked1 as a')
            ->selectRaw("a.vehicle_no,c.item_group,SUM(b.line_cost ) AS line_cost")
            ->leftjoin("t_inventory_booked2 as b","a.sysid","=","b.sysid")
            ->leftjoin("m_item as c","b.item_code","=","c.item_code")
            ->where("a.ref_date",'>=',$start_date)
            ->where("a.ref_date",'<=',$end_date)
            ->where("a.vehicle_no",$row['vehicle_no'])
            ->where("a.is_approved",'1')
            ->groupByRaw("a.vehicle_no,c.item_group")
            ->get();
            foreach($costs as $cost) {
                if ($cost->item_group=='100'){
                    $row['sparepart']=floatval($cost->line_cost);
                } else if ($cost->item_group=='200'){
                    $row['ban']=floatval($cost->line_cost);
                } else if ($cost->item_group=='300'){
                    $row['pelumas']=floatval($cost->line_cost);
                }
            }
            $jobs=JobInvoice1::selectRaw("vehicle_no,SUM(total) as total")
            ->where("ref_date",'>=',$start_date)
            ->where("ref_date",'<=',$end_date)
            ->where("vehicle_no",$row['vehicle_no'])
            ->where("is_void",'0')
            ->groupByRaw("vehicle_no")
            ->get();
            foreach($jobs as $job) {
                $row['external']=floatval($job->total);
            }
            $row['total']=$row['sparepart']+$row['ban']+$row['pelumas']+$row['external'];
            $rows[]=$row;
        }
        $data['data']=$rows;
        return response()->success('Success', $data);
    }

    public function report2(Request $request)
    {
        $sortBy = 'vehicle_no';
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $pool_code = $request->pool_code;
        $data= Vehicle::from('m_vehicle as a')
            ->selectRaw("a.sysid as _index,a.vehicle_no,police_no,a.year_production, 0 as ritase,0 as revenue,0 as dispensation,0 as sparepart,
                0 as ban, 0 as pelumas, 0 as external,0 as total,a.pool_code")
            ->where('is_active','1');
        if (!($pool_code=='ALL')){
            $data=$data->where('a.pool_code',$pool_code);
        }
        $data=$data->orderBy('a.vehicle_no')->get();
        $data=$data->toArray();
        $rows=array();
        foreach($data as $row){
            $dtops=DB::table('t_operation')
                ->selectRaw("vehicle_no,COUNT(*) AS ritase,SUM(dispensation) AS dispensation,SUM(paid) AS revenue")
                ->where("ref_date",'>=',$start_date)
                ->where("ref_date",'<=',$end_date)
                ->where("vehicle_no",$row['vehicle_no'])
                ->where("is_cancel",'0')
                ->groupByRaw("vehicle_no")
                ->get();
            foreach($dtops as $ops) {
                $row['ritase']=floatval($ops->ritase);
                $row['dispensation']=floatval($ops->dispensation);
                $row['revenue']=floatval($ops->revenue);
            }
            $rows[]=$row;
        }
        $data=$rows;
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet(0);
        \PhpOffice\PhpSpreadsheet\Cell\Cell::setValueBinder( new \PhpOffice\PhpSpreadsheet\Cell\AdvancedValueBinder());

        $sheet->setCellValue('A1', 'LAPORAN SERVICE (SUMMARY)');
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
        $sheet->setCellValue('B5', 'No.Polisi');
        $sheet->setCellValue('C5', 'Tahun');
        $sheet->setCellValue('D5', 'Ritase');
        $sheet->setCellValue('E5', 'Sparepart');
        $sheet->setCellValue('F5', 'Ban');
        $sheet->setCellValue('G5', 'Pelumas');
        $sheet->setCellValue('H5', 'External');
        $sheet->setCellValue('I5', 'Total');
        $sheet->setCellValue('J5', 'Pool');
        $sheet->getStyle('A5:J5')->getAlignment()->setHorizontal('center');

        $idx=5;
        foreach($data as $row){
            $row['sparepart']=0;
            $row['ban']=0;
            $row['pelumas']=0;
            $row['external']=0;
            $costs=DB::table('t_inventory_booked1 as a')
            ->selectRaw("a.vehicle_no,c.item_group,SUM(b.line_cost ) AS line_cost")
            ->leftjoin("t_inventory_booked2 as b","a.sysid","=","b.sysid")
            ->leftjoin("m_item as c","b.item_code","=","c.item_code")
            ->where("a.ref_date",'>=',$start_date)
            ->where("a.ref_date",'<=',$end_date)
            ->where("a.vehicle_no",$row['vehicle_no'])
            ->where("a.is_approved",'1')
            ->groupByRaw("a.vehicle_no,c.item_group")
            ->get();
            foreach($costs as $cost) {
                if ($cost->item_group=='100'){
                    $row['sparepart']=floatval($cost->line_cost);
                } else if ($cost->item_group=='200'){
                    $row['ban']=floatval($cost->line_cost);
                } else if ($cost->item_group=='300'){
                    $row['pelumas']=floatval($cost->line_cost);
                }
            }
            $jobs=JobInvoice1::selectRaw("vehicle_no,SUM(total) as total")
            ->where("ref_date",'>=',$start_date)
            ->where("ref_date",'<=',$end_date)
            ->where("vehicle_no",$row['vehicle_no'])
            ->where("is_void",'0')
            ->groupByRaw("vehicle_no")
            ->get();
            foreach($jobs as $job) {
                $row['external']=floatval($job->total);
            }
            $row['total']=$row['sparepart']+$row['ban']+$row['pelumas']+$row['external'];

            $idx=$idx+1;
            $sheet->setCellValue('A'.$idx, $row['vehicle_no']);
            $sheet->setCellValue('B'.$idx, $row['police_no']);
            $sheet->setCellValue('C'.$idx, $row['year_production']);
            $sheet->setCellValue('D'.$idx, $row['ritase']);
            $sheet->setCellValue('E'.$idx, $row['sparepart']);
            $sheet->setCellValue('F'.$idx, $row['ban']);
            $sheet->setCellValue('G'.$idx, $row['pelumas']);
            $sheet->setCellValue('H'.$idx, $row['external']);
            $sheet->setCellValue('I'.$idx, $row['total']);
            $sheet->setCellValue('J'.$idx, $row['pool_code']);
        }

        $sheet->getStyle('A6:C'.$idx)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
        $last=$idx;
        $idx=$idx+1;
        $sheet->setCellValue('D'.$idx, "TOTAL");
        $sheet->setCellValue('E'.$idx, "=SUM(E6:E$last)");
        $sheet->setCellValue('F'.$idx, "=SUM(F6:F$last)");
        $sheet->setCellValue('G'.$idx, "=SUM(G6:G$last)");
        $sheet->setCellValue('H'.$idx, "=SUM(H6:H$last)");
        $sheet->setCellValue('I'.$idx, "=SUM(I6:I$last)");
        $sheet->getStyle('E6:I'.$idx)->getNumberFormat()->setFormatCode('#,##0;[RED](#,##0)');
        // Formater
        $sheet->getStyle('A1:J5')->getFont()->setBold(true);
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
        $sheet->getColumnDimension('A')->setWidth(10);
        $sheet->getColumnDimension('B')->setWidth(12);
        ob_end_clean();
        $response = response()->streamDownload(function() use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->setPreCalculateFormulas(true);
            $writer->save('php://output');
        });
        $xls="laporan_service_".$start_date.'_'.$end_date.".xlsx";
        PagesHelp::Response($response,$xls)->send();
    }
}
