<?php

namespace App\Http\Controllers\Ops;

use App\Models\Ops\Accident;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use PagesHelp;
use PDF;


class AccidentController extends Controller
{
    public function show(Request $request){
        $filter = $request->filter;
        $limit = $request->limit;
        $descending = $request->descending=="true";
        $sortBy = $request->sortBy;
        $data= Accident::from('t_accident_document as a')
        ->selectRaw("a.sysid,a.doc_number,a.ref_date,a.ops_document,a.vehicle_no,a.police_no,
                   a.driver_id,b.personal_name as driver,a.accident_date,a.accident_location,a.notes,a.cost,a.office_cost,a.driver_cost")
        ->leftjoin('m_personal as b','a.driver_id','=','b.employee_id');
        if (!($filter=='')){
            $filter='%'.trim($filter).'%';
            $data=$data->where('a.pool_code','like',$filter)
               ->orwhere('a.accident_location','like',$filter)
               ->orwhere('a.vehicle_no','like',$filter)
               ->orwhere('a.police_no','like',$filter);
        }
        if ($descending) {
            $data=$data->orderBy($sortBy,'desc')->paginate($limit);
        } else {
            $data=$data->orderBy($sortBy,'asc')->paginate($limit);
        }
        return response()->success('Success',$data);
    }

    public function destroy(Request $request){
        $id=$request->id;
        $data=Accident::find($id);
        if (!($data==null)) {
            $data->delete();
            return response()->success('Success','Data berhasil dihapus');
        } else {
           return response()->error('',501,'Data tidak ditemukan');
        }
    }
    public function get(Request $request){
        $id=$request->id;
        $data=Accident::find($id);
        return response()->success('Success',$data);
    }
    public function post(Request $request){
        $data= $request->json()->all();
        $opr=$data['operation'];
        $where=$data['where'];
        $rec=$data['data'];
        $rec['pool_code']=PagesHelp::PoolCode($request);
        $validator=Validator::make($rec,[
            'pool_code'=>'bail|required',
            'ref_date'=>'bail|required',
            'vehicle_no'=>'bail|required',
            'driver_id'=>'bail|required',
            'accident_location'=>'bail|required',
            'accident_date'=>'bail|required'
        ],[
            'pool_code.required'=>'Kode Pool harus diisi',
            'accident_location.required'=>'Lokasi kejadian harus diisi',
            'accident_date.required'=>'Tanggal kejadian harus diisi',
            'driver_id.required'=>'Pengemudi harus diisi',
            'vehicle_no.required'=>'Nomor unit harus diisi'
        ]);
        if ($validator->fails()){
            return response()->error('',501,$validator->errors()->first());
        }

        try{
            if ($opr=='updated'){
                Accident::where($where)
                    ->update($rec);
            } else if ($opr='inserted'){
                unset($rec['sysid']);
                $number=Accident::GenerateNumber($rec['pool_code'],$rec['ref_date']);
                $rec['doc_number']=$number;
                Accident::insert($rec);
            }
            return response()->success('Success','Simpan data Berhasil');
		} catch (Exception $e) {
            return response()->error('',501,$e);
        }
    }
    public function print(Request $request){
        $sysid=$request->sysid;
        $data= Accident::from('t_accident_document as a')
        ->selectRaw("a.sysid,a.doc_number,a.ref_date,a.ops_document,a.vehicle_no,a.police_no,
                   a.driver_id,b.personal_name as driver,a.accident_date,a.accident_location,a.notes,a.cost,a.office_cost,a.driver_cost")
        ->leftjoin('m_personal as b','a.driver_id','=','b.employee_id')
        ->first();
        if ($data) {
            $data->ref_date=date_format(date_create($data->ref_date),'d-m-Y');
            $data->accident_date=date_format(date_create($data->accident_date),'d-m-Y');
            $profile=PagesHelp::Profile();
            $pdf = PDF::loadView('ops.Accident',['header'=>$data,'profile'=>$profile])->setPaper('A4','potriat');
            return $pdf->stream();
        } else {
            return response()->error('',501,'Data Tidak ditemukan');
        }
    }

}
