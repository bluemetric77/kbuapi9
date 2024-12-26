<?php

namespace App\Http\Controllers\Ops;

use App\Models\Ops\Operation;
use App\Models\Ops\OperationChecklist1;
use App\Models\Ops\OperationChecklist2;
use App\Models\Master\VehicleService;
use App\Models\Service\Service;
use App\Models\Master\Vehicle;
use App\Models\Master\Checklist;
use App\Models\Master\Driver;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PagesHelp;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\View;
use PDF;

class OperationCtrlController extends Controller
{
    public function show(Request $request){
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending=="true";
        $sortBy = $request->sortBy;
        $open = isset($request->open) ? $request->open : '1';
        $start_date = $request->start_date;
        $end_date = $request->end_date;
        $pool_code = PagesHelp::PoolCode($request);
        $data= OperationChecklist1::from('t_vehicle_checklist1 as a')
            ->selectRaw("a.sysid,a.pool_code,a.doc_number,a.vehicle_no,
                a.ref_date,a.ref_time,a.mechanic_id,a.valid_date,a.is_used,a.doc_ops,
                b.police_no,b.descriptions,c.personal_name AS mechanic_name,a.conclusion")
           ->leftJoin('m_vehicle as b','a.vehicle_no','=','b.vehicle_no')
           ->leftJoin('m_personal as c','a.mechanic_id','=','c.employee_id')
           ->where('a.pool_code',$pool_code);
        if ($open=='1') {
           $data=$data->where('a.is_used', '=',0)
           ->WhereRaw("a.valid_date >='".Date('Y-m-d')."'")
           ->whereRaw("a.conclusion='Layak Jalan'");
        } else {
           $data=$data->where('a.ref_date', '>=',$start_date)
           ->where('a.ref_date', '<=',$end_date);
        }
        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where(function($q) use ($filter) {
                    $q->where('a.doc_number','like',$filter)
                   ->orwhere('b.police_no','like',$filter)
                   ->orwhere('b.police_no','like',$filter)
                   ->orwhere('a.vehicle_no','like',$filter);
            });
        }
        if (!($sortBy=='')) {
            if ($sortBy=='police_no') {
                $sortBy="b.".$sortBy;
            } else if ($sortBy=='mechanic_name'){
                $sortBy="c.personal_name";
            } else {
                $sortBy="a.".$sortBy;
            }
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
        $id=$request->id;
        DB::beginTransaction();
        try {
            $ctrl=OperationControl::where('sysid',$id);
            if (!($ctrl==null)) {
                $ctrl->delete();
                OperationChecklist1::where('sysid',$id)->delete();
                OperationControllist::where('sysid',$id)->delete();
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
    public function get(Request $request){
        $id=isset($request->id) ? $request->id :'';
        $data['header']=OperationChecklist1::where('sysid',$id)->first();
        $data['detail']=OperationChecklist2::
          selectRaw('item_id as sysid,sort_number,descriptions,field_type,is_header,is_subheader,colidx,checked,notes,grpidx')
          ->where('sysid',$id)->get();
        return response()->success('Success',$data);
    }
    public function post(Request $request){
        $data= $request->json()->all();
        $opr=$data['operation'];
        $where=$data['where'];
        $header=$data['head'];
        $header['pool_code'] = PagesHelp::PoolCode($request);
        $detail=$data['detail'];

        $validator=Validator::make($header,[
            'ref_date'=>'bail|required',
            'pool_code'=>'bail|required',
            'ref_time'=>'bail|required',
            'vehicle_no'=>'bail|required',
            'valid_date'=>'bail|required',
            'conclusion'=>'bail|required',
            'mechanic_id'=>'bail|required'
        ],[
            'out_date.required'=>'Tanggal harus diisi',
            'pool_code.required'=>'Pool harus diisi',
            'ref_time.required'=>'Jam harus diisi',
            'vehicle_no.required'=>'Unit kendaraan harus diisi',
            'valid_date.required'=>'Masa berlaku harus diisi',
            'conclusion.required'=>'Kesimpulan harus diisi',
            'mechanic_id.required'=>'Mekanik harus diisi',
        ]);
        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }
        if (($header['pool_code'])=='N/A') {
            return response()->error('',501,'Error pemilihan pool, silahkan refresh atau login ulang');
        }
        if ($opr=='updated'){
            $info=OperationChecklist1::select('doc_number','ref_date','valid_date','is_used','doc_ops')->where($where)->first();
            if ($info){
                if ($info->is_used==1){
                    return response()->error('',501,"Dokumen layak jalan [".$info->doc_number."] sudah terpakai untuk SPJ ".$info->doc_ops);
                }
            }
        }
        if ($opr=='inserted'){
            $info=OperationChecklist1::select('doc_number','ref_date','valid_date','is_used','doc_ops')
            ->where('vehicle_no',$header['vehicle_no'])
            ->where('is_used',0)
            ->where('conclusion','Layak Jalan')
            ->where('valid_date','>=',Date('Y-m-d'))
            ->first();
            if ($info){
                if ($info->is_used==0){
                    return response()->error('',501,"Unit ".$header['vehicle_no']." masih memiliki surat SLJ yang masih berlaku");
                }
            }
            $vehicle=Vehicle::selectRaw('vehicle_status')->where('vehicle_no',$header['vehicle_no'])->first();
            if ($vehicle->vehicle_status=='Beroperasi') {
                return response()->error('',501,"Unit ".$header['vehicle_no']." masih dalam status beroperasi");
            } else if ($vehicle->vehicle_status=='Service') {
                return response()->error('',501,"Unit ".$header['vehicle_no']." sedang dalam perbaikan/service");
            }
        }
        DB::beginTransaction();
        try{
            $sysid=$header['sysid'];
            unset($header['sysid']);
            $header['update_userid'] =PagesHelp::UserID($request);
            $header['update_timestamp'] =Date('Y-m-d H:i:s');
            if ($opr=='updated'){
                $number=$header['doc_number'];
                OperationChecklist1::where($where)->update($header);
                OperationChecklist2::where('sysid',$sysid)->delete();
            } else if ($opr='inserted'){
                $number=OperationChecklist1::GenerateNumber($header['pool_code'],$header['ref_date']);
                $header['doc_number']=$number;
                $sysid=OperationChecklist1::insertGetId($header);
            }
            $row=array();
            foreach($detail as $record) {
                $row =(array)$record;
                $row['item_id']=$row['sysid'];
                $row['sysid']=$sysid;
                $dtl[] = $row;
            }
            OperationChecklist2::insert($dtl);
            if ($header['conclusion']=='Layak Jalan'){
               DB::update("UPDATE m_vehicle SET ops_permit=?,ops_permit_valid=?,vehicle_status='Siap' WHERE vehicle_no=?",
               [$number,$header['valid_date'],$header['vehicle_no']]);
            } else {
               DB::update("UPDATE m_vehicle SET ops_permit='',ops_permit_valid=NULL,vehicle_status='Checklist' WHERE vehicle_no=?",
               [$header['vehicle_no']]);
            }
            if ($header['conclusion']=='Perbaikan'){
                $docservice=OperationChecklist1::where('sysid',$sysid)->first();
                $userid = PagesHelp::UserID($request);
                $unit=Vehicle::select('police_no')->where('vehicle_no',$header['vehicle_no'])->first();
                if ($unit){
                    $policeno=$unit->police_no;
                } else {
                    $policeno='';
                }
                if (!($docservice)){
                    $docservice='';
                }
                $service=Service::where('doc_number',$docservice)->first();
                if ($service==null){
                    $docservice=Service::GenerateNumber($header['pool_code'],$header['ref_date']);
                    Service::insert([
                        'doc_number'=>$docservice,
                        'ref_date'=>$header['ref_date'],
                        'ref_time'=>$header['ref_time'],
                        'pool_code'=>$header['pool_code'],
                        'vehicle_no'=>$header['vehicle_no'],
                        'requester'=>'',
                        'problem'=>$header['recomendation'],
                        'status'=>'permintaan',
                        'police_no'=>$policeno,
                        'update_userid'=>$userid,
                        'update_timestamp'=>Date('Y-m-d'),
                        'service_type'=>'Checklist'
                    ]);
                } else {
                    Service::where('doc_number',$docservice)->update([
                        'ref_date'=>$header['ref_date'],
                        'ref_time'=>$header['ref_time'],
                        'pool_code'=>$header['pool_code'],
                        'vehicle_no'=>$header['vehicle_no'],
                        'requester'=>'',
                        'problem'=>$header['recomendation'],
                        'status'=>'permintaan',
                        'police_no'=>$policeno,
                        'update_userid'=>$userid,
                        'update_timestamp'=>Date('Y-m-d')
                    ]);
                }
                DB::update("UPDATE t_vehicle_checklist1 SET service=1,work_order=? WHERE sysid=?",[$docservice,$sysid]);
            }  else {
                DB::update("UPDATE t_vehicle_checklist1 SET service=0,work_order='-' WHERE sysid=?",[$sysid]);
            }
            DB::commit();
            return response()->success('Success','Simpan data Berhasil');
		} catch (Exception $e) {
            DB::rollback();
            return response()->error('',501,$e);
        }
    }

    public function getitem(Request $request){
        $data=Checklist::selectRaw('sysid,sort_number,descriptions,field_type,is_header,is_subheader,colidx,grpidx,checked,notes')
        ->where('is_active',1)->orderBy('sort_number','asc')->get();
        return response()->success('Success',$data);
    }

    public function print(Request $request){
        $sysid=$request->sysid;
        $header=OperationChecklist1::from('t_vehicle_checklist1 as a')
            ->selectRaw(" a.sysid,a.doc_number,a.vehicle_no,b.police_no,a.mechanic_id,a.ref_date,
            a.ref_time,a.valid_date,a.recomendation,a.conclusion,c.personal_name,
            CASE TRIM(IFNULL(a.conclusion,''))
            WHEN 'Layak Jalan' THEN 'KENDARAAN SIAP OPERASI'
            WHEN 'Perbaikan' THEN 'PERBAIKAN'
            ELSE '' END as conclusion,a.update_userid,a.update_timestamp")
            ->leftJoin('m_vehicle as b','a.vehicle_no','=','b.vehicle_no')
            ->leftJoin('m_personal as c','a.mechanic_id','=','c.employee_id')
            ->where('a.sysid',$sysid)->first();
        if (!($header==null)){
            $sign['mekanik']='';
            $home = storage_path().'/app/';
            $user=Driver::selectRaw("IFNULL(sign,'-') as sign")->where('employee_id',$header->mechanic_id)->first();
            if ($user) {
                if ($user->sign<>'-'){
                    $sign['mekanik']=$home.$user->sign;
                }
            }
            $header->ref_date=date_format(date_create($header->ref_date),'d-m-Y');
            $header->valid_date=date_format(date_create($header->valid_date),'d-m-Y');
            $header->update_timestamp=date_format(date_create($header->update_timestamp),'d-m-Y H:i');
            $detail=OperationChecklist2::where('sysid',$sysid)->get();
            $profile=PagesHelp::Profile();
            $pdf = PDF::loadView('workshop.SLJ',['header'=>$header,'detail'=>$detail,'sign'=>$sign,'profile'=>$profile])->setPaper('A4','potrait');
            return $pdf->stream();
        } else {
            return response()->error('',501,'Data Tidak ditemukan');
        }
    }

}
