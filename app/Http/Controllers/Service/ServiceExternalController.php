<?php

namespace App\Http\Controllers\Service;

use App\Models\Master\Vehicle;
use App\Models\Master\Partner;
use App\Models\Service\ServiceExternal;
use App\Models\Service\ServiceExternalJob;
use App\Models\Service\ServiceExternalDetail;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PagesHelp;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\View;
use PDF;

class ServiceExternalController extends Controller
{
    public function show(Request $request){
        $service_no = isset($request->service_no) ? $request->service_no:'-';
        $data= ServiceExternal::from('t_workorder_external as a')
        ->selectRaw("a.sysid,a.doc_number,a.ref_date,a.partner_id,a.partner_name,a.vehicle_no,
            a.police_no,a.notes,a.cost_estimation,a.date_estimation,a.time_estimation,a.is_closed,
            a.service_no,a.is_cancel,a.material_estimation")
        ->where('a.service_no',$service_no)
        ->where('a.is_cancel',0)->get();
        return response()->success('Success',$data);
    }

    public function destroy(Request $request){
        $id=isset($request->sysid) ? $request->sysid : -1;
        DB::beginTransaction();
        try {
            $ctrl=ServiceExternal::where('sysid',$id)->first();
            if ($ctrl) {
                if ($ctrl->is_closed=='1') {
                    DB::rollback();
                    return response()->error('',501,'Perbaikan keluar sudah selesai tidak bisa dibatalkan');
                } else {
                    ServiceExternal::where('sysid',$id)
                    ->update(['is_cancel'=>'1']);
                    DB::commit();
                    return response()->success('Success','Perbaikan keluar berhasil dibatalkan');
                }
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
        $id=isset($request->sysid) ? $request->sysid :'-1';
        $data['header']=ServiceExternal::from('t_workorder_external as a')
        ->selectRaw("a.sysid,a.doc_number,a.ref_date,a.partner_id,a.vehicle_no,
                    a.police_no,a.notes,a.cost_estimation,a.date_estimation,a.time_estimation,
                    a.is_closed,a.service_no,a.is_cancel,a.material_estimation")
        ->where('a.sysid',$id)->first();
        $data['detail']=ServiceExternalDetail::where('sysid',$id)->get();
        $data['jobs']=ServiceExternalJob::where('sysid',$id)->get();
        return response()->success('Success',$data);
    }
    public function post(Request $request){
        $data= $request->json()->all();
        $opr=$data['operation'];
        $where=$data['where'];
        $header=$data['data'];
        $detail=$data['detail'];
        $job=$data['jobs'];
        $header['pool_code'] = PagesHelp::PoolCode($request);

        $validator=Validator::make($header,[
            'partner_id'=>'bail|required',
            'ref_date'=>'bail|required',
            'date_estimation'=>'bail|required',
            'time_estimation'=>'bail|required',
            'vehicle_no'=>'bail|required',
            'service_no'=>'bail|required'
        ],[
            'partner_id.required'=>'Suplier/Bengkel harus diisi',
            'ref_date.required'=>'Tanggal harus diisi',
            'date_estimation.required'=>'Tanggal perkiraan selesai perbaikan harus diisi',
            'time_estimation.required'=>'Jam perkiraan selesai perbaikan harus diisi',
            'vehicle_no.required'=>'Nomor unit harus diisi',
            'service_no.required'=>'Nomor perbaikan harus diisi'
        ]);

        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }

        $validator=Validator::make($detail,[
            '*.item_code'=>'bail|required|distinct|exists:m_item,item_code',
            '*.qty'=>'bail|required|numeric|min:1',
            '*.price'=>'bail|required|numeric|min:1'
        ],[
            '*.item_code.required'=>'Kode barang harus diisi',
            '*.item_code.exists'=>'Kode barang :input tidak ditemukan dimaster',
            '*.qty.min'=>'Jumlah barang harus diisi/lebih besar dari NOL',
            '*.price.min'=>'Harga pembelian tidak boleh NOL',
            '*.item_code.distinct'=>'Kode barang :input terduplikasi (terinput lebih dari 1)',
        ]);
        $validator=Validator::make($job,[
            '*.item_code'=>'bail|required|distinct|exists:m_item,item_code',
            '*.qty'=>'bail|required|numeric|min:1',
            '*.price'=>'bail|required|numeric|min:1'
        ],[
            '*.item_code.required'=>'Kode barang harus diisi',
            '*.item_code.exists'=>'Kode barang :input tidak ditemukan dimaster',
            '*.qty.min'=>'Jumlah barang harus diisi/lebih besar dari NOL',
            '*.price.min'=>'Harga pembelian tidak boleh NOL',
            '*.item_code.distinct'=>'Kode barang :input terduplikasi (terinput lebih dari 1)',
        ]);

        $sysid=$header['sysid'];
        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }
        $external=ServiceExternal::selectRaw("sysid,doc_number,is_closed,invoice_number")->where('sysid',$sysid)->first();
        if ($external){
            if ($external->is_closed=="1"){
            return response()->error('',501,"Perbaikan keluar sudah selesai dengan nomor invoice ".$external->invoice_number);
            }
        }
        DB::beginTransaction();
        try{
            $header['update_userid'] = PagesHelp::UserID($request);
            $header['update_timestamp'] =new \DateTime();
            $header['update_userid'] = PagesHelp::UserID($request);
            unset($header['item_name']);
            $unit=Vehicle::select('police_no')->where('vehicle_no',$header['vehicle_no'])->first();
            if ($unit){
                $header['police_no']=$unit->police_no;
            }
            unset($header['sysid']);
            $Partner=Partner::select('partner_name')
                ->where('partner_id',$header['partner_id'])->first();
            if (!($Partner==null)){
                $header['partner_name']=$Partner->partner_name;
            }
            if ($opr=='updated'){
                ServiceExternal::where($where)->update($header);
                ServiceExternalDetail::where($where)->delete();
                ServiceExternalJob::where($where)->delete();
            } else if ($opr='inserted'){
                $number=ServiceExternal::GenerateNumber($header['pool_code'],$header['ref_date']);
                $header['doc_number']=$number;
                $sysid=ServiceExternal::insertGetId($header);
            }
            foreach($detail as $record){
                $dtl=(array)$record;
                unset($dtl['part_number']);
                $dtl['sysid']=$sysid;
                ServiceExternalDetail::insert($dtl);
            }
            foreach($job as $record){
                $dtl=(array)$record;
                $dtl['sysid']=$sysid;
                ServiceExternalJob::insert($dtl);
            }
            DB::commit();
            return response()->success('Success','Simpan data Berhasil');
		} catch (Exception $e) {
            DB::rollback();
            return response()->error('',501,$e);
        }
    }
}
